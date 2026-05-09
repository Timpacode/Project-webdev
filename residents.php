<?php
// Start session and include database connection
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get_resident':
            getResident($db);
            break;
        case 'add_resident':
            addResident($db);
            break;
        case 'update_resident':
            updateResident($db);
            break;
        case 'delete_resident':
            deleteResident($db);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

// Get all residents for display
$residents = [];
try {
    $query = "SELECT * FROM resident ORDER BY registration_date DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $residents = [];
}

// PHP Functions for AJAX handling
function getResident($db) {
    if (!isset($_GET['resident_id'])) {
        echo json_encode(['success' => false, 'message' => 'Resident ID required']);
        return;
    }
    
    try {
        $query = "SELECT * FROM resident WHERE resident_id = ? LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute([$_GET['resident_id']]);
        
        if ($stmt->rowCount() > 0) {
            $resident = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'resident' => $resident]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Resident not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function addResident($db) {
    if ($_POST) {
        try {
            // Generate resident code
            $resident_code = 'RES-' . date('Ymd') . '-' . rand(1000, 9999);
            
            $query = "INSERT INTO resident SET 
                     resident_code = ?, full_name = ?, email = ?, birthdate = ?, 
                     address = ?, contact_number = ?, family_count = ?, 
                     voter_status = ?, monthly_income = ?, occupation = ?, 
                     registration_date = NOW(), STATUS = 'active'";
            
            $stmt = $db->prepare($query);
            $success = $stmt->execute([
                $resident_code,
                $_POST['full_name'],
                $_POST['email'] ?? '',
                $_POST['birthdate'] ?? null,
                $_POST['address'] ?? '',
                $_POST['contact_number'],
                $_POST['family_count'] ?? 1,
                $_POST['voter_status'] ?? 'no',
                $_POST['monthly_income'] ?? 0,
                $_POST['occupation'] ?? ''
            ]);
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Resident added successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add resident']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }
}

function updateResident($db) {
    if ($_POST && isset($_POST['resident_id'])) {
        try {
            $query = "UPDATE resident SET 
                     full_name = ?, email = ?, birthdate = ?, address = ?, 
                     contact_number = ?, family_count = ?, voter_status = ?, 
                     monthly_income = ?, occupation = ?, STATUS = ?,
                     updated_at = NOW()
                     WHERE resident_id = ?";
            
            $stmt = $db->prepare($query);
            $success = $stmt->execute([
                $_POST['full_name'],
                $_POST['email'] ?? '',
                $_POST['birthdate'] ?? null,
                $_POST['address'] ?? '',
                $_POST['contact_number'],
                $_POST['family_count'] ?? 1,
                $_POST['voter_status'] ?? 'no',
                $_POST['monthly_income'] ?? 0,
                $_POST['occupation'] ?? '',
                $_POST['status'] ?? 'active',
                $_POST['resident_id']
            ]);
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Resident updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update resident']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }
}

function deleteResident($db) {
    if ($_POST && isset($_POST['resident_id'])) {
        try {
            $query = "UPDATE resident SET STATUS = 'inactive' WHERE resident_id = ?";
            $stmt = $db->prepare($query);
            $success = $stmt->execute([$_POST['resident_id']]);
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Resident deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete resident']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }
}
?>

<?php 
// Set the current page for the header sidebar active class
$_SESSION['current_page'] = 'residents.php';
include '../includes/header.php'; 
?>

<div class="main-content">
    <div class="content-section">
        <!-- Residents Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Residents Management</h3>
                <div>
                    <button class="btn btn-primary" id="addResidentBtn">
                        <i class="fas fa-plus"></i> Add New Resident
                    </button>
                    <button class="btn btn-outline" id="exportResidentsBtn">
                        <i class="fas fa-file-export"></i> Export CSV
                    </button>
                </div>
            </div>
            
            <div class="card-body">
                <p class="mb-20">Manage resident information and records</p>
                
                <div class="mb-20">
                    <div class="search-box">
                        <input type="text" class="form-control" id="residentSearch" 
                               placeholder="Search residents by name, ID, or address">
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Resident ID</th>
                                <th>Name</th>
                                <th>Address</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($residents as $resident): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($resident['resident_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($resident['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($resident['address']); ?></td>
                                <td><?php echo htmlspecialchars($resident['contact_number']); ?></td>
                                <td>
                                    <span class="status status-<?php echo strtolower($resident['STATUS'] ?? 'active'); ?>">
                                        <?php echo ucfirst($resident['STATUS'] ?? 'Active'); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="resident-actions">
                                        <button class="btn btn-warning edit-btn" 
                                                data-id="<?php echo $resident['resident_id']; ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-danger delete-btn" 
                                                data-id="<?php echo $resident['resident_id']; ?>">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="residentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add New Resident</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="residentForm">
                <div class="modal-body">
                    <input type="hidden" id="residentId" name="resident_id">
                    
                    <div class="form-section">
                        <div class="form-section-title">Personal Information</div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="fullName" name="full_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Birthdate</label>
                                    <input type="date" class="form-control" id="birthdate" name="birthdate">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Contact Number *</label>
                                    <input type="text" class="form-control" id="contactNumber" name="contact_number" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title">Address Information</div>
                        <div class="form-group">
                            <label class="form-label">Complete Address *</label>
                            <textarea class="form-control" id="address" name="address" rows="3" required></textarea>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title">Additional Information</div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label">Family Count</label>
                                    <input type="number" class="form-control" id="familyCount" name="family_count" value="1" min="1">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label">Voter Status</label>
                                    <select class="form-control" id="voterStatus" name="voter_status">
                                        <option value="yes">Yes</option>
                                        <option value="no" selected>No</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label">Monthly Income</label>
                                    <input type="number" class="form-control" id="monthlyIncome" name="monthly_income" value="0" step="0.01" min="0">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Occupation</label>
                                    <input type="text" class="form-control" id="occupation" name="occupation">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Status</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="active" selected>Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveBtn">
                        <span class="spinner-border spinner-border-sm" id="saveSpinner" style="display: none;"></span>
                        Save Resident
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// SIMPLE FIX - Clean JavaScript without conflicts
document.addEventListener('DOMContentLoaded', function() {
    console.log('Page loaded successfully');
    
    // Add Resident Button - SIMPLE CLICK HANDLER
    document.getElementById('addResidentBtn').onclick = function() {
        console.log('Add button clicked');
        showModal('Add New Resident');
    };
    
    // Export Button
    document.getElementById('exportResidentsBtn').onclick = function() {
        alert('Export functionality would be implemented here!');
    };
    
    // Search functionality
    document.getElementById('residentSearch').oninput = function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    };
    
    // Edit buttons - Event delegation
    document.addEventListener('click', function(e) {
        if (e.target.closest('.edit-btn')) {
            const btn = e.target.closest('.edit-btn');
            const residentId = btn.getAttribute('data-id');
            console.log('Edit clicked for:', residentId);
            editResident(residentId);
        }
        
        if (e.target.closest('.delete-btn')) {
            const btn = e.target.closest('.delete-btn');
            const residentId = btn.getAttribute('data-id');
            const residentName = btn.closest('tr').querySelector('td:nth-child(2)').textContent;
            console.log('Delete clicked for:', residentName);
            deleteResident(residentId, residentName);
        }
    });
    
    // Form submission
    document.getElementById('residentForm').onsubmit = function(e) {
        e.preventDefault();
        saveResident();
    };
});

function showModal(title, resident = null) {
    console.log('Showing modal:', title);
    
    // Reset form
    document.getElementById('residentForm').reset();
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('residentId').value = '';
    
    if (resident) {
        // Fill form with resident data
        document.getElementById('residentId').value = resident.resident_id;
        document.getElementById('fullName').value = resident.full_name || '';
        document.getElementById('email').value = resident.email || '';
        document.getElementById('birthdate').value = resident.birthdate || '';
        document.getElementById('contactNumber').value = resident.contact_number || '';
        document.getElementById('address').value = resident.address || '';
        document.getElementById('familyCount').value = resident.family_count || '1';
        document.getElementById('voterStatus').value = resident.voter_status || 'no';
        document.getElementById('monthlyIncome').value = resident.monthly_income || '0';
        document.getElementById('occupation').value = resident.occupation || '';
        document.getElementById('status').value = resident.STATUS || 'active';
    } else {
        // Set default values for new resident
        document.getElementById('familyCount').value = '1';
        document.getElementById('voterStatus').value = 'no';
        document.getElementById('monthlyIncome').value = '0';
        document.getElementById('status').value = 'active';
    }
    
    // Show modal using Bootstrap
    const modal = new bootstrap.Modal(document.getElementById('residentModal'));
    modal.show();
}

function editResident(residentId) {
    console.log('Editing resident:', residentId);
    toggleLoading(true);
    
    // Simple fetch request
    fetch('residents.php?action=get_resident&resident_id=' + residentId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showModal('Edit Resident', data.resident);
            } else {
                alert('Error: ' + (data.message || 'Resident not found'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading resident data');
        })
        .finally(() => toggleLoading(false));
}

function deleteResident(residentId, residentName) {
    if (confirm('Are you sure you want to delete ' + residentName + '?')) {
        const formData = new FormData();
        formData.append('resident_id', residentId);
        
        fetch('residents.php?action=delete_resident', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Resident deleted successfully!');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Delete failed'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting resident');
        });
    }
}

function saveResident() {
    const formData = new FormData(document.getElementById('residentForm'));
    const isEdit = document.getElementById('residentId').value !== '';
    const action = isEdit ? 'update_resident' : 'add_resident';
    
    console.log('Saving resident, action:', action);
    toggleLoading(true);
    
    fetch('residents.php?action=' + action, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Resident ' + (isEdit ? 'updated' : 'added') + ' successfully!');
            // Hide modal properly
            const modal = bootstrap.Modal.getInstance(document.getElementById('residentModal'));
            modal.hide();
            // Reload page after a delay
            setTimeout(() => location.reload(), 1000);
        } else {
            alert('Error: ' + (data.message || 'Save failed'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error saving resident');
    })
    .finally(() => toggleLoading(false));
}

function toggleLoading(show) {
    const saveBtn = document.getElementById('saveBtn');
    const spinner = document.getElementById('saveSpinner');
    
    if (show) {
        saveBtn.disabled = true;
        spinner.style.display = 'inline-block';
        saveBtn.innerHTML = 'Saving...';
    } else {
        saveBtn.disabled = false;
        spinner.style.display = 'none';
        saveBtn.innerHTML = 'Save Resident';
    }
}
</script>

<?php include '../includes/footer.php'; ?>
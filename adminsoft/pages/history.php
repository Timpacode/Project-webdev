<?php
include '../includes/header.php';

// Get current page and rows per page
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['rows']) ? intval($_GET['rows']) : 20; // Changed from 10 to 20 to match residents
$offset = ($current_page - 1) * $limit;

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$document_type_filter = isset($_GET['document_type']) ? $_GET['document_type'] : 'all';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get all requests including completed, rejected, and approved
$requests = [];
$total_count = 0;

try {
    // Build WHERE conditions for filters
    $where_conditions = ["r.status IN ('completed', 'rejected', 'approved')", "r.request_code NOT LIKE 'DOC-%'"];
    $params = [];
    
    if ($status_filter !== 'all') {
        $where_conditions[] = "r.status = :status";
        $params[':status'] = $status_filter;
    }
    
    if ($document_type_filter !== 'all') {
        $where_conditions[] = "dt.name = :document_type";
        $params[':document_type'] = $document_type_filter;
    }
    
    if (!empty($search_query)) {
        $where_conditions[] = "(r.resident_name LIKE :search OR r.request_code LIKE :search OR r.resident_email LIKE :search)";
        $params[':search'] = "%$search_query%";
    }
    
    $where_clause = implode(' AND ', $where_conditions);

    // First, get total count for pagination
    $count_query = "SELECT COUNT(*) as total 
                    FROM request r 
                    JOIN document_type dt ON r.document_type_id = dt.type_id 
                    LEFT JOIN admin a ON r.processed_by = a.admin_id
                    WHERE $where_clause";
    
    $count_stmt = $db->prepare($count_query);
    foreach ($params as $key => $value) {
        $count_stmt->bindValue($key, $value);
    }
    $count_stmt->execute();
    $total_count = $count_stmt->fetchColumn();

    // Then get paginated results
    $query = "SELECT r.request_id, r.request_code, r.status, r.resident_name, 
                     dt.name as document_type, r.request_date, r.processed_date,
                     a.username as admin_name, r.rejection_reason
              FROM request r 
              JOIN document_type dt ON r.document_type_id = dt.type_id 
              LEFT JOIN admin a ON r.processed_by = a.admin_id
              WHERE $where_clause
              ORDER BY r.request_date DESC
              LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $requests = [];
}

$total_pages = ceil($total_count / $limit);
$start_count = $total_count > 0 ? $offset + 1 : 0;
$end_count = min($offset + $limit, $total_count);

// Build query parameters for pagination links
$pagination_params = [];
if (!empty($search_query)) {
    $pagination_params['search'] = $search_query;
}
if ($status_filter !== 'all') {
    $pagination_params['status'] = $status_filter;
}
if ($document_type_filter !== 'all') {
    $pagination_params['document_type'] = $document_type_filter;
}
if ($limit != 20) {
    $pagination_params['rows'] = $limit;
}

// Function to generate URL with maintained filters
function generatePageUrl($page, $current_params) {
    $params = $current_params;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request History - BarangayHub</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Main Container */
        .history-container {
            max-width: 100%;
            margin: 0;
            padding: 30px;
            width: 100%;
            min-height: calc(100vh - 120px);
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        
        /* Stats Section */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            margin-bottom: 35px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 30px 25px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            text-align: center;
            border-left: 5px solid var(--primary);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), #6c757d);
            opacity: 0.7;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        
        .stat-label {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-value {
            font-size: 42px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 5px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Content Card */
        .content-card {
            background: linear-gradient(135deg, #ffffff 0%, #fafbfc 100%);
            border-radius: 16px;
            box-shadow: 0 4px 25px rgba(0,0,0,0.1);
            overflow: hidden;
            min-height: 600px;
            display: flex;
            flex-direction: column;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .card-header {
            padding: 30px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            background: rgba(248,249,250,0.8);
            backdrop-filter: blur(10px);
        }
        
        .card-title {
            margin: 0;
            font-size: 1.75rem;
            font-weight: 700;
            color: #2c3e50;
            position: relative;
        }
        
        .card-title::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 50px;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), #6c757d);
            border-radius: 2px;
        }
        
        .filter-controls {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-select, .search-input {
            padding: 12px 18px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            background: white;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .filter-select {
            min-width: 160px;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        }
        
        .search-input {
            min-width: 280px;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        }
        
        .filter-select:focus, .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 4px 15px rgba(0,123,255,0.15);
            transform: translateY(-1px);
        }
        
        /* Table Styles */
       .table-container {
    overflow-x: auto;
    width: 100%;
    flex: 1;
    background: white;
    /* Hide scrollbar for Chrome, Safari and Opera */
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE and Edge */
}
        .table-container::-webkit-scrollbar {
    display: none;
}
 .history-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    min-height: 400px;
    /* Ensure table can still be scrolled */
    min-width: 800px; /* Minimum width to ensure content doesn't get too squeezed */
}
        
        .history-table th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-weight: 700;
            color: #495057;
            font-size: 0.9rem;
            position: sticky;
            top: 0;
            padding: 20px;
            border-bottom: 2px solid #dee2e6;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .history-table td {
            padding: 18px 20px;
            text-align: left;
            border-bottom: 1px solid #f1f3f4;
            transition: all 0.2s ease;
        }
        
        .history-table tbody tr {
            background: white;
            transition: all 0.3s ease;
        }
        
        .history-table tbody tr:hover {
            background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 100%);
            transform: scale(1.01);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        /* Status Badges */
        .status {
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .status-completed { 
            background: linear-gradient(135deg, #d4edda 0%, #c8e6c9 100%); 
            color: #155724; 
            border: 1px solid #c8e6c9;
        }
        .status-approved { 
            background: linear-gradient(135deg, #d1ecf1 0%, #a8e6cf 100%); 
            color: #0c5460; 
            border: 1px solid #a8e6cf;
        }
        .status-rejected { 
            background: linear-gradient(135deg, #f8d7da 0%, #ffcdd2 100%); 
            color: #721c24; 
            border: 1px solid #ffcdd2;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 40px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .empty-state h4 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: #495057;
        }
        
        .empty-state p {
            font-size: 1rem;
            opacity: 0.7;
        }
        
        /* Results Count */
        .results-count {
            margin-bottom: 15px;
            color: #6c757d;
            font-size: 0.9rem;
            padding: 0 30px;
            font-weight: 600;
        }
        
        /* Pagination Styles - Matching Residents.php */
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-top: 1px solid #e9ecef;
            background: #f8f9fa;
        }

        .pagination-info {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .pagination-controls {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .pagination-btn {
            padding: 8px 16px;
            border: 2px solid #6c757d;
            border-radius: 8px;
            background: white;
            color: #6c757d;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .pagination-btn:hover {
            background: #6c757d;
            color: white;
            transform: translateY(-1px);
        }

        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-btn.disabled:hover {
            background: white;
            color: #6c757d;
            transform: none;
        }

        .page-indicator {
            padding: 8px 16px;
            background: white;
            border-radius: 8px;
            font-weight: 600;
            color: #495057;
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .history-container {
                padding: 25px;
            }
            
            .stats-container {
                gap: 20px;
            }
        }
        
        @media (max-width: 1024px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .card-header {
                padding: 25px;
            }
        }
        
        @media (max-width: 768px) {
            .history-container {
                padding: 20px 15px;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .stat-card {
                padding: 25px 20px;
            }
            
            .stat-value {
                font-size: 36px;
            }
            
            .card-header {
                flex-direction: column;
                align-items: stretch;
                padding: 20px;
                gap: 15px;
            }
            
            .filter-controls {
                width: 100%;
            }
            
            .filter-select, .search-input {
                flex: 1;
                min-width: unset;
                width: 100%;
            }
            
            .history-table th,
            .history-table td {
                padding: 15px 12px;
                font-size: 0.85rem;
            }
            
            .pagination {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
                text-align: center;
            }
            
            .pagination-controls {
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .history-container {
                padding: 15px 10px;
            }
            
            .stat-card {
                padding: 20px 15px;
            }
            
            .stat-value {
                font-size: 32px;
            }
            
            .card-title {
                font-size: 1.5rem;
            }
            
            .history-table {
                font-size: 0.8rem;
            }
            
            .history-table th,
            .history-table td {
                padding: 12px 8px;
            }
            
            .pagination-btn {
                padding: 8px 15px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="history-container">
        <!-- Stats Section -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-label">Total Completed</div>
                <div class="stat-value">
                    <?php 
                    try {
                        $completed_stmt = $db->prepare("SELECT COUNT(*) FROM request WHERE status = 'completed' AND request_code NOT LIKE 'DOC-%'");
                        $completed_stmt->execute();
                        echo $completed_stmt->fetchColumn();
                    } catch (PDOException $e) {
                        echo '0';
                    }
                    ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Approved</div>
                <div class="stat-value">
                    <?php 
                    try {
                        $approved_stmt = $db->prepare("SELECT COUNT(*) FROM request WHERE status = 'approved' AND request_code NOT LIKE 'DOC-%'");
                        $approved_stmt->execute();
                        echo $approved_stmt->fetchColumn();
                    } catch (PDOException $e) {
                        echo '0';
                    }
                    ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Rejected</div>
                <div class="stat-value">
                    <?php 
                    try {
                        $rejected_stmt = $db->prepare("SELECT COUNT(*) FROM request WHERE status = 'rejected' AND request_code NOT LIKE 'DOC-%'");
                        $rejected_stmt->execute();
                        echo $rejected_stmt->fetchColumn();
                    } catch (PDOException $e) {
                        echo '0';
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <!-- History Table -->
        <div class="content-card">
            <div class="card-header">
                <h3 class="card-title">Request History</h3>
                <div class="filter-controls">
                    <select class="filter-select" id="history-status-filter">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                    <select class="filter-select" id="history-document-type-filter">
                        <option value="all" <?php echo $document_type_filter === 'all' ? 'selected' : ''; ?>>All Document Types</option>
                        <option value="Barangay Clearance" <?php echo $document_type_filter === 'Barangay Clearance' ? 'selected' : ''; ?>>Barangay Clearance</option>
                        <option value="Certificate of Residency" <?php echo $document_type_filter === 'Certificate of Residency' ? 'selected' : ''; ?>>Certificate of Residency</option>
                        <option value="Certificate of Indigency" <?php echo $document_type_filter === 'Certificate of Indigency' ? 'selected' : ''; ?>>Certificate of Indigency</option>
                    </select>
                    <input type="text" class="search-input" id="history-search" placeholder="Search requests..." value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
            </div>
            
            <!-- Results Count -->
            <div class="results-count">
                Showing <span id="history-start"><?php echo $start_count; ?></span>-<span id="history-end"><?php echo $end_count; ?></span> of <span id="history-total"><?php echo $total_count; ?></span> requests
                <?php if (!empty($search_query)): ?>
                    for "<?php echo htmlspecialchars($search_query); ?>"
                <?php endif; ?>
            </div>
            
            <div class="table-container">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Request Code</th>
                            <th>Document Type</th>
                            <th>Resident</th>
                            <th>Status</th>
                            <th>Processed By</th>
                            <th>Date Processed</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): 
                            $status = strtolower($request['status']);
                            $notes = '';
                            
                            // Determine notes based on status
                            switch($status) {
                                case 'completed':
                                    $notes = 'Document ready for pickup';
                                    break;
                                case 'approved':
                                    $notes = 'Request approved and in process';
                                    break;
                                case 'rejected':
                                    $notes = $request['rejection_reason'] ?: 'Request rejected';
                                    break;
                                default:
                                    $notes = 'Status updated';
                            }
                        ?>
                        <tr class="history-row" data-status="<?php echo $status; ?>" data-document-type="<?php echo htmlspecialchars($request['document_type']); ?>">
                            <td style="font-weight: 700; color: #007bff;"><?php echo htmlspecialchars($request['request_code']); ?></td>
                            <td><?php echo htmlspecialchars($request['document_type']); ?></td>
                            <td style="font-weight: 600; color: #495057;"><?php echo htmlspecialchars($request['resident_name']); ?></td>
                            <td>
                                <span class="status status-<?php echo $status; ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </td>
                            <td style="color: #6c757d;"><?php echo htmlspecialchars($request['admin_name'] ?: 'System'); ?></td>
                            <td style="color: #6c757d;"><?php echo date('M j, Y g:i A', strtotime($request['processed_date'] ?: $request['request_date'])); ?></td>
                            <td style="color: #6c757d; max-width: 200px;" title="<?php echo htmlspecialchars($notes); ?>">
                                <?php echo htmlspecialchars($notes); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <h4>No History Found</h4>
                                        <p>When requests are processed, they will appear here for tracking.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination - Matching Residents.php Style -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <div class="pagination-info">
                    Showing <?php echo min($limit, count($requests)); ?> of <?php echo $total_count; ?> requests
                    <?php if (!empty($search_query)): ?>
                        for "<?php echo htmlspecialchars($search_query); ?>"
                    <?php endif; ?>
                </div>
                <div class="pagination-controls">
                    <?php 
                    // Previous page link
                    if ($current_page > 1): 
                        $prev_params = array_merge($pagination_params, ['page' => $current_page - 1]);
                        $prev_url = '?' . http_build_query($prev_params);
                    ?>
                        <a href="<?php echo $prev_url; ?>" class="pagination-btn">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php else: ?>
                        <span class="pagination-btn disabled">
                            <i class="fas fa-chevron-left"></i> Previous
                        </span>
                    <?php endif; ?>
                    
                    <span class="page-indicator">
                        Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                    </span>
                    
                    <?php 
                    // Next page link
                    if ($current_page < $total_pages): 
                        $next_params = array_merge($pagination_params, ['page' => $current_page + 1]);
                        $next_url = '?' . http_build_query($next_params);
                    ?>
                        <a href="<?php echo $next_url; ?>" class="pagination-btn">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="pagination-btn disabled">
                            Next <i class="fas fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

<script>
function updateRowsPerPage(rows) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('rows', rows);
    urlParams.set('page', '1'); // Reset to first page when changing rows
    window.location.href = '?' + urlParams.toString();
}

function applyFilters() {
    const urlParams = new URLSearchParams(window.location.search);
    
    // Get current filter values
    const statusFilter = document.getElementById('history-status-filter');
    const documentTypeFilter = document.getElementById('history-document-type-filter');
    const searchInput = document.getElementById('history-search');
    
    // Update URL parameters with current filter values
    if (statusFilter && statusFilter.value !== 'all') {
        urlParams.set('status', statusFilter.value);
    } else {
        urlParams.delete('status');
    }
    
    if (documentTypeFilter && documentTypeFilter.value !== 'all') {
        urlParams.set('document_type', documentTypeFilter.value);
    } else {
        urlParams.delete('document_type');
    }
    
    if (searchInput && searchInput.value.trim() !== '') {
        urlParams.set('search', searchInput.value.trim());
    } else {
        urlParams.delete('search');
    }
    
    // Always reset to page 1 when applying new filters
    urlParams.set('page', '1');
    
    // Reload page with new filters
    window.location.href = '?' + urlParams.toString();
}

function initFiltersFromURL() {
    const urlParams = new URLSearchParams(window.location.search);
    
    // Set status filter
    const statusFilter = document.getElementById('history-status-filter');
    const urlStatus = urlParams.get('status');
    if (statusFilter && urlStatus) {
        statusFilter.value = urlStatus;
    }
    
    // Set document type filter
    const documentTypeFilter = document.getElementById('history-document-type-filter');
    const urlDocumentType = urlParams.get('document_type');
    if (documentTypeFilter && urlDocumentType) {
        documentTypeFilter.value = urlDocumentType;
    }
    
    // Set search input
    const searchInput = document.getElementById('history-search');
    const urlSearch = urlParams.get('search');
    if (searchInput && urlSearch) {
        searchInput.value = urlSearch;
    }
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Function to update stats counts from server-side data
function updateStatsCounts() {
    // These values are set by PHP in the HTML
    const completedCount = <?php 
        try {
            $completed_stmt = $db->prepare("SELECT COUNT(*) FROM request WHERE status = 'completed' AND request_code NOT LIKE 'DOC-%'");
            $completed_stmt->execute();
            echo $completed_stmt->fetchColumn();
        } catch (PDOException $e) {
            echo '0';
        }
    ?>;
    
    const approvedCount = <?php 
        try {
            $approved_stmt = $db->prepare("SELECT COUNT(*) FROM request WHERE status = 'approved' AND request_code NOT LIKE 'DOC-%'");
            $approved_stmt->execute();
            echo $approved_stmt->fetchColumn();
        } catch (PDOException $e) {
            echo '0';
        }
    ?>;
    
    const rejectedCount = <?php 
        try {
            $rejected_stmt = $db->prepare("SELECT COUNT(*) FROM request WHERE status = 'rejected' AND request_code NOT LIKE 'DOC-%'");
            $rejected_stmt->execute();
            echo $rejected_stmt->fetchColumn();
        } catch (PDOException $e) {
            echo '0';
        }
    ?>;
    
    // Update the DOM elements
    const statCards = document.querySelectorAll('.stat-value');
    if (statCards.length >= 3) {
        statCards[0].textContent = completedCount;
        statCards[1].textContent = approvedCount;
        statCards[2].textContent = rejectedCount;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize filters from URL parameters
    initFiltersFromURL();
    
    // Update stats counts with correct totals from database
    updateStatsCounts();
    
    // Filter and search elements
    const statusFilter = document.getElementById('history-status-filter');
    const documentTypeFilter = document.getElementById('history-document-type-filter');
    const searchInput = document.getElementById('history-search');
    
    // Initialize event listeners
    function initEventListeners() {
        if (statusFilter) {
            statusFilter.addEventListener('change', applyFilters);
        }
        
        if (documentTypeFilter) {
            documentTypeFilter.addEventListener('change', applyFilters);
        }
        
        if (searchInput) {
            searchInput.addEventListener('input', debounce(applyFilters, 500));
        }
    }

    // Initialize
    initEventListeners();
});
</script>
</body>
</html>

<?php include '../includes/footer.php'; ?>
<?php
// Added: DB connection moved here from search_residents.php
require_once '../config/database.php';
require_once '../config/auth.php';

session_start();
error_log("=== SESSION DEBUG ===");
error_log("Session ID: " . session_id());
error_log("Session data: " . print_r($_SESSION, true));

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$auth->checkAuth();
$currentUser = $auth->getCurrentUser();
$current_page = basename($_SERVER['PHP_SELF']);

// Get current user role from session
$current_user_role = $_SESSION['role'] ?? 'Staff';

// Handle delete resident - Only allow if not staff
if (isset($_GET['delete_id']) && $current_user_role !== 'Staff') {
    $delete_id = $_GET['delete_id'];
    $query = "DELETE FROM resident WHERE resident_id = ?";
    $stmt = $db->prepare($query);
    if ($stmt->execute([$delete_id])) {
        $_SESSION['success_message'] = "Resident deleted successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to delete resident.";
    }
    session_write_close();
    header("Location: residents.php");
    exit();
} elseif (isset($_GET['delete_id']) && $current_user_role === 'Staff') {
    $_SESSION['error_message'] = "Staff members cannot delete residents. Please contact your administrator.";
    session_write_close();
    header("Location: residents.php");
    exit();
}

// Handle edit resident - Only allow if not staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_resident']) && $current_user_role !== 'Staff') {
    $resident_id = $_POST['resident_id'];
    $full_name = $_POST['full_name'];
    $email = $_POST['email'] ?? '';
    $birthdate = $_POST['birthdate'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $civil_status = $_POST['civil_status'] ?? 'single';
    $address = $_POST['address'];
    $year_of_residency = $_POST['year_of_residency'] ?? '';
    $contact_number = $_POST['contact_number'];
    $emergency_contact = $_POST['emergency_contact'] ?? '';
    $emergency_number = $_POST['emergency_number'] ?? '';
    $family_count = $_POST['family_count'] ?? 0;
    $voter_status = $_POST['voter_status'] ?? 'no';
    $senior_citizen_status = $_POST['senior_citizen_status'] ?? 'no';
    $monthly_income = $_POST['monthly_income'] ?? 0.00;
    $occupation = $_POST['occupation'] ?? '';
    $status = $_POST['status'] ?? 'active';

    $query = "UPDATE resident SET 
                full_name = ?, email = ?, birthdate = ?, gender = ?, civil_status = ?, 
                address = ?, year_of_residency = ?, contact_number = ?, emergency_contact = ?, emergency_number = ?, 
                family_count = ?, voter_status = ?, senior_citizen_status = ?, 
                monthly_income = ?, occupation = ?, status = ?, updated_at = NOW() 
              WHERE resident_id = ?";
    
    $stmt = $db->prepare($query);
    if ($stmt->execute([$full_name, $email, $birthdate, $gender, $civil_status, 
                       $address, $year_of_residency, $contact_number, $emergency_contact, $emergency_number,
                       $family_count, $voter_status, $senior_citizen_status, 
                       $monthly_income, $occupation, $status, $resident_id])) {
        $_SESSION['success_message'] = "Resident updated successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to update resident.";
    }
    session_write_close();
    header("Location: residents.php");
    exit();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_resident']) && $current_user_role === 'Staff') {
    $_SESSION['error_message'] = "Staff members cannot edit residents. Please contact your administrator.";
    session_write_close();
    header("Location: residents.php");
    exit();
}

// Handle add resident - Only allow if not staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_resident']) && $current_user_role !== 'Staff') {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'] ?? '';
    $birthdate = $_POST['birthdate'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $civil_status = $_POST['civil_status'] ?? 'single';
    $address = $_POST['address'];
    $year_of_residency = $_POST['year_of_residency'] ?? '';
    $contact_number = $_POST['contact_number'];
    $emergency_contact = $_POST['emergency_contact'] ?? '';
    $emergency_number = $_POST['emergency_number'] ?? '';
    $family_count = $_POST['family_count'] ?? 0;
    $voter_status = $_POST['voter_status'] ?? 'no';
    $senior_citizen_status = $_POST['senior_citizen_status'] ?? 'no';
    $monthly_income = $_POST['monthly_income'] ?? 0.00;
    $occupation = $_POST['occupation'] ?? '';
    $status = $_POST['status'] ?? 'active';
    
    $resident_code = 'RES-' . date('YmdHis') . rand(100, 999);
    
    $query = "INSERT INTO resident (
                resident_code, full_name, email, birthdate, gender, civil_status, 
                address, year_of_residency, contact_number, emergency_contact, emergency_number, 
                family_count, voter_status, senior_citizen_status, monthly_income, 
                occupation, status, registration_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $db->prepare($query);
    if ($stmt->execute([$resident_code, $full_name, $email, $birthdate, $gender, $civil_status,
                       $address, $year_of_residency, $contact_number, $emergency_contact, $emergency_number,
                       $family_count, $voter_status, $senior_citizen_status, $monthly_income,
                       $occupation, $status])) {
        $_SESSION['success_message'] = "Resident added successfully! Resident Code: $resident_code";
    } else {
        $_SESSION['error_message'] = "Failed to add resident.";
    }
    session_write_close();
    header("Location: residents.php");
    exit();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_resident']) && $current_user_role === 'Staff') {
    $_SESSION['error_message'] = "Staff members cannot add residents. Please contact your administrator.";
    session_write_close();
    header("Location: residents.php");
    exit();
}

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Search setup
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_conditions = [];
$query_params = [];

// Apply search filter
if (!empty($search)) {
    $where_conditions[] = "(full_name LIKE :search OR contact_number LIKE :search OR address LIKE :search OR resident_code LIKE :search OR email LIKE :search)";
    $query_params[':search'] = "%$search%";
}

// Build final WHERE clause
$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination with search
$total_query = "SELECT COUNT(*) as total FROM resident $where_clause";
$total_stmt = $db->prepare($total_query);
foreach ($query_params as $key => $value) {
    $total_stmt->bindValue($key, $value);
}
$total_stmt->execute();
$total_residents = (int)$total_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = (int)ceil($total_residents / $limit);

// Fetch residents with pagination, search, and age calculation
$query = "SELECT *, 
          TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) - 
          (DATE_FORMAT(CURDATE(), '%m%d') < DATE_FORMAT(birthdate, '%m%d')) AS age 
          FROM resident 
          $where_clause
          ORDER BY created_at DESC 
          LIMIT :limit OFFSET :offset";
          
$stmt = $db->prepare($query);

// Bind search parameters
foreach ($query_params as $key => $value) {
    $stmt->bindValue($key, $value);
}

// Bind pagination parameters
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$residents = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AdminSoft - Barangay Management System</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    /* Main Container */
    .residents-container {
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
        grid-template-columns: repeat(4, 1fr);
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
    
    /* Enhanced Content Card */
    .content-card {
        background: linear-gradient(135deg, #ffffff 0%, #fafbfc 100%);
        border-radius: 20px;
        box-shadow: 0 8px 40px rgba(0,0,0,0.12);
        overflow: hidden;
        min-height: 600px;
        display: flex;
        flex-direction: column;
        border: 1px solid rgba(255,255,255,0.8);
        backdrop-filter: blur(10px);
    }
    
    /* Enhanced Card Header */
    .card-header {
        padding: 30px 35px;
        border-bottom: 1px solid rgba(0,0,0,0.08);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 25px;
        background: linear-gradient(135deg, rgba(248,249,250,0.95) 0%, rgba(233,236,239,0.9) 100%);
        backdrop-filter: blur(15px);
        position: relative;
    }
    
    .card-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #4361ee, #3a0ca3, #7209b7);
        border-radius: 20px 20px 0 0;
    }
    
    .card-title {
        margin: 0;
        font-size: 1.9rem;
        font-weight: 800;
        color: #2c3e50;
        position: relative;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .card-title::before {
        content: '';
        width: 6px;
        height: 35px;
        background: linear-gradient(135deg, #4361ee, #3a0ca3);
        border-radius: 3px;
        display: inline-block;
    }
    
    .card-title::after {
        content: '';
        position: absolute;
        bottom: -8px;
        left: 21px;
        width: 40px;
        height: 3px;
        background: linear-gradient(90deg, #4361ee, #3a0ca3);
        border-radius: 2px;
    }
    
    /* Enhanced Search and Filter Controls */
    .filter-controls {
        display: flex;
        gap: 20px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .search-container {
        position: relative;
        min-width: 350px;
    }
    
    .search-input {
        padding: 16px 55px 16px 50px;
        border: 2px solid #e9ecef;
        border-radius: 15px;
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        font-size: 15px;
        transition: all 0.4s ease;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        width: 100%;
        font-weight: 500;
    }
    
    .search-input::placeholder {
        color: #9aa0a6;
        font-weight: 500;
    }
    
    .search-input:focus {
        outline: none;
        border-color: #4361ee;
        box-shadow: 0 6px 25px rgba(67, 97, 238, 0.2);
        transform: translateY(-2px);
        background: linear-gradient(135deg, #ffffff 0%, #f0f4ff 100%);
    }
    
    .search-icon {
        position: absolute;
        left: 20px;
        top: 50%;
        transform: translateY(-50%);
        color: #6c757d;
        font-size: 18px;
        z-index: 2;
        transition: all 0.3s ease;
    }
    
    .search-input:focus + .search-icon {
        color: #4361ee;
        transform: translateY(-50%) scale(1.1);
    }
    
    .search-clear {
        position: absolute;
        right: 18px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #6c757d;
        cursor: pointer;
        font-size: 16px;
        opacity: 0;
        transition: all 0.3s ease;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .search-clear:hover {
        background: #e9ecef;
        color: #dc3545;
    }
    
    .search-input:not(:placeholder-shown) + .search-clear {
        opacity: 1;
    }
    
    .filter-select {
        padding: 16px 20px;
        border: 2px solid #e9ecef;
        border-radius: 15px;
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        font-size: 15px;
        transition: all 0.4s ease;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        min-width: 180px;
        font-weight: 500;
        cursor: pointer;
    }
    
    .filter-select:focus {
        outline: none;
        border-color: #4361ee;
        box-shadow: 0 6px 25px rgba(67, 97, 238, 0.2);
        transform: translateY(-2px);
        background: linear-gradient(135deg, #ffffff 0%, #f0f4ff 100%);
    }
    
    /* Enhanced Action Button */
    .action-btn {
        padding: 16px 28px;
        border: none;
        border-radius: 15px;
        cursor: pointer;
        font-size: 0.95rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: all 0.4s ease;
        box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        position: relative;
        overflow: hidden;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .action-btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
        transition: left 0.6s;
    }
    
    .action-btn:hover::before {
        left: 100%;
    }
    
    .btn-primary { 
        background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%); 
        color: white; 
        border: none;
    }
    
    .btn-primary:hover { 
        background: linear-gradient(135deg, #3a56d4 0%, #2a0a8a 100%); 
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(67, 97, 238, 0.4);
    }
    
    /* Enhanced Table Container */
    .table-container {
        overflow-x: auto;
        width: 100%;
        flex: 1;
        background: white;
        border-radius: 0 0 20px 20px;
        position: relative;
    }
    
    /* Enhanced Table Styles */
    .residents-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        min-height: 400px;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .residents-table th {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        font-weight: 700;
        color: #2c3e50;
        font-size: 0.85rem;
        position: sticky;
        top: 0;
        padding: 22px 20px;
        border-bottom: 3px solid #dee2e6;
        text-transform: uppercase;
        letter-spacing: 1px;
        text-align: left;
        white-space: nowrap;
    }
    
    .residents-table th:first-child {
        border-radius: 0;
    }
    
    .residents-table th:last-child {
        border-radius: 0;
    }
    
    .residents-table td {
        padding: 20px;
        text-align: left;
        border-bottom: 1px solid rgba(0,0,0,0.06);
        transition: all 0.3s ease;
        font-weight: 500;
        color: #495057;
    }
    
    .residents-table tbody tr {
        background: white;
        transition: all 0.4s ease;
        position: relative;
    }
    
    .residents-table tbody tr::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: linear-gradient(135deg, #4361ee, #3a0ca3);
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .residents-table tbody tr:hover {
        background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 100%);
        transform: translateX(5px);
        box-shadow: 0 8px 25px rgba(67, 97, 238, 0.15);
    }
    
    .residents-table tbody tr:hover::before {
        opacity: 1;
    }
    
    .residents-table tbody tr:hover td {
        color: #2c3e50;
        font-weight: 600;
    }
    
    /* Enhanced Status Badges */
    .status {
        padding: 10px 18px;
        border-radius: 25px;
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
        box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        display: inline-block;
        min-width: 90px;
        text-align: center;
    }
    
    .status-active { 
        background: linear-gradient(135deg, #d4edda 0%, #c8e6c9 100%); 
        color: #155724; 
        border: 2px solid #c8e6c9;
    }
    .status-inactive { 
        background: linear-gradient(135deg, #f8d7da 0%, #ffcdd2 100%); 
        color: #721c24; 
        border: 2px solid #ffcdd2;
    }
    
    /* Enhanced Action Buttons in Table */
    .action-buttons {
        display: flex;
        gap: 10px;
        justify-content: flex-start;
        flex-wrap: wrap;
    }
    
    .table-action-btn {
        padding: 12px 16px;
        border: none;
        border-radius: 12px;
        cursor: pointer;
        font-size: 0.8rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 45px;
        height: 45px;
        transition: all 0.3s ease;
        box-shadow: 0 3px 12px rgba(0,0,0,0.15);
        position: relative;
        overflow: hidden;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .table-action-btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
        transition: left 0.5s;
    }
    
    .table-action-btn:hover::before {
        left: 100%;
    }
    
    .btn-success { 
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%); 
        color: white; 
    }
    .btn-danger { 
        background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%); 
        color: white; 
    }
    .btn-warning { 
        background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); 
        color: white; 
    }
    .btn-outline { 
        background: transparent; 
        border: 2px solid #6c757d; 
        color: #6c757d; 
    }
    
    .btn-success:hover { 
        background: linear-gradient(135deg, #218838 0%, #1ea085 100%); 
        transform: translateY(-2px) scale(1.05);
        box-shadow: 0 6px 20px rgba(40,167,69,0.3);
    }
    .btn-danger:hover { 
        background: linear-gradient(135deg, #c82333 0%, #d91a7a 100%); 
        transform: translateY(-2px) scale(1.05);
        box-shadow: 0 6px 20px rgba(220,53,69,0.3);
    }
    .btn-warning:hover { 
        background: linear-gradient(135deg, #e0a800 0%, #e55a13 100%); 
        transform: translateY(-2px) scale(1.05);
        box-shadow: 0 6px 20px rgba(255,193,7,0.3);
    }
    .btn-outline:hover { 
        background: #6c757d; 
        color: white; 
        transform: translateY(-2px) scale(1.05);
        box-shadow: 0 6px 20px rgba(108,117,125,0.3);
    }
    
    /* Enhanced Resident Avatar */
    .resident-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, #4361ee, #3a0ca3);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 800;
        font-size: 18px;
        box-shadow: 0 6px 15px rgba(67, 97, 238, 0.4);
        transition: all 0.3s ease;
    }
    
    .resident-info {
        display: flex;
        align-items: center;
        gap: 18px;
    }
    
    .resident-name {
        font-weight: 700;
        color: #2c3e50;
        font-size: 1.05rem;
        margin-bottom: 4px;
    }
    
    .resident-code {
        font-size: 0.8rem;
        color: #6c757d;
        font-weight: 600;
        background: #f8f9fa;
        padding: 4px 8px;
        border-radius: 6px;
        display: inline-block;
    }

    /* Year of Residency Badge */
    .year-badge {
        background: linear-gradient(135deg, #4361ee, #3a0ca3);
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 700;
        display: inline-block;
        box-shadow: 0 2px 8px rgba(67, 97, 238, 0.3);
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 100px 40px;
        color: #6c757d;
    }
    
    .empty-state i {
        font-size: 80px;
        margin-bottom: 25px;
        opacity: 0.2;
        color: #4361ee;
    }
    
    .empty-state h4 {
        font-size: 1.6rem;
        margin-bottom: 15px;
        color: #495057;
        font-weight: 700;
    }
    
    .empty-state p {
        font-size: 1.1rem;
        opacity: 0.7;
        margin-bottom: 30px;
        line-height: 1.6;
    }
    
    /* Table Footer */
    .table-footer {
        padding: 20px 30px;
        border-top: 1px solid #e9ecef;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.9rem;
        color: #6c757d;
        font-weight: 600;
    }
    
    .table-info {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .resident-count {
        background: #4361ee;
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.8rem;
    }

    /* Enhanced Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1050;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.7);
        backdrop-filter: blur(8px);
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s ease;
        padding: 20px;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    .modal-content {
        background: linear-gradient(135deg, #ffffff 0%, #fafbfc 100%);
        border-radius: 20px;
        width: 90%;
        max-width: 900px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 25px 80px rgba(0,0,0,0.4);
        animation: slideInUp 0.4s ease;
        border: 1px solid rgba(255,255,255,0.3);
        position: relative;
    }
    
    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(60px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
    
    .modal-header {
        padding: 30px;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 20px 20px 0 0;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    
    .modal-title {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
        color: #2c3e50;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .modal-title i {
        color: #4361ee;
        font-size: 1.3em;
    }
    
    .close {
        font-size: 1.5rem;
        cursor: pointer;
        color: #6c757d;
        background: none;
        border: none;
        width: 45px;
        height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.3s ease;
    }
    
    .close:hover {
        background: #e9ecef;
        color: #dc3545;
        transform: rotate(90deg) scale(1.1);
    }
    
    /* Enhanced Detail Sections */
    .detail-section {
        margin: 25px 30px;
        padding: 25px;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 12px;
        border-left: 5px solid #4361ee;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }
    
    .detail-section h4 {
        margin: 0 0 20px 0;
        color: #4361ee;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 700;
    }
    
    .detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }
    
    .detail-item {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
  .detail-item.full-width {
    grid-column: 1 / -1;
    min-height: 90px;
}
    
    .detail-item label {
        font-weight: 700;
        color: #495057;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .detail-value {
        color: #2c3e50;
        font-size: 1rem;
        font-weight: 500;
    }
    
    .detail-value.highlight {
        font-weight: 700;
        color: #4361ee;
        font-size: 1.1rem;
    }
    
    /* Enhanced Form Styles */
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 25px;
        padding: 30px;
    }
    
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .form-group.full-width {
        grid-column: 1 / -1;
    }
    
    .form-group label {
        font-weight: 600;
        color: #495057;
        font-size: 0.9rem;
    }
    
    .form-control {
        padding: 14px 16px;
        border: 2px solid #e9ecef;
        border-radius: 10px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        background: white;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    
    .form-control:focus {
        outline: none;
        border-color: #4361ee;
        box-shadow: 0 4px 15px rgba(67, 97, 238, 0.15);
        transform: translateY(-1px);
    }

    /* Form styling for year input */
    .form-control[type="number"] {
        -moz-appearance: textfield;
    }

    .form-control[type="number"]::-webkit-outer-spin-button,
    .form-control[type="number"]::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    
    .radio-group {
        display: flex;
        gap: 25px;
        margin-top: 8px;
    }
    
    .radio-option {
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
    }
    
    .radio-option input {
        width: auto;
        transform: scale(1.2);
    }
    
    /* Enhanced Confirmation Modal */
    .confirmation-modal {
        display: none;
        position: fixed;
        z-index: 1060;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.7);
        backdrop-filter: blur(8px);
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s ease;
    }
    
    .confirmation-content {
        background: linear-gradient(135deg, #ffffff 0%, #fafbfc 100%);
        border-radius: 20px;
        width: 90%;
        max-width: 500px;
        padding: 0;
        box-shadow: 0 25px 80px rgba(0,0,0,0.4);
        animation: slideInUp 0.4s ease;
        border: 1px solid rgba(255,255,255,0.3);
    }
    
    .confirmation-header {
        padding: 30px;
        border-bottom: 1px solid #e9ecef;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 20px 20px 0 0;
        text-align: center;
    }
    
    .confirmation-title {
        margin: 0;
        font-size: 1.4rem;
        font-weight: 700;
        color: #2c3e50;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
    }
    
    .confirmation-body {
        padding: 40px 30px;
        text-align: center;
    }
    
    .confirmation-message {
        font-size: 1.1rem;
        margin-bottom: 30px;
        color: #495057;
        line-height: 1.6;
    }
    
    .confirmation-buttons {
        display: flex;
        gap: 15px;
        justify-content: center;
    }
    
    .confirmation-btn {
        padding: 14px 30px;
        border: none;
        border-radius: 12px;
        cursor: pointer;
        font-size: 0.95rem;
        font-weight: 600;
        min-width: 120px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    
    .btn-confirm {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
    }
    
    .btn-confirm:hover {
        background: linear-gradient(135deg, #218838 0%, #1ea085 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(40,167,69,0.3);
    }
    
    .btn-cancel {
        background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
        color: white;
    }
    
    .btn-cancel:hover {
        background: linear-gradient(135deg, #5a6268 0%, #495057 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(108,117,125,0.3);
    }
    
    .btn-confirm-danger {
        background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
        color: white;
    }
    
    .btn-confirm-danger:hover {
        background: linear-gradient(135deg, #c82333 0%, #d91a7a 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(220,53,69,0.3);
    }
    
    /* Enhanced Notification */
    .notification {
        position: fixed;
        top: 25px;
        right: 25px;
        padding: 18px 25px;
        border-radius: 12px;
        color: white;
        font-weight: 600;
        z-index: 1070;
        box-shadow: 0 8px 30px rgba(0,0,0,0.2);
        animation: slideInRight 0.4s ease-out;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.3);
        display: flex;
        align-items: center;
        gap: 12px;
        max-width: 400px;
    }
    
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100%);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    .notification.success { 
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%); 
    }
    .notification.error { 
        background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%); 
    }
    .notification.info { 
        background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%); 
    }
    
    /* Staff Permission Modal Styles */
    .staff-permission-modal {
        display: none;
        position: fixed;
        z-index: 1060;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.6);
        backdrop-filter: blur(5px);
        align-items: center;
        justify-content: center;
    }
    
    .staff-permission-content {
        background: linear-gradient(135deg, #ffffff 0%, #fafbfc 100%);
        border-radius: 20px;
        width: 90%;
        max-width: 450px;
        padding: 0;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        animation: slideInUp 0.3s ease;
        border: 1px solid rgba(255,255,255,0.2);
    }
    
    /* Responsive Design */
    @media (max-width: 1200px) {
        .residents-container {
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
        
        .search-container {
            min-width: 280px;
        }
    }
    
    @media (max-width: 768px) {
        .residents-container {
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
        
        .search-container {
            min-width: unset;
            width: 100%;
        }
        
        .filter-select {
            width: 100%;
        }
        
        .residents-table th,
        .residents-table td {
            padding: 15px 12px;
            font-size: 0.85rem;
        }
        
        .action-buttons {
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .table-action-btn {
            min-width: 36px;
            height: 36px;
            padding: 8px 10px;
        }
        
        .form-grid {
            grid-template-columns: 1fr;
            padding: 20px;
            gap: 20px;
        }
        
        .detail-grid {
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .confirmation-buttons {
            flex-direction: column;
            gap: 10px;
        }
        
        .confirmation-btn {
            width: 100%;
        }
        
        .modal-content {
            width: 95%;
            margin: 10px;
        }
        
        .detail-section {
            margin: 15px;
            padding: 20px;
        }
    }
    
    @media (max-width: 480px) {
        .residents-container {
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
        
        .residents-table {
            font-size: 0.8rem;
        }
        
        .residents-table th,
        .residents-table td {
            padding: 12px 8px;
        }
        
        .modal-content,
        .confirmation-content {
            width: 95%;
            margin: 10px;
        }
    }
    
    /* Utility Classes */
    .d-none {
        display: none !important;
    }
    
    .text-center {
        text-align: center;
    }
    
    .mb-0 {
        margin-bottom: 0 !important;
    }
    
    .spinner {
        border: 4px solid #f3f3f3;
        border-top: 4px solid #4361ee;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
        margin: 0 auto 20px;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

/* --- Add Resident button layout fix --- */
.card-header {
  display: flex;
  align-items: center;
}
.card-header .table-actions {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 12px;
}
.card-header .search-box {
  position: relative;
  width: 320px;
}
.card-header .search-box i {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  pointer-events: none;
}
.card-header .search-box .form-control {
  padding-left: 36px;
}
/* --- end fix --- */

/* --- View Modal readability improvements --- */
.modal .modal-content {
    width: 100%;
    max-width: 1080px;
    max-height: 85vh; /* Increased height */
    border-radius: 16px;
    box-shadow: 0 20px 50px rgba(0,0,0,.25);
    overflow: hidden;
}

.modal .modal-header {
  position: sticky;
  top: 0;
  background: linear-gradient(180deg, #f8fafc, #f1f5f9);
  padding: 18px 24px;
  border-bottom: 1px solid #e5e7eb;
  z-index: 2;
}

.modal .modal-title {
  font-size: 1.4rem;
  font-weight: 800;
  color: #0f172a;
  display: flex;
  align-items: center;
  gap: 10px;
}

.modal .modal-body {
    max-height: calc(85vh - 100px); /* Increased height */
    overflow: auto;
    padding: 25px 30px; /* More padding */
    background: #ffffff;
}

.modal .modal-footer {
  position: sticky;
  bottom: 0;
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  padding: 14px 16px;
  background: linear-gradient(180deg, rgba(255,255,255,.7), #ffffff);
  border-top: 1px solid #e5e7eb;
  z-index: 2;
}

.resident-details {
    display: grid;
    gap: 25px; /* Increased gap between sections */
}

.detail-section {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 22px; /* More padding */
    min-height: 120px; /* Minimum height for sections */
}

.detail-section h4 {
  margin: 0 0 14px 0;
  color: #1d4ed8;
  font-size: 1.05rem;
  font-weight: 800;
  display: flex;
  align-items: center;
  gap: 8px;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    gap: 18px 22px; /* Increased gaps */
}

.detail-item {
    grid-column: span 4;
    min-height: 70px; /* Minimum height for items */
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.detail-item label {
    font-size: .78rem; /* Slightly larger */
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #64748b;
    margin-bottom: 8px; /* More spacing */
    display: block;
    font-weight: 700;
}

.detail-value {
    font-size: 1.08rem; /* Larger text */
    color: #0f172a;
    font-weight: 600;
    line-height: 1.5; /* Better line height */
    word-break: break-word;
    min-height: 24px; /* Ensure consistent height */
}

.detail-value.muted { color: #475569; font-weight: 500; }

.detail-value .status {
  padding: 2px 8px;
  border-radius: 999px;
  background: #e2f6ff;
  color: #075985;
  font-weight: 700;
}

.copyable {
  cursor: pointer;
  position: relative;
}
.copyable::after {
  content: '⧉';
  font-size: .8rem;
  margin-left: .35rem;
  opacity: .6;
}
.copyable.copied::after {
  content: 'Copied!';
  font-size: .75rem;
  margin-left: .5rem;
  opacity: .9;
}
/* --- end view modal improvements --- */

/* Pagination Styles */
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

/* Disabled button styles for staff */
.btn-disabled {
    opacity: 0.6;
    cursor: not-allowed !important;
    transform: none !important;
}

.btn-disabled:hover {
    transform: none !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15) !important;
}
</style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-content">
            <div class="logo">
                <h1><i class="fas fa-home"></i> BarangayHub</h1>
            </div>
            <ul class="menu">
                <li><a href="dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="residents.php" class="<?php echo $current_page == 'residents.php' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Residents</a></li>
                <li><a href="requests.php" class="<?php echo $current_page == 'requests.php' ? 'active' : ''; ?>"><i class="fas fa-file-alt"></i> Document Requests</a></li>
                <li><a href="documents.php" class="<?php echo $current_page == 'documents.php' ? 'active' : ''; ?>"><i class="fas fa-archive"></i> Documents</a></li>
        
                <li><a href="history.php" class="<?php echo $current_page == 'history.php' ? 'active' : ''; ?>"><i class="fas fa-history"></i> Request History</a></li>
                           <li>
        <a href="roles.php" class="<?php echo $current_page == 'roles.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-shield"></i>
            Roles & Admin
        </a>
    </li>
                <li><a href="logout.php" style="color: #ff6b6b;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <h2 id="page-title">Residents Management</h2>
            <div class="user-info">
                <div class="user-details">
                    <div class="name"><?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></div>
                    <div class="role"><?php echo ucfirst($_SESSION['role']); ?></div>
                </div>
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['first_name'] . '+' . $_SESSION['last_name']); ?>&background=4361ee&color=fff" alt="Admin">
            </div>
        </div>

        <!-- Notification Container -->
        <div class="notification-container" id="notificationContainer">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="notification success show">
                    <i class="fas fa-check-circle"></i> 
                    <span><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></span>
                    <button class="notification-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="notification error show">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <span><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></span>
                    <button class="notification-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
                </div>
            <?php endif; ?>
        </div>

        <div class="content">
            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number"><?php echo $total_residents; ?></div>
                    <div class="stat-label">Total Residents</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <?php 
                    $active_count_query = "SELECT COUNT(*) as count FROM resident WHERE status = 'active'";
                    if (!empty($search)) {
                        $active_count_query .= " AND (full_name LIKE :search OR contact_number LIKE :search OR address LIKE :search OR resident_code LIKE :search OR email LIKE :search)";
                    }
                    $active_stmt = $db->prepare($active_count_query);
                    if (!empty($search)) {
                        $active_stmt->bindValue(':search', "%$search%");
                    }
                    $active_stmt->execute();
                    $active_count = $active_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    ?>
                    <div class="stat-number"><?php echo $active_count; ?></div>
                    <div class="stat-label">Active Residents</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-vote-yea"></i>
                    </div>
                    <?php 
                    $voter_count_query = "SELECT COUNT(*) as count FROM resident WHERE voter_status = 'yes'";
                    if (!empty($search)) {
                        $voter_count_query .= " AND (full_name LIKE :search OR contact_number LIKE :search OR address LIKE :search OR resident_code LIKE :search OR email LIKE :search)";
                    }
                    $voter_stmt = $db->prepare($voter_count_query);
                    if (!empty($search)) {
                        $voter_stmt->bindValue(':search', "%$search%");
                    }
                    $voter_stmt->execute();
                    $voter_count = $voter_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    ?>
                    <div class="stat-number"><?php echo $voter_count; ?></div>
                    <div class="stat-label">Registered Voters</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <?php 
                    $senior_count_query = "SELECT COUNT(*) as count FROM resident WHERE senior_citizen_status = 'yes'";
                    if (!empty($search)) {
                        $senior_count_query .= " AND (full_name LIKE :search OR contact_number LIKE :search OR address LIKE :search OR resident_code LIKE :search OR email LIKE :search)";
                    }
                    $senior_stmt = $db->prepare($senior_count_query);
                    if (!empty($search)) {
                        $senior_stmt->bindValue(':search', "%$search%");
                    }
                    $senior_stmt->execute();
                    $senior_count = $senior_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    ?>
                    <div class="stat-number"><?php echo $senior_count; ?></div>
                    <div class="stat-label">Senior Citizens</div>
                </div>
            </div>

            <!-- Residents Table Card -->
            <div class="card">
                <div class="card-header">
                    <h3>Residents List</h3>
                    <div class="table-actions">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <form method="GET" action="residents.php" id="searchForm" style="display: inline;">
                                <input type="text" name="search" id="residentSearch" class="form-control" placeholder="Search residents..." value="<?php echo htmlspecialchars($search); ?>">
                                <input type="hidden" name="page" value="1">
                            </form>
                        </div>
                        <?php if ($current_user_role !== 'Staff'): ?>
                            <button class="btn btn-primary" onclick="openAddModal()">
                                <i class="fas fa-user-plus"></i> Add Resident
                            </button>
                        <?php else: ?>
                            <button class="btn btn-primary btn-disabled" onclick="showStaffPermissionModal('Staff members cannot add residents. Please contact your administrator.')">
                                <i class="fas fa-user-plus"></i> Add Resident
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="table" id="residentsTable">
                            <thead>
                                <tr>
                                    <th>Resident</th>
                                    <th>Contact Info</th>
                                    <th>Address</th>
                                    <th>Age</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($residents) > 0): ?>
                                    <?php foreach ($residents as $resident): ?>
                                    <tr data-resident-id="<?php echo $resident['resident_id']; ?>">
                                        <td>
                                            <div class="resident-info">
                                                <div class="resident-avatar">
                                                    <?php echo strtoupper(substr($resident['full_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div class="resident-name"><?php echo htmlspecialchars($resident['full_name']); ?></div>
                                                    <div class="resident-code"><?php echo htmlspecialchars($resident['resident_code']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($resident['contact_number']); ?></div>
                                            <div style="font-size: 12px; color: #718096;"><?php echo htmlspecialchars($resident['email'] ?: 'No email'); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($resident['address']); ?></td>
                                        <td><?php echo !empty($resident['birthdate']) && $resident['birthdate'] != '0000-00-00' ? htmlspecialchars($resident['age'] ?? 'N/A') : 'N/A'; ?></td>
                                        <td>
                                            <span class="status status-<?php echo $resident['STATUS']; ?>">
                                                <?php echo ucfirst($resident['STATUS']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="action-btn view" onclick="openViewModal(<?php echo $resident['resident_id']; ?>)">
                                                    <i class="fas fa-eye"></i>  View
                                                </button>
                                                <?php if ($current_user_role !== 'Staff'): ?>
                                                    <button class="action-btn edit" onclick="openEditModal(<?php echo $resident['resident_id']; ?>)">
                                                        <i class="fas fa-edit"></i>  Edit
                                                    </button>
                                                    <button class="action-btn delete" onclick="showDeleteConfirmation(<?php echo $resident['resident_id']; ?>)">
                                                        <i class="fas fa-trash"></i>  Delete
                                                    </button>
                                                <?php else: ?>
                                                    <button class="action-btn edit btn-disabled" onclick="showStaffPermissionModal('Staff members cannot edit residents. Please contact your administrator.')">
                                                        <i class="fas fa-edit"></i>  Edit
                                                    </button>
                                                    <button class="action-btn delete btn-disabled" onclick="showStaffPermissionModal('Staff members cannot delete residents. Please contact your administrator.')">
                                                        <i class="fas fa-trash"></i>  Delete
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">
                                            <div class="empty-state">
                                                <i class="fas fa-users"></i>
                                                <h4>No Residents Found</h4>
                                                <p><?php echo !empty($search) ? 'No residents match your search criteria.' : 'No residents have been added yet.'; ?></p>
                                                <?php if (empty($search) && $current_user_role !== 'Staff'): ?>
                                                    <button class="btn btn-primary" onclick="openAddModal()">
                                                        <i class="fas fa-user-plus"></i> Add First Resident
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination Controls -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <div class="pagination-info">
                            Showing <?php echo min($limit, count($residents)); ?> of <?php echo $total_residents; ?> residents
                            <?php if (!empty($search)): ?>
                                for "<?php echo htmlspecialchars($search); ?>"
                            <?php endif; ?>
                        </div>
                        <div class="pagination-controls">
                            <?php 
                            // Build query parameters for pagination links
                            $pagination_params = [];
                            if (!empty($search)) {
                                $pagination_params['search'] = $search;
                            }
                            
                            // Previous page link
                            if ($page > 1): 
                                $prev_params = array_merge($pagination_params, ['page' => $page - 1]);
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
                                Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                            </span>
                            
                            <?php 
                            // Next page link
                            if ($page < $total_pages): 
                                $next_params = array_merge($pagination_params, ['page' => $page + 1]);
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
        </div>
    </div>

    <!-- Add Resident Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-user-plus"></i> Add New Resident</h3>
                <span class="close" onclick="closeAddModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" action="residents.php" id="addResidentForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="full_name">Full Name <span style="color: #f72585;">*</span></label>
                            <input type="text" id="full_name" name="full_name" class="form-control" placeholder="John G. Alpha" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="birthdate">Birth Date</label>
                            <input type="date" id="birthdate" name="birthdate" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender" class="form-control">
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="civil_status">Civil Status</label>
                            <select id="civil_status" name="civil_status" class="form-control">
                                <option value="single">Single</option>
                                <option value="married">Married</option>
                                <option value="widowed">Widowed</option>
                                <option value="separated">Separated</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="contact_number">Contact Number <span style="color: #f72585;">*</span></label>
                            <input type="text" id="contact_number" name="contact_number" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="year_of_residency">Year of Residency</label>
                            <input type="number" id="year_of_residency" name="year_of_residency" class="form-control" 
                                   min="1900" max="<?php echo date('Y'); ?>" 
                                   placeholder="e.g., 2015">
                        </div>
                    <div class="form-group full-width">
    <label for="address">Complete Address <span style="color: #f72585;">*</span></label>
    <textarea id="address" name="address" class="form-control" placeholder="Purok 4, Sta Rita WesT Aringay La Union" required rows="3"></textarea>
</div>
                        <div class="form-group">
                            <label for="emergency_contact">Emergency Contact Person</label>
                            <input type="text" id="emergency_contact" name="emergency_contact" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="emergency_number">Emergency Contact Number</label>
                            <input type="text" id="emergency_number" name="emergency_number" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="family_count">Family Members</label>
                            <input type="number" id="family_count" name="family_count" class="form-control" min="0" value="1">
                        </div>
                        <div class="form-group">
                            <label for="monthly_income">Monthly Income (₱)</label>
                            <input type="number" id="monthly_income" name="monthly_income" class="form-control" step="0.01" min="0" value="0.00">
                        </div>
                        <div class="form-group">
                            <label for="occupation">Occupation</label>
                            <input type="text" id="occupation" name="occupation" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Voter Status</label>
                            <div class="radio-group">
                                <div class="radio-option">
                                    <input type="radio" id="voter_yes_add" name="voter_status" value="yes">
                                    <label for="voter_yes_add">Registered Voter</label>
                                </div>
                                <div class="radio-option">
                                    <input type="radio" id="voter_no_add" name="voter_status" value="no" checked>
                                    <label for="voter_no_add">Not Registered</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Senior Citizen Status</label>
                            <div class="radio-group">
                                <div class="radio-option">
                                    <input type="radio" id="senior_yes_add" name="senior_citizen_status" value="yes">
                                    <label for="senior_yes_add">Senior Citizen</label>
                                </div>
                                <div class="radio-option">
                                    <input type="radio" id="senior_no_add" name="senior_citizen_status" value="no" checked>
                                    <label for="senior_no_add">Not Senior Citizen</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" class="form-control">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" onclick="closeAddModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="add_resident">Add Resident</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<!-- View Resident Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content" style="max-width: 1000px; max-height: 85vh;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-eye"></i> Resident Details</h3>
            <span class="close" onclick="closeViewModal()">&times;</span>
        </div>
        <div class="modal-body" id="viewModalBody" style="padding: 30px; max-height: calc(85vh - 100px); overflow-y: auto;">
            <div class="spinner"></div>
            <div style="text-align: center;">Loading resident details...</div>
        </div>
    </div>
</div>

    <!-- Edit Resident Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-edit"></i> Edit Resident</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <div class="modal-body" id="editModalBody">
                <div class="spinner"></div>
                <div style="text-align: center;">Loading resident data...</div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmationModal" class="modal">
        <div class="confirmation-content">
            <div class="confirmation-header">
                <h3 class="confirmation-title">
                    <i class="fas fa-exclamation-triangle" style="color: #f72585;"></i> Confirm Deletion
                </h3>
            </div>
            <div class="confirmation-body">
                <div class="text-center mb-3">
                    <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #f72585; margin-bottom: 15px;"></i>
                </div>
                <p class="confirmation-message">
                    Are you sure you want to delete this resident? This action cannot be undone.
                </p>
                <div class="confirmation-buttons">
                    <button class="confirmation-btn btn-cancel" onclick="cancelDelete()">
                        <i class="fas fa-times"></i> No, Cancel
                    </button>
                    <button class="confirmation-btn btn-confirm-danger" onclick="confirmDelete()">
                        <i class="fas fa-trash"></i> Yes, Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- Staff Permission Modal -->
    <div id="staff-permission-modal" class="staff-permission-modal">
        <div class="staff-permission-content">
            <div class="confirmation-header">
                <h3 class="confirmation-title">
                    <i class="fas fa-shield-alt" style="color: #ffc107;"></i> Permission Required
                </h3>
            </div>
            <div class="confirmation-body">
                <div class="text-center mb-3">
                    <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #ffc107; margin-bottom: 15px;"></i>
                </div>
                <p class="confirmation-message" id="staff-permission-message">
                    Staff members cannot perform this action. Please contact your administrator.
                </p>
                <div class="confirmation-buttons">
                    <button class="confirmation-btn btn-cancel" onclick="closeStaffPermissionModal()">OK</button>
                </div>
            </div>
        </div>
    </div>

<script>
    let residentToDelete = null;
    const currentUserRole = '<?php echo $current_user_role; ?>';

    // Function to check permissions for resident actions
    function canManageResidents() {
        if (currentUserRole === 'Staff') {
            showStaffPermissionModal('Staff members cannot add, edit, or delete residents. Please contact your administrator.');
            return false;
        }
        return true;
    }

    // Function to show staff permission modal
    function showStaffPermissionModal(message) {
        if (message) {
            document.getElementById('staff-permission-message').textContent = message;
        }
        document.getElementById('staff-permission-modal').style.display = 'flex';
    }

    // Function to close staff permission modal
    function closeStaffPermissionModal() {
        document.getElementById('staff-permission-modal').style.display = 'none';
    }

    // Auto-remove notifications after 5 seconds
    setTimeout(() => {
        document.querySelectorAll('.notification').forEach(notification => {
            setTimeout(() => notification.remove(), 800);
        });
    }, 5000);

    // Search functionality - submit form on input with debounce
    let searchTimeout;
    document.getElementById('residentSearch').addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            document.getElementById('searchForm').submit();
        }, 500);
    });

    // Modal functions
    function openAddModal() {
        if (!canManageResidents()) return;
        document.getElementById('addModal').style.display = 'flex';
        // Set max birthdate to today
        document.getElementById('birthdate').max = new Date().toISOString().split('T')[0];
        // Set max year of residency to current year
        document.getElementById('year_of_residency').max = new Date().getFullYear();
    }

    function closeAddModal() {
        document.getElementById('addModal').style.display = 'none';
    }

    function openViewModal(residentId) {
        document.getElementById('viewModal').style.display = 'flex';
        document.getElementById('viewModalBody').innerHTML = `
            <div class="spinner"></div>
            <div style="text-align: center;">Loading resident details...</div>
                    `;
        
        fetch(`get_resident_details.php?id=${residentId}`)
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (data.success && data.resident) {
                    const resident = data.resident;
                    const age = resident.age || 'N/A';
                    const birthdate = resident.birthdate && resident.birthdate !== '0000-00-00' ? resident.birthdate : 'Not provided';
                    const registrationDate = resident.registration_date ? resident.registration_date.split(' ')[0] : 'Not available';
                    const yearOfResidency = resident.year_of_residency && resident.year_of_residency !== '0000' ? resident.year_of_residency : 'Not specified';
                    
                    document.getElementById('viewModalBody').innerHTML = `
                        <div class="resident-details">
                            <div class="detail-section">
                                <h4><i class="fas fa-info-circle"></i> Basic Information</h4>
                                <div class="detail-grid">
                                    <div class="detail-item">
                                        <label>Resident Code</label>
                                        <div class="detail-value highlight"><span class="copyable" data-copy="resident_code">${resident.resident_code}</span></div>
                                    </div>
                                    <div class="detail-item">
                                        <label>Full Name</label>
                                        <div class="detail-value highlight">${resident.full_name}</div>
                                    </div>
                                    <div class="detail-item">
                                        <label>Age</label>
                                        <div class="detail-value">${age}</div>
                                    </div>
                                    <div class="detail-item">
                                        <label>Gender</label>
                                        <div class="detail-value">${resident.gender ? resident.gender.charAt(0).toUpperCase() + resident.gender.slice(1) : 'Not provided'}</div>
                                    </div>
                                    <div class="detail-item">
                                        <label>Civil Status</label>
                                        <div class="detail-value">${resident.civil_status ? resident.civil_status.charAt(0).toUpperCase() + resident.civil_status.slice(1) : 'Not provided'}</div>
                                    </div>
                                    <div class="detail-item">
                                        <label>Birth Date</label>
                                        <div class="detail-value">${birthdate}</div>
                                    </div>
                                    <div class="detail-item">
                                        <label>Year of Residency</label>
                                        <div class="detail-value">${yearOfResidency}</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="detail-section">
                                <h4><i class="fas fa-address-book"></i> Contact Information</h4>
                                <div class="detail-grid">
                                    <div class="detail-item">
                                        <label>Contact Number</label>
                                        <div class="detail-value"><span class="copyable" data-copy="contact">${resident.contact_number}</span></div>
                                    </div>
                                    <div class="detail-item">
                                        <label>Email Address</label>
                                        <div class="detail-value"><span class="copyable" data-copy="email">${resident.email || 'Not provided'}</span></div>
                                    </div>
                                    <div class="detail-item">
                                        <label>Emergency Contact</label>
                                        <div class="detail-value">${resident.emergency_contact || 'Not provided'}</div>
                                    </div>
                                    <div class="detail-item">
                                        <label>Emergency Number</label>
                                        <div class="detail-value">${resident.emergency_number || 'Not provided'}</div>
                                    </div>
                                    <div class="detail-item full-width">
                                        <label>Complete Address</label>
                                        <div class="detail-value">${resident.address}</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="detail-section">
                                <h4><i class="fas fa-home"></i> Family & Economic Information</h4>
                                <div class="detail-grid">
                                    <div class="detail-item">
                                        <label>Family Members</label>
                                        <div class="detail-value">${resident.family_count || '1'}</div>
                                    </div>
                                    <div class="detail-item">
                                        <label>Monthly Income</label>
                                        <div class="detail-value">₱${parseFloat(resident.monthly_income || 0).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                                    </div>
                                    <div class="detail-item">
                                        <label>Occupation</label>
                                        <div class="detail-value">${resident.occupation || 'Not provided'}</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="detail-section">
                                <h4><i class="fas fa-chart-bar"></i> Status Information</h4>
                                <div class="detail-grid">
                                    <div class="detail-item">
                                        <label>Voter Status</label>
                                        <div class="detail-value">${resident.voter_status === 'yes' ? 'Registered Voter' : 'Not Registered'}</div>
                                    </div>
                                    <div class="detail-item">
                                        <label>Senior Citizen Status</label>
                                        <div class="detail-value">${resident.senior_citizen_status === 'yes' ? 'Senior Citizen' : 'Not Senior Citizen'}</div>
                                    </div>
                                    <div class="detail-item">
                                        <label>Registration Date</label>
                                        <div class="detail-value">${registrationDate}</div>
                                    </div>
                                    <div class="detail-item">
                                        <label>Status</label>
                                        <div class="detail-value">
                                            <span class="status status-${resident.status}">${resident.status ? resident.status.charAt(0).toUpperCase() + resident.status.slice(1) : 'Active'}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            ${currentUserRole !== 'Staff' ? `
                                <button type="button" class="btn btn-warning" onclick="closeViewModal(); setTimeout(() => openEditModal(${resident.resident_id}), 300)">
                                    <i class="fas fa-edit"></i> Edit Resident
                                </button>
                            ` : ''}
                            <button type="button" class="btn btn-outline" onclick="closeViewModal()">Close</button>
                        </div>
                    `;
                } else {
                    document.getElementById('viewModalBody').innerHTML = `
                        <div class="notification error show">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <span>Error: ${data.message || 'Resident not found'}</span>
                        </div>
                    `;
                }
            })
            .catch(error => {
                document.getElementById('viewModalBody').innerHTML = `
                    <div class="notification error show">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <span>Error loading resident details: ${error.message}</span>
                    </div>
                `;
            });
    }

    function closeViewModal() {
        document.getElementById('viewModal').style.display = 'none';
    }

    function openEditModal(residentId) {
        if (!canManageResidents()) return;
        document.getElementById('editModal').style.display = 'flex';
        document.getElementById('editModalBody').innerHTML = `
            <div class="spinner"></div>
            <div style="text-align: center;">Loading resident data...</div>
        `;
        
        fetch(`get_resident_details.php?id=${residentId}`)
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (data.success && data.resident) {
                    const resident = data.resident;
                    const birthdate = resident.birthdate && resident.birthdate !== '0000-00-00' ? resident.birthdate : '';
                    
                    document.getElementById('editModalBody').innerHTML = `
                        <form method="POST" action="residents.php">
                            <input type="hidden" name="resident_id" value="${resident.resident_id}">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="full_name_edit">Full Name <span style="color: #f72585;">*</span></label>
                                    <input type="text" id="full_name_edit" name="full_name" value="${resident.full_name}" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="email_edit">Email Address</label>
                                    <input type="email" id="email_edit" name="email" value="${resident.email || ''}" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label for="birthdate_edit">Birth Date</label>
                                    <input type="date" id="birthdate_edit" name="birthdate" value="${birthdate}" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label for="gender_edit">Gender</label>
                                    <select id="gender_edit" name="gender" class="form-control">
                                        <option value="">Select Gender</option>
                                        <option value="male" ${resident.gender === 'male' ? 'selected' : ''}>Male</option>
                                        <option value="female" ${resident.gender === 'female' ? 'selected' : ''}>Female</option>
                                        <option value="other" ${resident.gender === 'other' ? 'selected' : ''}>Other</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="civil_status_edit">Civil Status</label>
                                    <select id="civil_status_edit" name="civil_status" class="form-control">
                                        <option value="single" ${resident.civil_status === 'single' ? 'selected' : ''}>Single</option>
                                        <option value="married" ${resident.civil_status === 'married' ? 'selected' : ''}>Married</option>
                                        <option value="widowed" ${resident.civil_status === 'widowed' ? 'selected' : ''}>Widowed</option>
                                        <option value="separated" ${resident.civil_status === 'separated' ? 'selected' : ''}>Separated</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="contact_number_edit">Contact Number <span style="color: #f72585;">*</span></label>
                                    <input type="text" id="contact_number_edit" name="contact_number" value="${resident.contact_number}" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="year_of_residency_edit">Year of Residency</label>
                                    <input type="number" id="year_of_residency_edit" name="year_of_residency" 
                                           class="form-control" min="1900" max="${new Date().getFullYear()}"
                                           value="${resident.year_of_residency || ''}">
                                </div>
                                <div class="form-group full-width">
                                    <label for="address_edit">Complete Address <span style="color: #f72585;">*</span></label>
                                    <textarea id="address_edit" name="address" class="form-control" required rows="3">${resident.address}</textarea>
                                </div>
                                <div class="form-group">
                                    <label for="emergency_contact_edit">Emergency Contact Person</label>
                                    <input type="text" id="emergency_contact_edit" name="emergency_contact" value="${resident.emergency_contact || ''}" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label for="emergency_number_edit">Emergency Contact Number</label>
                                    <input type="text" id="emergency_number_edit" name="emergency_number" value="${resident.emergency_number || ''}" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label for="family_count_edit">Family Members</label>
                                    <input type="number" id="family_count_edit" name="family_count" class="form-control" min="0" value="${resident.family_count || 1}">
                                </div>
                                <div class="form-group">
                                    <label for="monthly_income_edit">Monthly Income (₱)</label>
                                    <input type="number" id="monthly_income_edit" name="monthly_income" class="form-control" step="0.01" min="0" value="${parseFloat(resident.monthly_income || 0).toFixed(2)}">
                                </div>
                                <div class="form-group">
                                    <label for="occupation_edit">Occupation</label>
                                    <input type="text" id="occupation_edit" name="occupation" value="${resident.occupation || ''}" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>Voter Status</label>
                                    <div class="radio-group">
                                        <div class="radio-option">
                                            <input type="radio" id="voter_yes_edit" name="voter_status" value="yes" ${resident.voter_status === 'yes' ? 'checked' : ''}>
                                            <label for="voter_yes_edit">Registered Voter</label>
                                        </div>
                                        <div class="radio-option">
                                            <input type="radio" id="voter_no_edit" name="voter_status" value="no" ${resident.voter_status !== 'yes' ? 'checked' : ''}>
                                            <label for="voter_no_edit">Not Registered</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Senior Citizen Status</label>
                                    <div class="radio-group">
                                        <div class="radio-option">
                                            <input type="radio" id="senior_yes_edit" name="senior_citizen_status" value="yes" ${resident.senior_citizen_status === 'yes' ? 'checked' : ''}>
                                            <label for="senior_yes_edit">Senior Citizen</label>
                                        </div>
                                        <div class="radio-option">
                                            <input type="radio" id="senior_no_edit" name="senior_citizen_status" value="no" ${resident.senior_citizen_status !== 'yes' ? 'checked' : ''}>
                                            <label for="senior_no_edit">Not Senior Citizen</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="status_edit">Status</label>
                                    <select id="status_edit" name="status" class="form-control">
                                        <option value="active" ${resident.status === 'active' ? 'selected' : ''}>Active</option>
                                        <option value="inactive" ${resident.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline" onclick="closeEditModal()">Cancel</button>
                                <button type="submit" class="btn btn-primary" name="edit_resident">Update Resident</button>
                            </div>
                        </form>
                    `;
                    // Set max birthdate to today
                    const birthdateInput = document.getElementById('birthdate_edit');
                    if (birthdateInput) birthdateInput.max = new Date().toISOString().split('T')[0];
                    
                    // Auto-format contact numbers
                    const contactInputs = ['contact_number_edit', 'emergency_number_edit'];
                    contactInputs.forEach(inputId => {
                        const input = document.getElementById(inputId);
                        if (input) {
                            input.addEventListener('input', function(e) {
                                this.value = this.value.replace(/[^0-9+-\s]/g, '');
                            });
                        }
                    });
                } else {
                    document.getElementById('editModalBody').innerHTML = `
                        <div class="notification error show">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <span>Error: ${data.message || 'Resident not found'}</span>
                        </div>
                    `;
                }
            })
            .catch(error => {
                document.getElementById('editModalBody').innerHTML = `
                    <div class="notification error show">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <span>Error loading resident data: ${error.message}</span>
                    </div>
                `;
            });
    }

    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    function showDeleteConfirmation(residentId) {
        if (!canManageResidents()) return;
        residentToDelete = residentId;
        document.getElementById('deleteConfirmationModal').style.display = 'flex';
    }

    function cancelDelete() {
        residentToDelete = null;
        document.getElementById('deleteConfirmationModal').style.display = 'none';
    }

    function confirmDelete() {
        if (residentToDelete && canManageResidents()) {
            window.location.href = `residents.php?delete_id=${residentToDelete}<?php echo !empty($search) ? '&search=' . urlencode($search) . '&page=' . $page : ''; ?>`;
        }
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    }

    // Auto-format contact numbers for add form
    const contactInputs = ['contact_number', 'emergency_number'];
    contactInputs.forEach(inputId => {
        const input = document.getElementById(inputId);
        if (input) {
            input.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9+-\s]/g, '');
            });
        }
    });
</script>

<script>
(function () {
  function wireCopy(container) {
    if (!container) return;
    container.querySelectorAll('.copyable').forEach(function(el){
      el.addEventListener('click', function(){
        const text = el.textContent.trim();
        if (!text || text === 'Not provided') return;
        navigator.clipboard && navigator.clipboard.writeText(text).then(function(){
          el.classList.add('copied');
          setTimeout(function(){ el.classList.remove('copied'); }, 1200);
        });
      });
    });
  }
  // Re-wire when view modal is opened and content is injected
  const _origOpenView = window.openViewModal;
  window.openViewModal = function(residentId){
    _origOpenView(residentId);
    setTimeout(function(){ wireCopy(document.getElementById('viewModalBody')); }, 600);
  };
})();
</script>
</body>
</html>
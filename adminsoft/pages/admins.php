<?php
// File: pages/admins.php
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

// Check permissions
$userRole = $_SESSION['role'];
$userId = $_SESSION['admin_id'];

// Handle delete admin
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    
    // Check if user has permission to delete
    if ($userRole === 'staff') {
        $_SESSION['error_message'] = "You don't have permission to delete admins.";
        header("Location: admins.php");
        exit();
    }
    
    // Get admin to delete info for permission check
    $check_query = "SELECT role FROM ADMIN WHERE admin_id = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute([$delete_id]);
    $targetAdmin = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Permission checks
    if ($userRole === 'secretary' && $targetAdmin['role'] === 'captain') {
        $_SESSION['error_message'] = "Secretary cannot delete Captain.";
        header("Location: admins.php");
        exit();
    }
    
    if ($userRole === 'secretary' && $targetAdmin['role'] === 'secretary') {
        $_SESSION['error_message'] = "Cannot delete same level admin.";
        header("Location: admins.php");
        exit();
    }
    
    // Prevent self-deletion
    if ($delete_id == $userId) {
        $_SESSION['error_message'] = "You cannot delete your own account.";
        header("Location: admins.php");
        exit();
    }
    
    $query = "DELETE FROM ADMIN WHERE admin_id = ?";
    $stmt = $db->prepare($query);
    if ($stmt->execute([$delete_id])) {
        $_SESSION['success_message'] = "Admin deleted successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to delete admin.";
    }
    session_write_close();
    header("Location: admins.php");
    exit();
}

// Handle edit admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_admin'])) {
    $admin_id = $_POST['admin_id'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $contact_number = $_POST['contact_number'];
    $role = $_POST['role'];
    $status = $_POST['status'];
    
    // Permission checks for role editing
    if ($admin_id != $userId) { // Not editing self
        if ($userRole === 'staff') {
            $_SESSION['error_message'] = "You can only edit your own profile.";
            header("Location: admins.php");
            exit();
        }
        
        // Get current admin role for permission check
        $check_query = "SELECT role FROM ADMIN WHERE admin_id = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([$admin_id]);
        $targetAdmin = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Secretary cannot edit captain or same level
        if ($userRole === 'secretary' && ($targetAdmin['role'] === 'captain' || $targetAdmin['role'] === 'secretary')) {
            $_SESSION['error_message'] = "You cannot edit admins of same or higher level.";
            header("Location: admins.php");
            exit();
        }
        
        // Prevent role escalation
        if ($userRole === 'secretary' && $role === 'captain') {
            $_SESSION['error_message'] = "Secretary cannot assign Captain role.";
            header("Location: admins.php");
            exit();
        }
    } else {
        // User editing themselves - cannot change their own role
        $role = $userRole; // Keep current role
    }
    
    $query = "UPDATE ADMIN SET 
                first_name = ?, last_name = ?, email = ?, contact_number = ?, 
                role = ?, status = ?, updated_at = NOW() 
              WHERE admin_id = ?";
    
    $stmt = $db->prepare($query);
    if ($stmt->execute([$first_name, $last_name, $email, $contact_number, $role, $status, $admin_id])) {
        $_SESSION['success_message'] = "Admin updated successfully!";
        
        // Update session if editing own profile
        if ($admin_id == $userId) {
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            $_SESSION['email'] = $email;
        }
    } else {
        $_SESSION['error_message'] = "Failed to update admin.";
    }
    session_write_close();
    header("Location: admins.php");
    exit();
}

// Handle add admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    // Check if user has permission to add admins
    if ($userRole === 'staff') {
        $_SESSION['error_message'] = "You don't have permission to add admins.";
        header("Location: admins.php");
        exit();
    }
    
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $contact_number = $_POST['contact_number'];
    $role = $_POST['role'];
    $status = $_POST['status'];
    
    // Permission checks for role assignment
    if ($userRole === 'secretary' && $role === 'captain') {
        $_SESSION['error_message'] = "Secretary cannot create Captain accounts.";
        header("Location: admins.php");
        exit();
    }
    
    if ($userRole === 'secretary' && $role === 'secretary') {
        $_SESSION['error_message'] = "Secretary cannot create same level accounts.";
        header("Location: admins.php");
        exit();
    }
    
    // Check if username or email already exists
    $check_query = "SELECT admin_id FROM ADMIN WHERE username = ? OR email = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute([$username, $email]);
    if ($check_stmt->fetch()) {
        $_SESSION['error_message'] = "Username or email already exists.";
        header("Location: admins.php");
        exit();
    }
    
    $query = "INSERT INTO ADMIN (
                username, password, email, first_name, last_name, 
                role, contact_number, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $db->prepare($query);
    if ($stmt->execute([$username, $password, $email, $first_name, $last_name, $role, $contact_number, $status])) {
        $_SESSION['success_message'] = "Admin added successfully! Username: $username";
    } else {
        $_SESSION['error_message'] = "Failed to add admin.";
    }
    session_write_close();
    header("Location: admins.php");
    exit();
}

// Build role-based WHERE clause for fetching admins
$whereClause = "";
$params = [];

if ($userRole === 'secretary') {
    $whereClause = "WHERE role IN ('staff')";
} elseif ($userRole === 'staff') {
    $whereClause = "WHERE admin_id = ?";
    $params[] = $userId;
}
// Captain sees all (no WHERE clause)

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get total count for pagination
$total_query = "SELECT COUNT(*) as total FROM ADMIN $whereClause";
$total_stmt = $db->prepare($total_query);
if ($userRole === 'staff') {
    $total_stmt->execute([$userId]);
} else {
    $total_stmt->execute($params);
}
$total_admins = (int)$total_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = (int)ceil($total_admins / $limit);

// Fetch admins with pagination
$query = "SELECT * FROM ADMIN $whereClause ORDER BY 
          FIELD(role, 'captain', 'secretary', 'staff'), 
          first_name, last_name 
          LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($query);

// Bind parameters based on role
if ($userRole === 'staff') {
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
} else {
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
}

$stmt->execute();
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get role options for forms based on current user's role
$roleOptions = [];
if ($userRole === 'captain') {
    $roleOptions = ['captain', 'secretary', 'staff'];
} elseif ($userRole === 'secretary') {
    $roleOptions = ['staff'];
} else {
    $roleOptions = [$userRole]; // Staff can only see their own role
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AdminSoft - Admin Management</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    /* Reuse residents page styles with minor adjustments */
    .admins-container {
        max-width: 100%;
        margin: 0;
        padding: 30px;
        width: 100%;
        min-height: calc(100vh - 120px);
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    }
    
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
    
    .table-container {
        overflow-x: auto;
        width: 100%;
        flex: 1;
        background: white;
        border-radius: 0 0 20px 20px;
        position: relative;
    }
    
    .admins-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        min-height: 400px;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .admins-table th {
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
    
    .admins-table td {
        padding: 20px;
        text-align: left;
        border-bottom: 1px solid rgba(0,0,0,0.06);
        transition: all 0.3s ease;
        font-weight: 500;
        color: #495057;
    }
    
    .admins-table tbody tr {
        background: white;
        transition: all 0.4s ease;
        position: relative;
    }
    
    .admins-table tbody tr:hover {
        background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 100%);
        transform: translateX(5px);
        box-shadow: 0 8px 25px rgba(67, 97, 238, 0.15);
    }
    
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
    
    .role-badge {
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: inline-block;
        min-width: 80px;
        text-align: center;
    }
    
    .role-captain { 
        background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%); 
        color: white; 
    }
    .role-secretary { 
        background: linear-gradient(135deg, #7209b7 0%, #560bad 100%); 
        color: white; 
    }
    .role-staff { 
        background: linear-gradient(135deg, #f72585 0%, #b5179e 100%); 
        color: white; 
    }
    
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
    
    .admin-avatar {
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
    
    .admin-info {
        display: flex;
        align-items: center;
        gap: 18px;
    }
    
    .admin-name {
        font-weight: 700;
        color: #2c3e50;
        font-size: 1.05rem;
        margin-bottom: 4px;
    }
    
    .admin-username {
        font-size: 0.8rem;
        color: #6c757d;
        font-weight: 600;
        background: #f8f9fa;
        padding: 4px 8px;
        border-radius: 6px;
        display: inline-block;
    }
    
    /* Modal and other styles from residents.php */
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
    
    .modal-footer {
        padding: 20px 30px;
        border-top: 1px solid #e9ecef;
        display: flex;
        justify-content: flex-end;
        gap: 15px;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    }
    
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
    
    .notification.success { 
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%); 
    }
    .notification.error { 
        background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%); 
    }
    
    /* Card header layout fix */
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
    
    .page-indicator {
        padding: 8px 16px;
        background: white;
        border-radius: 8px;
        font-weight: 600;
        color: #495057;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .stats-container {
            grid-template-columns: 1fr;
        }
        
        .card-header {
            flex-direction: column;
            align-items: stretch;
        }
        
        .search-container {
            min-width: unset;
            width: 100%;
        }
        
        .action-buttons {
            justify-content: center;
        }
        
        .form-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="main-content">
        <div class="header">
            <h2 id="page-title">Admin Management</h2>
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
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <div class="stat-number"><?php echo $total_admins; ?></div>
                    <div class="stat-label">Total Admins</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-number"><?php echo count(array_filter($admins, function($a) { return $a['status'] == 'active'; })); ?></div>
                    <div class="stat-label">Active Admins</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-crown"></i>
                    </div>
                    <div class="stat-number"><?php echo count(array_filter($admins, function($a) { return $a['role'] == 'captain'; })); ?></div>
                    <div class="stat-label">Captains</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="stat-number"><?php echo count(array_filter($admins, function($a) { return $a['role'] == 'staff'; })); ?></div>
                    <div class="stat-label">Staff Members</div>
                </div>
            </div>

            <!-- Admins Table Card -->
            <div class="card">
                <div class="card-header">
                    <h3>Admins List</h3>
                    <div class="table-actions">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="adminSearch" class="form-control" placeholder="Search admins...">
                        </div>
                        <?php if ($userRole !== 'staff'): ?>
                        <button class="btn btn-primary" onclick="openAddModal()">
                            <i class="fas fa-user-plus"></i> Add Admin
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="table" id="adminsTable">
                            <thead>
                                <tr>
                                    <th>Admin</th>
                                    <th>Contact Info</th>
                                    <th>Role</th>
                                    <th>Last Login</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($admins as $admin): ?>
                                <tr data-admin-id="<?php echo $admin['admin_id']; ?>">
                                    <td>
                                        <div class="admin-info">
                                            <div class="admin-avatar">
                                                <?php echo strtoupper(substr($admin['first_name'], 0, 1) . substr($admin['last_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="admin-name"><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></div>
                                                <div class="admin-username">@<?php echo htmlspecialchars($admin['username']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($admin['email']); ?></div>
                                        <div style="font-size: 12px; color: #718096;"><?php echo htmlspecialchars($admin['contact_number'] ?: 'No contact'); ?></div>
                                    </td>
                                    <td>
                                        <span class="role-badge role-<?php echo $admin['role']; ?>">
                                            <?php echo ucfirst($admin['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $admin['last_login'] ? date('M j, Y g:i A', strtotime($admin['last_login'])) : 'Never'; ?></td>
                                    <td>
                                        <span class="status status-<?php echo $admin['status']; ?>">
                                            <?php echo ucfirst($admin['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="table-action-btn btn-warning" onclick="openEditModal(<?php echo $admin['admin_id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($userRole !== 'staff' && $admin['admin_id'] != $userId): ?>
                                            <button class="table-action-btn btn-danger" onclick="showDeleteConfirmation(<?php echo $admin['admin_id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Controls -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <div class="pagination-info">
                            Showing <?php echo min($limit, count($admins)); ?> of <?php echo $total_admins; ?> admins
                        </div>
                        <div class="pagination-controls">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>" class="pagination-btn">
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
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>" class="pagination-btn">
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

    <!-- Add Admin Modal -->
    <?php if ($userRole !== 'staff'): ?>
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-user-plus"></i> Add New Admin</h3>
                <span class="close" onclick="closeAddModal()">&times;</span>
            </div>
            <form method="POST" action="admins.php" id="addAdminForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="username">Username <span style="color: #f72585;">*</span></label>
                        <input type="text" id="username" name="username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password <span style="color: #f72585;">*</span></label>
                        <input type="password" id="password" name="password" class="form-control" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label for="first_name">First Name <span style="color: #f72585;">*</span></label>
                        <input type="text" id="first_name" name="first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name <span style="color: #f72585;">*</span></label>
                        <input type="text" id="last_name" name="last_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address <span style="color: #f72585;">*</span></label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="contact_number">Contact Number</label>
                        <input type="text" id="contact_number" name="contact_number" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="role">Role <span style="color: #f72585;">*</span></label>
                        <select id="role" name="role" class="form-control" required>
                            <?php foreach ($roleOptions as $roleOption): ?>
                                <option value="<?php echo $roleOption; ?>"><?php echo ucfirst($roleOption); ?></option>
                            <?php endforeach; ?>
                        </select>
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
                    <button type="submit" class="btn btn-primary" name="add_admin">Add Admin</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Edit Admin Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-edit"></i> Edit Admin</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <div class="modal-body" id="editModalBody">
                <div class="spinner"></div>
                <div style="text-align: center;">Loading admin data...</div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-exclamation-triangle" style="color: #f72585;"></i> Confirm Deletion</h3>
                <span class="close" onclick="cancelDelete()">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this admin? This action cannot be undone.</p>
                <div style="display: flex; gap: 10px; justify-content: center; margin-top: 20px;">
                    <button type="button" class="btn btn-outline" onclick="cancelDelete()">No, Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="confirmDelete()">Yes, Delete</button>
                </div>
            </div>
        </div>
    </div>

<script>
    let adminToDelete = null;

    // Auto-remove notifications after 5 seconds
    setTimeout(() => {
        document.querySelectorAll('.notification').forEach(notification => {
            setTimeout(() => notification.remove(), 800);
        });
    }, 5000);

    // Search functionality
    document.getElementById('adminSearch').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('#adminsTable tbody tr');
        
        rows.forEach(row => {
            const name = row.cells[0].textContent.toLowerCase();
            const email = row.cells[1].textContent.toLowerCase();
            const role = row.cells[2].textContent.toLowerCase();
            const usernameEl = row.querySelector('.admin-username');
            const username = usernameEl ? usernameEl.textContent.toLowerCase() : '';
            
            if (name.includes(searchTerm) || email.includes(searchTerm) || role.includes(searchTerm) || username.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });

    // Modal functions
    function openAddModal() {
        document.getElementById('addModal').style.display = 'flex';
    }

    function closeAddModal() {
        document.getElementById('addModal').style.display = 'none';
    }

    function openEditModal(adminId) {
        document.getElementById('editModal').style.display = 'flex';
        document.getElementById('editModalBody').innerHTML = `
            <div class="spinner"></div>
            <div style="text-align: center;">Loading admin data...</div>
        `;
        
        fetch(`../ajax/get_admin_details.php?id=${adminId}`)
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (data.success && data.admin) {
                    const admin = data.admin;
                    const isCurrentUser = admin.admin_id == <?php echo $userId; ?>;
                    const canEditRole = <?php echo $userRole === 'captain' ? 'true' : 'false'; ?> && !isCurrentUser;
                    
                    document.getElementById('editModalBody').innerHTML = `
                        <form method="POST" action="admins.php">
                            <input type="hidden" name="admin_id" value="${admin.admin_id}">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="first_name_edit">First Name <span style="color: #f72585;">*</span></label>
                                    <input type="text" id="first_name_edit" name="first_name" value="${admin.first_name}" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="last_name_edit">Last Name <span style="color: #f72585;">*</span></label>
                                    <input type="text" id="last_name_edit" name="last_name" value="${admin.last_name}" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="email_edit">Email Address <span style="color: #f72585;">*</span></label>
                                    <input type="email" id="email_edit" name="email" value="${admin.email}" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="contact_number_edit">Contact Number</label>
                                    <input type="text" id="contact_number_edit" name="contact_number" value="${admin.contact_number || ''}" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label for="role_edit">Role <span style="color: #f72585;">*</span></label>
                                    <select id="role_edit" name="role" class="form-control" required ${isCurrentUser || !canEditRole ? 'disabled' : ''}>
                                        <?php foreach ($roleOptions as $roleOption): ?>
                                            <option value="<?php echo $roleOption; ?>"><?php echo ucfirst($roleOption); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    ${isCurrentUser ? '<input type="hidden" name="role" value="' + admin.role + '">' : ''}
                                    ${!canEditRole && !isCurrentUser ? '<input type="hidden" name="role" value="' + admin.role + '">' : ''}
                                </div>
                                <div class="form-group">
                                    <label for="status_edit">Status</label>
                                    <select id="status_edit" name="status" class="form-control">
                                        <option value="active" ${admin.status === 'active' ? 'selected' : ''}>Active</option>
                                        <option value="inactive" ${admin.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline" onclick="closeEditModal()">Cancel</button>
                                <button type="submit" class="btn btn-primary" name="edit_admin">Update Admin</button>
                            </div>
                        </form>
                        <script>
                            // Set the selected role
                            document.getElementById('role_edit').value = '${admin.role}';
                        <\/script>
                    `;
                } else {
                    document.getElementById('editModalBody').innerHTML = `
                        <div class="notification error show">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <span>Error: ${data.message || 'Admin not found'}</span>
                        </div>
                    `;
                }
            })
            .catch(error => {
                document.getElementById('editModalBody').innerHTML = `
                    <div class="notification error show">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <span>Error loading admin data: ${error.message}</span>
                    </div>
                `;
            });
    }

    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    function showDeleteConfirmation(adminId) {
        adminToDelete = adminId;
        document.getElementById('deleteConfirmationModal').style.display = 'flex';
    }

    function cancelDelete() {
        adminToDelete = null;
        document.getElementById('deleteConfirmationModal').style.display = 'none';
    }

    function confirmDelete() {
        if (adminToDelete) {
            window.location.href = `admins.php?delete_id=${adminToDelete}`;
        }
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    }

    // Auto-format contact numbers
    const contactInputs = ['contact_number', 'contact_number_edit'];
    contactInputs.forEach(inputId => {
        const input = document.getElementById(inputId);
        if (input) {
            input.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9+-\s]/g, '');
            });
        }
    });
</script>
</body>
</html>
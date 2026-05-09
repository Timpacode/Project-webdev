<?php
include '../includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Database connection
require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get current admin info from session
$current_admin_id = $_SESSION['admin_id'];
$current_admin_role = $_SESSION['role'] ?? 'Staff';

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get total count for pagination
$total_query = "SELECT COUNT(*) as total FROM admin";
$total_stmt = $db->prepare($total_query);
$total_stmt->execute();
$total_admins = $total_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_admins / $limit);

// Get admins data with pagination
$admins = [];
try {
    $query = "SELECT admin_id, first_name, last_name, username, email, role, contact_number, status, created_at 
              FROM admin 
              ORDER BY created_at DESC 
              LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $admins = [];
}

// Get stats
$stats = [];
try {
    // Total admins
    $query = "SELECT COUNT(*) as count FROM admin";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_admins'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Active admins
    $query = "SELECT COUNT(*) as count FROM admin WHERE status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['active_admins'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Captains
    $query = "SELECT COUNT(*) as count FROM admin WHERE role = 'Captain'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['captains'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats = ['total_admins' => 0, 'active_admins' => 0, 'captains' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Roles & Admin - BarangayHub</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Your original CSS styles remain exactly the same */
        :root {
            --primary: #4361ee;
            --primary-light: #4895ef;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --info: #4895ef;
            --dark: #212529;
            --light: #f8f9fa;
            --gray: #6c757d;
            --border: #e9ecef;
        }
        



         .action-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .action-btn:disabled:hover {
            transform: none !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15) !important;
        }
        
        .tooltip {
            position: relative;
            display: inline-block;
        }
        
        .tooltip .tooltiptext {
            visibility: hidden;
            width: 200px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 8px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 12px;
            font-weight: normal;
        }
        
        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
        
        .roles-container { 
            max-width: 100%; 
            margin: 0; 
            padding: 30px; 
            width: 100%; 
            min-height: calc(100vh - 120px); 
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); 
        }
        
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
        
   .table-container {
    overflow-x: auto;
    width: 100%;
    flex: 1;
    background: white;
    min-height: 10px;
    /* Hide scrollbar for all browsers */
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE and Edge */
}

.table-container::-webkit-scrollbar {
    display: none;
}
 .admins-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    min-height: 400px;
    /* Ensure table can still be scrolled */
    min-width: 800px; /* Minimum width to ensure content doesn't get too squeezed */
}
        
        .admins-table th { 
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
        
        .admins-table td { 
            padding: 18px 20px; 
            text-align: left; 
            border-bottom: 1px solid #f1f3f4; 
            transition: all 0.2s ease; 
        }
        
        .admins-table tbody tr { 
            background: white; 
            transition: all 0.3s ease; 
        }
        
        .admins-table tbody tr:hover { 
            background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 100%); 
            transform: scale(1.01); 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
        }
        
        .status { 
            padding: 8px 16px; 
            border-radius: 25px; 
            font-size: 0.75rem; 
            font-weight: 700; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); 
        }
        
        .status-active { 
            background: linear-gradient(135deg, #d4edda 0%, #c8e6c9 100%); 
            color: #155724; 
            border: 1px solid #c8e6c9; 
        }
        
        .status-inactive { 
            background: linear-gradient(135deg, #f8d7da 0%, #ffcdd2 100%); 
            color: #721c24; 
            border: 1px solid #ffcdd2; 
        }
        
        .role-badge { 
            padding: 8px 16px; 
            border-radius: 25px; 
            font-size: 0.75rem; 
            font-weight: 700; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); 
        }
        
        .role-captain { 
            background: linear-gradient(135deg, #d1ecf1 0%, #a8e6cf 100%); 
            color: #0c5460; 
            border: 1px solid #a8e6cf; 
        }
        
        .role-secretary { 
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); 
            color: #856404; 
            border: 1px solid #ffeaa7; 
        }
        
        .role-staff { 
            background: linear-gradient(135deg, #d1edf1 0%, #a8d8e6 100%); 
            color: #0c5460; 
            border: 1px solid #a8d8e6; 
        }
        
        .action-buttons { 
            display: flex; 
            gap: 8px; 
            justify-content: flex-start; 
        }
        
        .action-btn { 
            padding: 10px 14px; 
            border: none; 
            border-radius: 10px; 
            cursor: pointer; 
            font-size: 0.85rem; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            min-width: 40px; 
            height: 40px; 
            transition: all 0.3s ease; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.15); 
            position: relative; 
            overflow: hidden; 
        }
        
        .action-btn::before { 
            content: ''; 
            position: absolute; 
            top: 0; 
            left: -100%; 
            width: 100%; 
            height: 100%; 
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent); 
            transition: left 0.5s; 
        }
        
        .action-btn:hover::before { 
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
        
        .btn-primary { 
            background: linear-gradient(135deg, #007bff 0%, #6f42c1 100%); 
            color: white; 
        }
        
        .btn-outline { 
            background: transparent; 
            border: 2px solid #6c757d; 
            color: #6c757d; 
        }
        
        .btn-success:hover { 
            background: linear-gradient(135deg, #218838 0%, #1ea085 100%); 
            transform: translateY(-2px); 
            box-shadow: 0 4px 15px rgba(40,167,69,0.3); 
        }
        
        .btn-danger:hover { 
            background: linear-gradient(135deg, #c82333 0%, #d91a7a 100%); 
            transform: translateY(-2px); 
            box-shadow: 0 4px 15px rgba(220,53,69,0.3); 
        }
        
        .btn-primary:hover { 
            background: linear-gradient(135deg, #0056b3 0%, #5a2d9e 100%); 
            transform: translateY(-2px); 
            box-shadow: 0 4px 15px rgba(0,123,255,0.3); 
        }
        
        .btn-outline:hover { 
            background: #6c757d; 
            color: white; 
            transform: translateY(-2px); 
            box-shadow: 0 4px 15px rgba(108,117,125,0.3); 
        }
        
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
        
        /* Improved Modal Styles */
        .modal { 
            display: none; 
            position: fixed; 
            z-index: 1050; 
            left: 0; 
            top: 0; 
            width: 100%; 
            height: 100%; 
            background-color: rgba(0,0,0,0.6); 
            backdrop-filter: blur(5px); 
            align-items: center; 
            justify-content: center; 
            animation: fadeIn 0.3s ease; 
        }
        
        @keyframes fadeIn { 
            from { opacity: 0; } 
            to { opacity: 1; } 
        }
        
        .modal-content { 
            background: linear-gradient(135deg, #ffffff 0%, #fafbfc 100%); 
            border-radius: 20px; 
            width: 90%; 
            max-width: 600px; 
            max-height: 85vh; 
            overflow-y: auto; 
            box-shadow: 0 20px 60px rgba(0,0,0,0.3); 
            animation: slideInUp 0.3s ease; 
            border: 1px solid rgba(255,255,255,0.2); 
            position: relative;
        }
        
        @keyframes slideInUp { 
            from { opacity: 0; transform: translateY(50px); } 
            to { opacity: 1; transform: translateY(0); } 
        }
        
        .modal-header { 
            padding: 25px; 
            border-bottom: 1px solid #e9ecef; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); 
            border-radius: 20px 20px 0 0; 
            position: relative;
        }
        
        .modal-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
        }
        
        .modal-title { 
            margin: 0; 
            font-size: 1.4rem; 
            font-weight: 700; 
            color: #2c3e50; 
        }
        
        .close { 
            font-size: 1.5rem; 
            cursor: pointer; 
            color: #6c757d; 
            background: none; 
            border: none; 
            width: 40px; 
            height: 40px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            border-radius: 50%; 
            transition: all 0.3s ease; 
        }
        
        .close:hover { 
            background: #e9ecef; 
            color: #dc3545; 
            transform: rotate(90deg); 
        }
        
        .form-group { 
            margin-bottom: 20px; 
        }
        
        .form-label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 600; 
            color: #495057; 
        }
        
        .form-control { 
            width: 100%; 
            padding: 12px 16px; 
            border: 2px solid #e9ecef; 
            border-radius: 10px; 
            font-size: 14px; 
            transition: all 0.3s ease; 
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); 
        }
        
        .form-control:focus { 
            outline: none; 
            border-color: var(--primary); 
            box-shadow: 0 4px 15px rgba(0,123,255,0.15); 
        }
        
        .form-row { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 15px; 
        }
        
        .form-section {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f1f3f4;
        }
        
        .form-section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-section-title i {
            font-size: 1rem;
        }
        
        .confirmation-modal { 
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
        
        .confirmation-content { 
            background: linear-gradient(135deg, #ffffff 0%, #fafbfc 100%); 
            border-radius: 20px; 
            width: 90%; 
            max-width: 450px; 
            padding: 0; 
            box-shadow: 0 20px 60px rgba(0,0,0,0.3); 
            animation: slideInUp 0.3s ease; 
            border: 1px solid rgba(255,255,255,0.2); 
        }
        
        .confirmation-header { 
            padding: 25px; 
            border-bottom: 1px solid #e9ecef; 
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); 
            border-radius: 20px 20px 0 0; 
        }
        
        .confirmation-title { 
            margin: 0; 
            font-size: 1.4rem; 
            font-weight: 700; 
            color: #2c3e50; 
            text-align: center; 
        }
        
        .confirmation-body { 
            padding: 30px 25px; 
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
            padding: 12px 25px; 
            border: none; 
            border-radius: 10px; 
            cursor: pointer; 
            font-size: 0.95rem; 
            font-weight: 600; 
            min-width: 100px; 
            transition: all 0.3s ease; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
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
        
.notification { 
    position: fixed; 
    top: 20px; 
    right: 20px; 
    padding: 16px 24px; 
    border-radius: 12px; 
    color: white; 
    font-weight: 600; 
    z-index: 1070; 
    box-shadow: 0 8px 25px rgba(0,0,0,0.15); 
    animation: slideInRight 0.4s ease-out; 
    backdrop-filter: blur(10px); 
    border: 1px solid rgba(255,255,255,0.2); 
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 300px;
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
        
        .d-none { 
            display: none !important; 
        }
        
        .text-center { 
            text-align: center; 
        }
        
        .mb-0 { 
            margin-bottom: 0 !important; 
        }
        
        .mt-3 { 
            margin-top: 15px; 
        }

        /* Enhanced Form Validation Styles */
        .form-control.error {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }

        .form-control.success {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }

        .error-message {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 5px;
            display: none;
        }

        .error-message.show {
            display: block;
        }

        .password-strength {
            margin-top: 5px;
            font-size: 0.875rem;
        }

        .password-strength.weak {
            color: #dc3545;
        }

        .password-strength.medium {
            color: #ffc107;
        }

        .password-strength.strong {
            color: #28a745;
        }

        /* Loading State */
        .btn-loading {
            position: relative;
            color: transparent !important;
        }

        .btn-loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin-left: -10px;
            margin-top: -10px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Fixed Modal Footer Button Positioning */
        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            background: #f8f9fa;
            border-radius: 0 0 20px 20px;
        }

        .modal-footer .btn {
            min-width: 100px;
            padding: 12px 24px;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .modal-footer .btn-outline {
            background: transparent;
            border: 2px solid #6c757d;
            color: #6c757d;
        }

        .modal-footer .btn-outline:hover {
            background: #6c757d;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }

        .modal-footer .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .modal-footer .btn-success:hover {
            background: linear-gradient(135deg, #218838 0%, #1ea085 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        /* Ensure the form footer uses the same styling */
        #admin-form > div:last-child {
            padding: 20px 25px;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            background: #f8f9fa;
            border-radius: 0 0 20px 20px;
        }

        #admin-form > div:last-child .action-btn {
            min-width: 100px;
            padding: 12px 24px;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        /* Responsive design for mobile */
        @media (max-width: 768px) {
            .roles-container { padding: 20px 15px; }
            .stats-container { grid-template-columns: 1fr; gap: 15px; }
            .stat-card { padding: 25px 20px; }
            .stat-value { font-size: 36px; }
            .card-header { flex-direction: column; align-items: stretch; padding: 20px; gap: 15px; }
            .filter-controls { width: 100%; }
            .filter-select, .search-input { flex: 1; min-width: unset; width: 100%; }
            .admins-table th, .admins-table td { padding: 15px 12px; font-size: 0.85rem; }
            .action-buttons { flex-wrap: wrap; justify-content: center; }
            .action-btn { min-width: 36px; height: 36px; padding: 8px 10px; }
            .form-row { grid-template-columns: 1fr; }
            .confirmation-buttons { flex-direction: column; gap: 10px; }
            .confirmation-btn { width: 100%; }
            .pagination { flex-direction: column; gap: 15px; text-align: center; }
            
            .modal-footer,
            #admin-form > div:last-child {
                flex-direction: column;
                gap: 10px;
            }
            
            .modal-footer .btn,
            #admin-form > div:last-child .action-btn {
                width: 100%;
                min-width: unset;
            }
        }
    </style>
</head>
<body>
    <div class="roles-container">
        <!-- Stats Section -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-label">Total Admins</div>
                <div class="stat-value"><?php echo $stats['total_admins']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Active Admins</div>
                <div class="stat-value"><?php echo $stats['active_admins']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Captains</div>
                <div class="stat-value"><?php echo $stats['captains']; ?></div>
            </div>
        </div>
        
        <!-- Admins Table -->
        <div class="content-card">
            <div class="card-header">
                <h3 class="card-title">Admin Users</h3>
                <div class="filter-controls">
                    <select class="filter-select" id="admin-status-filter">
                        <option value="all">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <select class="filter-select" id="admin-role-filter">
                        <option value="all">All Roles</option>
                        <option value="Captain">Captain</option>
                        <option value="Secretary">Secretary</option>
                        <option value="Staff">Staff</option>
                    </select>
                    <input type="text" class="search-input" id="admin-search" placeholder="Search admins...">
                    <?php if ($current_admin_role !== 'Staff'): ?>
                    <button class="btn btn-primary action-btn" id="add-admin-btn">
                        <i class="fas fa-plus"></i> Add Admin
                    </button>
                    <?php else: ?>
                    <button class="btn btn-primary action-btn" id="add-admin-btn" disabled>
                        <i class="fas fa-plus"></i> Add Admin
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="table-container">
                <table class="admins-table">
                    <thead>
                        <tr>
                            <th>Admin ID</th>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $admin):
                            $status = isset($admin['status']) ? $admin['status'] : 'active';
                            $role = isset($admin['role']) ? $admin['role'] : 'Staff';
                            $status_class = strtolower($status);
                            $role_class = strtolower($role);
                            $is_current_user = ($admin['admin_id'] == $current_admin_id);
                        ?>
                        <tr class="admin-row" data-status="<?php echo $status_class; ?>" data-role="<?php echo $role_class; ?>" data-current-user="<?php echo $is_current_user ? 'true' : 'false'; ?>">
                            <td style="font-weight: 700; color: #007bff;"><?php echo htmlspecialchars($admin['admin_id']); ?></td>
                            <td style="font-weight: 600; color: #495057;"><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($admin['username']); ?></td>
                            <td><?php echo htmlspecialchars($admin['email']); ?></td>
                            <td><?php echo htmlspecialchars($admin['contact_number'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="role-badge role-<?php echo $role_class; ?>">
                                    <?php echo ucfirst($role); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status status-<?php echo $status_class; ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <?php if ($current_admin_role !== 'Staff'): ?>
                                        <button class="action-btn btn-primary edit-admin" data-id="<?php echo $admin['admin_id']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if (!$is_current_user): ?>
                                        <button class="action-btn btn-danger delete-admin" data-id="<?php echo $admin['admin_id']; ?>" data-name="<?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php else: ?>
                                        <div class="tooltip">
                                            <button class="action-btn btn-danger" disabled>
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <span class="tooltiptext">You cannot delete your own account</span>
                                        </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="tooltip">
                                            <button class="action-btn btn-primary" disabled>
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <span class="tooltiptext">Staff cannot edit admin accounts</span>
                                        </div>
                                        <div class="tooltip">
                                            <button class="action-btn btn-danger" disabled>
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <span class="tooltiptext">Staff cannot delete admin accounts</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($admins)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-user-shield"></i>
                                    <h4>No Admins Found</h4>
                                    <p>There are no admin users in the system yet.</p>
                                    <?php if ($current_admin_role !== 'Staff'): ?>
                                    <button class="btn btn-primary action-btn mt-3" id="add-admin-empty-btn">
                                        <i class="fas fa-plus"></i> Add First Admin
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-primary action-btn mt-3" disabled>
                                        <i class="fas fa-plus"></i> Add First Admin
                                    </button>
                                    <p class="mt-2" style="color: #6c757d; font-size: 0.9rem;">Staff members cannot add new admins</p>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <div class="pagination-info">
                    Showing <?php echo count($admins); ?> of <?php echo $total_admins; ?> admins
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
                    
                    <span class="page-indicator">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                    
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
    
    <!-- Add/Edit Admin Modal -->
    <div class="modal" id="admin-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modal-title">Add New Admin</h3>
                <button class="close">&times;</button>
            </div>
            <form id="admin-form">
                <div style="padding: 25px;">
                    <input type="hidden" id="admin-id" name="admin_id">
                    
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-user"></i> Personal Information
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="first-name">First Name</label>
                                <input type="text" class="form-control" id="first-name" name="first_name" required>
                                <div class="error-message" id="first-name-error"></div>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="last-name">Last Name</label>
                                <input type="text" class="form-control" id="last-name" name="last_name" required>
                                <div class="error-message" id="last-name-error"></div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="contact-number">Contact Number</label>
                            <input type="text" class="form-control" id="contact-number" name="contact_number">
                            <div class="error-message" id="contact-number-error"></div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-key"></i> Account Information
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="username">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                            <div class="error-message" id="username-error"></div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                            <div class="error-message" id="email-error"></div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="password">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <div class="error-message" id="password-error"></div>
                                <div class="password-strength" id="password-strength"></div>
                                <small style="color: #6c757d; font-size: 0.8rem;">Minimum 6 characters</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="confirm-password">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm-password" name="confirm_password" required>
                                <div class="error-message" id="confirm-password-error"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-cog"></i> Role & Status
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="role">Role</label>
                                <select class="form-control" id="role" name="role" required>
                                    <option value="Staff">Staff</option>
                                    <option value="Secretary">Secretary</option>
                                    <option value="Captain">Captain</option>
                                </select>
                                <div class="error-message" id="role-error"></div>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="status">Status</label>
                                <select class="form-control" id="status" name="status" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                                <div class="error-message" id="status-error"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" id="cancel-btn">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success" id="save-btn">
                        <i class="fas fa-save"></i> Save Admin
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="confirmation-modal" id="delete-modal">
        <div class="confirmation-content">
            <div class="confirmation-header">
                <h3 class="confirmation-title">Confirm Deletion</h3>
            </div>
            <div class="confirmation-body">
                <p class="confirmation-message" id="delete-message">
                    Are you sure you want to delete this admin user?
                </p>
                <div class="confirmation-buttons">
                    <button class="confirmation-btn btn-cancel" id="cancel-delete">Cancel</button>
                    <button class="confirmation-btn btn-confirm-danger" id="confirm-delete">Delete</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Staff Permission Modal -->
    <div class="confirmation-modal" id="staff-permission-modal">
        <div class="confirmation-content">
            <div class="confirmation-header">
                <h3 class="confirmation-title">Permission Required</h3>
            </div>
            <div class="confirmation-body">
                <div class="text-center mb-3">
                    <i class="fas fa-shield-alt" style="font-size: 48px; color: #ffc107; margin-bottom: 15px;"></i>
                </div>
                <p class="confirmation-message" id="staff-permission-message">
                    You don't have sufficient permissions to perform this action. Please contact your administrator.
                </p>
                <div class="confirmation-buttons">
                    <button class="confirmation-btn btn-cancel" id="close-staff-modal">OK</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Notification -->
    <div class="notification d-none" id="notification"></div><script>
// Pass PHP variables to JavaScript
const CURRENT_USER_ROLE = '<?php echo $current_admin_role; ?>';
const CURRENT_USER_ID = '<?php echo $current_admin_id; ?>';

document.addEventListener('DOMContentLoaded', function() {
    // Modal elements
    const adminModal = document.getElementById('admin-modal');
    const deleteModal = document.getElementById('delete-modal');
    const staffPermissionModal = document.getElementById('staff-permission-modal');
    const closeButtons = document.querySelectorAll('.close, #cancel-btn, #cancel-delete, #close-staff-modal');
    const addAdminBtn = document.getElementById('add-admin-btn');
    const addAdminEmptyBtn = document.getElementById('add-admin-empty-btn');
    const adminForm = document.getElementById('admin-form');
    const confirmDeleteBtn = document.getElementById('confirm-delete');
    const notification = document.getElementById('notification');
    
    // Filter elements
    const statusFilter = document.getElementById('admin-status-filter');
    const roleFilter = document.getElementById('admin-role-filter');
    const searchInput = document.getElementById('admin-search');
    
    // Current admin being edited/deleted
    let currentAdminId = null;
    let isSubmitting = false;
    
    // Check if current user is staff
    function isCurrentUserStaff() {
        return CURRENT_USER_ROLE === 'Staff';
    }
    
    // Check if user can perform admin actions
    function canPerformAdminAction() {
        if (isCurrentUserStaff()) {
            showStaffPermissionModal('This action requires Captain or Secretary privileges. Please contact your administrator.');
            return false;
        }
        return true;
    }
    
    // Show staff permission modal
    function showStaffPermissionModal(message) {
        const modal = document.getElementById('staff-permission-modal');
        const messageElement = document.getElementById('staff-permission-message');
        
        if (message) {
            messageElement.textContent = message;
        }
        
        modal.style.display = 'flex';
    }
    
    // Open Add Admin Modal
    function openAddModal() {
        if (!canPerformAdminAction()) {
            return;
        }
        
        document.getElementById('modal-title').textContent = 'Add New Admin';
        adminForm.reset();
        document.getElementById('admin-id').value = '';
        document.getElementById('password').required = true;
        document.getElementById('confirm-password').required = true;
        
        // Clear error messages
        clearErrorMessages();
        
        adminModal.style.display = 'flex';
        
        // Focus on first input
        setTimeout(() => {
            document.getElementById('first-name').focus();
        }, 300);
    }
    
    // Open Edit Admin Modal
    function openEditModal(adminId) {
        if (!canPerformAdminAction()) {
            return;
        }
        
        // Show loading state
        const saveBtn = document.getElementById('save-btn');
        saveBtn.classList.add('btn-loading');
        saveBtn.disabled = true;
         
        fetch(`../ajax/get_admin.php?id=${adminId}`)
            .then(response => {
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Failed to parse JSON:', text);
                        throw new Error('Server returned invalid JSON');
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    const admin = data.admin;
                    document.getElementById('modal-title').textContent = 'Edit Admin';
                    document.getElementById('admin-id').value = admin.admin_id;
                    document.getElementById('first-name').value = admin.first_name;
                    document.getElementById('last-name').value = admin.last_name;
                    document.getElementById('username').value = admin.username;
                    document.getElementById('email').value = admin.email;
                    document.getElementById('contact-number').value = admin.contact_number || '';
                    document.getElementById('role').value = admin.role;
                    document.getElementById('status').value = admin.status;
                    
                    // Make password fields optional for editing
                    document.getElementById('password').required = false;
                    document.getElementById('confirm-password').required = false;
                    
                    // Clear password fields
                    document.getElementById('password').value = '';
                    document.getElementById('confirm-password').value = '';
                    
                    // Clear error messages
                    clearErrorMessages();

                    adminModal.style.display = 'flex';
                } else {
                    showNotification(data.message || 'Failed to load admin data', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Failed to load admin data: ' + error.message, 'error');
            })
            .finally(() => {
                saveBtn.classList.remove('btn-loading');
                saveBtn.disabled = false;
            });
    }
    
    // Open Delete Confirmation Modal
    function openDeleteModal(adminId, adminName) {
        // Check if trying to delete own account
        const row = document.querySelector(`.delete-admin[data-id="${adminId}"]`)?.closest('.admin-row');
        if (row) {
            const isOwnAccount = row.getAttribute('data-current-user') === 'true';
            
            if (isOwnAccount) {
                showNotification('You cannot delete your own account', 'error');
                return;
            }
        }
        
        // Check permissions for staff
        if (!canPerformAdminAction()) {
            return;
        }
        
        currentAdminId = adminId;
        document.getElementById('delete-message').textContent = 
            `Are you sure you want to delete the admin user "${adminName}"? This action cannot be undone.`;
        deleteModal.style.display = 'flex';
    }
    
    // Close Modals
    function closeModals() {
        adminModal.style.display = 'none';
        deleteModal.style.display = 'none';
        staffPermissionModal.style.display = 'none';
        currentAdminId = null;
        isSubmitting = false;
        
        // Reset form
        adminForm.reset();
        clearErrorMessages();
    }
    
    // Show Notification with improved timing
    function showNotification(message, type = 'info', duration = 5000) {
        notification.textContent = message;
        notification.className = `notification ${type}`;
        notification.classList.remove('d-none');
        
        setTimeout(() => {
            notification.classList.add('d-none');
        }, duration);
    }
    
    // Clear all error messages
    function clearErrorMessages() {
        const errorMessages = document.querySelectorAll('.error-message');
        errorMessages.forEach(msg => {
            msg.classList.remove('show');
        });
        
        const formControls = document.querySelectorAll('.form-control');
        formControls.forEach(control => {
            control.classList.remove('error', 'success');
        });
    }
    
    // Show field error
    function showFieldError(fieldId, message) {
        const field = document.getElementById(fieldId);
        const errorElement = document.getElementById(`${fieldId}-error`);
        
        if (field && errorElement) {
            field.classList.add('error');
            field.classList.remove('success');
            errorElement.textContent = message;
            errorElement.classList.add('show');
        }
    }
    
    // Show field success
    function showFieldSuccess(fieldId) {
        const field = document.getElementById(fieldId);
        const errorElement = document.getElementById(`${fieldId}-error`);
        
        if (field && errorElement) {
            field.classList.add('success');
            field.classList.remove('error');
            errorElement.classList.remove('show');
        }
    }
    
    // Validate form
    function validateForm() {
        let isValid = true;
        const isEdit = document.getElementById('admin-id').value !== '';
        
        // Validate required fields
        const requiredFields = ['first-name', 'last-name', 'username', 'email', 'role', 'status'];
        requiredFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field && !field.value.trim()) {
                showFieldError(fieldId, 'This field is required');
                isValid = false;
            } else {
                showFieldSuccess(fieldId);
            }
        });
        
        // Validate email format
        const emailField = document.getElementById('email');
        if (emailField && emailField.value.trim()) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(emailField.value)) {
                showFieldError('email', 'Please enter a valid email address');
                isValid = false;
            }
        }
        
        // Validate password for new admin
        if (!isEdit) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            
            if (!password) {
                showFieldError('password', 'Password is required');
                isValid = false;
            } else if (password.length < 6) {
                showFieldError('password', 'Password must be at least 6 characters');
                isValid = false;
            } else {
                showFieldSuccess('password');
            }
            
            if (!confirmPassword) {
                showFieldError('confirm-password', 'Please confirm your password');
                isValid = false;
            } else if (password !== confirmPassword) {
                showFieldError('confirm-password', 'Passwords do not match');
                isValid = false;
            } else {
                showFieldSuccess('confirm-password');
            }
        }
        
        // Validate password for edit if provided
        if (isEdit) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            
            if (password || confirmPassword) {
                if (password.length < 6) {
                    showFieldError('password', 'Password must be at least 6 characters');
                    isValid = false;
                } else {
                    showFieldSuccess('password');
                }
                
                if (password !== confirmPassword) {
                    showFieldError('confirm-password', 'Passwords do not match');
                    isValid = false;
                } else {
                    showFieldSuccess('confirm-password');
                }
            }
        }
        
        return isValid;
    }
    
    // Real-time validation
    function setupRealTimeValidation() {
        const formControls = document.querySelectorAll('.form-control');
        formControls.forEach(control => {
            control.addEventListener('blur', function() {
                validateForm();
            });
            
            // Clear error on input
            control.addEventListener('input', function() {
                const fieldId = this.id;
                const errorElement = document.getElementById(`${fieldId}-error`);
                
                if (errorElement) {
                    this.classList.remove('error');
                    errorElement.classList.remove('show');
                }
            });
        });
        
        // Password strength indicator
        const passwordField = document.getElementById('password');
        if (passwordField) {
            passwordField.addEventListener('input', function() {
                const strengthElement = document.getElementById('password-strength');
                if (strengthElement) {
                    const password = this.value;
                    let strength = 'weak';
                    let message = 'Weak password';
                    
                    if (password.length >= 8) {
                        strength = 'medium';
                        message = 'Medium strength';
                    }
                    
                    if (password.length >= 10 && /[A-Z]/.test(password) && /[0-9]/.test(password)) {
                        strength = 'strong';
                        message = 'Strong password';
                    }
                    
                    strengthElement.textContent = message;
                    strengthElement.className = `password-strength ${strength}`;
                }
            });
        }
    }
    
    // Filter Admins
    function filterAdmins() {
        const statusValue = statusFilter.value;
        const roleValue = roleFilter.value;
        const searchValue = searchInput.value.toLowerCase();
        
        const rows = document.querySelectorAll('.admin-row');
        
        rows.forEach(row => {
            const status = row.getAttribute('data-status');
            const role = row.getAttribute('data-role');
            const name = row.cells[1].textContent.toLowerCase();
            const username = row.cells[2].textContent.toLowerCase();
            const email = row.cells[3].textContent.toLowerCase();
            
            const statusMatch = statusValue === 'all' || status === statusValue;
            const roleMatch = roleValue === 'all' || role === roleValue.toLowerCase();
            const searchMatch = name.includes(searchValue) || 
                              username.includes(searchValue) || 
                              email.includes(searchValue);
            
            if (statusMatch && roleMatch && searchMatch) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
    
    // Event Listeners
    addAdminBtn?.addEventListener('click', openAddModal);
    addAdminEmptyBtn?.addEventListener('click', openAddModal);
    
    // Edit and Delete button event listeners
    document.addEventListener('click', function(e) {
        if (e.target.closest('.edit-admin')) {
            const button = e.target.closest('.edit-admin');
            const adminId = button.getAttribute('data-id');
            openEditModal(adminId);
        }
        
        if (e.target.closest('.delete-admin')) {
            const button = e.target.closest('.delete-admin');
            const adminId = button.getAttribute('data-id');
            const adminName = button.getAttribute('data-name');
            openDeleteModal(adminId, adminName);
        }
    });
    
    closeButtons.forEach(button => {
        button.addEventListener('click', closeModals);
    });
    
    // Form Submission
    adminForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (isSubmitting) return;
        
        // Validate form
        if (!validateForm()) {
            showNotification('Please fix the errors in the form', 'error');
            return;
        }
        
        isSubmitting = true;
        const saveBtn = document.getElementById('save-btn');
        saveBtn.classList.add('btn-loading');
        saveBtn.disabled = true;
        
        const formData = new FormData(this);
        const isEdit = document.getElementById('admin-id').value !== '';
        
        // For edit, if password is empty, remove it from formData
        if (isEdit) {
            const password = document.getElementById('password').value;
            if (!password) {
                formData.delete('password');
                formData.delete('confirm_password');
            }
        }
        
        fetch(`../ajax/${isEdit ? 'update_admin.php' : 'add_admin.php'}`, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            return response.text().then(text => {
                try {
                    const data = JSON.parse(text);
                    return { success: true, data: data };
                } catch (e) {
                    if (text.includes('success') || text.toLowerCase().includes('successfully')) {
                        return { success: true, data: { success: true, message: 'Operation completed successfully' } };
                    } else {
                        return { success: false, error: 'Invalid response from server: ' + text.substring(0, 100) };
                    }
                }
            });
        })
        .then(result => {
            if (result.success) {
                const data = result.data;
                if (data.success) {
                    showNotification(data.message || 'Admin saved successfully!', 'success', 4000);
                    
                    // Close modal after showing success message
                    setTimeout(() => {
                        closeModals();
                        // Refresh immediately after closing modal
                        location.reload();
                    }, 1000); // Refresh after 1000ms (1 second)
                    
                } else {
                    showNotification(data.message || 'Failed to save admin', 'error', 5000);
                }
            } else {
                showNotification(result.error, 'error', 5000);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred while saving admin: ' + error.message, 'error', 5000);
        })
        .finally(() => {
            saveBtn.classList.remove('btn-loading');
            saveBtn.disabled = false;
            isSubmitting = false;
        });
    });
    
    // Delete Confirmation
    confirmDeleteBtn.addEventListener('click', function() {
        if (!currentAdminId) return;
        
        const formData = new FormData();
        formData.append('id', currentAdminId);
        
        // Show loading state
        confirmDeleteBtn.classList.add('btn-loading');
        confirmDeleteBtn.disabled = true;
        
        fetch('../ajax/delete_admin.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    if (text.includes('success') || text.toLowerCase().includes('deleted')) {
                        return { success: true, message: 'Admin deleted successfully' };
                    } else {
                        return { success: false, message: 'Invalid response from server' };
                    }
                }
            });
        })
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success', 4000);
                
                // Close modal after showing success message
                setTimeout(() => {
                    closeModals();
                    // Refresh immediately after closing modal
                    location.reload();
                }, 1000); // Refresh after 1000ms (1 second)
            } else {
                showNotification(data.message, 'error', 5000);
                closeModals();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred while deleting admin', 'error', 5000);
            closeModals();
        })
        .finally(() => {
            confirmDeleteBtn.classList.remove('btn-loading');
            confirmDeleteBtn.disabled = false;
        });
    });
    
    // Filter Event Listeners
    statusFilter.addEventListener('change', filterAdmins);
    roleFilter.addEventListener('change', filterAdmins);
    searchInput.addEventListener('input', filterAdmins);
    
    // Close modals when clicking outside
    window.addEventListener('click', function(e) {
        if (e.target === adminModal || e.target === deleteModal || e.target === staffPermissionModal) {
            closeModals();
        }
    });
    
    // Setup real-time validation
    setupRealTimeValidation();
    
    // Initialize filters
    filterAdmins();
});
</script>
</body>
</html>

<?php
// Make sure session is started at the very beginning

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

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filter setup
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build WHERE clause for filters
// Build WHERE clause for filters - EXCLUDE REJECTED REQUESTS
$where_conditions = ["r.status != 'completed'", "r.status != 'rejected'"];
$query_params = [];

// Apply status filter
if ($filter !== 'all' && in_array($filter, ['pending', 'approved', 'rejected'])) {
    $where_conditions[] = "r.status = :status";
    $query_params[':status'] = $filter;
}

// Apply search filter
if (!empty($search)) {
    $where_conditions[] = "(r.resident_name LIKE :search OR r.request_code LIKE :search OR dt.name LIKE :search)";
    $query_params[':search'] = "%$search%";
}

// Build final WHERE clause
$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination with filters
$total_query = "SELECT COUNT(*) as total FROM request r JOIN document_type dt ON r.document_type_id = dt.type_id WHERE $where_clause";
$total_stmt = $db->prepare($total_query);
foreach ($query_params as $key => $value) {
    $total_stmt->bindValue($key, $value);
}
$total_stmt->execute();
$total_requests = $total_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_requests / $limit);

// Get requests data with pagination and filters
$requests = [];
try {
    $query = "SELECT r.request_id, r.status, r.request_code, r.resident_name, dt.name as document_type, r.request_date 
              FROM request r 
              JOIN document_type dt ON r.document_type_id = dt.type_id 
              WHERE $where_clause
              ORDER BY r.request_date DESC 
              LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);
    
    // Bind filter parameters
    foreach ($query_params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    // Bind pagination parameters
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $requests = [];
}

// Log the requests data for debugging
error_log(print_r($requests, true));

// Get stats
$stats = [];
try {
    // Approved today
    $query = "SELECT COUNT(*) as count FROM request WHERE DATE(processed_date) = CURDATE() AND STATUS = 'approved'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['approved_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Total pending requests
    $query = "SELECT COUNT(*) as count FROM request WHERE STATUS = 'pending'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_pending'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Completion rate percentage
    $query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM request";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $completion_rate = $result['total'] > 0 ? round(($result['completed'] / $result['total']) * 100) : 0;
    $stats['completion_rate'] = $completion_rate;
} catch (PDOException $e) {
    $stats = ['approved_today' => 0, 'total_pending' => 0, 'completion_rate' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Requests - BarangayHub</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Your existing CSS styles remain the same */
        /* Main Container */
        .requests-container {
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
    width: 100%;
    flex: 1;
    background: white;
    min-height: 10px;
    /* Remove scrolling entirely and make table fit container */
    overflow: visible;
}

/* Hide scrollbar for Chrome, Safari and Opera */
.table-container::-webkit-scrollbar {
    display: none;
}
        
.requests-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    min-height: 400px;
    /* Ensure table uses full width without scrolling */
    table-layout: fixed;
}
        
        .requests-table th {
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
        
        .requests-table td {
            padding: 18px 20px;
            text-align: left;
            border-bottom: 1px solid #f1f3f4;
            transition: all 0.2s ease;
        }
        
        .requests-table tbody tr {
            background: white;
            transition: all 0.3s ease;
        }
        
        .requests-table tbody tr:hover {
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
        
        .status-pending { 
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); 
            color: #856404; 
            border: 1px solid #ffeaa7;
        }
        .status-approved { 
            background: linear-gradient(135deg, #d1ecf1 0%, #a8e6cf 100%); 
            color: #0c5460; 
            border: 1px solid #a8e6cf;
        }
        .status-completed { 
            background: linear-gradient(135deg, #d4edda 0%, #c8e6c9 100%); 
            color: #155724; 
            border: 1px solid #c8e6c9;
        }
        .status-rejected { 
            background: linear-gradient(135deg, #f8d7da 0%, #ffcdd2 100%); 
            color: #721c24; 
            border: 1px solid #ffcdd2;
        }
        
        /* Action Buttons */
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
        
        /* Modal Styles */
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
            max-width: 500px;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideInUp 0.3s ease;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            padding: 25px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px 20px 0 0;
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
        
        /* Confirmation Modal */
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
        
        .btn-confirm-primary {
            background: linear-gradient(135deg, #007bff 0%, #6f42c1 100%);
            color: white;
        }
        
        .btn-confirm-primary:hover {
            background: linear-gradient(135deg, #0056b3 0%, #5a2d9e 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,123,255,0.3);
        }
        
        /* Notification Modal Styles */
        .notification-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
            backdrop-filter: blur(5px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            animation: fadeIn 0.3s ease;
        }
        
        .notification-content {
            background: linear-gradient(135deg, #ffffff 0%, #fafbfc 100%);
            border-radius: 20px;
            width: 90%;
            max-width: 400px;
            padding: 0;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideInUp 0.3s ease;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .notification-header {
            padding: 25px;
            border-bottom: 1px solid #e9ecef;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px 20px 0 0;
            text-align: center;
        }
        
        .notification-title {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 700;
            color: #2c3e50;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .notification-body {
            padding: 30px 25px;
            text-align: center;
        }
        
        .notification-message {
            font-size: 1.1rem;
            margin-bottom: 30px;
            color: #495057;
            line-height: 1.6;
        }
        
        .notification-buttons {
            display: flex;
            justify-content: center;
        }
        
        .notification-btn {
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            min-width: 100px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
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
            .requests-container {
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
            .requests-container {
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
            
            .requests-table th,
            .requests-table td {
                padding: 15px 12px;
                font-size: 0.85rem;
            }
            
            .action-buttons {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .action-btn {
                min-width: 36px;
                height: 36px;
                padding: 8px 10px;
            }
            
            .confirmation-buttons {
                flex-direction: column;
                gap: 10px;
            }
            
            .confirmation-btn {
                width: 100%;
            }
            
            .pagination {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
        
        @media (max-width: 480px) {
            .requests-container {
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
            
            .requests-table {
                font-size: 0.8rem;
            }
            
            .requests-table th,
            .requests-table td {
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
        
        /* Row update animation */
        .row-updating {
            opacity: 0.7;
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%) !important;
        }
        
        .row-updated {
            animation: highlightUpdate 2s ease;
        }
        
        @keyframes highlightUpdate {
            0% { background: linear-gradient(135deg, #d4edda 0%, #c8e6c9 100%); }
            100% { background: white; }
        }
    </style>
</head>
<body>
    <div class="requests-container">
<div class="stats-container">
    <div class="stat-card">
        <div class="stat-label">Approved Today</div>
        <div class="stat-value"><?php echo $stats['approved_today']; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Completion Rate</div>
        <div class="stat-value"><?php echo $stats['completion_rate']; ?>%</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Pending</div>
        <div class="stat-value"><?php echo $stats['total_pending']; ?></div>
    </div>
</div>
        
        <!-- Requests Table -->
        <div class="content-card">
            <div class="card-header">
                <h3 class="card-title">Recent Requests</h3>
                <div class="filter-controls">
                    <select class="filter-select" id="request-status-filter">
                        <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                    <input type="text" class="search-input" id="request-search" placeholder="Search requests..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            
            <div class="table-container">
                <table class="requests-table">
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Resident</th>
                            <th>Document Type</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="requests-table-body">
              <?php foreach ($requests as $request):
    $status = isset($request['status']) ? $request['status'] : 'pending';

    // Skip rows with status 'completed' OR 'rejected'
    if ($status == 'completed' || $status == 'rejected') {
        continue;
    }

    $status_class = strtolower($status);
?>
                        <tr class="request-row" data-request-id="<?php echo htmlspecialchars($request['request_id']); ?>" data-status="<?php echo $status_class; ?>">
                            <td style="font-weight: 700; color: #007bff;"><?php echo htmlspecialchars($request['request_code']); ?></td>
                            <td style="font-weight: 600; color: #495057;"><?php echo htmlspecialchars($request['resident_name']); ?></td>
                            <td><?php echo htmlspecialchars($request['document_type']); ?></td>
                            <td style="color: #6c757d;"><?php echo date('M j, Y', strtotime($request['request_date'])); ?></td>
                            <td>
                                <span class="status status-<?php echo $status_class; ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <?php if ($status == 'pending'): ?>
                                        <!-- Approve and Reject buttons -->
                                        <button class="btn btn-success action-btn approve-request" 
                                                data-id="<?php echo htmlspecialchars($request['request_id']); ?>" 
                                                title="Approve">
                                            Approve
                                        </button>
                                        <button class="btn btn-danger action-btn reject-request" 
                                                data-id="<?php echo htmlspecialchars($request['request_id']); ?>" 
                                                title="Reject">
                                            Reject
                                        </button>
                                    <?php elseif ($status == 'approved'): ?>
                                        <!-- Complete button -->
                                        <button class="btn btn-primary action-btn complete-request" 
                                                data-id="<?php echo htmlspecialchars($request['request_id']); ?>" 
                                                title="Complete">
                                            Complete
                                        </button>
                                    <?php endif; ?>
                                    <!-- View button -->
                                    <button class="btn btn-outline action-btn view-request" 
                                            data-id="<?php echo htmlspecialchars($request['request_id']); ?>" 
                                            title="View">
                                        View
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <h4>No Requests Found</h4>
                                        <p>When residents submit document requests, they will appear here for review.</p>
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
                    Showing <?php echo min($limit, count($requests)); ?> of <?php echo $total_requests; ?> requests
                </div>
                <div class="pagination-controls">
                    <?php 
                    // Get current filter and search values
                    $current_filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
                    $current_search = isset($_GET['search']) ? $_GET['search'] : '';
                    
                    // Build query parameters for pagination links
                    $query_params = [];
                    if ($current_filter !== 'all') {
                        $query_params['filter'] = $current_filter;
                    }
                    if (!empty($current_search)) {
                        $query_params['search'] = $current_search;
                    }
                    
                    // Previous page link
                    if ($page > 1): 
                        $prev_params = array_merge($query_params, ['page' => $page - 1]);
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
                        $next_params = array_merge($query_params, ['page' => $page + 1]);
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

    <!-- Modals -->
    <div id="details-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Request Details</h3>
                <button class="close">&times;</button>
            </div>
            <div id="modal-body" style="padding: 25px;">
                <!-- Content will be loaded here dynamically -->
            </div>
        </div>
    </div>

    <div id="reject-reason-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Reject Request</h3>
                <button class="close">&times;</button>
            </div>
            <div style="padding: 25px;">
                <label for="reject-reason-select" style="display: block; margin-bottom: 15px; font-weight: 600; color: #495057;">Select Reason for Rejection:</label>
                <select id="reject-reason-select" class="filter-select" style="width: 100%;">
                    <option value="No complete information in the database">No complete information in the database</option>
                    <option value="Invalid document type requested">Invalid document type requested</option>
                    <option value="Duplicate request">Duplicate request</option>
                    <option value="Resident not found">Resident not found</option>
                    <option value="Other">Other (please specify below)</option>
                </select>
                <textarea id="reject-reason-other" class="search-input d-none" placeholder="Specify other reason..." rows="3" style="width: 100%; margin-top: 15px;"></textarea>
            </div>
            <div style="padding: 20px; text-align: right; border-top: 1px solid #e9ecef;">
                <button class="btn btn-outline" id="cancel-reject-btn" style="margin-right: 10px;">Cancel</button>
                <button class="btn btn-danger" id="confirm-reject-btn">Confirm Reject</button>
            </div>
        </div>
    </div>

    <!-- Email Notification Status Modal -->
    <div id="email-status-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Notification Status</h3>
                <button class="close">&times;</button>
            </div>
            <div id="email-status-body" style="padding: 30px; text-align: center;">
                <!-- Email status will be shown here -->
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmation-modal" class="confirmation-modal">
        <div class="confirmation-content">
            <div class="confirmation-header">
                <h3 class="confirmation-title" id="confirmation-title">Confirm Action</h3>
            </div>
            <div class="confirmation-body">
                <div class="confirmation-message" id="confirmation-message">Are you sure you want to proceed?</div>
                <div class="confirmation-buttons">
                    <button class="confirmation-btn btn-cancel" id="confirmation-cancel">Cancel</button>
                    <button class="confirmation-btn" id="confirmation-confirm">Confirm</button>
                </div>
            </div>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Requests page loaded successfully');
    
    // Modal elements
    const detailsModal = document.getElementById('details-modal');
    const rejectReasonModal = document.getElementById('reject-reason-modal');
    const emailStatusModal = document.getElementById('email-status-modal');
    const confirmationModal = document.getElementById('confirmation-modal');
    const closeButtons = document.querySelectorAll('.close');
    const cancelRejectBtn = document.getElementById('cancel-reject-btn');
    
    // Filter and search elements
    const statusFilter = document.getElementById('request-status-filter');
    const searchInput = document.getElementById('request-search');
    
    // Reject modal elements
    const rejectReasonSelect = document.getElementById('reject-reason-select');
    const rejectReasonOther = document.getElementById('reject-reason-other');
    const confirmRejectBtn = document.getElementById('confirm-reject-btn');
    
    // Confirmation modal elements
    const confirmationTitle = document.getElementById('confirmation-title');
    const confirmationMessage = document.getElementById('confirmation-message');
    const confirmationCancel = document.getElementById('confirmation-cancel');
    const confirmationConfirm = document.getElementById('confirmation-confirm');
    
    // Current request ID for actions
    let currentRequestId = null;
    let currentRequestEmail = null;
    let currentAction = null;

    // Improved notification function - Modal style
    function showNotification(message, type = 'info', duration = 0) {
        console.log('Showing notification:', message, type, duration);
        
        // Remove any existing notifications first
        const existingNotifications = document.querySelectorAll('.notification-modal');
        existingNotifications.forEach(notification => {
            if (notification.parentNode) {
                notification.remove();
            }
        });
        
        // Create notification modal
        const notificationModal = document.createElement('div');
        notificationModal.className = `notification-modal`;
        notificationModal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
            backdrop-filter: blur(5px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            animation: fadeIn 0.3s ease;
        `;
        
        // Determine icon and title based on type
        let icon = 'info-circle';
        let title = 'Information';
        let btnClass = 'btn-confirm-primary';
        
        switch(type) {
            case 'success':
                icon = 'check-circle';
                title = 'Success';
                btnClass = 'btn-confirm';
                break;
            case 'error':
                icon = 'exclamation-triangle';
                title = 'Error';
                btnClass = 'btn-confirm-danger';
                break;
            case 'info':
            default:
                icon = 'info-circle';
                title = 'Information';
                btnClass = 'btn-confirm-primary';
                break;
        }
        
        notificationModal.innerHTML = `
            <div class="notification-content">
                <div class="notification-header">
                    <h3 class="notification-title">
                        <i class="fas fa-${icon}"></i> ${title}
                    </h3>
                </div>
                <div class="notification-body">
                    <div class="notification-message">${message}</div>
                    <div class="notification-buttons">
                        <button class="notification-btn ${btnClass}" id="notification-ok-btn">OK</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(notificationModal);
        
        // Add OK button functionality
        const okButton = document.getElementById('notification-ok-btn');
        okButton.addEventListener('click', () => {
            console.log('Notification OK button clicked');
            if (notificationModal.parentNode) {
                notificationModal.remove();
            }
        });
        
        // Auto remove after duration if specified (for non-blocking notifications)
        if (duration > 0) {
            setTimeout(() => {
                if (notificationModal.parentNode) {
                    console.log('Auto-removing notification');
                    notificationModal.remove();
                }
            }, duration);
        }
        
        return notificationModal;
    }

    // Add debounce function to prevent too many rapid requests
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

    // Function to generate action buttons based on status
    function generateActionButtons(requestId, status) {
        let buttons = '';
        const statusLower = status.toLowerCase();
        
        if (statusLower === 'pending') {
            buttons = `
                <button class="btn btn-success action-btn approve-request" 
                        data-id="${requestId}" 
                        title="Approve">
                    Approve
                </button>
                <button class="btn btn-danger action-btn reject-request" 
                        data-id="${requestId}" 
                        title="Reject">
                    Reject
                </button>
            `;
        } else if (statusLower === 'approved') {
            buttons = `
                <button class="btn btn-primary action-btn complete-request" 
                        data-id="${requestId}" 
                        title="Complete">
                    Complete
                </button>
            `;
        }
        
        // Always include view button
        buttons += `
            <button class="btn btn-outline action-btn view-request" 
                    data-id="${requestId}" 
                    title="View">
                View
            </button>
        `;
        
        return buttons;
    }

    // Function to update a specific row in the table
    function updateRequestRow(requestId, newStatus) {
        const row = document.querySelector(`tr[data-request-id="${requestId}"]`);
        if (!row) {
            console.log('Row not found for request ID:', requestId);
            return false;
        }

        console.log('Updating row for request:', requestId, 'to status:', newStatus);

        // Add updating state
        row.classList.add('row-updating');

        // Update status badge
        const statusBadge = row.querySelector('.status');
        const statusClass = newStatus.toLowerCase();
        statusBadge.className = `status status-${statusClass}`;
        statusBadge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);

        // Update action buttons
        const actionButtonsContainer = row.querySelector('.action-buttons');
        actionButtonsContainer.innerHTML = generateActionButtons(requestId, newStatus);

        // Update row data attribute
        row.setAttribute('data-status', statusClass);

        // Re-attach event listeners to the new buttons
        attachActionListenersToRow(row);

        // Remove updating state and add success animation
        setTimeout(() => {
            row.classList.remove('row-updating');
            row.classList.add('row-updated');
            setTimeout(() => row.classList.remove('row-updated'), 2000);
        }, 500);

        return true;
    }

    // Function to attach event listeners to a specific row
    function attachActionListenersToRow(row) {
        const approveBtn = row.querySelector('.approve-request');
        const rejectBtn = row.querySelector('.reject-request');
        const completeBtn = row.querySelector('.complete-request');
        const viewBtn = row.querySelector('.view-request');

        if (approveBtn) {
            approveBtn.addEventListener('click', function() {
                const requestId = this.getAttribute('data-id');
                console.log('Approve button clicked for request:', requestId);
                showConfirmation(
                    'Approve Request',
                    'Are you sure you want to approve this request?',
                    'btn-confirm',
                    () => approveRequest(requestId)
                );
            });
        }

        if (rejectBtn) {
            rejectBtn.addEventListener('click', function() {
                const requestId = this.getAttribute('data-id');
                console.log('Reject button clicked for request:', requestId);
                openRejectReasonModal(requestId);
            });
        }

        if (completeBtn) {
            completeBtn.addEventListener('click', function() {
                const requestId = this.getAttribute('data-id');
                console.log('Complete button clicked for request:', requestId);
                showConfirmation(
                    'Complete Request',
                    'Are you sure you want to mark this request as completed?',
                    'btn-confirm-primary',
                    () => completeRequest(requestId)
                );
            });
        }

        if (viewBtn) {
            viewBtn.addEventListener('click', function() {
                const requestId = this.getAttribute('data-id');
                console.log('View button clicked for request:', requestId);
                viewRequestDetails(requestId);
            });
        }
    }

    // Function to refresh the entire requests table
    function refreshRequestsTable() {
        console.log('Refreshing requests table');
        const currentParams = new URLSearchParams(window.location.search);
        loadFilteredResults(currentParams.toString());
    }

    // Function to update stats via AJAX
function updateStats() {
    console.log('Updating stats');
    fetch('../ajax/get_request_status.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Update stats cards
                const statValues = document.querySelectorAll('.stat-value');
                if (statValues.length >= 3) {
                    statValues[0].textContent = data.stats.approved_today;
                    statValues[1].textContent = data.stats.completion_rate + '%';  // Add % symbol
                    statValues[2].textContent = data.stats.total_pending;
                }
                console.log('Stats updated successfully');
            } else {
                console.error('Failed to update stats:', data.message);
            }
        })
        .catch(error => {
            console.error('Error updating stats:', error);
        });
}

    function filterRequests() {
        const statusFilterValue = statusFilter.value;
        const searchValue = searchInput.value.trim();
        console.log('Filtering requests - Status:', statusFilterValue, 'Search:', searchValue);
        
        // Build URL with filter parameters
        const params = new URLSearchParams();
        
        if (statusFilterValue !== 'all') {
            params.append('filter', statusFilterValue);
        }
        
        if (searchValue) {
            params.append('search', searchValue);
        }
        
        // Update URL without reloading the page
        const newUrl = window.location.pathname + '?' + params.toString();
        window.history.replaceState(null, '', newUrl);
        
        // Load filtered results via AJAX
        loadFilteredResults(params.toString());
    }

    function loadFilteredResults(queryString) {
        console.log('Loading filtered results with query:', queryString);
        
        // Show loading state
        const tableBody = document.getElementById('requests-table-body');
        const pagination = document.querySelector('.pagination');
        
        tableBody.innerHTML = `
            <tr>
                <td colspan="6">
                    <div style="text-align: center; padding: 40px;">
                        <div class="spinner" style="border: 4px solid #f3f3f3; border-top: 4px solid #007bff; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>
                        <p style="color: #6c757d;">Loading requests...</p>
                    </div>
                </td>
            </tr>
        `;
        
        if (pagination) {
            pagination.style.opacity = '0.5';
        }

        fetch(`../ajax/get_requests.php?${queryString}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Received requests data:', data);
                if (data.success) {
                    updateRequestsTable(data.requests);
                    updatePagination(data.pagination);
                } else {
                    showNotification('Error loading requests: ' + data.message, 'error', 5000);
                }
            })
            .catch(error => {
                console.error('Error loading requests:', error);
                showNotification('Error loading requests: ' + error.message, 'error', 5000);
                // Restore original content on error
                setTimeout(() => location.reload(), 2000);
            });
    }

    function updateRequestsTable(requests) {
        const tableBody = document.getElementById('requests-table-body');
        console.log('Updating table with', requests.length, 'requests');
        
        if (requests.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h4>No Requests Found</h4>
                            <p>When residents submit document requests, they will appear here for review.</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        let html = '';
        requests.forEach(request => {
            const status = request.status || 'pending';
            const status_class = status.toLowerCase();
            
            // Skip completed requests
            if (status === 'completed') return;
            
            html += `
                <tr class="request-row" data-request-id="${request.request_id}" data-status="${status_class}">
                    <td style="font-weight: 700; color: #007bff;">${escapeHtml(request.request_code)}</td>
                    <td style="font-weight: 600; color: #495057;">${escapeHtml(request.resident_name)}</td>
                    <td>${escapeHtml(request.document_type)}</td>
                    <td style="color: #6c757d;">${formatDate(request.request_date)}</td>
                    <td>
                        <span class="status status-${status_class}">
                            ${ucfirst(status)}
                        </span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            ${generateActionButtons(request.request_id, status)}
                        </div>
                    </td>
                </tr>
            `;
        });
        
        tableBody.innerHTML = html;
        
        // Re-attach event listeners to all rows
        attachActionListeners();
        console.log('Table updated and event listeners attached');
    }

    function updatePagination(pagination) {
        const paginationContainer = document.querySelector('.pagination');
        if (!paginationContainer) return;

        if (pagination.total_pages > 1) {
            const { current_page, total_pages, total_requests, limit } = pagination;
            const showing = Math.min(limit, total_requests - ((current_page - 1) * limit));
            
            // Get current filter parameters
            const currentFilter = new URLSearchParams(window.location.search).get('filter') || 'all';
            const currentSearch = new URLSearchParams(window.location.search).get('search') || '';
            
            // Build query parameters for pagination links
            const queryParams = new URLSearchParams();
            if (currentFilter !== 'all') queryParams.append('filter', currentFilter);
            if (currentSearch) queryParams.append('search', currentSearch);
            
            let paginationHTML = `
                <div class="pagination-info">
                    Showing ${showing} of ${total_requests} requests
                </div>
                <div class="pagination-controls">
            `;
            
            // Previous button
            if (current_page > 1) {
                queryParams.set('page', current_page - 1);
                paginationHTML += `
                    <a href="javascript:void(0)" class="pagination-btn pagination-link" data-page="${current_page - 1}">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                `;
            } else {
                paginationHTML += `
                    <span class="pagination-btn disabled">
                        <i class="fas fa-chevron-left"></i> Previous
                    </span>
                `;
            }
            
            paginationHTML += `
                <span class="page-indicator">
                    Page ${current_page} of ${total_pages}
                </span>
            `;
            
            // Next button
            if (current_page < total_pages) {
                queryParams.set('page', current_page + 1);
                paginationHTML += `
                    <a href="javascript:void(0)" class="pagination-btn pagination-link" data-page="${current_page + 1}">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                `;
            } else {
                paginationHTML += `
                    <span class="pagination-btn disabled">
                        Next <i class="fas fa-chevron-right"></i>
                    </span>
                `;
            }
            
            paginationHTML += `</div>`;
            paginationContainer.innerHTML = paginationHTML;
            paginationContainer.style.opacity = '1';
            
            // Attach event listeners to pagination links
            document.querySelectorAll('.pagination-link').forEach(link => {
                link.addEventListener('click', function() {
                    const page = this.getAttribute('data-page');
                    console.log('Pagination link clicked, page:', page);
                    loadPage(parseInt(page));
                });
            });
        } else {
            paginationContainer.innerHTML = `
                <div class="pagination-info">
                    Showing ${pagination.total_requests} of ${pagination.total_requests} requests
                </div>
            `;
            paginationContainer.style.opacity = '1';
        }
    }

    function loadPage(page) {
        console.log('Loading page:', page);
        const params = new URLSearchParams(window.location.search);
        params.set('page', page);
        loadFilteredResults(params.toString());
    }

    function attachActionListeners() {
        console.log('Attaching action listeners to all rows');
        // Attach event listeners to all action buttons in the table
        document.querySelectorAll('.request-row').forEach(row => {
            attachActionListenersToRow(row);
        });
    }

    // Helper function to restore button states
    function restoreButtonStates(requestId, actionButtons, row) {
        console.log('Restoring button states for request:', requestId);
        actionButtons.forEach(button => {
            button.disabled = false;
            if (button.dataset.originalHTML) {
                button.innerHTML = button.dataset.originalHTML;
            }
        });
        if (row) {
            row.classList.remove('row-updating');
        }
    }

    // Create debounced version of filterRequests for search input
    const debouncedFilterRequests = debounce(filterRequests, 500);

    // Initialize event listeners
    function initEventListeners() {
        console.log('Initializing event listeners');
        
        // Modal close events
        closeButtons.forEach(btn => {
            btn.addEventListener('click', closeAllModals);
        });
        
        if (cancelRejectBtn) {
            cancelRejectBtn.addEventListener('click', closeAllModals);
        }
        
        confirmationCancel.addEventListener('click', closeAllModals);
        
        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === detailsModal) closeAllModals();
            if (event.target === rejectReasonModal) closeAllModals();
            if (event.target === emailStatusModal) closeAllModals();
            if (event.target === confirmationModal) closeAllModals();
        });
        
        // Filter and search
        if (statusFilter) {
            statusFilter.addEventListener('change', filterRequests);
        }
        
        if (searchInput) {
            // Use debounced version for search input to prevent reload on every keystroke
            searchInput.addEventListener('input', debouncedFilterRequests);
            
            // Also allow pressing Enter to search immediately
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    filterRequests();
                }
            });
        }

        // Reject reason select change
        rejectReasonSelect.addEventListener('change', function() {
            if (this.value === 'Other') {
                rejectReasonOther.classList.remove('d-none');
            } else {
                rejectReasonOther.classList.add('d-none');
            }
        });

        // Confirm reject button
        confirmRejectBtn.addEventListener('click', function() {
            let reason = rejectReasonSelect.value;
            if (reason === 'Other') {
                reason = rejectReasonOther.value.trim();
                if (!reason) {
                    showNotification('Please specify the rejection reason.', 'error', 4000);
                    return;
                }
            }
            rejectRequest(currentRequestId, reason);
        });

        // Initial attachment of action listeners
        attachActionListeners();
        console.log('Event listeners initialized successfully');
    }

    // Utility functions
    function formatDate(dateString) {
        if (!dateString) return 'Not set';
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            });
        } catch (e) {
            return 'Invalid Date';
        }
    }

    function ucfirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    function showConfirmation(title, message, confirmClass, confirmCallback) {
        confirmationTitle.textContent = title;
        confirmationMessage.textContent = message;
        confirmationConfirm.className = 'confirmation-btn ' + confirmClass;
        confirmationConfirm.textContent = 'Confirm';
        
        // Remove previous event listeners
        confirmationConfirm.replaceWith(confirmationConfirm.cloneNode(true));
        const newConfirmBtn = document.getElementById('confirmation-confirm');
        
        // Add new event listener
        newConfirmBtn.addEventListener('click', function() {
            console.log('Confirmation confirmed');
            closeAllModals();
            confirmCallback();
        });
        
        confirmationModal.style.display = 'flex';
    }

    function viewRequestDetails(requestId) {
        if (!requestId) return;
        
        console.log('Viewing request details for:', requestId);
        
        // Show loading state
        document.getElementById('modal-body').innerHTML = `
            <div style="text-align: center; padding: 40px;">
                <div class="spinner" style="border: 4px solid #f3f3f3; border-top: 4px solid #007bff; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>
                <p style="color: #6c757d;">Loading request details...</p>
            </div>
            <style>
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            </style>
        `;
        detailsModal.style.display = 'flex';
        
        fetch(`../ajax/get_request.php?id=${requestId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Received request details:', data);
                if (data.success && data.request) {
                    displayRequestDetails(data.request);
                } else {
                    showError('Error loading request details: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error loading request details:', error);
                showError('Error loading request details: ' + error.message);
            });
    }

    function displayRequestDetails(request) {
        let modalBody = `
            <div style="display: grid; gap: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #e9ecef;">
                    <strong style="color: #495057;">Request Code:</strong>
                    <span style="color: #007bff; font-weight: 700;">${escapeHtml(request.request_code || 'N/A')}</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #e9ecef;">
                    <strong style="color: #495057;">Resident Name:</strong>
                    <span>${escapeHtml(request.resident_name || 'N/A')}</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #e9ecef;">
                    <strong style="color: #495057;">Resident Email:</strong>
                    <span>${escapeHtml(request.resident_email || 'N/A')}</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #e9ecef;">
                    <strong style="color: #495057;">Contact Number:</strong>
                    <span>${escapeHtml(request.resident_contact || 'N/A')}</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #e9ecef;">
                    <strong style="color: #495057;">Document Type:</strong>
                    <span>${escapeHtml(request.document_type || 'N/A')}</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #e9ecef;">
                    <strong style="color: #495057;">Request Date:</strong>
                    <span>${formatDate(request.request_date)}</span>
                </div>
        `;

        if (request.purpose) {
            modalBody += `
                <div style="padding: 20px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 10px; margin-top: 15px;">
                    <strong style="color: #495057; display: block; margin-bottom: 10px;">Purpose:</strong>
                    <div style="color: #6c757d; line-height: 1.5;">${escapeHtml(request.purpose)}</div>
                </div>
            `;
        }

        if (request.specific_purpose) {
            modalBody += `
                <div style="padding: 20px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 10px; margin-top: 15px;">
                    <strong style="color: #495057; display: block; margin-bottom: 10px;">Specific Purpose:</strong>
                    <div style="color: #6c757d; line-height: 1.5;">${escapeHtml(request.specific_purpose)}</div>
                </div>
            `;
        }

        if (request.resident_address) {
            modalBody += `
                <div style="padding: 20px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 10px; margin-top: 15px;">
                    <strong style="color: #495057; display: block; margin-bottom: 10px;">Address:</strong>
                    <div style="color: #6c757d; line-height: 1.5;">${escapeHtml(request.resident_address)}</div>
                </div>
            `;
        }

        modalBody += `</div>`;
        document.getElementById('modal-body').innerHTML = modalBody;
        console.log('Request details displayed');
    }

    function showError(message) {
        document.getElementById('modal-body').innerHTML = `
            <div style="text-align: center; padding: 40px; color: #dc3545;">
                <i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 20px;"></i>
                <h4 style="margin-bottom: 10px;">Error</h4>
                <p>${escapeHtml(message)}</p>
            </div>
        `;
    }

    function openRejectReasonModal(requestId) {
        currentRequestId = requestId;
        rejectReasonSelect.value = 'No complete information in the database';
        rejectReasonOther.value = '';
        rejectReasonOther.classList.add('d-none');
        rejectReasonModal.style.display = 'flex';
        console.log('Reject reason modal opened for request:', requestId);
    }

    function approveRequest(requestId) {
        console.log('Approving request:', requestId);
        updateRequestStatus(requestId, 'approved');
    }

    function rejectRequest(requestId, reason) {
        console.log('Rejecting request:', requestId, 'with reason:', reason);
        rejectReasonModal.style.display = 'none';
        updateRequestStatus(requestId, 'rejected', reason);
    }

    function completeRequest(requestId) {
        console.log('Completing request:', requestId);
        updateRequestStatus(requestId, 'completed');
    }

    // Function to update request status with automatic refresh
function updateRequestStatus(requestId, status, reason = '') {
    console.log('🚀 Updating request status:', { requestId, status, reason });
    
    const formData = new FormData();
    formData.append('request_id', requestId);
    formData.append('status', status);
    if (reason) {
        formData.append('reason', reason);
    }
    
    // Show updating state on the row
    const row = document.querySelector(`tr[data-request-id="${requestId}"]`);
    if (row) {
        row.classList.add('row-updating');
    }
    
    // Disable all action buttons for this request
    const actionButtons = document.querySelectorAll(`[data-id="${requestId}"]`);
    actionButtons.forEach(button => {
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    });
    
    fetch('../ajax/update_request.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            let notificationMessage = '✅ ' + data.message;
            
            // Check if email was sent
            if (data.email_result) {
                if (data.email_result.email_sent) {
                    notificationMessage += ' 📧 Email sent successfully.';
                } else if (!data.email_result.success) {
                    notificationMessage += ' ⚠️ Email failed: ' + data.email_result.message;
                }
            }
            
            showNotification(notificationMessage, 'success', 0);
            closeAllModals();
            
            // SIMPLE SOLUTION: Refresh the whole page after 1.5 seconds
            setTimeout(() => {
                window.location.reload();
            }, 1500);
            
        } else {
            showNotification('❌ ' + data.message, 'error', 0);
            // Restore button states on error
            actionButtons.forEach(button => {
                button.disabled = false;
                button.innerHTML = button.dataset.originalHTML || 'Retry';
            });
            if (row) row.classList.remove('row-updating');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('❌ Error: ' + error.message, 'error', 0);
        // Restore button states on error
        actionButtons.forEach(button => {
            button.disabled = false;
            button.innerHTML = button.dataset.originalHTML || 'Retry';
        });
        if (row) row.classList.remove('row-updating');
    });
}

// NEW FUNCTION: Immediately remove rejected request row from table
function removeRequestRow(requestId) {
    console.log('🗑️ Removing rejected request from table:', requestId);
    const row = document.querySelector(`tr[data-request-id="${requestId}"]`);
    
    if (row) {
        // Add fade-out animation
        row.style.transition = 'all 0.3s ease';
        row.style.opacity = '0';
        row.style.transform = 'translateX(-100%)';
        
        // Remove row after animation
        setTimeout(() => {
            row.remove();
            console.log('✅ Rejected request removed from table');
            
            // Check if table is now empty and show empty state if needed
            const tableBody = document.getElementById('requests-table-body');
            const remainingRows = tableBody.querySelectorAll('.request-row');
            
            if (remainingRows.length === 0) {
                showEmptyState();
            }
        }, 300);
    } else {
        console.log('⚠️ Row not found for request:', requestId);
        // If row not found, refresh the whole table
        refreshRequestsTable();
    }
}

// NEW FUNCTION: Show empty state when all requests are gone
function showEmptyState() {
    const tableBody = document.getElementById('requests-table-body');
    tableBody.innerHTML = `
        <tr>
            <td colspan="6">
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h4>No Requests Found</h4>
                    <p>When residents submit document requests, they will appear here for review.</p>
                </div>
            </td>
        </tr>
    `;
    
    // Also hide pagination if it exists
    const pagination = document.querySelector('.pagination');
    if (pagination) {
        pagination.style.display = 'none';
    }
}

    function closeAllModals() {
        console.log('Closing all modals');
        detailsModal.style.display = 'none';
        rejectReasonModal.style.display = 'none';
        emailStatusModal.style.display = 'none';
        confirmationModal.style.display = 'none';
        currentRequestId = null;
        currentRequestEmail = null;
        currentAction = null;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize
    initEventListeners();
    console.log('Requests page initialization complete');
});
</script>
</body>
</html>

<?php include '../includes/footer.php'; ?>
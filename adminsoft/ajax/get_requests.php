<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

try {
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
    $where_conditions = ["r.status != 'completed'"];
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

    // Return JSON response
    echo json_encode([
        'success' => true,
        'requests' => $requests,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_requests' => $total_requests,
            'limit' => $limit
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
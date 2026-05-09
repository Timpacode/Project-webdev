<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Request ID is required']);
    exit;
}

$requestId = filter_var($_GET['id'], FILTER_VALIDATE_INT);

if (!$requestId || $requestId < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid request ID']);
    exit;
}

try {
    // Database connection
    require_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    // Get request details with proper field names
    $query = "SELECT 
                r.request_id,
                r.request_code,
                r.resident_name,
                r.resident_email,
                r.resident_contact,
                r.resident_address,
                dt.name as document_type,
                r.purpose,
                r.specific_purpose,
                r.status,
                DATE_FORMAT(r.request_date, '%M %d, %Y %h:%i %p') as request_date,
                r.processed_date,
                a.username as processed_by
              FROM request r
              JOIN document_type dt ON r.document_type_id = dt.type_id
              LEFT JOIN admin a ON r.processed_by = a.admin_id
              WHERE r.request_id = :request_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':request_id', $requestId, PDO::PARAM_INT);
    $stmt->execute();
    
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($request) {
        echo json_encode([
            'success' => true,
            'request' => $request
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Request not found'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Get request info error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
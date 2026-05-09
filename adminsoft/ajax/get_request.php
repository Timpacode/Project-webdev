<?php
session_start();
header('Content-Type: application/json');

// Turn off error display but log them
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Request ID required']);
    exit;
}

// Validate input
$request_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

if (!$request_id || $request_id < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid request ID']);
    exit;
}

try {
    // Database connection
    require_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    // Get request details with document type name
    $query = "SELECT r.*, dt.name as document_type 
              FROM request r 
              JOIN document_type dt ON r.document_type_id = dt.type_id 
              WHERE r.request_id = :request_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':request_id', $request_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'request' => $request
    ]);
    
} catch (Exception $e) {
    error_log("Get request error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
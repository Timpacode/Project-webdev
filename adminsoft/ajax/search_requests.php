<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if (!isset($_GET['q']) || empty(trim($_GET['q']))) {
    echo json_encode([]);
    exit;
}

$searchTerm = trim($_GET['q']);
$status = isset($_GET['status']) ? $_GET['status'] : 'approved';

try {
    // Database connection
    require_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    // Debug logging
    error_log("Searching requests with term: '$searchTerm' and status: '$status'");
    
    // Build query to search approved requests
    $query = "SELECT 
                r.request_id,
                r.request_code,
                r.resident_name,
                r.resident_email,
                r.resident_contact,
                r.resident_address,
                r.status,
                dt.name as document_type,
                r.purpose,
                r.specific_purpose,
                r.request_date
              FROM request r
              JOIN document_type dt ON r.document_type_id = dt.type_id
              WHERE r.status = :status 
                AND (r.request_code LIKE :search 
                     OR r.resident_name LIKE :search 
                     OR dt.name LIKE :search)
              ORDER BY r.request_date DESC
              LIMIT 10";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':status', $status);
    $stmt->bindValue(':search', "%$searchTerm%");
    $stmt->execute();
    
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug logging
    error_log("Found " . count($requests) . " requests");
    foreach ($requests as $req) {
        error_log("Request found: " . $req['request_code'] . " - " . $req['resident_name']);
    }
    
    echo json_encode($requests);
    
} catch (Exception $e) {
    error_log("Search requests error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
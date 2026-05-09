<?php
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['request_code']) || !isset($_POST['resident_id'])) {
        echo json_encode(['success' => false, 'message' => 'Request code and resident ID are required']);
        exit;
    }
    
    try {
        // Get request details with resident information
        $requestQuery = "SELECT r.*, res.full_name, res.birthdate, res.gender, res.civil_status, 
                        res.address, res.contact_number, res.year_of_residency,
                        dt.name as document_type,
                        TIMESTAMPDIFF(YEAR, res.birthdate, CURDATE()) as age
                        FROM request r
                        JOIN resident res ON r.resident_id = res.resident_id
                        JOIN document_type dt ON r.document_type_id = dt.type_id
                        WHERE r.request_code = :request_code AND r.resident_id = :resident_id";
        
        $requestStmt = $db->prepare($requestQuery);
        $requestStmt->bindValue(':request_code', $_POST['request_code']);
        $requestStmt->bindValue(':resident_id', $_POST['resident_id']);
        $requestStmt->execute();
        $request = $requestStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) {
            echo json_encode(['success' => false, 'message' => 'Request not found for the given resident ID']);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'request' => $request
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
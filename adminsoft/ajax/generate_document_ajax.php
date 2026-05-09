<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../classes/DocumentGenerator.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['resident_id']) || !isset($_POST['document_type'])) {
        echo json_encode(['success' => false, 'message' => 'Resident ID and document type are required']);
        exit;
    }
    
    try {
        $docGenerator = new DocumentGenerator();
        $residentId = $_POST['resident_id'];
        $documentType = $_POST['document_type'];
        $requestId = $_POST['request_id'] ?? null;
        
        $result = $docGenerator->generateDocument($residentId, $documentType, $requestId);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Document generated successfully',
            'file_path' => $result['download_url'],
            'document_id' => $result['document_id']
        ]);
        
    } catch (Exception $e) {
        error_log("Document generation error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
<?php
session_start();
require_once '../classes/DocumentGenerator.php';

header('Content-Type: application/json');

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
        
        // Store result in session for display
        $_SESSION['document_result'] = $result;
        
        echo json_encode(['success' => true, 'message' => 'Document generated successfully']);
        
    } catch (Exception $e) {
        $_SESSION['document_error'] = $e->getMessage();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
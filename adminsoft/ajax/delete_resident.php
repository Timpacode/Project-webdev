<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['resident_id']) || empty($_POST['resident_id'])) {
        echo json_encode(['success' => false, 'message' => 'Resident ID is required']);
        exit;
    }
    
    $resident_id = $_POST['resident_id'];
    
    try {
        // Check if resident exists
        $checkQuery = "SELECT resident_id FROM resident WHERE resident_id = :resident_id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindValue(':resident_id', $resident_id);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Resident not found']);
            exit;
        }
        
        $query = "DELETE FROM resident WHERE resident_id = :resident_id";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':resident_id', $resident_id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Resident deleted successfully'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete resident']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
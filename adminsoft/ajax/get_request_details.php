<?php
session_start();
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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['id'])) {
        echo json_encode(['success' => false, 'message' => 'Resident ID is required']);
        exit;
    }
    
    try {
        $query = "SELECT *, 
                  TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) - 
                  (DATE_FORMAT(CURDATE(), '%m%d') < DATE_FORMAT(birthdate, '%m%d')) AS age 
                  FROM resident 
                  WHERE resident_id = :resident_id";
                  
        $stmt = $db->prepare($query);
        $stmt->bindValue(':resident_id', $_GET['id']);
        $stmt->execute();
        
        $resident = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($resident) {
            echo json_encode([
                'success' => true, 
                'resident' => $resident
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Resident not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
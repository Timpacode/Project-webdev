<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Resident ID is required']);
    exit;
}

$resident_id = (int)$_GET['id'];

try {
    $query = "SELECT *, 
              TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) - 
              (DATE_FORMAT(CURDATE(), '%m%d') < DATE_FORMAT(birthdate, '%m%d')) AS age 
              FROM resident 
              WHERE resident_id = ? AND status = 'active'";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$resident_id]);
    $resident = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($resident) {
        echo json_encode([
            'success' => true, 
            'data' => [
                'full_name' => $resident['full_name'] ?? 'N/A',
                'age' => $resident['age'] ?? 'N/A',
                'gender' => $resident['gender'] ?? 'Not specified',
                'civil_status' => $resident['civil_status'] ?? 'N/A',
                'address' => $resident['address'] ?? 'N/A',
                'contact_number' => $resident['contact_number'] ?? 'N/A',
                'birthdate' => $resident['birthdate'] ?? 'N/A',
                'email' => $resident['email'] ?? 'N/A',
                'resident_code' => $resident['resident_code'] ?? 'N/A'
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Resident not found']);
    }
} catch (PDOException $e) {
    error_log("Get resident info error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
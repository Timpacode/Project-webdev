<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('Admin ID is required');
    }
    
    $admin_id = $_GET['id'];
    
    $query = "SELECT admin_id, username, email, first_name, last_name, role, contact_number, status 
              FROM admin 
              WHERE admin_id = :admin_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':admin_id', $admin_id);
    $stmt->execute();
    
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo json_encode([
            'success' => true,
            'admin' => $admin
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Admin not found'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
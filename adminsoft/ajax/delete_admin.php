<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get POST data
    $admin_id = $_POST['id'] ?? '';
    
    if (empty($admin_id)) {
        throw new Exception('Admin ID is required');
    }
    
    // Prevent deleting the last admin
    $count_query = "SELECT COUNT(*) as admin_count FROM admin";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute();
    $admin_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['admin_count'];
    
    if ($admin_count <= 1) {
        throw new Exception('Cannot delete the last admin user');
    }
    
    // Prevent admin from deleting themselves
    session_start();
    if (isset($_SESSION['admin_id']) && $_SESSION['admin_id'] == $admin_id) {
        throw new Exception('You cannot delete your own account');
    }
    
    // Delete admin
    $query = "DELETE FROM admin WHERE admin_id = :admin_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':admin_id', $admin_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Admin deleted successfully'
        ]);
    } else {
        throw new Exception('Failed to delete admin');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
<?php
include '../config/database.php';
$database = new Database();
$db = $database->getConnection();

header('Content-Type: application/json');

try {
    // Total admins
    $query = "SELECT COUNT(*) as count FROM admin";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $total_admins = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Active admins
    $query = "SELECT COUNT(*) as count FROM admin WHERE status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $active_admins = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Captains
    $query = "SELECT COUNT(*) as count FROM admin WHERE role = 'Captain'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $captains = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo json_encode([
        'success' => true, 
        'total_admins' => $total_admins,
        'active_admins' => $active_admins,
        'captains' => $captains
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to fetch stats']);
}
?>
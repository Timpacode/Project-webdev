<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    $stats = [];
    
    // Approved today
    $query = "SELECT COUNT(*) as count FROM request WHERE DATE(processed_date) = CURDATE() AND STATUS = 'approved'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['approved_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Total this month
    $query = "SELECT COUNT(*) as count FROM request WHERE MONTH(request_date) = MONTH(CURDATE()) AND YEAR(request_date) = YEAR(CURDATE())";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Total pending requests
    $query = "SELECT COUNT(*) as count FROM request WHERE STATUS = 'pending'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_pending'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
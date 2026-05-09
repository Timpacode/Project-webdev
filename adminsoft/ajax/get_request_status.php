<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Database connection
$database = new Database();
$db = $database->getConnection();

$stats = [];
try {
    // Approved today
    $query = "SELECT COUNT(*) as count FROM request WHERE DATE(processed_date) = CURDATE() AND STATUS = 'approved'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['approved_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Total pending requests
    $query = "SELECT COUNT(*) as count FROM request WHERE STATUS = 'pending'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_pending'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Completion rate percentage
    $query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM request";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $completion_rate = $result['total'] > 0 ? round(($result['completed'] / $result['total']) * 100) : 0;
    $stats['completion_rate'] = $completion_rate;
    
    echo json_encode(['success' => true, 'stats' => $stats]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
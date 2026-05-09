<?php
// File: ajax/get_admin_details.php
require_once '../config/database.php';
require_once '../config/auth.php';

session_start();
$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Check if user is logged in
if (!$auth->checkAuth()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Admin ID is required']);
    exit();
}

$admin_id = (int)$_GET['id'];

try {
    $query = "SELECT * FROM ADMIN WHERE admin_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo json_encode(['success' => true, 'admin' => $admin]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Admin not found']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
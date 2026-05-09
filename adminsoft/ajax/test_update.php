<?php
session_start();
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Test query - get current status of request 87
$stmt = $db->prepare("SELECT request_id, status, resident_name FROM request WHERE request_id = 87");
$stmt->execute();
$request = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($request);
echo "</pre>";
?>
<?php
session_start();
require_once '../config/auth.php';
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn()) {
    die("Not authenticated");
}

if (isset($_GET['file'])) {
    $filePath = '../documents/' . $_GET['file'];
    
    // Security check
    if (strpos(realpath($filePath), realpath('../documents')) === 0 && file_exists($filePath)) {
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    } else {
        http_response_code(404);
        echo "File not found or access denied.";
    }
} else {
    http_response_code(400);
    echo "No file specified.";
}
?>
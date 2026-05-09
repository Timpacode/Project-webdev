<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn()) {
    echo json_encode([]);
    exit;
}

$searchTerm = $_GET['q'] ?? '';

if (strlen($searchTerm) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $query = "SELECT resident_id, resident_code, full_name, address, contact_number, 
                     birthdate, gender, civil_status, email
              FROM resident 
              WHERE (full_name LIKE :search 
                 OR resident_code LIKE :search 
                 OR contact_number LIKE :search
                 OR email LIKE :search)
                AND status = 'active'
              ORDER BY full_name 
              LIMIT 10";
    
    $stmt = $db->prepare($query);
    $searchTerm = '%' . $searchTerm . '%';
    $stmt->bindValue(':search', $searchTerm);
    $stmt->execute();
    
    $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($residents);
    
} catch (Exception $e) {
    error_log("Search residents error: " . $e->getMessage());
    echo json_encode([]);
}
?>
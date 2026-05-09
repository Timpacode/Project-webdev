<?php
// test_update.php - Simple test to check if update works
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Test data
$test_data = [
    'resident_id' => 1, // Change to an existing resident ID
    'full_name' => 'John Smith Updated',
    'address' => '123 Updated Street',
    'contact_number' => '555-0000'
];

try {
    $query = "UPDATE resident SET 
              full_name = :full_name,
              address = :address,
              contact_number = :contact_number,
              updated_at = NOW()
              WHERE resident_id = :resident_id";
              
    $stmt = $db->prepare($query);
    $stmt->execute($test_data);
    
    echo "Update successful!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
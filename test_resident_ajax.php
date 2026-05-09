<?php
// test_resident_ajax.php
session_start();
$_SESSION['admin_id'] = 1; // Simulate login

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Test data
$test_data = [
    'resident_id' => 1,
    'full_name' => 'Test Name ' . time(),
    'address' => 'Test Address',
    'contact_number' => '555-0000',
    'email' => 'test@test.com',
    'family_count' => 2,
    'voter_status' => 'yes',
    'monthly_income' => 50000,
    'occupation' => 'Tester',
    'status' => 'active'
];

try {
    $query = "UPDATE resident SET 
              full_name = :full_name,
              email = :email,
              address = :address,
              contact_number = :contact_number,
              family_count = :family_count,
              voter_status = :voter_status,
              monthly_income = :monthly_income,
              occupation = :occupation,
              status = :status,
              updated_at = NOW()
              WHERE resident_id = :resident_id";
              
    $stmt = $db->prepare($query);
    
    if ($stmt->execute($test_data)) {
        echo "✅ AJAX ENDPOINT WORKS! Resident updated in database.";
        echo "<br>Rows affected: " . $stmt->rowCount();
    } else {
        echo "❌ Database update failed";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
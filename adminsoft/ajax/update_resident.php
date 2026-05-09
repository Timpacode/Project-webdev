<?php
session_start();
require_once '../config/database.php';

// Always send JSON header
header('Content-Type: application/json; charset=utf-8');

// Clear any accidental whitespace before output
if (ob_get_length()) ob_clean();

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['resident_id']) || empty($_POST['resident_id'])) {
        echo json_encode(['success' => false, 'message' => 'Resident ID is required']);
        exit;
    }
    
    // Validate required fields
    $required_fields = ['full_name', 'address', 'contact_number'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            echo json_encode([
                'success' => false, 
                'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required'
            ]);
            exit;
        }
    }
    
    try {
        $query = "UPDATE resident SET 
                  full_name = :full_name,
                  email = :email,
                  birthdate = :birthdate,
                  address = :address,
                  contact_number = :contact_number,
                  family_count = :family_count,
                  voter_status = :voter_status,
                  monthly_income = :monthly_income,
                  occupation = :occupation,
                  status = :status,
                  updated_at = NOW()
                  WHERE resident_id = :resident_id
                  LIMIT 1";
                  
        $stmt = $db->prepare($query);
        
        // Bind parameters safely
        $stmt->bindValue(':resident_id', intval($_POST['resident_id']), PDO::PARAM_INT);
        $stmt->bindValue(':full_name', trim($_POST['full_name']));
        $stmt->bindValue(':email', !empty($_POST['email']) ? trim($_POST['email']) : null);
        $stmt->bindValue(':birthdate', !empty($_POST['birthdate']) ? $_POST['birthdate'] : null);
        $stmt->bindValue(':address', trim($_POST['address']));
        $stmt->bindValue(':contact_number', trim($_POST['contact_number']));
        $stmt->bindValue(':family_count', isset($_POST['family_count']) ? intval($_POST['family_count']) : 0, PDO::PARAM_INT);
        $stmt->bindValue(':voter_status', $_POST['voter_status'] ?? 'no');
        $stmt->bindValue(':monthly_income', isset($_POST['monthly_income']) ? floatval($_POST['monthly_income']) : 0);
        $stmt->bindValue(':occupation', !empty($_POST['occupation']) ? trim($_POST['occupation']) : null);
        $stmt->bindValue(':status', $_POST['status'] ?? 'active');
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Resident updated successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Failed to update resident'
            ]);
        }
    } catch (PDOException $e) {
        error_log("Database error in update_resident.php: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Database error occurred'
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
exit;
?>
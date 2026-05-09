[file name]: ajax/update_admin.php
[file content begin]
<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Check if user is logged in and has permission
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_id = (int)$_POST['admin_id'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $contact_number = trim($_POST['contact_number'] ?? '');
    $role = $_POST['role'];
    $status = $_POST['status'];
    $password = $_POST['password'];
    
    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($role) || empty($status)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit();
    }
    
    try {
        // Check if username already exists (excluding current admin)
        $check_query = "SELECT admin_id FROM admin WHERE username = :username AND admin_id != :admin_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':username', $username);
        $check_stmt->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Username already exists']);
            exit();
        }
        
        // Check if email already exists (excluding current admin)
        $check_query = "SELECT admin_id FROM admin WHERE email = :email AND admin_id != :admin_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':email', $email);
        $check_stmt->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            exit();
        }
        
        // Build update query
        $update_fields = [
            'first_name = :first_name',
            'last_name = :last_name', 
            'username = :username',
            'email = :email',
            'role = :role',
            'contact_number = :contact_number',
            'status = :status',
            'updated_at = NOW()'
        ];
        
        $params = [
            ':first_name' => $first_name,
            ':last_name' => $last_name,
            ':username' => $username,
            ':email' => $email,
            ':role' => $role,
            ':contact_number' => $contact_number,
            ':status' => $status,
            ':admin_id' => $admin_id
        ];
        
        // Update password if provided
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $update_fields[] = 'password = :password';
            $params[':password'] = $hashed_password;
        }
        
        $update_query = "UPDATE admin SET " . implode(', ', $update_fields) . " WHERE admin_id = :admin_id";
        $update_stmt = $db->prepare($update_query);
        
        if ($update_stmt->execute($params)) {
            echo json_encode(['success' => true, 'message' => 'Admin updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update admin']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
[file content end]
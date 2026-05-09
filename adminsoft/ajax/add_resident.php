<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in and has permission
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if user has permission to add admins (only Captain and Secretary can add admins)
if ($_SESSION['role'] !== 'Captain' && $_SESSION['role'] !== 'Secretary') {
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions to add admin users']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $db = $database->getConnection();

        // Get form data
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role = $_POST['role'] ?? 'Staff';
        $status = $_POST['status'] ?? 'active';
        $contact_number = trim($_POST['contact_number'] ?? '');

        // Validate required fields
        if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
            exit();
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email format']);
            exit();
        }

        // Validate password strength
        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']);
            exit();
        }

        // Check if passwords match
        if ($password !== $confirm_password) {
            echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
            exit();
        }

        // Check if username already exists
        $check_username_sql = "SELECT admin_id FROM admin WHERE username = :username";
        $check_username_stmt = $db->prepare($check_username_sql);
        $check_username_stmt->bindParam(':username', $username);
        $check_username_stmt->execute();

        if ($check_username_stmt->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode(['success' => false, 'message' => 'Username already exists']);
            exit();
        }

        // Check if email already exists
        $check_email_sql = "SELECT admin_id FROM admin WHERE email = :email";
        $check_email_stmt = $db->prepare($check_email_sql);
        $check_email_stmt->bindParam(':email', $email);
        $check_email_stmt->execute();

        if ($check_email_stmt->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            exit();
        }

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert new admin
        $sql = "INSERT INTO admin (first_name, last_name, username, password, email, role, status, contact_number, created_at, updated_at) 
                VALUES (:first_name, :last_name, :username, :password, :email, :role, :status, :contact_number, NOW(), NOW())";
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':first_name', $first_name);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':contact_number', $contact_number);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Admin added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add admin']);
        }

    } catch (PDOException $e) {
        error_log("Database error in add_admin.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    } catch (Exception $e) {
        error_log("Error in add_admin.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
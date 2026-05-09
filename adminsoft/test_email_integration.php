<?php
session_start();
require_once 'config/database.php';
require_once 'config/email_service.php';

// Test the email service integration
$database = new Database();
$db = $database->getConnection();

echo "<h2>Testing Email Service Integration</h2>";

try {
    $emailService = new EmailService($db);
    
    // Test with a real request from your database
    $test_request_id = 1; // Change to an actual request ID
    $test_status = 'approved';
    $test_reason = 'Test integration reason';
    
    echo "<p><strong>Testing request ID:</strong> $test_request_id</p>";
    echo "<p><strong>Testing status:</strong> $test_status</p>";
    
    $result = $emailService->sendRequestNotification($test_request_id, $test_status, $test_reason);
    
    echo "<pre>";
    print_r($result);
    echo "</pre>";
    
    if ($result['success'] && $result['email_sent']) {
        echo "<p style='color: green; font-weight: bold;'>✓ Email service working correctly!</p>";
    } else if ($result['success'] && !$result['email_sent']) {
        echo "<p style='color: orange; font-weight: bold;'>⚠️ Email service responded but no email sent: " . $result['message'] . "</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>✗ Email service error: " . $result['message'] . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>✗ Exception: " . $e->getMessage() . "</p>";
}

// Test database connection and request data
echo "<h3>Testing Database Connection</h3>";
try {
    $query = "SELECT request_id, resident_name, resident_email, status FROM request WHERE request_id = :request_id";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':request_id', $test_request_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($request) {
        echo "<p style='color: green;'>✓ Database connection successful</p>";
        echo "<pre>Request Data: ";
        print_r($request);
        echo "</pre>";
    } else {
        echo "<p style='color: red;'>✗ Request not found in database</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database error: " . $e->getMessage() . "</p>";
}
?>
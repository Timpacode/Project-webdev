<?php
require_once 'auth.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $documentTypeId = $_POST['documentTypeId'] ?? '';
    $fullName = $_POST['fullName'] ?? '';
    $email = $_POST['email'] ?? '';
    $address = $_POST['address'] ?? '';
    $contactNumber = $_POST['contactNumber'] ?? '';
    $purpose = $_POST['purpose'] ?? '';
    $birthdate = $_POST['birthdate'] ?? '';
    $occupation = $_POST['occupation'] ?? '';
    
    // Validate required fields (purpose is now optional)
    if (empty($documentTypeId) || empty($fullName) || empty($email) || empty($address) || empty($contactNumber)) {
        http_response_code(400);
        echo "Please fill in all required fields.";
        exit;
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo "Please enter a valid email address.";
        exit;
    }
    
    // Set default purpose if empty
    if (empty($purpose)) {
        $purpose = "Not specified";
    }
    
    try {
        // Generate a unique request code
        $requestCode = 'REQ-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        // Get document type details for fee calculation
        $stmt = $pdo->prepare("SELECT base_fee, name, processing_days FROM document_type WHERE type_id = ?");
        $stmt->execute([$documentTypeId]);
        $documentType = $stmt->fetch();
        
        $feeAmount = $documentType ? $documentType['base_fee'] : 0.00;
        $processingDays = $documentType ? $documentType['processing_days'] : 3;
        
        // Insert request data directly (no resident_id lookup)
        $stmt = $pdo->prepare("INSERT INTO request (request_code, resident_email, resident_name, resident_contact, resident_address, document_type_id, purpose, fee_amount) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $requestCode, 
            $email,
            $fullName,
            $contactNumber,
            $address,
            $documentTypeId, 
            $purpose,
            $feeAmount
        ]);
        $requestId = $pdo->lastInsertId();
        
        // Create notification (without resident_id since it's not in your table)
        $stmt = $pdo->prepare("INSERT INTO notification (request_id, recipient_email, subject, message, status) 
                              VALUES (?, ?, ?, ?, 'sent')");
        $subject = "Document Request Received - " . ($documentType['name'] ?? 'Document');
        $message = "Dear $fullName,\n\nYour document request for " . ($documentType['name'] ?? 'document') . " (Request Code: $requestCode) has been received and is being processed.\n\nPurpose: $purpose\nFee: ₱$feeAmount\nEstimated Processing: $processingDays day(s)\n\nYou will be notified once your document is ready for pickup.\n\nThank you,\nBarangay Sta. Rita West";
        $stmt->execute([$requestId, $email, $subject, $message]);
        
        // Log request history
        $stmt = $pdo->prepare("INSERT INTO request_history (request_id, admin_id, action, old_status, new_status, notes) 
                              VALUES (?, 1, 'created', NULL, 'pending', 'Online request submitted')");
        $stmt->execute([$requestId]);
        
        // Return success message with request code
        $documentName = $documentType['name'] ?? 'document';
        echo "Your $documentName request has been submitted successfully! Your request code is: $requestCode. We've sent a confirmation email to $email. You can use the request code to track your application status.";
        
    } catch (PDOException $e) {
        error_log("Error processing request: " . $e->getMessage());
        http_response_code(500);
        echo "There was an error processing your request. Please try again or contact the barangay office. Error: " . $e->getMessage();
    }
} else {
    http_response_code(405);
    echo "Method not allowed";
}
?>
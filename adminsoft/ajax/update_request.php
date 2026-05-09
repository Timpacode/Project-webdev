<?php
session_start();
header('Content-Type: application/json');

// Turn off error display but log them
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['request_id']) || !isset($_POST['status'])) {
    echo json_encode(['success' => false, 'message' => 'Request ID and status required']);
    exit;
}

// Validate inputs
$request_id = filter_var($_POST['request_id'], FILTER_VALIDATE_INT);
$status = $_POST['status'];
$reason = isset($_POST['reason']) ? $_POST['reason'] : '';
$allowed_statuses = ['pending', 'approved', 'rejected', 'completed'];

if (!$request_id || $request_id < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid request ID']);
    exit;
}

if (!in_array($status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    // Database connection
    require_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    // First, check if request exists and get current status
    $checkQuery = "SELECT request_id, status, resident_name, resident_email FROM request WHERE request_id = :request_id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindValue(':request_id', $request_id, PDO::PARAM_INT);
    $checkStmt->execute();
    
    $existingRequest = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existingRequest) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }
    
    // Update the request
    $updateQuery = "UPDATE request SET 
                    status = :status, 
                    processed_by = :admin_id, 
                    processed_date = NOW(),
                    updated_at = NOW()";
    
    // Add reason if provided (for rejections)
    if (!empty($reason)) {
        $updateQuery .= ", rejection_reason = :reason";
    }
    
    $updateQuery .= " WHERE request_id = :request_id";
    
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindValue(':request_id', $request_id, PDO::PARAM_INT);
    $updateStmt->bindValue(':status', $status);
    $updateStmt->bindValue(':admin_id', $_SESSION['admin_id'], PDO::PARAM_INT);
    
    if (!empty($reason)) {
        $updateStmt->bindValue(':reason', $reason);
    }
    
    $updateStmt->execute();
    $rowsAffected = $updateStmt->rowCount();
    
    if ($rowsAffected > 0) {
        // Send email notification for status changes (except pending)
        $emailResult = ['success' => true, 'email_sent' => false, 'message' => ''];
        
        if ($status !== 'pending') {
            // Include email service from config directory
            require_once '../config/email_service.php';
            $emailService = new EmailService($db);
            $emailResult = $emailService->sendRequestNotification($request_id, $status, $reason);
        }
        
        // Success response
        $response = [
            'success' => true, 
            'message' => 'Request status updated successfully!',
            'rows_affected' => $rowsAffected,
            'old_status' => $existingRequest['status'],
            'new_status' => $status,
            'request_id' => $request_id,
            'resident_name' => $existingRequest['resident_name']
        ];
        
        // Add email result to response
        if ($status !== 'pending') {
            $response['email_result'] = $emailResult;
            if ($emailResult['email_sent']) {
                $response['message'] .= ' Email notification sent.';
            } else if (!$emailResult['success']) {
                $response['message'] .= ' But failed to send email notification: ' . $emailResult['message'];
            }
        }
        
        echo json_encode($response);
        
    } else {
        // No rows were updated
        echo json_encode([
            'success' => false, 
            'message' => 'No changes made. Request may already have this status.',
            'current_status' => $existingRequest['status'],
            'attempted_status' => $status
        ]);
    }
    
} catch (Exception $e) {
    error_log("Update request error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
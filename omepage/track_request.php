<?php
require_once 'auth.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestCode = $_POST['request_code'] ?? '';
    
    if (empty($requestCode)) {
        echo json_encode(['success' => false, 'message' => 'Please provide a request code.']);
        exit;
    }
    
    try {
        // Get request details
        $stmt = $pdo->prepare("
            SELECT 
                r.request_id,
                r.request_code,
                r.resident_name,
                r.resident_contact,
                r.resident_email,
                r.resident_address,
                dt.name AS document_type,
                r.purpose,
                r.specific_purpose,
                r.urgency_level,
                r.status,
                r.request_date,
                r.fee_amount,
                r.fee_paid,
                r.processed_date,
                r.pickup_date,
                r.rejection_reason,
                DATE_FORMAT(r.request_date, '%M %d, %Y') as request_date_formatted,
                DATE_FORMAT(r.processed_date, '%M %d, %Y') as processed_date_formatted,
                DATE_FORMAT(r.pickup_date, '%M %d, %Y') as pickup_date_formatted
            FROM request r
            JOIN document_type dt ON r.document_type_id = dt.type_id
            WHERE r.request_code = ?
        ");
        $stmt->execute([$requestCode]);
        $request = $stmt->fetch();
        
        if (!$request) {
            echo json_encode(['success' => false, 'message' => 'Request not found. Please check your request code.']);
            exit;
        }
        
        // Get request history
        $stmt = $pdo->prepare("
            SELECT 
                rh.action,
                rh.old_status,
                rh.new_status,
                rh.notes,
                rh.change_date,
                CONCAT(a.first_name, ' ', a.last_name) as admin_name
            FROM request_history rh
            LEFT JOIN admin a ON rh.admin_id = a.admin_id
            WHERE rh.request_id = ?
            ORDER BY rh.change_date ASC
        ");
        $stmt->execute([$request['request_id']]);
        $history = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'request' => $request,
            'history' => $history
        ]);
        
    } catch (PDOException $e) {
        error_log("Error tracking request: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
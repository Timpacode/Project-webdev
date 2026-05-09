<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../includes/header.php';

require_once '../config/database.php';
require_once '../config/auth.php';

// Check authentication
$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$auth->checkAuth();

// Include the DocumentGenerator class
require_once '../classes/DocumentGenerator.php';
// Include the EmailService class
require_once '../config/email_service.php';

// Create document generator instance
$docGenerator = new DocumentGenerator();
$installationStatus = $docGenerator->checkPHPWordInstallation();

// Create email service instance
$emailService = new EmailService($db);

// Get recently generated documents
$recent_documents = $docGenerator->getRecentDocuments(5);

// Get statistics for dashboard
$stats = $docGenerator->getStatistics();

// Get template status
$templateStatus = $docGenerator->verifyTemplates();

// Show warning if TemplateProcessor is not available
if (!$installationStatus['template_processor_available']) {
    $warning = "⚠️ PHPWord TemplateProcessor is not available. Documents will be generated without variable replacement. Please install PHPWord using: composer require phpoffice/phpword";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_document'])) {
    try {
        $residentId = $_POST['resident_id'];
        $documentType = $_POST['document_type'];
        $requestId = $_POST['request_id'] ?? null;
        
        $result = $docGenerator->generateDocument($residentId, $documentType);
        
        // If request ID is provided, update the request status to "completed" ONLY if it's approved
        if (!empty($requestId)) {
            // First check if the request exists and is approved
            $checkQuery = "SELECT status, resident_email, resident_name, request_code, document_type_id FROM request WHERE request_id = :request_id";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->bindValue(':request_id', $requestId, PDO::PARAM_INT);
            $checkStmt->execute();
            
            $requestData = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($requestData && $requestData['status'] === 'approved') {
                // Only update to completed if the request is approved
                $updateQuery = "UPDATE request SET status = 'completed', processed_by = :admin_id, processed_date = NOW(), updated_at = NOW() WHERE request_id = :request_id";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->bindValue(':request_id', $requestId, PDO::PARAM_INT);
                $updateStmt->bindValue(':admin_id', $_SESSION['admin_id'], PDO::PARAM_INT);
                $updateStmt->execute();
                
                // Send email notification for completion
                $emailResult = $emailService->sendRequestNotification($requestId, 'completed');
                
                // Add email result to the document result
                $result['email_result'] = $emailResult;
                
                // Add request ID to result for display
                $result['request_id'] = $requestId;
                $result['request_completed'] = true;
                $result['previous_status'] = 'approved';
            } else {
                // Request is not approved, don't update status but still include request info
                $result['request_id'] = $requestId;
                $result['request_completed'] = false;
                $result['previous_status'] = $requestData['status'] ?? 'unknown';
            }
        }
        
        $_SESSION['document_result'] = $result;
        
        header('Location: documents.php?success=1');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['document_error'] = $e->getMessage();
        header('Location: documents.php?error=1');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Generator - Barangay Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    /* Main Container */
    .dashboard-container {
        position: absolute;
        left: 325px;
        max-width: 85%;
        margin: 0;
        padding: 0;
        margin-right: 300px !important;
        width: 100%;
        min-height: calc(100vh - 120px);
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    }

    /* Header Section */
    .header {
        margin-bottom: 30px;
    }

    .header h1 {
        font-size: 2rem;
        font-weight: 800;
        color: #2c3e50;
        margin-bottom: 8px;
        position: relative;
    }

    .header h1::after {
        content: '';
        position: absolute;
        bottom: -8px;
        left: 0;
        width: 60px;
        height: 3px;
        background: linear-gradient(90deg, #4361ee, #3a0ca3);
        border-radius: 2px;
    }

    .header p {
        color: #6c757d;
        font-size: 1.1rem;
        margin-top: 15px;
    }

    /* Card Styles */
    .card {
        background: linear-gradient(135deg, #ffffff 0%, #fafbfc 100%);
        border-radius: 16px;
        box-shadow: 0 4px 25px rgba(0,0,0,0.1);
        margin-bottom: 30px;
        overflow: hidden;
        border: 1px solid rgba(0,0,0,0.05);
        transition: all 0.3s ease;
    }

    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.15);
    }

    .card-header {
        padding: 25px 30px;
        border-bottom: 1px solid #e9ecef;
        background: rgba(248,249,250,0.8);
        backdrop-filter: blur(10px);
    }

    .card-header h3 {
        font-size: 1.4rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 5px;
        position: relative;
    }

    .card-header h3::after {
        content: '';
        position: absolute;
        bottom: -8px;
        left: 0;
        width: 40px;
        height: 3px;
        background: linear-gradient(90deg, #4361ee, #3a0ca3);
        border-radius: 2px;
    }

    .card-header p {
        color: #6c757d;
        font-size: 0.95rem;
    }

    .card-body {
        padding: 30px;
    }

    /* Quick Stats */
    .quick-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 25px;
        margin-bottom: 35px;
    }

    .stat-card {
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        padding: 30px 25px;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        text-align: center;
        border-left: 5px solid #4361ee;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #4361ee, #6c757d);
        opacity: 0.7;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.15);
    }

    .stat-number {
        font-size: 42px;
        font-weight: 800;
        color: #4361ee;
        margin-bottom: 5px;
        text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .stat-label {
        font-size: 14px;
        color: #6c757d;
        margin-bottom: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Form Styles */
    .form-group {
        margin-bottom: 25px;
    }

    .form-group label {
        display: block;
        margin-bottom: 10px;
        font-weight: 600;
        color: #495057;
        font-size: 0.95rem;
    }

    .form-control {
        width: 100%;
        padding: 14px 18px;
        border: 2px solid #e9ecef;
        border-radius: 10px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }

    .form-control:focus {
        outline: none;
        border-color: #4361ee;
        box-shadow: 0 4px 15px rgba(67, 97, 238, 0.15);
        transform: translateY(-1px);
    }

    /* Button Styles */
    .btn {
        display: inline-flex;
        align-items: center;
        padding: 14px 28px;
        border: none;
        border-radius: 10px;
        font-size: 0.95rem;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.3s ease;
        gap: 10px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        position: relative;
        overflow: hidden;
    }

    .btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
        transition: left 0.5s;
    }

    .btn:hover::before {
        left: 100%;
    }

    .btn-primary {
        background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
        color: white;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #3a56d4 0%, #2d0a8c 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(67, 97, 238, 0.3);
    }

    .btn-success {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
    }

    .btn-success:hover {
        background: linear-gradient(135deg, #218838 0%, #1a936c 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(40, 167, 69, 0.3);
    }

    /* Alert Styles */
    .alert {
        padding: 20px 25px;
        border-radius: 12px;
        margin-bottom: 25px;
        border-left: 5px solid;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        backdrop-filter: blur(10px);
    }

    .alert-warning {
        background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        border-color: #f59e0b;
        color: #856404;
    }

    .alert-success {
        background: linear-gradient(135deg, #d4edda 0%, #c8e6c9 100%);
        border-color: #28a745;
        color: #155724;
    }

    .alert-danger {
        background: linear-gradient(135deg, #f8d7da 0%, #ffcdd2 100%);
        border-color: #dc3545;
        color: #721c24;
    }

    /* Search Box Styles */
    .search-box {
        position: relative;
    }

    .search-results {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        border: 2px solid #e9ecef;
        border-top: none;
        border-radius: 0 0 10px 10px;
        max-height: 250px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .search-result-item {
        padding: 15px 20px;
        cursor: pointer;
        border-bottom: 1px solid #e9ecef;
        transition: all 0.2s ease;
        background: white;
    }

    .search-result-item:hover {
        background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 100%);
        border-left: 4px solid #4361ee;
        transform: translateX(2px);
    }

    .search-result-item:last-child {
        border-bottom: none;
    }

    /* Request Search Box Styles */
    .request-search-box {
        position: relative;
    }

    .request-search-results {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        border: 2px solid #e9ecef;
        border-top: none;
        border-radius: 0 0 10px 10px;
        max-height: 250px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .request-search-result-item {
        padding: 15px 20px;
        cursor: pointer;
        border-bottom: 1px solid #e9ecef;
        transition: all 0.2s ease;
        background: white;
    }

    .request-search-result-item:hover {
        background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 100%);
        border-left: 4px solid #28a745;
        transform: translateX(2px);
    }

    .request-search-result-item:last-child {
        border-bottom: none;
    }

    /* Resident Info Card */
    .resident-info {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        padding: 20px;
        border-radius: 10px;
        margin: 20px 0;
        border-left: 4px solid #4361ee;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }

    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    .info-item {
        margin-bottom: 12px;
    }

    .info-label {
        font-weight: 600;
        color: #495057;
        font-size: 0.9rem;
    }

    .info-value {
        color: #212529;
        font-weight: 500;
    }

    /* Request Info Card */
    .request-info {
        background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%);
        padding: 20px;
        border-radius: 10px;
        margin: 20px 0;
        border-left: 4px solid #28a745;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }

    /* Template List Styles */
    .template-list {
        margin-top: 25px;
    }

    .template-item {
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        padding: 15px 20px;
        margin-bottom: 10px;
        border-radius: 8px;
        border-left: 4px solid #4361ee;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        transition: all 0.3s ease;
    }

    .template-item:hover {
        transform: translateX(5px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }

    .template-name {
        font-weight: 600;
        color: #2c3e50;
    }

    .template-status {
        background: #28a745;
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .template-missing {
        background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
    }

    /* Table Styles */
    .table-container {
        overflow-x: auto;
        width: 100%;
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }

    table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    th, td {
        padding: 16px 20px;
        text-align: left;
        border-bottom: 1px solid #f1f3f4;
    }

    th {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        font-weight: 700;
        color: #495057;
        font-size: 0.9rem;
        position: sticky;
        top: 0;
        border-bottom: 2px solid #dee2e6;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    tr {
        background: white;
        transition: all 0.3s ease;
    }

    tr:hover {
        background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 100%);
        transform: scale(1.01);
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }

    /* Grid Layout */
    .grid-2col {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-top: 20px;
    }

    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 8px;
        justify-content: flex-start;
    }

    .action-btn {
        padding: 10px 14px;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 40px;
        height: 40px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        position: relative;
        overflow: hidden;
    }

    .action-btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
        transition: left 0.5s;
    }

    .action-btn:hover::before {
        left: 100%;
    }

    .btn-outline { 
        background: transparent; 
        border: 2px solid #6c757d; 
        color: #6c757d; 
    }

    .btn-outline:hover { 
        background: #6c757d; 
        color: white; 
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(108,117,125,0.3);
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 80px 40px;
        color: #6c757d;
    }

    .empty-state i {
        font-size: 64px;
        margin-bottom: 20px;
        opacity: 0.3;
    }

    .empty-state h4 {
        font-size: 1.5rem;
        margin-bottom: 10px;
        color: #495057;
    }

    .empty-state p {
        font-size: 1rem;
        opacity: 0.7;
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
        .dashboard-container {
            padding: 25px;
        }
        
        .quick-stats {
            gap: 20px;
        }
    }

    @media (max-width: 1024px) {
        .grid-2col {
            grid-template-columns: 1fr;
        }
        
        .quick-stats {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .card-header {
            padding: 20px 25px;
        }
    }

    @media (max-width: 768px) {
        .dashboard-container {
            padding: 20px 15px;
            margin-left: 0;
            width: 100%;
        }
        
        .quick-stats {
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .stat-card {
            padding: 25px 20px;
        }
        
        .stat-number {
            font-size: 36px;
        }
        
        .card-header {
            padding: 20px;
        }
        
        .info-grid {
            grid-template-columns: 1fr;
        }
        
        .btn {
            width: 100%;
            justify-content: center;
        }
        
        .action-buttons {
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .action-btn {
            min-width: 36px;
            height: 36px;
            padding: 8px 10px;
        }
    }

    @media (max-width: 480px) {
        .dashboard-container {
            padding: 15px 10px;
        }
        
        .header {
            padding: 20px;
        }
        
        .header h1 {
            font-size: 1.5rem;
        }
        
        .card-body {
            padding: 20px;
        }
        
        th, td {
            padding: 12px 15px;
            font-size: 0.85rem;
        }
        
        .stat-card {
            padding: 20px 15px;
        }
        
        .stat-number {
            font-size: 32px;
        }
    }

    /* Utility Classes */
    .d-none {
        display: none !important;
    }

    .text-center {
        text-align: center;
    }

    .mb-0 {
        margin-bottom: 0 !important;
    }

    /* Text Muted */
    .text-muted {
        color: #6c757d !important;
    }

    /* Form Text */
    .form-text {
        display: block;
        margin-top: 0.25rem;
        font-size: 0.875em;
        color: #6c757d;
    }
</style>
</head>
<body>
    <!-- Main Content -->
    <div class="main-content">
        <!-- Dashboard Container - Added to match dashboard.php -->
        <div class="dashboard-container">

        <!-- Documents Content -->
        <div id="documents-content">
            <!-- Installation Warning -->
            <?php if (isset($warning)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $warning; ?>
                </div>
            <?php endif; ?>

            <div class="card">

                
                <div class="card-body">
                    <div class="grid-2col">
                        <!-- Left Column - Document Generation Form -->
                        <div>
                            <h4 style="margin-bottom: 20px;">Generate Document</h4>
                            
                            <form id="document-form" method="POST">
                                <input type="hidden" name="generate_document" value="1">
                                
                                <!-- Request Code Search -->
                                <div class="form-group">
                                    <label for="request-search">Search Approved Request (Optional)</label>
                                    <div class="request-search-box">
                                        <input type="text" id="request-search" class="form-control" 
                                               placeholder="Type request code to search approved requests..." 
                                               autocomplete="off">
                                        <div id="request-search-results" class="request-search-results"></div>
                                    </div>
                                    <input type="hidden" id="request-id" name="request_id">
                                    <small class="form-text text-muted" id="selected-request-text" style="display: none;">
                                        Selected Request: <span id="selected-request-code"></span> - <span id="selected-request-resident"></span>
                                    </small>
                                </div>

                                <div id="request-info" class="request-info" style="display: none;">
                                    <h5>📋 Request Information</h5>
                                    <div class="info-grid" id="request-info-content">
                                        <!-- Request info will be loaded here -->
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="resident-search">Search and Select Resident *</label>
                                    <div class="search-box">
                                        <input type="text" id="resident-search" class="form-control" 
                                               placeholder="Type resident name or code to search..." 
                                               autocomplete="off">
                                        <div id="search-results" class="search-results"></div>
                                    </div>
                                    <input type="hidden" id="resident-id" name="resident_id" required>
                                    <small class="form-text text-muted" id="selected-resident-text" style="display: none;">
                                        Selected: <span id="selected-resident-name"></span> (ID: <span id="selected-resident-id"></span>)
                                    </small>
                                </div>

                                <div id="resident-info" class="resident-info" style="display: none;">
                                    <h5>👤 Resident Information</h5>
                                    <div class="info-grid" id="resident-info-content">
                                        <!-- Resident info will be loaded here -->
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="document-type">Document Type *</label>
                                    <select id="document-type" name="document_type" class="form-control" required>
                                        <option value="">Select document type...</option>
                                        <?php 
                                        $documentTypes = $docGenerator->getDocumentTypes();
                                        foreach ($documentTypes as $type): 
                                            $name = $type['NAME'] ?? $type['name'] ?? '';
                                            $fee = $type['base_fee'] ?? 0;
                                            $displayFee = ($fee > 0) ? " - ₱" . number_format($fee, 2) : " - Free";
                                            
                                            if (!empty($name)):
                                        ?>
                                            <option value="<?= htmlspecialchars($name) ?>">
                                                📋 <?= htmlspecialchars($name) ?> <?= $displayFee ?>
                                            </option>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-primary" id="generate-btn">
                                    <i class="fas fa-file-download"></i> Generate Document
                                </button>
                            </form>

                            <?php if (isset($_SESSION['document_result'])): ?>
                                <?php $result = $_SESSION['document_result']; unset($_SESSION['document_result']); ?>
                                <div class="alert alert-success" style="margin-top: 20px;">
                                    <strong>✅ Document Generated Successfully!</strong><br>
                                    <strong>Resident:</strong> <?= htmlspecialchars($result['resident_name']) ?><br>
                                    <strong>Document Type:</strong> <?= htmlspecialchars($result['document_type']) ?><br>
                                    <?php if (isset($result['request_id']) && $result['request_completed']): ?>
                                        <strong>Request:</strong> Completed and marked as finished<br>
                                        <?php if (isset($result['email_result'])): ?>
                                            <strong>Email Notification:</strong> 
                                            <?php if ($result['email_result']['email_sent']): ?>
                                                ✅ Sent successfully to <?= htmlspecialchars($result['email_result']['resident_email'] ?? 'resident') ?>
                                            <?php else: ?>
                                                ⚠️ Failed: <?= htmlspecialchars($result['email_result']['message'] ?? 'Unknown error') ?>
                                            <?php endif; ?><br>
                                        <?php endif; ?>
                                    <?php elseif (isset($result['request_id']) && !$result['request_completed']): ?>
                                        <strong>Request:</strong> Not completed (Status was: <?= htmlspecialchars($result['previous_status']) ?>)<br>
                                    <?php endif; ?>
                                    <strong>File:</strong> <?= htmlspecialchars($result['file_name']) ?><br>
                                    <strong>Generated:</strong> <?= $result['generated_at'] ?><br>
                                    <?php if (isset($result['variables_replaced'])): ?>
                                        <strong>Variables Replaced:</strong> <?= $result['variables_replaced'] ? 'Yes' : 'No' ?><br>
                                    <?php endif; ?>
                                    <a href="<?= $result['download_url'] ?>" class="btn btn-success" target="_blank" style="margin-top: 10px;" id="download-btn">
                                        <i class="fas fa-download"></i> Download Document
                                    </a>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($_SESSION['document_error'])): ?>
                                <div class="alert alert-danger" style="margin-top: 20px;">
                                    <strong>❌ Error:</strong> <?= htmlspecialchars($_SESSION['document_error']) ?>
                                </div>
                                <?php unset($_SESSION['document_error']); ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Right Column - System Info -->
                        <div>
                            <h4 style="margin-bottom: 20px;">System Overview</h4>

                            <div class="quick-stats">
                                <div class="stat-card">
                                    <div class="stat-number"><?= $stats['total_residents'] ?></div>
                                    <div class="stat-label">Total Residents</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-number"><?= $stats['documents_today'] ?></div>
                                    <div class="stat-label">Documents Today</div>
                                </div>
                            
                            </div>

                            <div class="template-list">
                                <h5 style="margin-bottom: 15px;">📁 Template Status</h5>
                                
                                <?php foreach ($templateStatus as $template => $status): ?>
                                    <div class="template-item">
                                        <span class="template-name"><?= htmlspecialchars($template) ?></span>
                                        <span class="template-status <?= $status['exists'] ? '' : 'template-missing' ?>">
                                            <?= $status['exists'] ? '✅ Available' : '❌ Missing' ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Installation Instructions -->
                            <?php if (!$installationStatus['template_processor_available']): ?>
                                <div class="alert alert-danger" style="margin-top: 20px;">
                                    <h5>🚨 Installation Required</h5>
                                    <p>To enable variable replacement in documents, install PHPWord:</p>
                                    <div style="background: white; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 12px;">
                                        <strong>Using Composer:</strong><br>
                                        cd /path/to/your/project<br>
                                        composer require phpoffice/phpword
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recently Generated Documents -->
            <div class="card">
                <div class="card-header">
                    <h3>Recently Generated Documents</h3>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Document ID</th>
                                    <th>File Name</th>
                                    <th>Resident</th>
                                    <th>Document Type</th>
                                    <th>Generated Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recent_documents)): ?>
                                    <?php foreach ($recent_documents as $doc): ?>
                                        <tr>
                                            <td>DOC-<?php echo $doc['document_id'] ?? 'N/A'; ?></td>
                                            <td><?php echo htmlspecialchars($doc['file_name']); ?></td>
                                            <td><?php echo htmlspecialchars($doc['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($doc['document_type']); ?></td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($doc['generation_date'])); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <?php if (isset($doc['file_name']) && file_exists('../documents/generated/' . $doc['file_name'])): ?>
                                                        <a href="../documents/generated/<?php echo $doc['file_name']; ?>" class="btn action-btn download-action-btn" download>
                                                            <i class="fas fa-download"></i> Download
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">File not found</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 20px; color: #6c757d;">
                                            No documents generated yet
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        </div> <!-- End dashboard-container -->
    </div>

<script>
    // Search functionality for residents
    document.getElementById('resident-search').addEventListener('input', function(e) {
        const searchTerm = e.target.value.trim();
        const resultsDiv = document.getElementById('search-results');
        
        if (searchTerm.length < 2) {
            resultsDiv.style.display = 'none';
            return;
        }

        // Show loading
        resultsDiv.innerHTML = '<div class="search-result-item">Searching...</div>';
        resultsDiv.style.display = 'block';

        fetch('../ajax/search_residents.php?q=' + encodeURIComponent(searchTerm))
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                resultsDiv.innerHTML = '';
                
                if (data && data.length > 0) {
                    data.forEach(resident => {
                        const item = document.createElement('div');
                        item.className = 'search-result-item';
                        
                        item.innerHTML = `
                            <div style="font-weight: bold;">${resident.full_name || 'No Name'}</div>
                            <div style="font-size: 0.9em; color: #666;">
                                ID: ${resident.resident_id} | Code: ${resident.resident_code || 'N/A'}
                            </div>
                            <div style="font-size: 0.8em; color: #888;">
                                ${resident.address || 'No address'} | ${resident.contact_number || 'No contact'}
                            </div>
                        `;
                        
                        item.addEventListener('click', function() {
                            selectResident(resident);
                        });
                        
                        resultsDiv.appendChild(item);
                    });
                    resultsDiv.style.display = 'block';
                } else {
                    resultsDiv.innerHTML = '<div class="search-result-item" style="color: #666; text-align: center;">No residents found</div>';
                    resultsDiv.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                resultsDiv.innerHTML = '<div class="search-result-item" style="color: #dc3545; text-align: center;">Error searching residents</div>';
                resultsDiv.style.display = 'block';
            });
    });

    // Search functionality for approved requests - IMPROVED VERSION
    document.getElementById('request-search').addEventListener('input', function(e) {
        const searchTerm = e.target.value.trim();
        const resultsDiv = document.getElementById('request-search-results');
        
        if (searchTerm.length === 0) {
            resultsDiv.style.display = 'none';
            document.getElementById('request-id').value = '';
            document.getElementById('selected-request-text').style.display = 'none';
            document.getElementById('request-info').style.display = 'none';
            return;
        }

        // Show loading
        resultsDiv.innerHTML = '<div class="request-search-result-item">Searching approved requests...</div>';
        resultsDiv.style.display = 'block';

        console.log('Searching for requests with term:', searchTerm);

        // Search for approved requests only
        fetch('../ajax/search_requests.php?q=' + encodeURIComponent(searchTerm) + '&status=approved')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('Search results:', data);
                resultsDiv.innerHTML = '';
                
                if (data && data.length > 0) {
                    data.forEach(request => {
                        const item = document.createElement('div');
                        item.className = 'request-search-result-item';
                        
                        item.innerHTML = `
                            <div style="font-weight: bold;">📋 ${request.request_code || 'No Code'}</div>
                            <div style="font-size: 0.9em; color: #666;">
                                Resident: ${request.resident_name || 'No Name'}
                            </div>
                            <div style="font-size: 0.8em; color: #888;">
                                Document: ${request.document_type || 'N/A'} | Status: ${request.status || 'N/A'}
                            </div>
                            <div style="font-size: 0.8em; color: #28a745;">
                                Request Date: ${request.request_date ? new Date(request.request_date).toLocaleDateString() : 'N/A'}
                            </div>
                        `;
                        
                        item.addEventListener('click', function() {
                            selectRequest(request);
                        });
                        
                        resultsDiv.appendChild(item);
                    });
                    resultsDiv.style.display = 'block';
                } else {
                    resultsDiv.innerHTML = '<div class="request-search-result-item" style="color: #666; text-align: center;">No approved requests found. Try searching by request code (REQ-...) or resident name.</div>';
                    resultsDiv.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Request search error:', error);
                resultsDiv.innerHTML = '<div class="request-search-result-item" style="color: #dc3545; text-align: center;">Error searching requests: ' + error.message + '</div>';
                resultsDiv.style.display = 'block';
            });
    });

    // Select resident function
    function selectResident(resident) {
        document.getElementById('resident-id').value = resident.resident_id;
        document.getElementById('resident-search').value = resident.full_name || 'Unknown';
        document.getElementById('selected-resident-name').textContent = resident.full_name || 'Unknown';
        document.getElementById('selected-resident-id').textContent = resident.resident_id;
        document.getElementById('selected-resident-text').style.display = 'block';
        document.getElementById('search-results').style.display = 'none';
        
        // Load and display resident information
        loadResidentInfo(resident.resident_id);
    }

// Select request function
function selectRequest(request) {
    console.log('Selecting request:', request);
    document.getElementById('request-id').value = request.request_id;
    document.getElementById('request-search').value = request.request_code;
    document.getElementById('selected-request-code').textContent = request.request_code;
    document.getElementById('selected-request-resident').textContent = request.resident_name;
    document.getElementById('selected-request-text').style.display = 'block';
    document.getElementById('request-search-results').style.display = 'none';
    
    // Load and display request information
    loadRequestInfo(request.request_id);
    
    // Note: Resident selection is now separate and must be done manually
    // The resident field will not be auto-filled when selecting a request
}

    // Load resident information
    function loadResidentInfo(residentId) {
        fetch('../ajax/get_resident_info.php?id=' + residentId)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data && data.success && data.data) {
                    const infoContent = document.getElementById('resident-info-content');
                    const residentData = data.data;
                    infoContent.innerHTML = `
                        <div class="info-item">
                            <div class="info-label">Full Name</div>
                            <div class="info-value">${residentData.full_name || 'N/A'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Resident Code</div>
                            <div class="info-value">${residentData.resident_code || 'N/A'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Address</div>
                            <div class="info-value">${residentData.address || 'N/A'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Contact</div>
                            <div class="info-value">${residentData.contact_number || 'N/A'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Email</div>
                            <div class="info-value">${residentData.email || 'N/A'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Birthdate</div>
                            <div class="info-value">${residentData.birthdate || 'N/A'}</div>
                        </div>
                    `;
                    document.getElementById('resident-info').style.display = 'block';
                } else {
                    console.error('Invalid resident data format:', data);
                }
            })
            .catch(error => {
                console.error('Error loading resident info:', error);
                const infoContent = document.getElementById('resident-info-content');
                infoContent.innerHTML = '<div class="info-item" style="grid-column: 1 / -1; text-align: center; color: #dc3545;">Error loading resident information</div>';
                document.getElementById('resident-info').style.display = 'block';
            });
    }

    // Load request information
    function loadRequestInfo(requestId) {
        console.log('Loading request info for ID:', requestId);
        fetch('../ajax/get_request_info.php?id=' + requestId)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('Request info response:', data);
                if (data && data.success && data.request) {
                    const infoContent = document.getElementById('request-info-content');
                    const requestData = data.request;
                    infoContent.innerHTML = `
                        <div class="info-item">
                            <div class="info-label">Request Code</div>
                            <div class="info-value">${requestData.request_code || 'N/A'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Document Type</div>
                            <div class="info-value">${requestData.document_type || 'N/A'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Purpose</div>
                            <div class="info-value">${requestData.purpose || 'N/A'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Specific Purpose</div>
                            <div class="info-value">${requestData.specific_purpose || 'N/A'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Request Date</div>
                            <div class="info-value">${requestData.request_date || 'N/A'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Status</div>
                            <div class="info-value">${requestData.status || 'N/A'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Resident Name</div>
                            <div class="info-value">${requestData.resident_name || 'N/A'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Resident Contact</div>
                            <div class="info-value">${requestData.resident_contact || 'N/A'}</div>
                        </div>
                    `;
                    document.getElementById('request-info').style.display = 'block';
                } else {
                    console.error('Invalid request data format:', data);
                    const infoContent = document.getElementById('request-info-content');
                    infoContent.innerHTML = '<div class="info-item" style="grid-column: 1 / -1; text-align: center; color: #dc3545;">Error loading request information</div>';
                    document.getElementById('request-info').style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error loading request info:', error);
                const infoContent = document.getElementById('request-info-content');
                infoContent.innerHTML = '<div class="info-item" style="grid-column: 1 / -1; text-align: center; color: #dc3545;">Error loading request information</div>';
                document.getElementById('request-info').style.display = 'block';
            });
    }

    // Close search results when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.search-box')) {
            document.getElementById('search-results').style.display = 'none';
        }
        if (!e.target.closest('.request-search-box')) {
            document.getElementById('request-search-results').style.display = 'none';
        }
    });

    // Form validation
    document.getElementById('document-form').addEventListener('submit', function(e) {
        const residentId = document.getElementById('resident-id').value;
        const documentType = document.getElementById('document-type').value;
        
        if (!residentId || !documentType) {
            e.preventDefault();
            alert('Please select a resident and document type before generating.');
            return false;
        }
        
        // Show loading state
        const generateBtn = document.getElementById('generate-btn');
        generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        generateBtn.disabled = true;
    });

    // Auto-download if download button exists
    document.addEventListener('DOMContentLoaded', function() {
        const downloadBtn = document.getElementById('download-btn');
        if (downloadBtn) {
            setTimeout(() => {
                downloadBtn.click();
            }, 1000);
        }
    });

    // Handle Enter key in search inputs
    document.getElementById('request-search').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            // Trigger search on Enter
            this.dispatchEvent(new Event('input'));
        }
    });

    document.getElementById('resident-search').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            // Trigger search on Enter
            this.dispatchEvent(new Event('input'));
        }
    });

    // Debug function to check what requests are available
    function debugRequests() {
        console.log('Debug: Checking all approved requests...');
        fetch('../ajax/search_requests.php?q=&status=approved')
            .then(response => response.json())
            .then(data => {
                console.log('All approved requests in database:', data);
                if (data && data.length > 0) {
                    data.forEach(req => {
                        console.log('Request:', req.request_code, 'ID:', req.request_id, 'Resident:', req.resident_name, 'Status:', req.status);
                    });
                } else {
                    console.log('No approved requests found in database');
                    console.log('This might be because:');
                    console.log('1. There are no requests with status "approved"');
                    console.log('2. The database connection is failing');
                    console.log('3. The query is incorrect');
                }
            })
            .catch(error => {
                console.error('Debug error:', error);
            });
    }

    // Test the search on page load
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Document generator loaded');
        // Uncomment the line below to see all approved requests in console
        // debugRequests();
    });
</script>
</body>
</html>
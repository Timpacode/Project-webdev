<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../vendor/autoload.php';

class EmailService {
    private $conn;
    private $mail;
    
    public function __construct($db) {
        $this->conn = $db;
        $this->initializeMailer();
    }
    
    private function initializeMailer() {
        $this->mail = new PHPMailer(true);
        
        // Gmail SMTP Configuration
        $this->mail->isSMTP();
        $this->mail->Host = 'smtp.gmail.com';
        $this->mail->SMTPAuth = true;
        $this->mail->Username = 'jericdulay008@gmail.com';
        $this->mail->Password = 'gxhf bdcm qhui wwzo';
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port = 587;
        
        // Sender information
        $this->mail->setFrom('jericdulay008@gmail.com', 'BarangayHub System');
        $this->mail->addReplyTo('jericdulay008@gmail.com', 'BarangayHub System');
        $this->mail->isHTML(true);
        
        // Debugging
        $this->mail->SMTPDebug = 0;
    }
    
    public function sendRequestNotification($request_id, $status, $reason = '') {
        try {
            // Get request details
            $query = "SELECT r.*, dt.name as document_type, dt.base_fee
                      FROM request r
                      JOIN document_type dt ON r.document_type_id = dt.type_id
                      WHERE r.request_id = :request_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':request_id', $request_id);
            $stmt->execute();
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$request) {
                return [
                    'success' => false, 
                    'message' => 'Request not found',
                    'email_sent' => false
                ];
            }
            
            $email_to_use = $request['resident_email'];
            
            if (empty($email_to_use)) {
                return [
                    'success' => false, 
                    'message' => 'No email address found for resident',
                    'email_sent' => false,
                    'resident_name' => $request['resident_name']
                ];
            }
            
            // Generate email content
            $emailContent = $this->generateEmailContent($request, $status, $reason);
            
            // Send email using PHPMailer
            $emailResult = $this->sendEmail(
                $email_to_use,
                $request['resident_name'],
                $emailContent['subject'],
                $emailContent['body'],
                $emailContent['altBody']
            );
            
            // Log notification
            $logResult = $this->logNotification(
                $request_id,
                $email_to_use,
                $emailContent['subject'],
                $emailContent['body'],
                $emailResult['success'] ? 'sent' : 'failed'
            );
            
            return [
                'success' => $emailResult['success'],
                'message' => $emailResult['message'],
                'email_sent' => $emailResult['success'],
                'resident_email' => $email_to_use,
                'resident_name' => $request['resident_name'],
                'notification_id' => $logResult ? $this->conn->lastInsertId() : null
            ];
            
        } catch (Exception $e) {
            error_log("Email notification error: " . $e->getMessage());
            return [
                'success' => false, 
                'message' => 'Email service error: ' . $e->getMessage(),
                'email_sent' => false
            ];
        }
    }
    
    private function sendEmail($toEmail, $toName, $subject, $htmlBody, $textBody) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($toEmail, $toName);
            $this->mail->Subject = $subject;
            $this->mail->Body = $htmlBody;
            $this->mail->AltBody = $textBody;
            
            $this->mail->send();
            return ['success' => true, 'message' => 'Email sent successfully'];
            
        } catch (Exception $e) {
            error_log("PHPMailer error: " . $this->mail->ErrorInfo);
            return ['success' => false, 'message' => 'Mailer Error: ' . $this->mail->ErrorInfo];
        }
    }
    
    private function generateEmailContent($request, $status, $reason) {
        $document_type = $request['document_type'];
        $request_code = $request['request_code'];
        $full_name = $request['resident_name'];
        $request_date = date('F j, Y', strtotime($request['request_date']));
        $fee_amount = $request['fee_amount'];
        $base_fee = $request['base_fee'];
        
        $configs = [
            'approved' => [
                'subject' => "Your Document Request has been Approved - $request_code",
                'body' => "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background: #4361ee; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                            .content { background: #f9f9f9; padding: 20px; }
                            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
                            .status-approved { color: #0c9b49; font-weight: bold; }
                            .details-box { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #4361ee; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h1>📋 BarangayHub Document Request</h1>
                            </div>
                            <div class='content'>
                                <h2>Request Approved</h2>
                                <p>Dear <strong>$full_name</strong>,</p>
                                <p>We are pleased to inform you that your document request has been <span class='status-approved'>APPROVED</span>.</p>
                                
                                <div class='details-box'>
                                    <p><strong>Request Details:</strong></p>
                                    <ul>
                                        <li><strong>Tracking Number:</strong> $request_code</li>
                                        <li><strong>Document Type:</strong> $document_type</li>
                                        <li><strong>Fee Amount:</strong> ₱$fee_amount</li>
                                        <li><strong>Base Fee:</strong> ₱$base_fee</li>
                                        <li><strong>Status:</strong> Approved</li>
                                        <li><strong>Date Processed:</strong> " . date('F j, Y \a\t g:i A') . "</li>
                                    </ul>
                                </div>
                                
                                <p>Your document is now being processed and will be ready for pickup soon. You will receive another notification when it's completed.</p>
                                <p>Thank you for using BarangayHub services.</p>
                            </div>
                            <div class='footer'>
                                <p>This is an automated message from BarangayHub System.</p>
                                <p>Please do not reply to this email.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                ",
                'altBody' => "Dear $full_name,\n\nYour document request ($request_code - $document_type) has been APPROVED.\n\nTracking Number: $request_code\nDocument Type: $document_type\nFee Amount: ₱$fee_amount\nBase Fee: ₱$base_fee\n\nYour document is now being processed and will be ready for pickup soon.\n\nThank you for using BarangayHub services.\n\nThis is an automated message."
            ],
            'rejected' => [
                'subject' => "Your Document Request has been Rejected - $request_code",
                'body' => "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background: #e30000; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                            .content { background: #f9f9f9; padding: 20px; }
                            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
                            .status-rejected { color: #e30000; font-weight: bold; }
                            .reason-box { background: #ffebee; padding: 15px; border-left: 4px solid #e30000; margin: 15px 0; }
                            .details-box { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #e30000; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h1>📋 BarangayHub Document Request</h1>
                            </div>
                            <div class='content'>
                                <h2>Request Rejected</h2>
                                <p>Dear <strong>$full_name</strong>,</p>
                                <p>We regret to inform you that your document request has been <span class='status-rejected'>REJECTED</span>.</p>
                                
                                <div class='details-box'>
                                    <p><strong>Request Details:</strong></p>
                                    <ul>
                                        <li><strong>Tracking Number:</strong> $request_code</li>
                                        <li><strong>Document Type:</strong> $document_type</li>
                                        <li><strong>Fee Amount:</strong> ₱$fee_amount</li>
                                        <li><strong>Base Fee:</strong> ₱$base_fee</li>
                                        <li><strong>Status:</strong> Rejected</li>
                                  
                                    </ul>
                                </div>
                                
                                <div class='reason-box'>
                                    <strong>Reason for Rejection:</strong><br>
                                    " . nl2br(htmlspecialchars($reason)) . "
                                </div>
                                <p>If you believe this is an error or would like to clarify the requirements, please visit the barangay office.</p>
                            </div>
                            <div class='footer'>
                                <p>This is an automated message from BarangayHub System.</p>
                                <p>Please do not reply to this email.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                ",
                'altBody' => "Dear $full_name,\n\nYour document request ($request_code - $document_type) has been REJECTED.\n\nTracking Number: $request_code\nDocument Type: $document_type\nFee Amount: ₱$fee_amount\nBase Fee: ₱$base_fee\n\nReason for rejection: $reason\n\nIf you believe this is an error or would like to clarify the requirements, please visit the barangay office.\n\nThis is an automated message."
            ],
            'completed' => [
                'subject' => "Your Document is Ready for Pickup - $request_code",
                'body' => "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background: #0c9b49; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                            .content { background: #f9f9f9; padding: 20px; }
                            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
                            .status-completed { color: #0c9b49; font-weight: bold; }
                            .instructions { background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 15px 0; }
                            .details-box { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #0c9b49; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h1>📋 BarangayHub Document Request</h1>
                            </div>
                            <div class='content'>
                                <h2>Document Ready for Pickup</h2>
                                <p>Dear <strong>$full_name</strong>,</p>
                                <p>We are pleased to inform you that your requested document has been <span class='status-completed'>COMPLETED</span> and is ready for pickup.</p>
                                
                                <div class='details-box'>
                                    <p><strong>Request Details:</strong></p>
                                    <ul>
                                        <li><strong>Tracking Number:</strong> $request_code</li>
                                        <li><strong>Document Type:</strong> $document_type</li>
                                        <li><strong>Fee Amount:</strong> ₱$fee_amount</li>
                                        <li><strong>Base Fee:</strong> ₱$base_fee</li>
                                        <li><strong>Status:</strong> Completed</li>
                     
                                    </ul>
                                </div>
                                
                                <div class='instructions'>
                                    <strong>Pickup Instructions:</strong><br>
                                    • Please bring a valid ID for verification<br>
                                    • Visit the barangay office during business hours (8:00 AM - 5:00 PM, Monday to Friday)<br>
                                    • Present this notification or your tracking number ($request_code)<br>
                                    • Documents must be claimed within 30 days
                                </div>
                                <p>Thank you for using BarangayHub services.</p>
                            </div>
                            <div class='footer'>
                                <p>This is an automated message from BarangayHub System.</p>
                                <p>Please do not reply to this email.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                ",
                'altBody' => "Dear $full_name,\n\nYour document request ($request_code - $document_type) has been COMPLETED and is ready for pickup.\n\nTracking Number: $request_code\nDocument Type: $document_type\nFee Amount: ₱$fee_amount\nBase Fee: ₱$base_fee\n\nPlease bring a valid ID and visit the barangay office during business hours (8:00 AM - 5:00 PM, Monday to Friday). Present your tracking number: $request_code\n\nDocuments must be claimed within 30 days.\n\nThank you for using BarangayHub services.\n\nThis is an automated message."
            ]
        ];
        
        return $configs[$status] ?? [
            'subject' => "Document Request Status Update - $request_code",
            'body' => "Status updated to $status",
            'altBody' => "Status updated to $status"
        ];
    }
    
    private function logNotification($request_id, $email, $subject, $message, $status) {
        try {
            $query = "INSERT INTO notification SET 
                     request_id = :request_id,
                     recipient_email = :email,
                     subject = :subject,
                     message = :message,
                     status = :status,
                     sent_time = NOW()";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':request_id', $request_id);
            $stmt->bindValue(':email', $email);
            $stmt->bindValue(':subject', $subject);
            $stmt->bindValue(':message', trim($message));
            $stmt->bindValue(':status', $status);
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log("Notification logging error: " . $e->getMessage());
            return false;
        }
    }
}
?>
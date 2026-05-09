<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Settings;

class DocumentGenerator {
    private $db;
    private $templatePath;
    private $outputPath;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        
        // Set paths
        $this->templatePath = __DIR__ . '/../documents/templates/';
        $this->outputPath = __DIR__ . '/../documents/generated/';
        
        // Create directories if they don't exist
        if (!is_dir($this->templatePath)) {
            mkdir($this->templatePath, 0755, true);
        }
        if (!is_dir($this->outputPath)) {
            mkdir($this->outputPath, 0755, true);
        }
        
        // Set PHPWord settings
        Settings::setOutputEscapingEnabled(true);
    }

    public function checkPHPWordInstallation() {
        return [
            'phpword_available' => class_exists('PhpOffice\PhpWord\PhpWord'),
            'template_processor_available' => class_exists('PhpOffice\PhpWord\TemplateProcessor')
        ];
    }

    public function verifyTemplates() {
        $templates = [
            'Barangay Clearance' => 'BARANGAY CLEARANCE.docx',
            'Certificate of Residency' => 'BARANGAY RESIDENCY.docx', 
            'Certificate of Indigency' => 'BARANGAY INDIGENCY.docx'
        ];
        
        $status = [];
        foreach ($templates as $name => $filename) {
            $fullPath = $this->templatePath . $filename;
            $status[$name] = [
                'exists' => file_exists($fullPath),
                'path' => $fullPath
            ];
        }
        return $status;
    }

    public function getResidentInfo($residentId) {
        try {
            $query = "SELECT r.*, TIMESTAMPDIFF(YEAR, r.birthdate, CURDATE()) as age 
                      FROM resident r 
                      WHERE r.resident_id = :resident_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':resident_id', $residentId);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting resident info: " . $e->getMessage());
            return null;
        }
    }

    public function searchResidents($searchTerm) {
        try {
            $query = "SELECT resident_id, resident_code, full_name, address, contact_number 
                      FROM resident 
                      WHERE full_name LIKE :search 
                         OR resident_code LIKE :search 
                         OR contact_number LIKE :search 
                      LIMIT 10";
            
            $stmt = $this->db->prepare($query);
            $searchTerm = '%' . $searchTerm . '%';
            $stmt->bindValue(':search', $searchTerm);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Search error: " . $e->getMessage());
            return [];
        }
    }

    public function generateDocument($residentId, $documentType, $requestId = null) {
        try {
            // Get resident information
            $resident = $this->getResidentInfo($residentId);
            if (!$resident) {
                throw new Exception("Resident not found with ID: " . $residentId);
            }

            // Map document types to template files
            $templateMap = [
                'Barangay Clearance' => 'BARANGAY CLEARANCE.docx',
                'Certificate of Residency' => 'BARANGAY RESIDENCY.docx',
                'Certificate of Indigency' => 'BARANGAY INDIGENCY.docx'
            ];

            if (!isset($templateMap[$documentType])) {
                throw new Exception("Invalid document type: " . $documentType);
            }

            $templateFile = $templateMap[$documentType];
            $templatePath = $this->templatePath . $templateFile;

            if (!file_exists($templatePath)) {
                throw new Exception("Template file not found: " . $templateFile);
            }

            // Create output filename
            $timestamp = date('Ymd_His');
            $outputFilename = strtoupper(str_replace(' ', '_', $documentType)) . '_' . 
                            $resident['resident_code'] . '_' . $timestamp . '.docx';
            $outputPath = $this->outputPath . $outputFilename;

            // Prepare template variables
            $variables = $this->prepareTemplateVariables($resident, $documentType);

            // Generate document using PHPWord TemplateProcessor
            $templateProcessor = new TemplateProcessor($templatePath);
            
            // Set all variables - try different formats
            foreach ($variables as $key => $value) {
                // Try different variable formats that might be in your template
                $templateProcessor->setValue('${' . $key . '}', $value);
                $templateProcessor->setValue('$' . $key, $value);
                $templateProcessor->setValue($key, $value);
            }

            // Save the generated document
            $templateProcessor->saveAs($outputPath);

            // Log the document generation in database
            $documentId = $this->logDocumentGeneration($residentId, $documentType, $outputFilename, $outputPath, $requestId);

            return [
                'success' => true,
                'document_id' => $documentId,
                'file_name' => $outputFilename,
                'file_path' => $outputPath,
                'download_url' => '../documents/generated/' . $outputFilename,
                'resident_name' => $resident['full_name'],
                'document_type' => $documentType,
                'generated_at' => date('Y-m-d H:i:s'),
                'variables_replaced' => true
            ];

        } catch (Exception $e) {
            error_log("Document generation error: " . $e->getMessage());
            throw new Exception("Failed to generate document: " . $e->getMessage());
        }
    }

    private function prepareTemplateVariables($resident, $documentType) {
        // Calculate age properly
        $age = 'N/A';
        if (!empty($resident['birthdate']) && $resident['birthdate'] != '0000-00-00') {
            try {
                $birthdate = new DateTime($resident['birthdate']);
                $today = new DateTime();
                $age = $birthdate->diff($today)->y;
            } catch (Exception $e) {
                error_log("Error calculating age: " . $e->getMessage());
            }
        }
        
        $variables = [
            'full_name' => $resident['full_name'] ?? '',
            'age' => $age,
            'civil_status' => $resident['civil_status'] ?? 'N/A',
            'address' => $resident['address'] ?? 'N/A',
            'year_of_residency' => $resident['year_of_residency'] ?? date('Y'),
            'date' => date('F j, Y'),
            'current_date' => date('F j, Y'),
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Add gender-specific titles
        $gender = strtolower($resident['gender'] ?? '');
        if ($gender === 'male') {
            $variables['title'] = 'Mr.';
        } elseif ($gender === 'female') {
            $variables['title'] = ($resident['civil_status'] ?? '') === 'married' ? 'Mrs.' : 'Ms.';
        } else {
            $variables['title'] = '';
        }

        return $variables;
    }

    private function logDocumentGeneration($residentId, $documentType, $fileName, $filePath, $requestId = null) {
        try {
            // First, create a request record if not provided
            if (!$requestId) {
                $requestCode = 'DOC-' . date('Ymd-His') . '-' . $residentId;
                
                // Get document type ID
                $typeQuery = "SELECT type_id FROM document_type WHERE name = :doc_type";
                $typeStmt = $this->db->prepare($typeQuery);
                $typeStmt->bindValue(':doc_type', $documentType);
                $typeStmt->execute();
                $docType = $typeStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($docType) {
                    $requestQuery = "INSERT INTO request 
                                    (request_code, resident_id, document_type_id, purpose, status, fee_paid, request_date) 
                                    VALUES 
                                    (:request_code, :resident_id, :doc_type_id, :purpose, 'completed', 1, NOW())";
                    
                    $requestStmt = $this->db->prepare($requestQuery);
                    $requestStmt->bindValue(':request_code', $requestCode);
                    $requestStmt->bindValue(':resident_id', $residentId);
                    $requestStmt->bindValue(':doc_type_id', $docType['type_id']);
                    $requestStmt->bindValue(':purpose', 'Document generated by admin');
                    $requestStmt->execute();
                    
                    $requestId = $this->db->lastInsertId();
                }
            }

            // Insert document record
            $docQuery = "INSERT INTO document 
                        (request_id, file_name, file_path, generated_by, generation_date) 
                        VALUES 
                        (:request_id, :file_name, :file_path, :admin_id, NOW())";
            
            $docStmt = $this->db->prepare($docQuery);
            $docStmt->bindValue(':request_id', $requestId);
            $docStmt->bindValue(':file_name', $fileName);
            $docStmt->bindValue(':file_path', $filePath);
            $docStmt->bindValue(':admin_id', $_SESSION['admin_id'] ?? 1);
            $docStmt->execute();
            
            return $this->db->lastInsertId();

        } catch (Exception $e) {
            error_log("Error logging document: " . $e->getMessage());
            return null;
        }
    }

    public function getRecentDocuments($limit = 5) {
        try {
            $query = "SELECT d.*, r.full_name, dt.name as document_type 
                      FROM document d
                      LEFT JOIN request req ON d.request_id = req.request_id
                      LEFT JOIN resident r ON req.resident_id = r.resident_id
                      LEFT JOIN document_type dt ON req.document_type_id = dt.type_id
                      ORDER BY d.generation_date DESC 
                      LIMIT :limit";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting recent documents: " . $e->getMessage());
            return [];
        }
    }

    public function getStatistics() {
        try {
            // Total residents
            $residentQuery = "SELECT COUNT(*) as total FROM resident WHERE status = 'active'";
            $residentStmt = $this->db->query($residentQuery);
            $totalResidents = $residentStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Documents today
            $todayQuery = "SELECT COUNT(*) as today FROM document WHERE DATE(generation_date) = CURDATE()";
            $todayStmt = $this->db->query($todayQuery);
            $documentsToday = $todayStmt->fetch(PDO::FETCH_ASSOC)['today'];

            // Revenue this month
            $revenueQuery = "SELECT COALESCE(SUM(fee_amount), 0) as revenue 
                            FROM request 
                            WHERE fee_paid = 1 
                            AND MONTH(request_date) = MONTH(CURDATE()) 
                            AND YEAR(request_date) = YEAR(CURDATE())";
            $revenueStmt = $this->db->query($revenueQuery);
            $revenueMonth = $revenueStmt->fetch(PDO::FETCH_ASSOC)['revenue'];

            return [
                'total_residents' => $totalResidents,
                'documents_today' => $documentsToday,
                'revenue_month' => $revenueMonth
            ];

        } catch (Exception $e) {
            error_log("Error getting statistics: " . $e->getMessage());
            return [
                'total_residents' => 0,
                'documents_today' => 0,
                'revenue_month' => 0
            ];
        }
    }

public function getDocumentTypes() {
    try {
        $query = "SELECT * FROM document_type ORDER BY name";
        $stmt = $this->db->query($query);
        $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug: log what we're getting
        error_log("Document types from DB: " . print_r($types, true));
        
        return $types;
    } catch (Exception $e) {
        error_log("Error getting document types: " . $e->getMessage());
        return [];
    }
}
}
?>
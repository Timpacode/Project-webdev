<?php
// generate_document.php
// Usage examples:
//   generate_document.php?resident_id=1&type=clearance
//   generate_document.php?resident_id=5&type=residency
//   generate_document.php?resident_id=8&type=indigency
//
// Requires: composer require phpoffice/phpword

require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;

// -------------------- CONFIG --------------------
$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = 'your_password';
$dbName = 'barangayhub';

// Map friendly type -> template file
$TEMPLATES = [
    'clearance' => __DIR__ . '/templates/Barangay Clearance.docx',
    'residency' => __DIR__ . '/templates/BARANGAY RESIDENCY.docx',
    'indigency' => __DIR__ . '/templates/BARANGAY INDIGENCY.docx',
];

// Where to save generated files
$OUTPUT_DIR = __DIR__ . '/generated';
// ------------------------------------------------

// Helpers
function fail($msg, $httpCode = 400) {
    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_SLASHES);
    exit;
}

function ageFromBirthdate(?string $birthdate): ?int {
    if (!$birthdate) return null;
    $b = new DateTime($birthdate);
    $now = new DateTime('today');
    return (int)$b->diff($now)->y;
}

function ensureDir(string $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

// -------------------- INPUTS --------------------
$residentId = isset($_GET['resident_id']) ? (int)$_GET['resident_id'] : (isset($_POST['resident_id']) ? (int)$_POST['resident_id'] : 0);
$typeRaw    = isset($_GET['type']) ? $_GET['type'] : (isset($_POST['type']) ? $_POST['type'] : '');

$type = strtolower(trim($typeRaw));
if (!in_array($type, ['clearance','residency','indigency'], true)) {
    fail("Invalid 'type'. Use one of: clearance | residency | indigency");
}

if ($residentId <= 0) {
    fail("Missing or invalid 'resident_id'.");
}

$templatePath = $TEMPLATES[$type] ?? null;
if (!$templatePath || !file_exists($templatePath)) {
    fail("Template for type '{$type}' not found on server.");
}

// -------------------- DB --------------------
$mysqli = @new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($mysqli->connect_errno) {
    fail("DB connection failed: " . $mysqli->connect_error, 500);
}

// Get resident info
$sql = "SELECT resident_id, resident_code, full_name, email, birthdate, gender, civil_status,
               address, contact_number, registration_date
        FROM resident
        WHERE resident_id = ?";
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    fail("DB error: " . $mysqli->error, 500);
}
$stmt->bind_param("i", $residentId);
$stmt->execute();
$res = $stmt->get_result();
$resident = $res->fetch_assoc();
$stmt->close();

if (!$resident) {
    fail("Resident not found (resident_id={$residentId}).", 404);
}

// Compute fields for placeholders
$age = ageFromBirthdate($resident['birthdate']);
$ageStr = $age !== null ? (string)$age : 'N/A';

// For Residency template, your doc uses ${year_of_residency}.
// We’ll derive it from registration_date (fallback to current year if null).
$yearOfResidency = 'N/A';
if (!empty($resident['registration_date'])) {
    $yearOfResidency = (new DateTime($resident['registration_date']))->format('Y');
} else {
    $yearOfResidency = (new DateTime())->format('Y');
}

$dateNow = (new DateTime())->format('F d, Y');

// -------------------- MERGE --------------------
try {
    $tpl = new TemplateProcessor($templatePath);

    // Common placeholders across your templates
    $tpl->setValue('full_name', $resident['full_name'] ?? '');
    $tpl->setValue('age', $ageStr);
    $tpl->setValue('civil_status', $resident['civil_status'] ?? '');
    $tpl->setValue('address', $resident['address'] ?? '');
    $tpl->setValue('date', $dateNow);

    // Residency-specific placeholder present in your template
    if ($type === 'residency') {
        $tpl->setValue('year_of_residency', $yearOfResidency);
    }

    ensureDir($OUTPUT_DIR);

    // Build output filename
    $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $resident['full_name'] ?? 'Resident');
    $outName  = sprintf('%s_%s_%s.docx',
        ucfirst($type),
        $safeName,
        (new DateTime())->format('Ymd_His')
    );
    $outPath  = $OUTPUT_DIR . '/' . $outName;

    $tpl->saveAs($outPath);

    // Optional: you can also insert a record to your 'document' table here
    // if you know the request_id and admin generating it.
    // Example (uncomment and set $requestId, $adminId properly):
    /*
    $requestId = 0; // set if you have one
    $adminId   = 1; // current admin ID
    if ($requestId > 0) {
        $size = file_exists($outPath) ? filesize($outPath) : null;
        $stmt = $mysqli->prepare("INSERT INTO document (request_id, file_name, file_path, file_size, generated_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issii", $requestId, $outName, $outPath, $size, $adminId);
        $stmt->execute();
        $stmt->close();
    }
    */

    header('Content-Type: application/json');
    echo json_encode([
        'ok' => true,
        'message' => 'Document generated.',
        'type' => $type,
        'resident_id' => $residentId,
        'output_file' => $outPath
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    fail("Template processing error: " . $e->getMessage(), 500);
}

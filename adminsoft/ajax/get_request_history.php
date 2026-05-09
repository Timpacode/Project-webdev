<?php
// Return only approved/completed/rejected requests (optionally filtered by one of those statuses)
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../config/database.php'; // must define $conn (mysqli)

    // Whitelist status
    $allowed = ['approved','completed','rejected','all'];
    $status  = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : 'all';
    if (!in_array($status, $allowed, true)) {
        $status = 'all';
    }

    // Base SQL - Exclude DOC-type requests
    $sql = "
        SELECT 
            r.request_id,
            r.request_code,
            r.resident_name,
            r.resident_email,
            r.resident_contact,
            r.resident_address,
            dt.name AS document_type,
            r.purpose,
            COALESCE(r.specific_purpose, '') AS specific_purpose,
            r.urgency_level,
            r.status,
            r.request_date
        FROM request r
        INNER JOIN document_type dt ON r.document_type_id = dt.type_id
        WHERE r.status IN ('approved','completed','rejected')
        AND r.request_code NOT LIKE 'DOC-%'  -- Exclude DOC-type requests
    ";

    $params = [];
    $types  = '';

    if ($status !== 'all') {
        $sql .= " AND r.status = ? ";
        $params[] = $status;
        $types   .= 's';
    }

    $sql .= " ORDER BY r.request_date DESC ";

    // Prepared statement
    if ($stmt = $conn->prepare($sql)) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();

        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = [
                'request_id'     => (int)$row['request_id'],
                'request_code'   => $row['request_code'],
                'resident_name'  => $row['resident_name'],
                'document_type'  => $row['document_type'],
                'purpose'        => trim($row['specific_purpose']) !== '' ? $row['purpose'] . ' — ' . $row['specific_purpose'] : $row['purpose'],
                'status'         => $row['status'],
                'request_date'   => $row['request_date'],
            ];
        }
        $stmt->close();

        echo json_encode(['ok' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        echo json_encode(['ok' => false, 'error' => 'Failed to prepare query.']);
        exit;
    }
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}
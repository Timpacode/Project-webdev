<?php
// api/get_dashboard_stats.php
// Returns JSON stats for dashboard widgets.
//
// Requirements from user:
// - Count ACTIVE residents only.
// - Documents issued = all requests with status 'completed'.
// - Pie graph: only the 3 document types; count requests with status IN ('approved','completed').
// - Pending KPI: count all current 'pending' requests.
// - Below the graph: list ONLY the 3 most recent pending requests.
// - No "Total Requests" slice/label in the pie output.
// - Do not add new files; use existing config includes if present.

header('Content-Type: application/json');

$response = [
  'success' => false,
  'message' => 'Unknown error',
  'kpis' => (object)[],
  'weekly' => (object)[],
  'monthly' => (object)[],
  'recent' => [],
  'pending_requests' => []
];

try {
  $root = __DIR__ . '/..';
  // Try to include auth (optional) and DB
  @require_once $root . '/config/auth.php';
  @require_once $root . '/config/database.php';

  // Detect PDO or MySQLi from included database.php; otherwise construct PDO using constants.
  $pdo = null;
  if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
    $pdo = $GLOBALS['pdo'];
  } elseif (function_exists('getPDO')) {
    $pdo = getPDO();
  } elseif (function_exists('getDb')) {
    $pdo = getDb(); // some projects use getDb()
  } elseif (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
    $pwd = defined('DB_PASS') ? DB_PASS : (defined('DB_PASSWORD') ? DB_PASSWORD : '');
    $pdo = new PDO($dsn, DB_USER, $pwd, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
  }

  // If no PDO, try mysqli ($conn) and wrap helpers.
  $useMysqli = false;
  if (!$pdo) {
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
      $useMysqli = true;
      $conn = $GLOBALS['conn'];
    } elseif (function_exists('getMysqli')) {
      $conn = getMysqli();
      if ($conn instanceof mysqli) $useMysqli = true;
    } elseif (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
      $pwd = defined('DB_PASS') ? DB_PASS : (defined('DB_PASSWORD') ? DB_PASSWORD : '');
      $conn = new mysqli(DB_HOST, DB_USER, $pwd, DB_NAME);
      if ($conn->connect_error) { throw new Exception('MySQLi connect error: ' . $conn->connect_error); }
      $useMysqli = true;
    } else {
      throw new Exception('Database connection not found.');
    }
  }

  // Helper to run a scalar SELECT COUNT(*) query
  $scalar = function($sql) use ($pdo, $useMysqli, $conn) {
    if ($pdo) {
      $stmt = $pdo->query($sql);
      return (int)$stmt->fetchColumn();
    } else {
      $res = $conn->query($sql);
      if (!$res) return 0;
      $row = $res->fetch_row();
      return (int)$row[0];
    }
  };

  // ===== KPIs =====
  $total_residents = $scalar("SELECT COUNT(*) FROM resident WHERE STATUS='active'");
  $documents_issued = $scalar("SELECT COUNT(*) FROM request WHERE status='completed'");
  $pending_count = $scalar("SELECT COUNT(*) FROM request WHERE status='pending'");

  $response['kpis'] = [
    'total_residents' => $total_residents,
    'documents_issued' => $documents_issued,
    'pending_requests' => $pending_count
  ];

  // ===== Weekly Bar (Requests per day, current week) =====
  // We'll compute counts for the current calendar week (Monday-Sunday).
  if ($pdo) {
    $stmt = $pdo->query("
      WITH days AS (
        SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6
      )
      SELECT
        DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL (WEEKDAY(CURDATE())) DAY) + INTERVAL d DAY, '%a') AS label,
        (
          SELECT COUNT(*)
          FROM request r
          WHERE DATE(r.request_date) = DATE_SUB(CURDATE(), INTERVAL (WEEKDAY(CURDATE())) DAY) + INTERVAL d DAY
        ) AS cnt
      FROM days
      ORDER BY d;
    ");
    $weekly = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } else {
    // MySQLi fallback (two queries to avoid CTE compatibility issues)
    $labels = [];
    $data = [];
    // Compute Monday of current week
    $res = $conn->query("SELECT DATE_SUB(CURDATE(), INTERVAL (WEEKDAY(CURDATE())) DAY)");
    $row = $res->fetch_row(); $monday = $row[0];
    for ($i=0; $i<7; $i++) {
      $res2 = $conn->query("SELECT DATE_FORMAT(DATE_ADD('$monday', INTERVAL $i DAY), '%a')");
      $label = $res2->fetch_row()[0];
      $res3 = $conn->query("SELECT COUNT(*) FROM request WHERE DATE(request_date) = DATE_ADD('$monday', INTERVAL $i DAY)");
      $cnt = (int)$res3->fetch_row()[0];
      $labels[] = $label;
      $data[] = $cnt;
    }
    $weekly = [];
    for ($i=0; $i<7; $i++) $weekly[] = ['label'=>$labels[$i], 'cnt'=>$data[$i]];
  }

  $response['weekly'] = [
    'labels' => array_map(fn($r) => $r['label'], $weekly),
    'data'   => array_map(fn($r) => (int)$r['cnt'], $weekly)
  ];

  // ===== Monthly Pie (by the 3 document types, statuses approved+completed only) =====
  // We rely on document_type.type_id 1..3 existing.
  $pieSql = "
    SELECT dt.name AS label, COALESCE(COUNT(r.request_id),0) AS cnt
    FROM document_type dt
    LEFT JOIN request r
      ON r.document_type_id = dt.type_id
      AND r.status IN ('approved','completed')
    WHERE dt.type_id IN (1,2,3)
    GROUP BY dt.type_id, dt.name
    ORDER BY dt.type_id ASC
  ";
  if ($pdo) {
    $stmt = $pdo->query($pieSql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $res = $conn->query($pieSql);
    $rows = [];
    while ($row = $res->fetch_assoc()) { $rows[] = $row; }
  }
  $response['monthly'] = [
    'labels' => array_map(fn($r) => $r['label'], $rows),
    'data'   => array_map(fn($r) => (int)$r['cnt'], $rows)
  ];

  // ===== Recent Requests (last 5 overall, newest first) =====
  $recentSql = "
    SELECT r.request_code, r.resident_name, dt.name AS document_type, r.status,
           DATE_FORMAT(r.request_date, '%Y-%m-%d %H:%i') AS d
    FROM request r
    JOIN document_type dt ON dt.type_id = r.document_type_id
    ORDER BY r.request_date DESC
    LIMIT 5
  ";
  if ($pdo) {
    $stmt = $pdo->query($recentSql);
    $response['recent'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $res = $conn->query($recentSql);
    $tmp = [];
    while ($row = $res->fetch_assoc()) { $tmp[] = $row; }
    $response['recent'] = $tmp;
  }

  // ===== Pending Requests (ONLY 3 most recent pending) =====
  $pendSql = "
    SELECT r.request_code, r.resident_name, dt.name AS document_type, r.urgency_level,
           DATE_FORMAT(r.request_date, '%Y-%m-%d %H:%i') AS request_date
    FROM request r
    JOIN document_type dt ON dt.type_id = r.document_type_id
    WHERE r.status='pending'
    ORDER BY r.request_date DESC
    LIMIT 3
  ";
  if ($pdo) {
    $stmt = $pdo->query($pendSql);
    $response['pending_requests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $res = $conn->query($pendSql);
    $tmp = [];
    while ($row = $res->fetch_assoc()) { $tmp[] = $row; }
    $response['pending_requests'] = $tmp;
  }

  $response['success'] = true;
  $response['message'] = 'OK';
  echo json_encode($response);
} catch (Throwable $e) {
  $response['success'] = false;
  $response['message'] = $e->getMessage();
  echo json_encode($response);
}

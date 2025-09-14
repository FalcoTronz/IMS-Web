<?php
// php/get-audit-log.php
header("Content-Type: application/json");
session_start();

// Staff-only guard (adjust if you use a different role key)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
  http_response_code(403);
  echo json_encode(["error" => "Forbidden"]);
  exit;
}

// DB
require_once __DIR__ . '/db.php';
$conn = db();
if (!$conn) {
  http_response_code(500);
  echo json_encode(["error" => "Database connection failed"]);
  exit;
}

// ---- Inputs ----
// from/to: YYYY-MM-DD (UTC); default: last 30 days
// page: 1-based; size: default 50, max 200
$from = $_GET['from'] ?? null;
$to   = $_GET['to']   ?? null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$size = isset($_GET['size']) ? (int)$_GET['size'] : 50;

if ($page < 1) $page = 1;
if ($size < 1) $size = 50;
if ($size > 200) $size = 200;

$offset = ($page - 1) * $size;

// Validate date strings (YYYY-MM-DD)
function valid_date_ymd($s) {
  if (!$s) return false;
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return false;
  $parts = explode('-', $s);
  return checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
}

// Defaults to last 30 days if not provided
if (!valid_date_ymd($from) && !valid_date_ymd($to)) {
  $from = (new DateTime('now', new DateTimeZone('UTC')))->modify('-30 days')->format('Y-m-d');
  $to   = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d');
} else {
  if (!valid_date_ymd($from)) $from = null;
  if (!valid_date_ymd($to))   $to   = null;
}

// Build WHERE
$where = [];
$params = [];
$i = 1;

if ($from) {
  $where[] = "timestamp >= $".$i++;
  $params[] = $from . " 00:00:00+00";
}
if ($to) {
  $where[] = "timestamp <= $".$i++;
  $params[] = $to . " 23:59:59.999+00";
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// Query (chronological)
$query = "
  SELECT
    id,
    timestamp,
    action_type,
    user_id, user_name, user_email, user_role,
    item_id, item_name, item_code, item_category,
    borrow_date, return_date,
    prev_hash, curr_hash
  FROM audit_log
  $whereSql
  ORDER BY timestamp ASC, id ASC
  LIMIT $size OFFSET $offset
";

$result = pg_query_params($conn, $query, $params);
if (!$result) {
  http_response_code(500);
  echo json_encode(["error" => "Failed to fetch audit log"]);
  exit;
}

$rows = [];
while ($row = pg_fetch_assoc($result)) {
  // Format to DD/MM/YYYY (empty if null)
  foreach (['timestamp','borrow_date','return_date'] as $df) {
    if (!empty($row[$df])) {
      $row[$df] = date("d/m/Y", strtotime($row[$df]));
    } else {
      $row[$df] = "";
    }
  }
  $rows[] = $row;
}

echo json_encode($rows);
pg_close($conn);

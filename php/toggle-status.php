<?php
header("Content-Type: application/json");

// Connect to PostgreSQL
require_once __DIR__ . '/db.php';
$conn = db();


if (!$conn) {
  http_response_code(500);
  echo json_encode(["success" => false, "error" => "Database connection failed."]);
  exit;
}

// Get data
$id     = $_POST['id'] ?? '';
$status = $_POST['status'] ?? '';
$role   = $_POST['role'] ?? '';

if (!$id || !$status) {
  http_response_code(400);
  echo json_encode(["success" => false, "error" => "Missing user ID or status."]);
  exit;
}

// Determine new status
if ($status === 'pending') {
  $newStatus = 'approved';
} elseif ($status === 'approved') {
  $newStatus = 'suspended';
} elseif ($status === 'suspended') {
  $newStatus = 'approved';
} else {
  http_response_code(400);
  echo json_encode(["success" => false, "error" => "Invalid current status."]);
  exit;
}

// Build update query
if ($status === 'pending') {
  // Also update role during approval
  $query = "UPDATE users SET status = $1, role = $2 WHERE id = $3";
  $params = [$newStatus, $role, $id];
} else {
  $query = "UPDATE users SET status = $1 WHERE id = $2";
  $params = [$newStatus, $id];
}

$result = pg_query_params($conn, $query, $params);

if ($result) {
  echo json_encode(["success" => true]);
} else {
  http_response_code(500);
  echo json_encode(["success" => false, "error" => "Failed to update user."]);
}

pg_close($conn);
?>


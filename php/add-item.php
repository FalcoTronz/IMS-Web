<?php
session_start();
header("Content-Type: application/json");

// Connect to PostgreSQL 
require_once __DIR__ . '/db.php';
$conn = db();

if (!$conn) {
  http_response_code(500);
  echo json_encode(["success" => false, "error" => "Database connection failed"]);
  exit;
}

// Gather POST data (same field names you used)
$name      = trim($_POST['name'] ?? '');
$details   = trim($_POST['details'] ?? '');
$category  = trim($_POST['category'] ?? '');
$quantity  = trim($_POST['quantity'] ?? '');
$item_code = trim($_POST['item_code'] ?? '');
$year      = trim($_POST['year'] ?? '');
$location  = trim($_POST['location'] ?? '');
$status    = isset($_POST['status']) ? trim($_POST['status']) : 'Available'; // default

// Validation (kept from your version)
if (
  $name === '' || $details === '' || $category === '' || $quantity === '' ||
  $item_code === '' || $year === '' || $location === ''
) {
  http_response_code(400);
  echo json_encode(["success" => false, "error" => "Missing required fields"]);
  pg_close($conn);
  exit;
}

if (!is_numeric($quantity) || (int)$quantity < 1 || (int)$quantity > 9999) {
  http_response_code(400);
  echo json_encode(["success" => false, "error" => "Invalid quantity"]);
  pg_close($conn);
  exit;
}

if (!is_numeric($year) || (int)$year < 1000 || (int)$year > 2100) {
  http_response_code(400);
  echo json_encode(["success" => false, "error" => "Invalid year"]);
  pg_close($conn);
  exit;
}

if (strlen($item_code) > 30 || strlen($name) > 255 || strlen($details) > 255 || strlen($category) > 255 || strlen($location) > 100) {
  http_response_code(400);
  echo json_encode(["success" => false, "error" => "Field length exceeded"]);
  pg_close($conn);
  exit;
}

// Insert and RETURN the new row
$sql = "INSERT INTO items (name, details, category, quantity, item_code, year, location, status)
        VALUES ($1,$2,$3,$4,$5,$6,$7,$8)
        RETURNING *";
$params = [$name, $details, $category, (int)$quantity, $item_code, (int)$year, $location, $status];

$result = pg_query_params($conn, $sql, $params);
if (!$result) {
  http_response_code(500);
  echo json_encode(["success" => false, "error" => "Insert failed: " . pg_last_error($conn)]);
  pg_close($conn);
  exit;
}

$item = pg_fetch_assoc($result);
echo json_encode(["success" => true, "item" => $item]);
pg_close($conn);


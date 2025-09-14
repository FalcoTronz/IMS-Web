<?php
session_start();
header("Content-Type: application/json");

// Database connection
require_once __DIR__ . '/db.php';
$conn = db();


if (!$conn) {
  http_response_code(500);
  echo json_encode(["success" => false, "error" => "Database connection failed."]);
  exit;
}

// Get data from POST
$isbn = $_POST['isbn'] ?? '';
$memberId = $_SESSION['user_id'] ?? null;

if (!$isbn || !$memberId) {
  http_response_code(400);
  echo json_encode(["success" => false, "error" => "Missing ISBN or user session."]);
  exit;
}

// Check if item exists and is available
$itemResult = pg_query_params($conn, "SELECT id, quantity, status FROM items WHERE item_code = $1 LIMIT 1", [$isbn]);
if (!$itemResult || pg_num_rows($itemResult) !== 1) {
  echo json_encode(["success" => false, "error" => "Item not found."]);
  exit;
}

$item = pg_fetch_assoc($itemResult);
$itemId = $item['id'];
$quantity = (int)$item['quantity'];
$status = strtolower($item['status']);

if ($quantity <= 0 || $status !== 'available') {
  echo json_encode(["success" => false, "error" => "Item is not available."]);
  exit;
}

// Check if member already requested or borrowed this item
$existsResult = pg_query_params($conn,
  "SELECT 1 FROM borrowings WHERE user_id = $1 AND item_id = $2 AND status IN ('pending', 'borrowed')",
  [$memberId, $itemId]
);

if (pg_num_rows($existsResult) > 0) {
  echo json_encode(["success" => false, "error" => "You already requested or borrowed this item."]);
  exit;
}

// Insert borrow request
$insertResult = pg_query_params($conn,
  "INSERT INTO borrowings (user_id, item_id, borrow_date, status) VALUES ($1, $2, NOW(), 'pending')",
  [$memberId, $itemId]
);

if ($insertResult) {
  echo json_encode(["success" => true, "message" => "Borrow request submitted."]);
} else {
  echo json_encode(["success" => false, "error" => "Failed to submit request."]);
}
?>


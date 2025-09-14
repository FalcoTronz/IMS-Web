<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Connect to Supabase PostgreSQL
require_once __DIR__ . '/db.php';
$conn = db();


if (!$conn) {
  echo json_encode(["error" => "Failed to connect to database."]);
  exit;
}

$query = "
  SELECT 
    b.id,
    b.user_id AS member_id,
    u.full_name AS member_name,
    b.item_id,
    i.name AS book_title,
    i.details AS author,
    i.item_code AS isbn,
    b.borrow_date,
    b.approval_date,
    b.due_date,
    b.return_date,
    b.status
  FROM borrowings b
  JOIN users u ON b.user_id = u.id
  JOIN items i ON b.item_id = i.id
  ORDER BY b.borrow_date DESC
";

$result = pg_query($conn, $query);

if (!$result) {
  echo json_encode(["error" => "Failed to fetch borrowings."]);
  exit;
}

$borrowings = [];
while ($row = pg_fetch_assoc($result)) {
  $borrowings[] = $row;
}

header('Content-Type: application/json');
echo json_encode($borrowings);


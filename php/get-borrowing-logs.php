<?php
header("Content-Type: application/json");
session_start();

// Connect to Supabase PostgreSQL
require_once __DIR__ . '/db.php';
$conn = db();


if (!$conn) {
  http_response_code(500);
  echo json_encode(["error" => "Database connection failed"]);
  exit;
}

// Fetch the 5 most recent borrowings that are pending or borrowed
$query = "
  SELECT 
    b.id,
    b.user_id,
    u.full_name AS member_name,
    i.name AS book_title,
    i.details AS author,
    i.item_code AS isbn,
    b.borrow_date,
    b.due_date,
    b.return_date,
    b.approval_date,
    b.status
  FROM borrowings b
  JOIN users u ON b.user_id = u.id
  JOIN items i ON b.item_id = i.id
  WHERE b.status IN ('pending', 'borrowed')
  ORDER BY b.borrow_date ASC
  LIMIT 5
";

$result = pg_query($conn, $query);

$logs = [];

while ($row = pg_fetch_assoc($result)) {
  // Format dates to dd/mm/yyyy
  foreach (['borrow_date', 'due_date', 'return_date', 'approval_date'] as $dateField) {
    if (!empty($row[$dateField])) {
      $row[$dateField] = date("d/m/Y", strtotime($row[$dateField]));
    } else {
      $row[$dateField] = "";
    }
  }

  $logs[] = $row;
}

echo json_encode($logs);
pg_close($conn);


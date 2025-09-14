<?php
session_start();
header("Content-Type: application/json");

// Connect to PostgreSQL
require_once __DIR__ . '/db.php';
$conn = db();


if (!$conn) {
  http_response_code(500);
  echo json_encode(["success" => false, "error" => "Database connection failed."]);
  exit;
}

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
  http_response_code(401);
  echo json_encode(["success" => false, "error" => "User not logged in."]);
  exit;
}

// Get approved borrowings (status = borrowed or returned)
$query = "
  SELECT 
    i.name AS title,
    i.details AS author,
    i.item_code AS isbn,
    b.borrow_date,
    b.due_date,
    b.return_date
  FROM borrowings b
  JOIN items i ON b.item_id = i.id
  WHERE b.user_id = $1 AND b.status IN ('borrowed', 'returned')
  ORDER BY b.borrow_date DESC
";

$result = pg_query_params($conn, $query, [$userId]);

$history = [];

while ($row = pg_fetch_assoc($result)) {
  // Parse raw dates
  $borrowRaw = $row['borrow_date'];
  $dueRaw = $row['due_date'];
  $returnRaw = $row['return_date'];

  $borrowDate = $borrowRaw ? date("d/m/Y", strtotime($borrowRaw)) : '';
  $dueDate    = $dueRaw ? date("d/m/Y", strtotime($dueRaw)) : '';
  $returnDate = $returnRaw ? date("d/m/Y", strtotime($returnRaw)) : '';

  $now = time();
  $dueTimestamp = $dueRaw ? strtotime($dueRaw) : null;
  $returnTimestamp = $returnRaw ? strtotime($returnRaw) : null;

  // Determine status
  if (!$returnRaw) {
    if ($dueTimestamp && $dueTimestamp < $now) {
      $status = "Overdue";
    } else {
      $status = "Borrowed";
    }
  } else {
    if ($dueTimestamp && $returnTimestamp > $dueTimestamp) {
      $status = "Returned late";
    } else {
      $status = "Returned";
    }
  }

  $history[] = [
    "title"        => $row['title'],
    "author"       => $row['author'],
    "isbn"         => $row['isbn'],
    "borrow_date"  => $borrowDate,
    "due_date"     => $dueDate,
    "return_date"  => $returnDate ?: "",
    "status"       => $status
  ];
}

echo json_encode($history);
pg_close($conn);
?>


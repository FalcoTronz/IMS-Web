<?php
header("Content-Type: application/json");
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit_log.php';

$conn = db();
if (!$conn) {
  http_response_code(500);
  echo json_encode(["success" => false, "error" => "Database connection failed."]);
  exit;
}

$borrowId = $_POST['id'] ?? null;
if (!$borrowId || !ctype_digit((string)$borrowId)) {
  http_response_code(400);
  echo json_encode(["success" => false, "error" => "Invalid borrowing ID."]);
  exit;
}

// Begin transaction
if (!pg_query($conn, "BEGIN")) {
  http_response_code(500);
  echo json_encode(["success" => false, "error" => "Failed to start transaction."]);
  exit;
}

$success = false;

do {
  // Lock the borrowing row for update and get current status + item_id
  $q1 = "
    SELECT id, user_id, item_id, status
    FROM borrowings
    WHERE id = $1
    FOR UPDATE
  ";
  $r1 = pg_query_params($conn, $q1, [$borrowId]);
  if (!$r1 || pg_num_rows($r1) === 0) { $err = "Borrowing not found."; break; }

  $borrowing = pg_fetch_assoc($r1);
  $status = $borrowing['status'];
  $itemId = (int)$borrowing['item_id'];

  if ($status === 'pending') {
    // Approve: set status=borrowed, set approval_date + due_date, decrement item quantity if available
    // Lock item row
    $qi = "SELECT id, quantity FROM items WHERE id = $1 FOR UPDATE";
    $ri = pg_query_params($conn, $qi, [$itemId]);
    if (!$ri || pg_num_rows($ri) === 0) { $err = "Item not found."; break; }
    $item = pg_fetch_assoc($ri);
    $qty  = (int)$item['quantity'];

    if ($qty <= 0) { $err = "Item not available."; break; }

    $q2 = "
      UPDATE borrowings
         SET status = 'borrowed',
             approval_date = NOW(),
             due_date = NOW() + INTERVAL '14 days'
       WHERE id = $1
    ";
    if (!pg_query_params($conn, $q2, [$borrowId])) { $err = "Failed to approve borrowing."; break; }

    $q3 = "UPDATE items SET quantity = quantity - 1 WHERE id = $1";
    if (!pg_query_params($conn, $q3, [$itemId])) { $err = "Failed to update item quantity."; break; }

    // Write audit entry BEFORE commit
    if (!audit_log_borrow_approved($borrowId)) { $err = "Failed to write audit log."; break; }

  } elseif ($status === 'borrowed') {
    // Mark returned: set status=returned, set return_date, increment item quantity
    $q4 = "
      UPDATE borrowings
         SET status = 'returned',
             return_date = NOW()
       WHERE id = $1
    ";
    if (!pg_query_params($conn, $q4, [$borrowId])) { $err = "Failed to mark as returned."; break; }

    $q5 = "UPDATE items SET quantity = quantity + 1 WHERE id = $1";
    if (!pg_query_params($conn, $q5, [$itemId])) { $err = "Failed to restore item quantity."; break; }

    // Write audit entry BEFORE commit
    if (!audit_log_return_completed($borrowId)) { $err = "Failed to write audit log."; break; }

  } else {
    $err = "Unsupported status transition from '$status'.";
    break;
  }

  $success = true;
} while (false);

// Commit or rollback based on success
if ($success) {
  pg_query($conn, "COMMIT");
  echo json_encode(["success" => true]);
} else {
  pg_query($conn, "ROLLBACK");
  http_response_code(400);
  echo json_encode(["success" => false, "error" => $err ?? "Update failed."]);
}

pg_close($conn);

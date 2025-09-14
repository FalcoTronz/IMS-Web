<?php
// php/audit_log.php
// Reusable audit logger for BORROW_APPROVED and RETURN_COMPLETED
// Usage from another PHP file (after updating a borrowing):
//   require_once __DIR__ . '/audit_log.php';
//   audit_log_borrow_approved($borrowingId);  // or audit_log_return_completed($borrowingId)

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';

function audit_db() {
  static $conn = null;
  if ($conn) return $conn;
  $conn = db();
  if (!$conn) {
    error_log("audit_log: DB connection failed");
  }
  return $conn;
}

function audit_prev_hash($conn) {
  $sql = "SELECT curr_hash FROM audit_log ORDER BY timestamp DESC, id DESC LIMIT 1";
  $res = @pg_query($conn, $sql);
  if ($res && pg_num_rows($res) === 1) {
    return pg_fetch_result($res, 0, 0) ?: '';
  }
  return '';
}

function audit_hash($payloadJson, $prevHash) {
  // Optional salt from env for extra integrity (not secret-critical, but helpful)
  $salt = getenv('AUDIT_SALT') ?: 'lms_audit_salt_v1';
  return hash('sha256', $prevHash . '|' . $payloadJson . '|' . $salt);
}

function audit_fetch_context($conn, $borrowingId) {
  // NOTE: We keep "user_name" as an empty string if your users table has no name column.
  // Adjust later if you add a name field.
  $sql = "
    SELECT
      b.id               AS borrowing_id,
      b.user_id          AS user_id,
      b.item_id          AS item_id,
      b.approval_date    AS borrow_date,
      b.return_date      AS return_date,
      u.email            AS user_email,
      ''::text           AS user_name,      -- placeholder if no name field exists
      u.role             AS user_role,
      i.name             AS item_name,
      i.item_code        AS item_code,
      i.category         AS item_category
    FROM borrowings b
    JOIN users u ON u.id = b.user_id
    JOIN items i ON i.id = b.item_id
    WHERE b.id = $1
    LIMIT 1
  ";
  $res = @pg_query_params($conn, $sql, [$borrowingId]);
  if (!$res || pg_num_rows($res) === 0) return null;
  return pg_fetch_assoc($res);
}

function audit_insert_entry($actionType, $borrowingId) {
  $conn = audit_db();
  if (!$conn) return false;

  $ctx = audit_fetch_context($conn, $borrowingId);
  if (!$ctx) {
    error_log("audit_log: borrowing $borrowingId not found");
    return false;
  }

  // Build the canonical payload (stringify for hashing)
  $payload = [
    'action_type'   => $actionType,
    'user_id'       => (int)$ctx['user_id'],
    'user_name'     => (string)$ctx['user_name'],
    'user_email'    => (string)$ctx['user_email'],
    'user_role'     => (string)$ctx['user_role'],
    'item_id'       => (int)$ctx['item_id'],
    'item_name'     => (string)$ctx['item_name'],
    'item_code'     => (string)$ctx['item_code'],
    'item_category' => (string)$ctx['item_category'],
    'borrow_date'   => $ctx['borrow_date'],  // may be null
    'return_date'   => $ctx['return_date'],  // may be null
  ];
  $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

  $prevHash = audit_prev_hash($conn);
  $currHash = audit_hash($payloadJson, $prevHash);

  // Insert into audit_log
  $sql = "
    INSERT INTO audit_log (
      timestamp, action_type,
      user_id, user_name, user_email, user_role,
      item_id, item_name, item_code, item_category,
      borrow_date, return_date,
      prev_hash, curr_hash
    ) VALUES (
      NOW(), $1,
      $2, $3, $4, $5,
      $6, $7, $8, $9,
      $10, $11,
      $12, $13
    )
    RETURNING id
  ";
  $params = [
    $actionType,
    (int)$payload['user_id'],
    $payload['user_name'],
    $payload['user_email'],
    $payload['user_role'],
    (int)$payload['item_id'],
    $payload['item_name'],
    $payload['item_code'],
    $payload['item_category'],
    $payload['borrow_date'],
    $payload['return_date'],
    $prevHash,
    $currHash
  ];

  $res = @pg_query_params($conn, $sql, $params);
  if (!$res) {
    error_log("audit_log: insert failed for borrowing $borrowingId");
    return false;
  }

  return true;
}

function audit_log_borrow_approved($borrowingId) {
  return audit_insert_entry('BORROW_APPROVED', $borrowingId);
}

function audit_log_return_completed($borrowingId) {
  return audit_insert_entry('RETURN_COMPLETED', $borrowingId);
}

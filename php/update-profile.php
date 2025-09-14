<?php
session_start();

// Connect to your Supabase PostgreSQL
require_once __DIR__ . '/db.php';
$conn = db();


if (!$conn) {
  die("Connection failed.");
}

$userId   = $_SESSION['user_id'];
$email    = trim($_POST['email'] ?? '');
$phone    = trim($_POST['phone'] ?? '');
$address  = trim($_POST['address'] ?? '');
$password = trim($_POST['password'] ?? '');

// === Validation ===
if (!$email || !$phone || !$address) {
  die("All fields except password are required.");
}

// Check if email exists (but exclude current user)
$checkEmail = pg_query_params($conn, "SELECT id FROM users WHERE email = $1 AND id != $2", [$email, $userId]);
if (pg_num_rows($checkEmail) > 0) {
  die("Email already in use.");
}

// Check if phone exists (but exclude current user)
$checkPhone = pg_query_params($conn, "SELECT id FROM users WHERE phone = $1 AND id != $2", [$phone, $userId]);
if (pg_num_rows($checkPhone) > 0) {
  die("Phone number already in use.");
}

// If password is provided, hash and update it
if (!empty($password)) {
  // Optionally: validate password strength
  if (strlen($password) < 6) {
    die("Password must be at least 6 characters.");
  }

  $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
  $query = "UPDATE users SET email = $1, phone = $2, address = $3, password = $4 WHERE id = $5";
  $params = [$email, $phone, $address, $hashedPassword, $userId];
} else {
  // Update without changing password
  $query = "UPDATE users SET email = $1, phone = $2, address = $3 WHERE id = $4";
  $params = [$email, $phone, $address, $userId];
}

// Execute update
$result = pg_query_params($conn, $query, $params);

if ($result) {
  header("Location: ../member-dashboard.php?updated=1");
  exit;
} else {
  die("Update failed.");
}
?>


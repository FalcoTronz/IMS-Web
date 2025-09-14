<?php
header("Content-Type: application/json");

// Connect to Supabase PostgreSQL
require_once __DIR__ . '/db.php';
$conn = db();


if (!$conn) {
  http_response_code(500);
  echo json_encode(["error" => "Database connection failed."]);
  exit;
}

// Get POST data
$full_name = $_POST['full_name'] ?? '';
$email     = $_POST['email'] ?? '';
$password  = $_POST['password'] ?? '';
$job       = $_POST['job'] ?? '';
$address   = $_POST['address'] ?? '';
$phone     = $_POST['phone'] ?? '';
$reason    = $_POST['reason'] ?? '';

// Basic validation
if (!$full_name || !$email || !$password || !$job || !$address || !$phone || !$reason) {
  http_response_code(400);
  echo json_encode(["error" => "All fields are required."]);
  pg_close($conn);
  exit;
}

// Field-specific validation
if (strlen($full_name) > 255 || strlen($email) > 255 || strlen($job) > 255 || strlen($address) > 255 || strlen($phone) > 20 || strlen($reason) > 1000) {
  http_response_code(400);
  echo json_encode(["error" => "One or more fields exceed the maximum allowed length."]);
  pg_close($conn);
  exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(["error" => "Invalid email format."]);
  pg_close($conn);
  exit;
}

if (!preg_match('/^\+?[0-9]{7,15}$/', $phone)) {
  http_response_code(400);
  echo json_encode(["error" => "Invalid phone number format. Use digits only (7–15 characters)."]);
  pg_close($conn);
  exit;
}

if (strlen($password) < 6) {
  http_response_code(400);
  echo json_encode(["error" => "Password must be at least 6 characters."]);
  pg_close($conn);
  exit;
}

// Hash the password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Insert user
$result = @pg_query_params($conn, "
  INSERT INTO users (full_name, email, password, job, address, phone, reason, status, role)
  VALUES ($1, $2, $3, $4, $5, $6, $7, 'pending', 'pending')
", [
  $full_name, $email, $hashedPassword, $job, $address, $phone, $reason
]);

if ($result) {
  echo json_encode(["success" => true, "message" => "Membership request submitted."]);
} else {
  $error = pg_last_error($conn);

  if (strpos($error, 'users_email_key') !== false) {
    http_response_code(409);
    echo json_encode(["error" => "Email is already in use."]);
  } elseif (strpos($error, 'unique_phone') !== false) {
    http_response_code(409);
    echo json_encode(["error" => "Phone number is already in use."]);
  } else {
    http_response_code(500);
    echo json_encode(["error" => "Database error."]);
  }
}

pg_close($conn);
?>


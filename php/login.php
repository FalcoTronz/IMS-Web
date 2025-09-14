<?php
session_start();
header("Content-Type: application/json");

// Connect to Supabase PostgreSQL
require_once __DIR__ . '/db.php';
$conn = db();


if (!$conn) {
  http_response_code(500);
  echo json_encode(["success" => false, "error" => "Database connection failed."]);
  exit;
}

// Get POST data
$email     = $_POST['username'] ?? '';
$password  = $_POST['password'] ?? '';
$role      = $_POST['role'] ?? '';
$recaptcha = $_POST['g-recaptcha-response'] ?? '';

// === reCAPTCHA validation ===
$secretKey = '6Lf9KporAAAAAKGrDTohiaXCfNU1RURMx03JJth-';
$verifyUrl = "https://www.google.com/recaptcha/api/siteverify?secret=$secretKey&response=$recaptcha";
$response = json_decode(file_get_contents($verifyUrl), true);

if (!$response || !$response['success']) {
http_response_code(403);
echo json_encode(["success" => false, "error" => "reCAPTCHA verification failed."]);
exit;
}

// Validate input
if (!$email || !$password || !$role) {
  http_response_code(400);
  echo json_encode(["success" => false, "error" => "Email, password, and role required."]);
  exit;
}

// Look up user
$result = pg_query_params($conn, "SELECT * FROM users WHERE email = $1 LIMIT 1", [$email]);

if (!$result || pg_num_rows($result) === 0) {
  http_response_code(401);
  echo json_encode(["success" => false, "error" => "Invalid email or password."]);
  exit;
}

$user = pg_fetch_assoc($result);

// Check approval status and role
if ($user['status'] !== 'approved') {
  echo json_encode(["success" => false, "error" => "Account not approved."]);
  exit;
}

if ($user['role'] !== $role) {
  echo json_encode(["success" => false, "error" => "Incorrect role for login."]);
  exit;
}

// Check password
if (!password_verify($password, $user['password']))
 {
  echo json_encode(["success" => false, "error" => "Incorrect password."]);
  exit;
}

// Set session
$_SESSION['user_id'] = $user['id'];
$_SESSION['email']   = $user['email'];
$_SESSION['role']    = $user['role'];

echo json_encode(["success" => true, "role" => $user['role']]);
?>








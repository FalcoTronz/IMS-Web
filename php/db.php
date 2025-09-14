<?php
// php/db.php
// Central Postgres connection using env vars. Call with: $conn = db();

if (!function_exists('db')) {
  function db() {
    static $conn = null;
    if ($conn) return $conn;

    // Prefer env vars (Render → Environment)
    $host = getenv('PGHOST') ?: 'aws-0-eu-west-2.pooler.supabase.com';
    $port = getenv('PGPORT') ?: '5432';
    $dbname = getenv('PGDATABASE') ?: 'postgres';
    $user = getenv('PGUSER') ?: 'postgres.YOUR_ID';
    $password = getenv('PGPASSWORD') ?: 'YOUR_PASSWORD';

    $conn = @pg_connect("host={$host} port={$port} dbname={$dbname} user={$user} password={$password}");

    if (!$conn) {
      http_response_code(500);
      header('Content-Type: application/json');
      echo json_encode(["error" => "Database connection failed"]);
      exit;
    }
    return $conn;
  }
}

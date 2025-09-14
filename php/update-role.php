<?php
header('Content-Type: application/json');

// TODO: reuse your existing connection style
require_once __DIR__ . '/db.php';
$conn = db();

if (!$conn) { echo json_encode(['success'=>false,'error'=>'DB connect failed']); exit; }

$id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$role = $_POST['role'] ?? '';

if ($id <= 0 || !in_array($role, ['member','staff'], true)) {
  echo json_encode(['success'=>false,'error'=>'Invalid input']); exit;
}

$res = pg_query_params($conn, "UPDATE users SET role = $1 WHERE id = $2", [$role, $id]);
echo json_encode(['success' => $res ? true : false]);

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Connect to PostgreSQL
  require_once __DIR__ . '/db.php';
$conn = db();



  if (!$conn) {
    http_response_code(500);
    echo "Database connection failed";
    exit;
  }

  $id = $_POST['id'];

  $result = pg_query_params($conn, "DELETE FROM items WHERE id = $1", [$id]);

  if ($result) {
    echo "success";
  } else {
    echo "error";
  }

  pg_close($conn);
}
?>


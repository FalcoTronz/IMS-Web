<?php
session_start();
header("Content-Type: application/json");

// Connect to PostgreSQL
require_once __DIR__ . '/db.php';
$conn = db();

if (!$conn) {
  http_response_code(500);
  echo json_encode(["error" => "Database connection failed"]);
  exit;
}

$userId = $_SESSION['user_id'] ?? null;
$q = isset($_GET['q']) ? trim($_GET['q']) : "";
$onlyAvailable = isset($_GET['available']) && $_GET['available'] == '1'; // optional filter

$whereClauses = [];
$params = [];
$paramIndex = 1;

// Optional: only available items
if ($onlyAvailable) {
    $whereClauses[] = "status = 'Available'";
}

// Search filter
if ($q !== "") {
    $whereClauses[] = "(name ILIKE $" . $paramIndex . " OR details ILIKE $" . $paramIndex . " OR category ILIKE $" . $paramIndex . " OR item_code ILIKE $" . $paramIndex . ")";
    $params[] = '%' . $q . '%';
    $paramIndex++;
}

// Build SQL
$sql = "SELECT * FROM items";
if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(" AND ", $whereClauses);
}
$sql .= " ORDER BY id DESC";

$result = pg_query_params($conn, $sql, $params);

$items = [];

while ($row = pg_fetch_assoc($result)) {
    // Format last_update to British format
    if (!empty($row['last_update'])) {
        $row['last_update'] = date("d/m/Y", strtotime($row['last_update']));
    } else {
        $row['last_update'] = "";
    }

    // Add 'already_requested' flag
    $alreadyRequested = false;
    if ($userId) {
        $check = pg_query_params(
            $conn,
            "SELECT 1 FROM borrowings WHERE user_id = $1 AND item_id = $2 AND status IN ('pending','borrowed')",
            [$userId, $row['id']]
        );
        if ($check && pg_num_rows($check) > 0) {
            $alreadyRequested = true;
        }
    }

    $row['already_requested'] = $alreadyRequested;
    $items[] = $row;
}

echo json_encode($items);
pg_close($conn);
?>


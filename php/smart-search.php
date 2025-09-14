<?php
header("Content-Type: application/json");

// Connect to PostgreSQL
require_once __DIR__ . '/db.php';
$conn = db();


if (!$conn) {
  http_response_code(500);
  echo json_encode(["error" => "Database connection failed"]);
  exit;
}

// Get and sanitize the query
$query = strtolower(trim($_POST['query'] ?? ''));
if (!$query) {
  echo json_encode([]);
  exit;
}

// Tokenize input (split into words)
$keywords = preg_split('/\s+/', $query);
$searchFields = ['name', 'details', 'category'];

$result = pg_query($conn, "SELECT * FROM items");
$matches = [];

while ($row = pg_fetch_assoc($result)) {
  $score = 0;
  $allKeywordsFound = true;

  // 1. Keyword matching for name, details, category
  foreach ($keywords as $kw) {
    $kw = trim($kw);
    $found = false;

    foreach ($searchFields as $field) {
      if (isset($row[$field]) && stripos($row[$field], $kw) !== false) {
        $found = true;
        break;
      }
    }

    if (!$found) {
      $allKeywordsFound = false;
      break;
    }
  }

  if ($allKeywordsFound) {
    $score += 10;
  }

  // 2. Match item_code if it starts with the query
  if (is_numeric($query) && isset($row['item_code'])) {
    $item_code = (string) $row['item_code'];
    if (strpos($item_code, $query) === 0) {
      $score += 10;
    }
  }

  // 3. Match Year if it starts with the query
  if (is_numeric($query) && isset($row['year'])) {
    $year = (string) $row['year'];
    if (strpos($year, $query) === 0) {
      $score += 10;
    }
  }

  if ($score > 0) {
    $row['score'] = $score;
    $matches[] = $row;
  }
}

// Sort results by score
usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);

// Return top 10 matches
echo json_encode(array_slice($matches, 0, 10));

pg_close($conn);
?>


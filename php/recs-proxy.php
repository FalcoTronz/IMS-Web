<?php
session_start();
header('Content-Type: application/json');

$apiBase = getenv('ET_API_BASE');
$apiKey  = getenv('ET_API_KEY');
if (!$apiBase || !$apiKey) { http_response_code(500); echo json_encode(["error"=>"Proxy not configured"]); exit; }

$userId = $_GET['user_id'] ?? ($_SESSION['user_id'] ?? null);
if (!$userId) { echo json_encode([]); exit; }

$cacheFile = sys_get_temp_dir() . '/recs_' . preg_replace('/\D/', '', (string)$userId) . '.json';
$ttl = 60;
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) { readfile($cacheFile); exit; }

$ch = curl_init($apiBase . '/recs?user_id=' . urlencode($userId));
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER     => ['X-API-KEY: ' . $apiKey],
  CURLOPT_TIMEOUT        => 20
]);
$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 200 || !$res) { http_response_code(502); echo json_encode(["error"=>"Upstream error"]); exit; }

file_put_contents($cacheFile, $res);
echo $res;

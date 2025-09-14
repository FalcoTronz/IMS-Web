<?php
header('Content-Type: application/json');
$apiBase = getenv('ET_API_BASE'); $apiKey = getenv('ET_API_KEY');
if (!$apiBase || !$apiKey) { http_response_code(500); echo json_encode(["error"=>"Proxy not configured"]); exit; }

$cacheFile = sys_get_temp_dir()."/overdue_stats.json"; $ttl = 60;

if (file_exists($cacheFile) && (time()-filemtime($cacheFile) < $ttl)) { readfile($cacheFile); exit; }

$ch = curl_init($apiBase."/overdue-stats");
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>['X-API-KEY: '.$apiKey], CURLOPT_TIMEOUT=>20]);
$res = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);

if ($code !== 200 || !$res) { http_response_code(502); echo json_encode(["error"=>"Upstream error from /overdue-stats"]); exit; }
file_put_contents($cacheFile, $res); echo $res;

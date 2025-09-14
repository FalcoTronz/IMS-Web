<?php
// php/session.php
// Centralized, sticky session setup for LMS

if (session_status() === PHP_SESSION_NONE) {
  // Store sessions on disk (container-safe)
  $savePath = sys_get_temp_dir() . '/phpsessions';
  if (!is_dir($savePath)) { @mkdir($savePath, 0700, true); }
  ini_set('session.save_path', $savePath);

  // Lifetime (default 1 day; override with SESSION_LIFETIME env seconds)
  $lifetime = (int) (getenv('SESSION_LIFETIME') ?: 86400);
  ini_set('session.gc_maxlifetime', $lifetime);
  ini_set('session.cookie_lifetime', $lifetime);
  ini_set('session.use_strict_mode', 1);
  ini_set('session.use_only_cookies', 1);
  ini_set('session.cookie_httponly', 1);

  // Detect HTTPS behind proxy (Render)
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

  // Secure cookie with site-wide scope
  session_set_cookie_params([
    'lifetime' => $lifetime,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $https,
    'httponly' => true,
    'samesite' => 'Lax'
  ]);

  // Consistent session name (avoid clashes)
  if (session_name() !== 'LMSSESSID') {
    session_name('LMSSESSID');
  }

  session_start();
}

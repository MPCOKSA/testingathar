<?php
declare(strict_types=1);

// استخدم توقيت المملكة في كل عمليات PHP التي لا تحدد منطقة زمنية صراحةً.
date_default_timezone_set('Asia/Riyadh');

$projectRoot = dirname(__DIR__);
$localConfig = $projectRoot . '/config/local.php';
if (is_file($localConfig) && is_readable($localConfig)) {
    $values = require $localConfig;
    if (is_array($values)) {
        foreach ($values as $key => $value) {
            if (is_string($key) && preg_match('/^[A-Z][A-Z0-9_]*$/', $key) && is_scalar($value) && getenv($key) === false) {
                putenv($key . '=' . (string)$value);
                $_ENV[$key] = (string)$value;
            }
        }
    }
}
$envFile = $projectRoot . '/.env';
if (is_file($envFile) && is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key); $value = trim($value);
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key) || getenv($key) !== false) continue;
        if (strlen($value) >= 2 && (($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'"))) $value = substr($value, 1, -1);
        putenv($key . '=' . $value); $_ENV[$key] = $value;
    }
}

$forwarded = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwarded === 'https';
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.gc_maxlifetime', '7200');
session_name('athar_session');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$isSecure,'httponly'=>true,'samesite'=>'Lax']);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once $projectRoot . '/app/Security.php';
Security::hardenSession();
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 0');
header('X-Permitted-Cross-Domain-Policies: none');
header('X-Download-Options: noopen');
header('Origin-Agent-Cluster: ?1');
header('Cross-Origin-Opener-Policy: same-origin');
header('Cross-Origin-Resource-Policy: same-origin');
$csp="default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; frame-src 'self'; object-src 'none'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self' https://cdn.jsdelivr.net https://tessdata.projectnaptha.com";
if ($isSecure) $csp .= "; upgrade-insecure-requests";
header('Content-Security-Policy: ' . $csp);
header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
if ($isSecure) header('Strict-Transport-Security: max-age=31536000');
header('Cache-Control: no-store');

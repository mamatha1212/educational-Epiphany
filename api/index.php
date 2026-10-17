<?php
ob_start();
/**
 * Vercel Serverless Entry Point & Front-Controller for EEMT 2027
 */

// Parse request path
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = trim($path, '/');

// Base directory (project root)
$baseDir = dirname(__DIR__);

// Default entry point
if ($path === '' || $path === 'index.php') {
    chdir($baseDir);
    require $baseDir . '/index.php';
    exit;
}

// Full path to requested target
$target = $baseDir . '/' . $path;

// 1. Direct file match (e.g. /register.php, /admin/dashboard.php)
if (is_file($target)) {
    chdir(dirname($target));
    require $target;
    exit;
}

// 2. Extensionless match (e.g. /register -> register.php)
if (is_file($target . '.php')) {
    chdir(dirname($target . '.php'));
    require $target . '.php';
    exit;
}

// 3. Directory index (e.g. /admin/ -> /admin/dashboard.php or index.php)
if (is_dir($target)) {
    if (is_file($target . '/index.php')) {
        chdir($target);
        require $target . '/index.php';
        exit;
    }
    if (is_file($target . '/dashboard.php')) {
        chdir($target);
        require $target . '/dashboard.php';
        exit;
    }
}

// 4. Fallback 404
http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><body style='font-family:sans-serif;text-align:center;padding:50px;'>";
echo "<h2>404 - Page Not Found</h2>";
echo "<p>The requested page <code>" . htmlspecialchars($path) . "</code> was not found.</p>";
echo "<p><a href='/'>&larr; Return to EEMT 2027 Home</a></p>";
echo "</body></html>";

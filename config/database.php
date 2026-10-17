<?php
/**
 * EEMT 2027 - Unified Database & Environment Configuration
 * 
 * Supports:
 *   1. Local Development (SQLite or MySQL)
 *   2. Shared Hosting (cPanel / LiteSpeed / Apache with MySQL / MariaDB via phpMyAdmin)
 *   3. VPS Hosting (Ubuntu / Debian / AlmaLinux / CentOS with Nginx / Apache + PHP-FPM)
 */

if (!function_exists('loadEnvFile')) {
    /**
     * Lightweight .env file loader (Zero external composer dependencies required)
     */
    function loadEnvFile(string $filePath): void {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            // Skip comments and empty lines
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                list($key, $val) = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val);

                // Remove surrounding quotes if any
                if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                    (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                    $val = substr($val, 1, -1);
                }

                if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                    putenv("$key=$val");
                    $_ENV[$key] = $val;
                    $_SERVER[$key] = $val;
                }
            }
        }
    }
}

// 1. Automatically load .env if present in project root
$rootDir = dirname(__DIR__);
loadEnvFile($rootDir . '/.env');

// 2. Load optional cPanel / hosting override array if present
$localConfig = [];
if (file_exists(__DIR__ . '/config.local.php')) {
    $localConfig = require __DIR__ . '/config.local.php';
}

if (!function_exists('env')) {
    /**
     * Retrieve environment setting with priority:
     * config.local.php -> $_ENV / getenv -> fallback default
     */
    function env(string $key, $default = null) {
        global $localConfig;
        if (isset($localConfig[$key])) {
            return $localConfig[$key];
        }
        $val = getenv($key);
        if ($val !== false) {
            return $val;
        }
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        return $default;
    }
}

// Global Constants
if (!defined('DB_DRIVER')) {
    define('DB_DRIVER', strtolower(env('DB_DRIVER', 'sqlite')));
}

define('MYSQL_HOST', env('DB_HOST', '127.0.0.1'));
define('MYSQL_PORT', (int)env('DB_PORT', 3306));
define('MYSQL_NAME', env('DB_DATABASE', 'ducatio9_eemt2027'));
define('MYSQL_USER', env('DB_USERNAME', 'ducatio9_registration'));
define('MYSQL_PASS', env('DB_PASSWORD', 'Rao969699'));

$sqliteFile = env('DB_SQLITE_PATH', 'database/eemt2027.sqlite');
if (!str_starts_with($sqliteFile, '/') && !preg_match('/^[A-Za-z]:\\\\/', $sqliteFile)) {
    // Relative to root
    $sqliteFile = $rootDir . '/' . ltrim($sqliteFile, '/\\');
}

// In Vercel serverless environment, filesystem is read-only. Copy SQLite to /tmp so PDO can open it safely
if (getenv('VERCEL') || isset($_ENV['VERCEL']) || (file_exists($sqliteFile) && !is_writable(dirname($sqliteFile)))) {
    $tmpDb = sys_get_temp_dir() . '/eemt2027.sqlite';
    if (!file_exists($tmpDb) && file_exists($sqliteFile)) {
        @copy($sqliteFile, $tmpDb);
    }
    if (file_exists($tmpDb)) {
        $sqliteFile = $tmpDb;
    }
}
define('SQLITE_PATH', $sqliteFile);

/**
 * Get the PDO Database Connection instance
 * Works seamlessly across MySQL (Shared Hosting / VPS) & SQLite (Local / VPS)
 * 
 * @return PDO
 * @throws PDOException
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $driver = DB_DRIVER;

    if ($driver === 'sqlite') {
        $dbDir = dirname(SQLITE_PATH);
        if (!is_dir($dbDir)) {
            @mkdir($dbDir, 0775, true);
        }
        
        $dsn = 'sqlite:' . SQLITE_PATH;
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("PRAGMA foreign_keys = ON;");
        try {
            $pdo->exec("PRAGMA journal_mode = WAL;");
        } catch (Exception $e) {
            @$pdo->exec("PRAGMA journal_mode = DELETE;");
        }
    } else {
        // MySQL / MariaDB (Standard for Shared Hosting cPanel & Production VPS)
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', MYSQL_HOST, MYSQL_PORT, MYSQL_NAME);
        try {
            $pdo = new PDO($dsn, MYSQL_USER, MYSQL_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);
        } catch (PDOException $e) {
            // Friendly error message for hosting setup without leaking raw credentials to public
            $isDev = (env('APP_ENV') === 'local' || env('APP_DEBUG') === 'true');
            if ($isDev) {
                throw new PDOException("Database connection failed [MySQL]: " . $e->getMessage() . " (Verify credentials in .env or config/config.local.php)");
            } else {
                error_log("EEMT 2027 Database Error: " . $e->getMessage());
                die("<!DOCTYPE html><html><body style='font-family:sans-serif;padding:3rem;text-align:center;'><h2>Database Connection Notice</h2><p>The system is currently unable to reach the database server. Please verify MySQL credentials in your hosting control panel (.env or config.local.php).</p></body></html>");
            }
        }
    }

    return $pdo;
}

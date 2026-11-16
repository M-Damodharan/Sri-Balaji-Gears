<?php
/**
 * Sri Balaji Gears - Core Configuration & Database Bootstrap
 * Reads environment variables with fallback to .env file
 */

// Prevent direct execution error reporting leak in production
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Session security settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
}

// Enterprise HTTP Security Headers
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

// 1. Load .env file if it exists
function loadEnv($filePath) {
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return;
    }
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            // Remove wrapping quotes
            if (preg_match('/^"(.*)"$/', $value, $m)) {
                $value = $m[1];
            } elseif (preg_match("/^'(.*)'$/", $value, $m)) {
                $value = $m[1];
            }
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// Check root .env
$envPath = dirname(__DIR__) . '/.env';
if (file_exists($envPath)) {
    loadEnv($envPath);
}

// 2. Config accessor helper
function config($key, $default = null) {
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

// 3. Database connection (SQLite default, portable & robust)
function getDbConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dbFile = dirname(__DIR__) . '/data/enquiries.db';
    $dataDir = dirname($dbFile);
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }

    $dsn = "sqlite:" . $dbFile;
    $pdo = new PDO($dsn, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Enable WAL mode for high concurrency
    $pdo->exec('PRAGMA journal_mode = WAL;');
    $pdo->exec('PRAGMA synchronous = NORMAL;');

    // Initialize Schema
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS enquiries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            reference_id TEXT UNIQUE NOT NULL,
            customer_name TEXT NOT NULL,
            company_name TEXT NOT NULL,
            email TEXT NOT NULL,
            phone TEXT NOT NULL,
            requirement_category TEXT NOT NULL,
            custom_specification TEXT,
            volume_estimate TEXT,
            message TEXT NOT NULL,
            attachment_name TEXT,
            attachment_path TEXT,
            attachment_size INTEGER,
            ip_address TEXT,
            user_agent TEXT,
            status TEXT DEFAULT 'New',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE INDEX IF NOT EXISTS idx_enquiries_created ON enquiries(created_at);
        CREATE INDEX IF NOT EXISTS idx_enquiries_status ON enquiries(status);
        CREATE INDEX IF NOT EXISTS idx_enquiries_ref ON enquiries(reference_id);
    ");

    return $pdo;
}

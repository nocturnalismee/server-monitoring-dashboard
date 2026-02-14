<?php
/**
 * Server Monitoring Dashboard - Configuration File
 * Version 2.0
 */

// ============== DATABASE CONFIGURATION ==============
define('DB_HOST', 'localhost');
define('DB_USER', 'serverstatus_user');
define('DB_PASS', 'your_strong_password_here');
define('DB_NAME', 'serverstatus');
define('DB_CHARSET', 'utf8mb4');

// ============== APPLICATION SETTINGS ==============
define('APP_VERSION', '2.0.0');
define('APP_NAME', 'Server Monitoring Dashboard');
define('REFRESH_INTERVAL', 10000); // milliseconds (10 seconds)
define('OFFLINE_TIMEOUT', 300); // seconds (5 minutes)
define('API_VERSION', '2.0');

// ============== PATHS ==============
define('BASE_PATH', dirname(__DIR__));
define('TEMPLATE_PATH', BASE_PATH . '/templates/');
define('API_PATH', BASE_PATH . '/api/');

// ============== LOAD DATABASE CLASS ==============
require_once(__DIR__ . '/Database.php');

// ============== INITIALIZE DATABASE CONNECTION ==============
try {
    $db = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_CHARSET);
} catch (Exception $e) {
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed']));
}

// ============== SECURITY HEADERS ==============
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// ============== UTILITY FUNCTIONS ==============

/**
 * Sanitize user input
 */
function sanitize($input) {
    if (is_array($input)) {
        foreach ($input as $key => $value) {
            $input[$key] = sanitize($value);
        }
        return $input;
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Check if server is online based on last update timestamp
 */
function isServerOnline($last_update_unix) {
    $current_time = time();
    $diff = $current_time - (int)$last_update_unix;
    return $diff < OFFLINE_TIMEOUT;
}

/**
 * Format bytes to human readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Log to file (optional)
 */
function logMessage($message, $level = 'INFO') {
    $logFile = __DIR__ . '/../logs/app.log';
    if (!file_exists(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

?>

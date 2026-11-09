<?php
// ==============================================================================
// DOCTOR CONNECT - APPLICATION CONFIGURATION
// ==============================================================================

// Error reporting for development (can be toggled in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set default timezone
date_default_timezone_set('UTC');

// Custom session save path inside project to ensure portability and write permissions
$sessionSavePath = __DIR__ . '/../sessions';
if (!is_dir($sessionSavePath)) {
    @mkdir($sessionSavePath, 0777, true);
}
@session_save_path($sessionSavePath);

// Secure Session Configuration (for Web requests)
if (php_sapi_name() !== 'cli') {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.gc_maxlifetime', 7200);
        session_start();
    }

    // Session timeout check (2 hours)
    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 7200)) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['LAST_ACTIVITY'] = time();
}

// Role Constants
define('ROLE_PATIENT', 'patient');
define('ROLE_DOCTOR', 'doctor');
define('ROLE_ADMIN', 'admin');

// Status Constants
define('STATUS_WAITING', 'waiting');
define('STATUS_SERVING', 'serving');
define('STATUS_COMPLETED', 'completed');
define('STATUS_NO_SHOW', 'no_show');
define('STATUS_CANCELLED', 'cancelled');

// Database Configuration
define('DB_DRIVER', getenv('DB_DRIVER') ?: 'mysql');
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'doctor_connect');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('SQLITE_PATH', __DIR__ . '/../database/doctor_connect.sqlite');

// App Settings
define('APP_NAME', 'DoctorConnect');
define('APP_TAGLINE', 'Token-Based Online Doctor Consultation Platform');
define('DEFAULT_JITSI_DOMAIN', 'meet.jit.si');
define('QUEUE_POLL_INTERVAL_MS', 3000);

/**
 * Compute the base URL of the application dynamically
 */
function getBaseUrl(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Find the root folder relative to script
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    
    // Check if script is inside subdirectories (patient, doctor, admin, api)
    $subdirs = ['/patient', '/doctor', '/admin', '/api'];
    foreach ($subdirs as $subdir) {
        if (str_ends_with($scriptDir, $subdir)) {
            $scriptDir = substr($scriptDir, 0, -strlen($subdir));
            break;
        }
    }
    
    $base = rtrim($scriptDir, '/');
    return $protocol . $host . $base;
}

/**
 * Generate an absolute URL for application paths
 */
function url(string $path = ''): string {
    $base = getBaseUrl();
    $path = ltrim($path, '/');
    return $path ? "$base/$path" : $base . '/';
}

/**
 * Generate asset URL
 */
function asset(string $path): string {
    return url('assets/' . ltrim($path, '/'));
}

/**
 * Set flash message
 */
function setFlash(string $type, string $message): void {
    if (!isset($_SESSION['flashes'])) {
        $_SESSION['flashes'] = [];
    }
    $_SESSION['flashes'][] = ['type' => $type, 'message' => $message];
}

/**
 * Retrieve and clear flash messages
 */
function getFlashes(): array {
    $flashes = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return $flashes;
}

/**
 * Safe sanitization output helper
 */
function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

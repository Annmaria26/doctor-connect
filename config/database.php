<?php
// ==============================================================================
// DOCTOR CONNECT - DATABASE CONNECTION (PDO)
// ==============================================================================

require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;
    private static string $activeDriver = 'mysql';

    public static function getConnection(): PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $driver = DB_DRIVER;

        if ($driver === 'mysql') {
            try {
                $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ];

                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
                self::$activeDriver = 'mysql';
                return self::$instance;
            } catch (PDOException $e) {
                // If MySQL is not running or database is not created, check if SQLite fallback is available
                if (file_exists(SQLITE_PATH)) {
                    return self::connectSqlite();
                }

                // If on web page, give helpful diagnostic
                $errorMsg = "Database Connection Failed: " . $e->getMessage();
                if (php_sapi_name() !== 'cli' && basename($_SERVER['PHP_SELF'] ?? '') !== 'setup.php') {
                    die("
                        <div style='font-family:sans-serif; max-width:600px; margin:50px auto; padding:24px; border:1px solid #e2e8f0; border-radius:8px; background:#fff;'>
                            <h2 style='color:#e11d48; margin-top:0;'>Database Connection Notice</h2>
                            <p>Could not connect to MySQL server at <code>" . e(DB_HOST) . ":" . e(DB_PORT) . "</code>.</p>
                            <p><strong>Error:</strong> " . e($e->getMessage()) . "</p>
                            <hr style='border:none; border-top:1px solid #e2e8f0; margin:20px 0;'>
                            <p>If you haven't initialized the database yet, run the automated setup wizard:</p>
                            <p><a href='" . url('setup.php') . "' style='display:inline-block; background:#0284c7; color:#fff; padding:10px 18px; text-decoration:none; border-radius:6px; font-weight:600;'>Go to Setup & Migration Wizard</a></p>
                        </div>
                    ");
                }
                throw $e;
            }
        } else {
            return self::connectSqlite();
        }
    }

    private static function connectSqlite(): PDO {
        $dbPath = SQLITE_PATH;
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $dsn = 'sqlite:' . $dbPath;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        self::$instance = new PDO($dsn, null, null, $options);
        // SQLite foreign keys & WAL mode
        self::$instance->exec('PRAGMA foreign_keys = ON;');
        self::$activeDriver = 'sqlite';
        return self::$instance;
    }

    public static function getDriver(): string {
        return self::$activeDriver;
    }
}

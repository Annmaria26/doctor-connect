<?php
// ==============================================================================
// DOCTOR CONNECT - AUTOMATED DATABASE SETUP & MIGRATION WIZARD
// Run via browser or CLI: php setup.php
// ==============================================================================

require_once __DIR__ . '/config/config.php';

$isCli = (php_sapi_name() === 'cli');
$messages = [];
$error = null;
$setupComplete = false;

function logSetup(string $msg, string $type = 'info'): void {
    global $messages, $isCli;
    $messages[] = ['type' => $type, 'text' => $msg];
    if ($isCli) {
        $prefix = match($type) {
            'success' => '[SUCCESS] ',
            'danger'  => '[ERROR] ',
            'warning' => '[WARN] ',
            default   => '[INFO] '
        };
        echo $prefix . $msg . PHP_EOL;
    }
}

// Function to run setup
function runSetup(): bool {
    global $error;

    // Check if user requested specific driver or auto-detect
    $targetDriver = DB_DRIVER;
    $mysqlAvailable = false;

    // 1. Try MySQL Connection
    try {
        $dsnWithoutDb = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', DB_HOST, DB_PORT);
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];
        $pdoServer = new PDO($dsnWithoutDb, DB_USER, DB_PASS, $options);
        
        // Create database if not exists
        logSetup("Connected to MySQL server at " . DB_HOST . ":" . DB_PORT, "success");
        $dbName = DB_NAME;
        $pdoServer->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        logSetup("Database `{$dbName}` verified/created.", "success");
        
        // Connect to the specific database
        $pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME), DB_USER, DB_PASS, $options);
        
        // Run schema.sql
        $schemaSql = file_get_contents(__DIR__ . '/database/schema.sql');
        $pdo->exec($schemaSql);
        logSetup("Executed database/schema.sql successfully (All tables & UNIQUE constraints created).", "success");

        // Run seed.sql
        $seedSql = file_get_contents(__DIR__ . '/database/seed.sql');
        $pdo->exec($seedSql);
        logSetup("Executed database/seed.sql successfully (Admin, 4 Doctors, Schedules, & Sample Queues).", "success");

        $mysqlAvailable = true;
        return true;
    } catch (Exception $e) {
        logSetup("MySQL setup note: " . $e->getMessage(), "warning");
        // Fall back to SQLite setup so the app is 100% usable right away
        logSetup("Initializing local SQLite database fallback for instant zero-config testing...", "info");
        try {
            $sqliteFile = SQLITE_PATH;
            $dir = dirname($sqliteFile);
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            
            // Delete old sqlite file if running fresh setup
            if (file_exists($sqliteFile)) @unlink($sqliteFile);

            $pdoSqlite = new PDO('sqlite:' . $sqliteFile, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            $pdoSqlite->exec('PRAGMA foreign_keys = ON;');

            // SQLite Schema
            $pdoSqlite->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    email TEXT NOT NULL UNIQUE,
                    phone TEXT NOT NULL UNIQUE,
                    password_hash TEXT NOT NULL,
                    role TEXT NOT NULL DEFAULT 'patient',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS doctors (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    specialization TEXT NOT NULL,
                    consultation_fee REAL NOT NULL DEFAULT 50.00,
                    avg_consultation_mins INTEGER NOT NULL DEFAULT 15,
                    bio TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS doctor_schedules (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    doctor_id INTEGER NOT NULL,
                    day_of_week INTEGER NOT NULL,
                    start_time TEXT NOT NULL,
                    end_time TEXT NOT NULL,
                    slot_duration INTEGER NOT NULL DEFAULT 15,
                    is_active INTEGER NOT NULL DEFAULT 1,
                    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS appointments (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    patient_id INTEGER NOT NULL,
                    doctor_id INTEGER NOT NULL,
                    appointment_date TEXT NOT NULL,
                    token_number INTEGER NOT NULL,
                    status TEXT NOT NULL DEFAULT 'waiting',
                    meeting_link TEXT NULL,
                    estimated_time TEXT NULL,
                    reason TEXT NULL,
                    diagnosis TEXT NULL,
                    prescription TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE (doctor_id, appointment_date, token_number),
                    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS queue_logs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    appointment_id INTEGER NOT NULL,
                    action TEXT NOT NULL,
                    action_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS notifications (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    message TEXT NOT NULL,
                    is_read INTEGER NOT NULL DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS clinic_settings (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    setting_key TEXT NOT NULL UNIQUE,
                    setting_value TEXT NULL
                );
            ");
            logSetup("SQLite schema created with UNIQUE (doctor_id, appointment_date, token_number) constraint.", "success");

            // Seed Users, Doctors, and Settings in SQLite
            $hash = '$2y$10$peNHoR.Z411S9mGwatXp8OtsprU3lEAYYztKJClzmBkc24nKUOf5e'; // password123
            
            // Clinic Settings
            $pdoSqlite->exec("
                INSERT OR REPLACE INTO clinic_settings (setting_key, setting_value) VALUES
                ('clinic_name', 'DoctorConnect TeleHealth Clinic'),
                ('clinic_tagline', 'Instant Online Token & Live Queue Consultation'),
                ('clinic_phone', '+1 (800) 555-0199'),
                ('clinic_email', 'care@doctorconnect.com'),
                ('clinic_address', '450 Healthtech Blvd, Suite 300, Silicon Valley, CA'),
                ('default_slot_duration', '15'),
                ('default_consultation_fee', '50.00'),
                ('jitsi_domain', 'meet.jit.si'),
                ('allow_cancel_hours', '2');
            ");

            // Users
            $pdoSqlite->exec("
                INSERT OR REPLACE INTO users (id, name, email, phone, password_hash, role) VALUES
                (1, 'Admin Receptionist', 'admin@doctorconnect.com', '+15550000001', '{$hash}', 'admin'),
                (2, 'Dr. Sarah Jenkins', 'sarah.jenkins@doctorconnect.com', '+15550000002', '{$hash}', 'doctor'),
                (3, 'Dr. Marcus Chen', 'marcus.chen@doctorconnect.com', '+15550000003', '{$hash}', 'doctor'),
                (4, 'Dr. Emily Rodriguez', 'emily.rodriguez@doctorconnect.com', '+15550000004', '{$hash}', 'doctor'),
                (5, 'Dr. David Patel', 'david.patel@doctorconnect.com', '+15550000005', '{$hash}', 'doctor'),
                (6, 'John Doe', 'john.doe@gmail.com', '+15550000006', '{$hash}', 'patient'),
                (7, 'Jane Smith', 'jane.smith@gmail.com', '+15550000007', '{$hash}', 'patient'),
                (8, 'Robert Brown', 'robert.brown@gmail.com', '+15550000008', '{$hash}', 'patient'),
                (9, 'Alice Taylor', 'alice.taylor@gmail.com', '+15550000009', '{$hash}', 'patient');
            ");

            // Doctors
            $pdoSqlite->exec("
                INSERT OR REPLACE INTO doctors (id, user_id, specialization, consultation_fee, avg_consultation_mins, bio) VALUES
                (1, 2, 'Cardiology', 80.00, 15, 'Senior Board Certified Cardiologist with 14+ years experience managing hypertension, heart rhythm, and preventive cardiovascular care.'),
                (2, 3, 'Pediatrics', 55.00, 15, 'Compassionate pediatrician specializing in newborn care, childhood immunizations, and pediatric acute care.'),
                (3, 4, 'Dermatology', 65.00, 20, 'Consultant dermatologist focused on skin conditions, eczema, acne management, and telehealth tele-dermatology diagnostics.'),
                (4, 5, 'General Medicine', 40.00, 10, 'Experienced primary care physician treating seasonal viral illnesses, diabetes management, chronic lifestyle conditions, and general wellness.');
            ");

            // Doctor Schedules
            $todayDate = date('Y-m-d');
            for ($docId = 1; $docId <= 4; $docId++) {
                for ($day = 0; $day <= 6; $day++) {
                    $pdoSqlite->exec("INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration, is_active) VALUES ({$docId}, {$day}, '08:30:00', '17:00:00', 15, 1);");
                }
            }

            // Today's Initial Live Queue Appointments
            $pdoSqlite->exec("
                INSERT OR REPLACE INTO appointments (id, patient_id, doctor_id, appointment_date, token_number, status, meeting_link, estimated_time, reason, diagnosis, prescription) VALUES
                (1, 6, 1, '{$todayDate}', 1, 'completed', 'https://meet.jit.si/doctor-connect-appt-1-x9a2f', '{$todayDate} 09:00:00', 'Routine hypertension checkup and palpitations', 'Mild essential hypertension (controlled)', '1. Amlodipine 5mg - 1 tablet once daily morning\n2. Low sodium diet (<2g/day)\n3. Recheck BP in 3 weeks'),
                (2, 7, 1, '{$todayDate}', 2, 'serving', 'https://meet.jit.si/doctor-connect-appt-2-b4k8m', '{$todayDate} 09:15:00', 'Chest tightness on fast exertion', NULL, NULL),
                (3, 8, 1, '{$todayDate}', 3, 'waiting', 'https://meet.jit.si/doctor-connect-appt-3-k2p9q', '{$todayDate} 09:30:00', 'High cholesterol report review and fatigue', NULL, NULL),
                (4, 9, 1, '{$todayDate}', 4, 'waiting', 'https://meet.jit.si/doctor-connect-appt-4-m7z1r', '{$todayDate} 09:45:00', 'Follow-up for ECG baseline review', NULL, NULL);

                INSERT INTO queue_logs (appointment_id, action, action_time) VALUES
                (1, 'called', datetime('now', '-45 minute')),
                (1, 'serving', datetime('now', '-44 minute')),
                (1, 'served', datetime('now', '-25 minute')),
                (2, 'called', datetime('now', '-10 minute')),
                (2, 'serving', datetime('now', '-9 minute'));
            ");

            logSetup("SQLite fallback database seeded successfully at {$sqliteFile}", "success");
            return true;
        } catch (Exception $e2) {
            $error = $e2->getMessage();
            logSetup("Setup failed: " . $e2->getMessage(), "danger");
            return false;
        }
    }
}

// Automatically execute on request or form submit
if ($isCli || isset($_POST['action']) || !isset($_POST['action'])) {
    $setupComplete = runSetup();
}

if ($isCli) {
    exit($setupComplete ? 0 : 1);
}

$pageTitle = "Database Setup & Migration Tool - " . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width:800px; margin:2rem auto 4rem;">
    <div class="card" style="box-shadow:var(--shadow-md);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; border-bottom:1px solid #f1f5f9; padding-bottom:1rem;">
            <div>
                <h1 style="font-size:1.6rem; font-weight:800; color:#0f172a; margin:0;">
                    ⚙️ Database Setup & Migration Wizard
                </h1>
                <p style="color:#64748b; font-size:0.875rem; margin:4px 0 0;">
                    Auto-creates schema, constraints, indexes, and pre-seeds test users & doctors
                </p>
            </div>
            <span class="badge" style="background:#e0f2fe; color:#0284c7; font-size:0.8rem;">
                Target: MySQL / SQLite
            </span>
        </div>

        <!-- EXECUTION LOGS -->
        <div style="background:#0f172a; color:#f8fafc; border-radius:8px; padding:1.25rem; font-family:monospace; font-size:0.85rem; line-height:1.6; margin-bottom:1.5rem; max-height:280px; overflow-y:auto;">
            <?php foreach ($messages as $m): 
                $color = match($m['type']) {
                    'success' => '#4ade80',
                    'danger'  => '#f87171',
                    'warning' => '#fbbf24',
                    default   => '#94a3b8'
                };
            ?>
                <div style="color:<?= $color ?>;">&bull; <?= e($m['text']) ?></div>
            <?php endforeach; ?>
        </div>

        <?php if ($setupComplete): ?>
            <div class="alert alert-success">
                <div>
                    <strong>✓ Database is Ready!</strong> All tables, constraints (including the race-safe <code>UNIQUE KEY unique_token_per_doctor_day</code>), and sample consultation tokens have been successfully installed.
                </div>
            </div>

            <!-- DEMO LOGIN QUICK LINKS -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:1.25rem; margin-top:1.5rem;">
                <h3 style="font-size:1.05rem; font-weight:700; color:#0f172a; margin-bottom:0.75rem;">
                    🚀 Jump Directly into Testing:
                </h3>
                <p style="font-size:0.85rem; color:#64748b; margin-bottom:1rem;">
                    Default password for all demo accounts: <code style="background:#e2e8f0; padding:2px 6px; border-radius:4px; font-weight:700;">password123</code>
                </p>

                <div class="grid-3">
                    <div style="background:white; border:1px solid #cbd5e1; border-radius:6px; padding:1rem; text-align:center;">
                        <span class="role-badge role-patient">Patient</span>
                        <div style="font-size:0.95rem; font-weight:700; margin:0.4rem 0 0.2rem;">John Doe</div>
                        <div style="font-size:0.75rem; color:#64748b; margin-bottom:0.75rem;">john.doe@gmail.com</div>
                        <a href="<?= url('login.php?quick=patient') ?>" class="btn btn-outline btn-sm" style="width:100%;">
                            Sign In as Patient &rarr;
                        </a>
                    </div>

                    <div style="background:white; border:1px solid #cbd5e1; border-radius:6px; padding:1rem; text-align:center;">
                        <span class="role-badge role-doctor">Doctor</span>
                        <div style="font-size:0.95rem; font-weight:700; margin:0.4rem 0 0.2rem;">Dr. Sarah Jenkins</div>
                        <div style="font-size:0.75rem; color:#64748b; margin-bottom:0.75rem;">Cardiology Specialist</div>
                        <a href="<?= url('login.php?quick=doctor') ?>" class="btn btn-outline btn-sm" style="width:100%;">
                            Sign In as Doctor &rarr;
                        </a>
                    </div>

                    <div style="background:white; border:1px solid #cbd5e1; border-radius:6px; padding:1rem; text-align:center;">
                        <span class="role-badge role-admin">Admin / Reception</span>
                        <div style="font-size:0.95rem; font-weight:700; margin:0.4rem 0 0.2rem;">Reception Desk</div>
                        <div style="font-size:0.75rem; color:#64748b; margin-bottom:0.75rem;">admin@doctorconnect.com</div>
                        <a href="<?= url('login.php?quick=admin') ?>" class="btn btn-outline btn-sm" style="width:100%;">
                            Sign In as Admin &rarr;
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                <div>Setup encountered an issue: <?= e($error) ?></div>
            </div>
        <?php endif; ?>

        <div style="margin-top:1.5rem; text-align:center;">
            <a href="<?= url() ?>" class="btn btn-primary">
                Return to DoctorConnect Homepage &rarr;
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

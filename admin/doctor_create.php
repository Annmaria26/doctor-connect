<?php
// ==============================================================================
// DOCTOR CONNECT - REGISTER NEW DOCTOR (ADMIN)
// ==============================================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_ADMIN);

$errors = [];
$name = '';
$email = '';
$phone = '';
$specialization = '';
$fee = '50.00';
$avgMins = '15';
$bio = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie();

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $specialization = trim($_POST['specialization'] ?? '');
    $fee = (float)($_POST['consultation_fee'] ?? 50.00);
    $avgMins = max(5, (int)($_POST['avg_consultation_mins'] ?? 15));
    $bio = trim($_POST['bio'] ?? '');

    if (empty($name) || empty($email) || empty($phone) || empty($password) || empty($specialization)) {
        $errors[] = 'Please fill out all required fields.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address.';
    }

    if (empty($errors)) {
        $db = Database::getConnection();

        // Check unique
        $stmtChk = $db->prepare("SELECT id FROM users WHERE email = ? OR phone = ? LIMIT 1");
        $stmtChk->execute([$email, $phone]);
        if ($stmtChk->fetch()) {
            $errors[] = 'A user with this email or phone number already exists.';
        } else {
            try {
                $db->beginTransaction();

                // 1. Create user
                $stmtU = $db->prepare("
                    INSERT INTO users (name, email, phone, password_hash, role)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmtU->execute([$name, $email, $phone, hashPassword($password), ROLE_DOCTOR]);
                $userId = (int)$db->lastInsertId();

                // 2. Create doctor profile
                $stmtD = $db->prepare("
                    INSERT INTO doctors (user_id, specialization, consultation_fee, avg_consultation_mins, bio)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmtD->execute([$userId, $specialization, $fee, $avgMins, $bio]);
                $doctorId = (int)$db->lastInsertId();

                // 3. Create default schedules (Mon to Fri 09:00 - 17:00)
                $stmtS = $db->prepare("
                    INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration, is_active)
                    VALUES (?, ?, '09:00:00', '17:00:00', ?, 1)
                ");
                for ($d = 1; $d <= 5; $d++) {
                    $stmtS->execute([$doctorId, $d, $avgMins]);
                }

                $db->commit();

                setFlash('success', "Dr. {$name} successfully registered with default Mon-Fri working schedule.");
                header('Location: ' . url('admin/doctors.php'));
                exit;
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $errors[] = 'Error saving doctor: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = "Register Doctor - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:750px; margin:0 auto 3rem;">
    <div style="margin-bottom:1.5rem;">
        <a href="<?= url('admin/doctors.php') ?>" style="font-size:0.875rem; color:#64748b;">&larr; Back to Doctor Roster</a>
        <h1 style="font-size:1.75rem; font-weight:800; color:#0f172a; margin-top:0.35rem;">
            Register New Doctor Account
        </h1>
        <p style="color:#64748b; font-size:0.9rem; margin:0;">
            Creates medical specialist credentials and initializes consultation schedule
        </p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul style="margin:0; padding-left:1.25rem;">
                <?php foreach ($errors as $e): ?>
                    <li><?= e($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="">
            <?= csrfField() ?>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label" for="name">Doctor Full Name (with Title) *</label>
                    <input type="text" id="name" name="name" class="form-control" required placeholder="e.g. Dr. Alex Mercer" value="<?= e($name) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="specialization">Medical Specialization *</label>
                    <input type="text" id="specialization" name="specialization" class="form-control" required placeholder="e.g. Neurology, Cardiology..." value="<?= e($specialization) ?>">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label" for="email">Doctor Email (Login ID) *</label>
                    <input type="email" id="email" name="email" class="form-control" required placeholder="dr.alex@doctorconnect.com" value="<?= e($email) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Direct Phone *</label>
                    <input type="tel" id="phone" name="phone" class="form-control" required placeholder="+1 555 999 8888" value="<?= e($phone) ?>">
                </div>
            </div>

            <div class="grid-3">
                <div class="form-group">
                    <label class="form-label" for="password">Account Password *</label>
                    <input type="password" id="password" name="password" class="form-control" required placeholder="Min 6 characters">
                </div>

                <div class="form-group">
                    <label class="form-label" for="consultation_fee">Consultation Fee ($) *</label>
                    <input type="number" step="0.5" id="consultation_fee" name="consultation_fee" class="form-control" required value="<?= e($fee) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="avg_consultation_mins">Avg Duration (Mins) *</label>
                    <input type="number" id="avg_consultation_mins" name="avg_consultation_mins" class="form-control" required value="<?= e($avgMins) ?>" min="5" max="60" step="5">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="bio">Professional Bio & Credentials (Optional)</label>
                <textarea id="bio" name="bio" rows="3" class="form-control" placeholder="Board certifications, fellowship, clinical focus..."><?= e($bio) ?></textarea>
            </div>

            <div style="margin-top:1.5rem; text-align:right;">
                <button type="submit" class="btn btn-teal btn-lg">
                    + Register Doctor & Create Schedule &rarr;
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

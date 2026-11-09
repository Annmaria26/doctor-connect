<?php
// ==============================================================================
// DOCTOR CONNECT - EDIT DOCTOR PROFILE (ADMIN)
// ==============================================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_ADMIN);

$doctorId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$db = Database::getConnection();

$stmt = $db->prepare("
    SELECT d.*, u.name, u.email, u.phone 
    FROM doctors d
    JOIN users u ON u.id = d.user_id
    WHERE d.id = ?
");
$stmt->execute([$doctorId]);
$doctor = $stmt->fetch();

if (!$doctor) {
    setFlash('danger', 'Doctor not found.');
    header('Location: ' . url('admin/doctors.php'));
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie();

    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $fee = (float)($_POST['consultation_fee'] ?? 50.00);
    $avgMins = max(5, (int)($_POST['avg_consultation_mins'] ?? 15));
    $bio = trim($_POST['bio'] ?? '');

    if (empty($name) || empty($specialization)) {
        $errors[] = 'Please fill out all required fields.';
    } else {
        try {
            $db->beginTransaction();

            // Update user name/phone
            $stmtU = $db->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
            $stmtU->execute([$name, $phone, $doctor['user_id']]);

            // Update doctor profile
            $stmtD = $db->prepare("
                UPDATE doctors 
                SET specialization = ?, consultation_fee = ?, avg_consultation_mins = ?, bio = ?
                WHERE id = ?
            ");
            $stmtD->execute([$specialization, $fee, $avgMins, $bio, $doctorId]);

            $db->commit();

            setFlash('success', "Profile for {$name} updated successfully.");
            header('Location: ' . url('admin/doctors.php'));
            exit;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $errors[] = 'Error updating doctor: ' . $e->getMessage();
        }
    }
}

$pageTitle = "Edit Doctor: " . $doctor['name'] . " - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:750px; margin:0 auto 3rem;">
    <div style="margin-bottom:1.5rem;">
        <a href="<?= url('admin/doctors.php') ?>" style="font-size:0.875rem; color:#64748b;">&larr; Back to Doctor Roster</a>
        <h1 style="font-size:1.75rem; font-weight:800; color:#0f172a; margin-top:0.35rem;">
            Edit Doctor Profile
        </h1>
        <p style="color:#64748b; font-size:0.9rem; margin:0;">
            Update credentials, consultation rates, and timing parameters
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
                    <label class="form-label" for="name">Doctor Name *</label>
                    <input type="text" id="name" name="name" class="form-control" required value="<?= e($doctor['name']) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="specialization">Specialization *</label>
                    <input type="text" id="specialization" name="specialization" class="form-control" required value="<?= e($doctor['specialization']) ?>">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Email Address (Read-Only)</label>
                    <input type="email" class="form-control" value="<?= e($doctor['email']) ?>" disabled style="background:#f1f5f9;">
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-control" value="<?= e($doctor['phone']) ?>">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label" for="consultation_fee">Consultation Fee ($) *</label>
                    <input type="number" step="0.5" id="consultation_fee" name="consultation_fee" class="form-control" required value="<?= e($doctor['consultation_fee']) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="avg_consultation_mins">Avg Duration (Mins) *</label>
                    <input type="number" id="avg_consultation_mins" name="avg_consultation_mins" class="form-control" required value="<?= (int)$doctor['avg_consultation_mins'] ?>" min="5" max="60" step="5">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="bio">Bio & Credentials</label>
                <textarea id="bio" name="bio" rows="3" class="form-control"><?= e($doctor['bio']) ?></textarea>
            </div>

            <div style="margin-top:1.5rem; text-align:right;">
                <button type="submit" class="btn btn-primary btn-lg">
                    💾 Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

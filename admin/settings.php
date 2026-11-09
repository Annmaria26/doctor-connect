<?php
// ==============================================================================
// DOCTOR CONNECT - CLINIC SETTINGS & PLATFORM CONFIGURATION (ADMIN)
// ==============================================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_ADMIN);

$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie();

    $settings = [
        'clinic_name' => trim($_POST['clinic_name'] ?? 'DoctorConnect Clinic'),
        'clinic_tagline' => trim($_POST['clinic_tagline'] ?? ''),
        'clinic_phone' => trim($_POST['clinic_phone'] ?? ''),
        'clinic_email' => trim($_POST['clinic_email'] ?? ''),
        'clinic_address' => trim($_POST['clinic_address'] ?? ''),
        'default_slot_duration' => max(5, (int)($_POST['default_slot_duration'] ?? 15)),
        'default_consultation_fee' => (float)($_POST['default_consultation_fee'] ?? 50.00),
        'jitsi_domain' => trim($_POST['jitsi_domain'] ?? 'meet.jit.si'),
        'allow_cancel_hours' => max(0, (int)($_POST['allow_cancel_hours'] ?? 2))
    ];

    $stmtUpsert = $db->prepare("
        INSERT INTO clinic_settings (setting_key, setting_value) 
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");

    foreach ($settings as $key => $val) {
        $stmtUpsert->execute([$key, (string)$val]);
    }

    setFlash('success', 'Clinic configuration updated successfully.');
    header('Location: ' . url('admin/settings.php'));
    exit;
}

// Fetch current settings
$currentSettings = [];
try {
    $stmt = $db->query("SELECT setting_key, setting_value FROM clinic_settings");
    while ($r = $stmt->fetch()) {
        $currentSettings[$r['setting_key']] = $r['setting_value'];
    }
} catch (Exception $e) {}

$pageTitle = "Clinic Settings - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:800px; margin:0 auto 3rem;">
    <div style="margin-bottom:1.5rem;">
        <a href="<?= url('admin/dashboard.php') ?>" style="font-size:0.875rem; color:#64748b;">&larr; Back to Admin Overview</a>
        <h1 style="font-size:1.75rem; font-weight:800; color:#0f172a; margin-top:0.35rem;">
            Clinic Profile & Telehealth Parameters
        </h1>
        <p style="color:#64748b; font-size:0.9rem; margin:0;">
            Configure practice information, letterhead branding, and consultation policies
        </p>
    </div>

    <div class="card">
        <form method="POST" action="">
            <?= csrfField() ?>

            <h3 style="font-size:1.15rem; font-weight:700; color:#0f172a; margin-bottom:1rem; border-bottom:1px solid #f1f5f9; padding-bottom:0.5rem;">
                Clinic Branding & Letterhead
            </h3>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label" for="clinic_name">Clinic / Hospital Name *</label>
                    <input type="text" id="clinic_name" name="clinic_name" class="form-control" required value="<?= e($currentSettings['clinic_name'] ?? 'DoctorConnect TeleHealth Clinic') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="clinic_tagline">Motto / Tagline</label>
                    <input type="text" id="clinic_tagline" name="clinic_tagline" class="form-control" value="<?= e($currentSettings['clinic_tagline'] ?? 'Instant Online Token & Live Queue Consultation') ?>">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label" for="clinic_phone">Official Telephone</label>
                    <input type="tel" id="clinic_phone" name="clinic_phone" class="form-control" value="<?= e($currentSettings['clinic_phone'] ?? '+1 (800) 555-0199') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="clinic_email">Support / Contact Email</label>
                    <input type="email" id="clinic_email" name="clinic_email" class="form-control" value="<?= e($currentSettings['clinic_email'] ?? 'care@doctorconnect.com') ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="clinic_address">Physical Address</label>
                <input type="text" id="clinic_address" name="clinic_address" class="form-control" value="<?= e($currentSettings['clinic_address'] ?? '450 Healthtech Blvd, Suite 300, Silicon Valley, CA') ?>">
            </div>

            <h3 style="font-size:1.15rem; font-weight:700; color:#0f172a; margin-top:2rem; margin-bottom:1rem; border-bottom:1px solid #f1f5f9; padding-bottom:0.5rem;">
                Queue & Telehealth Operational Defaults
            </h3>

            <div class="grid-3">
                <div class="form-group">
                    <label class="form-label" for="default_slot_duration">Default Slot Duration (mins)</label>
                    <input type="number" id="default_slot_duration" name="default_slot_duration" class="form-control" value="<?= e($currentSettings['default_slot_duration'] ?? '15') ?>" min="5" max="60" step="5">
                </div>

                <div class="form-group">
                    <label class="form-label" for="default_consultation_fee">Default Consultation Fee ($)</label>
                    <input type="number" step="0.5" id="default_consultation_fee" name="default_consultation_fee" class="form-control" value="<?= e($currentSettings['default_consultation_fee'] ?? '50.00') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="allow_cancel_hours">Cancel Window (hours)</label>
                    <input type="number" id="allow_cancel_hours" name="allow_cancel_hours" class="form-control" value="<?= e($currentSettings['allow_cancel_hours'] ?? '2') ?>" min="0" max="48">
                    <div class="form-text">Cannot cancel within this window.</div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="jitsi_domain">Video Platform Domain</label>
                <input type="text" id="jitsi_domain" name="jitsi_domain" class="form-control" value="<?= e($currentSettings['jitsi_domain'] ?? 'meet.jit.si') ?>" placeholder="meet.jit.si">
                <div class="form-text">Free public Jitsi instance or private self-hosted Jitsi Meet domain.</div>
            </div>

            <div style="margin-top:2rem; text-align:right;">
                <button type="submit" class="btn btn-primary btn-lg">
                    💾 Save Clinic Settings
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

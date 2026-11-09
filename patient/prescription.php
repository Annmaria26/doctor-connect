<?php
// ==============================================================================
// DOCTOR CONNECT - PRESCRIPTION VIEW & PRINTABLE FORMAT
// Clean medical letterhead layout with instant window.print() formatting
// ==============================================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$appointmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$db = Database::getConnection();

$stmt = $db->prepare("
    SELECT a.*, 
           u.name as patient_name, u.email as patient_email, u.phone as patient_phone,
           du.name as doctor_name, du.email as doctor_email,
           d.specialization, d.consultation_fee
    FROM appointments a
    JOIN users u ON u.id = a.patient_id
    JOIN doctors d ON d.id = a.doctor_id
    JOIN users du ON du.id = d.user_id
    WHERE a.id = ?
");
$stmt->execute([$appointmentId]);
$appt = $stmt->fetch();

if (!$appt) {
    die("Prescription not found.");
}

// Access check: must be the patient, or a doctor, or an admin
if (isPatient() && $appt['patient_id'] != currentUserId()) {
    die("Unauthorized access to this prescription.");
}

$clinicName = getClinicSetting($db, 'clinic_name', 'DoctorConnect TeleHealth Clinic');
$clinicPhone = getClinicSetting($db, 'clinic_phone', '+1 (800) 555-0199');
$clinicEmail = getClinicSetting($db, 'clinic_email', 'care@doctorconnect.com');
$clinicAddress = getClinicSetting($db, 'clinic_address', '450 Healthtech Blvd, Suite 300, CA');

$pageTitle = "Prescription - Token #" . $appt['token_number'] . " - " . $appt['patient_name'];
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:850px; margin:1rem auto 3rem;">
    <!-- TOOLBAR -->
    <div class="no-print" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
        <a href="javascript:history.back()" class="btn btn-outline btn-sm">&larr; Back</a>
        <button type="button" class="btn btn-primary" onclick="window.print()">
            🖨 Print / Save as PDF
        </button>
    </div>

    <!-- FORMAL CLINICAL PRESCRIPTION SHEET -->
    <div class="prescription-sheet">
        <!-- HEADER -->
        <div class="rx-header">
            <div>
                <div style="font-size:1.4rem; font-weight:800; color:#0f172a;"><?= e($clinicName) ?></div>
                <div style="font-size:0.85rem; color:#64748b; margin-top:2px;"><?= e($clinicAddress) ?></div>
                <div style="font-size:0.85rem; color:#64748b;">Phone: <?= e($clinicPhone) ?> &bull; Email: <?= e($clinicEmail) ?></div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:1.15rem; font-weight:700; color:#0284c7;"><?= e($appt['doctor_name']) ?></div>
                <div style="font-size:0.85rem; font-weight:600; color:#0f766e;"><?= e($appt['specialization']) ?></div>
                <div style="font-size:0.8rem; color:#64748b;"><?= e($appt['doctor_email']) ?></div>
            </div>
        </div>

        <!-- PATIENT & APPOINTMENT META -->
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:1rem 1.25rem; margin-bottom:1.5rem; font-size:0.9rem;">
            <div class="grid-3" style="margin-bottom:0.5rem;">
                <div>
                    <span style="color:#64748b; font-size:0.8rem; display:block;">PATIENT NAME</span>
                    <strong><?= e($appt['patient_name']) ?></strong>
                </div>
                <div>
                    <span style="color:#64748b; font-size:0.8rem; display:block;">CONTACT</span>
                    <strong><?= e($appt['patient_phone'] ?: $appt['patient_email']) ?></strong>
                </div>
                <div>
                    <span style="color:#64748b; font-size:0.8rem; display:block;">DATE & TOKEN</span>
                    <strong><?= date('M d, Y', strtotime($appt['appointment_date'])) ?> &bull; Token #<?= (int)$appt['token_number'] ?></strong>
                </div>
            </div>
            <?php if (!empty($appt['reason'])): ?>
                <div style="border-top:1px solid #e2e8f0; padding-top:0.5rem; margin-top:0.5rem; font-size:0.85rem;">
                    <span style="color:#64748b;">Chief Complaint / Visit Reason:</span>
                    <span style="color:#334155; font-weight:500;"><?= e($appt['reason']) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- CLINICAL DIAGNOSIS -->
        <div style="margin-bottom:1.75rem;">
            <h4 style="font-size:0.95rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:#475569; margin-bottom:0.5rem;">
                Clinical Diagnosis
            </h4>
            <div style="background:#ffffff; border-left:4px solid #0284c7; padding:0.6rem 1rem; font-size:1rem; color:#0f172a; font-weight:600;">
                <?= !empty($appt['diagnosis']) ? nl2br(e($appt['diagnosis'])) : 'General Consultation / Clinical Assessment' ?>
            </div>
        </div>

        <!-- RX MEDICAL PRESCRIPTION -->
        <div style="margin-bottom:2.5rem;">
            <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.75rem;">
                <span class="rx-symbol">℞</span>
                <span style="font-size:1rem; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:0.05em;">Medications & Instructions</span>
            </div>

            <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; padding:1.25rem; font-family:monospace, sans-serif; font-size:0.95rem; line-height:1.7; white-space:pre-wrap; color:#1e293b;">
<?= !empty($appt['prescription']) ? e($appt['prescription']) : 'No specific pharmaceutical prescriptions recorded for this visit.' ?>
            </div>
        </div>

        <!-- FOOTER & DIGITAL SIGNATURE -->
        <div style="border-top:2px solid #e2e8f0; padding-top:1.5rem; display:flex; justify-content:space-between; align-items:flex-end;">
            <div style="font-size:0.75rem; color:#94a3b8; max-width:380px;">
                * This is a digitally generated telehealth prescription issued via the DoctorConnect platform. Verified online consultation.
            </div>
            <div style="text-align:right;">
                <div style="font-family:serif; font-style:italic; font-size:1.2rem; color:#0f172a; border-bottom:1px solid #0f172a; padding-bottom:4px; display:inline-block; min-width:180px;">
                    <?= e($appt['doctor_name']) ?>
                </div>
                <div style="font-size:0.8rem; font-weight:600; color:#475569; margin-top:4px;">
                    Authorized Medical Practitioner
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

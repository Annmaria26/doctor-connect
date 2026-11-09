<?php
// ==============================================================================
// DOCTOR CONNECT - DOCTOR CONSULTATION STATION
// WebRTC Video Room + Live Clinical Examination & Prescription Builder
// ==============================================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_DOCTOR);

$appointmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$doctorId = (int)($_SESSION['doctor_id'] ?? 0);
$db = Database::getConnection();

// Fetch appointment & patient
$stmt = $db->prepare("
    SELECT a.*, u.name as patient_name, u.email as patient_email, u.phone as patient_phone
    FROM appointments a
    JOIN users u ON u.id = a.patient_id
    WHERE a.id = ? AND a.doctor_id = ?
");
$stmt->execute([$appointmentId, $doctorId]);
$appt = $stmt->fetch();

if (!$appt) {
    setFlash('danger', 'Consultation session not found.');
    header('Location: ' . url('doctor/queue.php'));
    exit;
}

// Automatically transition status to 'serving' if was 'waiting'
if ($appt['status'] === STATUS_WAITING) {
    $stmtServe = $db->prepare("UPDATE appointments SET status = ? WHERE id = ?");
    $stmtServe->execute([STATUS_SERVING, $appointmentId]);
    logQueueAction($db, $appointmentId, 'serving');
    $appt['status'] = STATUS_SERVING;
}

// Handle Form Submission: Complete Visit & Save Prescription
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie();

    $action = $_POST['submit_action'] ?? 'complete';

    if ($action === 'complete') {
        $diagnosis = trim($_POST['diagnosis'] ?? '');
        $prescription = trim($_POST['prescription'] ?? '');
        $customLink = trim($_POST['custom_meeting_link'] ?? '');

        $sql = "UPDATE appointments SET status = ?, diagnosis = ?, prescription = ?";
        $params = [STATUS_COMPLETED, $diagnosis, $prescription];

        if (!empty($customLink)) {
            $sql .= ", meeting_link = ?";
            $params[] = $customLink;
        }

        $sql .= " WHERE id = ?";
        $params[] = $appointmentId;

        $stmtUp = $db->prepare($sql);
        $stmtUp->execute($params);

        logQueueAction($db, $appointmentId, 'served');
        createNotification($db, $appt['patient_id'], "Dr. has completed your consultation and issued your digital prescription.");

        setFlash('success', "Consultation for Token #{$appt['token_number']} ({$appt['patient_name']}) completed.");
        header('Location: ' . url('doctor/queue.php'));
        exit;
    } elseif ($action === 'no_show') {
        $stmtNoShow = $db->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        $stmtNoShow->execute([STATUS_NO_SHOW, $appointmentId]);
        logQueueAction($db, $appointmentId, 'no_show');

        setFlash('warning', "Token #{$appt['token_number']} marked as No-Show.");
        header('Location: ' . url('doctor/queue.php'));
        exit;
    }
}

$pageTitle = "Consultation: Token #" . $appt['token_number'] . " (" . $appt['patient_name'] . ") - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:1240px; margin:0 auto 3rem;">
    <!-- HEADER -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; flex-wrap:wrap; gap:1rem;">
        <div>
            <div style="display:flex; align-items:center; gap:0.5rem;">
                <span class="badge badge-serving"><span class="pulse-dot"></span> LIVE CONSULTATION IN PROGRESS</span>
                <span style="font-weight:700; color:#0f172a; font-size:1.1rem;">Token #<?= (int)$appt['token_number'] ?></span>
            </div>
            <h1 style="font-size:1.6rem; font-weight:800; color:#0f172a; margin-top:0.25rem;">
                Patient: <?= e($appt['patient_name']) ?>
            </h1>
        </div>

        <div style="display:flex; gap:0.5rem;">
            <a href="<?= e($appt['meeting_link']) ?>" target="_blank" rel="noopener" class="btn btn-outline btn-sm">
                Open Video in Separate Window ↗
            </a>
            <a href="<?= url('doctor/queue.php') ?>" class="btn btn-outline btn-sm">
                &larr; Back to Queue Roster
            </a>
        </div>
    </div>

    <!-- MAIN 2-COLUMN WORKSTATION -->
    <div class="grid-2" style="grid-template-columns: 1.15fr 1fr; gap:1.5rem; align-items:start;">
        <!-- LEFT COLUMN: VIDEO ROOM -->
        <div>
            <div class="card" style="padding:0; overflow:hidden;">
                <div style="padding:0.75rem 1rem; background:#0f172a; color:white; display:flex; justify-content:space-between; align-items:center;">
                    <div style="font-size:0.85rem; font-weight:600; display:flex; align-items:center; gap:6px;">
                        <span class="pulse-dot" style="background:#10b981;"></span> Jitsi WebRTC Video Consultation
                    </div>
                    <span style="font-size:0.75rem; color:#94a3b8;">HD Audio & Video Encrypted</span>
                </div>

                <div style="height:540px; background:#000;">
                    <iframe 
                        src="<?= e($appt['meeting_link']) ?>#userInfo.displayName=Dr.+<?= urlencode(currentUser()['name']) ?>" 
                        allow="camera; microphone; fullscreen; display-capture; autoplay"
                        style="width:100%; height:100%; border:none;"
                    ></iframe>
                </div>
            </div>

            <!-- CUSTOM MEETING LINK OVERRIDE ACCORDION -->
            <div class="card" style="margin-top:1rem; padding:1rem 1.25rem;">
                <details>
                    <summary style="font-size:0.875rem; font-weight:600; color:#475569; cursor:pointer;">
                        ⚙️ Optional: Use Zoom or Google Meet Link Instead
                    </summary>
                    <div style="margin-top:0.75rem;">
                        <label class="form-label" style="font-size:0.8rem;">Custom Video Meeting Link</label>
                        <input type="url" name="custom_meeting_link" form="rx-form" class="form-control" value="<?= e($appt['meeting_link']) ?>" placeholder="https://meet.google.com/... or https://zoom.us/j/...">
                        <div class="form-text">If entered, patient will be redirected to this link.</div>
                    </div>
                </details>
            </div>
        </div>

        <!-- RIGHT COLUMN: PATIENT INFO & PRESCRIPTION WRITER -->
        <div>
            <!-- PATIENT SUMMARY -->
            <div class="card" style="margin-bottom:1.25rem; padding:1.25rem;">
                <h3 style="font-size:1.05rem; font-weight:700; color:#0f172a; margin-bottom:0.75rem;">
                    Patient Clinical Profile
                </h3>
                <div class="grid-2" style="font-size:0.875rem; line-height:1.6; margin-bottom:0.75rem;">
                    <div>
                        <span style="color:#64748b;">Phone:</span><br>
                        <strong><?= e($appt['patient_phone']) ?></strong>
                    </div>
                    <div>
                        <span style="color:#64748b;">Email:</span><br>
                        <strong><?= e($appt['patient_email']) ?></strong>
                    </div>
                </div>

                <div style="background:#f8fafc; border-left:3px solid #0284c7; padding:0.6rem 0.9rem; border-radius:4px; font-size:0.85rem;">
                    <strong>Reported Symptoms / Chief Complaint:</strong><br>
                    <span style="color:#334155;"><?= e($appt['reason'] ?: 'No specific complaint entered.') ?></span>
                </div>
            </div>

            <!-- PRESCRIPTION BUILDER FORM -->
            <div class="card" style="padding:1.5rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                    <h3 style="font-size:1.15rem; font-weight:700; color:#0f172a; margin:0;">
                        Clinical Notes & Prescription
                    </h3>
                    <span class="rx-symbol" style="font-size:1.8rem;">℞</span>
                </div>

                <form id="rx-form" method="POST" action="">
                    <?= csrfField() ?>

                    <div class="form-group">
                        <label class="form-label" for="diagnosis">Clinical Diagnosis / Impression *</label>
                        <input type="text" id="diagnosis" name="diagnosis" class="form-control" required placeholder="e.g. Acute upper respiratory tract infection / Allergic dermatitis" value="<?= e($appt['diagnosis'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="prescription">Medications & Rx Instructions *</label>
                        <textarea id="prescription" name="prescription" rows="7" class="form-control" style="font-family:monospace; font-size:0.9rem; line-height:1.5;" required placeholder="1. Amoxicillin 500mg - 1 capsule TID after food x 5 days
2. Paracetamol 650mg - 1 tablet SOS for fever
3. Adequate hydration & rest
4. Follow-up review in 5 days"><?= e($appt['prescription'] ?? '') ?></textarea>
                        <div class="form-text">Include medication name, dosage strength, frequency, and duration.</div>
                    </div>

                    <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
                        <button type="submit" name="submit_action" value="complete" class="btn btn-primary btn-lg" style="flex:1;">
                            ✓ Save Rx & End Consultation
                        </button>
                        <button type="submit" name="submit_action" value="no_show" class="btn btn-outline btn-sm" onclick="return confirm('Mark this patient as No-Show?')">
                            No-Show
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

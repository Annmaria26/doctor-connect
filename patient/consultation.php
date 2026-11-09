<?php
// ==============================================================================
// DOCTOR CONNECT - PATIENT VIDEO CONSULTATION ROOM
// High-Definition WebRTC Video Room via Jitsi Meet
// ==============================================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_PATIENT);

$appointmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$patientId = currentUserId();
$db = Database::getConnection();

$stmt = $db->prepare("
    SELECT a.*, d.specialization, d.consultation_fee, du.name as doctor_name
    FROM appointments a
    JOIN doctors d ON d.id = a.doctor_id
    JOIN users du ON du.id = d.user_id
    WHERE a.id = ? AND a.patient_id = ?
");
$stmt->execute([$appointmentId, $patientId]);
$appt = $stmt->fetch();

if (!$appt) {
    setFlash('danger', 'Consultation room not found.');
    header('Location: ' . url('patient/dashboard.php'));
    exit;
}

// Check current queue position to verify access rule
$queue = getDoctorLiveQueue($db, $appt['doctor_id'], $appt['appointment_date']);
if (!isMeetingLinkUnlockedForPatient($appt, $queue['current_serving_token'], $queue['next_waiting_token'])) {
    setFlash('warning', 'The video room is only accessible when your token is NEXT or SERVING. Please wait in the queue.');
    header('Location: ' . url('patient/queue.php?id=' . $appt['id']));
    exit;
}

$pageTitle = "Video Consultation with " . $appt['doctor_name'] . " - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:1100px; margin:0 auto 2rem;">
    <!-- HEADER -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:1rem;">
        <div>
            <div style="display:flex; align-items:center; gap:0.5rem;">
                <span class="badge badge-serving"><span class="pulse-dot"></span> LIVE CONSULTATION</span>
                <span style="font-weight:700; color:#0f172a;">Token #<?= (int)$appt['token_number'] ?></span>
            </div>
            <h1 style="font-size:1.5rem; font-weight:800; color:#0f172a; margin-top:0.25rem;">
                Consultation with <?= e($appt['doctor_name']) ?>
            </h1>
            <p style="color:#64748b; font-size:0.875rem; margin:0;">
                <?= e($appt['specialization']) ?> &bull; Please ensure your microphone and camera permissions are granted.
            </p>
        </div>

        <div style="display:flex; gap:0.5rem;">
            <a href="<?= e($appt['meeting_link']) ?>" target="_blank" rel="noopener" class="btn btn-outline btn-sm">
                Open in Full Window ↗
            </a>
            <a href="<?= url('patient/queue.php?id=' . $appt['id']) ?>" class="btn btn-outline btn-sm">
                Queue Board
            </a>
        </div>
    </div>

    <!-- JITSI WEBRTC VIDEO CONTAINER -->
    <div class="video-frame-container" style="position:relative;">
        <iframe 
            src="<?= e($appt['meeting_link']) ?>#userInfo.displayName=<?= urlencode(currentUser()['name']) ?>" 
            allow="camera; microphone; fullscreen; display-capture; autoplay"
            style="width:100%; height:100%; border:none;"
            id="jitsi-iframe"
        ></iframe>
    </div>

    <!-- BOTTOM CONSULTATION INFO -->
    <div class="card" style="margin-top:1.25rem; background:#ffffff;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
            <div>
                <h4 style="color:#0f172a; margin-bottom:0.25rem; font-size:1rem;">Appointment Details</h4>
                <div style="font-size:0.875rem; color:#64748b;">
                    <strong>Reason for Consultation:</strong> <?= e($appt['reason'] ?: 'General Consultation') ?>
                </div>
            </div>
            <div style="display:flex; gap:0.75rem;">
                <a href="<?= url('patient/dashboard.php') ?>" class="btn btn-primary btn-sm">
                    Done / Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

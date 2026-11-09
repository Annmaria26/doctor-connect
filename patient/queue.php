<?php
// ==============================================================================
// DOCTOR CONNECT - PATIENT LIVE QUEUE BOARD & WAITING ROOM
// Live sub-3s polling with Web Audio chime and dynamic video link activation
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

if ($appointmentId <= 0) {
    // If no ID passed, try to find today's active appointment for this patient
    $stmtFind = $db->prepare("
        SELECT id FROM appointments 
        WHERE patient_id = ? AND appointment_date = CURRENT_DATE() AND status IN ('waiting', 'serving')
        ORDER BY id DESC LIMIT 1
    ");
    $stmtFind->execute([$patientId]);
    $found = $stmtFind->fetch();
    if ($found) {
        $appointmentId = (int)$found['id'];
    } else {
        setFlash('info', 'No active appointment found for today. Please book a token.');
        header('Location: ' . url('patient/book.php'));
        exit;
    }
}

// Fetch appointment details
$stmt = $db->prepare("
    SELECT a.*, d.specialization, d.consultation_fee, d.avg_consultation_mins, du.name as doctor_name
    FROM appointments a
    JOIN doctors d ON d.id = a.doctor_id
    JOIN users du ON du.id = d.user_id
    WHERE a.id = ? AND a.patient_id = ?
");
$stmt->execute([$appointmentId, $patientId]);
$appt = $stmt->fetch();

if (!$appt) {
    setFlash('danger', 'Appointment not found or unauthorized.');
    header('Location: ' . url('patient/dashboard.php'));
    exit;
}

// Get initial queue state for server-side render
$queue = getDoctorLiveQueue($db, $appt['doctor_id'], $appt['appointment_date']);
$canJoinInitial = isMeetingLinkUnlockedForPatient($appt, $queue['current_serving_token'], $queue['next_waiting_token']);

// Calculate tokens ahead initially
$tokensAhead = 0;
if ($appt['status'] === STATUS_WAITING) {
    $stmtAhead = $db->prepare("
        SELECT COUNT(*) 
        FROM appointments 
        WHERE doctor_id = ? AND appointment_date = ? AND status = ? AND token_number < ?
    ");
    $stmtAhead->execute([$appt['doctor_id'], $appt['appointment_date'], STATUS_WAITING, $appt['token_number']]);
    $tokensAhead = (int)$stmtAhead->fetchColumn();
}

$pageTitle = "Token #" . $appt['token_number'] . " Live Queue - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:960px; margin:0 auto 3rem;">
    <!-- BREADCRUMB & HEADER -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
        <div>
            <a href="<?= url('patient/dashboard.php') ?>" style="font-size:0.875rem; color:#64748b;">&larr; Back to Dashboard</a>
            <h1 style="font-size:1.75rem; font-weight:800; color:#0f172a; margin-top:0.35rem;">
                Live Consultation Waiting Room
            </h1>
            <p style="color:#64748b; font-size:0.9rem; margin:0;">
                Doctor: <strong><?= e($appt['doctor_name']) ?></strong> &bull; <?= e($appt['specialization']) ?> &bull; <?= date('M d, Y', strtotime($appt['appointment_date'])) ?>
            </p>
        </div>

        <div style="display:flex; align-items:center; gap:0.75rem;">
            <div style="display:flex; align-items:center; gap:6px; background:#ffffff; border:1px solid #cbd5e1; padding:6px 12px; border-radius:999px; font-size:0.8rem; font-weight:600; color:#334155;">
                <span class="pulse-dot"></span> Live Queue Active (3s Sync)
            </div>
            <button type="button" class="btn btn-outline btn-sm" onclick="queueManager.playChime()" title="Test chime audio">
                🔔 Test Sound
            </button>
        </div>
    </div>

    <!-- MAIN HERO QUEUE CARD -->
    <div class="queue-hero-card">
        <div style="font-size:0.85rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; opacity:0.9;">
            Your Assigned Consultation Token
        </div>

        <div class="token-circle-badge">
            <div class="token-lbl">TOKEN</div>
            <div class="token-num">#<?= (int)$appt['token_number'] ?></div>
        </div>

        <div style="display:flex; gap:1rem; align-items:center; margin-bottom:1rem;">
            <span id="lq-my-status" class="badge badge-<?= e($appt['status']) ?>" style="font-size:0.9rem; padding:0.4rem 1rem;">
                <?= strtoupper(e($appt['status'])) ?>
                <?php if ($appt['status'] === STATUS_SERVING): ?><span class="pulse-dot"></span><?php endif; ?>
            </span>
        </div>

        <div style="max-width:550px; font-size:0.95rem; opacity:0.95;">
            Estimated Meeting Time: <strong><?= date('h:i A', strtotime($appt['estimated_time'])) ?></strong>
        </div>
    </div>

    <!-- 3 LIVE COUNTERS -->
    <div class="stats-grid">
        <div class="stat-card stat-success">
            <div class="stat-icon">👨‍⚕️</div>
            <div>
                <div id="lq-current-serving" class="stat-val" style="color:#059669;">
                    <?= $queue['current_serving_token'] ? '#' . $queue['current_serving_token'] : 'None' ?>
                </div>
                <div class="stat-label">Currently Serving Token</div>
            </div>
        </div>

        <div class="stat-card stat-warning">
            <div class="stat-icon">⏭</div>
            <div>
                <div id="lq-next-token" class="stat-val" style="color:#d97706;">
                    <?= $queue['next_waiting_token'] ? '#' . $queue['next_waiting_token'] : 'None' ?>
                </div>
                <div class="stat-label">Next in Line</div>
            </div>
        </div>

        <div class="stat-card stat-primary">
            <div class="stat-icon">⏳</div>
            <div>
                <div id="lq-tokens-ahead" class="stat-val" style="color:#0284c7;">
                    <?= $tokensAhead ?>
                </div>
                <div class="stat-label">Tokens Ahead of You</div>
            </div>
        </div>
    </div>

    <!-- DYNAMIC VIDEO CONSULTATION MEETING CONTAINER (CRITICAL REQUIREMENT 3.4) -->
    <div id="lq-meeting-container" class="meeting-banner <?= $canJoinInitial ? 'meeting-unlocked' : 'meeting-locked' ?>">
        <?php if ($canJoinInitial): ?>
            <div style="font-size:2rem; margin-bottom:0.5rem;">🎉</div>
            <h3 style="color:#065f46; margin-bottom:0.4rem; font-size:1.3rem;">It's Your Turn! Video Consultation Ready</h3>
            <p style="color:#047857; margin-bottom:1rem; max-width:500px;">
                The doctor is ready or preparing for your consultation. Please join the video room now.
            </p>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap; justify-content:center;">
                <a href="<?= url('patient/consultation.php?id=' . $appt['id']) ?>" class="btn btn-teal btn-lg" style="box-shadow:0 4px 12px rgba(13,148,136,0.3);">
                    📹 Enter Video Consultation Room
                </a>
                <a href="<?= e($appt['meeting_link']) ?>" target="_blank" rel="noopener" class="btn btn-outline btn-lg">
                    Open in New Tab ↗
                </a>
            </div>
        <?php elseif ($appt['status'] === STATUS_COMPLETED): ?>
            <h4 style="color:#334155; margin-bottom:0.5rem;">Consultation Completed</h4>
            <p style="color:#64748b; margin-bottom:1rem;">Your consultation with Dr. <?= e($appt['doctor_name']) ?> has concluded.</p>
            <a href="<?= url('patient/prescription.php?id=' . $appt['id']) ?>" class="btn btn-primary">
                📄 View / Print Prescription
            </a>
        <?php else: ?>
            <div style="font-size:1.8rem; margin-bottom:0.5rem;">🔒</div>
            <h3 style="color:#334155; margin-bottom:0.4rem; font-size:1.15rem;">Video Consultation Room Locked</h3>
            <p style="color:#64748b; margin-bottom:0; max-width:550px; font-size:0.9rem;">
                Per clinic privacy protocols, your video meeting room unlocks automatically when your token is <strong>NEXT</strong> or <strong>SERVING</strong>.
                You will hear an alert chime and this box will turn green when ready.
            </p>
        <?php endif; ?>
    </div>

    <!-- TODAY'S FULL QUEUE LIST (FOR TRANSPARENCY) -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Today's Doctor Queue Board</h3>
            <span style="font-size:0.85rem; color:#64748b;">
                Doctor: <?= e($appt['doctor_name']) ?> (<?= $queue['total_tokens'] ?> tokens today)
            </span>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Token</th>
                        <th>Patient</th>
                        <th>Estimated Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="lq-table-body">
                    <?php foreach ($queue['tokens'] as $tok): ?>
                        <tr style="<?= ((int)$tok['id'] === (int)$appt['id']) ? 'background:#e0f2fe; font-weight:600;' : '' ?>">
                            <td>
                                <strong>#<?= (int)$tok['token_number'] ?></strong>
                                <?php if ((int)$tok['id'] === (int)$appt['id']): ?>
                                    <span class="badge" style="background:#0284c7; color:white; font-size:0.7rem; margin-left:4px;">YOU</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= ((int)$tok['id'] === (int)$appt['id']) ? e($tok['patient_name']) : (substr(e($tok['patient_name']), 0, 1) . '*** ' . substr(e($tok['patient_name']), -1)) ?>
                            </td>
                            <td><?= date('h:i A', strtotime($tok['estimated_time'])) ?></td>
                            <td>
                                <span class="badge badge-<?= e($tok['status']) ?>">
                                    <?= strtoupper(e($tok['status'])) ?>
                                    <?php if ($tok['status'] === STATUS_SERVING): ?><span class="pulse-dot"></span><?php endif; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="<?= asset('js/live_queue.js') ?>"></script>
<script>
const queueManager = new LiveQueueManager({
    doctorId: <?= (int)$appt['doctor_id'] ?>,
    appointmentId: <?= (int)$appt['id'] ?>,
    role: 'patient',
    apiUrl: '<?= url("api/live_queue.php") ?>',
    pollInterval: 3000
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

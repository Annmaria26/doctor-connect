<?php
// ==============================================================================
// DOCTOR CONNECT - PATIENT DASHBOARD
// ==============================================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_PATIENT);

$db = Database::getConnection();
$patientId = currentUserId();

// 1. Fetch active / today's appointments (waiting or serving)
$stmtActive = $db->prepare("
    SELECT a.*, d.specialization, d.consultation_fee, du.name as doctor_name
    FROM appointments a
    JOIN doctors d ON d.id = a.doctor_id
    JOIN users du ON du.id = d.user_id
    WHERE a.patient_id = ? AND a.status IN ('waiting', 'serving') AND a.appointment_date >= CURRENT_DATE()
    ORDER BY a.appointment_date ASC, a.token_number ASC
");
$stmtActive->execute([$patientId]);
$activeAppointments = $stmtActive->fetchAll();

// 2. Fetch past appointments (completed, no-show, cancelled)
$stmtPast = $db->prepare("
    SELECT a.*, d.specialization, du.name as doctor_name
    FROM appointments a
    JOIN doctors d ON d.id = a.doctor_id
    JOIN users du ON du.id = d.user_id
    WHERE a.patient_id = ? AND a.status NOT IN ('waiting', 'serving')
    ORDER BY a.appointment_date DESC, a.id DESC
    LIMIT 5
");
$stmtPast->execute([$patientId]);
$pastAppointments = $stmtPast->fetchAll();

// 3. Fetch recent notifications
$stmtNotifs = $db->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY id DESC 
    LIMIT 4
");
$stmtNotifs->execute([$patientId]);
$notifications = $stmtNotifs->fetchAll();

$pageTitle = "Patient Dashboard - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem; flex-wrap:wrap; gap:1rem;">
    <div>
        <h1 style="font-size:1.85rem; font-weight:800; color:#0f172a; margin-bottom:0.25rem;">
            Welcome, <?= e(currentUser()['name']) ?> 👋
        </h1>
        <p style="color:#64748b; font-size:0.95rem; margin:0;">
            Track your consultation tokens and join live video queues
        </p>
    </div>
    <a href="<?= url('patient/book.php') ?>" class="btn btn-primary btn-lg" style="box-shadow:0 6px 15px rgba(2,132,199,0.3);">
        + Book New Token
    </a>
</div>

<!-- NOTIFICATION BANNER IF UNREAD NOTIFICATIONS EXIST -->
<?php if (!empty($notifications)): ?>
    <div class="card" style="background:#f0fdf4; border:1px solid #bbf7d0; padding:1rem 1.25rem; margin-bottom:1.75rem;">
        <div style="display:flex; align-items:flex-start; gap:0.75rem;">
            <div style="font-size:1.2rem;">🔔</div>
            <div style="flex:1;">
                <strong style="color:#166534; font-size:0.9rem;">Latest System Updates:</strong>
                <ul style="margin:0.25rem 0 0 1.25rem; font-size:0.875rem; color:#14532d;">
                    <?php foreach ($notifications as $n): ?>
                        <li><?= e($n['message']) ?> <span style="color:#15803d; font-size:0.75rem;">(<?= date('M d, h:i A', strtotime($n['created_at'])) ?>)</span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ACTIVE APPOINTMENTS / LIVE TOKENS -->
<div style="margin-bottom:2.5rem;">
    <div class="card-header" style="margin-bottom:1rem;">
        <h2 class="card-title">Active Consultation Tokens</h2>
        <span class="badge" style="background:#e0f2fe; color:#0284c7; font-weight:700;">
            <?= count($activeAppointments) ?> Active
        </span>
    </div>

    <?php if (empty($activeAppointments)): ?>
        <div class="card" style="text-align:center; padding:3rem 1.5rem; background:#ffffff;">
            <div style="font-size:2.5rem; margin-bottom:1rem;">🎫</div>
            <h3 style="color:#0f172a; margin-bottom:0.5rem;">No Active Consultation Tokens</h3>
            <p style="color:#64748b; margin-bottom:1.5rem; max-width:400px; margin-left:auto; margin-right:auto;">
                You do not have any pending appointments for today. Book a doctor to receive your race-safe token number.
            </p>
            <a href="<?= url('patient/book.php') ?>" class="btn btn-teal">
                Find Doctor & Book Token
            </a>
        </div>
    <?php else: ?>
        <div class="grid-2">
            <?php foreach ($activeAppointments as $appt): ?>
                <div class="card" style="border:2px solid <?= $appt['status'] === 'serving' ? '#10b981' : '#e2e8f0' ?>; position:relative;">
                    <?php if ($appt['status'] === 'serving'): ?>
                        <div style="position:absolute; top:-12px; right:16px; background:#10b981; color:white; font-size:0.75rem; font-weight:700; padding:2px 10px; border-radius:999px; display:flex; align-items:center; gap:5px;">
                            <span class="pulse-dot" style="background:white;"></span> CURRENTLY SERVING
                        </div>
                    <?php endif; ?>

                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1rem;">
                        <div>
                            <span class="badge badge-<?= e($appt['status']) ?>" style="margin-bottom:0.4rem;">
                                <?= strtoupper(e($appt['status'])) ?>
                            </span>
                            <h3 style="font-size:1.2rem; font-weight:700; color:#0f172a;">
                                <?= e($appt['doctor_name']) ?>
                            </h3>
                            <div style="color:#0284c7; font-size:0.875rem; font-weight:600;">
                                <?= e($appt['specialization']) ?>
                            </div>
                        </div>

                        <!-- TOKEN NUMBER BADGE -->
                        <div style="text-align:center; background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:8px 14px; min-width:80px;">
                            <div style="font-size:0.7rem; font-weight:700; text-transform:uppercase; color:#64748b;">Token</div>
                            <div style="font-size:1.85rem; font-weight:800; color:#0284c7; line-height:1;">
                                #<?= (int)$appt['token_number'] ?>
                            </div>
                        </div>
                    </div>

                    <div style="background:#f8fafc; padding:0.75rem; border-radius:6px; font-size:0.875rem; margin-bottom:1.25rem;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:0.25rem;">
                            <span style="color:#64748b;">Date:</span>
                            <strong><?= date('D, M d, Y', strtotime($appt['appointment_date'])) ?></strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:0.25rem;">
                            <span style="color:#64748b;">Estimated Meeting Time:</span>
                            <strong style="color:#0f766e;"><?= date('h:i A', strtotime($appt['estimated_time'])) ?></strong>
                        </div>
                        <?php if (!empty($appt['reason'])): ?>
                            <div style="display:flex; justify-content:space-between;">
                                <span style="color:#64748b;">Reason:</span>
                                <span style="color:#334155;"><?= e($appt['reason']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div style="display:flex; gap:0.5rem;">
                        <a href="<?= url('patient/queue.php?id=' . $appt['id']) ?>" class="btn btn-primary" style="flex:1;">
                            🔴 Open Live Queue Board
                        </a>
                        <button type="button" class="btn btn-outline btn-sm" onclick="cancelToken(<?= $appt['id'] ?>)">
                            Cancel
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- RECENT CONSULTATION HISTORY -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Recent Completed & Past Consultations</h2>
        <a href="<?= url('patient/history.php') ?>" class="btn btn-outline btn-sm">View All History</a>
    </div>

    <?php if (empty($pastAppointments)): ?>
        <p style="color:#64748b; font-size:0.9rem; margin:0;">No past consultation records found.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Doctor</th>
                        <th>Token #</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pastAppointments as $past): ?>
                        <tr>
                            <td><?= date('M d, Y', strtotime($past['appointment_date'])) ?></td>
                            <td>
                                <strong><?= e($past['doctor_name']) ?></strong><br>
                                <span style="font-size:0.8rem; color:#64748b;"><?= e($past['specialization']) ?></span>
                            </td>
                            <td><strong style="color:#0284c7;">#<?= (int)$past['token_number'] ?></strong></td>
                            <td>
                                <span class="badge badge-<?= e($past['status']) ?>">
                                    <?= strtoupper(e($past['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($past['status'] === 'completed' && !empty($past['prescription'])): ?>
                                    <a href="<?= url('patient/prescription.php?id=' . $past['id']) ?>" class="btn btn-sm btn-outline">
                                        📄 Prescription
                                    </a>
                                <?php else: ?>
                                    <span style="color:#94a3b8; font-size:0.8rem;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
async function cancelToken(apptId) {
    if (!confirm('Are you sure you want to cancel this consultation token?')) return;
    try {
        const formData = new FormData();
        formData.append('appointment_id', apptId);

        const res = await fetch('<?= url("api/cancel_token.php") ?>', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            alert('Appointment cancelled.');
            window.location.reload();
        } else {
            alert(data.error || 'Could not cancel appointment.');
        }
    } catch (e) {
        alert('Network error while cancelling.');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

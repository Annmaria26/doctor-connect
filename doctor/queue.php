<?php
// ==============================================================================
// DOCTOR CONNECT - DOCTOR LIVE QUEUE CONSOLE
// Real-time queue controller with live polling & walk-in patient token issuance
// ==============================================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_DOCTOR);

$db = Database::getConnection();
$doctorId = (int)($_SESSION['doctor_id'] ?? 0);
$today = date('Y-m-d');

$queue = getDoctorLiveQueue($db, $doctorId, $today);

$pageTitle = "Doctor Live Queue Console - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:1150px; margin:0 auto 3rem;">
    <!-- HEADER -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
        <div>
            <a href="<?= url('doctor/dashboard.php') ?>" style="font-size:0.875rem; color:#64748b;">&larr; Back to Console</a>
            <h1 style="font-size:1.75rem; font-weight:800; color:#0f172a; margin-top:0.35rem;">
                Live Token Queue Controller
            </h1>
            <p style="color:#64748b; font-size:0.9rem; margin:0;">
                Today's consultations &bull; Real-time sync (3s) &bull; Race-safe token control
            </p>
        </div>

        <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
            <button type="button" class="btn btn-outline" onclick="openWalkinModal()">
                + Add Walk-In Patient
            </button>
            <button type="button" class="btn btn-primary btn-lg" onclick="handleQueueAction('call_next')">
                📢 Call Next Token &rarr;
            </button>
        </div>
    </div>

    <!-- 4 STATUS METRIC CARDS -->
    <div class="stats-grid">
        <div class="stat-card stat-success">
            <div class="stat-icon">👨‍⚕️</div>
            <div>
                <div id="lq-current-serving" class="stat-val" style="color:#059669;">
                    <?= $queue['current_serving_token'] ? '#' . $queue['current_serving_token'] : 'None' ?>
                </div>
                <div class="stat-label">Currently Serving</div>
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
                <div id="lq-waiting-count" class="stat-val" style="color:#0284c7;">
                    <?= $queue['waiting_count'] ?>
                </div>
                <div class="stat-label">Waiting in Line</div>
            </div>
        </div>

        <div class="stat-card stat-primary">
            <div class="stat-icon">✓</div>
            <div>
                <div id="lq-completed-count" class="stat-val" style="color:#475569;">
                    <?= $queue['completed_count'] ?>
                </div>
                <div class="stat-label">Served Today</div>
            </div>
        </div>
    </div>

    <!-- LIVE QUEUE TABLE -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Active Queue Roster (<?= count($queue['tokens']) ?> Patients)</h3>
            <span style="font-size:0.8rem; color:#64748b;">
                Auto-refreshes every 3 seconds &bull; Actions update queue audit logs
            </span>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:90px;">Token #</th>
                        <th>Patient Name</th>
                        <th>Est. Time</th>
                        <th>Status</th>
                        <th style="width:200px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="lq-table-body">
                    <?php foreach ($queue['tokens'] as $t): ?>
                        <tr style="<?= ($t['status'] === 'serving') ? 'background:#ecfdf5;' : '' ?>">
                            <td><strong style="font-size:1.1rem; color:#0284c7;">#<?= (int)$t['token_number'] ?></strong></td>
                            <td>
                                <strong><?= e($t['patient_name']) ?></strong><br>
                                <span style="font-size:0.8rem; color:#64748b;">Phone: <?= e($t['patient_phone']) ?></span>
                                <?php if (!empty($t['reason'])): ?>
                                    <div style="font-size:0.8rem; color:#475569; margin-top:2px;">Reason: <?= e($t['reason']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= date('h:i A', strtotime($t['estimated_time'])) ?></td>
                            <td>
                                <span class="badge badge-<?= e($t['status']) ?>">
                                    <?= strtoupper(e($t['status'])) ?>
                                    <?php if ($t['status'] === 'serving'): ?><span class="pulse-dot"></span><?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($t['status'] === 'waiting'): ?>
                                    <button class="btn btn-sm btn-primary" onclick="handleQueueAction('mark_serving', <?= $t['id'] ?>)">Call</button>
                                    <button class="btn btn-sm btn-outline" onclick="handleQueueAction('mark_no_show', <?= $t['id'] ?>)">No-Show</button>
                                <?php elseif ($t['status'] === 'serving'): ?>
                                    <a href="<?= url('doctor/consultation.php?id=' . $t['id']) ?>" class="btn btn-sm btn-teal">In Call 📹</a>
                                    <button class="btn btn-sm btn-outline" onclick="handleQueueAction('mark_served', <?= $t['id'] ?>)">Complete</button>
                                <?php elseif ($t['status'] === 'completed' && !empty($t['prescription'])): ?>
                                    <a href="<?= url('patient/prescription.php?id=' . $t['id']) ?>" target="_blank" class="btn btn-sm btn-outline">Rx Print</a>
                                <?php else: ?>
                                    <span style="color:#94a3b8; font-size:0.8rem;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- WALK-IN PATIENT MODAL -->
<div id="walkin-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:white; max-width:440px; width:90%; border-radius:12px; padding:1.75rem; box-shadow:0 20px 25px -5px rgba(0,0,0,0.2);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
            <h3 style="margin:0; font-size:1.25rem; font-weight:700; color:#0f172a;">Issue Walk-In Token</h3>
            <button type="button" onclick="closeWalkinModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#64748b;">&times;</button>
        </div>

        <form id="walkin-form" onsubmit="submitWalkin(event)">
            <div class="form-group">
                <label class="form-label" for="walkin_name">Patient Name *</label>
                <input type="text" id="walkin_name" name="patient_name" class="form-control" required placeholder="Full Name">
            </div>

            <div class="form-group">
                <label class="form-label" for="walkin_phone">Phone Number (Optional)</label>
                <input type="tel" id="walkin_phone" name="patient_phone" class="form-control" placeholder="+1 555 000 0000">
            </div>

            <div class="form-group">
                <label class="form-label" for="walkin_reason">Reason for Visit</label>
                <input type="text" id="walkin_reason" name="reason" class="form-control" placeholder="e.g. Acute headache, fever...">
            </div>

            <div style="display:flex; gap:0.5rem; justify-content:flex-end; margin-top:1.5rem;">
                <button type="button" class="btn btn-outline" onclick="closeWalkinModal()">Cancel</button>
                <button type="submit" class="btn btn-teal">Issue Token &rarr;</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= asset('js/live_queue.js') ?>"></script>
<script>
const queueManager = new LiveQueueManager({
    doctorId: <?= $doctorId ?>,
    role: 'doctor',
    apiUrl: '<?= url("api/live_queue.php") ?>',
    pollInterval: 3000
});

async function handleQueueAction(action, apptId = null) {
    const formData = new FormData();
    formData.append('action', action);
    formData.append('doctor_id', <?= $doctorId ?>);
    if (apptId) formData.append('appointment_id', apptId);

    try {
        const res = await fetch('<?= url("api/queue_action.php") ?>', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            queueManager.fetchStatus();
        } else {
            alert(data.message || data.error || 'Action failed.');
        }
    } catch(e) {
        alert('Action error.');
    }
}

function openWalkinModal() {
    document.getElementById('walkin-modal').style.display = 'flex';
}
function closeWalkinModal() {
    document.getElementById('walkin-modal').style.display = 'none';
}

async function submitWalkin(e) {
    e.preventDefault();
    const form = document.getElementById('walkin-form');
    const formData = new FormData(form);
    formData.append('action', 'add_walkin');
    formData.append('doctor_id', <?= $doctorId ?>);

    try {
        const res = await fetch('<?= url("api/queue_action.php") ?>', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            alert(data.message);
            closeWalkinModal();
            form.reset();
            queueManager.fetchStatus();
        } else {
            alert(data.error || 'Failed to issue walk-in token.');
        }
    } catch(e) {
        alert('Network error.');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

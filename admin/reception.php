<?php
// ==============================================================================
// DOCTOR CONNECT - RECEPTIONIST QUEUE CONSOLE
// Multi-doctor live queue controller, walk-in token generation, and no-show handling
// ==============================================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_ADMIN);

$db = Database::getConnection();
$today = date('Y-m-d');

// Fetch all active doctors
$stmtDocs = $db->query("
    SELECT d.id, d.specialization, u.name as doctor_name
    FROM doctors d
    JOIN users u ON u.id = d.user_id
    ORDER BY u.name ASC
");
$doctors = $stmtDocs->fetchAll();

$selectedDoctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : ($doctors[0]['id'] ?? 0);
$queue = getDoctorLiveQueue($db, $selectedDoctorId, $today);

// Find active doctor info
$activeDoctor = null;
foreach ($doctors as $d) {
    if ((int)$d['id'] === $selectedDoctorId) {
        $activeDoctor = $d;
        break;
    }
}

$pageTitle = "Receptionist Console - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:1200px; margin:0 auto 3rem;">
    <!-- HEADER -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
        <div>
            <a href="<?= url('admin/dashboard.php') ?>" style="font-size:0.875rem; color:#64748b;">&larr; Back to Admin Overview</a>
            <h1 style="font-size:1.75rem; font-weight:800; color:#0f172a; margin-top:0.35rem;">
                Reception Desk & Queue Controller
            </h1>
            <p style="color:#64748b; font-size:0.9rem; margin:0;">
                Active Doctor: <strong><?= e($activeDoctor['doctor_name'] ?? 'Doctor') ?></strong> (<?= e($activeDoctor['specialization'] ?? '') ?>)
            </p>
        </div>

        <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
            <button type="button" class="btn btn-outline" onclick="openWalkinModal()">
                + Issue Walk-In Token
            </button>
            <button type="button" class="btn btn-primary btn-lg" onclick="handleQueueAction('call_next')">
                📢 Call Next Token &rarr;
            </button>
        </div>
    </div>

    <!-- DOCTOR SELECTOR TABS -->
    <div style="display:flex; gap:0.5rem; overflow-x:auto; padding-bottom:0.75rem; margin-bottom:1.25rem;">
        <?php foreach ($doctors as $doc): ?>
            <a href="<?= url('admin/reception.php?doctor_id=' . $doc['id']) ?>" 
               class="btn <?= ($selectedDoctorId === (int)$doc['id']) ? 'btn-primary' : 'btn-outline' ?> btn-sm"
               style="white-space:nowrap;">
               <?= e($doc['doctor_name']) ?> (<?= e($doc['specialization']) ?>)
            </a>
        <?php endforeach; ?>
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
                <div class="stat-label">Waiting</div>
            </div>
        </div>

        <div class="stat-card stat-primary">
            <div class="stat-icon">✓</div>
            <div>
                <div id="lq-completed-count" class="stat-val" style="color:#475569;">
                    <?= $queue['completed_count'] ?>
                </div>
                <div class="stat-label">Completed</div>
            </div>
        </div>
    </div>

    <!-- LIVE QUEUE ROSTER -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Today's Appointments for <?= e($activeDoctor['doctor_name'] ?? '') ?> (<?= count($queue['tokens']) ?> total)</h3>
            <span style="font-size:0.8rem; color:#64748b;">Live Sub-3s Sync</span>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:90px;">Token #</th>
                        <th>Patient Name</th>
                        <th>Contact</th>
                        <th>Est. Time</th>
                        <th>Status</th>
                        <th style="width:200px;">Reception Actions</th>
                    </tr>
                </thead>
                <tbody id="lq-table-body">
                    <?php if (empty($queue['tokens'])): ?>
                        <tr><td colspan="6" style="text-align:center; padding:2rem; color:#64748b;">No appointments or tokens booked for this doctor today.</td></tr>
                    <?php else: ?>
                        <?php foreach ($queue['tokens'] as $t): ?>
                            <tr style="<?= ($t['status'] === 'serving') ? 'background:#ecfdf5;' : '' ?>">
                                <td><strong style="color:#0284c7; font-size:1.1rem;">#<?= (int)$t['token_number'] ?></strong></td>
                                <td>
                                    <strong><?= e($t['patient_name']) ?></strong>
                                    <?php if (!empty($t['reason'])): ?>
                                        <div style="font-size:0.8rem; color:#64748b;"><?= e($t['reason']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($t['patient_phone']) ?></td>
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
                                        <button class="btn btn-sm btn-outline" onclick="handleQueueAction('mark_served', <?= $t['id'] ?>)">Mark Served</button>
                                    <?php elseif ($t['status'] === 'completed' && !empty($t['prescription'])): ?>
                                        <a href="<?= url('patient/prescription.php?id=' . $t['id']) ?>" target="_blank" class="btn btn-sm btn-outline">Rx</a>
                                    <?php else: ?>
                                        <span style="color:#94a3b8; font-size:0.8rem;">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
                <label class="form-label" for="walkin_name">Patient Full Name *</label>
                <input type="text" id="walkin_name" name="patient_name" class="form-control" required placeholder="Full Name">
            </div>

            <div class="form-group">
                <label class="form-label" for="walkin_phone">Patient Phone Number</label>
                <input type="tel" id="walkin_phone" name="patient_phone" class="form-control" placeholder="+1 555 000 0000">
            </div>

            <div class="form-group">
                <label class="form-label" for="walkin_reason">Reason for Visit</label>
                <input type="text" id="walkin_reason" name="reason" class="form-control" placeholder="Symptoms / consultation purpose">
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
    doctorId: <?= $selectedDoctorId ?>,
    role: 'admin',
    apiUrl: '<?= url("api/live_queue.php") ?>',
    pollInterval: 3000
});

async function handleQueueAction(action, apptId = null) {
    const formData = new FormData();
    formData.append('action', action);
    formData.append('doctor_id', <?= $selectedDoctorId ?>);
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
    formData.append('doctor_id', <?= $selectedDoctorId ?>);

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

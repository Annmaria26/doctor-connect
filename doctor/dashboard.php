<?php
// ==============================================================================
// DOCTOR CONNECT - DOCTOR DASHBOARD & CONSOLE
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

// Fetch live queue statistics for today
$queue = getDoctorLiveQueue($db, $doctorId, $today);

// Fetch weekly schedule summary
$stmtSched = $db->prepare("
    SELECT * FROM doctor_schedules 
    WHERE doctor_id = ? 
    ORDER BY day_of_week ASC
");
$stmtSched->execute([$doctorId]);
$schedules = $stmtSched->fetchAll();

$dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

$pageTitle = "Doctor Console - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem; flex-wrap:wrap; gap:1rem;">
    <div>
        <h1 style="font-size:1.85rem; font-weight:800; color:#0f172a; margin-bottom:0.25rem;">
            <?= e(currentUser()['name']) ?>'s Clinic Console 🩺
        </h1>
        <p style="color:#64748b; font-size:0.95rem; margin:0;">
            Specialization: <strong><?= e($_SESSION['doctor_specialization'] ?? 'Specialist') ?></strong> &bull; Today: <?= date('l, F j, Y') ?>
        </p>
    </div>
    <div style="display:flex; gap:0.75rem;">
        <a href="<?= url('doctor/queue.php') ?>" class="btn btn-primary btn-lg" style="box-shadow:0 6px 15px rgba(2,132,199,0.3);">
            🔴 Manage Live Queue
        </a>
        <a href="<?= url('doctor/schedule.php') ?>" class="btn btn-outline btn-lg">
            Weekly Hours
        </a>
    </div>
</div>

<!-- 4 TODAY'S METRIC COUNTERS -->
<div class="stats-grid">
    <div class="stat-card stat-success">
        <div class="stat-icon">👨‍⚕️</div>
        <div>
            <div class="stat-val" style="color:#059669;">
                <?= $queue['current_serving_token'] ? '#' . $queue['current_serving_token'] : 'None' ?>
            </div>
            <div class="stat-label">Currently Serving</div>
        </div>
    </div>

    <div class="stat-card stat-warning">
        <div class="stat-icon">⏳</div>
        <div>
            <div class="stat-val" style="color:#d97706;">
                <?= $queue['waiting_count'] ?>
            </div>
            <div class="stat-label">Patients Waiting</div>
        </div>
    </div>

    <div class="stat-card stat-primary">
        <div class="stat-icon">✓</div>
        <div>
            <div class="stat-val" style="color:#0284c7;">
                <?= $queue['completed_count'] ?>
            </div>
            <div class="stat-label">Completed Today</div>
        </div>
    </div>

    <div class="stat-card stat-danger">
        <div class="stat-icon">✕</div>
        <div>
            <div class="stat-val" style="color:#dc2626;">
                <?= $queue['no_show_count'] ?>
            </div>
            <div class="stat-label">No-Shows</div>
        </div>
    </div>
</div>

<!-- ACTIVE PATIENT & QUICK CALL NEXT ROW -->
<div class="grid-2" style="margin-bottom:2rem;">
    <!-- CURRENTLY SERVING CARD -->
    <div class="card" style="border-left:4px solid #10b981;">
        <div class="card-header">
            <h3 class="card-title" style="display:flex; align-items:center; gap:8px;">
                <span class="pulse-dot"></span> In Consultation Room Now
            </h3>
            <span class="badge badge-serving">SERVING</span>
        </div>

        <?php if ($queue['current_serving']): $serving = $queue['current_serving']; ?>
            <div style="margin-bottom:1.25rem;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div>
                        <div style="font-size:1.35rem; font-weight:800; color:#0f172a;"><?= e($serving['patient_name']) ?></div>
                        <div style="font-size:0.875rem; color:#64748b;">Phone: <?= e($serving['patient_phone']) ?></div>
                    </div>
                    <div style="background:#d1fae5; color:#065f46; font-size:1.4rem; font-weight:800; padding:6px 14px; border-radius:8px;">
                        #<?= (int)$serving['token_number'] ?>
                    </div>
                </div>
                <div style="margin-top:0.75rem; background:#f8fafc; padding:0.6rem 0.9rem; border-radius:6px; font-size:0.85rem; color:#475569;">
                    <strong>Reason for visit:</strong> <?= e($serving['reason'] ?: 'General Consultation') ?>
                </div>
            </div>

            <div style="display:flex; gap:0.5rem;">
                <a href="<?= url('doctor/consultation.php?id=' . $serving['id']) ?>" class="btn btn-teal btn-lg" style="flex:1;">
                    📹 Enter Consultation Station
                </a>
                <button type="button" class="btn btn-outline" onclick="advanceQueue('mark_served', <?= $serving['id'] ?>)">
                    Mark Completed
                </button>
            </div>
        <?php else: ?>
            <div style="text-align:center; padding:2rem 1rem; color:#64748b;">
                <div style="font-size:2rem; margin-bottom:0.5rem;">🪑</div>
                <p style="margin-bottom:1rem; font-size:0.95rem;">No patient is currently in the consultation room.</p>
                <?php if ($queue['next_waiting']): ?>
                    <button type="button" class="btn btn-primary" onclick="advanceQueue('call_next')">
                        Call Next Token (#<?= (int)$queue['next_waiting']['token_number'] ?>) &rarr;
                    </button>
                <?php else: ?>
                    <span style="font-size:0.85rem; color:#94a3b8;">No waiting patients in queue</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- NEXT IN LINE CARD -->
    <div class="card" style="border-left:4px solid #f59e0b;">
        <div class="card-header">
            <h3 class="card-title">Next Patient in Queue</h3>
            <span class="badge badge-waiting">WAITING</span>
        </div>

        <?php if ($queue['next_waiting']): $nxt = $queue['next_waiting']; ?>
            <div style="margin-bottom:1.25rem;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div>
                        <div style="font-size:1.3rem; font-weight:700; color:#0f172a;"><?= e($nxt['patient_name']) ?></div>
                        <div style="font-size:0.875rem; color:#64748b;">Est. Time: <?= date('h:i A', strtotime($nxt['estimated_time'])) ?></div>
                    </div>
                    <div style="background:#fef3c7; color:#92400e; font-size:1.4rem; font-weight:800; padding:6px 14px; border-radius:8px;">
                        #<?= (int)$nxt['token_number'] ?>
                    </div>
                </div>
                <div style="margin-top:0.75rem; background:#f8fafc; padding:0.6rem 0.9rem; border-radius:6px; font-size:0.85rem; color:#475569;">
                    <strong>Reason for visit:</strong> <?= e($nxt['reason'] ?: 'Routine checkup') ?>
                </div>
            </div>

            <div style="display:flex; gap:0.5rem;">
                <button type="button" class="btn btn-primary" style="flex:1;" onclick="advanceQueue('call_next')">
                    📢 Call This Patient Now
                </button>
                <button type="button" class="btn btn-outline btn-sm" onclick="advanceQueue('mark_no_show', <?= $nxt['id'] ?>)">
                    Mark No-Show
                </button>
            </div>
        <?php else: ?>
            <div style="text-align:center; padding:2rem 1rem; color:#64748b;">
                <div style="font-size:2rem; margin-bottom:0.5rem;">✓</div>
                <p style="margin:0; font-size:0.95rem;">Queue is empty! All patients for today have been attended to.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- TODAY'S QUEUE OVERVIEW TABLE -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Today's Token Queue (<?= count($queue['tokens']) ?> total)</h3>
        <a href="<?= url('doctor/queue.php') ?>" class="btn btn-outline btn-sm">Full Live Queue &rarr;</a>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Token #</th>
                    <th>Patient Name</th>
                    <th>Contact</th>
                    <th>Est. Time</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($queue['tokens'])): ?>
                    <tr><td colspan="6" style="text-align:center; color:#64748b; padding:1.5rem;">No appointments booked for today.</td></tr>
                <?php else: ?>
                    <?php foreach ($queue['tokens'] as $tok): ?>
                        <tr style="<?= ($tok['status'] === 'serving') ? 'background:#ecfdf5;' : '' ?>">
                            <td><strong style="color:#0284c7; font-size:1.05rem;">#<?= (int)$tok['token_number'] ?></strong></td>
                            <td><strong><?= e($tok['patient_name']) ?></strong></td>
                            <td><?= e($tok['patient_phone']) ?></td>
                            <td><?= date('h:i A', strtotime($tok['estimated_time'])) ?></td>
                            <td>
                                <span class="badge badge-<?= e($tok['status']) ?>">
                                    <?= strtoupper(e($tok['status'])) ?>
                                    <?php if ($tok['status'] === 'serving'): ?><span class="pulse-dot"></span><?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($tok['status'] === 'waiting'): ?>
                                    <button class="btn btn-sm btn-primary" onclick="advanceQueue('mark_serving', <?= $tok['id'] ?>)">Call</button>
                                    <button class="btn btn-sm btn-outline" onclick="advanceQueue('mark_no_show', <?= $tok['id'] ?>)">No-Show</button>
                                <?php elseif ($tok['status'] === 'serving'): ?>
                                    <a href="<?= url('doctor/consultation.php?id=' . $tok['id']) ?>" class="btn btn-sm btn-teal">In Call 📹</a>
                                <?php elseif ($tok['status'] === 'completed' && !empty($tok['prescription'])): ?>
                                    <a href="<?= url('patient/prescription.php?id=' . $tok['id']) ?>" target="_blank" class="btn btn-sm btn-outline">Rx</a>
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

<script>
async function advanceQueue(action, apptId = null) {
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
            window.location.reload();
        } else {
            alert(data.message || data.error || 'Action failed.');
        }
    } catch(e) {
        alert('Network error.');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

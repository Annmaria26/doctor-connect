<?php
// ==============================================================================
// DOCTOR CONNECT - ADMIN & RECEPTION OVERVIEW
// ==============================================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_ADMIN);

$db = Database::getConnection();
$today = date('Y-m-d');

// 1. KPI Counts
$stmtStats = $db->query("
    SELECT 
        (SELECT COUNT(*) FROM doctors) as total_doctors,
        (SELECT COUNT(*) FROM users WHERE role = 'patient') as total_patients,
        (SELECT COUNT(*) FROM appointments WHERE appointment_date = CURRENT_DATE()) as today_total,
        (SELECT COUNT(*) FROM appointments WHERE appointment_date = CURRENT_DATE() AND status = 'waiting') as today_waiting,
        (SELECT COUNT(*) FROM appointments WHERE appointment_date = CURRENT_DATE() AND status = 'serving') as today_serving,
        (SELECT COUNT(*) FROM appointments WHERE appointment_date = CURRENT_DATE() AND status = 'completed') as today_completed,
        (SELECT COUNT(*) FROM appointments WHERE appointment_date = CURRENT_DATE() AND status = 'no_show') as today_no_show,
        (SELECT COALESCE(SUM(d.consultation_fee), 0) FROM appointments a JOIN doctors d ON d.id = a.doctor_id WHERE a.appointment_date = CURRENT_DATE() AND a.status = 'completed') as today_revenue
");
$stats = $stmtStats->fetch();

// 2. Doctor Live Queues Overview
$stmtDocs = $db->query("
    SELECT d.id, d.specialization, u.name as doctor_name
    FROM doctors d
    JOIN users u ON u.id = d.user_id
    ORDER BY u.name ASC
");
$allDoctors = $stmtDocs->fetchAll();

// 3. Recent Queue Audit Logs
$stmtLogs = $db->query("
    SELECT ql.*, a.token_number, a.appointment_date, u.name as patient_name, du.name as doctor_name
    FROM queue_logs ql
    JOIN appointments a ON a.id = ql.appointment_id
    JOIN users u ON u.id = a.patient_id
    JOIN doctors d ON d.id = a.doctor_id
    JOIN users du ON du.id = d.user_id
    ORDER BY ql.id DESC
    LIMIT 8
");
$recentLogs = $stmtLogs->fetchAll();

$pageTitle = "Admin & Reception Console - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem; flex-wrap:wrap; gap:1rem;">
    <div>
        <h1 style="font-size:1.85rem; font-weight:800; color:#0f172a; margin-bottom:0.25rem;">
            Hospital & Clinic Operations Console 🏥
        </h1>
        <p style="color:#64748b; font-size:0.95rem; margin:0;">
            Real-time multi-doctor queues, token management, and audit telemetry &bull; <?= date('l, F j, Y') ?>
        </p>
    </div>
    <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
        <a href="<?= url('admin/reception.php') ?>" class="btn btn-teal btn-lg" style="box-shadow:0 6px 15px rgba(13,148,136,0.25);">
            🎯 Reception Console
        </a>
        <a href="<?= url('admin/queue_monitor.php') ?>" target="_blank" class="btn btn-primary btn-lg" style="box-shadow:0 6px 15px rgba(2,132,199,0.25);">
            📺 Live Waiting Room Display ↗
        </a>
    </div>
</div>

<!-- 5 KPI STAT CARDS -->
<div class="stats-grid">
    <div class="stat-card stat-primary">
        <div class="stat-icon">📅</div>
        <div>
            <div class="stat-val"><?= (int)$stats['today_total'] ?></div>
            <div class="stat-label">Today's Bookings</div>
        </div>
    </div>

    <div class="stat-card stat-success">
        <div class="stat-icon">👨‍⚕️</div>
        <div>
            <div class="stat-val" style="color:#059669;"><?= (int)$stats['today_serving'] ?></div>
            <div class="stat-label">Currently Serving</div>
        </div>
    </div>

    <div class="stat-card stat-warning">
        <div class="stat-icon">⏳</div>
        <div>
            <div class="stat-val" style="color:#d97706;"><?= (int)$stats['today_waiting'] ?></div>
            <div class="stat-label">In Live Queue</div>
        </div>
    </div>

    <div class="stat-card stat-primary">
        <div class="stat-icon">✓</div>
        <div>
            <div class="stat-val" style="color:#0284c7;"><?= (int)$stats['today_completed'] ?></div>
            <div class="stat-label">Completed Consults</div>
        </div>
    </div>

    <div class="stat-card stat-danger">
        <div class="stat-icon">✕</div>
        <div>
            <div class="stat-val" style="color:#dc2626;"><?= (int)$stats['today_no_show'] ?></div>
            <div class="stat-label">No-Shows</div>
        </div>
    </div>
</div>

<!-- DOCTOR LIVE QUEUES MONITOR & RECENT AUDIT TRAIL -->
<div class="grid-2" style="grid-template-columns: 1.35fr 1fr; margin-bottom:2.5rem; align-items:start;">
    <!-- ACTIVE QUEUES ACROSS DOCTORS -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Active Doctor Queues Today</h3>
            <a href="<?= url('admin/reception.php') ?>" class="btn btn-outline btn-sm">Manage in Reception &rarr;</a>
        </div>

        <div style="display:flex; flex-direction:column; gap:1rem;">
            <?php foreach ($allDoctors as $doc): 
                $q = getDoctorLiveQueue($db, (int)$doc['id'], $today);
            ?>
                <div style="border:1px solid #e2e8f0; border-radius:8px; padding:1rem; display:flex; justify-content:space-between; align-items:center; background:#ffffff;">
                    <div>
                        <div style="font-size:1.05rem; font-weight:700; color:#0f172a;"><?= e($doc['doctor_name']) ?></div>
                        <div style="font-size:0.8rem; color:#0284c7; font-weight:600;"><?= e($doc['specialization']) ?></div>
                        <div style="font-size:0.8rem; color:#64748b; margin-top:4px;">
                            Total Today: <strong><?= $q['total_tokens'] ?></strong> &bull; Waiting: <strong style="color:#d97706;"><?= $q['waiting_count'] ?></strong> &bull; Done: <strong><?= $q['completed_count'] ?></strong>
                        </div>
                    </div>

                    <div style="text-align:right;">
                        <div style="font-size:0.75rem; text-transform:uppercase; color:#64748b; font-weight:700;">Serving Now</div>
                        <div style="font-size:1.6rem; font-weight:800; color:#059669; line-height:1.2;">
                            <?= $q['current_serving_token'] ? '#' . $q['current_serving_token'] : '-' ?>
                        </div>
                        <div style="font-size:0.75rem; color:#64748b;">Next: <?= $q['next_waiting_token'] ? '#' . $q['next_waiting_token'] : 'None' ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- RECENT AUDIT ACTIVITY LOGS -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Queue Telemetry & Audit Logs</h3>
            <span style="font-size:0.8rem; color:#64748b;">Live Stream</span>
        </div>

        <?php if (empty($recentLogs)): ?>
            <p style="color:#64748b; font-size:0.875rem; text-align:center; padding:1.5rem 0;">No audit events recorded yet.</p>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:0.75rem;">
                <?php foreach ($recentLogs as $log): 
                    $actionColor = match($log['action']) {
                        'called' => '#3b82f6',
                        'serving' => '#10b981',
                        'served' => '#059669',
                        'no_show' => '#ef4444',
                        'cancelled' => '#94a3b8',
                        default => '#64748b'
                    };
                ?>
                    <div style="border-left:3px solid <?= $actionColor ?>; background:#f8fafc; padding:0.6rem 0.85rem; border-radius:4px; font-size:0.85rem;">
                        <div style="display:flex; justify-content:space-between;">
                            <strong style="color:#0f172a;">Token #<?= (int)$log['token_number'] ?> &bull; <?= e($log['patient_name']) ?></strong>
                            <span style="color:#64748b; font-size:0.75rem;"><?= date('h:i:s A', strtotime($log['action_time'])) ?></span>
                        </div>
                        <div style="color:#475569; font-size:0.8rem; margin-top:2px;">
                            Action: <span style="font-weight:700; text-transform:uppercase; color:<?= $actionColor ?>;"><?= e($log['action']) ?></span> &bull; Dr. <?= e($log['doctor_name']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
// ==============================================================================
// DOCTOR CONNECT - REPORTS & ANALYTICS DASHBOARD (ADMIN)
// Doctor-wise & Date-wise reports, no-show breakdown, and CSV export
// ==============================================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_ADMIN);

$db = Database::getConnection();

// Filters
$doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$status = trim($_GET['status'] ?? '');
$fromDate = trim($_GET['from_date'] ?? date('Y-m-01')); // 1st of current month
$toDate = trim($_GET['to_date'] ?? date('Y-m-d'));

$where = ["a.appointment_date BETWEEN ? AND ?"];
$params = [$fromDate, $toDate];

if ($doctorId > 0) {
    $where[] = "a.doctor_id = ?";
    $params[] = $doctorId;
}
if (!empty($status)) {
    $where[] = "a.status = ?";
    $params[] = $status;
}

$whereClause = implode(" AND ", $where);

// 1. Fetch filtered appointments
$stmt = $db->prepare("
    SELECT a.*, 
           u.name as patient_name, u.phone as patient_phone, u.email as patient_email,
           du.name as doctor_name, d.specialization, d.consultation_fee
    FROM appointments a
    JOIN users u ON u.id = a.patient_id
    JOIN doctors d ON d.id = a.doctor_id
    JOIN users du ON du.id = d.user_id
    WHERE {$whereClause}
    ORDER BY a.appointment_date DESC, a.token_number ASC
");
$stmt->execute($params);
$appointments = $stmt->fetchAll();

// 2. Compute summary metrics
$totalBookings = count($appointments);
$completedCount = 0;
$noShowCount = 0;
$waitingCount = 0;
$cancelledCount = 0;
$totalRevenue = 0.0;

foreach ($appointments as $a) {
    if ($a['status'] === 'completed') {
        $completedCount++;
        $totalRevenue += (float)$a['consultation_fee'];
    } elseif ($a['status'] === 'no_show') {
        $noShowCount++;
    } elseif ($a['status'] === 'waiting' || $a['status'] === 'serving') {
        $waitingCount++;
    } elseif ($a['status'] === 'cancelled') {
        $cancelledCount++;
    }
}

$completedRate = $totalBookings > 0 ? round(($completedCount / $totalBookings) * 100, 1) : 0;
$noShowRate = $totalBookings > 0 ? round(($noShowCount / $totalBookings) * 100, 1) : 0;

// Fetch doctors for filter dropdown
$stmtDocs = $db->query("SELECT d.id, u.name, d.specialization FROM doctors d JOIN users u ON u.id = d.user_id ORDER BY u.name ASC");
$allDocs = $stmtDocs->fetchAll();

$exportQuery = http_build_query([
    'doctor_id' => $doctorId,
    'status' => $status,
    'from_date' => $fromDate,
    'to_date' => $toDate
]);

$pageTitle = "Consultation Reports & Telemetry - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:1200px; margin:0 auto 3rem;">
    <!-- HEADER -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
        <div>
            <a href="<?= url('admin/dashboard.php') ?>" style="font-size:0.875rem; color:#64748b;">&larr; Back to Admin Overview</a>
            <h1 style="font-size:1.75rem; font-weight:800; color:#0f172a; margin-top:0.35rem;">
                Consultation Analytics & Reports
            </h1>
            <p style="color:#64748b; font-size:0.9rem; margin:0;">
                Doctor-wise & date-wise performance, no-show monitoring, and data exports
            </p>
        </div>

        <a href="<?= url('admin/export_csv.php?' . $exportQuery) ?>" class="btn btn-teal btn-lg" style="box-shadow:0 4px 12px rgba(13,148,136,0.25);">
            📥 Download CSV Report
        </a>
    </div>

    <!-- FILTER BAR -->
    <div class="card" style="padding:1.25rem; margin-bottom:1.75rem;">
        <form method="GET" action="" style="display:flex; gap:1rem; align-items:flex-end; flex-wrap:wrap;">
            <div style="flex:1; min-width:180px;">
                <label class="form-label" style="font-size:0.8rem;">Doctor / Specialist</label>
                <select name="doctor_id" class="form-select" style="padding:0.45rem 0.75rem;">
                    <option value="">All Doctors</option>
                    <?php foreach ($allDocs as $doc): ?>
                        <option value="<?= $doc['id'] ?>" <?= ($doctorId === (int)$doc['id']) ? 'selected' : '' ?>>
                            <?= e($doc['name']) ?> (<?= e($doc['specialization']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="flex:1; min-width:140px;">
                <label class="form-label" style="font-size:0.8rem;">Status</label>
                <select name="status" class="form-select" style="padding:0.45rem 0.75rem;">
                    <option value="">All Statuses</option>
                    <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="waiting" <?= $status === 'waiting' ? 'selected' : '' ?>>Waiting</option>
                    <option value="serving" <?= $status === 'serving' ? 'selected' : '' ?>>Serving</option>
                    <option value="no_show" <?= $status === 'no_show' ? 'selected' : '' ?>>No-Show</option>
                    <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>

            <div style="flex:1; min-width:140px;">
                <label class="form-label" style="font-size:0.8rem;">From Date</label>
                <input type="date" name="from_date" class="form-control" value="<?= e($fromDate) ?>" style="padding:0.45rem 0.75rem;">
            </div>

            <div style="flex:1; min-width:140px;">
                <label class="form-label" style="font-size:0.8rem;">To Date</label>
                <input type="date" name="to_date" class="form-control" value="<?= e($toDate) ?>" style="padding:0.45rem 0.75rem;">
            </div>

            <div>
                <button type="submit" class="btn btn-primary" style="height:38px;">Apply Filter</button>
                <a href="<?= url('admin/reports.php') ?>" class="btn btn-outline" style="height:38px;">Reset</a>
            </div>
        </form>
    </div>

    <!-- 4 SUMMARY KPIS -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">📊</div>
            <div>
                <div class="stat-val"><?= $totalBookings ?></div>
                <div class="stat-label">Total Bookings</div>
            </div>
        </div>

        <div class="stat-card stat-success">
            <div class="stat-icon">✓</div>
            <div>
                <div class="stat-val" style="color:#059669;"><?= $completedCount ?> (<?= $completedRate ?>%)</div>
                <div class="stat-label">Completed Consultations</div>
            </div>
        </div>

        <div class="stat-card stat-danger">
            <div class="stat-icon">⚠️</div>
            <div>
                <div class="stat-val" style="color:#dc2626;"><?= $noShowCount ?> (<?= $noShowRate ?>%)</div>
                <div class="stat-label">No-Show Rate</div>
            </div>
        </div>

        <div class="stat-card stat-primary">
            <div class="stat-icon">💵</div>
            <div>
                <div class="stat-val" style="color:#0f766e;">$<?= number_format($totalRevenue, 2) ?></div>
                <div class="stat-label">Revenue Realized</div>
            </div>
        </div>
    </div>

    <!-- APPOINTMENTS TABLE -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Appointments Report (<?= count($appointments) ?> Records)</h3>
            <span style="font-size:0.8rem; color:#64748b;">
                Showing <?= date('M d, Y', strtotime($fromDate)) ?> to <?= date('M d, Y', strtotime($toDate)) ?>
            </span>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Token</th>
                        <th>Patient Name</th>
                        <th>Doctor</th>
                        <th>Specialty</th>
                        <th>Status</th>
                        <th>Fee</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($appointments)): ?>
                        <tr><td colspan="8" style="text-align:center; padding:2rem; color:#64748b;">No appointment records match the selected criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($appointments as $a): ?>
                            <tr>
                                <td><?= date('M d, Y', strtotime($a['appointment_date'])) ?></td>
                                <td><strong style="color:#0284c7;">#<?= (int)$a['token_number'] ?></strong></td>
                                <td>
                                    <strong><?= e($a['patient_name']) ?></strong><br>
                                    <span style="font-size:0.75rem; color:#64748b;"><?= e($a['patient_phone']) ?></span>
                                </td>
                                <td><?= e($a['doctor_name']) ?></td>
                                <td><span style="color:#0f766e; font-size:0.85rem;"><?= e($a['specialization']) ?></span></td>
                                <td>
                                    <span class="badge badge-<?= e($a['status']) ?>">
                                        <?= strtoupper(e($a['status'])) ?>
                                    </span>
                                </td>
                                <td>$<?= number_format((float)$a['consultation_fee'], 2) ?></td>
                                <td>
                                    <?php if ($a['status'] === 'completed' && !empty($a['prescription'])): ?>
                                        <a href="<?= url('patient/prescription.php?id=' . $a['id']) ?>" target="_blank" class="btn btn-sm btn-outline">Rx</a>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

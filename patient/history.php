<?php
// ==============================================================================
// DOCTOR CONNECT - PATIENT CONSULTATION HISTORY
// ==============================================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_PATIENT);

$patientId = currentUserId();
$db = Database::getConnection();

$statusFilter = $_GET['status'] ?? '';
$params = [$patientId];
$whereStatus = "";

if (!empty($statusFilter)) {
    $whereStatus = "AND a.status = ?";
    $params[] = $statusFilter;
}

$stmt = $db->prepare("
    SELECT a.*, d.specialization, d.consultation_fee, du.name as doctor_name
    FROM appointments a
    JOIN doctors d ON d.id = a.doctor_id
    JOIN users du ON du.id = d.user_id
    WHERE a.patient_id = ? {$whereStatus}
    ORDER BY a.appointment_date DESC, a.id DESC
");
$stmt->execute($params);
$appointments = $stmt->fetchAll();

$pageTitle = "Consultation History - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:1050px; margin:0 auto 3rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.75rem; flex-wrap:wrap; gap:1rem;">
        <div>
            <h1 style="font-size:1.75rem; font-weight:800; color:#0f172a; margin-bottom:0.25rem;">
                My Consultation Records
            </h1>
            <p style="color:#64748b; font-size:0.9rem; margin:0;">
                Review all your past online appointments, doctor notes, and prescriptions
            </p>
        </div>
        <a href="<?= url('patient/book.php') ?>" class="btn btn-primary">+ Book Token</a>
    </div>

    <!-- FILTER BAR -->
    <div class="card" style="padding:1rem 1.25rem; margin-bottom:1.5rem;">
        <form method="GET" action="" style="display:flex; gap:1rem; align-items:center; flex-wrap:wrap;">
            <label style="font-size:0.875rem; font-weight:600; color:#475569;">Filter by Status:</label>
            <select name="status" class="form-select" style="max-width:200px; padding:0.4rem 0.75rem;" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="waiting" <?= $statusFilter === 'waiting' ? 'selected' : '' ?>>Waiting</option>
                <option value="serving" <?= $statusFilter === 'serving' ? 'selected' : '' ?>>Serving</option>
                <option value="no_show" <?= $statusFilter === 'no_show' ? 'selected' : '' ?>>No-Show</option>
                <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
            <?php if (!empty($statusFilter)): ?>
                <a href="<?= url('patient/history.php') ?>" class="btn btn-outline btn-sm">Clear Filter</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- APPOINTMENTS TABLE -->
    <div class="card">
        <?php if (empty($appointments)): ?>
            <p style="color:#64748b; text-align:center; padding:2rem 0; margin:0;">No consultation records matching this filter.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Token</th>
                            <th>Date & Time</th>
                            <th>Doctor & Specialty</th>
                            <th>Reason / Symptoms</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $a): ?>
                            <tr>
                                <td>
                                    <strong style="font-size:1.05rem; color:#0284c7;">#<?= (int)$a['token_number'] ?></strong>
                                </td>
                                <td>
                                    <strong><?= date('M d, Y', strtotime($a['appointment_date'])) ?></strong><br>
                                    <span style="font-size:0.8rem; color:#64748b;"><?= date('h:i A', strtotime($a['estimated_time'])) ?></span>
                                </td>
                                <td>
                                    <strong><?= e($a['doctor_name']) ?></strong><br>
                                    <span style="font-size:0.8rem; color:#0f766e;"><?= e($a['specialization']) ?></span>
                                </td>
                                <td>
                                    <span style="font-size:0.85rem; color:#475569;">
                                        <?= e($a['reason'] ?: 'Routine checkup') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?= e($a['status']) ?>">
                                        <?= strtoupper(e($a['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (in_array($a['status'], ['waiting', 'serving'])): ?>
                                        <a href="<?= url('patient/queue.php?id=' . $a['id']) ?>" class="btn btn-sm btn-primary">
                                            Live Queue 🔴
                                        </a>
                                    <?php elseif ($a['status'] === 'completed' && !empty($a['prescription'])): ?>
                                        <a href="<?= url('patient/prescription.php?id=' . $a['id']) ?>" class="btn btn-sm btn-outline">
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
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

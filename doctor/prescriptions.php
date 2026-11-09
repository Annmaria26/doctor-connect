<?php
// ==============================================================================
// DOCTOR CONNECT - DOCTOR PAST CONSULTATIONS & PRESCRIPTIONS ARCHIVE
// ==============================================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_DOCTOR);

$db = Database::getConnection();
$doctorId = (int)($_SESSION['doctor_id'] ?? 0);

$search = trim($_GET['search'] ?? '');
$params = [$doctorId];
$whereSearch = "";

if (!empty($search)) {
    $whereSearch = "AND (u.name LIKE ? OR u.phone LIKE ? OR a.diagnosis LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$stmt = $db->prepare("
    SELECT a.*, u.name as patient_name, u.phone as patient_phone, u.email as patient_email
    FROM appointments a
    JOIN users u ON u.id = a.patient_id
    WHERE a.doctor_id = ? AND a.status = 'completed' {$whereSearch}
    ORDER BY a.appointment_date DESC, a.id DESC
");
$stmt->execute($params);
$consultations = $stmt->fetchAll();

$pageTitle = "Completed Consultations Archive - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:1150px; margin:0 auto 3rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
        <div>
            <a href="<?= url('doctor/dashboard.php') ?>" style="font-size:0.875rem; color:#64748b;">&larr; Back to Dashboard</a>
            <h1 style="font-size:1.75rem; font-weight:800; color:#0f172a; margin-top:0.35rem;">
                Completed Patient Consultations & Rx
            </h1>
            <p style="color:#64748b; font-size:0.9rem; margin:0;">
                Clinical records and issued prescriptions
            </p>
        </div>
    </div>

    <!-- SEARCH BAR -->
    <div class="card" style="padding:1rem 1.25rem; margin-bottom:1.5rem;">
        <form method="GET" action="" style="display:flex; gap:0.75rem; align-items:center; flex-wrap:wrap;">
            <input type="text" name="search" class="form-control" placeholder="Search by patient name, phone, or diagnosis..." value="<?= e($search) ?>" style="max-width:350px;">
            <button type="submit" class="btn btn-primary btn-sm">Search</button>
            <?php if (!empty($search)): ?>
                <a href="<?= url('doctor/prescriptions.php') ?>" class="btn btn-outline btn-sm">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- TABLE -->
    <div class="card">
        <?php if (empty($consultations)): ?>
            <p style="color:#64748b; text-align:center; padding:2rem 0; margin:0;">No completed consultation records found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Token</th>
                            <th>Patient Name</th>
                            <th>Diagnosis</th>
                            <th>Prescription Summary</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($consultations as $c): ?>
                            <tr>
                                <td><?= date('M d, Y', strtotime($c['appointment_date'])) ?></td>
                                <td><strong style="color:#0284c7;">#<?= (int)$c['token_number'] ?></strong></td>
                                <td>
                                    <strong><?= e($c['patient_name']) ?></strong><br>
                                    <span style="font-size:0.8rem; color:#64748b;"><?= e($c['patient_phone']) ?></span>
                                </td>
                                <td>
                                    <span style="font-weight:600; color:#0f172a;">
                                        <?= e($c['diagnosis'] ?: 'General Consult') ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size:0.85rem; color:#475569; font-family:monospace;">
                                        <?= e(mb_strimwidth($c['prescription'] ?? '', 0, 70, '...')) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?= url('patient/prescription.php?id=' . $c['id']) ?>" target="_blank" class="btn btn-sm btn-outline">
                                        📄 View / Print Rx
                                    </a>
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

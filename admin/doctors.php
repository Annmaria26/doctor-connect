<?php
// ==============================================================================
// DOCTOR CONNECT - DOCTOR ROSTER MANAGEMENT (ADMIN)
// ==============================================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_ADMIN);

$db = Database::getConnection();

$stmt = $db->query("
    SELECT d.*, u.name as doctor_name, u.email as doctor_email, u.phone as doctor_phone,
           (SELECT COUNT(*) FROM doctor_schedules ds WHERE ds.doctor_id = d.id AND ds.is_active = 1) as active_shifts,
           (SELECT COUNT(*) FROM appointments a WHERE a.doctor_id = d.id) as total_appointments
    FROM doctors d
    JOIN users u ON u.id = d.user_id
    ORDER BY d.id ASC
");
$doctors = $stmt->fetchAll();

$pageTitle = "Manage Doctors - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:1150px; margin:0 auto 3rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.75rem; flex-wrap:wrap; gap:1rem;">
        <div>
            <a href="<?= url('admin/dashboard.php') ?>" style="font-size:0.875rem; color:#64748b;">&larr; Back to Admin Overview</a>
            <h1 style="font-size:1.75rem; font-weight:800; color:#0f172a; margin-top:0.35rem;">
                Doctor Roster & Specialties
            </h1>
            <p style="color:#64748b; font-size:0.9rem; margin:0;">
                Configure specialist profiles, consultation fees, and schedule defaults
            </p>
        </div>
        <a href="<?= url('admin/doctor_create.php') ?>" class="btn btn-primary btn-lg">
            + Register New Doctor
        </a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Doctor Name</th>
                        <th>Specialization</th>
                        <th>Fee</th>
                        <th>Avg Duration</th>
                        <th>Active Shifts</th>
                        <th>Total Consults</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($doctors as $doc): ?>
                        <tr>
                            <td>#<?= (int)$doc['id'] ?></td>
                            <td>
                                <strong><?= e($doc['doctor_name']) ?></strong><br>
                                <span style="font-size:0.8rem; color:#64748b;"><?= e($doc['doctor_email']) ?> &bull; <?= e($doc['doctor_phone']) ?></span>
                            </td>
                            <td>
                                <span class="badge" style="background:#e0f2fe; color:#0369a1; font-weight:600;">
                                    <?= e($doc['specialization']) ?>
                                </span>
                            </td>
                            <td><strong>$<?= number_format((float)$doc['consultation_fee'], 2) ?></strong></td>
                            <td><?= (int)$doc['avg_consultation_mins'] ?> mins</td>
                            <td>
                                <span class="badge" style="background:#dcfce7; color:#15803d;">
                                    <?= (int)$doc['active_shifts'] ?> days/wk
                                </span>
                            </td>
                            <td><?= (int)$doc['total_appointments'] ?></td>
                            <td>
                                <a href="<?= url('admin/doctor_edit.php?id=' . $doc['id']) ?>" class="btn btn-sm btn-outline">
                                    Edit Profile
                                </a>
                                <a href="<?= url('admin/reception.php?doctor_id=' . $doc['id']) ?>" class="btn btn-sm btn-primary">
                                    Queue
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

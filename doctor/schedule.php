<?php
// ==============================================================================
// DOCTOR CONNECT - DOCTOR WEEKLY SCHEDULE MANAGER
// Configure consultation working hours, slot durations, and active days
// ==============================================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_DOCTOR);

$db = Database::getConnection();
$doctorId = (int)($_SESSION['doctor_id'] ?? 0);

$dayNames = [
    0 => 'Sunday',
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie();

    $schedulesPost = $_POST['schedule'] ?? [];

    $stmtDelete = $db->prepare("DELETE FROM doctor_schedules WHERE doctor_id = ?");
    $stmtDelete->execute([$doctorId]);

    $stmtInsert = $db->prepare("
        INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration, is_active)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($schedulesPost as $day => $data) {
        $isActive = isset($data['is_active']) ? 1 : 0;
        $startTime = !empty($data['start_time']) ? $data['start_time'] : '09:00:00';
        $endTime = !empty($data['end_time']) ? $data['end_time'] : '17:00:00';
        $slotDuration = max(5, (int)($data['slot_duration'] ?? 15));

        $stmtInsert->execute([
            $doctorId,
            (int)$day,
            $startTime,
            $endTime,
            $slotDuration,
            $isActive
        ]);
    }

    setFlash('success', 'Weekly schedule updated successfully.');
    header('Location: ' . url('doctor/schedule.php'));
    exit;
}

// Fetch existing schedules
$stmt = $db->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = ? ORDER BY day_of_week ASC");
$stmt->execute([$doctorId]);
$existingRows = $stmt->fetchAll();

$schedulesByDay = [];
foreach ($existingRows as $row) {
    $schedulesByDay[$row['day_of_week']] = $row;
}

$pageTitle = "Weekly Schedule - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:900px; margin:0 auto 3rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
        <div>
            <a href="<?= url('doctor/dashboard.php') ?>" style="font-size:0.875rem; color:#64748b;">&larr; Back to Dashboard</a>
            <h1 style="font-size:1.75rem; font-weight:800; color:#0f172a; margin-top:0.35rem;">
                Weekly Consultation Availability
            </h1>
            <p style="color:#64748b; font-size:0.9rem; margin:0;">
                Define your operating hours. Daily appointment capacity and token order will be computed dynamically.
            </p>
        </div>
    </div>

    <form method="POST" action="">
        <?= csrfField() ?>

        <div class="card">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:140px;">Day of Week</th>
                            <th style="width:110px;">Available</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th style="width:130px;">Slot (Mins)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($d = 0; $d <= 6; $d++): 
                            $sched = $schedulesByDay[$d] ?? [
                                'is_active' => ($d > 0 && $d < 6) ? 1 : 0,
                                'start_time' => '09:00:00',
                                'end_time' => '17:00:00',
                                'slot_duration' => 15
                            ];
                        ?>
                            <tr>
                                <td>
                                    <strong><?= $dayNames[$d] ?></strong>
                                </td>
                                <td>
                                    <label style="display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                                        <input type="checkbox" name="schedule[<?= $d ?>][is_active]" value="1" <?= !empty($sched['is_active']) ? 'checked' : '' ?>>
                                        <span style="font-size:0.85rem;">Active</span>
                                    </label>
                                </td>
                                <td>
                                    <input type="time" name="schedule[<?= $d ?>][start_time]" class="form-control" value="<?= substr($sched['start_time'], 0, 5) ?>" style="padding:0.35rem 0.6rem;">
                                </td>
                                <td>
                                    <input type="time" name="schedule[<?= $d ?>][end_time]" class="form-control" value="<?= substr($sched['end_time'], 0, 5) ?>" style="padding:0.35rem 0.6rem;">
                                </td>
                                <td>
                                    <input type="number" name="schedule[<?= $d ?>][slot_duration]" class="form-control" value="<?= (int)$sched['slot_duration'] ?>" min="5" max="120" step="5" style="padding:0.35rem 0.6rem;">
                                </td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

            <div style="margin-top:1.5rem; text-align:right;">
                <button type="submit" class="btn btn-primary btn-lg">
                    💾 Save Weekly Availability
                </button>
            </div>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

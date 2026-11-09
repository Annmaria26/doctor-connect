<?php
// ==============================================================================
// DOCTOR CONNECT - BOOK CONSULTATION TOKEN
// Atomic race-safe token booking with dynamic slot computation
// ==============================================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_PATIENT);

$db = Database::getConnection();
$patientId = currentUserId();

$preselectedDoctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$error = '';

// Fetch all active doctors
$stmtDocs = $db->query("
    SELECT d.*, u.name as doctor_name 
    FROM doctors d
    JOIN users u ON u.id = d.user_id
    ORDER BY d.specialization ASC, u.name ASC
");
$doctors = $stmtDocs->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie();

    $doctorId = (int)($_POST['doctor_id'] ?? 0);
    $appointmentDate = trim($_POST['appointment_date'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    if ($doctorId <= 0 || empty($appointmentDate)) {
        $error = 'Please select a doctor and consultation date.';
    } elseif (strtotime($appointmentDate) < strtotime(date('Y-m-d'))) {
        $error = 'Cannot book an appointment for past dates.';
    } else {
        // Check if doctor has schedule on this date
        $slots = computeSlotsForDate($db, $doctorId, $appointmentDate);
        if (!$slots['available']) {
            $error = $slots['message'];
        } else {
            // Check if patient already has a waiting/serving token for this doctor on this date
            $stmtCheckDuplicate = $db->prepare("
                SELECT id, token_number FROM appointments 
                WHERE patient_id = ? AND doctor_id = ? AND appointment_date = ? AND status IN ('waiting', 'serving')
                LIMIT 1
            ");
            $stmtCheckDuplicate->execute([$patientId, $doctorId, $appointmentDate]);
            $existing = $stmtCheckDuplicate->fetch();

            if ($existing) {
                setFlash('warning', "You already hold active Token #{$existing['token_number']} for this doctor today.");
                header("Location: " . url("patient/queue.php?id=" . $existing['id']));
                exit;
            }

            // ATOMIC RACE-SAFE BOOKING
            $result = bookAppointmentRaceSafe($db, $patientId, $doctorId, $appointmentDate, $reason);

            if ($result['success']) {
                setFlash('success', "🎉 Token #{$result['token_number']} successfully booked! Track your queue position below.");
                header("Location: " . url("patient/queue.php?id=" . $result['appointment_id']));
                exit;
            } else {
                $error = $result['error'] ?? 'Booking failed due to high concurrency. Please try again.';
            }
        }
    }
}

$pageTitle = "Book Doctor Token - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:750px; margin:1rem auto 3rem;">
    <div style="margin-bottom:1.5rem;">
        <a href="<?= url('patient/dashboard.php') ?>" style="font-size:0.875rem; color:#64748b;">&larr; Back to Dashboard</a>
        <h1 style="font-size:1.85rem; font-weight:800; color:#0f172a; margin-top:0.5rem;">
            Book an Online Consultation Token
        </h1>
        <p style="color:#64748b;">
            Select a specialist and date. Tokens are assigned sequentially and race-safely.
        </p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="grid-2" style="grid-template-columns: 1.4fr 1fr; align-items:start;">
        <!-- BOOKING FORM -->
        <div class="card">
            <form method="POST" action="">
                <?= csrfField() ?>

                <div class="form-group">
                    <label class="form-label" for="doctor_id">Select Doctor / Specialist *</label>
                    <select name="doctor_id" id="doctor_id" class="form-select" required>
                        <option value="">-- Choose a Doctor --</option>
                        <?php foreach ($doctors as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= ($preselectedDoctorId === (int)$d['id']) ? 'selected' : '' ?>>
                                <?= e($d['doctor_name']) ?> &mdash; <?= e($d['specialization']) ?> ($<?= number_format((float)$d['consultation_fee'], 2) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="appointment_date">Consultation Date *</label>
                    <input type="date" id="appointment_date" name="appointment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    <div class="form-text">Choose today for immediate live queue or an upcoming date.</div>
                </div>

                <!-- DYNAMIC AVAILABILITY & CAPACITY BANNER -->
                <div id="slot-info-box" style="margin-bottom:1.25rem; display:none;">
                    <div id="slot-summary"></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="reason">Reason for Visit / Symptoms (Optional)</label>
                    <textarea id="reason" name="reason" rows="3" class="form-control" placeholder="Describe symptoms, primary health concern, or reason for follow-up..."></textarea>
                </div>

                <div style="margin-top:1.5rem;">
                    <button type="submit" id="btn-book-submit" class="btn btn-teal btn-lg" style="width:100%; box-shadow:0 4px 12px rgba(13,148,136,0.3);">
                        Confirm & Issue Token &rarr;
                    </button>
                </div>
            </form>
        </div>

        <!-- LIVE TOKEN ORDER PREVIEW CARD -->
        <div>
            <div class="card" style="background:linear-gradient(135deg, #f8fafc, #e0f2fe); border:1px solid #bfdbfe;">
                <h3 style="font-size:1.05rem; font-weight:700; color:#0369a1; margin-bottom:1rem; display:flex; align-items:center; gap:6px;">
                    <span>🎫</span> Token Order Preview
                </h3>

                <div style="background:white; border-radius:8px; padding:1.25rem; text-align:center; box-shadow:var(--shadow-sm); margin-bottom:1.25rem;">
                    <div style="font-size:0.75rem; text-transform:uppercase; color:#64748b; font-weight:700;">Projected Token #</div>
                    <div id="preview-token-number" style="font-size:2.8rem; font-weight:800; color:#0284c7; line-height:1.2;">
                        --
                    </div>
                    <div style="font-size:0.8rem; color:#64748b; margin-top:4px;">Assigned atomically upon confirmation</div>
                </div>

                <div style="font-size:0.875rem; color:#334155; line-height:1.8;">
                    <div style="display:flex; justify-content:space-between;">
                        <span style="color:#64748b;">Doctor:</span>
                        <strong id="preview-doctor-name">-</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span style="color:#64748b;">Fee:</span>
                        <strong id="preview-fee" style="color:#0f766e;">-</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span style="color:#64748b;">Est. Meeting Time:</span>
                        <strong id="preview-estimated-time">-</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span style="color:#64748b;">Video Platform:</span>
                        <strong>Jitsi WebRTC (HD)</strong>
                    </div>
                </div>

                <hr style="border:none; border-top:1px dashed #cbd5e1; margin:1rem 0;">

                <p style="font-size:0.75rem; color:#64748b; margin:0; line-height:1.4;">
                    🔒 <strong>Privacy & Meeting Access:</strong> Per clinic protocol, your private video room link unlocks automatically when your token is called or next in line.
                </p>
            </div>
        </div>
    </div>
</div>

<script src="<?= asset('js/booking.js') ?>"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

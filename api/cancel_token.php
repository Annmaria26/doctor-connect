<?php
// ==============================================================================
// DOCTOR CONNECT - CANCEL TOKEN API
// ==============================================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
verifyCsrfOrDie();

$appointmentId = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0;
$patientId = currentUserId();

$db = Database::getConnection();

// Fetch appointment
$stmt = $db->prepare("SELECT * FROM appointments WHERE id = ?");
$stmt->execute([$appointmentId]);
$appt = $stmt->fetch();

if (!$appt) {
    echo json_encode(['success' => false, 'error' => 'Appointment not found.']);
    exit;
}

// Ensure patient owns it unless admin
if ($appt['patient_id'] != $patientId && !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$cancelPolicy = canCancelAppointment($appt);
if (!$cancelPolicy['allowed']) {
    echo json_encode(['success' => false, 'error' => $cancelPolicy['message']]);
    exit;
}

// Mark cancelled
$stmtCancel = $db->prepare("UPDATE appointments SET status = ? WHERE id = ?");
$stmtCancel->execute([STATUS_CANCELLED, $appointmentId]);

logQueueAction($db, $appointmentId, 'cancelled');
createNotification($db, $appt['patient_id'], "Your Token #{$appt['token_number']} for appointment ID #{$appt['id']} has been cancelled.");

echo json_encode([
    'success' => true,
    'message' => 'Appointment and token cancelled successfully.'
]);

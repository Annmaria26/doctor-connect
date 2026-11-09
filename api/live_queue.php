<?php
// ==============================================================================
// DOCTOR CONNECT - LIVE QUEUE POLLING API
// Returns JSON <= 3s response time for patient & doctor live queue updates
// ==============================================================================

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = Database::getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection unavailable']);
    exit;
}

$doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$appointmentId = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;
$date = $_GET['date'] ?? date('Y-m-d');

// If appointment_id provided, deduce doctor_id and date if not supplied
$myAppt = null;
if ($appointmentId > 0) {
    $stmtAppt = $db->prepare("
        SELECT a.*, u.name as patient_name, d.specialization, du.name as doctor_name
        FROM appointments a
        JOIN users u ON u.id = a.patient_id
        JOIN doctors d ON d.id = a.doctor_id
        JOIN users du ON du.id = d.user_id
        WHERE a.id = ?
    ");
    $stmtAppt->execute([$appointmentId]);
    $myAppt = $stmtAppt->fetch();
    if ($myAppt) {
        $doctorId = (int)$myAppt['doctor_id'];
        $date = $myAppt['appointment_date'];
    }
}

// If user is logged in as doctor and doctorId not passed, use session doctor_id
if ($doctorId <= 0 && isDoctor() && !empty($_SESSION['doctor_id'])) {
    $doctorId = (int)$_SESSION['doctor_id'];
}

if ($doctorId <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Doctor ID required'
    ]);
    exit;
}

// Fetch live queue state
$queue = getDoctorLiveQueue($db, $doctorId, $date);

// Calculate patient-specific status if appointment requested
$myStatus = null;
if ($myAppt) {
    $isServing = ($myAppt['status'] === STATUS_SERVING);
    $isNext = ($myAppt['status'] === STATUS_WAITING && (int)$myAppt['token_number'] === $queue['next_waiting_token']);
    
    // Meeting link is unlocked ONLY if currently SERVING or NEXT
    $canJoin = isMeetingLinkUnlockedForPatient($myAppt, $queue['current_serving_token'], $queue['next_waiting_token']);

    // Count waiting tokens strictly ahead of this token
    $tokensAhead = 0;
    if ($myAppt['status'] === STATUS_WAITING) {
        $stmtAhead = $db->prepare("
            SELECT COUNT(*) 
            FROM appointments 
            WHERE doctor_id = ? AND appointment_date = ? AND status = ? AND token_number < ?
        ");
        $stmtAhead->execute([$doctorId, $date, STATUS_WAITING, $myAppt['token_number']]);
        $tokensAhead = (int)$stmtAhead->fetchColumn();
    }

    $myStatus = [
        'id' => (int)$myAppt['id'],
        'token_number' => (int)$myAppt['token_number'],
        'status' => $myAppt['status'],
        'estimated_time' => $myAppt['estimated_time'],
        'is_serving' => $isServing,
        'is_next' => $isNext,
        'tokens_ahead' => $tokensAhead,
        'can_join_meeting' => $canJoin,
        // Only return meeting link if unlocked!
        'meeting_link' => $canJoin ? $myAppt['meeting_link'] : null,
        'meeting_room_url' => $canJoin ? url("patient/consultation.php?id=" . $myAppt['id']) : null
    ];
}

echo json_encode([
    'success' => true,
    'doctor_id' => $doctorId,
    'date' => $date,
    'current_serving' => $queue['current_serving'],
    'current_serving_token' => $queue['current_serving_token'],
    'next_waiting_token' => $queue['next_waiting_token'],
    'waiting_count' => $queue['waiting_count'],
    'serving_count' => $queue['serving_count'],
    'completed_count' => $queue['completed_count'],
    'no_show_count' => $queue['no_show_count'],
    'total_tokens' => $queue['total_tokens'],
    'my_appointment' => $myStatus,
    'tokens' => $queue['tokens'],
    'timestamp' => date('Y-m-d H:i:s')
]);

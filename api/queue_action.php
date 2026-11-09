<?php
// ==============================================================================
// DOCTOR CONNECT - QUEUE ACTIONS API (DOCTOR & RECEPTIONIST)
// ==============================================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

// Must be Doctor or Admin
if (!isDoctor() && !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized action']);
    exit;
}

verifyCsrfOrDie();

$action = $_POST['action'] ?? '';
$doctorId = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : 0;
$appointmentId = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : null;

// If Doctor is calling and doctor_id was not explicitly passed, use their own doctor ID
if (isDoctor() && $doctorId <= 0) {
    $doctorId = (int)($_SESSION['doctor_id'] ?? 0);
}

if ($doctorId <= 0 && $action !== 'mark_served' && $action !== 'mark_no_show' && $action !== 'mark_serving') {
    echo json_encode(['success' => false, 'error' => 'Doctor ID required']);
    exit;
}

$db = Database::getConnection();

// Handle walk-in booking from reception or doctor console
if ($action === 'add_walkin') {
    $patientName = trim($_POST['patient_name'] ?? '');
    $patientPhone = trim($_POST['patient_phone'] ?? '');
    $reason = trim($_POST['reason'] ?? 'Walk-in consultation');
    $today = date('Y-m-d');

    if (empty($patientName)) {
        echo json_encode(['success' => false, 'error' => 'Patient name is required for walk-in']);
        exit;
    }

    // Find or create temporary walk-in patient account
    $email = 'walkin_' . time() . '_' . rand(100, 999) . '@clinic.local';
    $phone = !empty($patientPhone) ? $patientPhone : ('+1' . rand(1000000000, 9999999999));

    // Check if phone already belongs to a registered patient
    $stmtCheck = $db->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
    $stmtCheck->execute([$phone]);
    $existingUser = $stmtCheck->fetch();

    if ($existingUser) {
        $patientId = (int)$existingUser['id'];
    } else {
        $stmtUser = $db->prepare("
            INSERT INTO users (name, email, phone, password_hash, role)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmtUser->execute([
            $patientName,
            $email,
            $phone,
            hashPassword('walkin123'),
            ROLE_PATIENT
        ]);
        $patientId = (int)$db->lastInsertId();
    }

    // Generate race-safe token for today
    $result = bookAppointmentRaceSafe($db, $patientId, $doctorId, $today, $reason);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => "Walk-in Token #{$result['token_number']} issued successfully for {$patientName}.",
            'token_number' => $result['token_number'],
            'appointment_id' => $result['appointment_id']
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => $result['error']]);
    }
    exit;
}

// Handle standard queue transitions
$result = processQueueAction($db, $doctorId, $action, $appointmentId);
echo json_encode($result);

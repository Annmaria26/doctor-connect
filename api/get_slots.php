<?php
// ==============================================================================
// DOCTOR CONNECT - DYNAMIC SLOTS API
// Computes availability dynamically from schedule per Design Decision #2
// ==============================================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$date = $_GET['date'] ?? date('Y-m-d');

if ($doctorId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Doctor ID is required']);
    exit;
}

try {
    $db = Database::getConnection();

    // Fetch doctor info
    $stmtDoc = $db->prepare("
        SELECT d.*, u.name, u.email 
        FROM doctors d 
        JOIN users u ON u.id = d.user_id 
        WHERE d.id = ?
    ");
    $stmtDoc->execute([$doctorId]);
    $doctor = $stmtDoc->fetch();

    if (!$doctor) {
        echo json_encode(['success' => false, 'error' => 'Doctor not found']);
        exit;
    }

    $slotData = computeSlotsForDate($db, $doctorId, $date);
    $slotData['doctor'] = [
        'id' => (int)$doctor['id'],
        'name' => $doctor['name'],
        'specialization' => $doctor['specialization'],
        'consultation_fee' => (float)$doctor['consultation_fee'],
        'avg_consultation_mins' => (int)$doctor['avg_consultation_mins']
    ];

    echo json_encode([
        'success' => true,
        'data' => $slotData
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

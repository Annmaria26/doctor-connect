<?php
// ==============================================================================
// DOCTOR CONNECT - CSV EXPORT UTILITY (ADMIN)
// ==============================================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_ADMIN);

$db = Database::getConnection();

$doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$status = trim($_GET['status'] ?? '');
$fromDate = trim($_GET['from_date'] ?? date('Y-m-01'));
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

$stmt = $db->prepare("
    SELECT a.id, a.appointment_date, a.token_number, a.status, a.estimated_time, a.reason,
           a.diagnosis, a.prescription, a.created_at,
           u.name as patient_name, u.email as patient_email, u.phone as patient_phone,
           du.name as doctor_name, d.specialization, d.consultation_fee
    FROM appointments a
    JOIN users u ON u.id = a.patient_id
    JOIN doctors d ON d.id = a.doctor_id
    JOIN users du ON du.id = d.user_id
    WHERE {$whereClause}
    ORDER BY a.appointment_date DESC, a.token_number ASC
");
$stmt->execute($params);

$filename = "appointments_report_" . date('Y-m-d_His') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV Header
fputcsv($output, [
    'Appointment ID',
    'Date',
    'Token Number',
    'Status',
    'Estimated Meeting Time',
    'Patient Name',
    'Patient Email',
    'Patient Phone',
    'Doctor Name',
    'Specialization',
    'Fee ($)',
    'Chief Complaint',
    'Diagnosis',
    'Prescription',
    'Booking Created At'
]);

while ($row = $stmt->fetch()) {
    fputcsv($output, [
        $row['id'],
        $row['appointment_date'],
        $row['token_number'],
        strtoupper($row['status']),
        $row['estimated_time'],
        $row['patient_name'],
        $row['patient_email'],
        $row['patient_phone'],
        $row['doctor_name'],
        $row['specialization'],
        number_format((float)$row['consultation_fee'], 2),
        $row['reason'] ?? '',
        $row['diagnosis'] ?? '',
        str_replace(["\r\n", "\n"], "; ", $row['prescription'] ?? ''),
        $row['created_at']
    ]);
}

fclose($output);
exit;

<?php
// ==============================================================================
// DOCTOR CONNECT - AUTOMATED VERIFICATION TEST SUITE
// Tests all functional modules, race-safety, constraints, and queue state
// ==============================================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$passed = 0;
$failed = 0;

function assertTest(string $desc, bool $condition, string $details = ''): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$desc}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$desc}" . ($details ? " - {$details}" : "") . "\n";
    }
}

echo "==============================================================================\n";
echo "DOCTOR CONNECT - AUTOMATED TEST SUITE EXECUTION\n";
echo "==============================================================================\n\n";

$db = Database::getConnection();

// TEST 1: Database Tables Existence
echo "TEST SUITE 1: Schema & Tables Verification\n";
$tables = ['users', 'doctors', 'doctor_schedules', 'appointments', 'queue_logs', 'notifications', 'clinic_settings'];
foreach ($tables as $tbl) {
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM {$tbl}");
        $cnt = $stmt->fetchColumn();
        assertTest("Table `{$tbl}` exists and queryable (records: {$cnt})", true);
    } catch (Exception $e) {
        assertTest("Table `{$tbl}` exists", false, $e->getMessage());
    }
}

// TEST 2: Pre-seeded Users & Authentication Verification
echo "\nTEST SUITE 2: User Roles & Password Verification\n";
$testUsers = [
    'admin@doctorconnect.com' => ROLE_ADMIN,
    'sarah.jenkins@doctorconnect.com' => ROLE_DOCTOR,
    'john.doe@gmail.com' => ROLE_PATIENT
];
foreach ($testUsers as $email => $role) {
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $u = $stmt->fetch();
    assertTest("User `{$email}` exists with role `{$role}`", $u && $u['role'] === $role);
    assertTest("User `{$email}` verifies with password `password123`", $u && verifyPassword('password123', $u['password_hash']));
}

// TEST 3: Dynamic Slots Calculation (Design Decision #2)
echo "\nTEST SUITE 3: Dynamic Slot Computation\n";
$doctorId = 1;
$testDate = date('Y-m-d', strtotime('+1 day'));
$slots = computeSlotsForDate($db, $doctorId, $testDate);
assertTest("Slot computation returns valid structure", isset($slots['available']) && isset($slots['total_capacity']));
assertTest("Doctor has active shifts (capacity > 0)", $slots['total_capacity'] > 0, "Capacity: " . $slots['total_capacity']);
assertTest("Next token number computed sequentially", $slots['next_token'] >= 1, "Next token: " . $slots['next_token']);
assertTest("Estimated meeting time formatted properly", !empty($slots['next_estimated_time_formatted']));

// TEST 4: Race-Safe Token Booking (Design Decision #1 & Requirement 3.3)
echo "\nTEST SUITE 4: Race-Safe Token Allocation & UNIQUE Constraint\n";
$bookingDate = date('Y-m-d', strtotime('+30 days'));
// Clean up any test records for this future test date
$db->exec("DELETE FROM appointments WHERE appointment_date = '{$bookingDate}'");

// Patient 6 books first token
$res1 = bookAppointmentRaceSafe($db, 6, $doctorId, $bookingDate, "Test complaint 1");
assertTest("First booking allocated Token #1", $res1['success'] && $res1['token_number'] === 1, "Got token: " . ($res1['token_number'] ?? 'null'));

// Patient 7 books second token
$res2 = bookAppointmentRaceSafe($db, 7, $doctorId, $bookingDate, "Test complaint 2");
assertTest("Second booking allocated Token #2", $res2['success'] && $res2['token_number'] === 2, "Got token: " . ($res2['token_number'] ?? 'null'));

// Patient 8 books third token
$res3 = bookAppointmentRaceSafe($db, 8, $doctorId, $bookingDate, "Test complaint 3");
assertTest("Third booking allocated Token #3", $res3['success'] && $res3['token_number'] === 3, "Got token: " . ($res3['token_number'] ?? 'null'));

// Verify UNIQUE constraint prevents duplicate token insertion
try {
    $dupStmt = $db->prepare("
        INSERT INTO appointments (patient_id, doctor_id, appointment_date, token_number, status)
        VALUES (9, ?, ?, 1, 'waiting')
    ");
    $dupStmt->execute([$doctorId, $bookingDate]);
    assertTest("UNIQUE constraint rejects duplicate (doctor_id, date, token_number)", false, "Did not reject duplicate!");
} catch (Exception $e) {
    assertTest("UNIQUE constraint correctly rejected duplicate token #1 on same date", true);
}

// TEST 5: Estimated Meeting Time Calculation
echo "\nTEST SUITE 5: Estimated Meeting Time Formula Check\n";
// Estimated meeting time: session_start + (token_number - 1) * avg_consultation_mins
$estTime1 = calculateEstimatedMeetingTime($db, $doctorId, $bookingDate, 1);
$estTime2 = calculateEstimatedMeetingTime($db, $doctorId, $bookingDate, 2);
$diffMinutes = (strtotime($estTime2) - strtotime($estTime1)) / 60;
assertTest("Token #2 offset by exactly doctor's avg consultation mins (15 mins)", $diffMinutes == 15, "Diff was: {$diffMinutes} mins");

// TEST 6: Meeting Link Visibility Access Rules (Requirement 3.4)
echo "\nTEST SUITE 6: Meeting Link Unlock Logic\n";
// Fake appointment #1 in 'serving'
$apptServing = ['status' => STATUS_SERVING, 'token_number' => 1];
assertTest("Serving token has video meeting link UNLOCKED", isMeetingLinkUnlockedForPatient($apptServing, 1, 2) === true);

// Fake appointment #2 in 'waiting' and is NEXT
$apptNext = ['status' => STATUS_WAITING, 'token_number' => 2];
assertTest("Immediate next waiting token has video meeting link UNLOCKED", isMeetingLinkUnlockedForPatient($apptNext, 1, 2) === true);

// Fake appointment #3 in 'waiting' and is NOT next (behind token #2)
$apptBehind = ['status' => STATUS_WAITING, 'token_number' => 3];
assertTest("Token 2 positions behind has video meeting link LOCKED", isMeetingLinkUnlockedForPatient($apptBehind, 1, 2) === false);

// Cancelled appointment
$apptCancelled = ['status' => STATUS_CANCELLED, 'token_number' => 1];
assertTest("Cancelled appointment has video meeting link LOCKED", isMeetingLinkUnlockedForPatient($apptCancelled, 1, 2) === false);

// TEST 7: Queue State & Polling API Output
echo "\nTEST SUITE 7: Live Queue State Calculation\n";
$today = date('Y-m-d');
// Ensure at least one serving and one waiting token exists for today's test
$db->exec("UPDATE appointments SET status = 'serving' WHERE doctor_id = 1 AND appointment_date = '{$today}' AND token_number = 2");
$db->exec("UPDATE appointments SET status = 'waiting' WHERE doctor_id = 1 AND appointment_date = '{$today}' AND token_number >= 3");
$liveQueue = getDoctorLiveQueue($db, 1, $today);
assertTest("Live queue returns current serving token", isset($liveQueue['current_serving_token']));
assertTest("Live queue returns next waiting token", isset($liveQueue['next_waiting_token']));
assertTest("Live queue includes tokens list", is_array($liveQueue['tokens']) && count($liveQueue['tokens']) > 0);

// TEST 8: Queue Actions (Doctor & Reception advance)
echo "\nTEST SUITE 8: Queue Advancement & Audit Trail\n";
// Call next
$advanceResult = processQueueAction($db, 1, 'call_next');
assertTest("Calling next token updates serving state successfully", $advanceResult['success'] === true);

// Check audit log
$stmtAudit = $db->query("SELECT * FROM queue_logs ORDER BY id DESC LIMIT 1");
$latestAudit = $stmtAudit->fetch();
assertTest("Queue action logged in queue_logs audit trail", $latestAudit !== false && in_array($latestAudit['action'], ['called', 'serving', 'served', 'no_show']));

echo "\n==============================================================================\n";
echo "TEST RESULTS SUMMARY\n";
echo "Total Tests Executed: " . ($passed + $failed) . "\n";
echo "Tests Passed: {$passed}\n";
echo "Tests Failed: {$failed}\n";
echo "==============================================================================\n";

if ($failed === 0) {
    echo "ALL SUITES PASSED! System conforms to all requirements.\n";
    exit(0);
} else {
    echo "SOME TESTS FAILED.\n";
    exit(1);
}

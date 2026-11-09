<?php
// ==============================================================================
// DOCTOR CONNECT - CORE BUSINESS FUNCTIONS
// ==============================================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Fetch a clinic setting by key
 */
function getClinicSetting(PDO $db, string $key, string $default = ''): string {
    static $settingsCache = null;
    if ($settingsCache === null) {
        $settingsCache = [];
        try {
            $stmt = $db->query("SELECT setting_key, setting_value FROM clinic_settings");
            while ($row = $stmt->fetch()) {
                $settingsCache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            // Ignore if table not created yet
        }
    }
    return $settingsCache[$key] ?? $default;
}

/**
 * Get active schedule for a doctor on a specific calendar date
 */
function getDoctorScheduleForDate(PDO $db, int $doctorId, string $date): ?array {
    $dayOfWeek = (int)date('w', strtotime($date)); // 0 = Sun ... 6 = Sat
    $stmt = $db->prepare("
        SELECT ds.*, d.avg_consultation_mins, d.consultation_fee, u.name as doctor_name, d.specialization
        FROM doctor_schedules ds
        JOIN doctors d ON d.id = ds.doctor_id
        JOIN users u ON u.id = d.user_id
        WHERE ds.doctor_id = ? AND ds.day_of_week = ? AND ds.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$doctorId, $dayOfWeek]);
    $schedule = $stmt->fetch();
    return $schedule ?: null;
}

/**
 * Dynamically compute slots for a doctor on a given date
 * (Slots are computed, not stored statically, per design decision #2)
 */
function computeSlotsForDate(PDO $db, int $doctorId, string $date): array {
    $schedule = getDoctorScheduleForDate($db, $doctorId, $date);
    if (!$schedule) {
        return [
            'available' => false,
            'message' => 'The doctor has no scheduled consultation hours on this day.',
            'slots' => [],
            'total_capacity' => 0,
            'booked_count' => 0,
            'next_token' => null
        ];
    }

    $slotDuration = (int)($schedule['slot_duration'] > 0 ? $schedule['slot_duration'] : ($schedule['avg_consultation_mins'] ?: 15));
    $startTime = strtotime($date . ' ' . $schedule['start_time']);
    $endTime = strtotime($date . ' ' . $schedule['end_time']);

    $totalMins = ($endTime - $startTime) / 60;
    $maxTokens = max(1, (int)floor($totalMins / $slotDuration));

    // Get count of existing bookings for this doctor and date (excluding cancelled)
    $stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status != 'cancelled'");
    $stmt->execute([$doctorId, $date]);
    $bookedCount = (int)$stmt->fetchColumn();

    // Get highest token number booked so far
    $stmtMax = $db->prepare("SELECT COALESCE(MAX(token_number), 0) FROM appointments WHERE doctor_id = ? AND appointment_date = ?");
    $stmtMax->execute([$doctorId, $date]);
    $highestToken = (int)$stmtMax->fetchColumn();

    $nextToken = $highestToken + 1;
    $isAvailable = $bookedCount < $maxTokens;

    // Generate list of calculated slot times
    $slots = [];
    for ($i = 1; $i <= $maxTokens; $i++) {
        $slotTimeTs = $startTime + (($i - 1) * $slotDuration * 60);
        $slots[] = [
            'token_number' => $i,
            'time_formatted' => date('h:i A', $slotTimeTs),
            'datetime' => date('Y-m-d H:i:s', $slotTimeTs),
            'is_booked' => ($i <= $highestToken)
        ];
    }

    return [
        'available' => $isAvailable,
        'message' => $isAvailable ? 'Slots available' : 'Fully booked for this date',
        'schedule' => $schedule,
        'total_capacity' => $maxTokens,
        'booked_count' => $bookedCount,
        'remaining_slots' => max(0, $maxTokens - $bookedCount),
        'next_token' => $nextToken,
        'next_estimated_time' => date('Y-m-d H:i:s', $startTime + (($nextToken - 1) * $slotDuration * 60)),
        'next_estimated_time_formatted' => date('h:i A', $startTime + (($nextToken - 1) * $slotDuration * 60)),
        'slots' => $slots
    ];
}

/**
 * Calculate estimated meeting time based on token order:
 * Formula: session_start + (token_number - 1) * avg_consultation_mins
 */
function calculateEstimatedMeetingTime(PDO $db, int $doctorId, string $date, int $tokenNumber): string {
    $schedule = getDoctorScheduleForDate($db, $doctorId, $date);
    $startTimeStr = $schedule['start_time'] ?? '09:00:00';
    $avgMins = (int)($schedule['avg_consultation_mins'] ?? 15);
    if ($avgMins <= 0) $avgMins = 15;

    $baseTimestamp = strtotime($date . ' ' . $startTimeStr);
    $offsetSeconds = ($tokenNumber - 1) * $avgMins * 60;
    $estimatedTimestamp = $baseTimestamp + $offsetSeconds;

    return date('Y-m-d H:i:s', $estimatedTimestamp);
}

/**
 * Generate a secure, unique Jitsi Meet room link for an appointment
 */
function generateMeetingLink(int $appointmentId): string {
    $salt = 'dc_telehealth_' . $appointmentId . '_salt99';
    $hash = substr(hash('sha256', $salt), 0, 8);
    $roomName = "doctor-connect-appt-{$appointmentId}-{$hash}";
    return "https://meet.jit.si/{$roomName}";
}

/**
 * RACE-SAFE Token Generation & Appointment Booking
 * 
 * Enforced by MySQL UNIQUE constraint: (doctor_id, appointment_date, token_number).
 * Uses transaction with FOR UPDATE locking and retry loop to guarantee zero race-condition collisions.
 */
function bookAppointmentRaceSafe(
    PDO $db,
    int $patientId,
    int $doctorId,
    string $date,
    ?string $reason = null,
    int $maxRetries = 5
): array {
    $attempts = 0;
    $lastError = null;

    while ($attempts < $maxRetries) {
        $attempts++;
        try {
            $db->beginTransaction();

            // 1. Lock and find the current max token for this doctor on this date
            // FOR UPDATE is used for MySQL row locking
            $forUpdate = (Database::getDriver() === 'sqlite') ? '' : ' FOR UPDATE';
            $stmt = $db->prepare("
                SELECT COALESCE(MAX(token_number), 0) AS max_token 
                FROM appointments 
                WHERE doctor_id = ? AND appointment_date = ?
                {$forUpdate}
            ");
            $stmt->execute([$doctorId, $date]);
            $currentMax = (int)$stmt->fetchColumn();
            $nextToken = $currentMax + 1;

            // 2. Compute estimated meeting time
            $estimatedTime = calculateEstimatedMeetingTime($db, $doctorId, $date, $nextToken);

            // 3. Insert new appointment with initial placeholder or temporary link
            $stmtInsert = $db->prepare("
                INSERT INTO appointments 
                (patient_id, doctor_id, appointment_date, token_number, status, estimated_time, reason)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtInsert->execute([
                $patientId,
                $doctorId,
                $date,
                $nextToken,
                STATUS_WAITING,
                $estimatedTime,
                $reason
            ]);

            $appointmentId = (int)$db->lastInsertId();

            // 4. Generate persistent Jitsi meeting link for this appointment
            $meetingLink = generateMeetingLink($appointmentId);
            $stmtUpdateLink = $db->prepare("UPDATE appointments SET meeting_link = ? WHERE id = ?");
            $stmtUpdateLink->execute([$meetingLink, $appointmentId]);

            // 5. Commit transaction
            $db->commit();

            // 6. Log in queue audit trail
            logQueueAction($db, $appointmentId, 'called'); // initial entry

            // 7. Add in-app notification for patient
            createNotification(
                $db,
                $patientId,
                "Token #{$nextToken} confirmed for Dr. on {$date}. Estimated time: " . date('h:i A', strtotime($estimatedTime))
            );

            return [
                'success' => true,
                'appointment_id' => $appointmentId,
                'token_number' => $nextToken,
                'estimated_time' => $estimatedTime,
                'meeting_link' => $meetingLink
            ];
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            // MySQL Error 1062 = Duplicate entry (UNIQUE KEY violation)
            // Or SQLSTATE 23000
            if ($e->getCode() == 23000 || strpos($e->getMessage(), '1062') !== false || strpos($e->getMessage(), 'UNIQUE') !== false) {
                // Race condition collision detected! Back off briefly and retry.
                usleep(random_int(10000, 50000)); // 10ms - 50ms jitter
                $lastError = "Token slot race condition resolved, retrying...";
                continue;
            }

            // Other database exception
            return [
                'success' => false,
                'error' => "Booking failed: " . $e->getMessage()
            ];
        }
    }

    return [
        'success' => false,
        'error' => "Could not assign token due to high concurrency. Please try again."
    ];
}

/**
 * Log action in queue_logs audit trail
 */
function logQueueAction(PDO $db, int $appointmentId, string $action): void {
    try {
        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare("INSERT INTO queue_logs (appointment_id, action, action_time) VALUES (?, ?, ?)");
        $stmt->execute([$appointmentId, $action, $now]);
    } catch (Exception $e) {
        // Audit log shouldn't crash caller
        error_log("Failed to insert queue_log: " . $e->getMessage());
    }
}

/**
 * Create user in-app notification
 */
function createNotification(PDO $db, int $userId, string $message): void {
    try {
        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, 0, ?)");
        $stmt->execute([$userId, $message, $now]);
    } catch (Exception $e) {
        error_log("Failed to insert notification: " . $e->getMessage());
    }
}

/**
 * Get comprehensive live queue state for a doctor on a specific date
 */
function getDoctorLiveQueue(PDO $db, int $doctorId, string $date): array {
    // 1. Fetch current serving appointment
    $stmtServing = $db->prepare("
        SELECT a.*, u.name as patient_name, u.phone as patient_phone, u.email as patient_email
        FROM appointments a
        JOIN users u ON u.id = a.patient_id
        WHERE a.doctor_id = ? AND a.appointment_date = ? AND a.status = ?
        ORDER BY a.token_number ASC
        LIMIT 1
    ");
    $stmtServing->execute([$doctorId, $date, STATUS_SERVING]);
    $currentServing = $stmtServing->fetch() ?: null;

    // 2. Fetch the immediate NEXT waiting appointment
    $stmtNext = $db->prepare("
        SELECT a.*, u.name as patient_name, u.phone as patient_phone, u.email as patient_email
        FROM appointments a
        JOIN users u ON u.id = a.patient_id
        WHERE a.doctor_id = ? AND a.appointment_date = ? AND a.status = ?
        ORDER BY a.token_number ASC
        LIMIT 1
    ");
    $stmtNext->execute([$doctorId, $date, STATUS_WAITING]);
    $nextWaiting = $stmtNext->fetch() ?: null;

    // 3. Counts by status
    $stmtCounts = $db->prepare("
        SELECT 
            status, 
            COUNT(*) as cnt 
        FROM appointments 
        WHERE doctor_id = ? AND appointment_date = ? 
        GROUP BY status
    ");
    $stmtCounts->execute([$doctorId, $date]);
    $counts = [
        STATUS_WAITING => 0,
        STATUS_SERVING => 0,
        STATUS_COMPLETED => 0,
        STATUS_NO_SHOW => 0,
        STATUS_CANCELLED => 0
    ];
    while ($row = $stmtCounts->fetch()) {
        $counts[$row['status']] = (int)$row['cnt'];
    }

    // 4. Full list of today's appointments for this doctor
    $stmtAll = $db->prepare("
        SELECT a.*, u.name as patient_name, u.phone as patient_phone, u.email as patient_email
        FROM appointments a
        JOIN users u ON u.id = a.patient_id
        WHERE a.doctor_id = ? AND a.appointment_date = ?
        ORDER BY a.token_number ASC
    ");
    $stmtAll->execute([$doctorId, $date]);
    $allTokens = $stmtAll->fetchAll();

    return [
        'doctor_id' => $doctorId,
        'date' => $date,
        'current_serving' => $currentServing,
        'current_serving_token' => $currentServing ? (int)$currentServing['token_number'] : null,
        'next_waiting' => $nextWaiting,
        'next_waiting_token' => $nextWaiting ? (int)$nextWaiting['token_number'] : null,
        'waiting_count' => $counts[STATUS_WAITING],
        'serving_count' => $counts[STATUS_SERVING],
        'completed_count' => $counts[STATUS_COMPLETED],
        'no_show_count' => $counts[STATUS_NO_SHOW],
        'total_tokens' => count($allTokens),
        'tokens' => $allTokens
    ];
}

/**
 * Determine if video meeting link should be accessible to a patient:
 * "CRITICAL REQUIREMENT 3.4: Link visible to patient only when token is NEXT or SERVING."
 */
function isMeetingLinkUnlockedForPatient(array $appointment, ?int $currentServingToken, ?int $nextWaitingToken): bool {
    // If appointment is cancelled or no_show, never accessible
    if (in_array($appointment['status'], [STATUS_CANCELLED, STATUS_NO_SHOW])) {
        return false;
    }

    // 1. If currently SERVING -> UNLOCKED
    if ($appointment['status'] === STATUS_SERVING) {
        return true;
    }

    // 2. If status is WAITING and this is the NEXT in line -> UNLOCKED
    if ($appointment['status'] === STATUS_WAITING && (int)$appointment['token_number'] === $nextWaitingToken) {
        return true;
    }

    // Otherwise, locked!
    return false;
}

/**
 * Check if appointment can be cancelled by patient
 * (e.g. Cannot cancel within 2 hours of estimated time, and cannot cancel if already serving/completed)
 */
function canCancelAppointment(array $appointment, int $cancelWindowHours = 2): array {
    if (in_array($appointment['status'], [STATUS_SERVING, STATUS_COMPLETED, STATUS_NO_SHOW, STATUS_CANCELLED])) {
        return [
            'allowed' => false,
            'message' => 'Appointment cannot be cancelled in its current state (' . $appointment['status'] . ').'
        ];
    }

    $estimatedTime = strtotime($appointment['estimated_time'] ?? ($appointment['appointment_date'] . ' 09:00:00'));
    $now = time();
    $diffSeconds = $estimatedTime - $now;
    $minSeconds = $cancelWindowHours * 3600;

    if ($diffSeconds < $minSeconds && $diffSeconds > 0) {
        return [
            'allowed' => false,
            'message' => "Appointments cannot be cancelled within {$cancelWindowHours} hours of the estimated time."
        ];
    }

    return ['allowed' => true, 'message' => 'Cancellation permitted'];
}

/**
 * Advance Queue: Action Handler (Doctor / Receptionist)
 * Actions: 'call_next', 'mark_serving', 'mark_served', 'mark_no_show'
 */
function processQueueAction(PDO $db, int $doctorId, string $action, ?int $appointmentId = null): array {
    $today = date('Y-m-d');

    if ($action === 'call_next') {
        // If there is an existing appointment in 'serving', mark it completed first or check
        $stmtServing = $db->prepare("
            SELECT id, token_number FROM appointments 
            WHERE doctor_id = ? AND appointment_date = ? AND status = ? 
            LIMIT 1
        ");
        $stmtServing->execute([$doctorId, $today, STATUS_SERVING]);
        $currentlyServing = $stmtServing->fetch();

        if ($currentlyServing) {
            // Mark previously serving as completed
            $stmtUp = $db->prepare("UPDATE appointments SET status = ? WHERE id = ?");
            $stmtUp->execute([STATUS_COMPLETED, $currentlyServing['id']]);
            logQueueAction($db, $currentlyServing['id'], 'served');
        }

        // Now find the lowest waiting token
        $stmtNext = $db->prepare("
            SELECT id, token_number, patient_id FROM appointments 
            WHERE doctor_id = ? AND appointment_date = ? AND status = ? 
            ORDER BY token_number ASC 
            LIMIT 1
        ");
        $stmtNext->execute([$doctorId, $today, STATUS_WAITING]);
        $nextAppt = $stmtNext->fetch();

        if (!$nextAppt) {
            return ['success' => false, 'message' => 'No more waiting patients in today\'s queue.'];
        }

        // Set to serving
        $stmtSetServing = $db->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        $stmtSetServing->execute([STATUS_SERVING, $nextAppt['id']]);
        logQueueAction($db, $nextAppt['id'], 'called');
        logQueueAction($db, $nextAppt['id'], 'serving');

        createNotification($db, $nextAppt['patient_id'], "Doctor is calling Token #{$nextAppt['token_number']}! Please join the consultation now.");

        return [
            'success' => true,
            'message' => "Token #{$nextAppt['token_number']} is now being served.",
            'appointment_id' => $nextAppt['id'],
            'token_number' => $nextAppt['token_number']
        ];
    }

    if ($action === 'mark_serving' && $appointmentId) {
        $stmt = $db->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        $stmt->execute([STATUS_SERVING, $appointmentId]);
        logQueueAction($db, $appointmentId, 'serving');
        return ['success' => true, 'message' => 'Status updated to Serving.'];
    }

    if ($action === 'mark_served' && $appointmentId) {
        $stmt = $db->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        $stmt->execute([STATUS_COMPLETED, $appointmentId]);
        logQueueAction($db, $appointmentId, 'served');
        return ['success' => true, 'message' => 'Consultation marked as Completed.'];
    }

    if ($action === 'mark_no_show' && $appointmentId) {
        $stmt = $db->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        $stmt->execute([STATUS_NO_SHOW, $appointmentId]);
        logQueueAction($db, $appointmentId, 'no_show');
        return ['success' => true, 'message' => 'Patient marked as No-Show.'];
    }

    return ['success' => false, 'message' => 'Invalid queue action requested.'];
}

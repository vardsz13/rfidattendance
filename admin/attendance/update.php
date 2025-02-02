<?php
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth_functions.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: daily.php');
    exit();
}

$db = getDatabase();
$userId = $_POST['user_id'] ?? null;
$date = $_POST['date'] ?? null;
$overrideStatus = $_POST['override_status'] ?? null;
$overrideRemarks = $_POST['override_remarks'] ?? null;

try {
    if (!$userId || !$date) {
        throw new Exception('Missing required fields');
    }

    $db->connect()->beginTransaction();

    // Get user's RFID assignment
    $assignment = $db->single(
        "SELECT ra.id 
         FROM rfid_assignments ra 
         WHERE ra.user_id = ? AND ra.is_active = 1",
        [$userId]
    );

    if (!$assignment) {
        throw new Exception('No active RFID assignment found for user');
    }

    // Check for existing attendance record
    $existingLog = $db->single(
        "SELECT id, time_in, time_out, status 
         FROM attendance_logs 
         WHERE assignment_id = ? AND DATE(log_date) = ?",
        [$assignment['id'], $date]
    );

    if ($existingLog) {
        // Update existing record with override
        $updateData = [
            'override_status' => $overrideStatus ?: null,
            'override_remarks' => $overrideRemarks ?: null
        ];

        // If removing override (override_status is empty), keep original status
        // If adding override, keep RFID-based status for record keeping
        $db->update('attendance_logs', $updateData, ['id' => $existingLog['id']]);
    } else {
        // Create new record for the override
        $logData = [
            'assignment_id' => $assignment['id'],
            'user_id' => $userId,
            'log_date' => $date,
            'status' => 'absent', // Default status when no RFID scan
            'override_status' => $overrideStatus,
            'override_remarks' => $overrideRemarks
        ];

        $db->insert('attendance_logs', $logData);
    }

    $db->connect()->commit();
    flashMessage('Attendance override updated successfully');

} catch (Exception $e) {
    $db->connect()->rollBack();
    error_log("Attendance Override Error: " . $e->getMessage());
    flashMessage('Error updating attendance override: ' . $e->getMessage(), 'error');
}

// Redirect back to daily view
header('Location: daily.php?date=' . $date);
exit();
<?php
// api/devices.php
require_once '../includes/functions.php';
header('Content-Type: application/json');

// Only accept POST requests from devices
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

$db = getDatabase();

// Receive and validate device data
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['rfid_uid'])) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid data format']));
}

try {
    // Begin transaction
    $db->connect()->beginTransaction();
    
    // Get RFID assignment
    $assignment = $db->single(
        "SELECT ra.id, ra.user_id, u.name 
         FROM rfid_assignments ra
         JOIN rfid_cards rc ON ra.rfid_id = rc.id
         JOIN users u ON ra.user_id = u.id
         WHERE rc.rfid_uid = ? AND ra.is_active = true",
        [$data['rfid_uid']]
    );

    if (!$assignment) {
        throw new Exception('Unregistered or inactive RFID card');
    }

    $currentDate = date('Y-m-d');
    
    // Get the last log for today (if any)
    $lastLog = $db->single(
        "SELECT log_type, log_time 
         FROM attendance_logs 
         WHERE assignment_id = ? AND attendance_date = ?
         ORDER BY log_time DESC LIMIT 1",
        [$assignment['id'], $currentDate]
    );

    // Determine if this should be an IN or OUT log
    $logType = (!$lastLog || $lastLog['log_type'] === 'out') ? 'in' : 'out';

    // For IN logs, check if it's late
    $status = null;
    if ($logType === 'in') {
        // Get late time setting
        $lateSetting = $db->single(
            "SELECT setting_value FROM system_settings WHERE setting_key = 'late_time'"
        );
        $lateTime = $lateSetting ? $lateSetting['setting_value'] : '09:00:00';
        $currentTime = date('H:i:s');
        $status = strtotime($currentTime) <= strtotime($lateTime) ? 'on_time' : 'late';
    }

    // Create attendance log
    $logData = [
        'assignment_id' => $assignment['id'],
        'log_time' => date('Y-m-d H:i:s'),
        'log_type' => $logType,
        'status' => $status
    ];

    if (!$db->insert('attendance_logs', $logData)) {
        throw new Exception('Failed to create attendance log');
    }

    // Determine buzzer tone
    $buzzerTone = 'SUCCESS_TONE';
    if ($logType === 'in' && $status === 'late') {
        $buzzerTone = 'LATE_TONE';
    }

    // Create device log
    $deviceLogData = [
        'rfid_uid' => $data['rfid_uid'],
        'log_type' => $logType,
        'buzzer_tone' => $buzzerTone,
        'status' => 'success'
    ];
    
    if (!$db->insert('device_logs', $deviceLogData)) {
        throw new Exception('Failed to create device log');
    }

    $db->connect()->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Attendance recorded successfully',
        'user_name' => $assignment['name'],
        'log_type' => $logType,
        'buzzer_tone' => $buzzerTone,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    if ($db->connect()->inTransaction()) {
        $db->connect()->rollBack();
    }

    $errorMessage = $e->getMessage();
    error_log("Device data processing error: " . $errorMessage);
    
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $errorMessage,
        'buzzer_tone' => 'ERROR_TONE'
    ]);
}
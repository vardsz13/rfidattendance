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
if (!$data) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid data format']));
}

// Required fields
$required_fields = ['rfid_uid'];
foreach ($required_fields as $field) {
    if (!isset($data[$field])) {
        http_response_code(400);
        exit(json_encode(['error' => "Missing required field: $field"]));
    }
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

    // Get late time setting
    $lateSetting = $db->single(
        "SELECT setting_value FROM system_settings WHERE setting_key = 'late_time'"
    );
    $lateTime = $lateSetting ? $lateSetting['setting_value'] : '09:00:00';
    
    $currentTime = date('H:i:s');
    $currentDate = date('Y-m-d');

    if ($data['log_type'] === 'in') {
        // Check if already logged in today
        $existingTimeIn = $db->single(
            "SELECT id FROM time_in_logs 
             WHERE assignment_id = ? AND attendance_date = ?",
            [$assignment['id'], $currentDate]
        );

        if ($existingTimeIn) {
            throw new Exception('Already logged in today');
        }

        // Create time in log
        $timeInData = [
            'assignment_id' => $assignment['id'],
            'time_in' => date('Y-m-d H:i:s'),
            'status' => strtotime($currentTime) <= strtotime($lateTime) ? 'on_time' : 'late'
        ];

        if (!$db->insert('time_in_logs', $timeInData)) {
            throw new Exception('Failed to create time in log');
        }

        $message = $timeInData['status'] === 'on_time' ? 'Welcome!' : 'You are late!';
    } else {
        // For time out, verify there's a time in record for today
        $timeInRecord = $db->single(
            "SELECT id FROM time_in_logs 
             WHERE assignment_id = ? AND attendance_date = ?",
            [$assignment['id'], $currentDate]
        );

        if (!$timeInRecord) {
            throw new Exception('Must log in before logging out');
        }

        // Check if already logged out
        $existingTimeOut = $db->single(
            "SELECT id FROM time_out_logs 
             WHERE assignment_id = ? AND attendance_date = ?",
            [$assignment['id'], $currentDate]
        );

        if ($existingTimeOut) {
            throw new Exception('Already logged out today');
        }

        // Create time out log
        $timeOutData = [
            'assignment_id' => $assignment['id'],
            'time_out' => date('Y-m-d H:i:s')
        ];

        if (!$db->insert('time_out_logs', $timeOutData)) {
            throw new Exception('Failed to create time out log');
        }

        $message = 'Goodbye!';
    }

    // Create device log
    $deviceLogData = [
        'verification_type' => $data['verification_type'],
        'rfid_uid' => $data['rfid_uid'],
        'lcd_message' => $assignment['name'] . ' - ' . $message,
        'buzzer_tone' => 'SUCCESS_TONE',
        'status' => 'success'
    ];
    
    $logId = $db->insert('device_logs', $deviceLogData);
    if (!$logId) {
        throw new Exception('Failed to create device log');
    }

    $db->connect()->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Attendance recorded successfully',
        'user_name' => $assignment['name'],
        'lcd_message' => $assignment['name'] . ' - ' . $message,
        'buzzer_tone' => 'SUCCESS_TONE',
        'log_type' => $data['log_type']
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
        'lcd_message' => 'Error: ' . $errorMessage,
        'buzzer_tone' => 'ERROR_TONE'
    ]);
}
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

// Required fields for device log
$required_fields = ['verification_type', 'lcd_message', 'buzzer_tone', 'status'];
foreach ($required_fields as $field) {
    if (!isset($data[$field])) {
        http_response_code(400);
        exit(json_encode(['error' => "Missing required field: $field"]));
    }
}

try {
    // Begin transaction
    $db->connect()->beginTransaction();
    
    // Insert device log
    $log_data = [
        'verification_type' => $data['verification_type'],
        'rfid_uid' => $data['rfid_uid'] ?? null,
        'lcd_message' => $data['lcd_message'],
        'buzzer_tone' => $data['buzzer_tone'],
        'status' => $data['status']
    ];
    
    $log_id = $db->insert('device_logs', $log_data);
    if (!$log_id) {
        throw new Exception("Failed to insert device log");
    }

    $response = ['success' => false, 'message' => '', 'log_id' => $log_id];

    // Handle RFID verification
    if ($data['verification_type'] === 'rfid' && isset($data['rfid_uid'])) {
        // Get late time setting
        $lateSetting = $db->single(
            "SELECT setting_value FROM system_settings WHERE setting_key = 'late_time'"
        );
        $lateTime = $lateSetting ? $lateSetting['setting_value'] : '09:00:00';

        // Check if RFID is registered
        $verification = $db->single(
            "SELECT vd.user_id, u.name 
             FROM verification_data vd
             JOIN users u ON vd.user_id = u.id
             WHERE vd.rfid_uid = ? AND vd.is_active = 1",
            [$data['rfid_uid']]
        );

        if ($verification) {
            // RFID is registered to a user
            $currentTime = date('H:i:s');
            $userId = $verification['user_id'];
            
            // Check if attendance already exists for today
            $existingAttendance = $db->single(
                "SELECT id FROM attendance_logs 
                 WHERE user_id = ? AND attendance_date = CURRENT_DATE",
                [$userId]
            );

            if (!$existingAttendance) {
                // Determine if on time or late based on setting
                $status = (strtotime($currentTime) <= strtotime($lateTime)) ? 'on_time' : 'late';
                
                // Create attendance record
                $attendance_data = [
                    'user_id' => $userId,
                    'time_in' => date('Y-m-d H:i:s'),
                    'rfid_log_id' => $log_id,
                    'status' => $status
                ];
                
                if ($db->insert('attendance_logs', $attendance_data)) {
                    // Update device log
                    $statusMessage = $status === 'on_time' ? 'Welcome!' : 'You are late!';
                    $db->update('device_logs', [
                        'status' => 'success',
                        'lcd_message' => $verification['name'] . ' - ' . $statusMessage,
                        'buzzer_tone' => $status === 'on_time' ? 'SUCCESS_TONE' : 'ALERT_TONE'
                    ], ['id' => $log_id]);

                    $response = [
                        'success' => true,
                        'message' => 'Attendance recorded',
                        'user_name' => $verification['name'],
                        'status' => $status,
                        'log_id' => $log_id
                    ];
                }
            } else {
                // Already checked in today
                $db->update('device_logs', [
                    'status' => 'failed',
                    'lcd_message' => 'Already checked in',
                    'buzzer_tone' => 'ERROR_TONE'
                ], ['id' => $log_id]);

                $response['message'] = 'Already checked in today';
            }
        } else {
            // Unregistered RFID
            $db->update('device_logs', [
                'status' => 'pending',
                'lcd_message' => 'Unregistered RFID',
                'buzzer_tone' => 'ERROR_TONE'
            ], ['id' => $log_id]);

            $response['message'] = 'Unregistered RFID';
        }
    }

    $db->connect()->commit();
    echo json_encode($response);

} catch (Exception $e) {
    $db->connect()->rollBack();
    error_log("Device data processing error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

// api/devices.php


// Handles physical device communication
// Accepts device data (RFID scans, LCD messages, buzzer tones)
// Records device logs and attendance
// No authentication required (device-to-server)
// Used by Arduino/ESP8266
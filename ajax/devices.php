<?php
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
$required_fields = ['verification_type', 'lcd_display', 'buzzer_sound', 'status'];
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
        'fingerprint_id' => $data['fingerprint_id'] ?? null,
        'lcd_display' => $data['lcd_display'],
        'buzzer_sound' => $data['buzzer_sound'],
        'status' => $data['status']
    ];
    
    $log_id = $db->insert('device_logs', $log_data);
    if (!$log_id) {
        throw new Exception("Failed to insert device log");
    }

    // If both verifications are successful, create attendance record
    if ($data['status'] === 'success' && 
        isset($data['rfid_uid']) && 
        isset($data['fingerprint_id'])) {
        
        // Get user_id from verification_data
        $user = $db->single(
            "SELECT user_id FROM verification_data 
             WHERE rfid_uid = ? AND fingerprint_id = ? AND is_active = 1",
            [$data['rfid_uid'], $data['fingerprint_id']]
        );

        if ($user) {
            // Check if attendance already exists for today
            $existing = $db->single(
                "SELECT id FROM attendance 
                 WHERE user_id = ? AND DATE(attendance_date) = CURRENT_DATE",
                [$user['user_id']]
            );

            if (!$existing) {
                // Create new attendance record
                $attendance_data = [
                    'user_id' => $user['user_id'],
                    'rfid_log_id' => $log_id,
                    'fingerprint_log_id' => $log_id,
                    'attendance_date' => date('Y-m-d'),
                    'time_in' => date('Y-m-d H:i:s')
                ];
                
                if (!$db->insert('attendance', $attendance_data)) {
                    throw new Exception("Failed to create attendance record");
                }
            }
        }
    }

    $db->connect()->commit();
    echo json_encode(['success' => true, 'log_id' => $log_id]);

} catch (Exception $e) {
    $db->connect()->rollBack();
    error_log("Device data processing error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Double authentication (RFID + Fingerprint)
 * Logs all verification attempts
 * Creates attendance records only when both verifications succeed
 * Handles LCD display messages and buzzer tones
 * Provides proper error handling and transaction management
 */
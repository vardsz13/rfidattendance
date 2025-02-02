<?php
// api/devices.php
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/duration_helper.php';

header('Content-Type: application/json');

// Handle mode check request
if (isset($_GET['check_mode'])) {
    $mode = $db->single(
        "SELECT setting_value FROM system_settings WHERE setting_key = 'device_mode'"
    );
    echo json_encode(['mode' => $mode['setting_value'] ?? 'scan']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode([
        'status' => 'error',
        'lcd_message' => LCD_MESSAGES['ERROR'],
        'buzzer_tone' => BUZZER_TONES['ERROR']
    ]));
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['rfid_uid'])) {
        throw new Exception('Invalid request data');
    }

    $db = getDatabase();

    // Get current device mode from database
    $deviceMode = $db->single(
        "SELECT setting_value FROM system_settings WHERE setting_key = 'device_mode'"
    )['setting_value'] ?? 'scan';

    // Get late time setting
    $lateTime = $db->single(
        "SELECT setting_value FROM system_settings WHERE setting_key = 'late_time'"
    )['setting_value'] ?? '09:00:00';

    $db->connect()->beginTransaction();
    $currentTime = date('Y-m-d H:i:s');
    
    // First check if RFID exists in rfid_cards
    $existingCard = $db->single(
        "SELECT rc.*, ra.id as assignment_id, ra.is_active 
         FROM rfid_cards rc
         LEFT JOIN rfid_assignments ra ON rc.id = ra.rfid_id
         WHERE rc.rfid_uid = ?",
        [$data['rfid_uid']]
    );

    if ($deviceMode === 'register') {
        // Handle registration mode (same as before)
        // ...
    } else {
        // Handle scan mode
        if (!$existingCard) {
            echo json_encode([
                'status' => 'error',
                'lcd_message' => LCD_MESSAGES['RFID_FAILED'],
                'buzzer_tone' => BUZZER_TONES['ERROR'],
                'message' => 'Unregistered RFID'
            ]);
            $db->connect()->commit();
            exit();
        }

        if (!$existingCard['assignment_id'] || !$existingCard['is_active']) {
            echo json_encode([
                'status' => 'error',
                'lcd_message' => LCD_MESSAGES['RFID_FAILED'],
                'buzzer_tone' => BUZZER_TONES['ERROR'],
                'message' => 'Unassigned RFID'
            ]);
            $db->connect()->commit();
            exit();
        }

        // Get user assignment details
        $assignment = $db->single(
            "SELECT ra.*, u.id as user_id, u.name, u.username 
             FROM rfid_assignments ra
             JOIN users u ON ra.user_id = u.id
             WHERE ra.id = ? AND ra.is_active = true",
            [$existingCard['assignment_id']]
        );

        if (!$assignment) {
            throw new Exception('Invalid or inactive assignment');
        }

        // Check existing attendance log
        $existingLog = $db->single(
            "SELECT * FROM attendance_logs 
             WHERE assignment_id = ? AND DATE(log_date) = CURRENT_DATE
             ORDER BY time_in DESC LIMIT 1",
            [$assignment['id']]
        );

        // Function to determine status based on time
        $determineStatus = function($timeString) use ($lateTime) {
            $scanTime = strtotime($timeString);
            $lateTimeToday = strtotime(date('Y-m-d') . ' ' . $lateTime);
            return $scanTime <= $lateTimeToday ? 'on_time' : 'late';
        };

        if (!$existingLog || ($existingLog && $existingLog['time_out'])) {
            // Create new time-in record
            $status = $determineStatus($currentTime);
            
            $logData = [
                'assignment_id' => $assignment['id'],
                'user_id' => $assignment['user_id'],
                'time_in' => $currentTime,
                'status' => $status
            ];

            if ($db->insert('attendance_logs', $logData)) {
                echo json_encode([
                    'status' => 'success',
                    'lcd_message' => $status === 'late' ? LCD_MESSAGES['LATE'] : LCD_MESSAGES['ON_TIME'],
                    'buzzer_tone' => $status === 'late' ? BUZZER_TONES['LATE'] : BUZZER_TONES['SUCCESS'],
                    'user_name' => $assignment['name'],
                    'timestamp' => $currentTime,
                    'log_type' => 'in',
                    'status' => $status
                ]);
            } else {
                throw new Exception('Failed to record time-in');
            }
        } else {
            // Handle time-out record
            $duration = strtotime($currentTime) - strtotime($existingLog['time_in']);
            
            $updateData = [
                'time_out' => $currentTime,
                'duration_seconds' => $duration
            ];
            
            if ($db->update('attendance_logs', $updateData, ['id' => $existingLog['id']])) {
                $durationFormatted = getVerboseDuration($duration);
                echo json_encode([
                    'status' => 'success',
                    'lcd_message' => LCD_MESSAGES['TIME_OUT'],
                    'duration_message' => "Duration: " . $durationFormatted,
                    'buzzer_tone' => BUZZER_TONES['SUCCESS'],
                    'user_name' => $assignment['name'],
                    'timestamp' => $currentTime,
                    'log_type' => 'out'
                ]);
            } else {
                throw new Exception('Failed to record time-out');
            }
        }
    }

    $db->connect()->commit();

} catch (Exception $e) {
    if ($db->connect()->inTransaction()) {
        $db->connect()->rollBack();
    }
    
    error_log("Device API Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'lcd_message' => LCD_MESSAGES['ERROR'],
        'buzzer_tone' => BUZZER_TONES['ERROR']
    ]);
}
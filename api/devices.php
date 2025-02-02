<?php
// api/devices.php
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/duration_helper.php';

header('Content-Type: application/json');

// Get database connection
$db = getDatabase();

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

    // Get current device mode from database
    $deviceMode = $db->single(
        "SELECT setting_value FROM system_settings WHERE setting_key = 'device_mode'"
    )['setting_value'] ?? 'scan';

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
        // Handle registration mode
        if (!$existingCard) {
            $cardData = [
                'rfid_uid' => $data['rfid_uid'],
                'registered_at' => $currentTime
            ];
            
            if ($db->insert('rfid_cards', $cardData)) {
                echo json_encode([
                    'status' => 'success',
                    'lcd_message' => LCD_MESSAGES['RFID_REGISTERED'],
                    'buzzer_tone' => BUZZER_TONES['SUCCESS']
                ]);
            } else {
                throw new Exception('Failed to register RFID');
            }
        } else {
            echo json_encode([
                'status' => 'error',
                'lcd_message' => LCD_MESSAGES['RFID_EXISTS'],
                'buzzer_tone' => BUZZER_TONES['ERROR']
            ]);
        }
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

        // Get late time setting
        $lateTime = $db->single(
            "SELECT setting_value FROM system_settings WHERE setting_key = 'late_time'"
        )['setting_value'] ?? '09:00:00';

        // Check existing attendance log
        $existingLog = $db->single(
            "SELECT * FROM attendance_logs 
             WHERE user_id = ? AND DATE(time_in) = CURRENT_DATE
             ORDER BY time_in DESC LIMIT 1",
            [$assignment['user_id']]
        );

        if (!$existingLog || ($existingLog && $existingLog['time_out'])) {
            // Create new time-in record
            $status = strtotime(date('H:i:s')) <= strtotime($lateTime) ? 'on_time' : 'late';
            
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
            // Calculate duration for display
            $duration = strtotime($currentTime) - strtotime($existingLog['time_in']);
            $durationFormatted = getVerboseDuration($duration);
            
            // Update existing record with time-out and duration
            $updateData = [
                'time_out' => $currentTime,
                'duration_seconds' => $duration
            ];
            
            if ($db->update('attendance_logs', $updateData, ['id' => $existingLog['id']])) {
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
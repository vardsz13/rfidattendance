<?php
// api/devices.php
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json');

$db = getDatabase();
$response = ['status' => 'error', 'message' => 'Invalid request'];

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        throw new Exception('Invalid data received');
    }

    // Get current device mode
    $mode = $db->single(
        "SELECT setting_value FROM system_settings WHERE setting_key = 'device_mode'"
    )['setting_value'] ?? 'scan';

    $db->connect()->beginTransaction();

    if ($mode === 'register') {
        if (isset($data['rfid_uid'])) {
            $response = registerRFID($db, $data['rfid_uid']);
        } elseif (isset($data['fingerprint_id'])) {
            $response = registerFingerprint($db, $data['fingerprint_id']);
        }
    } else {
        $response = handleAttendance($db, $data);
    }

    $db->connect()->commit();

} catch (Exception $e) {
    if ($db->connect()->inTransaction()) {
        $db->connect()->rollBack();
    }
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'lcd_message' => LCD_MESSAGES['ERROR'],
        'buzzer_tone' => BUZZER_TONES['ERROR']
    ];
}

echo json_encode($response);

function registerRFID($db, $rfidUid) {
    // Check if RFID already exists
    $existing = $db->single(
        "SELECT id FROM rfid_cards WHERE rfid_uid = ?",
        [$rfidUid]
    );

    if ($existing) {
        return [
            'status' => 'error',
            'message' => 'RFID already registered',
            'lcd_message' => LCD_MESSAGES['RFID_EXISTS'],
            'buzzer_tone' => BUZZER_TONES['ERROR']
        ];
    }

    // Register new RFID card
    $cardId = $db->insert('rfid_cards', [
        'rfid_uid' => $rfidUid,
        'registered_at' => date('Y-m-d H:i:s')
    ]);

    if (!$cardId) {
        throw new Exception('Failed to register RFID card');
    }

    return [
        'status' => 'success',
        'message' => 'RFID registered successfully',
        'lcd_message' => LCD_MESSAGES['RFID_REGISTERED'],
        'buzzer_tone' => BUZZER_TONES['SUCCESS']
    ];
}

function registerFingerprint($db, $fingerprintId) {
    // Check if fingerprint ID is already registered
    $existing = $db->single(
        "SELECT id FROM user_verification_data WHERE fingerprint_id = ? AND is_active = true",
        [$fingerprintId]
    );

    if ($existing) {
        return [
            'status' => 'error',
            'message' => 'Fingerprint already registered',
            'lcd_message' => LCD_MESSAGES['FINGER_EXISTS'],
            'buzzer_tone' => BUZZER_TONES['ERROR']
        ];
    }

    // Update user verification data if in registration process
    if (isset($_SESSION['registering_user_id'])) {
        $userId = $_SESSION['registering_user_id'];
        
        // Get user type
        $user = $db->single(
            "SELECT user_type FROM users WHERE id = ?",
            [$userId]
        );

        if (!$user || $user['user_type'] !== 'normal') {
            throw new Exception('Invalid user for fingerprint registration');
        }

        // Update verification data
        $updated = $db->update(
            'user_verification_data',
            ['fingerprint_id' => $fingerprintId],
            ['user_id' => $userId, 'is_active' => true]
        );

        if (!$updated) {
            throw new Exception('Failed to register fingerprint');
        }
    }

    return [
        'status' => 'success',
        'message' => 'Fingerprint registered successfully',
        'lcd_message' => LCD_MESSAGES['FINGER_REGISTERED'],
        'buzzer_tone' => BUZZER_TONES['SUCCESS']
    ];
}

function handleAttendance($db, $data) {
    if (!isset($data['rfid_uid'])) {
        throw new Exception('RFID required');
    }

    // Get user verification data
    $verification = $db->single(
        "SELECT uvd.*, u.user_type, u.name, u.id_number 
         FROM user_verification_data uvd
         JOIN users u ON uvd.user_id = u.id
         JOIN rfid_cards rc ON uvd.rfid_id = rc.id
         WHERE rc.rfid_uid = ? AND uvd.is_active = true",
        [$data['rfid_uid']]
    );

    if (!$verification) {
        return [
            'status' => 'error',
            'message' => 'Invalid RFID',
            'lcd_message' => LCD_MESSAGES['RFID_FAILED'],
            'buzzer_tone' => BUZZER_TONES['ERROR']
        ];
    }

    // Special users bypass fingerprint
    if ($verification['user_type'] === 'special') {
        return processAttendance($db, $verification);
    }

    // Normal users need fingerprint
    if (!isset($data['fingerprint_id'])) {
        return [
            'status' => 'waiting',
            'message' => 'Fingerprint required',
            'lcd_message' => LCD_MESSAGES['FINGER_REQUIRED'],
            'buzzer_tone' => BUZZER_TONES['WAIT']
        ];
    }

    // Verify fingerprint matches
    if ($verification['fingerprint_id'] != $data['fingerprint_id']) {
        return [
            'status' => 'error',
            'message' => 'Invalid fingerprint',
            'lcd_message' => LCD_MESSAGES['FINGER_FAILED'],
            'buzzer_tone' => BUZZER_TONES['ERROR']
        ];
    }

    return processAttendance($db, $verification);
}

function processAttendance($db, $verification) {
    // Get latest log for today
    $lastLog = $db->single(
        "SELECT * FROM attendance_logs 
         WHERE user_id = ? AND DATE(time_in) = CURRENT_DATE
         ORDER BY time_in DESC LIMIT 1",
        [$verification['user_id']]
    );

    $currentTime = date('Y-m-d H:i:s');

    // Handle time-in if no log exists or last log has time_out
    if (!$lastLog || $lastLog['time_out']) {
        $lateTime = $db->single(
            "SELECT setting_value FROM system_settings WHERE setting_key = 'late_time'"
        )['setting_value'] ?? '09:00:00';

        $status = strtotime(date('H:i:s')) <= strtotime($lateTime) ? 'on_time' : 'late';

        $logId = $db->insert('attendance_logs', [
            'verification_id' => $verification['id'],
            'user_id' => $verification['user_id'],
            'time_in' => $currentTime,
            'verification_type' => $verification['user_type'] === 'special' ? 'rfid_only' : 'dual',
            'status' => $status,
            'override_status' => null,
            'override_remarks' => null,
            'created_at' => $currentTime
        ]);

        if (!$logId) {
            throw new Exception('Failed to create attendance log');
        }

        return [
            'status' => 'success',
            'message' => 'Time in recorded',
            'lcd_message' => $status === 'late' ? LCD_MESSAGES['LATE'] : LCD_MESSAGES['ON_TIME'],
            'buzzer_tone' => BUZZER_TONES['SUCCESS'],
            'user_name' => $verification['name'],
            'log_type' => 'in',
            'status' => $status
        ];
    }

    // Handle time-out
    $updated = $db->update('attendance_logs',
        ['time_out' => $currentTime],
        ['id' => $lastLog['id']]
    );

    if (!$updated) {
        throw new Exception('Failed to update attendance log');
    }

    return [
        'status' => 'success',
        'message' => 'Time out recorded',
        'lcd_message' => LCD_MESSAGES['TIME_OUT'],
        'buzzer_tone' => BUZZER_TONES['SUCCESS'],
        'user_name' => $verification['name'],
        'log_type' => 'out'
    ];
}
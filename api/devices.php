<?php
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json');

$db = getDatabase();
$data = json_decode(file_get_contents('php://input'), true);

try {
    $db->connect()->beginTransaction();
    
    if (!$data || !isset($data['verification_type'])) {
        throw new Exception('Invalid request data');
    }

    $deviceMode = $db->single(
        "SELECT setting_value FROM system_settings WHERE setting_key = 'device_mode'"
    )['setting_value'] ?? 'scan';

    if ($deviceMode === 'register') {
        handleRegistrationMode($db, $data);
    } else {
        handleVerificationMode($db, $data);
    }

    $db->connect()->commit();
} catch (Exception $e) {
    $db->connect()->rollBack();
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'lcd_message' => LCD_MESSAGES['ERROR'],
        'buzzer_tone' => BUZZER_TONES['ERROR']
    ]);
}

function handleRegistrationMode($db, $data) {
    if ($data['verification_type'] === 'rfid' && isset($data['rfid_uid'])) {
        $existingCard = $db->single(
            "SELECT * FROM rfid_cards WHERE rfid_uid = ?", 
            [$data['rfid_uid']]
        );

        if (!$existingCard) {
            $db->insert('rfid_cards', [
                'rfid_uid' => $data['rfid_uid'],
                'registered_at' => date('Y-m-d H:i:s')
            ]);

            echo json_encode([
                'status' => 'success',
                'lcd_message' => LCD_MESSAGES['RFID_REGISTERED'],
                'buzzer_tone' => BUZZER_TONES['SUCCESS']
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'lcd_message' => LCD_MESSAGES['RFID_EXISTS'],
                'buzzer_tone' => BUZZER_TONES['ERROR']
            ]);
        }
        return;
    }
}

function handleVerificationMode($db, $data) {
    if (!isset($_SESSION['verification'])) {
        $_SESSION['verification'] = [
            'timestamp' => time(),
            'rfid_verified' => false,
            'user_id' => null
        ];
    }

    if ((time() - $_SESSION['verification']['timestamp']) > VERIFICATION_TIMEOUT) {
        $_SESSION['verification'] = [
            'timestamp' => time(),
            'rfid_verified' => false,
            'user_id' => null
        ];
    }

    if ($data['verification_type'] === 'rfid') {
        handleRFIDVerification($db, $data);
    } else if ($data['verification_type'] === 'fingerprint') {
        handleFingerprintVerification($db, $data);
    }
}

function handleRFIDVerification($db, $data) {
    $verificationData = $db->single(
        "SELECT uvd.*, u.user_type, u.remarks, u.id as user_id 
         FROM user_verification_data uvd
         JOIN users u ON uvd.user_id = u.id
         JOIN rfid_cards rc ON uvd.rfid_id = rc.id
         WHERE rc.rfid_uid = ? AND uvd.is_active = true",
        [$data['rfid_uid']]
    );

    if (!$verificationData) {
        echo json_encode([
            'status' => 'error',
            'lcd_message' => LCD_MESSAGES['RFID_FAILED'],
            'buzzer_tone' => BUZZER_TONES['ERROR']
        ]);
        return;
    }

    $_SESSION['verification']['rfid_verified'] = true;
    $_SESSION['verification']['user_id'] = $verificationData['user_id'];
    $_SESSION['verification']['timestamp'] = time();

    if ($verificationData['user_type'] === 'special') {
        processAttendance($db, $verificationData, $data['device_id']);
        return;
    }

    echo json_encode([
        'status' => 'success',
        'verification_type' => 'rfid',
        'lcd_message' => LCD_MESSAGES['FINGER_REQUIRED'],
        'buzzer_tone' => BUZZER_TONES['WAIT']
    ]);
}

function handleFingerprintVerification($db, $data) {
    if (!$_SESSION['verification']['rfid_verified']) {
        echo json_encode([
            'status' => 'error',
            'lcd_message' => LCD_MESSAGES['SCAN_RFID'],
            'buzzer_tone' => BUZZER_TONES['ERROR']
        ]);
        return;
    }

    $verificationData = $db->single(
        "SELECT uvd.*, u.user_type, u.remarks 
         FROM user_verification_data uvd
         JOIN users u ON uvd.user_id = u.id
         WHERE uvd.user_id = ? AND uvd.fingerprint_id = ? AND uvd.is_active = true",
        [$_SESSION['verification']['user_id'], $data['fingerprint_id']]
    );

    if (!$verificationData) {
        echo json_encode([
            'status' => 'error',
            'lcd_message' => LCD_MESSAGES['FINGER_FAILED'],
            'buzzer_tone' => BUZZER_TONES['ERROR']
        ]);
        return;
    }

    processAttendance($db, $verificationData, $data['device_id']);
}

function processAttendance($db, $verificationData, $deviceId) {
    // Check if already logged attendance today
    $existingLog = $db->single(
        "SELECT * FROM attendance_logs 
         WHERE verification_id = ? AND DATE(time_in) = CURRENT_DATE",
        [$verificationData['id']]
    );

    if ($existingLog) {
        echo json_encode([
            'status' => 'error',
            'lcd_message' => LCD_MESSAGES['ALREADY_LOGGED'],
            'buzzer_tone' => BUZZER_TONES['ERROR']
        ]);
        return;
    }

    $lateTime = $db->single(
        "SELECT setting_value FROM system_settings WHERE setting_key = 'late_time'"
    )['setting_value'] ?? '09:00:00';

    $status = strtotime(date('H:i:s')) <= strtotime($lateTime) ? 'on_time' : 'late';
    
    $db->insert('attendance_logs', [
        'verification_id' => $verificationData['id'],
        'user_id' => $verificationData['user_id'],
        'time_in' => date('Y-m-d H:i:s'),
        'verification_type' => $verificationData['user_type'] === 'special' ? 'rfid_only' : 'dual',
        'status' => $status,
        'device_id' => $deviceId
    ]);

    $_SESSION['verification'] = [
        'timestamp' => time(),
        'rfid_verified' => false,
        'user_id' => null
    ];

    echo json_encode([
        'status' => 'success',
        'lcd_message' => $status === 'late' ? LCD_MESSAGES['LATE'] : LCD_MESSAGES['ON_TIME'],
        'buzzer_tone' => BUZZER_TONES['SUCCESS']
    ]);
}
<?php
// ajax/device_status.php
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json');

$db = getDatabase();

try {
    // Get current device mode
    $mode = $db->single(
        "SELECT setting_value FROM system_settings WHERE setting_key = 'device_mode'"
    )['setting_value'] ?? 'scan';

    // Get latest attendance log
    $attendance = $db->single(
        "SELECT al.*, u.name, u.user_type
         FROM attendance_logs al
         JOIN users u ON al.user_id = u.id
         ORDER BY al.created_at DESC 
         LIMIT 1"
    );

    // Get latest device log
    $deviceLog = $db->single(
        "SELECT dl.*, u.name
         FROM device_logs dl
         LEFT JOIN rfid_cards rc ON dl.rfid_uid = rc.rfid_uid
         LEFT JOIN user_verification_data uvd ON rc.id = uvd.rfid_id
         LEFT JOIN users u ON uvd.user_id = u.id
         ORDER BY dl.verification_time DESC 
         LIMIT 1"
    );

    $response = [
        'mode' => $mode
    ];

    if ($attendance && strtotime($attendance['created_at']) > time() - 5) {
        // Recent attendance
        $response += [
            'verification_status' => 'success',
            'verification_message' => 'Verification successful',
            'user_name' => $attendance['name'],
            'log_type' => $attendance['log_type'],
            'status' => $attendance['status'],
            'timestamp' => $attendance['created_at'],
            'lcd_message' => $attendance['log_type'] === 'in' 
                ? ($attendance['status'] === 'late' ? LCD_MESSAGES['LATE'] : LCD_MESSAGES['ON_TIME'])
                : LCD_MESSAGES['TIME_OUT']
        ];

        if ($attendance['user_type'] === 'special') {
            $response['rfid_status'] = 'success';
            $response['rfid_message'] = LCD_MESSAGES['RFID_SUCCESS'];
        }
    } elseif ($deviceLog && strtotime($deviceLog['verification_time']) > time() - 5) {
        // Recent device activity
        $response += [
            'rfid_status' => $deviceLog['status'],
            'rfid_message' => $deviceLog['lcd_display'],
            'buzzer_tone' => $deviceLog['buzzer_tone'],
            'verification_type' => $deviceLog['verification_type'],
            'timestamp' => $deviceLog['verification_time']
        ];

        if ($deviceLog['name']) {
            $response['user_name'] = $deviceLog['name'];
        }
    } else {
        // No recent activity
        $response += [
            'status' => 'ready',
            'lcd_message' => $mode === 'register' ? LCD_MESSAGES['READY_REGISTER'] : LCD_MESSAGES['READY'],
            'buzzer_tone' => null
        ];
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to check device status',
        'message' => $e->getMessage()
    ]);
}
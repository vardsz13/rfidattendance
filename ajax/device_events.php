<?php
// device_events.php
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable nginx buffering

$db = getDatabase();
$lastId = 0;

while (true) {
    // Get latest verification data
    $verification = $db->single(
        "SELECT al.*, u.name, u.user_type
         FROM attendance_logs al
         JOIN users u ON al.user_id = u.id
         WHERE al.id > ? 
         ORDER BY al.created_at DESC 
         LIMIT 1",
        [$lastId]
    );

    if ($verification) {
        $lastId = $verification['id'];

        $data = [
            'verification_status' => 'success',
            'verification_message' => 'Verification successful',
            'user_name' => $verification['name'],
            'log_type' => $verification['log_type'],
            'status' => $verification['status'],
            'timestamp' => $verification['created_at']
        ];

        // Set LCD message based on status and log type
        if ($verification['log_type'] === 'in') {
            $data['lcd_message'] = $verification['status'] === 'late' 
                ? LCD_MESSAGES['LATE'] 
                : LCD_MESSAGES['ON_TIME'];
        } else {
            $data['lcd_message'] = LCD_MESSAGES['TIME_OUT'];
        }

        // Special users get RFID success message
        if ($verification['user_type'] === 'special') {
            $data['rfid_status'] = 'success';
            $data['rfid_message'] = LCD_MESSAGES['RFID_SUCCESS'];
        }

        echo "data: " . json_encode($data) . "\n\n";
    }

    // Check for device logs (RFID/Fingerprint attempts)
    $deviceLog = $db->single(
        "SELECT dl.*, u.name
         FROM device_logs dl
         LEFT JOIN rfid_cards rc ON dl.rfid_uid = rc.rfid_uid
         LEFT JOIN user_verification_data uvd ON rc.id = uvd.rfid_id
         LEFT JOIN users u ON uvd.user_id = u.id
         WHERE dl.id > ?
         ORDER BY dl.verification_time DESC 
         LIMIT 1",
        [$lastId]
    );

    if ($deviceLog) {
        $lastId = $deviceLog['id'];

        $data = [
            'rfid_status' => $deviceLog['status'],
            'rfid_message' => $deviceLog['lcd_display'],
            'buzzer_tone' => $deviceLog['buzzer_tone'],
            'verification_type' => $deviceLog['verification_type'],
            'timestamp' => $deviceLog['verification_time']
        ];

        if ($deviceLog['name']) {
            $data['user_name'] = $deviceLog['name'];
        }

        echo "data: " . json_encode($data) . "\n\n";
    }

    // Clear output buffer
    ob_flush();
    flush();

    // Wait before next check
    sleep(1);

    // Check connection status
    if (connection_aborted()) {
        break;
    }
}
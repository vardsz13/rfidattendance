<?php
// api/verification.php - Handles device verification requests

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

$db = getDatabase();
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid data format']));
}

// Validate required fields
$requiredFields = ['device_id', 'verification_type'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field])) {
        http_response_code(400);
        exit(json_encode(['error' => "Missing field: $field"]));
    }
}

try {
    $db->connect()->beginTransaction();
    
    $response = [
        'status' => 'pending',
        'lcd_message' => LCD_MESSAGES['READY'],
        'buzzer_tone' => BUZZER_TONES['WAIT']
    ];
    
    // Log the verification attempt
    $logData = [
        'verification_type' => $data['verification_type'],
        'rfid_uid' => $data['rfid_uid'] ?? null,
        'fingerprint_id' => $data['fingerprint_id'] ?? null,
        'lcd_display' => $response['lcd_message'],
        'buzzer_sound' => $response['buzzer_tone'],
        'status' => $response['status']
    ];
    
    $logId = $db->insert('device_logs', $logData);
    if (!$logId) {
        throw new Exception('Failed to log verification attempt');
    }
    
    // Handle RFID verification
    if ($data['verification_type'] === 'rfid') {
        if (!isset($data['rfid_uid'])) {
            throw new Exception('RFID UID is required');
        }
        
        // Check if RFID exists in verification_data
        $rfidCheck = $db->single(
            "SELECT user_id FROM verification_data 
             WHERE rfid_uid = ? AND is_active = 1",
            [$data['rfid_uid']]
        );
        
        if ($rfidCheck) {
            // Store successful RFID scan in session (temporary)
            $_SESSION['pending_verification'] = [
                'user_id' => $rfidCheck['user_id'],
                'rfid_uid' => $data['rfid_uid'],
                'rfid_log_id' => $logId,
                'timestamp' => time()
            ];
            
            $response['status'] = 'success';
            $response['lcd_message'] = LCD_MESSAGES['RFID_SUCCESS'];
            $response['buzzer_tone'] = BUZZER_TONES['SUCCESS'];
            $response['next_step'] = 'fingerprint';
        } else {
            $response['status'] = 'failed';
            $response['lcd_message'] = LCD_MESSAGES['RFID_FAILED'];
            $response['buzzer_tone'] = BUZZER_TONES['ERROR'];
        }
    }
    
    // Handle Fingerprint verification
    elseif ($data['verification_type'] === 'fingerprint') {
        if (!isset($data['fingerprint_id'])) {
            throw new Exception('Fingerprint ID is required');
        }
        
        // Check for pending RFID verification
        if (!isset($_SESSION['pending_verification']) || 
            (time() - $_SESSION['pending_verification']['timestamp']) > VERIFICATION_TIMEOUT) {
            // RFID verification expired or not found
            $response['status'] = 'failed';
            $response['lcd_message'] = LCD_MESSAGES['ERROR'];
            $response['buzzer_tone'] = BUZZER_TONES['ERROR'];
            $response['next_step'] = 'rfid';
        } else {
            // Verify fingerprint matches the same user as RFID
            $verificationCheck = $db->single(
                "SELECT user_id FROM verification_data 
                 WHERE user_id = ? AND fingerprint_id = ? AND is_active = 1",
                [$_SESSION['pending_verification']['user_id'], $data['fingerprint_id']]
            );
            
            if ($verificationCheck) {
                // Both verifications successful - create attendance record
                $attendanceData = [
                    'user_id' => $verificationCheck['user_id'],
                    'rfid_log_id' => $_SESSION['pending_verification']['rfid_log_id'],
                    'fingerprint_log_id' => $logId,
                    'attendance_date' => date('Y-m-d'),
                    'time_in' => date('Y-m-d H:i:s')
                ];
                
                // Check if attendance already exists for today
                $existingAttendance = $db->single(
                    "SELECT id FROM attendance 
                     WHERE user_id = ? AND DATE(attendance_date) = CURRENT_DATE",
                    [$verificationCheck['user_id']]
                );
                
                if (!$existingAttendance) {
                    if (!$db->insert('attendance', $attendanceData)) {
                        throw new Exception('Failed to create attendance record');
                    }
                }
                
                $response['status'] = 'success';
                $response['lcd_message'] = LCD_MESSAGES['FINGER_SUCCESS'];
                $response['buzzer_tone'] = BUZZER_TONES['SUCCESS'];
                
                // Clear pending verification
                unset($_SESSION['pending_verification']);
            } else {
                $response['status'] = 'failed';
                $response['lcd_message'] = LCD_MESSAGES['FINGER_FAILED'];
                $response['buzzer_tone'] = BUZZER_TONES['ERROR'];
            }
        }
    }
    
    // Update the log with final status
    $db->update('device_logs', [
        'lcd_display' => $response['lcd_message'],
        'buzzer_sound' => $response['buzzer_tone'],
        'status' => $response['status']
    ], ['id' => $logId]);
    
    $db->connect()->commit();
    echo json_encode($response);

} catch (Exception $e) {
    $db->connect()->rollBack();
    error_log("Verification error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Verification failed',
        'lcd_message' => LCD_MESSAGES['ERROR'],
        'buzzer_tone' => BUZZER_TONES['ERROR']
    ]);
}

// The verification process works in two steps:

//     First RFID scan
//     Then fingerprint scan within the timeout period (30 seconds by default)
    
    
//     For each verification attempt, the system:
    
//     Logs the attempt in device_logs
//     Updates LCD display messages
//     Triggers appropriate buzzer tones
//     Creates attendance records when both verifications succeed
    
    
//     LCD Messages (from your constants.php):
    
//     "Please scan your ID" (Ready state)
//     "RFID Verified" (After successful RFID scan)
//     "Invalid RFID" (Failed RFID scan)
//     "Place finger" (After RFID success)
//     "Access Granted" (Both verifications successful)
//     "Invalid Finger" (Failed fingerprint)
//     "System Error" (For any other errors)
    
    
//     Buzzer Tones:
    
//     Success tone (Both verifications passed)
//     Error tone (Failed verification)
//     Wait tone (Ready state)
//     Alert tone (Suspicious activity)
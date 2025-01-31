<?php
// api/devices.php
require_once '../includes/functions.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['check_mode'])) {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

$db = getDatabase();

// Handle mode check request from ESP8266
if (isset($_GET['check_mode'])) {
    $mode = $db->single(
        "SELECT setting_value FROM system_settings WHERE setting_key = 'device_mode'"
    );
    echo json_encode(['mode' => $mode['setting_value'] ?? 'scan']);
    exit();
}

// Handle RFID scan/registration
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['rfid_uid'])) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid data format']));
}

try {
    $db->connect()->beginTransaction();
    $currentMode = $db->single(
        "SELECT setting_value FROM system_settings WHERE setting_key = 'device_mode'"
    )['setting_value'];

    if ($currentMode === 'register') {
        // Check if RFID already exists
        $existing = $db->single(
            "SELECT id FROM rfid_cards WHERE rfid_uid = ?",
            [$data['rfid_uid']]
        );

        if (!$existing) {
            // Register new RFID card
            if ($db->insert('rfid_cards', [
                'rfid_uid' => $data['rfid_uid'],
                'registered_at' => date('Y-m-d H:i:s')
            ])) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'RFID registered successfully',
                    'buzzer_tone' => 'SUCCESS_TONE'
                ]);
            } else {
                throw new Exception('Failed to register RFID');
            }
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'RFID already registered',
                'buzzer_tone' => 'ERROR_TONE'
            ]);
        }
    } else {
        // Scan mode - validate RFID
        $assignment = $db->single(
            "SELECT ra.*, u.name 
             FROM rfid_cards rc
             JOIN rfid_assignments ra ON rc.id = ra.rfid_id
             JOIN users u ON ra.user_id = u.id
             WHERE rc.rfid_uid = ? AND ra.is_active = true",
            [$data['rfid_uid']]
        );

        if ($assignment) {
            // Create attendance log
            $logData = [
                'assignment_id' => $assignment['id'],
                'time_in' => date('Y-m-d H:i:s'),
                'status' => strtotime(date('H:i:s')) <= strtotime('09:00:00') ? 'on_time' : 'late'
            ];

            if ($db->insert('time_in_logs', $logData)) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Attendance recorded',
                    'user_name' => $assignment['name'],
                    'buzzer_tone' => $logData['status'] === 'on_time' ? 'SUCCESS_TONE' : 'LATE_TONE'
                ]);
            } else {
                throw new Exception('Failed to record attendance');
            }
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid or unassigned RFID',
                'buzzer_tone' => 'ERROR_TONE'
            ]);
        }
    }

    $db->connect()->commit();
} catch (Exception $e) {
    $db->connect()->rollBack();
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'buzzer_tone' => 'ERROR_TONE'
    ]);
}
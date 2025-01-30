<?php
// ajax/devices.php
require_once '../includes/functions.php';
require_once '../includes/auth_functions.php';

// Ensure user is logged in and is admin
requireAdmin();

header('Content-Type: application/json');

$db = getDatabase();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_unassigned':
            // Get unassigned RFID cards
            $unassigned = $db->all(
                "SELECT DISTINCT dl.rfid_uid, dl.verification_time 
                 FROM device_logs dl
                 LEFT JOIN verification_data vd ON dl.rfid_uid = vd.rfid_uid
                 WHERE dl.verification_type = 'rfid'
                 AND dl.rfid_uid IS NOT NULL
                 AND vd.id IS NULL
                 ORDER BY dl.verification_time DESC"
            );
            echo json_encode(['success' => true, 'data' => $unassigned]);
            break;

        case 'assign_rfid':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }

            $userId = $_POST['user_id'] ?? '';
            $rfidUid = $_POST['rfid_uid'] ?? '';

            if (empty($userId) || empty($rfidUid)) {
                throw new Exception('Missing required fields');
            }

            // Begin transaction
            $db->connect()->beginTransaction();

            // Check if RFID is already assigned
            $existing = $db->single(
                "SELECT id FROM verification_data WHERE rfid_uid = ? AND is_active = 1",
                [$rfidUid]
            );

            if ($existing) {
                throw new Exception('RFID already assigned');
            }

            // Deactivate any existing RFID for this user
            $db->update(
                'verification_data',
                ['is_active' => 0],
                ['user_id' => $userId, 'is_active' => 1]
            );

            // Create new verification data
            $verificationData = [
                'user_id' => $userId,
                'rfid_uid' => $rfidUid,
                'is_active' => 1
            ];

            if (!$db->insert('verification_data', $verificationData)) {
                throw new Exception('Failed to assign RFID');
            }

            // Update device logs status
            $db->update(
                'device_logs',
                [
                    'status' => 'success',
                    'lcd_message' => 'RFID Registered',
                    'buzzer_tone' => 'SUCCESS_TONE'
                ],
                ['rfid_uid' => $rfidUid, 'status' => 'pending']
            );

            $db->connect()->commit();
            echo json_encode(['success' => true]);
            break;

        case 'deactivate_rfid':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }

            $verificationId = $_POST['verification_id'] ?? '';
            if (empty($verificationId)) {
                throw new Exception('Missing verification ID');
            }

            if (!$db->update('verification_data', 
                ['is_active' => 0], 
                ['id' => $verificationId])
            ) {
                throw new Exception('Failed to deactivate RFID');
            }

            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

// ajax/devices.php


// Handles web interface actions
// Requires admin authentication
// Manages RFID assignments and deactivations
// Returns JSON for frontend updates
// Used by the admin web interface


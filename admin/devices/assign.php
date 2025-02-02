<?php
// admin/devices/assign_rfid.php
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth_functions.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

// Ensure admin access
requireAdmin();

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $rfidId = $_POST['rfid_id'] ?? '';
    $userId = $_POST['user_id'] ?? '';

    if (empty($rfidId) || empty($userId)) {
        throw new Exception('RFID card and user must be selected');
    }

    $db = getDatabase();

    // Begin transaction
    $db->connect()->beginTransaction();

    // Verify RFID card exists and isn't already assigned
    $card = $db->single(
        "SELECT rc.* FROM rfid_cards rc
         LEFT JOIN rfid_assignments ra ON rc.id = ra.rfid_id AND ra.is_active = true
         WHERE rc.id = ? AND ra.id IS NULL",
        [$rfidId]
    );

    if (!$card) {
        throw new Exception('Invalid or already assigned RFID card');
    }

    // Verify user exists and doesn't have an active RFID
    $user = $db->single(
        "SELECT u.* FROM users u
         LEFT JOIN rfid_assignments ra ON u.id = ra.user_id AND ra.is_active = true
         WHERE u.id = ? AND ra.id IS NULL",
        [$userId]
    );

    if (!$user) {
        throw new Exception('Invalid user or user already has an active RFID card');
    }

    // Deactivate any existing assignments for this user (as a precaution)
    $db->update(
        'rfid_assignments',
        ['is_active' => false],
        ['user_id' => $userId, 'is_active' => true]
    );

    // Create new assignment
    $assignmentData = [
        'rfid_id' => $rfidId,
        'user_id' => $userId,
        'assigned_at' => date('Y-m-d H:i:s'),
        'is_active' => true
    ];

    if (!$db->insert('rfid_assignments', $assignmentData)) {
        throw new Exception('Failed to create RFID assignment');
    }

    $db->connect()->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'RFID card successfully assigned to user'
    ]);

} catch (Exception $e) {
    if ($db->connect()->inTransaction()) {
        $db->connect()->rollBack();
    }

    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
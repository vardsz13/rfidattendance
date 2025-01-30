<?php
// admin/devices/deactivate_rfid.php
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

    $assignmentId = $_POST['assignment_id'] ?? '';

    if (empty($assignmentId)) {
        throw new Exception('Assignment ID is required');
    }

    $db = getDatabase();

    // Begin transaction
    $db->connect()->beginTransaction();

    // Get assignment details
    $assignment = $db->single(
        "SELECT ra.*, u.name as user_name, rc.rfid_uid 
         FROM rfid_assignments ra
         JOIN users u ON ra.user_id = u.id
         JOIN rfid_cards rc ON ra.rfid_id = rc.id
         WHERE ra.id = ? AND ra.is_active = true",
        [$assignmentId]
    );

    if (!$assignment) {
        throw new Exception('Invalid or already deactivated RFID assignment');
    }

    // Deactivate the assignment
    if (!$db->update(
        'rfid_assignments',
        [
            'is_active' => false
        ],
        ['id' => $assignmentId]
    )) {
        throw new Exception('Failed to deactivate RFID assignment');
    }

    // Log the deactivation
    $logData = [
        'rfid_id' => $assignment['rfid_id'],
        'user_id' => $assignment['user_id'],
        'action' => 'deactivate',
        'performed_by' => $_SESSION['user_id'],
        'action_time' => date('Y-m-d H:i:s')
    ];

    if (!$db->insert('rfid_action_logs', $logData)) {
        throw new Exception('Failed to log RFID deactivation');
    }

    $db->connect()->commit();

    echo json_encode([
        'status' => 'success',
        'message' => sprintf(
            'RFID card %s has been deactivated for user %s',
            $assignment['rfid_uid'],
            $assignment['user_name']
        )
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
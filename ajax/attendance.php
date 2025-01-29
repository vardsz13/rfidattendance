<?php
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/auth_functions.php';
require_once dirname(__DIR__) . '/includes/functions.php';

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
}

header('Content-Type: application/json');

$db = getDatabase();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_user_attendance':
        // Get start and end dates from FullCalendar
        $start = $_GET['start'] ?? date('Y-m-01');
        $end = $_GET['end'] ?? date('Y-m-t');
        $userId = $_GET['user_id'] ?? $_SESSION['user_id'];

        // Only allow viewing own attendance unless admin
        if ($userId != $_SESSION['user_id'] && !isAdmin()) {
            http_response_code(403);
            exit(json_encode(['error' => 'Forbidden']));
        }

        // Get attendance records
        $records = $db->all(
            "SELECT a.*, 
                    dl_rfid.verification_time as rfid_time,
                    dl_finger.verification_time as finger_time
             FROM attendance a
             LEFT JOIN device_logs dl_rfid ON a.rfid_log_id = dl_rfid.id
             LEFT JOIN device_logs dl_finger ON a.fingerprint_log_id = dl_finger.id
             WHERE a.user_id = ? 
             AND DATE(a.attendance_date) BETWEEN ? AND ?
             ORDER BY a.attendance_date DESC",
            [$userId, $start, $end]
        );

        // Format for FullCalendar
        $events = array_map(function($record) {
            $timeIn = new DateTime($record['time_in']);
            
            // Determine color based on time
            $color = $timeIn->format('H:i') <= '09:00' ? '#4CAF50' : '#FFC107';
            
            return [
                'title' => 'Present',
                'start' => $record['attendance_date'],
                'color' => $color,
                'timeDetails' => sprintf(
                    "In: %s\nRFID: %s\nFingerprint: %s",
                    $timeIn->format('h:i A'),
                    (new DateTime($record['rfid_time']))->format('h:i A'),
                    (new DateTime($record['finger_time']))->format('h:i A')
                )
            ];
        }, $records);

        echo json_encode($events);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}
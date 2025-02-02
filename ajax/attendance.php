<?php
// ajax/attendance.php
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/auth_functions.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (!isLoggedIn()) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

try {
    $db = getDatabase();
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_daily_details':
            $date = $_GET['date'] ?? date('Y-m-d');
            $userId = $_SESSION['user_id'] ?? null;
            $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

            // Get total users excluding admins
            $totalUsersQuery = "SELECT COUNT(*) as count FROM users WHERE role != 'admin'";
            if (!$isAdmin && $userId) {
                $totalUsersQuery .= " AND id = ?";
                $totalUsers = $db->single($totalUsersQuery, [$userId])['count'];
            } else {
                $totalUsers = $db->single($totalUsersQuery)['count'];
            }

            // Check if future date
            if ($date > date('Y-m-d')) {
                echo json_encode([
                    'futureDate' => true,
                    'total_users' => $totalUsers
                ]);
                exit;
            }

            // Check if it's a holiday
            $holiday = $db->single(
                "SELECT name as title, description 
                 FROM holidays 
                 WHERE (
                    (is_recurring = 1 AND DATE_FORMAT(holiday_date, '%m-%d') = DATE_FORMAT(?, '%m-%d'))
                    OR 
                    (is_recurring = 0 AND holiday_date = ?)
                 )",
                [$date, $date]
            );

            // Get attendance summary
            $query = "SELECT 
                COUNT(DISTINCT CASE WHEN al.status = 'on_time' THEN u.id END) as on_time,
                COUNT(DISTINCT CASE WHEN al.status = 'late' THEN u.id END) as late,
                COUNT(DISTINCT CASE WHEN al.status = 'excused' THEN u.id END) as excused,
                COUNT(DISTINCT CASE WHEN al.status = 'half_day' THEN u.id END) as half_day,
                COUNT(DISTINCT CASE WHEN al.status = 'vacation' THEN u.id END) as vacation,
                COUNT(DISTINCT CASE WHEN al.status IN ('on_time', 'late', 'excused', 'half_day', 'vacation') THEN u.id END) as total_present,
                COUNT(DISTINCT CASE WHEN al.time_out IS NULL AND al.time_in IS NOT NULL THEN u.id END) as still_in
             FROM users u
             LEFT JOIN rfid_assignments ra ON u.id = ra.user_id AND ra.is_active = true
             LEFT JOIN attendance_logs al ON ra.id = al.assignment_id 
                AND DATE(al.log_date) = ?
             WHERE u.role != 'admin'";

            if (!$isAdmin && $userId) {
                $query .= " AND u.id = ?";
                $attendanceParams = [$date, $userId];
            } else {
                $attendanceParams = [$date];
            }

            $summary = $db->single($query, $attendanceParams);

            // Get detailed records if admin
            $detailedRecords = [];
            if ($isAdmin) {
                $detailedRecords = $db->all(
                    "SELECT 
                        u.name as user_name,
                        u.username,
                        al.time_in,
                        al.time_out,
                        al.status,
                        al.remarks,
                        rc.rfid_uid
                     FROM users u
                     LEFT JOIN rfid_assignments ra ON u.id = ra.user_id AND ra.is_active = true
                     LEFT JOIN attendance_logs al ON ra.id = al.assignment_id 
                        AND DATE(al.log_date) = ?
                     LEFT JOIN rfid_cards rc ON ra.rfid_id = rc.id
                     WHERE u.role != 'admin'
                     ORDER BY u.name",
                    [$date]
                );
            }

            echo json_encode([
                'holiday' => $holiday,
                'attendance' => array_merge($summary ?: [], [
                    'total_users' => $totalUsers,
                    'isToday' => $date === date('Y-m-d')
                ]),
                'detailedRecords' => $detailedRecords
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    error_log("Attendance AJAX Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
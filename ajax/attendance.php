<?php
// ajax/attendance.php
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth_functions.php';

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

            // Get attendance summary
            $query = "SELECT 
                COUNT(DISTINCT CASE WHEN log_type = 'in' THEN u.id END) as total_present,
                COUNT(DISTINCT CASE WHEN log_type = 'in' AND status = 'on_time' THEN u.id END) as on_time,
                COUNT(DISTINCT CASE WHEN log_type = 'in' AND status = 'late' THEN u.id END) as late,
                COUNT(DISTINCT u.id) as total_users
             FROM users u
             LEFT JOIN rfid_assignments ra ON u.id = ra.user_id
             LEFT JOIN attendance_logs al ON ra.id = al.assignment_id 
                AND DATE(al.log_time) = ?
             WHERE u.role != 'admin'";

            if (!$isAdmin && $userId) {
                $query .= " AND u.id = ?";
                $attendanceParams = [$date, $userId];
            } else {
                $attendanceParams = [$date];
            }

            $summary = $db->single($query, $attendanceParams);

            // Get user creation date if not admin
            if (!$isAdmin && $userId) {
                $user = $db->single(
                    "SELECT DATE(created_at) as created_date FROM users WHERE id = ?",
                    [$userId]
                );
                if ($user && $date < $user['created_date']) {
                    echo json_encode([
                        'beforeCreation' => true,
                        'total_users' => $totalUsers
                    ]);
                    exit;
                }
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

            echo json_encode([
                'holiday' => $holiday,
                'attendance' => [
                    'total_present' => (int)$summary['total_present'],
                    'on_time' => (int)$summary['on_time'],
                    'late' => (int)$summary['late'],
                    'absent' => $totalUsers - (int)$summary['total_present'],
                    'total_users' => $totalUsers,
                    'isToday' => $date === date('Y-m-d')
                ]
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
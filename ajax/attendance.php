<?php
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth_functions.php';

header('Content-Type: application/json');

$db = getDatabase();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_daily_details':
        $date = $_GET['date'] ?? date('Y-m-d');
        
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

        // Get attendance data based on user role
        $isAuth = isset($_SESSION['user_id']);
        $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
        
        $query = "SELECT 
                    u.name,
                    a.log_time,
                    a.log_type,
                    TIME(a.log_time) as time,
                    TIME(a.log_time) > '09:00:00' AND a.log_type = 'in' as is_late
                 FROM attendance_logs a
                 JOIN users u ON a.user_id = u.id
                 WHERE DATE(a.log_time) = ?";

        if ($isAuth && !$isAdmin) {
            $query .= " AND a.user_id = ?";
            $params = [$date, $_SESSION['user_id']];
        } else {
            $params = [$date];
        }

        $query .= " ORDER BY a.log_time";
        $logs = $db->all($query, $params);

        // Get summary statistics
        $stats = $db->single(
            "SELECT 
                (SELECT COUNT(*) FROM users WHERE role = 'user') as total_users,
                COUNT(DISTINCT CASE WHEN log_type = 'in' THEN user_id END) as present,
                COUNT(DISTINCT CASE WHEN TIME(log_time) <= '09:00:00' AND log_type = 'in' THEN user_id END) as on_time
             FROM attendance_logs 
             WHERE DATE(log_time) = ?",
            [$date]
        );
        
        $response = [
            'holiday' => $holiday,
            'attendance' => [
                'total_users' => (int)$stats['total_users'],
                'present' => (int)$stats['present'],
                'on_time' => (int)$stats['on_time'],
                'late' => (int)$stats['present'] - (int)$stats['on_time'],
                'logs' => $logs
            ]
        ];
        
        echo json_encode($response);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}
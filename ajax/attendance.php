<?php
// attendance.php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth_functions.php';

// Ensure clean output
ob_clean();
header('Content-Type: application/json');

try {
    $db = getDatabase();
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_daily_details':
            $date = $_GET['date'] ?? date('Y-m-d');
            $today = date('Y-m-d');
            $userId = $_SESSION['user_id'] ?? null;
            $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
            
            // Get total users excluding admins
            $totalUsersQuery = "SELECT COUNT(*) as count FROM users WHERE role != 'admin'";
            if (!$isAdmin && $userId) {
                $totalUsersQuery .= " AND id = ?";
                $totalUsers = $db->single($totalUsersQuery, [$userId])['count'] ?? 0;
            } else {
                $totalUsers = $db->single($totalUsersQuery)['count'] ?? 0;
            }
            
            // Get user creation date if not admin
            if (!$isAdmin && $userId) {
                $user = $db->single(
                    "SELECT DATE(created_at) as created_date FROM users WHERE id = ?",
                    [$userId]
                );
                $userCreatedAt = $user ? $user['created_date'] : null;
                
                if ($userCreatedAt && $date < $userCreatedAt) {
                    echo json_encode([
                        'holiday' => null,
                        'attendance' => null,
                        'beforeCreation' => true,
                        'total_users' => $totalUsers
                    ]);
                    exit;
                }
            }
            
            // Only check for future dates if the date is after today
            if ($date > $today) {
                echo json_encode([
                    'holiday' => null,
                    'attendance' => null,
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

            // Get attendance summary with all remarks
            $query = "SELECT 
                COUNT(DISTINCT CASE WHEN log_type = 'in' THEN user_id END) as total_present,
                COUNT(DISTINCT CASE WHEN TIME(log_time) <= '09:00:00' AND log_type = 'in' THEN user_id END) as on_time,
                COUNT(DISTINCT CASE WHEN TIME(log_time) > '09:00:00' AND log_type = 'in' THEN user_id END) as late,
                COUNT(DISTINCT CASE WHEN remarks = 'excused' THEN user_id END) as excused,
                COUNT(DISTINCT CASE WHEN remarks = 'half_day' THEN user_id END) as half_day,
                COUNT(DISTINCT CASE WHEN remarks = 'vacation' THEN user_id END) as vacation
             FROM attendance_logs 
             WHERE DATE(log_time) = ?";

            if (!$isAdmin && $userId) {
                $query .= " AND user_id = ?";
                $summaryParams = [$date, $userId];
            } else {
                $summaryParams = [$date];
            }

            $summary = $db->single($query, $summaryParams);
            
            if ($summary === false) {
                $summary = [
                    'total_present' => 0,
                    'on_time' => 0,
                    'late' => 0,
                    'excused' => 0,
                    'half_day' => 0,
                    'vacation' => 0
                ];
            }
            
            // Calculate absent (excluding excused, vacation, etc.)
            $nonAbsent = ($summary['total_present'] ?? 0) + 
                        ($summary['excused'] ?? 0) + 
                        ($summary['vacation'] ?? 0) + 
                        ($summary['half_day'] ?? 0);
            
            $absent = $totalUsers - $nonAbsent;
            
            $response = [
                'holiday' => $holiday,
                'attendance' => [
                    'total_present' => (int)($summary['total_present'] ?? 0),
                    'on_time' => (int)($summary['on_time'] ?? 0),
                    'late' => (int)($summary['late'] ?? 0),
                    'excused' => (int)($summary['excused'] ?? 0),
                    'half_day' => (int)($summary['half_day'] ?? 0),
                    'vacation' => (int)($summary['vacation'] ?? 0),
                    'absent' => $absent,
                    'total_users' => $totalUsers,
                    'isToday' => $date === $today
                ]
            ];
            
            echo json_encode($response);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error: ' . $e->getMessage(),
        'debug' => DEBUG ? $e->getTrace() : null
    ]);
}
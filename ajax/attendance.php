<?php
// ajax/attendance.php
// Prevent any output before header
ob_start();

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth_functions.php';

// Ensure clean output
ob_clean();

// Set JSON header
header('Content-Type: application/json');

// Disable error display
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/error.log');

try {
    if (!isset($_GET['action'])) {
        throw new Exception('Action parameter is required');
    }

    $db = getDatabase();
    if (!$db) {
        throw new Exception('Database connection failed');
    }

    $action = $_GET['action'];
    $date = $_GET['date'] ?? date('Y-m-d');
    $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    $userId = $_SESSION['user_id'] ?? null;

    // Check if date is in future
    if (strtotime($date) > strtotime(date('Y-m-d'))) {
        echo json_encode([
            'status' => 'success',
            'isFutureDate' => true,
            'message' => 'Future date'
        ]);
        exit;
    }

    switch ($action) {
        case 'get_daily_details':
            // Get total users
            $totalQuery = "SELECT COUNT(*) as count FROM users WHERE role = 'student'";
            if (!$isAdmin && $userId) {
                $totalQuery .= " AND id = ?";
                $totalUsers = $db->single($totalQuery, [$userId])['count'];
            } else {
                $totalUsers = $db->single($totalQuery)['count'];
            }

            // Check for holiday
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
                COUNT(DISTINCT CASE WHEN status = 'on_time' THEN user_id END) as on_time,
                COUNT(DISTINCT CASE WHEN status = 'late' THEN user_id END) as late,
                COUNT(DISTINCT CASE WHEN override_status = 'excused' THEN user_id END) as excused,
                COUNT(DISTINCT CASE WHEN override_status = 'event' THEN user_id END) as event,
                COUNT(DISTINCT CASE WHEN override_status = 'medical' THEN user_id END) as medical
                FROM attendance_logs 
                WHERE DATE(time_in) = ?";

            $params = [$date];
            if (!$isAdmin && $userId) {
                $query .= " AND user_id = ?";
                $params[] = $userId;
            }

            $summary = $db->single($query, $params);

            // Calculate totals
            $totalPresent = ($summary['on_time'] ?? 0) + ($summary['late'] ?? 0);
            $totalAbsent = $totalUsers - $totalPresent;

            // Get detailed records if admin
            $details = [];
            if ($isAdmin) {
                $details = $db->all(
                    "SELECT 
                        al.*, u.name, u.id_number
                     FROM attendance_logs al
                     JOIN users u ON al.user_id = u.id
                     WHERE DATE(al.time_in) = ?
                     ORDER BY al.time_in ASC",
                    [$date]
                );
            }

            echo json_encode([
                'status' => 'success',
                'holiday' => $holiday,
                'attendance' => [
                    'total_present' => $totalPresent,
                    'on_time' => $summary['on_time'] ?? 0,
                    'late' => $summary['late'] ?? 0,
                    'excused' => $summary['excused'] ?? 0,
                    'event' => $summary['event'] ?? 0,
                    'medical' => $summary['medical'] ?? 0,
                    'absent' => $totalAbsent,
                    'total_users' => $totalUsers,
                    'isToday' => $date === date('Y-m-d')
                ],
                'details' => $details
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    // Log the error
    error_log("Attendance AJAX Error: " . $e->getMessage());
    
    // Return JSON error response
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

// Ensure no additional output
exit;
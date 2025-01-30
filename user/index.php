<?php
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/auth_functions.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/components/Calendar.php';

// Ensure user is logged in
requireLogin();

// Enable calendar
$useCalendar = true;

// Get database connection
$db = getDatabase();

// Get calendar parameters
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');

// Get user data
$userId = $_SESSION['user_id'];
$userData = $db->single(
    "SELECT u.*, 
            COUNT(DISTINCT CASE WHEN DATE(a.log_time) = CURRENT_DATE AND a.log_type = 'in' THEN a.id END) as checked_in_today,
            MIN(CASE WHEN DATE(a.log_time) = CURRENT_DATE THEN TIME(a.log_time) END) as first_log_today
     FROM users u
     LEFT JOIN attendance_logs a ON u.id = a.user_id
     WHERE u.id = ?
     GROUP BY u.id",
    [$userId]
);

// Get today's logs
$todayLogs = $db->all(
    "SELECT 
        log_type,
        TIME(log_time) as time,
        CASE 
            WHEN log_type = 'in' AND TIME(log_time) > '09:00:00' THEN 'late'
            WHEN log_type = 'in' AND TIME(log_time) <= '09:00:00' THEN 'on-time'
            ELSE NULL
        END as status
     FROM attendance_logs
     WHERE user_id = ? AND DATE(log_time) = CURRENT_DATE
     ORDER BY log_time",
    [$userId]
);

// Get this month's summary
$monthSummary = $db->single(
    "SELECT 
        COUNT(DISTINCT CASE WHEN TIME(log_time) <= '09:00:00' AND log_type = 'in' THEN DATE(log_time) END) as on_time_days,
        COUNT(DISTINCT CASE WHEN TIME(log_time) > '09:00:00' AND log_type = 'in' THEN DATE(log_time) END) as late_days,
        COUNT(DISTINCT DATE(log_time)) as total_present_days
     FROM attendance_logs
     WHERE user_id = ? 
     AND YEAR(log_time) = ? 
     AND MONTH(log_time) = ?",
    [$userId, $year, $month]
);

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="space-y-6">
    <!-- User Profile Summary -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($userData['name']) ?></h2>
                <p class="text-gray-600">Employee ID: <?= htmlspecialchars($userData['username']) ?></p>
            </div>
            <div class="text-right">
                <div class="font-semibold">
                    <?php if ($userData['checked_in_today']): ?>
                        <span class="text-green-600">✓ Checked In Today</span>
                        <div class="text-sm text-gray-600">First Log: <?= date('h:i A', strtotime($userData['first_log_today'])) ?></div>
                    <?php else: ?>
                        <span class="text-red-600">✗ Not Checked In Today</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Today's Logs -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Today's Attendance Logs</h3>
        <?php if (empty($todayLogs)): ?>
            <p class="text-gray-600 text-center py-4">No attendance records for today</p>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php foreach ($todayLogs as $log): ?>
                    <div class="p-4 border rounded-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm text-gray-600"><?= ucfirst($log['log_type']) ?></div>
                                <div class="text-lg font-semibold"><?= date('h:i A', strtotime($log['time'])) ?></div>
                            </div>
                            <?php if ($log['status']): ?>
                                <span class="px-2 py-1 text-xs rounded-full <?= $log['status'] === 'late' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>">
                                    <?= ucfirst($log['status']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Monthly Summary -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">This Month's Summary</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
            <div class="p-4 border rounded-lg">
                <div class="text-2xl font-bold text-blue-600"><?= $monthSummary['total_present_days'] ?? 0 ?></div>
                <div class="text-sm text-gray-600">Days Present</div>
            </div>
            <div class="p-4 border rounded-lg">
                <div class="text-2xl font-bold text-green-600"><?= $monthSummary['on_time_days'] ?? 0 ?></div>
                <div class="text-sm text-gray-600">Days On Time</div>
            </div>
            <div class="p-4 border rounded-lg">
                <div class="text-2xl font-bold text-red-600"><?= $monthSummary['late_days'] ?? 0 ?></div>
                <div class="text-sm text-gray-600">Days Late</div>
            </div>
        </div>
    </div>

    <!-- Calendar -->
    <?php 
    // Initialize calendar with user's ID to show only their records
    $calendar = new Calendar($db, $year, $month, false, $userId);
    echo $calendar->render(); 
    ?>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

<?php 
// user dashboard provides:

// Profile Summary:

// User name and ID
// Today's check-in status
// First log time for today


// Today's Logs:

// Shows all in/out records for today
// Time for each log
// Status (late/on-time) for check-ins
// Visual indicators


// Monthly Summary:

// Total days present
// Days on time
// Days late
// Clean grid layout


// Personal Calendar:

// Shows only user's attendance records
// Holidays still visible
// Color-coded attendance status
// Clickable days for detailed view
?>
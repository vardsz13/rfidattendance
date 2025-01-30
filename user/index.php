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
            CASE 
                WHEN al_in.id IS NOT NULL THEN 'in'
                WHEN al_out.id IS NOT NULL THEN 'out'
                ELSE NULL 
            END as current_status,
            al_in.log_time as time_in,
            al_in.status as time_in_status
     FROM users u
     LEFT JOIN rfid_assignments ra ON u.id = ra.user_id AND ra.is_active = true
     LEFT JOIN attendance_logs al_in ON ra.id = al_in.assignment_id 
        AND DATE(al_in.log_time) = CURRENT_DATE 
        AND al_in.log_type = 'in'
     LEFT JOIN attendance_logs al_out ON ra.id = al_out.assignment_id 
        AND DATE(al_out.log_time) = CURRENT_DATE 
        AND al_out.log_type = 'out'
     WHERE u.id = ?",
    [$userId]
);

// Get today's complete logs
$todayLogs = $db->all(
    "SELECT 
        al.log_type,
        al.log_time,
        al.status
     FROM rfid_assignments ra
     JOIN attendance_logs al ON ra.id = al.assignment_id
     WHERE ra.user_id = ? 
     AND DATE(al.log_time) = CURRENT_DATE
     ORDER BY al.log_time",
    [$userId]
);

// Get this month's summary
$monthSummary = $db->single(
    "SELECT 
        COUNT(DISTINCT CASE WHEN al.status = 'on_time' THEN DATE(al.log_time) END) as on_time_days,
        COUNT(DISTINCT CASE WHEN al.status = 'late' THEN DATE(al.log_time) END) as late_days,
        COUNT(DISTINCT DATE(al.log_time)) as total_present_days
     FROM rfid_assignments ra
     JOIN attendance_logs al ON ra.id = al.assignment_id 
     WHERE ra.user_id = ? 
     AND YEAR(al.log_time) = ? 
     AND MONTH(al.log_time) = ?
     AND al.log_type = 'in'",
    [$userId, $year, $month]
);

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="space-y-6">
    <!-- User Profile Summary -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
            <h2 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($userData['name'] ?? '') ?></h2>
            <p class="text-gray-600">Employee ID: <?= htmlspecialchars($userData['username'] ?? '') ?></p>
            </div>
            <div class="text-right">
                <div class="font-semibold">
                <?php if ($userData && $userData['current_status'] === 'in'): ?>
                        <span class="text-green-600">✓ Currently Checked In</span>
                        <div class="text-sm text-gray-600">
                            Since: <?= date('h:i A', strtotime($userData['time_in'])) ?>
                            <span class="ml-2 px-2 py-1 text-xs rounded-full 
                                <?= $userData['time_in_status'] === 'late' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' ?>">
                                <?= ucfirst($userData['time_in_status']) ?>
                            </span>
                        </div>
                        <?php elseif ($userData && $userData['current_status'] === 'out'): ?>
                        <span class="text-blue-600">✓ Checked Out</span>
                        <div class="text-sm text-gray-600">Have a great day!</div>
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
            <div class="space-y-4">
                <?php foreach ($todayLogs as $log): ?>
                    <div class="p-4 border rounded-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm text-gray-600">
                                    <?= ucfirst($log['log_type']) ?>
                                </div>
                                <div class="text-lg font-semibold">
                                    <?= date('h:i:s A', strtotime($log['log_time'])) ?>
                                </div>
                            </div>
                            <?php if ($log['log_type'] === 'in' && $log['status']): ?>
                                <span class="px-2 py-1 text-xs rounded-full 
                                    <?= $log['status'] === 'late' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' ?>">
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
                <div class="text-2xl font-bold text-blue-600">
                    <?= number_format($monthSummary['total_present_days'] ?? 0) ?>
                </div>
                <div class="text-sm text-gray-600">Days Present</div>
            </div>
            <div class="p-4 border rounded-lg">
                <div class="text-2xl font-bold text-green-600">
                    <?= number_format($monthSummary['on_time_days'] ?? 0) ?>
                </div>
                <div class="text-sm text-gray-600">Days On Time</div>
            </div>
            <div class="p-4 border rounded-lg">
                <div class="text-2xl font-bold text-yellow-600">
                    <?= number_format($monthSummary['late_days'] ?? 0) ?>
                </div>
                <div class="text-sm text-gray-600">Days Late</div>
            </div>
        </div>
    </div>

    <!-- Calendar -->
    <?php 
    $calendar = new Calendar($db, $year, $month, false, $userId);
    echo $calendar->render(); 
    ?>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
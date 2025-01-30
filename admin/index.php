<?php
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/auth_functions.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/components/Calendar.php';

// Ensure admin access
requireAdmin();

// Enable features
$useCalendar = true;
$useDataTables = true;

// Get database connection
$db = getDatabase();

// Get calendar parameters
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');

// Get today's stats
$todayStats = $db->single(
    "SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'user') as total_users,
        COUNT(DISTINCT CASE WHEN DATE(log_time) = CURRENT_DATE AND log_type = 'in' THEN user_id END) as present_today,
        COUNT(DISTINCT CASE WHEN TIME(log_time) <= '09:00:00' AND DATE(log_time) = CURRENT_DATE AND log_type = 'in' THEN user_id END) as on_time_today,
        COUNT(DISTINCT CASE WHEN TIME(log_time) > '09:00:00' AND DATE(log_time) = CURRENT_DATE AND log_type = 'in' THEN user_id END) as late_today
    FROM attendance_logs"
);

// Get today's attendance records
$todayAttendance = $db->all(
    "SELECT 
        u.name,
        MIN(CASE WHEN a.log_type = 'in' THEN TIME(a.log_time) END) as time_in,
        MAX(CASE WHEN a.log_type = 'out' THEN TIME(a.log_time) END) as time_out,
        MIN(CASE 
            WHEN a.log_type = 'in' AND TIME(a.log_time) <= '09:00:00' THEN 'on-time'
            WHEN a.log_type = 'in' AND TIME(a.log_time) > '09:00:00' THEN 'late'
        END) as status
    FROM users u
    LEFT JOIN attendance_logs a ON u.id = a.user_id 
        AND DATE(a.log_time) = CURRENT_DATE
    WHERE u.role = 'user'
    GROUP BY u.id, u.name
    ORDER BY time_in IS NULL, time_in"
);

// Get recent device logs
$recentLogs = $db->all(
    "SELECT 
        l.*,
        u.name as user_name,
        u.username as user_id
    FROM device_logs l
    LEFT JOIN verification_data v ON 
        (l.rfid_uid = v.rfid_uid OR l.fingerprint_id = v.fingerprint_id)
    LEFT JOIN users u ON v.user_id = u.id
    ORDER BY l.verification_time DESC
    LIMIT 10"
);

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="space-y-6">
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- Total Users -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-700">Total Users</h3>
            <div class="mt-2">
                <p class="text-3xl font-bold text-blue-600"><?= $todayStats['total_users'] ?></p>
                <p class="text-sm text-gray-600">Registered Employees</p>
            </div>
        </div>

        <!-- Present Today -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-700">Present Today</h3>
            <div class="mt-2">
                <p class="text-3xl font-bold text-green-600"><?= $todayStats['present_today'] ?></p>
                <p class="text-sm text-gray-600">Checked In Today</p>
            </div>
        </div>

        <!-- On Time -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-700">On Time Today</h3>
            <div class="mt-2">
                <p class="text-3xl font-bold text-emerald-600"><?= $todayStats['on_time_today'] ?></p>
                <p class="text-sm text-gray-600">Before 9:00 AM</p>
            </div>
        </div>

        <!-- Late Today -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-700">Late Today</h3>
            <div class="mt-2">
                <p class="text-3xl font-bold text-red-600"><?= $todayStats['late_today'] ?></p>
                <p class="text-sm text-gray-600">After 9:00 AM</p>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Quick Actions</h3>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="<?= ADMIN_URL ?>/users/add.php" 
               class="p-4 border rounded-lg text-center hover:bg-gray-50">
                <i class="fas fa-user-plus text-2xl text-blue-600 mb-2"></i>
                <div class="text-sm font-medium">Add User</div>
            </a>
            <a href="<?= ADMIN_URL ?>/devices/registration.php" 
               class="p-4 border rounded-lg text-center hover:bg-gray-50">
                <i class="fas fa-id-card text-2xl text-green-600 mb-2"></i>
                <div class="text-sm font-medium">Register Device</div>
            </a>
            <a href="<?= ADMIN_URL ?>/attendance/export.php" 
               class="p-4 border rounded-lg text-center hover:bg-gray-50">
                <i class="fas fa-file-export text-2xl text-purple-600 mb-2"></i>
                <div class="text-sm font-medium">Export Data</div>
            </a>
            <a href="<?= ADMIN_URL ?>/devices/logs.php" 
               class="p-4 border rounded-lg text-center hover:bg-gray-50">
                <i class="fas fa-list-alt text-2xl text-orange-600 mb-2"></i>
                <div class="text-sm font-medium">View Logs</div>
            </a>
        </div>
    </div>

    <!-- Today's Attendance -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Today's Attendance</h3>
        <div class="overflow-x-auto">
            <table id="todayAttendance" class="min-w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time In</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time Out</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($todayAttendance as $record): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?= htmlspecialchars($record['name']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?= $record['time_in'] ? date('h:i A', strtotime($record['time_in'])) : '---' ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?= $record['time_out'] ? date('h:i A', strtotime($record['time_out'])) : '---' ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if (!$record['time_in']): ?>
                                <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Absent</span>
                            <?php else: ?>
                                <span class="px-2 py-1 text-xs rounded-full <?= $record['status'] === 'late' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' ?>">
                                    <?= ucfirst($record['status']) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Device Logs -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Recent Device Activities</h3>
        <div class="overflow-x-auto">
            <table id="recentLogs" class="min-w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Message</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($recentLogs as $log): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?= date('h:i:s A', strtotime($log['verification_time'])) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?= $log['user_name'] ? htmlspecialchars($log['user_name']) : 
                                '<span class="text-gray-500">Unknown</span>' ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?= ucfirst($log['verification_type']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs rounded-full 
                                <?= $log['status'] === 'success' ? 'bg-green-100 text-green-800' : 
                                    ($log['status'] === 'failed' ? 'bg-red-100 text-red-800' : 
                                    'bg-yellow-100 text-yellow-800') ?>">
                                <?= ucfirst($log['status']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?= htmlspecialchars($log['lcd_display']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Calendar -->
    <?php 
    // Initialize calendar
    $calendar = new Calendar($db, $year, $month, true);
    echo $calendar->render(); 
    ?>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTables
    $('#todayAttendance').DataTable({
        order: [[1, 'asc']],
        pageLength: 10
    });

    $('#recentLogs').DataTable({
        order: [[0, 'desc']],
        pageLength: 10
    });

    // Auto refresh every 30 seconds
    setInterval(function() {
        window.location.reload();
    }, 30000);
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

<?php 
// This admin dashboard includes:

// Stats Cards:

// Total registered users
// Present today count
// On-time arrivals
// Late arrivals


// Quick Actions:

// Add new user
// Register device
// Export attendance data
// View device logs


// Today's Attendance:

// Complete list of all employees
// Time in and out
// Status (Present/Late/Absent)
// Sortable and searchable table


// Recent Device Logs:

// Latest verification attempts
// Success/failure status
// Device messages
// Real-time updates


// Calendar:

// Monthly view of all attendance
// Holiday markers
// Attendance statistics
// Clickable days for details



// Features:

// Auto-refresh every 30 seconds
// DataTables for sorting and searching
// Color-coded status indicators
// Responsive design
// Quick access to all admin functions

?>
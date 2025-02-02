<?php
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/auth_functions.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/components/Calendar.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/error.log');

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

// Initialize variables
$todayStats = [
    'total_users' => 0,
    'present_today' => 0,
    'on_time_today' => 0,
    'late_today' => 0
];

$todayAttendance = [];
$recentLogs = [];
$error = false;
$errorMessage = '';
$lateTime = '09:00:00'; // Default value

try {
    // Debug database connection
    if (!$db->connect()) {
        throw new Exception("Database connection failed");
    }

    // Get late time from settings
    $lateTimeSetting = $db->single(
        "SELECT setting_value FROM system_settings WHERE setting_key = 'late_time'"
    );
    if ($lateTimeSetting) {
        $lateTime = $lateTimeSetting['setting_value'];
    }
    error_log("Late time setting: " . $lateTime);

    // Get total users (separate query)
    $userCount = $db->single("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
    if ($userCount === false) {
        throw new Exception("Failed to get user count");
    }
    $todayStats['total_users'] = $userCount['count'];

    // Get today's attendance statistics with dynamic late time
    $attendanceStats = $db->single(
        "SELECT 
            COUNT(DISTINCT user_id) as present_today,
            COUNT(DISTINCT CASE 
                WHEN TIME(log_time) <= ? AND log_type = 'in' 
                THEN user_id 
            END) as on_time_today,
            COUNT(DISTINCT CASE 
                WHEN TIME(log_time) > ? AND log_type = 'in' 
                THEN user_id 
            END) as late_today
         FROM attendance_logs 
         WHERE DATE(log_time) = CURRENT_DATE",
        [$lateTime, $lateTime]
    );

    if ($attendanceStats) {
        $todayStats['present_today'] = $attendanceStats['present_today'];
        $todayStats['on_time_today'] = $attendanceStats['on_time_today'];
        $todayStats['late_today'] = $attendanceStats['late_today'];
    }

    // Log the stats for debugging
    error_log("Today's Stats: " . print_r($todayStats, true));

    // Get today's attendance records with dynamic late time
    $todayAttendance = $db->all(
        "SELECT 
            u.id,
            u.name,
            MIN(CASE WHEN a.log_type = 'in' THEN TIME(a.log_time) END) as time_in,
            MAX(CASE WHEN a.log_type = 'out' THEN TIME(a.log_time) END) as time_out,
            CASE 
                WHEN MIN(CASE WHEN a.log_type = 'in' THEN TIME(a.log_time) END) <= ? 
                THEN 'on-time'
                WHEN MIN(CASE WHEN a.log_type = 'in' THEN TIME(a.log_time) END) > ? 
                THEN 'late'
                ELSE NULL 
            END as status
        FROM users u
        LEFT JOIN attendance_logs a ON u.id = a.user_id 
            AND DATE(a.log_time) = CURRENT_DATE
        WHERE u.role = 'user'
        GROUP BY u.id, u.name
        ORDER BY u.name ASC",
        [$lateTime, $lateTime]
    );

    // Log attendance records for debugging
    error_log("Today's Attendance: " . print_r($todayAttendance, true));

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
// Calculate absent count (total users minus present users)
$todayStats['absent_today'] = $todayStats['total_users'] - $todayStats['present_today'];
    // Log device logs for debugging
    error_log("Recent Device Logs: " . print_r($recentLogs, true));

} catch (Exception $e) {
    $error = true;
    $errorMessage = $e->getMessage();
    error_log("Admin Dashboard Error: " . $e->getMessage());
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="space-y-6">
    <?php if ($error): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
        <strong class="font-bold">Error!</strong>
        <span class="block sm:inline">
            <?= htmlspecialchars($errorMessage) ?>
        </span>
    </div>
    <?php endif; ?>

  
   <!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <!-- Total Users -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-700">Total Users</h3>
            <div class="mt-2">
                <p class="text-3xl font-bold text-blue-600">
                    <?= number_format($todayStats['total_users']) ?>
                </p>
                <p class="text-sm text-gray-600">Registered Employees</p>
            </div>
        </div>
<!-- Absent Today -->
<div class="bg-white rounded-lg shadow p-6">
    <h3 class="text-lg font-semibold text-gray-700">Absent Today</h3>
    <div class="mt-2">
        <p class="text-3xl font-bold text-gray-600">
            <?= number_format($todayStats['absent_today']) ?>
        </p>
        <p class="text-sm text-gray-600">Not Checked In Today</p>
    </div>
</div>
        <!-- Present Today -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-700">Present Today</h3>
            <div class="mt-2">
                <p class="text-3xl font-bold text-green-600">
                    <?= number_format($todayStats['present_today']) ?>
                </p>
                <p class="text-sm text-gray-600">Checked In Today</p>
            </div>
        </div>

        <!-- On Time -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-700">On Time Today</h3>
            <div class="mt-2">
                <p class="text-3xl font-bold text-emerald-600">
                    <?= number_format($todayStats['on_time_today']) ?>
                </p>
                <p class="text-sm text-gray-600">Before <?= date('g:i A', strtotime($lateTime)) ?></p>
            </div>
        </div>

        <!-- Late Today -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-700">Late Today</h3>
            <div class="mt-2">
                <p class="text-3xl font-bold text-red-600">
                    <?= number_format($todayStats['late_today']) ?>
                </p>
                <p class="text-sm text-gray-600">After <?= date('g:i A', strtotime($lateTime)) ?></p>
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
        <?php if (empty($todayAttendance)): ?>
            <div class="text-center py-4 text-gray-600">
                <p>No attendance records for today</p>
            </div>
        <?php else: ?>
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
        <?php endif; ?>
    </div>

    <!-- Recent Device Logs -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Recent Device Activities</h3>
        <?php if (empty($recentLogs)): ?>
            <div class="text-center py-4 text-gray-600">
                <p>No recent device activities</p>
            </div>
        <?php else: ?>
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
        <?php endif; ?>
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
    // Initialize DataTables if tables exist
    if ($('#todayAttendance').length) {
        $('#todayAttendance').DataTable({
            order: [[1, 'asc']],
            pageLength: 10,
            language: {
                emptyTable: "No attendance records for today",
                zeroRecords: "No matching records found",
                info: "Showing _START_ to _END_ of _TOTAL_ records",
                infoEmpty: "Showing 0 to 0 of 0 records",
                infoFiltered: "(filtered from _MAX_ total records)"
            },
            responsive: true
        });
    }

    if ($('#recentLogs').length) {
        $('#recentLogs').DataTable({
            order: [[0, 'desc']],
            pageLength: 10,
            language: {
                emptyTable: "No recent device activities",
                zeroRecords: "No matching records found",
                info: "Showing _START_ to _END_ of _TOTAL_ records",
                infoEmpty: "Showing 0 to 0 of 0 records",
                infoFiltered: "(filtered from _MAX_ total records)"
            },
            responsive: true
        });
    }

    // Function to format date
    function formatDate(date) {
        return new Date(date).toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }

    // Function to format time
    function formatTime(time) {
        return new Date('1970-01-01T' + time).toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: 'numeric',
            hour12: true
        });
    }

    // Auto refresh every 30 seconds
    let refreshInterval = setInterval(function() {
        refreshData();
    }, 30000);

    // Function to refresh data without reloading page
    function refreshData() {
        $.ajax({
            url: window.location.href,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.todayStats) {
                    updateStats(response.todayStats);
                }
                if (response.todayAttendance) {
                    updateAttendanceTable(response.todayAttendance);
                }
                if (response.recentLogs) {
                    updateLogsTable(response.recentLogs);
                }
            },
            error: function(xhr, status, error) {
                console.error('Refresh failed:', error);
            }
        });
    }

    // Function to update statistics
    function updateStats(stats) {
        $('#total-users').text(stats.total_users);
        $('#present-today').text(stats.present_today);
        $('#on-time-today').text(stats.on_time_today);
        $('#late-today').text(stats.late_today);
    }

    // Function to update attendance table
    function updateAttendanceTable(attendance) {
        if ($('#todayAttendance').length) {
            const table = $('#todayAttendance').DataTable();
            table.clear();
            attendance.forEach(function(record) {
                table.row.add([
                    record.name,
                    record.time_in ? formatTime(record.time_in) : '---',
                    record.time_out ? formatTime(record.time_out) : '---',
                    getStatusBadge(record)
                ]);
            });
            table.draw();
        }
    }

    // Function to update logs table
    function updateLogsTable(logs) {
        if ($('#recentLogs').length) {
            const table = $('#recentLogs').DataTable();
            table.clear();
            logs.forEach(function(log) {
                table.row.add([
                    formatTime(log.verification_time),
                    log.user_name || '<span class="text-gray-500">Unknown</span>',
                    ucfirst(log.verification_type),
                    getStatusBadge(log),
                    log.lcd_display
                ]);
            });
            table.draw();
        }
    }

    // Helper function to get status badge HTML
    function getStatusBadge(record) {
        if (!record.time_in) {
            return '<span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Absent</span>';
        }
        const isLate = record.status === 'late';
        const classes = isLate ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800';
        return `<span class="px-2 py-1 text-xs rounded-full ${classes}">${ucfirst(record.status)}</span>`;
    }

    // Helper function to capitalize first letter
    function ucfirst(string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    }

    // Handle quick action buttons
    $('.quick-action-btn').on('click', function(e) {
        const actionType = $(this).data('action');
        if (actionType === 'export') {
            e.preventDefault();
            if (confirm('Download attendance report?')) {
                window.location.href = $(this).attr('href');
            }
        }
    });

    // Calendar navigation
    $('.calendar-nav').on('click', 'button', function() {
        const direction = $(this).data('direction');
        navigateCalendar(direction);
    });

    function navigateCalendar(direction) {
        const currentDate = new Date(year, month - 1);
        if (direction === 'prev') {
            currentDate.setMonth(currentDate.getMonth() - 1);
        } else {
            currentDate.setMonth(currentDate.getMonth() + 1);
        }
        
        window.location.href = `?year=${currentDate.getFullYear()}&month=${currentDate.getMonth() + 1}`;
    }

    // Cleanup on page unload
    $(window).on('unload', function() {
        clearInterval(refreshInterval);
    });
});
</script>

<?php 
// Add debugging output if needed
if (isset($_GET['debug']) && $_GET['debug'] === 'true' && isAdmin()): 
?>
<div class="mt-8 p-4 bg-gray-100 rounded">
    <h3 class="text-lg font-semibold mb-2">Debug Information</h3>
    <pre class="text-xs"><?php
        echo "PHP Version: " . PHP_VERSION . "\n";
        echo "Today's Stats: " . print_r($todayStats, true) . "\n";
        echo "Today's Attendance Count: " . count($todayAttendance) . "\n";
        echo "Recent Logs Count: " . count($recentLogs) . "\n";
    ?></pre>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
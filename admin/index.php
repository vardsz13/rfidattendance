<?php
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/auth_functions.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Start session at the very beginning
session_start();

// Debug
error_log("Admin index - Session: " . print_r($_SESSION, true));

// Check login status
if (!isset($_SESSION['user_id'])) {
    error_log("No user_id in session, redirecting to login");
    header('Location: ' . AUTH_URL . '/login.php');
    exit();
}

// Check admin status
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    error_log("User not admin, redirecting to user area");
    header('Location: ' . USER_URL);
    exit();
}

// If we get here, user is logged in and is admin
error_log("Admin access granted");

// Enable DataTables and Charts
$useDataTables = true;
$useCharts = true;

require_once dirname(__DIR__) . '/includes/header.php';

// Get today's attendance count
$db = getDatabase();
$todayStats = $db->single(
    "SELECT 
        COUNT(DISTINCT user_id) as total_attendance,
        COUNT(DISTINCT CASE WHEN TIME(time_in) <= '09:00:00' THEN user_id END) as on_time
     FROM attendance 
     WHERE DATE(attendance_date) = CURRENT_DATE"
);

// Get today's verification attempts
$verificationStats = $db->single(
    "SELECT 
        COUNT(*) as total_attempts,
        SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
     FROM device_logs 
     WHERE DATE(verification_time) = CURRENT_DATE"
);

// Get recent device logs
$recentLogs = $db->all(
    "SELECT dl.*, u.name as user_name
     FROM device_logs dl
     LEFT JOIN verification_data vd ON 
        (dl.rfid_uid = vd.rfid_uid OR dl.fingerprint_id = vd.fingerprint_id)
     LEFT JOIN users u ON vd.user_id = u.id
     ORDER BY verification_time DESC
     LIMIT 10"
);
?>

<div class="space-y-6">
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Attendance Card -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800">Today's Attendance</h3>
            <div class="mt-2">
                <p class="text-3xl font-bold text-blue-600"><?= $todayStats['total_attendance'] ?? 0 ?></p>
                <p class="text-sm text-gray-600">On Time: <?= $todayStats['on_time'] ?? 0 ?></p>
            </div>
        </div>

        <!-- Verification Stats Card -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800">Today's Verifications</h3>
            <div class="mt-2">
                <p class="text-3xl font-bold text-green-600"><?= $verificationStats['successful'] ?? 0 ?></p>
                <p class="text-sm text-gray-600">Failed: <?= $verificationStats['failed'] ?? 0 ?></p>
            </div>
        </div>

        <!-- System Status Card -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800">System Status</h3>
            <div class="mt-2">
                <p class="text-sm"><i class="fas fa-check-circle text-green-500"></i> RFID Reader: Active</p>
                <p class="text-sm"><i class="fas fa-check-circle text-green-500"></i> Fingerprint Scanner: Active</p>
                <p class="text-sm"><i class="fas fa-check-circle text-green-500"></i> Database: Connected</p>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-800">Recent Activity</h3>
            <div class="mt-4">
                <table id="recentLogsTable" class="w-full">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Type</th>
                            <th>User</th>
                            <th>Status</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentLogs as $log): ?>
                            <tr>
                                <td><?= date('Y-m-d H:i:s', strtotime($log['verification_time'])) ?></td>
                                <td><?= ucfirst($log['verification_type']) ?></td>
                                <td><?= $log['user_name'] ?? 'Unknown' ?></td>
                                <td>
                                    <span class="status-badge status-<?= $log['status'] ?>">
                                        <?= ucfirst($log['status']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($log['lcd_display']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#recentLogsTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 10,
        responsive: true
    });

    // Auto refresh every 30 seconds
    setInterval(function() {
        window.location.reload();
    }, 30000);
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

<!--  
Admin Dashboard (index.php):


Today's attendance statistics
Verification attempts overview
System status display
Recent activity log
Auto-refresh every 30 seconds
Responsive layout

-->
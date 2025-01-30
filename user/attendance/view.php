<?php
// user/attendance/view.php
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth_functions.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

// Ensure user is logged in
requireLogin();

// Enable DataTables
$useDataTables = true;

$db = getDatabase();
$userId = $_SESSION['user_id'];

// Get date range from request or default to current month
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Get attendance records for the date range
$attendanceLogs = $db->all(
    "SELECT 
        a.log_time,
        a.log_type,
        a.sequence_number,
        r.rfid_uid,
        f.fingerprint_id,
        CASE 
            WHEN a.log_type = 'in' AND TIME(a.log_time) > '09:00:00' THEN 'late'
            WHEN a.log_type = 'in' AND TIME(a.log_time) <= '09:00:00' THEN 'on-time'
            ELSE NULL
        END as status
     FROM attendance_logs a
     LEFT JOIN device_logs r ON a.rfid_log_id = r.id
     LEFT JOIN device_logs f ON a.fingerprint_log_id = f.id
     WHERE a.user_id = ? 
     AND DATE(a.log_time) BETWEEN ? AND ?
     ORDER BY a.log_time DESC",
    [$userId, $startDate, $endDate]
);

// Get attendance summary
$summary = $db->single(
    "SELECT 
        COUNT(DISTINCT CASE WHEN TIME(log_time) <= '09:00:00' AND log_type = 'in' THEN DATE(log_time) END) as on_time_days,
        COUNT(DISTINCT CASE WHEN TIME(log_time) > '09:00:00' AND log_type = 'in' THEN DATE(log_time) END) as late_days,
        COUNT(DISTINCT DATE(log_time)) as total_present_days
     FROM attendance_logs
     WHERE user_id = ? 
     AND DATE(log_time) BETWEEN ? AND ?",
    [$userId, $startDate, $endDate]
);

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container mx-auto px-4">
    <!-- Date Range Filter -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Start Date</label>
                <input type="date" name="start_date" value="<?= $startDate ?>" 
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">End Date</label>
                <input type="date" name="end_date" value="<?= $endDate ?>" 
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full md:w-auto px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Filter Records
                </button>
            </div>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-2xl font-bold text-blue-600"><?= $summary['total_present_days'] ?></div>
            <div class="text-sm text-gray-600">Total Days Present</div>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-2xl font-bold text-green-600"><?= $summary['on_time_days'] ?></div>
            <div class="text-sm text-gray-600">Days On Time</div>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-2xl font-bold text-red-600"><?= $summary['late_days'] ?></div>
            <div class="text-sm text-gray-600">Days Late</div>
        </div>
    </div>

    <!-- Attendance Records Table -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Attendance Records</h3>
            <table id="attendanceTable" class="w-full">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>RFID</th>
                        <th>Fingerprint ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attendanceLogs as $log): ?>
                        <tr>
                            <td><?= date('Y-m-d', strtotime($log['log_time'])) ?></td>
                            <td><?= date('H:i:s', strtotime($log['log_time'])) ?></td>
                            <td><?= ucfirst($log['log_type']) ?></td>
                            <td>
                                <?php if ($log['status']): ?>
                                    <span class="px-2 py-1 text-xs rounded-full 
                                        <?= $log['status'] === 'late' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>">
                                        <?= ucfirst($log['status']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?= $log['rfid_uid'] ?></td>
                            <td><?= $log['fingerprint_id'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#attendanceTable').DataTable({
        order: [[0, 'desc'], [1, 'desc']],
        pageLength: 25,
        responsive: true
    });
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
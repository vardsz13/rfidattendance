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
        al.log_time,
        al.log_type,
        al.status,
        rc.rfid_uid
     FROM rfid_assignments ra
     JOIN attendance_logs al ON ra.id = al.assignment_id
     LEFT JOIN rfid_cards rc ON ra.rfid_id = rc.id
     WHERE ra.user_id = ? 
     AND DATE(al.log_time) BETWEEN ? AND ?
     ORDER BY al.log_time DESC",
    [$userId, $startDate, $endDate]
);

// Get summary for the period
$summary = $db->single(
    "SELECT 
        COUNT(DISTINCT CASE WHEN al.status = 'on_time' THEN DATE(al.log_time) END) as on_time_days,
        COUNT(DISTINCT CASE WHEN al.status = 'late' THEN DATE(al.log_time) END) as late_days,
        COUNT(DISTINCT DATE(al.log_time)) as total_present_days
     FROM rfid_assignments ra
     JOIN attendance_logs al ON ra.id = al.assignment_id
     WHERE ra.user_id = ? 
     AND DATE(al.log_time) BETWEEN ? AND ?
     AND al.log_type = 'in'",
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
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm font-medium text-gray-500">Days Present</div>
            <div class="mt-2">
                <div class="text-2xl font-bold text-blue-600">
                    <?= number_format($summary['total_present_days']) ?>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm font-medium text-gray-500">Days On Time</div>
            <div class="mt-2">
                <div class="text-2xl font-bold text-green-600">
                    <?= number_format($summary['on_time_days']) ?>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm font-medium text-gray-500">Days Late</div>
            <div class="mt-2">
                <div class="text-2xl font-bold text-yellow-600">
                    <?= number_format($summary['late_days']) ?>
                </div>
            </div>
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
                        <th>RFID Used</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attendanceLogs as $log): ?>
                        <tr>
                            <td><?= date('Y-m-d', strtotime($log['log_time'])) ?></td>
                            <td><?= date('h:i:s A', strtotime($log['log_time'])) ?></td>
                            <td><?= ucfirst($log['log_type']) ?></td>
                            <td>
                                <?php if ($log['log_type'] === 'in'): ?>
                                    <span class="px-2 py-1 text-xs rounded-full 
                                        <?= $log['status'] === 'late' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' ?>">
                                        <?= ucfirst($log['status']) ?>
                                        </span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($log['rfid_uid']) ?></td>
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
        responsive: true,
        language: {
            emptyTable: "No attendance records found for selected period",
            zeroRecords: "No matching records found",
            info: "Showing _START_ to _END_ of _TOTAL_ records",
            infoEmpty: "Showing 0 to 0 of 0 records",
            infoFiltered: "(filtered from _MAX_ total records)"
        }
    });
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
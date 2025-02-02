<?php
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth_functions.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

requireAdmin();
$useDataTables = true;

$db = getDatabase();

// Get date range from request or default to current month
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Get attendance records with user details
$attendance = $db->all(
    "SELECT 
        av.*,
        u.name as user_name,
        u.username
     FROM attendance_view av
     JOIN users u ON av.user_id = u.id
     WHERE DATE(av.log_date) BETWEEN ? AND ?
     ORDER BY av.time_in DESC",
    [$startDate, $endDate]
);

// Get summary statistics
$summary = $db->single(
    "SELECT 
        COUNT(DISTINCT CASE WHEN status = 'on_time' THEN user_id END) as on_time_users,
        COUNT(DISTINCT CASE WHEN status = 'late' THEN user_id END) as late_users,
        COUNT(DISTINCT user_id) as total_present,
        (SELECT COUNT(*) FROM users WHERE role != 'admin') as total_users
     FROM attendance_logs 
     WHERE DATE(log_date) = CURRENT_DATE"
);

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container mx-auto px-4">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">Attendance Management</h2>
        <div class="space-x-2">
            <a href="daily.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                Daily View
            </a>
            <a href="monthly.php" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                Monthly Report
            </a>
            <a href="manage.php" class="bg-violet-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                Manage Attendance
            </a>
        </div>
    </div>

    <!-- Today's Summary -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-700">Total Users</h3>
            <div class="mt-2">
                <p class="text-3xl font-bold text-blue-600">
                    <?= number_format($summary['total_users']) ?>
                </p>
                <p class="text-sm text-gray-600">Registered Users</p>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-700">Present Today</h3>
            <div class="mt-2">
                <p class="text-3xl font-bold text-green-600">
                    <?= number_format($summary['total_present']) ?>
                </p>
                <p class="text-sm text-gray-600">Checked In Today</p>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-700">On Time Today</h3>
            <div class="mt-2">
                <p class="text-3xl font-bold text-emerald-600">
                    <?= number_format($summary['on_time_users']) ?>
                </p>
                <p class="text-sm text-gray-600">Arrived On Time</p>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-700">Late Today</h3>
            <div class="mt-2">
                <p class="text-3xl font-bold text-red-600">
                    <?= number_format($summary['late_users']) ?>
                </p>
                <p class="text-sm text-gray-600">Arrived Late</p>
            </div>
        </div>
    </div>

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

    <!-- Attendance Records -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-6">
            <table id="attendanceTable" class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th>Date</th>
                        <th>Name</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>RFID</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($attendance as $record): ?>
                    <tr>
                        <td><?= date('Y-m-d', strtotime($record['log_date'])) ?></td>
                        <td><?= htmlspecialchars($record['user_name']) ?></td>
                        <td><?= date('h:i:s A', strtotime($record['time_in'])) ?></td>
                        <td>
                            <?= $record['time_out'] ? date('h:i:s A', strtotime($record['time_out'])) : '---' ?>
                        </td>
                        <td><?= $record['duration'] ?? '---' ?></td>
                        <td>
                            <span class="px-2 py-1 text-xs rounded-full 
                                <?= $record['status'] === 'late' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' ?>">
                                <?= ucfirst($record['status']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($record['rfid_uid']) ?></td>
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
        order: [[0, 'desc'], [2, 'desc']],
        pageLength: 25,
        responsive: true
    });
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
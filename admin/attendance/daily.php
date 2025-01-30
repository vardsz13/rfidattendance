<?php
// admin/attendance/daily.php
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth_functions.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

requireAdmin();
$useDataTables = true;

$db = getDatabase();

// Get late time setting
$lateSetting = $db->single(
    "SELECT setting_value FROM system_settings WHERE setting_key = 'late_time'"
);
$lateTime = $lateSetting ? $lateSetting['setting_value'] : '09:00:00';

// Get selected date (default to today)
$selectedDate = $_GET['date'] ?? date('Y-m-d');

// Get attendance records for the selected date
$attendance = $db->all(
    "SELECT 
        u.name,
        u.username,
        a.time_in,
        a.status,
        d.rfid_uid,
        d.lcd_message
     FROM users u
     LEFT JOIN attendance_logs a ON u.id = a.user_id 
        AND DATE(a.time_in) = ?
     LEFT JOIN device_logs d ON a.rfid_log_id = d.id
     WHERE u.role = 'user'
     ORDER BY u.name",
    [$selectedDate]
);

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container mx-auto px-4">
    <div class="mb-6">
        <h2 class="text-2xl font-bold">Daily Attendance</h2>
        <p class="text-gray-600">
            Current late time threshold: <span class="font-semibold"><?= date('g:i A', strtotime($lateTime)) ?></span>
            <a href="../settings" class="text-blue-500 hover:text-blue-700 ml-2">(Change)</a>
        </p>
    </div>

    <!-- Date Selection -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <form method="GET" class="flex items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Select Date</label>
                <input type="date" 
                       name="date" 
                       value="<?= $selectedDate ?>" 
                       max="<?= date('Y-m-d') ?>"
                       class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <button type="submit" 
                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                View Attendance
            </button>
        </form>
    </div>

    <!-- Attendance Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table id="attendanceTable" class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time In</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">RFID UID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Device Message</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($attendance as $record): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?= htmlspecialchars($record['name']) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?= htmlspecialchars($record['username']) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?= $record['time_in'] ? date('g:i:s A', strtotime($record['time_in'])) : 'Absent' ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php if ($record['time_in']): ?>
                            <span class="px-2 py-1 text-xs rounded-full <?= 
                                $record['status'] === 'on_time' 
                                    ? 'bg-green-100 text-green-800' 
                                    : 'bg-red-100 text-red-800' 
                            ?>">
                                <?= ucfirst($record['status']) ?>
                            </span>
                        <?php else: ?>
                            <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">
                                Absent
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?= htmlspecialchars($record['rfid_uid'] ?? 'N/A') ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?= htmlspecialchars($record['lcd_message'] ?? 'N/A') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#attendanceTable').DataTable({
        order: [[2, 'asc']],
        pageLength: 25
    });
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
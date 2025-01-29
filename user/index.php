<?php
// user/index.php
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/auth_functions.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Start session and check login
session_start();
requireLogin();

// Enable FullCalendar and DataTables
$useCalendar = true;
$useDataTables = true;

require_once dirname(__DIR__) . '/includes/header.php';

$db = getDatabase();

// Get user's attendance summary for current month
$summary = $db->single(
    "SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN TIME(time_in) <= '09:00:00' THEN 1 ELSE 0 END) as on_time,
        SUM(CASE WHEN TIME(time_in) > '09:00:00' THEN 1 ELSE 0 END) as late
     FROM attendance 
     WHERE user_id = ? 
     AND MONTH(attendance_date) = MONTH(CURRENT_DATE)
     AND YEAR(attendance_date) = YEAR(CURRENT_DATE)",
    [$_SESSION['user_id']]
);

// Get recent attendance records
$recentAttendance = $db->all(
    "SELECT a.*, 
            dl_rfid.verification_time as rfid_time,
            dl_finger.verification_time as finger_time
     FROM attendance a
     LEFT JOIN device_logs dl_rfid ON a.rfid_log_id = dl_rfid.id
     LEFT JOIN device_logs dl_finger ON a.fingerprint_log_id = dl_finger.id
     WHERE a.user_id = ?
     ORDER BY a.attendance_date DESC
     LIMIT 10",
    [$_SESSION['user_id']]
);
?>

<div class="space-y-6">
    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Total Days -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800">Total Days Present</h3>
            <p class="text-3xl font-bold text-blue-600"><?= $summary['total_days'] ?? 0 ?></p>
            <p class="text-sm text-gray-600">This Month</p>
        </div>

        <!-- On Time -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800">On Time Arrivals</h3>
            <p class="text-3xl font-bold text-green-600"><?= $summary['on_time'] ?? 0 ?></p>
            <p class="text-sm text-gray-600">Before 9:00 AM</p>
        </div>

        <!-- Late -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800">Late Arrivals</h3>
            <p class="text-3xl font-bold text-yellow-600"><?= $summary['late'] ?? 0 ?></p>
            <p class="text-sm text-gray-600">After 9:00 AM</p>
        </div>
    </div>

    <!-- Calendar -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Attendance Calendar</h3>
        <div id="calendar" class="min-h-[600px]"></div>
    </div>

    <!-- Recent Attendance -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Attendance</h3>
        <div class="overflow-x-auto">
            <table id="recentAttendanceTable" class="w-full">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time In</th>
                        <th>RFID Time</th>
                        <th>Fingerprint Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentAttendance as $record): ?>
                        <tr>
                            <td><?= date('Y-m-d', strtotime($record['attendance_date'])) ?></td>
                            <td><?= date('h:i A', strtotime($record['time_in'])) ?></td>
                            <td><?= date('h:i A', strtotime($record['rfid_time'])) ?></td>
                            <td><?= date('h:i A', strtotime($record['finger_time'])) ?></td>
                            <td>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                    <?= strtotime($record['time_in']) <= strtotime($record['attendance_date'] . ' 09:00:00') 
                                        ? 'bg-green-100 text-green-800' 
                                        : 'bg-yellow-100 text-yellow-800' ?>">
                                    <?= strtotime($record['time_in']) <= strtotime($record['attendance_date'] . ' 09:00:00') 
                                        ? 'On Time' 
                                        : 'Late' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Calendar
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek'
        },
        events: {
            url: '<?= BASE_URL ?>/ajax/attendance.php',
            method: 'GET',
            extraParams: {
                action: 'get_user_attendance',
                user_id: <?= $_SESSION['user_id'] ?>
            }
        },
        eventDidMount: function(info) {
            // Add tooltips
            $(info.el).tooltip({
                title: info.event.extendedProps.timeDetails,
                placement: 'top',
                trigger: 'hover',
                container: 'body'
            });
        }
    });
    calendar.render();

    // Initialize DataTable
    $('#recentAttendanceTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 10,
        responsive: true
    });
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
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
        MIN(CASE WHEN al.log_type = 'in' THEN al.log_time END) as time_in,
        MAX(CASE WHEN al.log_type = 'out' THEN al.log_time END) as time_out,
        MIN(CASE WHEN al.log_type = 'in' THEN al.status END) as status,
        rc.rfid_uid,
        dl.buzzer_tone,
        COUNT(DISTINCT CASE WHEN al.log_type = 'in' THEN al.id END) as total_in,
        COUNT(DISTINCT CASE WHEN al.log_type = 'out' THEN al.id END) as total_out
     FROM users u
     LEFT JOIN rfid_assignments ra ON u.id = ra.user_id AND ra.is_active = true
     LEFT JOIN rfid_cards rc ON ra.rfid_id = rc.id
     LEFT JOIN attendance_logs al ON ra.id = al.assignment_id 
        AND DATE(al.log_time) = ?
     LEFT JOIN device_logs dl ON rc.rfid_uid = dl.rfid_uid 
        AND DATE(dl.verification_time) = ?
     WHERE u.role = 'user'
     GROUP BY u.id, u.name, u.username, rc.rfid_uid
     ORDER BY u.name",
    [$selectedDate, $selectedDate]
);

// Get summary statistics
$summary = $db->single(
    "SELECT 
        COUNT(DISTINCT CASE WHEN al.log_type = 'in' THEN ra.user_id END) as total_present,
        COUNT(DISTINCT CASE WHEN al.log_type = 'in' AND al.status = 'on_time' THEN ra.user_id END) as on_time,
        COUNT(DISTINCT CASE WHEN al.log_type = 'in' AND al.status = 'late' THEN ra.user_id END) as late,
        COUNT(DISTINCT CASE WHEN al.log_type = 'in' IS NULL THEN u.id END) as absent
     FROM users u
     LEFT JOIN rfid_assignments ra ON u.id = ra.user_id AND ra.is_active = true
     LEFT JOIN attendance_logs al ON ra.id = al.assignment_id 
        AND DATE(al.log_time) = ?
     WHERE u.role = 'user'",
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

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <!-- Total Present -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm font-medium text-gray-500">Total Present</div>
            <div class="mt-2 flex items-baseline">
                <div class="text-2xl font-semibold text-blue-600">
                    <?= number_format($summary['total_present']) ?>
                </div>
            </div>
        </div>

        <!-- On Time -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm font-medium text-gray-500">On Time</div>
            <div class="mt-2 flex items-baseline">
                <div class="text-2xl font-semibold text-green-600">
                    <?= number_format($summary['on_time']) ?>
                </div>
            </div>
        </div>

        <!-- Late -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm font-medium text-gray-500">Late</div>
            <div class="mt-2 flex items-baseline">
                <div class="text-2xl font-semibold text-yellow-600">
                    <?= number_format($summary['late']) ?>
                </div>
            </div>
        </div>

        <!-- Absent -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm font-medium text-gray-500">Absent</div>
            <div class="mt-2 flex items-baseline">
                <div class="text-2xl font-semibold text-red-600">
                    <?= number_format($summary['absent']) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Attendance Details</h3>
            <table id="attendanceTable" class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time In</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time Out</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">RFID UID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
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
                            <?= $record['time_in'] ? date('h:i:s A', strtotime($record['time_in'])) : '---' ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?= $record['time_out'] ? date('h:i:s A', strtotime($record['time_out'])) : '---' ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if (!$record['time_in']): ?>
                                <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Absent</span>
                            <?php else: ?>
                                <span class="px-2 py-1 text-xs rounded-full 
                                    <?= $record['status'] === 'late' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' ?>">
                                    <?= ucfirst($record['status']) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?= htmlspecialchars($record['rfid_uid'] ?? 'Not Assigned') ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <?php if ($selectedDate === date('Y-m-d')): ?>
                            <button onclick="updateAttendance('<?= $record['username'] ?>')" 
                                    class="text-blue-600 hover:text-blue-900">
                                Update
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Attendance Update Modal -->
    <div id="updateModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 id="modalTitle" class="text-lg font-medium leading-6 text-gray-900 mb-4"></h3>
                <form id="updateForm" class="space-y-4">
                    <input type="hidden" id="updateUsername" name="username">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Time</label>
                        <input type="time" 
                               id="updateTime" 
                               name="time" 
                               step="1"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Type</label>
                        <select id="updateType" 
                                name="type"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="in">Time In</option>
                            <option value="out">Time Out</option>
                        </select>
                    </div>
                    <div class="flex justify-end space-x-2">
                        <button type="button"
                                onclick="closeUpdateModal()"
                                class="px-4 py-2 border rounded-md text-gray-600 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                            Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#attendanceTable').DataTable({
        order: [[0, 'asc']],
        pageLength: 25,
        responsive: true
    });
});

function updateAttendance(username) {
    document.getElementById('updateUsername').value = username;
    document.getElementById('modalTitle').textContent = 'Update Attendance: ' + username;
    document.getElementById('updateTime').value = new Date().toTimeString().slice(0,8);
    document.getElementById('updateModal').classList.remove('hidden');
}

function closeUpdateModal() {
    document.getElementById('updateModal').classList.add('hidden');
}

document.getElementById('updateForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('date', '<?= $selectedDate ?>');

    fetch('<?= BASE_URL ?>/ajax/attendance.php?action=update_manual', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.error || 'Failed to update attendance');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update attendance');
    });
});

// Close modal when clicking outside
document.getElementById('updateModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeUpdateModal();
    }
});

// Handle escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeUpdateModal();
    }
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
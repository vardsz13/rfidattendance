<?php
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth_functions.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

requireAdmin();
$useDataTables = true;

$db = getDatabase();

// Get selected date or default to today
$selectedDate = $_GET['date'] ?? date('Y-m-d');

// Get late time setting
$lateTime = $db->single(
    "SELECT setting_value FROM system_settings WHERE setting_key = 'late_time'"
)['setting_value'] ?? '09:00:00';

// Get daily summary
$summary = $db->single(
    "SELECT 
        COUNT(DISTINCT CASE WHEN status = 'on_time' OR (override_status IS NOT NULL AND status != 'absent') THEN user_id END) as on_time,
        COUNT(DISTINCT CASE WHEN status = 'late' THEN user_id END) as late,
        COUNT(DISTINCT CASE WHEN override_status = 'excused' THEN user_id END) as excused,
        COUNT(DISTINCT CASE WHEN override_status = 'half_day' THEN user_id END) as half_day,
        COUNT(DISTINCT CASE WHEN override_status = 'vacation' THEN user_id END) as vacation,
        (SELECT COUNT(*) FROM users WHERE role != 'admin') as total_users
     FROM attendance_logs
     WHERE DATE(log_date) = ?",
    [$selectedDate]
);

// Get all users with their attendance status
$attendance = $db->all(
    "SELECT 
        u.id,
        u.name,
        u.username,
        al.id as log_id,
        al.time_in,
        al.time_out,
        al.status,
        al.override_status,
        al.override_remarks,
        rc.rfid_uid
     FROM users u
     LEFT JOIN rfid_assignments ra ON u.id = ra.user_id AND ra.is_active = 1
     LEFT JOIN rfid_cards rc ON ra.rfid_id = rc.id
     LEFT JOIN (
         SELECT * FROM attendance_logs 
         WHERE DATE(log_date) = ?
     ) al ON ra.id = al.assignment_id
     WHERE u.role != 'admin'
     ORDER BY u.name",
    [$selectedDate]
);

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container mx-auto px-4">
    <!-- Header and Date Selection (same as before) -->
    ...

    <!-- Status Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
        <!-- Regular Status Summary -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-700">Total Users</h3>
            <div class="mt-2">
                <p class="text-3xl font-bold text-blue-600">
                    <?= number_format($summary['total_users'] ?? 0) ?>
                </p>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-700">On Time</h3>
            <div class="mt-2">
                <p class="text-3xl font-bold text-green-600">
                    <?= number_format($summary['on_time'] ?? 0) ?>
                </p>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-700">Late</h3>
            <div class="mt-2">
                <p class="text-3xl font-bold text-yellow-600">
                    <?= number_format($summary['late'] ?? 0) ?>
                </p>
            </div>
        </div>

        <!-- Override Status Summary -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-700">Excused</h3>
            <div class="mt-2">
                <p class="text-3xl font-bold text-blue-600">
                    <?= number_format($summary['excused'] ?? 0) ?>
                </p>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-700">Half Day</h3>
            <div class="mt-2">
                <p class="text-3xl font-bold text-purple-600">
                    <?= number_format($summary['half_day'] ?? 0) ?>
                </p>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-700">Vacation</h3>
            <div class="mt-2">
                <p class="text-3xl font-bold text-indigo-600">
                    <?= number_format($summary['vacation'] ?? 0) ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Attendance List -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-6">
            <table id="dailyAttendanceTable" class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th>Name</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>RFID Status</th>
                        <th>Override</th>
                        <th>Remarks</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($attendance as $record): ?>
                        <tr>
                            <td class="px-6 py-4">
                                <?= htmlspecialchars($record['name']) ?>
                                <div class="text-sm text-gray-500">
                                    <?= htmlspecialchars($record['username']) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <?= $record['time_in'] ? date('h:i:s A', strtotime($record['time_in'])) : '---' ?>
                            </td>
                            <td class="px-6 py-4">
                                <?= $record['time_out'] ? date('h:i:s A', strtotime($record['time_out'])) : '---' ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs rounded-full 
                                    <?php
                                    switch ($record['status']) {
                                        case 'on_time':
                                            echo 'bg-green-100 text-green-800';
                                            break;
                                        case 'late':
                                            echo 'bg-yellow-100 text-yellow-800';
                                            break;
                                        default:
                                            echo 'bg-red-100 text-red-800';
                                    }
                                    ?>">
                                    <?= ucfirst($record['status'] ?? 'absent') ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($record['override_status']): ?>
                                    <span class="px-2 py-1 text-xs rounded-full 
                                        <?php
                                        switch ($record['override_status']) {
                                            case 'excused':
                                                echo 'bg-blue-100 text-blue-800';
                                                break;
                                            case 'half_day':
                                                echo 'bg-purple-100 text-purple-800';
                                                break;
                                            case 'vacation':
                                                echo 'bg-indigo-100 text-indigo-800';
                                                break;
                                        }
                                        ?>">
                                        <?= ucfirst($record['override_status']) ?>
                                    </span>
                                <?php else: ?>
                                    ---
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <?= htmlspecialchars($record['override_remarks'] ?? '') ?>
                            </td>
                            <td class="px-6 py-4">
                                <button onclick="openOverrideModal(<?= htmlspecialchars(json_encode([
                                    'userId' => $record['id'],
                                    'name' => $record['name'],
                                    'override_status' => $record['override_status'] ?? null,
                                    'override_remarks' => $record['override_remarks'] ?? '',
                                    'date' => $selectedDate
                                ])) ?>)" 
                                    class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Override Modal -->
<div id="overrideModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 id="modalTitle" class="text-lg font-medium leading-6 text-gray-900 mb-4"></h3>
            <form id="overrideForm" method="POST" action="update.php">
                <input type="hidden" name="user_id" id="userId">
                <input type="hidden" name="date" id="attendanceDate">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Override Status</label>
                    <select name="override_status" id="overrideStatusSelect" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">No Override</option>
                        <option value="excused">Excused</option>
                        <option value="half_day">Half Day</option>
                        <option value="vacation">Vacation</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Remarks</label>
                    <textarea name="override_remarks" id="remarksInput" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                              placeholder="Enter reason for override"></textarea>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeOverrideModal()"
                            class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                        Cancel
                    </button>
                    <button type="submit"
                            class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        Save Override
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#dailyAttendanceTable').DataTable({
        order: [[0, 'asc']],
        pageLength: 50,
        responsive: true
    });
});

function openOverrideModal(data) {
    $('#modalTitle').text('Override Attendance: ' + data.name);
    $('#userId').val(data.userId);
    $('#attendanceDate').val(data.date);
    $('#overrideStatusSelect').val(data.override_status || '');
    $('#remarksInput').val(data.override_remarks || '');
    $('#overrideModal').removeClass('hidden');
}

function closeOverrideModal() {
    $('#overrideModal').addClass('hidden');
}

// Close modal when clicking outside
$(document).on('click', '#overrideModal', function(e) {
    if ($(e.target).is('#overrideModal')) {
        closeOverrideModal();
    }
});

// Handle escape key
$(document).keydown(function(e) {
    if (e.key === "Escape") {
        closeOverrideModal();
    }
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
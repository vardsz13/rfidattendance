<?php
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth_functions.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

// Ensure user is logged in and is admin
requireAdmin();

// Enable DataTables
$useDataTables = true;

require_once dirname(__DIR__, 2) . '/includes/header.php';

$db = getDatabase();

// Get filter parameters
$dateFrom = $_GET['date_from'] ?? date('Y-m-d');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$status = $_GET['status'] ?? '';
$type = $_GET['type'] ?? '';

// Build query conditions
$conditions = ["DATE(verification_time) BETWEEN ? AND ?"];
$params = [$dateFrom, $dateTo];

if ($status) {
    $conditions[] = "status = ?";
    $params[] = $status;
}

if ($type) {
    $conditions[] = "verification_type = ?";
    $params[] = $type;
}

// Get device logs with user information
$query = "SELECT dl.*, u.name as user_name
          FROM device_logs dl
          LEFT JOIN verification_data vd ON 
            (dl.rfid_uid = vd.rfid_uid OR dl.fingerprint_id = vd.fingerprint_id)
          LEFT JOIN users u ON vd.user_id = u.id
          WHERE " . implode(" AND ", $conditions) . "
          ORDER BY verification_time DESC";

$logs = $db->all($query, $params);
?>

<div class="space-y-6">
    <!-- Filter Form -->
    <div class="bg-white rounded-lg shadow p-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">From Date</label>
                <input type="date" name="date_from" value="<?= $dateFrom ?>" 
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">To Date</label>
                <input type="date" name="date_to" value="<?= $dateTo ?>" 
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Status</label>
                <select name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">All</option>
                    <option value="success" <?= $status === 'success' ? 'selected' : '' ?>>Success</option>
                    <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Failed</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Type</label>
                <select name="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">All</option>
                    <option value="rfid" <?= $type === 'rfid' ? 'selected' : '' ?>>RFID</option>
                    <option value="fingerprint" <?= $type === 'fingerprint' ? 'selected' : '' ?>>Fingerprint</option>
                </select>
            </div>
            <div class="md:col-span-4">
                <button type="submit" class="w-full md:w-auto px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Apply Filters
                </button>
            </div>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-800">Device Logs</h3>
            <div class="mt-4">
                <table id="deviceLogsTable" class="w-full">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Type</th>
                            <th>User</th>
                            <th>ID/UID</th>
                            <th>Status</th>
                            <th>Display Message</th>
                            <th>Sound</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= date('Y-m-d H:i:s', strtotime($log['verification_time'])) ?></td>
                                <td><?= ucfirst($log['verification_type']) ?></td>
                                <td><?= $log['user_name'] ?? 'Unknown' ?></td>
                                <td>
                                    <?php if ($log['verification_type'] === 'rfid'): ?>
                                        <?= $log['rfid_uid'] ?>
                                    <?php else: ?>
                                        <?= $log['fingerprint_id'] ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $log['status'] ?>">
                                        <?= ucfirst($log['status']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($log['lcd_display']) ?></td>
                                <td><?= $log['buzzer_sound'] ?></td>
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
    $('#deviceLogsTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 25,
        responsive: true
    });
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>

<!-- 
Date range filtering
Status filtering (success/failed/pending)
Type filtering (RFID/Fingerprint)
Detailed log table with:

Timestamp
Verification type
User information
ID/UID used
Status
LCD message
Buzzer sound


DataTables for sorting and searching
-->
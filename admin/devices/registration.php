<?php
// admin/devices/registration.php

require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth_functions.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

session_start();
requireAdmin();
$useDataTables = true;

require_once dirname(__DIR__, 2) . '/includes/header.php';

$db = getDatabase();
$error = '';
$success = '';

// Get unregistered device logs (pending status)
$pendingLogs = $db->all(
    "SELECT dl.* 
     FROM device_logs dl
     LEFT JOIN verification_data vd ON 
        (dl.rfid_uid = vd.rfid_uid OR dl.fingerprint_id = vd.fingerprint_id)
     WHERE vd.id IS NULL 
     AND dl.status = 'pending'
     ORDER BY dl.verification_time DESC"
);

// Get all users for assignment
$users = $db->all("SELECT id, name, username FROM users ORDER BY name");

// Handle assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_POST['user_id'] ?? '';
    $rfidUid = $_POST['rfid_uid'] ?? '';
    $fingerprintId = $_POST['fingerprint_id'] ?? '';
    
    if (empty($userId) || (empty($rfidUid) && empty($fingerprintId))) {
        $error = 'All fields are required';
    } else {
        try {
            $db->connect()->beginTransaction();
            
            // Check if user already has active verification data
            $existing = $db->single(
                "SELECT id FROM verification_data 
                 WHERE user_id = ? AND is_active = 1",
                [$userId]
            );
            
            if ($existing) {
                // Deactivate existing data
                $db->update(
                    'verification_data',
                    ['is_active' => 0],
                    ['user_id' => $userId]
                );
            }
            
            // Insert new verification data
            $verificationData = [
                'user_id' => $userId,
                'rfid_uid' => $rfidUid,
                'fingerprint_id' => $fingerprintId,
                'is_active' => 1
            ];
            
            if ($db->insert('verification_data', $verificationData)) {
                // Update related device logs
                $db->query(
                    "UPDATE device_logs 
                     SET status = 'success' 
                     WHERE (rfid_uid = ? OR fingerprint_id = ?) 
                     AND status = 'pending'",
                    [$rfidUid, $fingerprintId]
                );
                
                $db->connect()->commit();
                $success = 'Device registration successful';
            } else {
                throw new Exception('Failed to insert verification data');
            }
        } catch (Exception $e) {
            $db->connect()->rollBack();
            $error = 'Error registering device: ' . $e->getMessage();
        }
    }
}

// Get registered devices
$registeredDevices = $db->all(
    "SELECT vd.*, u.name, u.username 
     FROM verification_data vd
     JOIN users u ON vd.user_id = u.id
     WHERE vd.is_active = 1
     ORDER BY u.name"
);
?>

<div class="container mx-auto px-4">
    <!-- Error/Success Messages -->
    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <!-- Pending Device Logs -->
    <div class="bg-white shadow-md rounded-lg p-6 mb-6">
        <h2 class="text-xl font-bold mb-4">Pending Device Registrations</h2>
        <table id="pendingLogsTable" class="w-full">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Type</th>
                    <th>ID/UID</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingLogs as $log): ?>
                    <tr>
                        <td><?= date('Y-m-d H:i:s', strtotime($log['verification_time'])) ?></td>
                        <td><?= ucfirst($log['verification_type']) ?></td>
                        <td>
                            <?= $log['verification_type'] === 'rfid' ? 
                                $log['rfid_uid'] : $log['fingerprint_id'] ?>
                        </td>
                        <td>
                            <button onclick="assignDevice('<?= $log['verification_type'] ?>', '<?= $log['rfid_uid'] ?? '' ?>', '<?= $log['fingerprint_id'] ?? '' ?>')"
                                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-3 rounded">
                                Assign to User
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Device Assignment Modal -->
    <div id="assignmentModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Assign Device to User</h3>
                <form method="POST" id="assignmentForm">
                    <input type="hidden" name="rfid_uid" id="rfid_uid">
                    <input type="hidden" name="fingerprint_id" id="fingerprint_id">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Select User</label>
                        <select name="user_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Select User</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>">
                                    <?= htmlspecialchars($user['name']) ?> 
                                    (<?= htmlspecialchars($user['username']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal()"
                                class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                            Cancel
                        </button>
                        <button type="submit"
                                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Assign
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Registered Devices -->
    <div class="bg-white shadow-md rounded-lg p-6">
        <h2 class="text-xl fondlkjt-bold mb-4">Registered Devices</h2>
        <table id="registeredDevicesTable" class="w-full">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Username</th>
                    <th>RFID UID</th>
                    <th>Fingerprint ID</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registeredDevices as $device): ?>
                    <tr>
                        <td><?= htmlspecialchars($device['name']) ?></td>
                        <td><?= htmlspecialchars($device['username']) ?></td>
                        <td><?= $device['rfid_uid'] ?></td>
                        <td><?= $device['fingerprint_id'] ?></td>
                        <td>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $device['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                <?= $device['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td>
                            <button onclick="deactivateDevice(<?= $device['id'] ?>)"
                                    class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-3 rounded">
                                Deactivate
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function assignDevice(type, rfidUid, fingerprintId) {
    document.getElementById('rfid_uid').value = rfidUid;
    document.getElementById('fingerprint_id').value = fingerprintId;
    document.getElementById('assignmentModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('assignmentModal').classList.add('hidden');
}

function deactivateDevice(id) {
    if (confirm('Are you sure you want to deactivate this device?')) {
        // Add deactivation logic here
        window.location.href = `deactivate.php?id=${id}`;
    }
}

$(document).ready(function() {
    $('#pendingLogsTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 10
    });
    
    $('#registeredDevicesTable').DataTable({
        order: [[0, 'asc']],
        pageLength: 10
    });
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
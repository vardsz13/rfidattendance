<?php
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth_functions.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

requireAdmin();
$useDataTables = true;

$db = getDatabase();

// Get device mode
$deviceMode = $db->single(
    "SELECT setting_value FROM system_settings WHERE setting_key = 'device_mode'"
)['setting_value'];

// Get all RFID cards with assignments
$cards = $db->all(
    "SELECT rc.*, ra.id as assignment_id, ra.is_active, 
            u.name as user_name, u.username
     FROM rfid_cards rc
     LEFT JOIN rfid_assignments ra ON rc.id = ra.rfid_id
     LEFT JOIN users u ON ra.user_id = u.id
     ORDER BY rc.registered_at DESC"
);

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container mx-auto px-4">
    <!-- Device Mode Status -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="text-2xl font-bold">Device Management</h2>
                <p class="text-gray-600">Current Mode: 
                    <span class="px-2 py-1 rounded-full <?= $deviceMode === 'register' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800' ?>">
                        <?= ucfirst($deviceMode) ?>
                    </span>
                </p>
            </div>
            <div class="space-x-4">
                <a href="register.php" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    Assign RFID Cards
                </a>
                <form method="POST" action="mode.php" class="inline">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Switch to <?= ucfirst($deviceMode === 'scan' ? 'Register' : 'Scan') ?> Mode
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- RFID Cards List -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-xl font-semibold mb-4">RFID Cards</h3>
        <div class="overflow-x-auto">
            <table id="rfidTable" class="min-w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            RFID UID
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Assigned To
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Registration Date
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($cards as $card): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?= htmlspecialchars($card['rfid_uid']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?= $card['user_name'] ? htmlspecialchars($card['user_name']) : 'Unassigned' ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full <?= 
                                    $card['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                    <?= $card['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?= date('Y-m-d H:i', strtotime($card['registered_at'])) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                <?php if (!$card['user_name']): ?>
                                    <a href="register.php?card_id=<?= $card['id'] ?>" 
                                       class="text-blue-600 hover:text-blue-900">Assign</a>
                                <?php elseif ($card['is_active']): ?>
                                    <button onclick="deactivateCard(<?= $card['assignment_id'] ?>)"
                                            class="text-red-600 hover:text-red-900">
                                        Deactivate
                                    </button>
                                <?php else: ?>
                                    <span class="text-gray-400">Deactivated</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#rfidTable').DataTable({
        order: [[3, 'desc']],
        pageLength: 25
    });
});

function deactivateCard(assignmentId) {
    if (!confirm('Are you sure you want to deactivate this RFID card?')) {
        return;
    }

    fetch('<?= BASE_URL ?>/admin/devices/deactivate_rfid.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `assignment_id=${assignmentId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert(data.message);
            window.location.reload();
        } else {
            alert(data.message || 'Failed to deactivate RFID card');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to deactivate RFID card');
    });
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
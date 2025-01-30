<?php
// admin/devices/registration.php
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth_functions.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

// Ensure admin access
requireAdmin();

$db = getDatabase();

// Get unassigned RFID cards
$unassignedCards = $db->all(
    "SELECT rc.* 
     FROM rfid_cards rc
     LEFT JOIN rfid_assignments ra ON rc.id = ra.rfid_id
     WHERE ra.id IS NULL OR ra.is_active = false
     ORDER BY rc.registered_at DESC"
);

// Get users without active RFID
$availableUsers = $db->all(
    "SELECT u.* 
     FROM users u
     LEFT JOIN rfid_assignments ra ON u.id = ra.user_id AND ra.is_active = true
     WHERE ra.id IS NULL AND u.role != 'admin'
     ORDER BY u.name"
);

// Get all registered cards and their assignments
$registeredCards = $db->all(
    "SELECT rc.*, ra.id as assignment_id, ra.is_active, u.name as user_name, u.username
     FROM rfid_cards rc
     LEFT JOIN rfid_assignments ra ON rc.id = ra.rfid_id
     LEFT JOIN users u ON ra.user_id = u.id
     ORDER BY rc.registered_at DESC"
);

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container mx-auto px-4">
    <!-- Registration Status -->
    <div id="registrationStatus" class="mb-4 hidden"></div>

    <!-- Main Registration Section -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
        <h2 class="text-2xl font-bold mb-6">RFID Card Registration</h2>

        <div class="grid md:grid-cols-2 gap-6">
            <!-- RFID Scanning Section -->
            <div class="border rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Scan New RFID Card</h3>
                <div id="scanningStatus" class="p-4 bg-gray-50 rounded text-center mb-4">
                    <p class="text-gray-600">Ready to scan new RFID card</p>
                </div>
                
                <div id="deviceStatus" class="p-4 rounded mb-4 hidden">
                    <!-- Device status messages will appear here -->
                </div>

                <button id="toggleRegistration" 
                        class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                    Start Registration Mode
                </button>
            </div>

            <!-- Assignment Section -->
            <div class="border rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Assign RFID to User</h3>
                
                <?php if (empty($unassignedCards) || empty($availableUsers)): ?>
                    <div class="text-center p-4 bg-gray-50 rounded">
                        <p class="text-gray-600">
                            <?php if (empty($unassignedCards)): ?>
                                No unassigned RFID cards available
                            <?php else: ?>
                                No users available for assignment
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <form id="assignmentForm" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Select RFID Card</label>
                            <select name="rfid_id" required class="w-full rounded-md border-gray-300">
                                <option value="">Choose RFID Card</option>
                                <?php foreach ($unassignedCards as $card): ?>
                                    <option value="<?= $card['id'] ?>">
                                        UID: <?= htmlspecialchars($card['rfid_uid']) ?>
                                        (Registered: <?= date('Y-m-d H:i', strtotime($card['registered_at'])) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Select User</label>
                            <select name="user_id" required class="w-full rounded-md border-gray-300">
                                <option value="">Choose User</option>
                                <?php foreach ($availableUsers as $user): ?>
                                    <option value="<?= $user['id'] ?>">
                                        <?= htmlspecialchars($user['name']) ?> 
                                        (<?= htmlspecialchars($user['username']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" 
                                class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
                            Assign Card
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Registered Cards List -->
    <div class="bg-white rounded-lg shadow-lg p-6">
        <h3 class="text-xl font-semibold mb-4">Registered RFID Cards</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
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
                    <?php foreach ($registeredCards as $card): ?>
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
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <?php if ($card['is_active']): ?>
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
let registrationMode = false;
let eventSource = null;

document.getElementById('toggleRegistration').addEventListener('click', function() {
    if (!registrationMode) {
        startRegistrationMode();
    } else {
        stopRegistrationMode();
    }
});

function startRegistrationMode() {
    registrationMode = true;
    document.getElementById('toggleRegistration').textContent = 'Stop Registration';
    document.getElementById('toggleRegistration').classList.replace('bg-blue-500', 'bg-red-500');
    document.getElementById('scanningStatus').innerHTML = `
        <p class="text-blue-600">
            <i class="fas fa-spinner fa-spin"></i> 
            Waiting for RFID card scan...
        </p>
    `;

    // Start listening for device events
    if (typeof EventSource !== 'undefined') {
        eventSource = new EventSource('<?= BASE_URL ?>/api/devices.php?mode=registration');
        
        eventSource.onmessage = function(event) {
            const data = JSON.parse(event.data);
            handleRegistrationEvent(data);
        };

        eventSource.onerror = function() {
            showError('Connection to device lost. Please try again.');
            stopRegistrationMode();
        };
    } else {
        showError('Your browser does not support real-time updates');
    }
}

function stopRegistrationMode() {
    registrationMode = false;
    if (eventSource) {
        eventSource.close();
        eventSource = null;
    }
    
    document.getElementById('toggleRegistration').textContent = 'Start Registration Mode';
    document.getElementById('toggleRegistration').classList.replace('bg-red-500', 'bg-blue-500');
    document.getElementById('scanningStatus').innerHTML = `
        <p class="text-gray-600">Ready to scan new RFID card</p>
    `;
}

function handleRegistrationEvent(data) {
    const deviceStatus = document.getElementById('deviceStatus');
    deviceStatus.classList.remove('hidden');

    if (data.status === 'success') {
        deviceStatus.innerHTML = `
            <div class="bg-green-100 text-green-700 p-4 rounded">
                ${data.message}
            </div>
        `;
        setTimeout(() => {
            window.location.reload();
        }, 2000);
    } else {
        deviceStatus.innerHTML = `
            <div class="bg-red-100 text-red-700 p-4 rounded">
                ${data.message}
            </div>
        `;
    }
}

// Handle RFID Assignment
document.getElementById('assignmentForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('<?= BASE_URL ?>/admin/devices/assign_rfid.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showSuccess(data.message);
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showError(data.message);
        }
    })
    .catch(error => {
        showError('Failed to assign RFID card');
        console.error('Error:', error);
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
            showSuccess(data.message);
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showError(data.message);
        }
    })
    .catch(error => {
        showError('Failed to deactivate RFID card');
        console.error('Error:', error);
    });
}

function showSuccess(message) {
    const status = document.getElementById('registrationStatus');
    status.classList.remove('hidden');
    status.innerHTML = `
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            ${message}
        </div>
    `;
}

function showError(message) {
    const status = document.getElementById('registrationStatus');
    status.classList.remove('hidden');
    status.innerHTML = `
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            ${message}
        </div>
    `;
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
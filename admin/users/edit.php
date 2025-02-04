<?php
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth_functions.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

requireAdmin();
$db = getDatabase();

$userId = $_GET['id'] ?? null;
if (!$userId) {
    header('Location: index.php');
    exit();
}

// Get user data with verification details
$user = $db->single(
    "SELECT u.*, uvd.id as verification_id, rc.rfid_uid, uvd.fingerprint_id 
     FROM users u
     LEFT JOIN user_verification_data uvd ON u.id = uvd.user_id AND uvd.is_active = true
     LEFT JOIN rfid_cards rc ON uvd.rfid_id = rc.id
     WHERE u.id = ?", 
    [$userId]
);

if (!$user) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

// Handle basic info update
if (isset($_POST['action']) && $_POST['action'] === 'update_basic') {
    $idNumber = trim($_POST['id_number'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $userType = $_POST['user_type'] ?? 'normal';
    $remarks = trim($_POST['remarks'] ?? '');
    $password = $_POST['password'] ?? '';

    try {
        // Check if ID number exists (excluding current user)
        $existing = $db->single(
            "SELECT id FROM users WHERE id_number = ? AND id != ?", 
            [$idNumber, $userId]
        );
        
        if ($existing) {
            throw new Exception('ID Number already exists');
        }

        $userData = [
            'id_number' => $idNumber,
            'name' => $name,
            'user_type' => $userType,
            'remarks' => $remarks
        ];

        // Only update password if provided
        if (!empty($password)) {
            $userData['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        if ($db->update('users', $userData, ['id' => $userId])) {
            flashMessage('Basic information updated successfully');
            header("Location: edit.php?id=$userId");
            exit();
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle RFID reassignment
if (isset($_POST['action']) && $_POST['action'] === 'assign_rfid') {
    $rfidId = $_POST['rfid_id'] ?? null;
    
    try {
        if (!$rfidId) {
            throw new Exception('Please select an RFID card');
        }

        // Deactivate current RFID
        $db->update(
            'user_verification_data',
            ['is_active' => false],
            ['user_id' => $userId, 'is_active' => true]
        );

        // Create new verification record
        $verificationId = $db->insert('user_verification_data', [
            'user_id' => $userId,
            'rfid_id' => $rfidId,
            'fingerprint_id' => $user['fingerprint_id'], // Maintain existing fingerprint
            'assigned_at' => date('Y-m-d H:i:s'),
            'is_active' => true
        ]);

        if ($verificationId) {
            flashMessage('RFID card reassigned successfully');
            header("Location: edit.php?id=$userId");
            exit();
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get unassigned RFID cards
$unassignedCards = $db->all(
    "SELECT rc.* FROM rfid_cards rc 
     LEFT JOIN user_verification_data uvd ON rc.id = uvd.rfid_id 
     WHERE uvd.id IS NULL OR uvd.is_active = false 
     ORDER BY rc.registered_at DESC"
);

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Edit User: <?= htmlspecialchars($user['name']) ?></h1>
        <a href="index.php" class="text-blue-500 hover:text-blue-700">
            <i class="fas fa-arrow-left mr-2"></i> Back to Users
        </a>
    </div>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Basic Information Section -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Basic Information</h2>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="update_basic">
            
            <div>
                <label class="block text-sm font-medium text-gray-700">ID Number</label>
                <input type="text" name="id_number" value="<?= htmlspecialchars($user['id_number']) ?>" required
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Full Name</label>
                <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">New Password (leave blank to keep current)</label>
                <input type="password" name="password"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">User Type</label>
                <select name="user_type" required 
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        onchange="toggleRemarks(this.value)">
                    <option value="normal" <?= $user['user_type'] === 'normal' ? 'selected' : '' ?>>Normal</option>
                    <option value="special" <?= $user['user_type'] === 'special' ? 'selected' : '' ?>>Special</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Remarks</label>
                <textarea name="remarks" rows="3" 
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"><?= htmlspecialchars($user['remarks']) ?></textarea>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    Update Basic Info
                </button>
            </div>
        </form>
    </div>

    <!-- RFID Management Section -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">RFID Management</h2>
        
        <div class="mb-4">
            <p class="text-gray-600">Current RFID:</p>
            <p class="font-semibold"><?= $user['rfid_uid'] ? htmlspecialchars($user['rfid_uid']) : 'No RFID assigned' ?></p>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="assign_rfid">
            
            <div>
                <label class="block text-sm font-medium text-gray-700">Assign New RFID Card</label>
                <select name="rfid_id" required 
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Choose a card</option>
                    <?php foreach ($unassignedCards as $card): ?>
                        <option value="<?= $card['id'] ?>">
                            UID: <?= htmlspecialchars($card['rfid_uid']) ?> 
                            (Registered: <?= date('Y-m-d H:i', strtotime($card['registered_at'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex justify-between items-center">
                <button type="button" onclick="startRfidRegistration()" 
                        class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                    Register New Card
                </button>
                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    Assign Selected Card
                </button>
            </div>
        </form>
    </div>

    <!-- Fingerprint Section -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4">Fingerprint Management</h2>
        
        <div class="mb-4">
            <p class="text-gray-600">Current Fingerprint ID:</p>
            <p class="font-semibold"><?= $user['fingerprint_id'] ? htmlspecialchars($user['fingerprint_id']) : 'No fingerprint registered' ?></p>
        </div>

        <?php if ($user['user_type'] === 'normal'): ?>
            <div id="fingerprintStatus" class="mb-4">
                <div class="p-4 border rounded-lg">
                    <p id="statusMessage" class="text-center text-gray-600">
                        Click Start Registration to register new fingerprint
                    </p>
                </div>
            </div>

            <div class="flex justify-end">
                <button onclick="startFingerprintRegistration()" 
                        class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    Start Fingerprint Registration
                </button>
            </div>
        <?php else: ?>
            <div class="p-4 bg-gray-50 rounded-lg">
                <p class="text-gray-600">Fingerprint registration not required for special users.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleRemarks(type) {
    const remarks = document.querySelector('textarea[name="remarks"]');
    remarks.placeholder = type === 'special' 
        ? 'Please describe the special condition' 
        : "Enter 'Normal'";
}

function startRfidRegistration() {
    const button = event.target;
    
    // First check current mode
    fetch('<?= BASE_URL ?>/api/device_status.php')
    .then(response => response.json())
    .then(statusData => {
        if (statusData.mode === 'register') {
            // Already in register mode
            alert('Device is ready. Please scan new RFID card on the device.');
            initializeRegistrationListener(button);
        } else {
            // Set to register mode
            return fetch('<?= BASE_URL ?>/api/toggle_mode.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ mode: 'register' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success' || data.message.includes('already')) {
                    alert('Device is ready. Please scan new RFID card on the device.');
                    initializeRegistrationListener(button);
                } else {
                    throw new Error(data.message);
                }
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error starting registration. Please try again.');
    });
}

function initializeRegistrationListener(button) {
    button.disabled = true;
    
    // Add event listener for device events
    const eventSource = new EventSource('<?= BASE_URL ?>/device_events.php');
    
    eventSource.onmessage = function(event) {
        const data = JSON.parse(event.data);
        if (data.rfid_status === 'success') {
            // RFID registered successfully
            eventSource.close();
            
            // Set back to scan mode
            fetch('<?= BASE_URL ?>/api/toggle_mode.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ mode: 'scan' })
            })
            .then(() => window.location.reload());
        }
    };
    
    // Timeout after 30 seconds
    setTimeout(() => {
        eventSource.close();
        fetch('<?= BASE_URL ?>/api/toggle_mode.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ mode: 'scan' })
        })
        .then(() => {
            button.disabled = false;
            window.location.reload();
        });
    }, 30000);
}

function startFingerprintRegistration() {
    if (!confirm('Start fingerprint registration? Current fingerprint will be replaced.')) {
        return;
    }

    const button = event.target;
    const statusMessage = document.getElementById('statusMessage');
    
    // First check current mode
    fetch('<?= BASE_URL ?>/api/device_status.php')
    .then(response => response.json())
    .then(statusData => {
        if (statusData.mode === 'register') {
            // Already in register mode
            statusMessage.textContent = 'Please follow the instructions on the device LCD';
            initializeFingerprintListener(button, statusMessage);
        } else {
            // Set to register mode
            return fetch('<?= BASE_URL ?>/api/toggle_mode.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ mode: 'register' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success' || data.message.includes('already')) {
                    statusMessage.textContent = 'Please follow the instructions on the device LCD';
                    initializeFingerprintListener(button, statusMessage);
                } else {
                    throw new Error(data.message);
                }
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        statusMessage.textContent = 'Error starting registration. Please try again.';
    });
}

function initializeFingerprintListener(button, statusMessage) {
    button.disabled = true;
    button.textContent = 'Registration in Progress';
    
    // Add event listener for device events
    const eventSource = new EventSource('<?= BASE_URL ?>/device_events.php');
    
    eventSource.onmessage = function(event) {
        const data = JSON.parse(event.data);
        if (data.fingerprint_status === 'success') {
            // Fingerprint registered successfully
            eventSource.close();
            
            // Set back to scan mode
            fetch('<?= BASE_URL ?>/api/toggle_mode.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ mode: 'scan' })
            })
            .then(() => window.location.reload());
        }
    };
    
    // Timeout after 30 seconds
    setTimeout(() => {
        eventSource.close();
        fetch('<?= BASE_URL ?>/api/toggle_mode.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ mode: 'scan' })
        })
        .then(() => {
            button.disabled = false;
            button.textContent = 'Start Fingerprint Registration';
            statusMessage.textContent = 'Registration timed out. Please try again.';
        });
    }, 30000);
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
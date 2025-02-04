<?php
// admin/users/register/rfid.php
require_once dirname(__DIR__, 3) . '/config/constants.php';
require_once dirname(__DIR__, 3) . '/includes/auth_functions.php';
require_once dirname(__DIR__, 3) . '/includes/functions.php';

requireAdmin();
$db = getDatabase();

$userId = $_GET['user_id'] ?? null;
if (!$userId) {
    header('Location: basic.php');
    exit();
}

// Get user details
$user = $db->single(
    "SELECT * FROM users WHERE id = ?", 
    [$userId]
);

if (!$user) {
    header('Location: basic.php');
    exit();
}

// Get unassigned RFID cards
$unassignedCards = $db->all(
    "SELECT rc.* FROM rfid_cards rc 
     LEFT JOIN user_verification_data uvd ON rc.id = uvd.rfid_id 
     WHERE uvd.id IS NULL 
     OR uvd.is_active = false 
     ORDER BY rc.registered_at DESC"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rfidId = $_POST['rfid_id'] ?? null;
    
    try {
        if (!$rfidId) {
            throw new Exception('Please select an RFID card');
        }

        // Deactivate any existing active cards for this user
        $db->update(
            'user_verification_data',
            ['is_active' => false],
            ['user_id' => $userId, 'is_active' => true]
        );

        // Create new verification record
        $verificationId = $db->insert('user_verification_data', [
            'user_id' => $userId,
            'rfid_id' => $rfidId,
            'assigned_at' => date('Y-m-d H:i:s'),
            'is_active' => true
        ]);

        if (!$verificationId) {
            throw new Exception('Failed to assign RFID card');
        }

        // Redirect based on user type
        if ($user['user_type'] === 'special') {
            header("Location: complete.php?user_id=$userId");
        } else {
            header("Location: fingerprint.php?user_id=$userId");
        }
        exit();

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

require_once dirname(__DIR__, 3) . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Breadcrumbs -->
    <div class="mb-8">
        <div class="flex items-center space-x-2 text-gray-500">
            <span class="bg-green-500 text-white px-3 py-1 rounded-full">✓</span>
            <span class="text-green-500">User Details</span>
            <span>→</span>
            <span class="bg-blue-500 text-white px-3 py-1 rounded-full">2</span>
            <span>RFID Assignment</span>
            <span>→</span>
            <?php if ($user['user_type'] === 'normal'): ?>
                <span class="text-gray-400">Fingerprint</span>
                <span>→</span>
            <?php endif; ?>
            <span class="text-gray-400">Complete</span>
        </div>
    </div>

    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-2xl font-bold mb-6">Assign RFID Card</h2>
            
            <div class="mb-4">
                <p class="text-gray-600">Assigning RFID for:</p>
                <p class="font-semibold"><?= htmlspecialchars($user['name']) ?></p>
                <p class="text-sm text-gray-500">ID: <?= htmlspecialchars($user['id_number']) ?></p>
            </div>

            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (empty($unassignedCards)): ?>
                <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
                    <p>No unassigned RFID cards available.</p>
                    <button onclick="startRegistration()" 
                            class="mt-2 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        Register New Card
                    </button>
                </div>
            <?php else: ?>
                <form method="POST" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Select RFID Card</label>
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
                        <button type="button" onclick="window.history.back()" 
                                class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                            ← Back
                        </button>
                        <div>
                            <button type="button" onclick="startRegistration()" 
                                    class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 mr-2">
                                Register New Card
                            </button>
                            <button type="submit" 
                                    class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                                Next: <?= $user['user_type'] === 'special' ? 'Complete' : 'Fingerprint' ?> →
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function startRegistration() {
    fetch('<?= BASE_URL ?>/api/toggle_mode.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ mode: 'register' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert('Please scan a new RFID card on the device');
            // Reload page after 5 seconds
            setTimeout(() => window.location.reload(), 5000);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to start registration mode');
    });
}
</script>

<?php require_once dirname(__DIR__, 3) . '/includes/footer.php'; ?>
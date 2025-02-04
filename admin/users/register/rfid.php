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

// Get all available RFID cards (unassigned or inactive assignments)
$availableCards = $db->all(
    "SELECT rc.*, 
            CASE 
                WHEN uvd.id IS NOT NULL THEN 'Previously assigned'
                ELSE 'Never assigned'
            END as status,
            COALESCE(u.name, 'None') as last_assigned_to
     FROM rfid_cards rc 
     LEFT JOIN user_verification_data uvd ON rc.id = uvd.rfid_id AND uvd.is_active = false
     LEFT JOIN users u ON uvd.user_id = u.id
     WHERE rc.id NOT IN (
         SELECT rfid_id 
         FROM user_verification_data 
         WHERE is_active = true
     )
     ORDER BY rc.registered_at DESC"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rfidId = $_POST['rfid_id'] ?? null;
    
    try {
        if (!$rfidId) {
            throw new Exception('Please select an RFID card');
        }

        // Start transaction
        $db->connect()->beginTransaction();

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

        $db->connect()->commit();

        flashMessage('RFID card successfully assigned');

        // Redirect based on user type
        if ($user['user_type'] === 'special') {
            header("Location: complete.php?user_id=$userId");
        } else {
            header("Location: fingerprint.php?user_id=$userId");
        }
        exit();

    } catch (Exception $e) {
        if ($db->connect()->inTransaction()) {
            $db->connect()->rollBack();
        }
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

            <?php if (empty($availableCards)): ?>
                <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
                    <p>No RFID cards available for assignment.</p>
                    <p class="mt-2 text-sm">Please register new cards in the device management section first.</p>
                </div>
            <?php else: ?>
                <form method="POST" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Select RFID Card</label>
                        <select name="rfid_id" required 
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Choose a card</option>
                            <?php foreach ($availableCards as $card): ?>
                                <option value="<?= $card['id'] ?>" class="py-2">
                                    UID: <?= htmlspecialchars($card['rfid_uid']) ?> 
                                    (<?= htmlspecialchars($card['status']) ?> - 
                                     Last user: <?= htmlspecialchars($card['last_assigned_to']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex justify-between items-center">
                        <a href="basic.php" 
                           class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                            ← Back
                        </a>
                        <button type="submit" 
                                class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                            Next: <?= $user['user_type'] === 'special' ? 'Complete' : 'Fingerprint' ?> →
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 3) . '/includes/footer.php'; ?>
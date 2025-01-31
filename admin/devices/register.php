<?php
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth_functions.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

requireAdmin();

$db = getDatabase();$success = '';

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rfidId = $_POST['rfid_id'] ?? '';
    $userId = $_POST['user_id'] ?? '';
    
    if (empty($rfidId) || empty($userId)) {
        $error = 'Please select both RFID card and user';
    } else {
        try {
            $db->connect()->beginTransaction();
            
            // Verify RFID card exists and isn't already assigned
            $card = $db->single(
                "SELECT rc.* FROM rfid_cards rc
                 LEFT JOIN rfid_assignments ra ON rc.id = ra.rfid_id AND ra.is_active = true
                 WHERE rc.id = ? AND ra.id IS NULL",
                [$rfidId]
            );
            
            if (!$card) {
                throw new Exception('Invalid or already assigned RFID card');
            }
            
            // Verify user exists and doesn't have an active RFID
            $user = $db->single(
                "SELECT u.* FROM users u
                 LEFT JOIN rfid_assignments ra ON u.id = ra.user_id AND ra.is_active = true
                 WHERE u.id = ? AND ra.id IS NULL",
                [$userId]
            );
            
            if (!$user) {
                throw new Exception('Invalid user or user already has an active RFID card');
            }
            
            // Create assignment
            $assignmentData = [
                'rfid_id' => $rfidId,
                'user_id' => $userId,
                'assigned_at' => date('Y-m-d H:i:s'),
                'is_active' => true
            ];
            
            if ($db->insert('rfid_assignments', $assignmentData)) {
                $db->connect()->commit();
                flashMessage('RFID card successfully assigned');
                header('Location: index.php');
                exit();
            } else {
                throw new Exception('Failed to create assignment');
            }
            
        } catch (Exception $e) {
            $db->connect()->rollBack();
            $error = $e->getMessage();
        }
    }
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container mx-auto px-4">
    <div class="max-w-2xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold">Assign RFID Card</h2>
            <a href="index.php" class="text-blue-500 hover:text-blue-700">Back to Devices</a>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($unassignedCards) || empty($availableUsers)): ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
                <?php if (empty($unassignedCards)): ?>
                    No unassigned RFID cards available. Switch to register mode to add new cards.
                <?php else: ?>
                    No users available for assignment.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="bg-white shadow-md rounded-lg p-6">
                <form method="POST" class="space-y-6">
                    <div>
                        <label for="rfid_id" class="block text-sm font-medium text-gray-700">Select RFID Card</label>
                        <select name="rfid_id" id="rfid_id" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Choose RFID Card</option>
                            <?php foreach ($unassignedCards as $card): ?>
                                <option value="<?= $card['id'] ?>" <?= isset($_GET['card_id']) && $_GET['card_id'] == $card['id'] ? 'selected' : '' ?>>
                                    UID: <?= htmlspecialchars($card['rfid_uid']) ?>
                                    (Registered: <?= date('Y-m-d H:i', strtotime($card['registered_at'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="user_id" class="block text-sm font-medium text-gray-700">Select User</label>
                        <select name="user_id" id="user_id" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Choose User</option>
                            <?php foreach ($availableUsers as $user): ?>
                                <option value="<?= $user['id'] ?>">
                                    <?= htmlspecialchars($user['name']) ?> 
                                    (<?= htmlspecialchars($user['username']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" 
                                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                            Assign RFID Card
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
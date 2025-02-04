<?php
// admin/users/register/complete.php
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

// Get complete user details with verification data
$user = $db->single(
    "SELECT u.*, rc.rfid_uid, uvd.fingerprint_id, uvd.assigned_at
     FROM users u
     LEFT JOIN user_verification_data uvd ON u.id = uvd.user_id AND uvd.is_active = true
     LEFT JOIN rfid_cards rc ON uvd.rfid_id = rc.id
     WHERE u.id = ?",
    [$userId]
);

if (!$user) {
    header('Location: basic.php');
    exit();
}

// Verify all required steps are complete
if (!$user['rfid_uid']) {
    header("Location: rfid.php?user_id=$userId");
    exit();
}

if ($user['user_type'] === 'normal' && !$user['fingerprint_id']) {
    header("Location: fingerprint.php?user_id=$userId");
    exit();
}

// Reset device mode to scan
$db->update(
    'system_settings',
    ['setting_value' => 'scan'],
    "setting_key = 'device_mode'"
);

require_once dirname(__DIR__, 3) . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Breadcrumbs -->
    <div class="mb-8">
        <div class="flex items-center space-x-2 text-gray-500">
            <span class="bg-green-500 text-white px-3 py-1 rounded-full">✓</span>
            <span class="text-green-500">Basic Info</span>
            <span>→</span>
            <span class="bg-green-500 text-white px-3 py-1 rounded-full">✓</span>
            <span class="text-green-500">RFID Assignment</span>
            <span>→</span>
            <?php if ($user['user_type'] === 'normal'): ?>
                <span class="bg-green-500 text-white px-3 py-1 rounded-full">✓</span>
                <span class="text-green-500">Fingerprint</span>
                <span>→</span>
            <?php endif; ?>
            <span class="bg-green-500 text-white px-3 py-1 rounded-full">✓</span>
            <span class="text-green-500">Complete</span>
        </div>
    </div>

    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="text-center mb-6">
                <div class="inline-block p-3 rounded-full bg-green-100 text-green-500 mb-4">
                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-900">Registration Complete!</h2>
            </div>

            <div class="bg-gray-50 rounded-lg p-6 mb-6">
                <h3 class="font-semibold text-lg mb-4">User Details</h3>
                <dl class="grid grid-cols-1 gap-4">
                    <div class="flex justify-between border-b pb-2">
                        <dt class="text-gray-600">ID Number:</dt>
                        <dd class="font-medium"><?= htmlspecialchars($user['id_number']) ?></dd>
                    </div>
                    <div class="flex justify-between border-b pb-2">
                        <dt class="text-gray-600">Name:</dt>
                        <dd class="font-medium"><?= htmlspecialchars($user['name']) ?></dd>
                    </div>
                    <div class="flex justify-between border-b pb-2">
                        <dt class="text-gray-600">User Type:</dt>
                        <dd class="font-medium">
                            <span class="px-2 py-1 rounded-full text-sm <?= 
                                $user['user_type'] === 'special' 
                                ? 'bg-purple-100 text-purple-800' 
                                : 'bg-blue-100 text-blue-800' 
                            ?>">
                                <?= ucfirst($user['user_type']) ?>
                            </span>
                        </dd>
                    </div>
                    <?php if ($user['user_type'] === 'special'): ?>
                        <div class="flex justify-between border-b pb-2">
                            <dt class="text-gray-600">Remarks:</dt>
                            <dd class="font-medium"><?= htmlspecialchars($user['remarks']) ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </div>

            <div class="bg-gray-50 rounded-lg p-6 mb-6">
                <h3 class="font-semibold text-lg mb-4">Verification Details</h3>
                <dl class="grid grid-cols-1 gap-4">
                    <div class="flex justify-between border-b pb-2">
                        <dt class="text-gray-600">RFID Card:</dt>
                        <dd class="font-medium"><?= htmlspecialchars($user['rfid_uid']) ?></dd>
                    </div>
                    <?php if ($user['user_type'] === 'normal'): ?>
                        <div class="flex justify-between border-b pb-2">
                            <dt class="text-gray-600">Fingerprint ID:</dt>
                            <dd class="font-medium"><?= htmlspecialchars($user['fingerprint_id']) ?></dd>
                        </div>
                    <?php endif; ?>
                    <div class="flex justify-between border-b pb-2">
                        <dt class="text-gray-600">Assigned On:</dt>
                        <dd class="font-medium">
                            <?= date('F j, Y g:i A', strtotime($user['assigned_at'])) ?>
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="flex justify-between items-center mt-6">
                <a href="<?= BASE_URL ?>/admin/users" 
                   class="text-gray-600 hover:text-gray-800">
                    ← Back to Users List
                </a>
                <a href="basic.php" 
                   class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    Register Another User
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 3) . '/includes/footer.php'; ?>
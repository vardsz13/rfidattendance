<?php
// user/profile/edit.php
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth_functions.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

// Ensure user is logged in
requireLogin();

$db = getDatabase();
$error = '';
$success = '';

// Get user data
$userId = $_SESSION['user_id'];
$user = $db->single("SELECT * FROM users WHERE id = ?", [$userId]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($username)) {
        $error = 'Username is required';
    } elseif (!empty($newPassword) && empty($currentPassword)) {
        $error = 'Current password is required to set a new password';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New passwords do not match';
    } else {
        // Check if username exists (excluding current user)
        $existing = $db->single(
            "SELECT id FROM users WHERE username = ? AND id != ?", 
            [$username, $userId]
        );
        
        if ($existing) {
            $error = 'Username already exists';
        } else {
            // Verify current password if changing password
            $canUpdate = true;
            if (!empty($newPassword)) {
                $canUpdate = password_verify($currentPassword, $user['password']);
                if (!$canUpdate) {
                    $error = 'Current password is incorrect';
                }
            }
            
            if ($canUpdate) {
                // Update user data
                $userData = ['username' => $username];
                if (!empty($newPassword)) {
                    $userData['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
                }
                
                if ($db->update('users', $userData, ['id' => $userId])) {
                    $_SESSION['username'] = $username;
                    flashMessage('Profile updated successfully');
                    header('Location: view.php');
                    exit();
                } else {
                    $error = 'Error updating profile';
                }
            }
        }
    }
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container mx-auto px-4">
    <div class="max-w-2xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold">Edit Profile</h2>
            <a href="view.php" class="text-blue-500 hover:text-blue-700">Back to Profile</a>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="bg-white shadow-md rounded-lg p-6">
            <form method="POST" action="">
                <!-- Username -->
                <div class="mb-4">
                    <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                    <input type="text" name="username" id="username" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                           value="<?= htmlspecialchars($user['username']) ?>">
                </div>

                <!-- Current Password (required for password change) -->
                <div class="mb-4">
                    <label for="current_password" class="block text-sm font-medium text-gray-700">
                        Current Password (required to change password)
                    </label>
                    <input type="password" name="current_password" id="current_password"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <!-- New Password -->
                <div class="mb-4">
                    <label for="new_password" class="block text-sm font-medium text-gray-700">
                        New Password (leave empty to keep current)
                    </label>
                    <input type="password" name="new_password" id="new_password"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <!-- Confirm New Password -->
                <div class="mb-6">
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700">
                        Confirm New Password
                    </label>
                    <input type="password" name="confirm_password" id="confirm_password"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end">
                    <button type="submit" 
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Update Profile
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
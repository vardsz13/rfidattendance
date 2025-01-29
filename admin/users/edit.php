<?php
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth_functions.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

session_start();

// Check login and admin status
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . AUTH_URL . '/login.php');
    exit();
}

$db = getDatabase();
$error = '';
$success = '';

// Get user ID from URL
$userId = $_GET['id'] ?? null;
if (!$userId) {
    header('Location: index.php');
    exit();
}

// Get user data
$user = $db->single("SELECT * FROM users WHERE id = ?", [$userId]);
if (!$user) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $role = $_POST['role'] ?? 'user';
    $password = $_POST['password'] ?? ''; // Optional for edit
    
    // Validation
    if (empty($username) || empty($name)) {
        $error = 'Username and name are required';
    } else {
        // Check if username exists (excluding current user)
        $existing = $db->single(
            "SELECT id FROM users WHERE username = ? AND id != ?", 
            [$username, $userId]
        );
        
        if ($existing) {
            $error = 'Username already exists';
        } else {
            // Update user data
            $userData = [
                'username' => $username,
                'name' => $name,
                'role' => $role
            ];
            
            // Only update password if provided
            if (!empty($password)) {
                $userData['password'] = password_hash($password, PASSWORD_DEFAULT);
            }
            
            if ($db->update('users', $userData, ['id' => $userId])) {
                flashMessage('User updated successfully');
                header('Location: index.php');
                exit();
            } else {
                $error = 'Error updating user';
            }
        }
    }
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container mx-auto px-4">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">Edit User: <?= htmlspecialchars($user['name']) ?></h2>
        <a href="index.php" class="text-blue-500 hover:text-blue-700">
            Back to Users List
        </a>
    </div>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white shadow-md rounded-lg p-6">
        <form method="POST" action="">
            <div class="mb-4">
                <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                <input type="text" name="username" id="username" required
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                       value="<?= htmlspecialchars($user['username']) ?>">
            </div>

            <div class="mb-4">
                <label for="password" class="block text-sm font-medium text-gray-700">
                    Password (leave empty to keep current)
                </label>
                <input type="password" name="password" id="password"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>

            <div class="mb-4">
                <label for="name" class="block text-sm font-medium text-gray-700">Full Name</label>
                <input type="text" name="name" id="name" required
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                       value="<?= htmlspecialchars($user['name']) ?>">
            </div>

            <div class="mb-4">
                <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                <select name="role" id="role" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Update User
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
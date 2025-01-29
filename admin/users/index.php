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

// Enable DataTables
$useDataTables = true;

require_once dirname(__DIR__, 2) . '/includes/header.php';

$db = getDatabase();
$users = $db->all("SELECT * FROM users ORDER BY created_at DESC");
?>

<div class="container mx-auto px-4">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">Users Management</h2>
        <a href="add.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            Add New User
        </a>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <table id="usersTable" class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Username</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created At</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($users as $user): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($user['username']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($user['name']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                            <?= $user['role'] === 'admin' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>">
                            <?= ucfirst($user['role']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?= date('Y-m-d H:i', strtotime($user['created_at'])) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                        <a href="edit.php?id=<?= $user['id'] ?>" class="text-blue-600 hover:text-blue-900">Edit</a>
                        <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                        <a href="delete.php?id=<?= $user['id'] ?>" 
                           onclick="return confirm('Are you sure you want to delete this user?')"
                           class="text-red-600 hover:text-red-900">Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#usersTable').DataTable({
        order: [[3, 'desc']], // Sort by created_at by default
        pageLength: 10
    });
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
<?php
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth_functions.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

// Ensure admin access
requireAdmin();

$db = getDatabase();
$error = '';
$success = '';

// Enable DataTables
$useDataTables = true;

// Get all users
try {
    $users = $db->all(
        "SELECT id, id_number, name, role, user_type, remarks, created_at,
         (SELECT COUNT(*) FROM attendance_logs WHERE user_id = users.id) as attendance_count
         FROM users 
         WHERE role = 'student'
         ORDER BY created_at DESC"
    );
} catch (Exception $e) {
    $error = 'Error fetching users: ' . $e->getMessage();
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">User Management</h1>
        <a href="register/basic.php" 
           class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center">
            <i class="fas fa-user-plus mr-2"></i> Register New User
        </a>
    </div>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-md">
        <div class="overflow-x-auto">
        <table id="usersTable" class="min-w-full">
    <thead class="bg-gray-50">
        <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID Number</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User Type</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Remarks</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Registered</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Attendance</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
        </tr>
    </thead>
    <tbody class="bg-white divide-y divide-gray-200">
        <?php foreach ($users as $user): ?>
        <tr>
            <td class="px-6 py-4 whitespace-nowrap">
                <?= htmlspecialchars($user['id_number']) ?>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <?= htmlspecialchars($user['name']) ?>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 py-1 text-xs rounded-full <?= 
                    $user['user_type'] === 'special' 
                        ? 'bg-purple-100 text-purple-800' 
                        : 'bg-blue-100 text-blue-800' 
                ?>">
                    <?= ucfirst($user['user_type']) ?>
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <?= htmlspecialchars($user['remarks'] ?: '-') ?>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <?= date('M j, Y', strtotime($user['created_at'])) ?>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <?= number_format($user['attendance_count']) ?> records
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex space-x-2">
                    <a href="edit.php?id=<?= $user['id'] ?>" 
                       class="text-blue-600 hover:text-blue-900">
                        <i class="fas fa-edit"></i>
                    </a>
                    <a href="attendance/view.php?id=<?= $user['id'] ?>" 
                       class="text-green-600 hover:text-green-900">
                        <i class="fas fa-calendar-alt"></i>
                    </a>
                    <button onclick="confirmDelete(<?= $user['id'] ?>)" 
                            class="text-red-600 hover:text-red-900">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Delete User</h3>
            <div class="mt-2 px-7 py-3">
                <p class="text-sm text-gray-500">
                    Are you sure you want to delete this user? This action cannot be undone.
                </p>
            </div>
            <div class="items-center px-4 py-3">
                <form id="deleteForm" method="POST" action="delete.php">
                    <input type="hidden" id="deleteUserId" name="id">
                    <button type="button" 
                            onclick="closeDeleteModal()"
                            class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 mr-2">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#usersTable').DataTable({
        order: [[3, 'desc']], // Sort by registration date by default
        pageLength: 25,
        language: {
            search: "Search users:",
            lengthMenu: "Show _MENU_ users per page",
            info: "Showing _START_ to _END_ of _TOTAL_ users",
            infoEmpty: "No users found",
            infoFiltered: "(filtered from _MAX_ total users)"
        }
    });
});

function confirmDelete(userId) {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        // Redirect to delete.php with the user ID
        window.location.href = `delete.php?id=${userId}`;
    }
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
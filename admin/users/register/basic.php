<?php
// admin/users/register/step1.php
require_once dirname(__DIR__, 3) . '/config/constants.php';
require_once dirname(__DIR__, 3) . '/includes/auth_functions.php';
require_once dirname(__DIR__, 3) . '/includes/functions.php';

requireAdmin();
$db = getDatabase();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idNumber = trim($_POST['id_number']);
    $password = $_POST['password'];
    $name = trim($_POST['name']);
    $userType = $_POST['user_type'];
    $remarks = trim($_POST['remarks']);

    try {
        // Validate input
        if (empty($idNumber) || empty($password) || empty($name)) {
            throw new Exception('All fields are required');
        }

        // Check ID number unique
        $existing = $db->single("SELECT id FROM users WHERE id_number = ?", [$idNumber]);
        if ($existing) {
            throw new Exception('ID Number already exists');
        }

        // Create user
        $userId = $db->insert('users', [
            'id_number' => $idNumber,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'name' => $name,
            'role' => 'student',
            'user_type' => $userType,
            'remarks' => $remarks,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        if ($userId) {
            // Redirect to RFID assignment
            header("Location: rfid.php?user_id=$userId");
            exit();
        }

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
            <span class="bg-blue-500 text-white px-3 py-1 rounded-full">1</span>
            <span>User Details</span>
            <span>→</span>
            <span class="text-gray-400">RFID Assignment</span>
            <span>→</span>
            <span class="text-gray-400">Fingerprint</span>
            <span>→</span>
            <span class="text-gray-400">Complete</span>
        </div>
    </div>

    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-2xl font-bold mb-6">Add New User</h2>

            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700">ID Number</label>
                    <input type="text" name="id_number" required 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" name="password" required 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Full Name</label>
                    <input type="text" name="name" required 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">User Type</label>
                    <select name="user_type" required 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            onchange="toggleRemarks(this.value)">
                        <option value="normal">Normal</option>
                        <option value="special">Special</option>
                    </select>
                </div>

                <div id="remarksField">
                    <label class="block text-sm font-medium text-gray-700">Remarks</label>
                    <textarea name="remarks" rows="3" 
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                              placeholder="For normal users, enter 'Normal'. For special users, describe their condition."></textarea>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        Next: RFID Assignment →
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleRemarks(type) {
    const remarks = document.querySelector('textarea[name="remarks"]');
    remarks.placeholder = type === 'special' 
        ? 'Please describe the special condition' 
        : "Enter 'Normal'";
}
</script>

<?php require_once dirname(__DIR__, 3) . '/includes/footer.php'; ?>
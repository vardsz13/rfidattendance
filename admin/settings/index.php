<?php
// admin/settings/index.php
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth_functions.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

requireAdmin();

$db = getDatabase();
$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lateTime = $_POST['late_time'] ?? '';
    
    // Validate time format
    if (preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/', $lateTime)) {
        try {
            $db->update(
                'system_settings',
                ['setting_value' => $lateTime],
                "setting_key = 'late_time'"
            );
            $success = 'Settings updated successfully';
        } catch (Exception $e) {
            $error = 'Error updating settings';
        }
    } else {
        $error = 'Invalid time format. Please use HH:MM:SS (24-hour format)';
    }
}

// Get current settings
$settings = $db->single(
    "SELECT * FROM system_settings WHERE setting_key = 'late_time'"
);

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container mx-auto px-4">
    <div class="max-w-2xl mx-auto">
        <h2 class="text-2xl font-bold mb-6">System Settings</h2>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="bg-white shadow-md rounded-lg p-6">
            <form method="POST">
                <div class="mb-4">
                    <label for="late_time" class="block text-sm font-medium text-gray-700">
                        Late Time Threshold
                    </label>
                    <input type="time" 
                           name="late_time" 
                           id="late_time" 
                           step="1"
                           required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                           value="<?= $settings['setting_value'] ?? '09:00:00' ?>">
                    <p class="mt-1 text-sm text-gray-500">
                        Attendance after this time will be marked as 'late'. Use 24-hour format (HH:MM:SS).
                    </p>
                </div>

                <div class="flex justify-end">
                    <button type="submit" 
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
<?php
// admin/devices/mode.php
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth_functions.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

requireAdmin();
$db = getDatabase();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentMode = $db->single(
        "SELECT setting_value FROM system_settings WHERE setting_key = 'device_mode'"
    )['setting_value'];
    
    $newMode = $currentMode === 'scan' ? 'register' : 'scan';
    
    if ($db->update(
        'system_settings',
        ['setting_value' => $newMode],
        "setting_key = 'device_mode'"
    )) {
        flashMessage("Device mode changed to: " . ucfirst($newMode));
    } else {
        flashMessage("Failed to change device mode", "error");
    }
    
    header('Location: index.php');
    exit();
}

$mode = $db->single(
    "SELECT setting_value FROM system_settings WHERE setting_key = 'device_mode'"
)['setting_value'];

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container mx-auto px-4">
    <div class="max-w-xl mx-auto">
        <div class="bg-white shadow-lg rounded-lg p-6">
            <h2 class="text-2xl font-bold mb-6">Device Mode Control</h2>
            
            <div class="text-center mb-6">
                <div class="text-lg mb-2">Current Mode:</div>
                <span class="px-4 py-2 rounded-full <?= $mode === 'register' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800' ?>">
                    <?= ucfirst($mode) ?>
                </span>
            </div>

            <form method="POST" class="text-center">
                <button type="submit" 
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg">
                    Switch to <?= ucfirst($mode === 'scan' ? 'register' : 'scan') ?> Mode
                </button>
            </form>
            
            <div class="mt-6 text-sm text-gray-600">
                <strong>Mode Descriptions:</strong>
                <ul class="mt-2 space-y-2">
                    <li><strong>Scan:</strong> Normal operation - validates RFID cards against database</li>
                    <li><strong>Register:</strong> Allows new RFID cards to be added to the system</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
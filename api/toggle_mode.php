<?php
// api/toggle_mode.php
ob_start();

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth_functions.php';

ob_clean();
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    requireAdmin();
    
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['mode']) || !in_array($data['mode'], ['scan', 'register'])) {
        throw new Exception('Invalid mode specified');
    }

    $db = getDatabase();
    if (!$db) {
        throw new Exception('Database connection failed');
    }

    // Check current mode
    $currentMode = $db->single(
        "SELECT setting_value FROM system_settings WHERE setting_key = 'device_mode'"
    )['setting_value'] ?? 'scan';

    // If requesting same mode as current
    if ($currentMode === $data['mode']) {
        echo json_encode([
            'status' => 'success',
            'message' => "Device already in {$data['mode']} mode",
            'mode' => $data['mode'],
            'alreadyInMode' => true,
            'lcd_message' => $data['mode'] === 'register' 
                ? LCD_MESSAGES['READY_REGISTER']
                : LCD_MESSAGES['READY']
        ]);
        exit();
    }
    
    // Update device mode in settings
    $updated = $db->update(
        'system_settings',
        ['setting_value' => $data['mode']],
        "setting_key = 'device_mode'"
    );

    if (!$updated) {
        throw new Exception('Failed to update device mode');
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Device mode updated',
        'mode' => $data['mode'],
        'alreadyInMode' => false,
        'lcd_message' => $data['mode'] === 'register' 
            ? LCD_MESSAGES['READY_REGISTER']
            : LCD_MESSAGES['READY']
    ]);

} catch (Exception $e) {
    error_log("Toggle mode error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

exit();
<?php
// api/toggle_mode.php
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth_functions.php';

requireAdmin();
header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['mode']) || !in_array($data['mode'], ['scan', 'register'])) {
        throw new Exception('Invalid mode specified');
    }

    $db = getDatabase();
    
    // Update device mode in settings
    $updated = $db->update(
        'system_settings',
        ['setting_value' => $data['mode']],
        "setting_key = 'device_mode'"
    );

    if (!$updated) {
        throw new Exception('Failed to update device mode');
    }

    // Get LCD message based on mode
    $lcdMessage = $data['mode'] === 'register' 
        ? LCD_MESSAGES['READY_REGISTER']
        : LCD_MESSAGES['READY'];

    echo json_encode([
        'status' => 'success',
        'message' => 'Device mode updated',
        'mode' => $data['mode'],
        'lcd_message' => $lcdMessage
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
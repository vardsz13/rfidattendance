<?php
require_once '../includes/functions.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['mode']) || !in_array($data['mode'], ['scan', 'register'])) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid mode']));
}

try {
    $db->update('system_settings', 
        ['setting_value' => $data['mode']], 
        'setting_key = ?', 
        ['device_mode']
    );
    echo json_encode(['status' => 'success', 'mode' => $data['mode']]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
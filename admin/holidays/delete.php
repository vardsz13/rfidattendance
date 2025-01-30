<?php
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth_functions.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

requireAdmin();

$db = getDatabase();

// Get holiday ID from URL
$holidayId = $_GET['id'] ?? null;

// Basic validation
if (!$holidayId) {
    flashMessage('Invalid holiday deletion request', 'error');
    header('Location: index.php');
    exit();
}

// Get holiday data
$holiday = $db->single("SELECT * FROM holidays WHERE id = ?", [$holidayId]);
if (!$holiday) {
    flashMessage('Holiday not found', 'error');
    header('Location: index.php');
    exit();
}

try {
    $db->query("DELETE FROM holidays WHERE id = ?", [$holidayId]);
    flashMessage('Holiday deleted successfully');
} catch (Exception $e) {
    error_log("Error deleting holiday: " . $e->getMessage());
    flashMessage('Error deleting holiday', 'error');
}

header('Location: index.php');
exit();
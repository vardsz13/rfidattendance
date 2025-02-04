<?php
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth_functions.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure admin access
requireAdmin();

$db = getDatabase();
$userId = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Basic validation
if (!$userId) {
    flashMessage('Invalid user ID provided', 'error');
    header('Location: index.php');
    exit();
}

// Prevent deleting own account
if ($userId == $_SESSION['user_id']) {
    flashMessage('Cannot delete your own account', 'error');
    header('Location: index.php');
    exit();
}

try {
    // Begin transaction
    $db->connect()->beginTransaction();

    // First delete attendance logs
    $db->query(
        "DELETE FROM attendance_logs WHERE user_id = ?", 
        [$userId]
    );

    // Delete verification data
    $db->query(
        "DELETE FROM user_verification_data WHERE user_id = ?", 
        [$userId]
    );

    // Finally delete the user
    $result = $db->query(
        "DELETE FROM users WHERE id = ? AND role != 'admin'", 
        [$userId]
    );

    if ($result === false) {
        throw new Exception('Failed to delete user');
    }

    // Commit transaction
    $db->connect()->commit();

    flashMessage('User successfully deleted');

} catch (Exception $e) {
    // Rollback on error
    if ($db->connect()->inTransaction()) {
        $db->connect()->rollBack();
    }
    
    error_log("Error deleting user (ID: $userId): " . $e->getMessage());
    flashMessage('Error deleting user: ' . $e->getMessage(), 'error');
}

// Redirect back to user list
header('Location: index.php');
exit();
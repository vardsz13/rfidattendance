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

$db = getDatabase();

// Get user ID from URL
$userId = $_GET['id'] ?? null;

// Basic validation
if (!$userId || $userId == $_SESSION['user_id']) {
    flashMessage('Invalid user deletion request', 'error');
    header('Location: index.php');
    exit();
}

// Get user data
$user = $db->single("SELECT * FROM users WHERE id = ?", [$userId]);
if (!$user) {
    flashMessage('User not found', 'error');
    header('Location: index.php');
    exit();
}

try {
    // Begin transaction
    $db->connect()->beginTransaction();

    // First delete related records in verification_data
    $db->query("DELETE FROM verification_data WHERE user_id = ?", [$userId]);

    // Then delete attendance records
    $db->query("DELETE FROM attendance WHERE user_id = ?", [$userId]);

    // Finally delete the user
    $db->query("DELETE FROM users WHERE id = ?", [$userId]);

    // Commit transaction
    $db->connect()->commit();

    flashMessage('User and related data deleted successfully');
} catch (Exception $e) {
    // Rollback on error
    $db->connect()->rollBack();
    error_log("Error deleting user: " . $e->getMessage());
    flashMessage('Error deleting user', 'error');
}

header('Location: index.php');
exit();
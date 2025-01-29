<!--includes/auth_functions.php
<?php
require_once 'functions.php';

function loginUser($username, $password) {
    $db = getDatabase();
    
    try {
        $user = $db->single(
            "SELECT * FROM users WHERE username = ?", 
            [$username]
        );

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        return false;
    }
}

function logoutUser() {
    $_SESSION = array();
    session_destroy();
    
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-3600, '/');
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . AUTH_URL . '/login.php');
        exit();
    }
}

function requireAdmin() {
    // Start session if not started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // First check if logged in
    if (!isLoggedIn()) {
        error_log("requireAdmin: User not logged in");
        header('Location: ' . AUTH_URL . '/login.php');
        exit();
    }

    // Then check if admin
    if (!isAdmin()) {
        error_log("requireAdmin: User not admin. Role: " . ($_SESSION['role'] ?? 'no role'));
        header('Location: ' . USER_URL);
        exit();
    }
}

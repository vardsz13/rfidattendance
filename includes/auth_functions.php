<?php
require_once dirname(__DIR__) . '/config/constants.php';
require_once 'functions.php';

// Start session if not already started
function ensureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function loginUser($username, $password) {
    ensureSession();
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
    ensureSession();
    $_SESSION = array();
    session_destroy();
    
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-3600, '/');
    }
}

function isLoggedIn() {
    ensureSession();
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    ensureSession();
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin() {
    ensureSession();
    
    if (!isLoggedIn()) {
        // Save the requested URL for redirect after login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        
        // Redirect to login
        header('Location: ' . AUTH_URL . '/login.php');
        exit();
    }
}

function requireAdmin() {
    ensureSession();

    // First check if logged in
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . AUTH_URL . '/login.php');
        exit();
    }

    // Then check if admin
    if (!isAdmin()) {
        header('Location: ' . USER_URL);
        exit();
    }
}

function redirectAfterLogin() {
    ensureSession();
    
    if (isset($_SESSION['redirect_after_login'])) {
        $redirect = $_SESSION['redirect_after_login'];
        unset($_SESSION['redirect_after_login']);
        return $redirect;
    }
    
    return isAdmin() ? ADMIN_URL : USER_URL;
}
<?php
require_once dirname(__DIR__) . '/config/constants.php';
require_once 'functions.php';

function ensureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function loginUser($id_number, $password) {
    ensureSession();
    $db = getDatabase();
    
    try {
        $user = $db->single(
            "SELECT * FROM users WHERE id_number = ?", 
            [$id_number]
        );

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['id_number'] = $user['id_number'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['user_type'] = $user['user_type'];
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
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . AUTH_URL . '/login.php');
        exit();
    }
}

function requireAdmin() {
    ensureSession();
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . AUTH_URL . '/login.php');
        exit();
    }
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

function checkAuthStatus() {
    $response = [
        'isAuthenticated' => false,
        'isAdmin' => false,
        'user' => null
    ];

    if (isLoggedIn()) {
        $response['isAuthenticated'] = true;
        $response['isAdmin'] = isAdmin();
        $response['user'] = [
            'id' => $_SESSION['user_id'],
            'id_number' => $_SESSION['id_number'],
            'name' => $_SESSION['name'],
            'role' => $_SESSION['role'],
            'user_type' => $_SESSION['user_type']
        ];
    }
    return $response;
}
<?php
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/auth_functions.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json');

$response = [
    'isAuthenticated' => false,
    'isAdmin' => false,
    'user' => null
];

if (isLoggedIn()) {
    $response['isAuthenticated'] = true;
    $response['isAdmin'] = isAdmin();
    
    if (isset($_SESSION['user_id'])) {
        $response['user'] = [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'name' => $_SESSION['name'],
            'role' => $_SESSION['role']
        ];
    }
}

echo json_encode($response);
exit();
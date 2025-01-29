<?php
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/auth_functions.php';
require_once dirname(__DIR__) . '/includes/functions.php';

logoutUser();
flashMessage('You have been successfully logged out');
header('Location: ' . AUTH_URL . '/login.php');
exit();
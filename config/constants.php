<!-- config/constants.php -->
<?php
// Site Information
define('SITE_NAME', 'RFID Attendance System');
define('SITE_VERSION', '1.0.0');
define('TIMEZONE', 'Asia/Manila');
date_default_timezone_set(TIMEZONE);

// Path Constants
define('BASE_PATH', dirname(__DIR__));
define('CONFIG_PATH', BASE_PATH . '/config');
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('ASSETS_PATH', BASE_PATH . '/assets');

// URL Constants
define('BASE_URL', 'http://localhost/rfidattendance'); // Change this according to your setup
define('ADMIN_URL', BASE_URL . '/admin');
define('USER_URL', BASE_URL . '/user');
define('AUTH_URL', BASE_URL . '/auth');
define('ASSETS_URL', BASE_URL . '/assets');

// Database Constants
define('DB_HOST', 'localhost');
define('DB_NAME', 'rfidattendance');
define('DB_USER', 'root');        // Change according to your setup, as-is as default
define('DB_PASS', '');            // Change according to your setup, as-is as default

// Session Constants
define('SESSION_NAME', 'rfidattendance');
define('SESSION_LIFETIME', 3600);
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
session_set_cookie_params(SESSION_LIFETIME);

// Device Constants
define('VERIFICATION_TIMEOUT', 30);
define('LCD_MESSAGES', [
    'READY' => 'Please scan your ID',
    'RFID_SUCCESS' => 'RFID Verified',
    'RFID_FAILED' => 'Invalid RFID',
    'FINGER_WAIT' => 'Place finger',
    'FINGER_SUCCESS' => 'Access Granted',
    'FINGER_FAILED' => 'Invalid Finger',
    'ERROR' => 'System Error'
]);

// Buzzer Tones
define('BUZZER_TONES', [
    'SUCCESS' => 'success_tone',
    'ERROR' => 'error_tone',
    'WAIT' => 'wait_tone',
    'ALERT' => 'alert_tone'
]);
<?php 
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/auth_functions.php';
require_once dirname(__DIR__) . '/includes/functions.php';

ensureSession();

error_log("Starting login process");
error_log("SESSION: " . print_r($_SESSION, true));

if (isLoggedIn()) {
    error_log("User already logged in with role: " . $_SESSION['role']);
    $redirect = redirectAfterLogin();
    header('Location: ' . $redirect);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_number = $_POST['id_number'] ?? '';
    $password = $_POST['password'] ?? '';
    
    error_log("Login attempt - ID Number: $id_number");
    
    if (empty($id_number) || empty($password)) {
        flashMessage('Please fill in all fields', 'error');
    } else {
        if (loginUser($id_number, $password)) {
            error_log("Login successful - Role: " . $_SESSION['role']);
            $redirect = redirectAfterLogin();
            header('Location: ' . $redirect);
            exit();
        } else {
            error_log("Login failed");
            flashMessage('Invalid ID number or password', 'error');
        }
    }
}

$minimal = true;
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="min-h-screen flex items-center justify-center bg-gray-100 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                <?= SITE_NAME ?>
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Sign in to your account
            </p>
        </div>
        
        <form class="mt-8 space-y-6" method="POST" action="">
            <div class="rounded-md shadow-sm -space-y-px">
                <div>
                    <label for="id_number" class="sr-only">ID Number</label>
                    <input id="id_number" 
                           name="id_number" 
                           type="text" 
                           required
                           class="appearance-none rounded-none relative block w-full px-3 py-2 border
                                 border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md
                                 focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                           placeholder="ID Number">
                </div>
                <div>
                    <label for="password" class="sr-only">Password</label>
                    <input id="password" 
                           name="password" 
                           type="password" 
                           required
                           class="appearance-none rounded-none relative block w-full px-3 py-2 border
                                 border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md
                                 focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                           placeholder="Password">
                </div>
            </div>

            <div>
                <button type="submit"
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent
                               text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700
                               focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Sign in
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
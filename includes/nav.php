<nav class="bg-white shadow-lg">
    <div class="max-w-6xl mx-auto px-4">
        <div class="flex justify-between">
            <div class="flex space-x-7">
                <a href="<?= BASE_URL ?>" class="flex items-center py-4 px-2">
                    <span class="font-semibold text-gray-500 text-lg">
                        <?= SITE_NAME ?>
                    </span>
                </a>
            </div>
            
            <!-- Navigation Menu -->
            <div class="flex items-center space-x-3">
                <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
                    <?php if (isAdmin()): ?>
                        <a href="<?= ADMIN_URL ?>" class="py-2 px-2 font-medium text-gray-500 hover:text-gray-900">
                            Dashboard
                        </a>
                        <a href="<?= ADMIN_URL ?>/users" class="py-2 px-2 font-medium text-gray-500 hover:text-gray-900">
                            Users
                        </a>
                        <a href="<?= ADMIN_URL ?>/attendance" class="py-2 px-2 font-medium text-gray-500 hover:text-gray-900">
                            Attendance
                        </a>
                        <a href="<?= ADMIN_URL ?>/devices" class="py-2 px-2 font-medium text-gray-500 hover:text-gray-900">
                            Devices
                        </a>
                        <a href="<?= ADMIN_URL ?>/holidays" class="py-2 px-2 font-medium text-gray-500 hover:text-gray-900">
                            Holidays
                        </a>
                        <a href="<?= ADMIN_URL ?>/settings" class="py-2 px-2 font-medium text-gray-500 hover:text-gray-900">
                            Settings
                        </a>
                    <?php else: ?>
                        <a href="<?= USER_URL ?>" class="py-2 px-2 font-medium text-gray-500 hover:text-gray-900">
                            Dashboard
                        </a>
                        <a href="<?= USER_URL ?>/attendance/view.php" class="py-2 px-2 font-medium text-gray-500 hover:text-gray-900">
                            My Attendance
                        </a>
                        <a href="<?= USER_URL ?>/profile/view.php" class="py-2 px-2 font-medium text-gray-500 hover:text-gray-900">
                            Profile
                        </a>
                    <?php endif; ?>
                    <div class="border-l pl-3 ml-3">
                        <span class="text-gray-500 mr-2"><?= $_SESSION['name'] ?? '' ?></span>
                        <a href="<?= AUTH_URL ?>/logout.php" class="py-2 px-2 font-medium text-red-500 hover:text-red-900">
                            Logout
                            <i class="fas fa-sign-out-alt"></i>
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Public navigation items -->
                    <a href="<?= AUTH_URL ?>/login.php" class="py-2 px-4 font-medium text-blue-500 hover:text-blue-700 border border-blue-500 rounded">
                        Login
                        <i class="fas fa-sign-in-alt"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
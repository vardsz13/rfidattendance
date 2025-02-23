<?php
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth_functions.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

requireAdmin();

$db = getDatabase();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $isRecurring = isset($_POST['is_recurring']);

    // Validation
    if (empty($date) || empty($name)) {
        $error = 'Date and name are required';
    } else {
        // Check if holiday already exists on the same date
        $existingHoliday = $db->single(
            "SELECT id FROM holidays WHERE holiday_date = ?",
            [$date]
        );

        if ($existingHoliday) {
            $error = 'A holiday already exists on the selected date';
        } else {
            $holidayData = [
                'holiday_date' => $date,
                'name' => $name,
                'description' => $description,
                'is_recurring' => $isRecurring ? 1 : 0
            ];

            if ($db->insert('holidays', $holidayData)) {
                flashMessage('Holiday added successfully');
                header('Location: index.php');
                exit();
            } else {
                $error = 'Error adding holiday';
            }
        }
    }
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container mx-auto px-4">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">Add Holiday</h2>
        <a href="index.php" class="text-blue-500 hover:text-blue-700">
            Back to Holidays List
        </a>
    </div>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white shadow-md rounded-lg p-6">
        <form method="POST" action="">
            <div class="mb-4">
                <label for="date" class="block text-sm font-medium text-gray-700">Date</label>
                <input type="date" name="date" id="date" required 
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                       value="<?= isset($_POST['date']) ? htmlspecialchars($_POST['date']) : '' ?>">
            </div>

            <div class="mb-4">
                <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                <input type="text" name="name" id="name" required
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                       value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
            </div>

            <div class="mb-4">
                <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                <textarea name="description" id="description" rows="3"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"><?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>
            </div>

            <div class="mb-6">
                <div class="flex items-center">
                    <input type="checkbox" name="is_recurring" id="is_recurring" 
                           class="h-4 w-4 border-gray-300 rounded text-blue-600 focus:ring-blue-500"
                           <?= isset($_POST['is_recurring']) ? 'checked' : '' ?>>
                    <label for="is_recurring" class="ml-2 block text-sm text-gray-900">Recurring Holiday</label>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Add Holiday
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
<?php
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth_functions.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

requireAdmin();
$useDataTables = true;

$db = getDatabase();

// Get all holidays
$holidays = $db->all("SELECT * FROM holidays ORDER BY holiday_date");

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container mx-auto px-4">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">Manage Holidays</h2>
        <a href="add.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            Add Holiday
        </a>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <table id="holidaysTable" class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Recurring</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($holidays as $holiday): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap"><?= date('F j, Y', strtotime($holiday['holiday_date'])) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($holiday['name']) ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($holiday['description']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap"><?= $holiday['is_recurring'] ? 'Yes' : 'No' ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                        <a href="edit.php?id=<?= $holiday['id'] ?>" class="text-blue-600 hover:text-blue-900">Edit</a>
                        <a href="delete.php?id=<?= $holiday['id'] ?>"
                           onclick="return confirm('Are you sure you want to delete this holiday?')" 
                           class="text-red-600 hover:text-red-900">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#holidaysTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 10
    });
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
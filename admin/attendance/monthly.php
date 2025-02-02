<?php
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth_functions.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

requireAdmin();
$useDataTables = true;

$db = getDatabase();

// Get month and year from request or default to current
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Calculate date range
$startDate = sprintf('%04d-%02d-01', $year, $month);
$endDate = date('Y-m-t', strtotime($startDate));

// Get monthly summary for all users
$userSummaries = $db->all(
    "SELECT 
        u.id,
        u.name,
        u.username,
        COUNT(DISTINCT CASE WHEN al.log_type = 'in' THEN DATE(al.log_date) END) as days_present,
        COUNT(DISTINCT CASE WHEN al.status = 'on_time' THEN DATE(al.log_date) END) as days_on_time,
        COUNT(DISTINCT CASE WHEN al.status = 'late' THEN DATE(al.log_date) END) as days_late
     FROM users u
     LEFT JOIN rfid_assignments ra ON u.id = ra.user_id
     LEFT JOIN attendance_logs al ON ra.id = al.assignment_id
        AND MONTH(al.log_date) = ?
        AND YEAR(al.log_date) = ?
     WHERE u.role != 'admin'
     GROUP BY u.id, u.name, u.username
     ORDER BY u.name",
    [$month, $year]
);

// Get total working days (excluding holidays)
$workingDays = $db->single(
    "SELECT COUNT(DISTINCT date_list.date) as total_days
     FROM (
         SELECT DATE_ADD(?, INTERVAL numbers.n DAY) as date
         FROM (
             SELECT 0 as n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION
             SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION
             SELECT 10 UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION
             SELECT 15 UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION
             SELECT 20 UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24 UNION
             SELECT 25 UNION SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29 UNION
             SELECT 30
         ) as numbers
         WHERE DATE_ADD(?, INTERVAL numbers.n DAY) <= ?
     ) as date_list
     LEFT JOIN holidays h ON date_list.date = h.holiday_date
     WHERE DAYOFWEEK(date_list.date) NOT IN (1, 7)
     AND h.id IS NULL",
    [$startDate, $startDate, $endDate]
);

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container mx-auto px-4">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">Monthly Attendance Report</h2>
        <div class="space-x-2">
            <a href="index.php" class="text-blue-500 hover:text-blue-700">
                Back to Attendance
            </a>
        </div>
    </div>

    <!-- Month Selection -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <form method="GET" class="flex items-center space-x-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Month</label>
                <select name="month" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= $i === $month ? 'selected' : '' ?>>
                            <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Year</label>
                <select name="year" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <?php for ($i = 2023; $i <= date('Y'); $i++): ?>
                        <option value="<?= $i ?>" <?= $i === $year ? 'selected' : '' ?>>
                            <?= $i ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    View Report
                </button>
            </div>
        </form>
    </div>

    <!-- Monthly Summary -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="text-center">
            <h3 class="text-lg font-semibold text-gray-700">
                <?= date('F Y', strtotime($startDate)) ?> Summary
            </h3>
            <p class="text-sm text-gray-600 mt-1">
                Total Working Days: <?= number_format($workingDays['total_days']) ?>
            </p>
        </div>
    </div>

    <!-- Monthly Report Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-6">
            <table id="monthlyReportTable" class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th>Name</th>
                        <th>Days Present</th>
                        <th>Days On Time</th>
                        <th>Days Late</th>
                        <th>Days Absent</th>
                        <th>Attendance Rate</th>
                        <th>Punctuality Rate</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($userSummaries as $summary): 
                        $daysPresent = (int)$summary['days_present'];
                        $daysOnTime = (int)$summary['days_on_time'];
                        $daysLate = (int)$summary['days_late'];
                        $totalWorkingDays = (int)$workingDays['total_days'];
                        $daysAbsent = $totalWorkingDays - $daysPresent;
                        
                        $attendanceRate = $totalWorkingDays > 0 ? 
                            ($daysPresent / $totalWorkingDays) * 100 : 0;
                            
                        $punctualityRate = $daysPresent > 0 ? 
                            ($daysOnTime / $daysPresent) * 100 : 0;
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($summary['name']) ?></td>
                            <td><?= $daysPresent ?></td>
                            <td><?= $daysOnTime ?></td>
                            <td><?= $daysLate ?></td>
                            <td><?= $daysAbsent ?></td>
                            <td>
                                <div class="flex items-center">
                                    <div class="w-16">
                                        <?= number_format($attendanceRate, 1) ?>%
                                    </div>
                                    <div class="flex-1 h-2 ml-2 bg-gray-200 rounded-full">
                                        <div class="h-2 bg-blue-500 rounded-full" 
                                             style="width: <?= $attendanceRate ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="flex items-center">
                                    <div class="w-16">
                                        <?= number_format($punctualityRate, 1) ?>%
                                    </div>
                                    <div class="flex-1 h-2 ml-2 bg-gray-200 rounded-full">
                                        <div class="h-2 bg-green-500 rounded-full" 
                                             style="width: <?= $punctualityRate ?>%"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#monthlyReportTable').DataTable({
        order: [[5, 'desc']],  // Sort by attendance rate by default
        pageLength: 50,
        responsive: true
    });
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
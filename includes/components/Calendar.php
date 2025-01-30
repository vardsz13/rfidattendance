<?php
class Calendar {
    private $db;
    private $year;
    private $month;
    private $isAdmin;
    private $userId;
    private $isAuth;

    public function __construct($db, $year = null, $month = null, $isAdmin = false, $userId = null) {
        $this->db = $db;
        $this->year = $year ?? date('Y');
        $this->month = $month ?? date('m');
        $this->isAdmin = $isAdmin;
        $this->userId = $userId;
        $this->isAuth = !is_null($userId);
    }

    private function getDaysInMonth() {
        return date('t', strtotime("{$this->year}-{$this->month}-01"));
    }

    private function getFirstDayOfMonth() {
        return date('w', strtotime("{$this->year}-{$this->month}-01"));
    }

    private function getMonthName() {
        return date('F Y', strtotime("{$this->year}-{$this->month}-01"));
    }

    private function getHolidays() {
        $holidays = $this->db->all(
            "SELECT 
                CASE 
                    WHEN is_recurring = 1 
                    THEN DATE(CONCAT(?, DATE_FORMAT(holiday_date, '-%m-%d')))
                    ELSE holiday_date 
                END as date,
                name as title,
                description
             FROM holidays 
             WHERE 
                (is_recurring = 1) OR 
                (is_recurring = 0 AND YEAR(holiday_date) = ?)",
            [$this->year, $this->year]
        );

        $holidayMap = [];
        foreach ($holidays as $holiday) {
            $holidayMap[$holiday['date']] = $holiday;
        }
        return $holidayMap;
    }

    private function getAttendanceData() {
        $startDate = "{$this->year}-{$this->month}-01";
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $query = "SELECT 
                    DATE(log_time) as date,
                    COUNT(DISTINCT user_id) as total_users,
                    COUNT(DISTINCT CASE WHEN TIME(log_time) <= '09:00:00' AND log_type = 'in' THEN user_id END) as on_time,
                    COUNT(DISTINCT CASE WHEN TIME(log_time) > '09:00:00' AND log_type = 'in' THEN user_id END) as late
                 FROM attendance_logs ";

        if ($this->isAuth && !$this->isAdmin) {
            $query .= "WHERE user_id = ? ";
            $params = [$this->userId];
        } else {
            $params = [];
        }

        $query .= "AND DATE(log_time) BETWEEN ? AND ? 
                  GROUP BY DATE(log_time)";
        
        array_push($params, $startDate, $endDate);
        
        $attendance = $this->db->all($query, $params);
        
        $attendanceMap = [];
        foreach ($attendance as $record) {
            $attendanceMap[$record['date']] = $record;
        }
        return $attendanceMap;
    }

    public function getStatsCards() {
        if (!$this->isAdmin) return '';

        $today = date('Y-m-d');
        $stats = $this->db->single(
            "SELECT 
                (SELECT COUNT(*) FROM users WHERE role = 'user') as total_users,
                COUNT(DISTINCT CASE WHEN DATE(log_time) = ? AND log_type = 'in' THEN user_id END) as present_today,
                COUNT(DISTINCT CASE WHEN TIME(log_time) <= '09:00:00' AND DATE(log_time) = ? AND log_type = 'in' THEN user_id END) as on_time_today
             FROM attendance_logs",
            [$today, $today]
        );

        $absent = $stats['total_users'] - $stats['present_today'];

        return <<<HTML
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-700">Total Users</h3>
                <p class="text-3xl font-bold text-blue-600">{$stats['total_users']}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-700">Present Today</h3>
                <p class="text-3xl font-bold text-green-600">{$stats['present_today']}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-700">On Time Today</h3>
                <p class="text-3xl font-bold text-emerald-600">{$stats['on_time_today']}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-700">Absent Today</h3>
                <p class="text-3xl font-bold text-red-600">$absent</p>
            </div>
        </div>
        HTML;
    }

    public function render() {
        $holidays = $this->getHolidays();
        $attendance = $this->getAttendanceData();
        $daysInMonth = $this->getDaysInMonth();
        $firstDay = $this->getFirstDayOfMonth();
        $monthName = $this->getMonthName();

        $html = '<div class="bg-white rounded-lg shadow p-6">';
        
        // Calendar Header
        $html .= <<<HTML
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">$monthName</h2>
            <div class="space-x-2">
                <button onclick="changeMonth(-1)" class="px-3 py-1 border rounded hover:bg-gray-100">←</button>
                <button onclick="changeMonth(1)" class="px-3 py-1 border rounded hover:bg-gray-100">→</button>
            </div>
        </div>
        HTML;

        // Calendar Grid
        $html .= '<div class="grid grid-cols-7 gap-px">';

        // Weekday headers
        $weekDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        foreach ($weekDays as $day) {
            $html .= "<div class='font-semibold text-center py-2'>$day</div>";
        }

        // Empty cells before start of month
        for ($i = 0; $i < $firstDay; $i++) {
            $html .= '<div class="h-24 border border-gray-200"></div>';
        }

        // Days of the month
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%s-%02d-%02d', $this->year, $this->month, $day);
            $isHoliday = isset($holidays[$date]);
            $hasAttendance = isset($attendance[$date]);

            $cellClass = 'h-24 border border-gray-200 p-2 cursor-pointer hover:bg-gray-50 relative';
            if ($isHoliday) {
                $cellClass .= ' bg-blue-50';
            }

            $html .= "<div class='$cellClass' onclick='showDayDetails(\"$date\")'>";
            $html .= "<div class='font-semibold'>$day</div>";

            if ($isHoliday) {
                $html .= sprintf(
                    '<div class="text-xs text-blue-600 mt-1">%s</div>', 
                    htmlspecialchars($holidays[$date]['title'])
                );
            }

            if ($hasAttendance) {
                $record = $attendance[$date];
                $total = $record['total_users'];
                $attendanceClass = $record['on_time'] > ($total / 2) 
                    ? 'bg-green-100 text-green-800' 
                    : 'bg-yellow-100 text-yellow-800';
                
                $html .= sprintf(
                    '<div class="mt-1 text-xs px-2 py-1 rounded-full %s">%d Present</div>',
                    $attendanceClass,
                    $total
                );
            }

            $html .= '</div>';
        }

        $html .= '</div>'; // End grid

        // Modal template
        $html .= $this->getModalTemplate();

        $html .= '</div>'; // End calendar container

        return $html;
    }

    private function getModalTemplate() {
        return <<<HTML
        <div id="dayDetailsModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <h3 id="modalDate" class="text-lg font-medium leading-6 text-gray-900 mb-4"></h3>
                    <div id="modalContent" class="space-y-4"></div>
                    <div class="mt-4">
                        <button onclick="closeModal()" 
                                class="w-full bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
        HTML;
    }
}
<?php
class Calendar {
    private $db;
    private $year;
    private $month;
    private $isAdmin;
    private $userId;
    private $isAuth;
    private $userCreatedAt;

    public function __construct($db, $year = null, $month = null, $isAdmin = false, $userId = null) {
        $this->db = $db;
        $this->year = $year ?? date('Y');
        $this->month = $month ?? date('m');
        $this->isAdmin = $isAdmin;
        $this->userId = $userId;
        $this->isAuth = !is_null($userId);
        
        if ($this->userId) {
            $user = $db->single(
                "SELECT DATE(created_at) as created_date FROM users WHERE id = ?", 
                [$this->userId]
            );
            $this->userCreatedAt = $user ? $user['created_date'] : null;
        }
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
                (is_recurring = 1 AND MONTH(DATE(CONCAT(?, DATE_FORMAT(holiday_date, '-%m-%d')))) = ?) OR 
                (is_recurring = 0 AND YEAR(holiday_date) = ? AND MONTH(holiday_date) = ?)",
            [$this->year, $this->year, $this->month, $this->year, $this->month]
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

        // Get total users excluding admins
        $totalUsersQuery = "SELECT COUNT(*) as count FROM users WHERE role != 'admin'";
        if ($this->isAuth && !$this->isAdmin) {
            $totalUsersQuery .= " AND id = ?";
            $totalUsers = $this->db->single($totalUsersQuery, [$this->userId])['count'];
        } else {
            $totalUsers = $this->db->single($totalUsersQuery)['count'];
        }

        // Get attendance records for each day
        $query = "SELECT 
                    DATE(log_time) as date,
                    COUNT(DISTINCT CASE WHEN log_type = 'in' THEN user_id END) as total_present,
                    COUNT(DISTINCT CASE 
                        WHEN log_type = 'in' AND status = 'on_time' 
                        THEN user_id 
                    END) as on_time,
                    COUNT(DISTINCT CASE 
                        WHEN log_type = 'in' AND status = 'late' 
                        THEN user_id 
                    END) as late,
                    COUNT(DISTINCT user_id) as total_attendance
                 FROM attendance_logs al
                 JOIN rfid_assignments ra ON al.assignment_id = ra.id";

        $params = [];
        if ($this->isAuth && !$this->isAdmin) {
            $query .= " WHERE ra.user_id = ?";
            $params[] = $this->userId;
        }

        $query .= " AND DATE(log_time) BETWEEN ? AND ?
                   GROUP BY DATE(log_time)";
        
        array_push($params, $startDate, $endDate);
        
        $attendance = $this->db->all($query, $params);
        
        // Process attendance data
        $attendanceMap = [];
        $currentDate = strtotime($startDate);
        $endTimestamp = strtotime($endDate);
        
        while ($currentDate <= $endTimestamp) {
            $dateStr = date('Y-m-d', $currentDate);
            $found = false;
            
            foreach ($attendance as $record) {
                if ($record['date'] === $dateStr) {
                    $attendanceMap[$dateStr] = array_merge($record, [
                        'total_active_users' => $totalUsers
                    ]);
                    $found = true;
                    break;
                }
            }
            
            if (!$found && $dateStr <= date('Y-m-d')) {
                $attendanceMap[$dateStr] = [
                    'date' => $dateStr,
                    'total_present' => 0,
                    'on_time' => 0,
                    'late' => 0,
                    'total_attendance' => 0,
                    'total_active_users' => $totalUsers
                ];
            }
            
            $currentDate = strtotime('+1 day', $currentDate);
        }
        
        return ['map' => $attendanceMap, 'total_users' => $totalUsers];
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

    public function getStatsCards() {
        if (!$this->isAdmin) return '';

        $today = date('Y-m-d');
        $attendanceData = $this->getAttendanceData();
        $todayData = $attendanceData['map'][$today] ?? null;

        if (!$todayData) {
            $todayData = [
                'total_present' => 0,
                'on_time' => 0,
                'late' => 0,
                'total_active_users' => $attendanceData['total_users']
            ];
        }

        $html = '<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">';

        // Total Users Card
        $html .= '
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-700">Total Users</h3>
                <div class="mt-2">
                    <p class="text-3xl font-bold text-blue-600">
                        ' . number_format($todayData['total_active_users']) . '
                    </p>
                    <p class="text-sm text-gray-600">Registered Employees</p>
                </div>
            </div>';

        // Present Today Card
        $html .= '
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-700">Present Today</h3>
                <div class="mt-2">
                    <p class="text-3xl font-bold text-green-600">
                        ' . number_format($todayData['total_present']) . '
                    </p>
                    <p class="text-sm text-gray-600">Checked In Today</p>
                </div>
            </div>';

        // On Time Card
        $html .= '
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-700">On Time Today</h3>
                <div class="mt-2">
                    <p class="text-3xl font-bold text-emerald-600">
                        ' . number_format($todayData['on_time']) . '
                    </p>
                    <p class="text-sm text-gray-600">Arrived On Time</p>
                </div>
            </div>';

        // Late Card
        $html .= '
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-700">Late Today</h3>
                <div class="mt-2">
                    <p class="text-3xl font-bold text-red-600">
                        ' . number_format($todayData['late']) . '
                    </p>
                    <p class="text-sm text-gray-600">Arrived Late</p>
                </div>
            </div>';

        $html .= '</div>';

        return $html;
    }

    public function render() {
        $holidays = $this->getHolidays();
        $attendanceData = $this->getAttendanceData();
        $attendance = $attendanceData['map'];
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
        $today = date('Y-m-d');
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%s-%02d-%02d', $this->year, $this->month, $day);
            $isHoliday = isset($holidays[$date]);
            $hasAttendance = isset($attendance[$date]) && 
                            ($attendance[$date]['total_present'] > 0);
            $isToday = $date === $today;
            $isFutureDate = $date > $today;
            $isBeforeCreation = $this->userCreatedAt && $date < $this->userCreatedAt;

            $cellClass = 'h-24 border border-gray-200 p-2 cursor-pointer hover:bg-gray-50 relative';
            if ($isHoliday) {
                $cellClass .= ' bg-blue-50';
            }
            if ($isToday) {
                $cellClass .= ' ring-2 ring-blue-500 ring-inset';
            }
            if ($isBeforeCreation) {
                $cellClass .= ' bg-gray-50';
            }

            $html .= "<div class='$cellClass' onclick='showDayDetails(\"$date\")'>";
            
            // Day number
            $dayClass = $isToday ? 'font-semibold text-blue-600' : 'font-semibold';
            $html .= "<div class='$dayClass'>$day</div>";

            // Show only holiday name or attendance indicator
            if ($isHoliday) {
                $html .= sprintf(
                    '<div class="text-xs text-blue-600 mt-1">%s</div>', 
                    htmlspecialchars($holidays[$date]['title'])
                );
            } elseif (!$isFutureDate && !$isBeforeCreation) {
                $statusDot = $hasAttendance ? 'text-green-600' : 'text-red-600';
                $html .= "<div class='mt-1 text-xs {$statusDot}'>•</div>";
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
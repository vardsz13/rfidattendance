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

    private function getAttendanceData() {
        $startDate = "{$this->year}-{$this->month}-01";
        $endDate = date('Y-m-t', strtotime($startDate));

        $totalStudentsQuery = "SELECT COUNT(*) as count FROM users WHERE role = 'student'";
        if ($this->isAuth && !$this->isAdmin) {
            $totalStudentsQuery .= " AND id = ?";
            $totalStudents = $this->db->single($totalStudentsQuery, [$this->userId])['count'];
        } else {
            $totalStudents = $this->db->single($totalStudentsQuery)['count'];
        }

        $query = "SELECT 
                    DATE(time_in) as date,
                    COUNT(DISTINCT user_id) as total_present,
                    COUNT(DISTINCT CASE WHEN status = 'on_time' THEN user_id END) as on_time,
                    COUNT(DISTINCT CASE WHEN status = 'late' THEN user_id END) as late,
                    COUNT(DISTINCT CASE WHEN override_status = 'excused' THEN user_id END) as excused,
                    COUNT(DISTINCT CASE WHEN override_status = 'event' THEN user_id END) as event,
                    COUNT(DISTINCT CASE WHEN override_status = 'medical' THEN user_id END) as medical,
                    COUNT(DISTINCT CASE WHEN status = 'absent' THEN user_id END) as absent
                FROM attendance_logs al
                JOIN user_verification_data uvd ON al.verification_id = uvd.id";

        $params = [];
        if ($this->isAuth && !$this->isAdmin) {
            $query .= " WHERE uvd.user_id = ?";
            $params[] = $this->userId;
        }

        $query .= " AND DATE(time_in) BETWEEN ? AND ?
                   GROUP BY DATE(time_in)";
        
        array_push($params, $startDate, $endDate);
        
        $attendance = $this->db->all($query, $params);
        
        $attendanceMap = [];
        $currentDate = strtotime($startDate);
        $endTimestamp = strtotime($endDate);
        
        while ($currentDate <= $endTimestamp) {
            $dateStr = date('Y-m-d', $currentDate);
            $found = false;
            
            foreach ($attendance as $record) {
                if ($record['date'] === $dateStr) {
                    $attendanceMap[$dateStr] = array_merge($record, [
                        'total_students' => $totalStudents
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
                    'excused' => 0,
                    'event' => 0,
                    'medical' => 0,
                    'absent' => $totalStudents,
                    'total_students' => $totalStudents
                ];
            }
            
            $currentDate = strtotime('+1 day', $currentDate);
        }
        
        return ['map' => $attendanceMap, 'total_students' => $totalStudents];
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
                'excused' => 0,
                'event' => 0,
                'medical' => 0,
                'absent' => $attendanceData['total_students'],
                'total_students' => $attendanceData['total_students']
            ];
        }

        $html = '<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">';
        
        // Main Stats
        $html .= $this->generateStatsCard('Total Students', $todayData['total_students'], 'Registered Students', 'blue');
        $html .= $this->generateStatsCard('Present Today', $todayData['total_present'], 'In Attendance', 'green');
        $html .= $this->generateStatsCard('Late Today', $todayData['late'], 'Arrived Late', 'yellow');
        $html .= $this->generateStatsCard('Absent Today', $todayData['absent'], 'Not Present', 'red');

        $html .= '</div>';

        // Override Stats
        $html .= '<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">';
        $html .= $this->generateStatsCard('Excused', $todayData['excused'], 'Excused Absences', 'purple');
        $html .= $this->generateStatsCard('Events', $todayData['event'], 'School Events', 'indigo');
        $html .= $this->generateStatsCard('Medical', $todayData['medical'], 'Medical Leave', 'pink');
        $html .= '</div>';

        return $html;
    }

    private function generateStatsCard($title, $value, $subtitle, $color) {
        return "
            <div class='bg-white rounded-lg shadow p-6'>
                <h3 class='text-lg font-semibold text-gray-700'>$title</h3>
                <div class='mt-2'>
                    <p class='text-3xl font-bold text-{$color}-600'>
                        " . number_format($value) . "
                    </p>
                    <p class='text-sm text-gray-600'>$subtitle</p>
                </div>
            </div>";
    }

    public function render() {
        $attendanceData = $this->getAttendanceData();
        $attendance = $attendanceData['map'];
        $daysInMonth = date('t', strtotime("{$this->year}-{$this->month}-01"));
        $firstDay = date('w', strtotime("{$this->year}-{$this->month}-01"));
        $monthName = date('F Y', strtotime("{$this->year}-{$this->month}-01"));

        $html = '<div class="bg-white rounded-lg shadow p-6">';
        
        $html .= "
        <div class='flex justify-between items-center mb-4'>
            <h2 class='text-xl font-bold'>$monthName</h2>
            <div class='space-x-2'>
                <button onclick='changeMonth(-1)' class='px-3 py-1 border rounded hover:bg-gray-100'>←</button>
                <button onclick='changeMonth(1)' class='px-3 py-1 border rounded hover:bg-gray-100'>→</button>
            </div>
        </div>";

        $html .= '<div class="grid grid-cols-7 gap-px">';

        foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day) {
            $html .= "<div class='font-semibold text-center py-2'>$day</div>";
        }

        for ($i = 0; $i < $firstDay; $i++) {
            $html .= '<div class="h-24 border border-gray-200"></div>';
        }

        $today = date('Y-m-d');
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%s-%02d-%02d', $this->year, $this->month, $day);
            $attendance_record = $attendance[$date] ?? null;
            $isToday = $date === $today;
            $isFutureDate = $date > $today;

            $cellClass = 'h-24 border border-gray-200 p-2 cursor-pointer hover:bg-gray-50 relative';
            if ($isToday) {
                $cellClass .= ' ring-2 ring-blue-500 ring-inset';
            }

            $html .= "<div class='$cellClass' onclick='showDayDetails(\"$date\")'>";
            
            $dayClass = $isToday ? 'font-semibold text-blue-600' : 'font-semibold';
            $html .= "<div class='$dayClass'>$day</div>";

            if (!$isFutureDate && $attendance_record) {
                $statusDot = $this->getStatusDot($attendance_record);
                $html .= "<div class='mt-1 text-xs {$statusDot}'>•</div>";
            }

            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= $this->getModalTemplate();
        $html .= '</div>';

        return $html;
    }

    private function getStatusDot($record) {
        if ($record['medical'] > 0) return 'text-pink-600';
        if ($record['event'] > 0) return 'text-indigo-600';
        if ($record['excused'] > 0) return 'text-purple-600';
        if ($record['total_present'] > 0) return 'text-green-600';
        return 'text-red-600';
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
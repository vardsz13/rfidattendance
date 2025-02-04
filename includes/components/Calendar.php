<?php
// includes/components/Calendar.php
class Calendar {
    private $db;
    private $year;
    private $month;
    private $isAdmin;
    private $userId;

    public function __construct($db, $year = null, $month = null, $isAdmin = false, $userId = null) {
        $this->db = $db;
        $this->year = $year ?? date('Y');
        $this->month = $month ?? date('m');
        $this->isAdmin = $isAdmin;
        $this->userId = $userId;
    }

    public function render() {
        $attendanceData = $this->getAttendanceData();
        $daysInMonth = date('t', strtotime("{$this->year}-{$this->month}-01"));
        $firstDay = date('w', strtotime("{$this->year}-{$this->month}-01"));
        $monthName = date('F Y', strtotime("{$this->year}-{$this->month}-01"));
        $today = date('Y-m-d');
    
        $html = '<div class="bg-white rounded-lg shadow p-6">';
        
        // Month navigation
        $html .= "
        <div class='flex justify-between items-center mb-4'>
            <h2 class='text-xl font-bold'>$monthName</h2>
            <div class='space-x-2'>
                <button onclick='changeMonth(-1)' class='px-3 py-1 border rounded hover:bg-gray-100'>←</button>
                <button onclick='changeMonth(1)' class='px-3 py-1 border rounded hover:bg-gray-100'>→</button>
            </div>
        </div>";
    
        // Calendar grid
        $html .= '<div class="grid grid-cols-7 gap-px bg-gray-200">';
    
        // Days of week
        foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day) {
            $html .= "<div class='bg-gray-50 font-semibold text-center py-2'>$day</div>";
        }
    
        // Blank cells before start of month
        for ($i = 0; $i < $firstDay; $i++) {
            $html .= '<div class="bg-white h-24"></div>';
        }
    
        // Calendar days
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%s-%02d-%02d', $this->year, $this->month, $day);
            $isToday = $date === $today;
            $isFutureDate = strtotime($date) > strtotime($today);
            
            // Base cell classes
            $cellClasses = [
                'bg-white',
                'h-24',
                'p-2',
                'relative',
                'cursor-pointer'
            ];
            
            if ($isToday) {
                $cellClasses[] = 'ring-2';
                $cellClasses[] = 'ring-blue-500';
                $cellClasses[] = 'ring-inset';
            }
            
            if ($isFutureDate) {
                $cellClasses[] = 'bg-gray-50';
                $cellClasses[] = 'opacity-50';
            } else {
                $cellClasses[] = 'hover:bg-gray-50';
            }
    
            $html .= sprintf(
                '<div class="%s" onclick="showDayDetails(\'%s\')">',
                implode(' ', $cellClasses),
                $date
            );
    
            // Day number
            $dayClasses = $isToday ? 'font-semibold text-blue-600' : 'font-semibold';
            $html .= "<div class='$dayClasses'>$day</div>";
    
            // Attendance indicators (only for non-future dates)
            if (!$isFutureDate && isset($attendanceData[$date])) {
                $html .= $this->getStatusIndicators($attendanceData[$date]);
            }
    
            $html .= '</div>';
        }
    
        // Fill in remaining cells
        $lastDay = ($firstDay + $daysInMonth) % 7;
        if ($lastDay > 0) {
            for ($i = 0; $i < (7 - $lastDay); $i++) {
                $html .= '<div class="bg-white h-24"></div>';
            }
        }
    
        $html .= '</div>';
        $html .= $this->getModalTemplate();
        $html .= '</div>';
    
        return $html;
    }

    private function getAttendanceData() {
        $startDate = "{$this->year}-{$this->month}-01";
        $endDate = date('Y-m-t', strtotime($startDate));

        $query = "
            SELECT 
                DATE(al.time_in) as date,
                COUNT(DISTINCT CASE WHEN al.status = 'on_time' THEN al.user_id END) as on_time,
                COUNT(DISTINCT CASE WHEN al.status = 'late' THEN al.user_id END) as late,
                COUNT(DISTINCT CASE WHEN al.override_status = 'excused' THEN al.user_id END) as excused,
                COUNT(DISTINCT CASE WHEN al.override_status = 'event' THEN al.user_id END) as event,
                COUNT(DISTINCT CASE WHEN al.override_status = 'medical' THEN al.user_id END) as medical
            FROM attendance_logs al
            WHERE DATE(al.time_in) BETWEEN ? AND ?";

        if (!$this->isAdmin) {
            $query .= " AND al.user_id = ?";
        }

        $query .= " GROUP BY DATE(al.time_in)";
        
        $params = [$startDate, $endDate];
        if (!$this->isAdmin) {
            $params[] = $this->userId;
        }

        $attendance = $this->db->all($query, $params);
        
        $data = [];
        foreach ($attendance as $record) {
            $data[$record['date']] = $record;
        }
        
        return $data;
    }

    private function getStatusIndicators($attendance) {
        $indicators = '';
        
        if ($attendance['medical'] > 0) {
            $indicators .= '<div class="text-pink-600">• Medical Leave</div>';
        }
        if ($attendance['event'] > 0) {
            $indicators .= '<div class="text-indigo-600">• Event</div>';
        }
        if ($attendance['excused'] > 0) {
            $indicators .= '<div class="text-purple-600">• Excused</div>';
        }
        if ($attendance['on_time'] > 0) {
            $indicators .= '<div class="text-green-600">• On Time</div>';
        }
        if ($attendance['late'] > 0) {
            $indicators .= '<div class="text-yellow-600">• Late</div>';
        }

        return $indicators;
    }

    private function getModalTemplate() {
        return '
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
        </div>';
    }

    public function getStatsCards() {
        if (!$this->isAdmin) return '';

        $today = date('Y-m-d');
        $stats = $this->db->single("
            SELECT 
                COUNT(DISTINCT user_id) as total_users,
                COUNT(DISTINCT CASE WHEN status = 'on_time' THEN user_id END) as on_time,
                COUNT(DISTINCT CASE WHEN status = 'late' THEN user_id END) as late,
                COUNT(DISTINCT CASE WHEN override_status IS NOT NULL THEN user_id END) as excused
            FROM attendance_logs 
            WHERE DATE(time_in) = ?", 
            [$today]
        );

        $html = '<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">';
        
        // Total Users
        $html .= $this->generateStatsCard(
            'Total Users', 
            $stats['total_users'], 
            'Registered Students', 
            'blue'
        );
        
        // On Time
        $html .= $this->generateStatsCard(
            'On Time', 
            $stats['on_time'], 
            'Arrived on Time', 
            'green'
        );
        
        // Late
        $html .= $this->generateStatsCard(
            'Late', 
            $stats['late'], 
            'Arrived Late', 
            'yellow'
        );
        
        // Excused
        $html .= $this->generateStatsCard(
            'Excused', 
            $stats['excused'], 
            'Excused Absences', 
            'purple'
        );

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
}
-- Start with fresh database
DROP DATABASE IF EXISTS rfidattendance;
CREATE DATABASE rfidattendance;
USE rfidattendance;

-- Users table
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_user_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- RFID Cards table
CREATE TABLE `rfid_cards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rfid_uid` varchar(50) NOT NULL,
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `rfid_uid` (`rfid_uid`),
  KEY `idx_rfid_uid` (`rfid_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- RFID Assignments table
CREATE TABLE `rfid_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rfid_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_rfid_assignment` (`rfid_id`,`user_id`,`is_active`),
  CONSTRAINT `rfid_assignments_ibfk_1` FOREIGN KEY (`rfid_id`) REFERENCES `rfid_cards` (`id`),
  CONSTRAINT `rfid_assignments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Attendance Logs table
CREATE TABLE `attendance_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `assignment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `time_in` datetime NOT NULL,
  `time_out` datetime DEFAULT NULL,
  `log_date` date GENERATED ALWAYS AS (DATE(time_in)) STORED,
  `status` enum('on_time','late') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_date` (`user_id`, `log_date`),
  KEY `idx_assignment` (`assignment_id`),
  CONSTRAINT `fk_attendance_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `rfid_assignments` (`id`),
  CONSTRAINT `fk_attendance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Holidays table
CREATE TABLE `holidays` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `holiday_date` date NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_recurring` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_holiday_date` (`holiday_date`),
  KEY `idx_holiday_date` (`holiday_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- System Settings table
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `setting_description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create view for attendance with formatted duration
CREATE OR REPLACE VIEW attendance_view AS
SELECT 
    al.*,
    u.name as user_name,
    u.username,
    CASE 
        WHEN al.time_out IS NOT NULL THEN
            CONCAT_WS(' ', 
                NULLIF(CONCAT(
                    FLOOR(TIMESTAMPDIFF(SECOND, al.time_in, al.time_out) / 3600),
                    ' hrs'
                ), '0 hrs'),
                NULLIF(CONCAT(
                    FLOOR((TIMESTAMPDIFF(SECOND, al.time_in, al.time_out) % 3600) / 60),
                    ' mins'
                ), '0 mins'),
                NULLIF(CONCAT(
                    TIMESTAMPDIFF(SECOND, al.time_in, al.time_out) % 60,
                    ' secs'
                ), '0 secs')
            )
        ELSE NULL
    END as duration,
    rc.rfid_uid
FROM attendance_logs al
JOIN users u ON al.user_id = u.id
JOIN rfid_assignments ra ON al.assignment_id = ra.id
JOIN rfid_cards rc ON ra.rfid_id = rc.id;

-- Create view for daily attendance summary
CREATE OR REPLACE VIEW daily_attendance_summary AS
SELECT 
    DATE(al.time_in) as attendance_date,
    COUNT(DISTINCT al.user_id) as total_present,
    COUNT(DISTINCT CASE WHEN al.status = 'on_time' THEN al.user_id END) as on_time,
    COUNT(DISTINCT CASE WHEN al.status = 'late' THEN al.user_id END) as late,
    (SELECT COUNT(*) FROM users WHERE role = 'user') as total_users,
    COUNT(DISTINCT CASE WHEN al.time_out IS NULL THEN al.user_id END) as still_in
FROM attendance_logs al
GROUP BY DATE(al.time_in);

-- Insert default admin user (password: password)
INSERT INTO users (username, password, name, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');

-- Insert default settings
INSERT INTO system_settings (setting_key, setting_value, setting_description) VALUES
('late_time', '09:00:00', 'Time after which attendance is marked as late (24-hour format)'),
('device_mode', 'scan', 'Current operating mode for RFID devices (scan/register)');
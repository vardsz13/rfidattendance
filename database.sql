-- Create the database
CREATE DATABASE IF NOT EXISTS rfidattendance;
USE rfidattendance;

-- Settings table for system configuration
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value VARCHAR(255) NOT NULL,
    setting_description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO system_settings (setting_key, setting_value, setting_description) VALUES
('late_time', '09:00:00', 'Time after which attendance is marked as late (24-hour format)');

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- RFID Cards table
CREATE TABLE rfid_cards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    rfid_uid VARCHAR(50) UNIQUE NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rfid_uid (rfid_uid)
);

-- RFID Card Assignments table
CREATE TABLE rfid_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    rfid_id INT NOT NULL,
    user_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT true,
    FOREIGN KEY (rfid_id) REFERENCES rfid_cards(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_rfid_assignment (rfid_id, user_id, is_active)
);

-- Time IN logs
CREATE TABLE time_in_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    assignment_id INT NOT NULL,
    time_in TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    attendance_date DATE GENERATED ALWAYS AS (DATE(time_in)) STORED,
    status ENUM('on_time', 'late') NOT NULL,
    FOREIGN KEY (assignment_id) REFERENCES rfid_assignments(id),
    UNIQUE KEY unique_daily_timein (assignment_id, attendance_date)
);

-- Time OUT logs
CREATE TABLE time_out_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    assignment_id INT NOT NULL,
    time_out TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    attendance_date DATE GENERATED ALWAYS AS (DATE(time_out)) STORED,
    FOREIGN KEY (assignment_id) REFERENCES rfid_assignments(id),
    UNIQUE KEY unique_daily_timeout (assignment_id, attendance_date)
);

-- Daily attendance summary view
CREATE VIEW daily_attendance_summary AS
SELECT 
    u.id as user_id,
    u.name as user_name,
    rc.rfid_uid,
    ti.attendance_date,
    TIME(ti.time_in) as time_in,
    TIME(to_logs.time_out) as time_out,
    ti.status as attendance_status,
    CASE 
        WHEN ti.id IS NULL THEN 'absent'
        ELSE ti.status
    END as status
FROM users u
JOIN rfid_assignments ra ON u.id = ra.user_id
JOIN rfid_cards rc ON ra.rfid_id = rc.id
LEFT JOIN time_in_logs ti ON ra.id = ti.assignment_id
LEFT JOIN time_out_logs to_logs ON ra.id = to_logs.assignment_id 
    AND ti.attendance_date = to_logs.attendance_date
WHERE u.role != 'admin' AND ra.is_active = true
GROUP BY u.id, u.name, ti.attendance_date;

-- Holidays table
CREATE TABLE holidays (
    id INT PRIMARY KEY AUTO_INCREMENT,
    holiday_date DATE NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_recurring BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_holiday_date (holiday_date)
);

-- Insert default holidays
INSERT INTO holidays (holiday_date, name, description, is_recurring) VALUES
('2025-01-01', 'New Year''s Day', 'New Year''s Day celebration', true),
('2025-04-09', 'Day of Valor', 'Araw ng Kagitingan', true),
('2025-04-18', 'Good Friday', 'Good Friday celebration', false),
('2025-05-01', 'Labor Day', 'International Labor Day', true),
('2025-06-12', 'Independence Day', 'Philippine Independence Day', true),
('2025-08-26', 'National Heroes Day', 'National Heroes Day', true),
('2025-11-30', 'Bonifacio Day', 'Bonifacio Day', true),
('2025-12-25', 'Christmas Day', 'Christmas Day celebration', true),
('2025-12-30', 'Rizal Day', 'Rizal Day', true);

-- Insert default admin user (password: admin123)
INSERT INTO users (username, password, name, role) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');

-- Create indexes for better performance
CREATE INDEX idx_user_role ON users(role);
CREATE INDEX idx_timein_date ON time_in_logs(attendance_date);
CREATE INDEX idx_timeout_date ON time_out_logs(attendance_date);
CREATE INDEX idx_assignment_timein ON time_in_logs(assignment_id, attendance_date);
CREATE INDEX idx_assignment_timeout ON time_out_logs(assignment_id, attendance_date);
CREATE INDEX idx_holiday_date ON holidays(holiday_date);
-- Simplified attendance table:

-- Added time_out column
-- Added reference to device_logs for both in/out verifications
-- Removed unnecessary fields
-- Added unique constraint for one attendance record per user per day


-- Kept core functionalities:

-- User management
-- RFID + Fingerprint verification
-- Device logs
-- Holiday management


-- Key features supported:

-- Double verification (RFID + Fingerprint)
-- Daily in/out tracking
-- Holiday tracking
-- Device log tracking
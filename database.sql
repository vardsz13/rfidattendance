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

-- Users table remains the same from original schema
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Verification data for RFID/Fingerprint
CREATE TABLE verification_data (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    rfid_uid VARCHAR(50) UNIQUE NOT NULL,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Device logs for all verification attempts
CREATE TABLE device_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    verification_type ENUM('rfid', 'fingerprint') NOT NULL,
    rfid_uid VARCHAR(50) NULL,
    verification_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    lcd_message VARCHAR(100) NOT NULL,
    buzzer_tone VARCHAR(20) NOT NULL,
    status ENUM('pending', 'success', 'failed') NOT NULL,
    INDEX idx_verification_time (verification_time),
    INDEX idx_rfid (rfid_uid)
);

-- Simplified attendance logs (IN only)
CREATE TABLE attendance_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    time_in TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    attendance_date DATE GENERATED ALWAYS AS (DATE(time_in)) STORED,
    status ENUM('on_time', 'late') NOT NULL,
    rfid_log_id INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (rfid_log_id) REFERENCES device_logs(id),
    UNIQUE KEY unique_daily_attendance (user_id, attendance_date)
);

-- Daily attendance summary view
CREATE VIEW daily_attendance_summary AS
SELECT 
    u.id as user_id,
    u.name as user_name,
    a.attendance_date,
    TIME(a.time_in) as time_in,
    a.status as attendance_status,
    CASE 
        WHEN a.id IS NULL THEN 'absent'
        ELSE a.status
    END as status
FROM users u
LEFT JOIN attendance_logs a ON u.id = a.user_id
WHERE u.role != 'admin'
GROUP BY u.id, u.name, a.attendance_date;

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

-- Insert default admin user
INSERT INTO users (username, password, name, role) 
VALUES ('admin', '$2b$10$Jp2WhmFTTmcbdwH4eK3GJudwAuFTTRyRv0RAuKBL8i5aiO0BRXRUC', 'Administrator', 'admin');

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
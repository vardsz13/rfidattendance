-- Create the database
CREATE DATABASE IF NOT EXISTS rfidattendance;
USE rfidattendance;

-- Users table remains the same
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Verification data remains the same
CREATE TABLE verification_data (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    rfid_uid VARCHAR(50) UNIQUE NOT NULL,
    fingerprint_id INT UNIQUE NOT NULL,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Device logs remains the same
CREATE TABLE device_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    verification_type ENUM('rfid', 'fingerprint') NOT NULL,
    rfid_uid VARCHAR(50) NULL,
    fingerprint_id INT NULL,
    verification_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    lcd_message VARCHAR(100) NOT NULL,
    buzzer_tone VARCHAR(20) NOT NULL,
    status ENUM('pending', 'success', 'failed') NOT NULL,
    INDEX idx_verification_time (verification_time),
    INDEX idx_rfid (rfid_uid),
    INDEX idx_fingerprint (fingerprint_id)
);

-- Modified attendance table for multiple in/out
CREATE TABLE attendance_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    log_type ENUM('in', 'out') NOT NULL,
    log_time TIMESTAMP NOT NULL,
    rfid_log_id INT NOT NULL,
    fingerprint_log_id INT NOT NULL,
    attendance_date DATE GENERATED ALWAYS AS (DATE(log_time)) STORED,
    sequence_number INT NOT NULL,  -- To track multiple in/out pairs
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (rfid_log_id) REFERENCES device_logs(id),
    FOREIGN KEY (fingerprint_log_id) REFERENCES device_logs(id),
    INDEX idx_user_date (user_id, attendance_date),
    INDEX idx_sequence (user_id, attendance_date, sequence_number)
);

CREATE TABLE holidays (
    id INT PRIMARY KEY AUTO_INCREMENT,
    holiday_date DATE NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_recurring BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_holiday_date (holiday_date)
);

-- Daily attendance summary with all in/out records
CREATE VIEW daily_attendance_logs AS
SELECT 
    a.user_id,
    u.name as user_name,
    a.attendance_date,
    a.sequence_number,
    MIN(CASE WHEN a.log_type = 'in' THEN TIME(a.log_time) END) as time_in,
    MIN(CASE WHEN a.log_type = 'out' THEN TIME(a.log_time) END) as time_out,
    CASE 
        WHEN MIN(CASE WHEN a.log_type = 'in' THEN TIME(a.log_time) END) <= '09:00:00' THEN 'present'
        WHEN MIN(CASE WHEN a.log_type = 'in' THEN TIME(a.log_time) END) > '09:00:00' THEN 'late'
        ELSE 'absent'
    END as status,
    TIMEDIFF(
        MIN(CASE WHEN a.log_type = 'out' THEN a.log_time END),
        MIN(CASE WHEN a.log_type = 'in' THEN a.log_time END)
    ) as duration
FROM attendance_logs a
JOIN users u ON a.user_id = u.id
GROUP BY a.user_id, u.name, a.attendance_date, a.sequence_number;

-- Daily summary showing first in and last out
CREATE VIEW daily_attendance_summary AS
SELECT 
    user_id,
    user_name,
    attendance_date,
    MIN(time_in) as first_time_in,
    MAX(time_out) as last_time_out,
    MIN(status) as attendance_status,
    SEC_TO_TIME(SUM(TIME_TO_SEC(duration))) as total_hours
FROM daily_attendance_logs
GROUP BY user_id, user_name, attendance_date;

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
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

-- Simplified attendance logs table (combined IN/OUT)
CREATE TABLE attendance_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    assignment_id INT NOT NULL,
    log_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    log_type ENUM('in', 'out') NOT NULL,
    status ENUM('on_time', 'late') NULL,
    attendance_date DATE GENERATED ALWAYS AS (DATE(log_time)) STORED,
    FOREIGN KEY (assignment_id) REFERENCES rfid_assignments(id),
    INDEX idx_assignment_date (assignment_id, attendance_date, log_type)
);

-- Device logs table
CREATE TABLE device_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    rfid_uid VARCHAR(50),
    log_type ENUM('in', 'out') NOT NULL,
    verification_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    buzzer_tone VARCHAR(50),
    status ENUM('success', 'failed', 'pending') DEFAULT 'pending',
    INDEX idx_verification_time (verification_time),
    INDEX idx_rfid_uid (rfid_uid)
);

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
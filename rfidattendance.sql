-- Create database if not exists
CREATE DATABASE IF NOT EXISTS rfidattendance;
USE rfidattendance;

-- System settings
CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value VARCHAR(255) NOT NULL,
    setting_description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_number VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    user_type ENUM('normal', 'special') NOT NULL DEFAULT 'normal',
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- RFID cards
CREATE TABLE IF NOT EXISTS rfid_cards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    rfid_uid VARCHAR(50) NOT NULL UNIQUE,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User verification data (combines RFID and fingerprint assignments)
CREATE TABLE IF NOT EXISTS user_verification_data (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    rfid_id INT NOT NULL,
    fingerprint_data VARCHAR(255),
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (rfid_id) REFERENCES rfid_cards(id),
    UNIQUE KEY unique_active_user (user_id, is_active)
);

-- Attendance logs
CREATE TABLE IF NOT EXISTS attendance_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    verification_id INT NOT NULL,
    user_id INT NOT NULL,
    time_in DATETIME NOT NULL,
    verification_type ENUM('rfid_only', 'dual') NOT NULL,
    status ENUM('on_time', 'late') NOT NULL,
    device_id VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (verification_id) REFERENCES user_verification_data(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Device settings
CREATE TABLE IF NOT EXISTS device_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    device_id VARCHAR(50) NOT NULL UNIQUE,
    device_name VARCHAR(100),
    device_type ENUM('arduino', 'esp8266') NOT NULL,
    last_seen TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Holidays
CREATE TABLE IF NOT EXISTS holidays (
    id INT PRIMARY KEY AUTO_INCREMENT,
    holiday_date DATE NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_recurring BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_holiday_date (holiday_date)
);

-- Initial system settings
INSERT INTO system_settings (setting_key, setting_value, setting_description) VALUES
('late_time', '09:00:00', 'Time after which attendance is marked as late (24-hour format)'),
('device_mode', 'scan', 'Current operating mode for RFID/fingerprint devices (scan/register)'),
('verification_timeout', '30', 'Seconds allowed to complete dual verification');

-- Initial admin user
INSERT INTO users (id_number, password, name, role) VALUES
('ADMIN', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');

ALTER TABLE attendance_logs 
DROP COLUMN status,
ADD COLUMN status ENUM('on_time', 'late', 'absent') DEFAULT 'absent',
ADD COLUMN override_status ENUM('vacation', 'half_day', 'excused') DEFAULT NULL,
ADD COLUMN override_remarks VARCHAR(255) DEFAULT NULL;


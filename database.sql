-- Create the database
CREATE DATABASE IF NOT EXISTS rfidattendance;
USE rfidattendance;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Student/Employee verification data
CREATE TABLE verification_data (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    rfid_uid VARCHAR(50) UNIQUE NOT NULL,
    fingerprint_id INT UNIQUE NOT NULL,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Raw Arduino device logs (direct from device)
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

-- Final attendance records (after both verifications succeed)
CREATE TABLE attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    rfid_log_id INT NOT NULL,
    fingerprint_log_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    time_in TIMESTAMP NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (rfid_log_id) REFERENCES device_logs(id),
    FOREIGN KEY (fingerprint_log_id) REFERENCES device_logs(id),
    UNIQUE KEY unique_daily_attendance (user_id, attendance_date)
);

-- Insert default admin user
INSERT INTO users (username, password, name, role) 
VALUES ('admin', '$2b$10$Jp2WhmFTTmcbdwH4eK3GJudwAuFTTRyRv0RAuKBL8i5aiO0BRXRUC', 'Administrator', 'admin');
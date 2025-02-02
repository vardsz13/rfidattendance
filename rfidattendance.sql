-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 02, 2025 at 07:37 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rfidattendance`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance_logs`
--

CREATE TABLE `attendance_logs` (
  `id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `time_in` datetime DEFAULT NULL,
  `time_out` datetime DEFAULT NULL,
  `duration_seconds` int(11) DEFAULT NULL,
  `log_date` date GENERATED ALWAYS AS (cast(`time_in` as date)) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `status` enum('on_time','late','absent') DEFAULT 'absent',
  `override_status` enum('vacation','half_day','excused') DEFAULT NULL,
  `override_remarks` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance_logs`
--

INSERT INTO `attendance_logs` (`id`, `assignment_id`, `user_id`, `time_in`, `time_out`, `duration_seconds`, `created_at`, `updated_by`, `updated_at`, `status`, `override_status`, `override_remarks`) VALUES
(1, 1, 1, '2025-02-02 23:40:13', '2025-02-02 23:41:00', 47, '2025-02-02 07:40:13', NULL, NULL, 'absent', NULL, NULL),
(2, 1, 2, '2025-02-02 23:43:51', '2025-02-02 23:44:05', 14, '2025-02-02 07:43:51', NULL, NULL, 'absent', NULL, NULL),
(3, 1, 3, '2025-02-02 23:46:50', '2025-02-02 23:47:18', 28, '2025-02-02 07:46:50', NULL, NULL, 'absent', NULL, NULL),
(4, 1, 4, '2025-02-02 23:47:29', '2025-02-02 23:55:50', 501, '2025-02-02 07:47:29', NULL, NULL, 'absent', NULL, NULL),
(5, 1, 3, '2025-02-02 23:57:05', '2025-02-02 23:57:13', 8, '2025-02-02 07:57:05', NULL, NULL, 'absent', NULL, NULL),
(6, 1, 4, '2025-02-02 23:57:24', '2025-02-02 23:57:30', 6, '2025-02-02 07:57:24', NULL, NULL, 'absent', NULL, NULL),
(7, 1, 4, '2025-02-02 23:57:43', '2025-02-02 23:57:47', 4, '2025-02-02 07:57:43', NULL, NULL, 'absent', NULL, NULL),
(8, 1, 2, '2025-02-02 23:58:05', '2025-02-02 23:58:47', 42, '2025-02-02 07:58:05', NULL, NULL, 'absent', NULL, NULL),
(9, 1, 3, '2025-02-02 23:59:35', '2025-02-02 23:59:43', 8, '2025-02-02 07:59:35', NULL, NULL, 'absent', NULL, NULL),
(10, 1, 4, '2025-02-03 00:11:45', '2025-02-03 00:11:51', 6, '2025-02-02 08:11:45', NULL, '2025-02-02 18:11:44', 'absent', NULL, NULL),
(11, 1, 4, '2025-02-03 00:22:14', '2025-02-03 00:22:27', 13, '2025-02-02 08:22:14', NULL, NULL, 'absent', NULL, NULL),
(12, 1, 3, '2025-02-03 00:28:24', '2025-02-03 00:28:29', 5, '2025-02-02 08:28:24', NULL, NULL, 'absent', NULL, NULL),
(13, 1, 4, '2025-02-03 00:32:11', '2025-02-03 00:32:14', 3, '2025-02-02 08:32:11', NULL, NULL, 'absent', NULL, NULL),
(14, 2, 3, '2025-02-03 00:00:00', NULL, NULL, '2025-02-02 18:10:32', NULL, '2025-02-02 18:10:38', 'absent', NULL, NULL),
(15, 4, 5, '2025-02-03 00:00:00', NULL, NULL, '2025-02-02 18:10:46', NULL, NULL, 'absent', NULL, NULL),
(16, 3, 4, '2025-02-03 00:00:00', NULL, NULL, '2025-02-02 18:11:04', NULL, '2025-02-02 18:11:28', 'absent', NULL, NULL);

--
-- Triggers `attendance_logs`
--
DELIMITER $$
CREATE TRIGGER `calculate_duration` BEFORE UPDATE ON `attendance_logs` FOR EACH ROW BEGIN
    IF NEW.time_out IS NOT NULL AND OLD.time_out IS NULL THEN
        SET NEW.duration_seconds = TIMESTAMPDIFF(SECOND, NEW.time_in, NEW.time_out);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `attendance_view`
-- (See below for the actual view)
--
CREATE TABLE `attendance_view` (
`id` int(11)
,`assignment_id` int(11)
,`user_id` int(11)
,`time_in` datetime
,`time_out` datetime
,`duration_seconds` int(11)
,`log_date` date
,`created_at` timestamp
,`updated_by` int(11)
,`updated_at` timestamp
,`status` enum('on_time','late','absent')
,`override_status` enum('vacation','half_day','excused')
,`override_remarks` varchar(255)
,`user_name` varchar(100)
,`username` varchar(50)
,`effective_status` varchar(8)
,`duration` varchar(51)
,`rfid_uid` varchar(50)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `daily_attendance_summary`
-- (See below for the actual view)
--
CREATE TABLE `daily_attendance_summary` (
`attendance_date` date
,`total_present` bigint(21)
,`on_time` bigint(21)
,`late` bigint(21)
,`excused` bigint(21)
,`half_day` bigint(21)
,`vacation` bigint(21)
,`absent` bigint(21)
,`total_users` bigint(21)
,`still_in` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `id` int(11) NOT NULL,
  `holiday_date` date NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_recurring` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rfid_assignments`
--

CREATE TABLE `rfid_assignments` (
  `id` int(11) NOT NULL,
  `rfid_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rfid_assignments`
--

INSERT INTO `rfid_assignments` (`id`, `rfid_id`, `user_id`, `assigned_at`, `is_active`) VALUES
(1, 1, 2, '2025-02-02 15:39:53', 1),
(2, 4, 3, '2025-02-02 18:10:15', 1),
(3, 2, 4, '2025-02-02 18:10:19', 1),
(4, 3, 5, '2025-02-02 18:10:24', 1);

-- --------------------------------------------------------

--
-- Table structure for table `rfid_cards`
--

CREATE TABLE `rfid_cards` (
  `id` int(11) NOT NULL,
  `rfid_uid` varchar(50) NOT NULL,
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rfid_cards`
--

INSERT INTO `rfid_cards` (`id`, `rfid_uid`, `registered_at`) VALUES
(1, '150D0104', '2025-02-02 15:38:26'),
(2, '150D0107', '2025-02-02 16:39:01'),
(3, '150D0103', '2025-02-02 16:00:13'),
(4, '150D0102', '2025-02-02 18:21:46');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `setting_description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_description`, `created_at`, `updated_at`) VALUES
(1, 'late_time', '09:00:00', 'Time after which attendance is marked as late (24-hour format)', '2025-02-02 14:59:01', '2025-02-02 14:59:01'),
(2, 'device_mode', 'scan', 'Current operating mode for RFID devices (scan/register)', '2025-02-02 14:59:01', '2025-02-02 18:09:55');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `name`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin', '2025-02-02 14:59:01'),
(2, 'test', '$2y$10$6/xFA8NqDs6war7nuNVKkOKbC26cG7n08nGdW8N2aIAdopuMwLcWu', 'test', 'user', '2025-02-02 15:39:36'),
(3, 'fwefwe', '$2y$10$Uysfb6.XnDchEn5D.i2as.UdNI7WVl4dtWtCPxik7cq2KYe3CnDG.', 'fpjgergr', 'user', '2025-02-02 17:41:42'),
(4, 'password', '$2y$10$lXF42KA2lprKbCtPOQHFk.VT.lF5ZTb0xAzCZESAyNweq6oFqCv5C', 'password', 'user', '2025-02-02 17:41:56'),
(5, 'pol', '$2y$10$2q8L4QLpV.q9a.71K2p2Be7mSY40mDOVwm9g4kFp1LTI7PJ.eJBOm', 'paulvardilala', 'user', '2025-02-02 17:42:08'),
(6, 'revenmalapoks', '$2y$10$Gdgmi0VB1fFirKFGtnciUObyTSXC2ABYIuQF9M8SBHfTue/u6qH8y', 'rebenlimotmot', 'user', '2025-02-02 17:42:26');

-- --------------------------------------------------------

--
-- Structure for view `attendance_view`
--
DROP TABLE IF EXISTS `attendance_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `attendance_view`  AS SELECT `al`.`id` AS `id`, `al`.`assignment_id` AS `assignment_id`, `al`.`user_id` AS `user_id`, `al`.`time_in` AS `time_in`, `al`.`time_out` AS `time_out`, `al`.`duration_seconds` AS `duration_seconds`, `al`.`log_date` AS `log_date`, `al`.`created_at` AS `created_at`, `al`.`updated_by` AS `updated_by`, `al`.`updated_at` AS `updated_at`, `al`.`status` AS `status`, `al`.`override_status` AS `override_status`, `al`.`override_remarks` AS `override_remarks`, `u`.`name` AS `user_name`, `u`.`username` AS `username`, CASE WHEN `al`.`override_status` is not null THEN `al`.`override_status` ELSE `al`.`status` END AS `effective_status`, CASE WHEN `al`.`time_out` is not null THEN concat_ws(' ',nullif(concat(floor(`al`.`duration_seconds` / 3600),' hrs'),'0 hrs'),nullif(concat(floor(`al`.`duration_seconds` MOD 3600 / 60),' mins'),'0 mins'),nullif(concat(`al`.`duration_seconds` MOD 60,' secs'),'0 secs')) ELSE NULL END AS `duration`, `rc`.`rfid_uid` AS `rfid_uid` FROM (((`attendance_logs` `al` join `rfid_assignments` `ra` on(`al`.`assignment_id` = `ra`.`id`)) join `users` `u` on(`ra`.`user_id` = `u`.`id`)) join `rfid_cards` `rc` on(`ra`.`rfid_id` = `rc`.`id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `daily_attendance_summary`
--
DROP TABLE IF EXISTS `daily_attendance_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `daily_attendance_summary`  AS SELECT cast(`al`.`log_date` as date) AS `attendance_date`, count(distinct `u`.`id`) AS `total_present`, count(distinct case when `al`.`status` = 'on_time' then `u`.`id` end) AS `on_time`, count(distinct case when `al`.`status` = 'late' then `u`.`id` end) AS `late`, count(distinct case when `al`.`status` = 'excused' then `u`.`id` end) AS `excused`, count(distinct case when `al`.`status` = 'half_day' then `u`.`id` end) AS `half_day`, count(distinct case when `al`.`status` = 'vacation' then `u`.`id` end) AS `vacation`, count(distinct case when `al`.`status` = 'absent' then `u`.`id` end) AS `absent`, (select count(0) from `users` where `users`.`role` = 'user') AS `total_users`, count(distinct case when `al`.`time_out` is null then `u`.`id` end) AS `still_in` FROM ((`users` `u` left join `rfid_assignments` `ra` on(`u`.`id` = `ra`.`user_id` and `ra`.`is_active` = 1)) left join `attendance_logs` `al` on(`ra`.`id` = `al`.`assignment_id`)) WHERE `u`.`role` <> 'admin' GROUP BY cast(`al`.`log_date` as date) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_date` (`user_id`,`log_date`),
  ADD KEY `idx_assignment` (`assignment_id`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_holiday_date` (`holiday_date`),
  ADD KEY `idx_holiday_date` (`holiday_date`);

--
-- Indexes for table `rfid_assignments`
--
ALTER TABLE `rfid_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_rfid_assignment` (`rfid_id`,`user_id`,`is_active`);

--
-- Indexes for table `rfid_cards`
--
ALTER TABLE `rfid_cards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rfid_uid` (`rfid_uid`),
  ADD KEY `idx_rfid_uid` (`rfid_uid`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_user_role` (`role`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rfid_assignments`
--
ALTER TABLE `rfid_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `rfid_cards`
--
ALTER TABLE `rfid_cards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD CONSTRAINT `attendance_logs_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_attendance_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `rfid_assignments` (`id`),
  ADD CONSTRAINT `fk_attendance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `rfid_assignments`
--
ALTER TABLE `rfid_assignments`
  ADD CONSTRAINT `rfid_assignments_ibfk_1` FOREIGN KEY (`rfid_id`) REFERENCES `rfid_cards` (`id`),
  ADD CONSTRAINT `rfid_assignments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

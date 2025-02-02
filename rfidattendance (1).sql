-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 02, 2025 at 05:35 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

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
  `status` enum('on_time','late') DEFAULT NULL,
  `log_date` date GENERATED ALWAYS AS (cast(`time_in` as date)) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance_logs`
--

INSERT INTO `attendance_logs` (`id`, `assignment_id`, `user_id`, `time_in`, `time_out`, `duration_seconds`, `status`, `created_at`) VALUES
(1, 1, 2, '2025-02-02 23:40:13', '2025-02-02 23:41:00', 47, 'late', '2025-02-02 15:40:13'),
(2, 1, 2, '2025-02-02 23:43:51', '2025-02-02 23:44:05', 14, 'late', '2025-02-02 15:43:51'),
(3, 1, 2, '2025-02-02 23:46:50', '2025-02-02 23:47:18', 28, 'late', '2025-02-02 15:46:50'),
(4, 1, 2, '2025-02-02 23:47:29', '2025-02-02 23:55:50', 501, 'late', '2025-02-02 15:47:29'),
(5, 1, 2, '2025-02-02 23:57:05', '2025-02-02 23:57:13', 8, 'late', '2025-02-02 15:57:05'),
(6, 1, 2, '2025-02-02 23:57:24', '2025-02-02 23:57:30', 6, 'late', '2025-02-02 15:57:24'),
(7, 1, 2, '2025-02-02 23:57:43', '2025-02-02 23:57:47', 4, 'late', '2025-02-02 15:57:43'),
(8, 1, 2, '2025-02-02 23:58:05', '2025-02-02 23:58:47', 42, 'late', '2025-02-02 15:58:05'),
(9, 1, 2, '2025-02-02 23:59:35', '2025-02-02 23:59:43', 8, 'late', '2025-02-02 15:59:35'),
(10, 1, 2, '2025-02-03 00:11:45', '2025-02-03 00:11:51', 6, 'on_time', '2025-02-02 16:11:45'),
(11, 1, 2, '2025-02-03 00:22:14', '2025-02-03 00:22:27', 13, 'on_time', '2025-02-02 16:22:14'),
(12, 1, 2, '2025-02-03 00:28:24', '2025-02-03 00:28:29', 5, 'on_time', '2025-02-02 16:28:24'),
(13, 1, 2, '2025-02-03 00:32:11', '2025-02-03 00:32:14', 3, 'on_time', '2025-02-02 16:32:11');

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
,`log_date` date
,`status` enum('on_time','late')
,`created_at` timestamp
,`user_name` varchar(100)
,`username` varchar(50)
,`duration` varchar(81)
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
(1, 1, 2, '2025-02-02 15:39:53', 1);

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
(1, '150D0104', '2025-02-02 15:38:26');

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
(2, 'device_mode', 'scan', 'Current operating mode for RFID devices (scan/register)', '2025-02-02 14:59:01', '2025-02-02 15:40:09');

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
(2, 'test', '$2y$10$6/xFA8NqDs6war7nuNVKkOKbC26cG7n08nGdW8N2aIAdopuMwLcWu', 'test', 'user', '2025-02-02 15:39:36');

-- --------------------------------------------------------

--
-- Structure for view `attendance_view`
--
DROP TABLE IF EXISTS `attendance_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `attendance_view`  AS SELECT `al`.`id` AS `id`, `al`.`assignment_id` AS `assignment_id`, `al`.`user_id` AS `user_id`, `al`.`time_in` AS `time_in`, `al`.`time_out` AS `time_out`, `al`.`log_date` AS `log_date`, `al`.`status` AS `status`, `al`.`created_at` AS `created_at`, `u`.`name` AS `user_name`, `u`.`username` AS `username`, CASE WHEN `al`.`time_out` is not null THEN concat_ws(' ',nullif(concat(floor(timestampdiff(SECOND,`al`.`time_in`,`al`.`time_out`) / 3600),' hrs'),'0 hrs'),nullif(concat(floor(timestampdiff(SECOND,`al`.`time_in`,`al`.`time_out`) MOD 3600 / 60),' mins'),'0 mins'),nullif(concat(timestampdiff(SECOND,`al`.`time_in`,`al`.`time_out`) MOD 60,' secs'),'0 secs')) ELSE NULL END AS `duration`, `rc`.`rfid_uid` AS `rfid_uid` FROM (((`attendance_logs` `al` join `users` `u` on(`al`.`user_id` = `u`.`id`)) join `rfid_assignments` `ra` on(`al`.`assignment_id` = `ra`.`id`)) join `rfid_cards` `rc` on(`ra`.`rfid_id` = `rc`.`id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `daily_attendance_summary`
--
DROP TABLE IF EXISTS `daily_attendance_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `daily_attendance_summary`  AS SELECT cast(`al`.`time_in` as date) AS `attendance_date`, count(distinct `al`.`user_id`) AS `total_present`, count(distinct case when `al`.`status` = 'on_time' then `al`.`user_id` end) AS `on_time`, count(distinct case when `al`.`status` = 'late' then `al`.`user_id` end) AS `late`, (select count(0) from `users` where `users`.`role` = 'user') AS `total_users`, count(distinct case when `al`.`time_out` is null then `al`.`user_id` end) AS `still_in` FROM `attendance_logs` AS `al` GROUP BY cast(`al`.`time_in` as date) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_date` (`user_id`,`log_date`),
  ADD KEY `idx_assignment` (`assignment_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rfid_assignments`
--
ALTER TABLE `rfid_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `rfid_cards`
--
ALTER TABLE `rfid_cards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
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

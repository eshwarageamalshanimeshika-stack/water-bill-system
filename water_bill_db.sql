-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 18, 2025 at 06:08 AM
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
-- Database: `water_bill_db`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_calculate_bill_amount` (IN `p_customer_id` INT, IN `p_units_consumed` DECIMAL(10,2), OUT `p_amount` DECIMAL(10,2))   BEGIN
    DECLARE v_tariff VARCHAR(20);
    
    SELECT tariff INTO v_tariff FROM customer WHERE customer_id = p_customer_id;
    
    -- Simple calculation - you can enhance this based on slab rates
    IF v_tariff = 'domestic' THEN
        SET p_amount = p_units_consumed * 50; -- Base rate
    ELSEIF v_tariff = 'commercial' THEN
        SET p_amount = p_units_consumed * 100;
    ELSEIF v_tariff = 'industrial' THEN
        SET p_amount = p_units_consumed * 120;
    ELSE
        SET p_amount = 0;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_customer_payment_history` (IN `p_customer_id` INT)   BEGIN
    SELECT 
        p.payment_id,
        p.bill_id,
        b.bill_date,
        b.amount as bill_amount,
        p.amount as payment_amount,
        p.method,
        p.payment_date,
        b.status as bill_status
    FROM payments p
    JOIN bills b ON p.bill_id = b.id
    WHERE p.customer_id = p_customer_id
    ORDER BY p.payment_date DESC;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `admin_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin') NOT NULL DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`admin_id`, `name`, `email`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'ADM001', 'adm001@1', 'ADM001', 'adm001', 'admin', '2025-09-15 09:20:39'),
(2, 'ADM002', 'adm002@2', 'ADM002', 'adm002', 'admin', '2025-09-18 04:27:05');

-- --------------------------------------------------------

--
-- Table structure for table `bills`
--

CREATE TABLE `bills` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `meter_reading_id` int(11) NOT NULL,
  `bill_date` date NOT NULL,
  `due_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `units_consumed` int(11) NOT NULL DEFAULT 0,
  `status` enum('paid','unpaid') DEFAULT 'unpaid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bills`
--

INSERT INTO `bills` (`id`, `customer_id`, `meter_reading_id`, `bill_date`, `due_date`, `amount`, `units_consumed`, `status`, `created_at`) VALUES
(39, 1, 62, '2025-11-17', '2025-12-01', 10725.00, 53, 'paid', '2025-11-17 05:55:01'),
(40, 1, 64, '2025-11-17', '2025-12-01', 1850.00, 19, 'unpaid', '2025-11-17 05:56:26'),
(41, 2, 63, '2025-11-17', '2025-12-01', 2290.00, 22, 'unpaid', '2025-11-17 06:08:20'),
(42, 5, 65, '2025-11-17', '2025-12-01', 1550.00, 7, 'paid', '2025-11-17 08:53:36'),
(43, 6, 66, '2025-11-17', '2025-12-01', 1270.00, 7, 'unpaid', '2025-11-17 08:57:25'),
(44, 3, 67, '2025-11-17', '2025-12-01', 690.00, 7, 'unpaid', '2025-11-17 08:58:59'),
(45, 5, 68, '2025-11-18', '2025-12-02', 2300.00, 12, 'unpaid', '2025-11-18 05:05:30');

-- --------------------------------------------------------

--
-- Table structure for table `connection_logs`
--

CREATE TABLE `connection_logs` (
  `log_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `previous_status` varchar(20) DEFAULT NULL,
  `new_status` varchar(20) NOT NULL,
  `action_reason` text DEFAULT NULL,
  `action_date` datetime NOT NULL,
  `performed_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `connection_logs`
--

INSERT INTO `connection_logs` (`log_id`, `customer_id`, `previous_status`, `new_status`, `action_reason`, `action_date`, `performed_by`, `created_at`) VALUES
(1, 2, 'active', 'disconnected', 'Non-payment for 45 days - Outstanding balance not cleared', '2025-11-14 10:30:00', 'ADM001', '2025-11-14 05:00:00'),
(2, 2, 'disconnected', 'active', 'Full payment received - Reconnection completed', '2025-11-14 14:30:00', 'ADM001', '2025-11-14 09:00:00'),
(3, 5, 'active', 'disconnected', 'Auto-logged status change', '2025-11-18 10:12:49', 'root@localhost', '2025-11-18 04:42:49'),
(4, 5, 'active', 'disconnected', 'not payment', '2025-11-18 10:12:49', '1', '2025-11-18 04:42:49'),
(5, 5, 'disconnected', 'active', 'Auto-logged status change', '2025-11-18 10:14:39', 'root@localhost', '2025-11-18 04:44:39'),
(6, 5, 'disconnected', 'active', 'ply the full payment', '2025-11-18 10:14:39', '1', '2025-11-18 04:44:39');

-- --------------------------------------------------------

--
-- Table structure for table `customer`
--

CREATE TABLE `customer` (
  `customer_id` int(11) NOT NULL,
  `account_no` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `house` varchar(255) NOT NULL,
  `house_no` varchar(50) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `tariff` enum('domestic','commercial','industrial') NOT NULL,
  `connection_status` enum('active','disconnected','hold','read_bill') NOT NULL DEFAULT 'active',
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer`
--

INSERT INTO `customer` (`customer_id`, `account_no`, `name`, `email`, `username`, `house`, `house_no`, `phone`, `tariff`, `connection_status`, `password`, `created_at`) VALUES
(1, '1007', 'ceylon electricity board', 'ceb@1007', 'CEB1007', 'CEB', '81', '0764019117', 'domestic', 'active', 'ceb1007', '2025-09-15 09:20:39'),
(2, '1051', 'MSO Stores', 'mso@1051', 'MSO1051', 'MSO', '40', '0772564859', 'domestic', 'active', 'mso1010', '2025-09-18 04:27:05'),
(3, '1028', 'ceylon electricity board', 'ceb@1028', 'CEB1028', 'CEB', '103', '0764019118', 'domestic', 'active', 'ceb1028', '2025-10-01 06:15:22'),
(5, '1000', 'a e a nimeshika', 'nimeshika@gmail.com', 'ama', 'nimeshika', '100', '0778865256', 'commercial', 'active', 'ama10', '2025-11-17 08:52:54'),
(6, '1001', 'k w v prabath', 'prabath@gmail.com', 'prabath', 'prabath', '101', '0772747952', 'industrial', 'active', 'prabath101', '2025-11-17 08:56:44');

--
-- Triggers `customer`
--
DELIMITER $$
CREATE TRIGGER `trg_customer_audit` AFTER UPDATE ON `customer` FOR EACH ROW BEGIN
    IF OLD.connection_status != NEW.connection_status THEN
        INSERT INTO connection_logs (
            customer_id, 
            previous_status, 
            new_status, 
            action_reason, 
            action_date, 
            performed_by
        )
        VALUES (
            NEW.customer_id,
            OLD.connection_status,
            NEW.connection_status,
            'Auto-logged status change',
            NOW(),
            USER()
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `meter_reading`
--

CREATE TABLE `meter_reading` (
  `reading_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `previous_reading` decimal(10,2) NOT NULL DEFAULT 0.00,
  `current_reading` decimal(10,2) NOT NULL DEFAULT 0.00,
  `units_consumed` decimal(10,2) NOT NULL DEFAULT 0.00,
  `fixed_units` decimal(10,2) DEFAULT NULL,
  `date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meter_reading`
--

INSERT INTO `meter_reading` (`reading_id`, `customer_id`, `previous_reading`, `current_reading`, `units_consumed`, `fixed_units`, `date`, `created_at`) VALUES
(62, 1, 0.00, 53.00, 53.00, NULL, '2025-11-17', '2025-11-17 05:24:18'),
(63, 2, 0.00, 0.00, 22.00, 22.00, '2025-11-17', '2025-11-17 05:24:34'),
(64, 1, 53.00, 72.00, 19.00, NULL, '2025-11-17', '2025-11-17 05:56:08'),
(65, 5, 0.00, 7.00, 7.00, NULL, '2025-11-17', '2025-11-17 08:53:16'),
(66, 6, 0.00, 7.00, 7.00, NULL, '2025-11-17', '2025-11-17 08:57:03'),
(67, 3, 0.00, 7.00, 7.00, NULL, '2025-11-17', '2025-11-17 08:58:44'),
(68, 5, 7.00, 19.00, 12.00, NULL, '2025-11-18', '2025-11-18 05:05:16');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `bill_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` enum('cash','card','online') DEFAULT 'cash',
  `payment_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `bill_id`, `customer_id`, `amount`, `method`, `payment_date`, `created_at`) VALUES
(21, 39, 1, 5000.00, 'cash', '2025-11-17', '2025-11-17 05:55:47'),
(22, 39, 1, 5000.00, 'cash', '2025-11-17', '2025-11-17 05:57:18'),
(23, 39, 1, 725.00, 'cash', '2025-11-17', '2025-11-17 08:45:18'),
(24, 42, 5, 1500.00, 'cash', '2025-11-18', '2025-11-18 04:43:29'),
(25, 42, 5, 50.00, 'cash', '2025-11-18', '2025-11-18 04:44:10');

-- --------------------------------------------------------

--
-- Table structure for table `tariff`
--

CREATE TABLE `tariff` (
  `tariff_id` int(11) NOT NULL,
  `category` enum('domestic','commercial','industrial') NOT NULL,
  `slab_range` varchar(50) NOT NULL,
  `rate` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tariff`
--

INSERT INTO `tariff` (`tariff_id`, `category`, `slab_range`, `rate`, `created_at`, `updated_at`) VALUES
(1, 'domestic', '0-10', 8.00, '2025-01-01 00:00:00', '2025-11-17 04:49:23'),
(2, 'domestic', '11-25', 15.00, '2025-01-01 00:00:00', '2025-11-17 04:49:23'),
(3, 'domestic', '26-40', 30.00, '2025-01-01 00:00:00', '2025-11-17 04:49:23'),
(4, 'domestic', '41-60', 75.00, '2025-01-01 00:00:00', '2025-11-17 04:49:23'),
(5, 'domestic', '61+', 110.00, '2025-01-01 00:00:00', '2025-11-17 04:49:23'),
(6, 'commercial', '0-20', 75.00, '2025-01-01 00:00:00', '2025-11-17 04:49:23'),
(7, 'commercial', '21-40', 110.00, '2025-01-01 00:00:00', '2025-11-17 04:49:23'),
(8, 'commercial', '41+', 135.00, '2025-01-01 00:00:00', '2025-11-17 04:49:23'),
(9, 'industrial', '0-50', 90.00, '2025-01-01 00:00:00', '2025-11-17 04:49:23'),
(10, 'industrial', '51-100', 120.00, '2025-01-01 00:00:00', '2025-11-17 04:49:23'),
(11, 'industrial', '101+', 150.00, '2025-01-01 00:00:00', '2025-11-17 04:49:23');

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_connection_status_summary`
-- (See below for the actual view)
--
CREATE TABLE `view_connection_status_summary` (
`connection_status` enum('active','disconnected','hold','read_bill')
,`customer_count` bigint(21)
,`total_bills` bigint(21)
,`outstanding_amount` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_customer_outstanding`
-- (See below for the actual view)
--
CREATE TABLE `view_customer_outstanding` (
`customer_id` int(11)
,`account_no` varchar(50)
,`name` varchar(100)
,`email` varchar(100)
,`phone` varchar(20)
,`tariff` enum('domestic','commercial','industrial')
,`connection_status` enum('active','disconnected','hold','read_bill')
,`total_bills` bigint(21)
,`unpaid_bills` bigint(21)
,`total_billed` decimal(32,2)
,`total_paid` decimal(32,2)
,`total_outstanding` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_monthly_revenue`
-- (See below for the actual view)
--
CREATE TABLE `view_monthly_revenue` (
`month` varchar(7)
,`total_payments` bigint(21)
,`unique_customers` bigint(21)
,`total_revenue` decimal(32,2)
,`average_payment` decimal(14,6)
);

-- --------------------------------------------------------

--
-- Structure for view `view_connection_status_summary`
--
DROP TABLE IF EXISTS `view_connection_status_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_connection_status_summary`  AS SELECT `c`.`connection_status` AS `connection_status`, count(0) AS `customer_count`, count(distinct `b`.`id`) AS `total_bills`, coalesce(sum(case when `b`.`status` = 'unpaid' then `b`.`amount` else 0 end),0) AS `outstanding_amount` FROM (`customer` `c` left join `bills` `b` on(`c`.`customer_id` = `b`.`customer_id`)) GROUP BY `c`.`connection_status` ;

-- --------------------------------------------------------

--
-- Structure for view `view_customer_outstanding`
--
DROP TABLE IF EXISTS `view_customer_outstanding`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_customer_outstanding`  AS SELECT `c`.`customer_id` AS `customer_id`, `c`.`account_no` AS `account_no`, `c`.`name` AS `name`, `c`.`email` AS `email`, `c`.`phone` AS `phone`, `c`.`tariff` AS `tariff`, `c`.`connection_status` AS `connection_status`, count(distinct `b`.`id`) AS `total_bills`, count(distinct case when `b`.`status` = 'unpaid' then `b`.`id` end) AS `unpaid_bills`, coalesce(sum(`b`.`amount`),0) AS `total_billed`, coalesce(sum(case when `b`.`status` = 'paid' then `b`.`amount` else 0 end),0) AS `total_paid`, coalesce(sum(case when `b`.`status` = 'unpaid' then `b`.`amount` else 0 end),0) AS `total_outstanding` FROM (`customer` `c` left join `bills` `b` on(`c`.`customer_id` = `b`.`customer_id`)) GROUP BY `c`.`customer_id` ;

-- --------------------------------------------------------

--
-- Structure for view `view_monthly_revenue`
--
DROP TABLE IF EXISTS `view_monthly_revenue`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_monthly_revenue`  AS SELECT date_format(`p`.`payment_date`,'%Y-%m') AS `month`, count(distinct `p`.`payment_id`) AS `total_payments`, count(distinct `p`.`customer_id`) AS `unique_customers`, sum(`p`.`amount`) AS `total_revenue`, avg(`p`.`amount`) AS `average_payment` FROM `payments` AS `p` GROUP BY date_format(`p`.`payment_date`,'%Y-%m') ORDER BY date_format(`p`.`payment_date`,'%Y-%m') DESC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `bills`
--
ALTER TABLE `bills`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `meter_reading_id` (`meter_reading_id`),
  ADD KEY `idx_bill_date` (`bill_date`),
  ADD KEY `idx_due_date` (`due_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `connection_logs`
--
ALTER TABLE `connection_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_action_date` (`action_date`),
  ADD KEY `idx_new_status` (`new_status`);

--
-- Indexes for table `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `account_no` (`account_no`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_connection_status` (`connection_status`),
  ADD KEY `idx_tariff` (`tariff`);

--
-- Indexes for table `meter_reading`
--
ALTER TABLE `meter_reading`
  ADD PRIMARY KEY (`reading_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `idx_date` (`date`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `fk_payments_bill` (`bill_id`),
  ADD KEY `fk_payments_customer` (`customer_id`),
  ADD KEY `idx_payment_date` (`payment_date`);

--
-- Indexes for table `tariff`
--
ALTER TABLE `tariff`
  ADD PRIMARY KEY (`tariff_id`),
  ADD KEY `idx_category` (`category`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `bills`
--
ALTER TABLE `bills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `connection_logs`
--
ALTER TABLE `connection_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `customer`
--
ALTER TABLE `customer`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `meter_reading`
--
ALTER TABLE `meter_reading`
  MODIFY `reading_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `tariff`
--
ALTER TABLE `tariff`
  MODIFY `tariff_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bills`
--
ALTER TABLE `bills`
  ADD CONSTRAINT `bills_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bills_ibfk_2` FOREIGN KEY (`meter_reading_id`) REFERENCES `meter_reading` (`reading_id`) ON DELETE CASCADE;

--
-- Constraints for table `connection_logs`
--
ALTER TABLE `connection_logs`
  ADD CONSTRAINT `fk_connection_logs_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE;

--
-- Constraints for table `meter_reading`
--
ALTER TABLE `meter_reading`
  ADD CONSTRAINT `meter_reading_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_bill` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_payments_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

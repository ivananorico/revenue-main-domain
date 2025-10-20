-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost: 3307
-- Generation Time: Oct 20, 2025 at 10:32 AM
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
-- Database: `rpt_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `building`
--

CREATE TABLE `building` (
  `building_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `land_id` int(11) DEFAULT NULL,
  `location` varchar(255) NOT NULL,
  `barangay` varchar(255) NOT NULL,
  `municipality` varchar(255) NOT NULL,
  `tdn_no` varchar(100) DEFAULT NULL,
  `building_type` varchar(100) NOT NULL,
  `building_area` decimal(12,2) NOT NULL,
  `construction_type` varchar(100) NOT NULL,
  `year_built` year(4) NOT NULL,
  `number_of_storeys` int(11) DEFAULT 1,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `building`
--

INSERT INTO `building` (`building_id`, `application_id`, `land_id`, `location`, `barangay`, `municipality`, `tdn_no`, `building_type`, `building_area`, `construction_type`, `year_built`, `number_of_storeys`, `status`, `created_at`, `updated_at`) VALUES
(2, 8, 4, '168 Lydia', 'Santa Monica', 'Quezon City', NULL, 'Residential', 100.00, 'Concrete', '2025', 1, 'active', '2025-10-20 00:41:37', '2025-10-20 00:41:37');

-- --------------------------------------------------------

--
-- Table structure for table `building_assessment_tax`
--

CREATE TABLE `building_assessment_tax` (
  `build_tax_id` int(11) NOT NULL,
  `building_id` int(11) NOT NULL,
  `assessment_year` year(4) NOT NULL,
  `building_value_per_sqm` decimal(12,2) NOT NULL,
  `building_assessed_lvl` decimal(5,2) NOT NULL,
  `building_assessed_value` decimal(15,2) NOT NULL,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 1.00,
  `sef_rate` decimal(5,2) NOT NULL DEFAULT 0.10,
  `building_total_tax` decimal(15,2) NOT NULL,
  `status` enum('current','paid','delinquent') DEFAULT 'current',
  `assessed_at` datetime DEFAULT current_timestamp(),
  `due_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `building_assessment_tax`
--

INSERT INTO `building_assessment_tax` (`build_tax_id`, `building_id`, `assessment_year`, `building_value_per_sqm`, `building_assessed_lvl`, `building_assessed_value`, `tax_rate`, `sef_rate`, `building_total_tax`, `status`, `assessed_at`, `due_date`) VALUES
(2, 2, '2025', 8000.00, 0.40, 320000.00, 0.10, 0.10, 640.00, 'current', '2025-10-20 00:41:37', '2025-12-31');

-- --------------------------------------------------------

--
-- Table structure for table `building_rate_config`
--

CREATE TABLE `building_rate_config` (
  `building_rate_id` int(11) NOT NULL,
  `building_type` varchar(100) NOT NULL,
  `construction_type` varchar(100) NOT NULL,
  `market_value_per_sqm` decimal(12,2) NOT NULL,
  `building_assessed_lvl` decimal(5,2) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `building_rate_config`
--

INSERT INTO `building_rate_config` (`building_rate_id`, `building_type`, `construction_type`, `market_value_per_sqm`, `building_assessed_lvl`, `created_at`) VALUES
(1, 'Residential', 'Concrete', 8000.00, 0.40, '2025-10-19 23:07:22'),
(2, 'Residential', 'Semi-Concrete', 5000.00, 0.35, '2025-10-19 23:07:22'),
(3, 'Residential', 'Wood', 3000.00, 0.30, '2025-10-19 23:07:22'),
(4, 'Commercial', 'Concrete', 12000.00, 0.50, '2025-10-19 23:07:22'),
(5, 'Commercial', 'Semi-Concrete', 8000.00, 0.45, '2025-10-19 23:07:22'),
(6, 'Industrial', 'Concrete', 10000.00, 0.50, '2025-10-19 23:07:22'),
(7, 'Industrial', 'Steel', 15000.00, 0.45, '2025-10-19 23:07:22'),
(8, 'Residential', 'Concrete/Wood', 15000.00, 0.30, '2025-10-20 14:52:07');

-- --------------------------------------------------------

--
-- Table structure for table `land`
--

CREATE TABLE `land` (
  `land_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `location` varchar(255) NOT NULL,
  `barangay` varchar(255) NOT NULL,
  `municipality` varchar(255) NOT NULL,
  `lot_area` decimal(12,2) NOT NULL,
  `land_use` varchar(100) NOT NULL,
  `tdn_no` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `land`
--

INSERT INTO `land` (`land_id`, `application_id`, `location`, `barangay`, `municipality`, `lot_area`, `land_use`, `tdn_no`, `status`, `created_at`, `updated_at`) VALUES
(4, 8, '168 Lydia', 'Santa Monica', 'Quezon City', 100.00, 'Residential', 'TDN-LAND-2025-008', 'active', '2025-10-20 00:41:37', '2025-10-20 00:41:37'),
(5, 9, '168 Lydia', 'Santa Monica', 'Quezon City', 100.00, 'Residential', 'TDN-LAND-2025-009', 'active', '2025-10-20 00:49:15', '2025-10-20 00:49:15');

-- --------------------------------------------------------

--
-- Table structure for table `land_assessment_tax`
--

CREATE TABLE `land_assessment_tax` (
  `land_tax_id` int(11) NOT NULL,
  `land_id` int(11) NOT NULL,
  `assessment_year` year(4) NOT NULL,
  `land_value_per_sqm` decimal(12,2) NOT NULL,
  `land_assessed_lvl` decimal(5,2) NOT NULL,
  `land_assessed_value` decimal(15,2) NOT NULL,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 1.00,
  `sef_rate` decimal(5,2) NOT NULL DEFAULT 0.10,
  `land_total_tax` decimal(15,2) NOT NULL,
  `status` enum('current','paid','delinquent') DEFAULT 'current',
  `assessed_at` datetime DEFAULT current_timestamp(),
  `due_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `land_assessment_tax`
--

INSERT INTO `land_assessment_tax` (`land_tax_id`, `land_id`, `assessment_year`, `land_value_per_sqm`, `land_assessed_lvl`, `land_assessed_value`, `tax_rate`, `sef_rate`, `land_total_tax`, `status`, `assessed_at`, `due_date`) VALUES
(4, 4, '2025', 1500.00, 0.20, 30000.00, 0.10, 0.10, 60.00, 'current', '2025-10-20 00:41:37', '2025-12-31'),
(5, 5, '2025', 1500.00, 0.20, 30000.00, 0.10, 0.10, 60.00, 'current', '2025-10-20 00:49:15', '2025-12-31');

-- --------------------------------------------------------

--
-- Table structure for table `land_rate_config`
--

CREATE TABLE `land_rate_config` (
  `land_rate_id` int(11) NOT NULL,
  `land_use` varchar(100) NOT NULL,
  `market_value_per_sqm` decimal(12,2) NOT NULL,
  `land_assessed_lvl` decimal(5,2) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `land_rate_config`
--

INSERT INTO `land_rate_config` (`land_rate_id`, `land_use`, `market_value_per_sqm`, `land_assessed_lvl`, `created_at`) VALUES
(1, 'Residential', 1500.00, 0.20, '2025-10-19 21:38:59'),
(2, 'Commercial', 3000.00, 0.35, '2025-10-19 21:38:59'),
(3, 'Industrial', 2500.00, 0.40, '2025-10-19 21:38:59'),
(4, 'Agricultural', 800.00, 0.10, '2025-10-19 21:38:59');

-- --------------------------------------------------------

--
-- Table structure for table `quarterly`
--

CREATE TABLE `quarterly` (
  `quarter_id` int(11) NOT NULL,
  `land_tax_id` int(11) NOT NULL,
  `quarter_no` tinyint(4) NOT NULL CHECK (`quarter_no` between 1 and 4),
  `tax_amount` decimal(15,2) NOT NULL,
  `penalty` decimal(15,2) DEFAULT 0.00,
  `total_tax_amount` decimal(15,2) NOT NULL,
  `status` enum('unpaid','paid','overdue') DEFAULT 'unpaid',
  `payment_method` enum('maya','gcash') DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `verification_code` varchar(10) DEFAULT NULL,
  `verification_attempts` int(11) DEFAULT 0,
  `expires_at` datetime DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `date_paid` date DEFAULT NULL,
  `or_no` varchar(100) DEFAULT NULL,
  `due_date` date NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quarterly`
--

INSERT INTO `quarterly` (`quarter_id`, `land_tax_id`, `quarter_no`, `tax_amount`, `penalty`, `total_tax_amount`, `status`, `payment_method`, `phone_number`, `email`, `verification_code`, `verification_attempts`, `expires_at`, `verified_at`, `date_paid`, `or_no`, `due_date`, `created_at`, `updated_at`) VALUES
(13, 4, 1, 175.00, 0.00, 175.00, 'paid', 'gcash', '09950281131', 'van@gmail.com', NULL, 0, NULL, '2025-10-20 04:16:02', '2025-10-20', NULL, '2025-03-31', '2025-10-20 00:41:37', '2025-10-20 04:16:02'),
(14, 4, 2, 175.00, 0.00, 175.00, 'unpaid', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, '2025-06-30', '2025-10-20 00:41:37', '2025-10-20 04:28:41'),
(15, 4, 3, 175.00, 0.00, 175.00, 'unpaid', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, '2025-09-30', '2025-10-20 00:41:37', '2025-10-20 00:41:37'),
(16, 4, 4, 175.00, 0.00, 175.00, 'unpaid', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, '2025-12-31', '2025-10-20 00:41:37', '2025-10-20 00:41:37'),
(17, 5, 4, 15.00, 0.00, 15.00, 'unpaid', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, '2025-12-31', '2025-10-20 00:49:15', '2025-10-20 15:47:16');

-- --------------------------------------------------------

--
-- Table structure for table `rpt_applications`
--

CREATE TABLE `rpt_applications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `application_type` enum('new','transfer') NOT NULL DEFAULT 'new',
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `date_of_birth` date NOT NULL,
  `civil_status` enum('Single','Married','Divorced','Widowed') NOT NULL,
  `house_number` varchar(50) DEFAULT NULL,
  `street` varchar(255) DEFAULT NULL,
  `barangay` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `zip_code` varchar(10) DEFAULT NULL,
  `contact_number` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `property_type` enum('land_only','land_with_house') NOT NULL,
  `property_address` varchar(255) NOT NULL,
  `property_barangay` varchar(255) NOT NULL,
  `property_municipality` varchar(255) NOT NULL,
  `previous_tdn` varchar(50) DEFAULT NULL,
  `previous_owner` varchar(255) DEFAULT NULL,
  `status` enum('pending','for_assessment','assessed','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `application_date` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rpt_applications`
--

INSERT INTO `rpt_applications` (`id`, `user_id`, `application_type`, `first_name`, `middle_name`, `last_name`, `gender`, `date_of_birth`, `civil_status`, `house_number`, `street`, `barangay`, `city`, `zip_code`, `contact_number`, `email`, `property_type`, `property_address`, `property_barangay`, `property_municipality`, `previous_tdn`, `previous_owner`, `status`, `application_date`, `updated_at`) VALUES
(8, 1, 'new', 'Ivan', 'Dolera', 'Anorico', 'Male', '2002-12-16', 'Single', '168', 'Lydia', 'Santa Monica', 'Quezon City', '1117', '09950281131', 'van@gmail.com', 'land_with_house', '168 Lydia', 'Santa Monica', 'Quezon City', '', '', 'approved', '2025-10-19 17:39:40', '2025-10-20 01:37:18'),
(9, 2, 'new', 'Ivan', 'Dolera', 'Anorico', 'Male', '2002-12-16', 'Single', '168', 'Lydia', 'Santa Monica', 'Quezon City', '1117', '09950281131', 'van@gmail.com', 'land_only', '168 Lydia', 'Santa Monica', 'Quezon City', '', '', 'approved', '2025-10-19 21:24:22', '2025-10-20 01:37:04');

-- --------------------------------------------------------

--
-- Table structure for table `rpt_assessment_schedule`
--

CREATE TABLE `rpt_assessment_schedule` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `visit_date` date NOT NULL,
  `assessor_name` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `scheduled_at` datetime DEFAULT current_timestamp(),
  `status` enum('scheduled','completed','cancelled') DEFAULT 'scheduled'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rpt_assessment_schedule`
--

INSERT INTO `rpt_assessment_schedule` (`id`, `application_id`, `visit_date`, `assessor_name`, `notes`, `scheduled_at`, `status`) VALUES
(3, 8, '2025-10-22', 'Ivan', 'Visiting your place', '2025-10-20 00:20:00', 'scheduled'),
(4, 9, '2025-10-22', 'Luan', 'visit', '2025-10-20 00:49:05', 'scheduled');

-- --------------------------------------------------------

--
-- Table structure for table `rpt_documents`
--

CREATE TABLE `rpt_documents` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `document_type` enum('tct','deed_of_sale','valid_id','barangay_clearance','tax_clearance','location_plan','survey_plan','tax_declaration','other') NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) NOT NULL,
  `file_extension` varchar(10) NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rpt_documents`
--

INSERT INTO `rpt_documents` (`id`, `application_id`, `document_type`, `file_name`, `file_path`, `file_size`, `file_extension`, `uploaded_at`) VALUES
(36, 8, 'tct', 'tct_1_1760866780_68f4b1dc97333.png', 'C:\\xampp\\htdocs\\revenue\\citizen_portal\\rpt_card\\register_rpt/../../RPT/uploads/tct_1_1760866780_68f4b1dc97333.png', 0, '', '2025-10-19 17:39:40'),
(37, 8, 'valid_id', 'valid_id_1_1760866780_68f4b1dc9815c.png', 'C:\\xampp\\htdocs\\revenue\\citizen_portal\\rpt_card\\register_rpt/../../RPT/uploads/valid_id_1_1760866780_68f4b1dc9815c.png', 0, '', '2025-10-19 17:39:40'),
(38, 8, 'barangay_clearance', 'barangay_clearance_1_1760866780_68f4b1dc98b89.png', 'C:\\xampp\\htdocs\\revenue\\citizen_portal\\rpt_card\\register_rpt/../../RPT/uploads/barangay_clearance_1_1760866780_68f4b1dc98b89.png', 0, '', '2025-10-19 17:39:40'),
(39, 8, 'location_plan', 'location_plan_1_1760866780_68f4b1dc99804.png', 'C:\\xampp\\htdocs\\revenue\\citizen_portal\\rpt_card\\register_rpt/../../RPT/uploads/location_plan_1_1760866780_68f4b1dc99804.png', 0, '', '2025-10-19 17:39:40'),
(40, 8, 'survey_plan', 'survey_plan_1_1760866780_68f4b1dc9a18d.png', 'C:\\xampp\\htdocs\\revenue\\citizen_portal\\rpt_card\\register_rpt/../../RPT/uploads/survey_plan_1_1760866780_68f4b1dc9a18d.png', 0, '', '2025-10-19 17:39:40'),
(41, 9, 'tct', 'tct_2_1760880262_68f4e6862189c.png', 'C:\\xampp\\htdocs\\revenue\\citizen_portal\\rpt_card\\register_rpt/../../RPT/uploads/tct_2_1760880262_68f4e6862189c.png', 0, '', '2025-10-19 21:24:22'),
(42, 9, 'valid_id', 'valid_id_2_1760880262_68f4e686231e7.png', 'C:\\xampp\\htdocs\\revenue\\citizen_portal\\rpt_card\\register_rpt/../../RPT/uploads/valid_id_2_1760880262_68f4e686231e7.png', 0, '', '2025-10-19 21:24:22'),
(43, 9, 'barangay_clearance', 'barangay_clearance_2_1760880262_68f4e6862461e.png', 'C:\\xampp\\htdocs\\revenue\\citizen_portal\\rpt_card\\register_rpt/../../RPT/uploads/barangay_clearance_2_1760880262_68f4e6862461e.png', 0, '', '2025-10-19 21:24:22'),
(44, 9, 'location_plan', 'location_plan_2_1760880262_68f4e686255a5.png', 'C:\\xampp\\htdocs\\revenue\\citizen_portal\\rpt_card\\register_rpt/../../RPT/uploads/location_plan_2_1760880262_68f4e686255a5.png', 0, '', '2025-10-19 21:24:22'),
(45, 9, 'survey_plan', 'survey_plan_2_1760880262_68f4e68626519.png', 'C:\\xampp\\htdocs\\revenue\\citizen_portal\\rpt_card\\register_rpt/../../RPT/uploads/survey_plan_2_1760880262_68f4e68626519.png', 0, '', '2025-10-19 21:24:22');

-- --------------------------------------------------------

--
-- Table structure for table `tax_rate_config`
--

CREATE TABLE `tax_rate_config` (
  `tax_rate_id` int(11) NOT NULL,
  `tax_rate` decimal(5,2) NOT NULL,
  `sef_rate` decimal(5,2) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tax_rate_config`
--

INSERT INTO `tax_rate_config` (`tax_rate_id`, `tax_rate`, `sef_rate`, `created_at`) VALUES
(1, 0.10, 0.10, '2025-10-19 21:38:59');

-- --------------------------------------------------------

--
-- Table structure for table `total_tax`
--

CREATE TABLE `total_tax` (
  `tax_id` int(11) NOT NULL,
  `land_tax_id` int(11) DEFAULT NULL,
  `build_tax_id` int(11) DEFAULT NULL,
  `total_assessed_value` decimal(15,2) NOT NULL,
  `total_tax` decimal(15,2) NOT NULL,
  `payment_type` enum('cash','quarterly','annual') DEFAULT 'quarterly'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `total_tax`
--

INSERT INTO `total_tax` (`tax_id`, `land_tax_id`, `build_tax_id`, `total_assessed_value`, `total_tax`, `payment_type`) VALUES
(1, 1, NULL, 30000.00, 33000.00, 'quarterly'),
(2, 2, NULL, 30000.00, 6000.00, 'quarterly'),
(3, 3, NULL, 349997.00, 69999.40, 'quarterly'),
(4, 4, 2, 350000.00, 700.00, 'quarterly'),
(5, 5, NULL, 30000.00, 15.00, 'quarterly');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `building`
--
ALTER TABLE `building`
  ADD PRIMARY KEY (`building_id`),
  ADD KEY `application_id` (`application_id`),
  ADD KEY `land_id` (`land_id`),
  ADD KEY `building_type` (`building_type`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `building_assessment_tax`
--
ALTER TABLE `building_assessment_tax`
  ADD PRIMARY KEY (`build_tax_id`),
  ADD UNIQUE KEY `building_year_unique` (`building_id`,`assessment_year`),
  ADD KEY `building_id` (`building_id`),
  ADD KEY `assessment_year` (`assessment_year`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `building_rate_config`
--
ALTER TABLE `building_rate_config`
  ADD PRIMARY KEY (`building_rate_id`),
  ADD UNIQUE KEY `building_construction_unique` (`building_type`,`construction_type`);

--
-- Indexes for table `land`
--
ALTER TABLE `land`
  ADD PRIMARY KEY (`land_id`),
  ADD KEY `application_id` (`application_id`),
  ADD KEY `land_use` (`land_use`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `land_assessment_tax`
--
ALTER TABLE `land_assessment_tax`
  ADD PRIMARY KEY (`land_tax_id`),
  ADD UNIQUE KEY `land_year_unique` (`land_id`,`assessment_year`),
  ADD KEY `land_id` (`land_id`),
  ADD KEY `assessment_year` (`assessment_year`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `land_rate_config`
--
ALTER TABLE `land_rate_config`
  ADD PRIMARY KEY (`land_rate_id`),
  ADD UNIQUE KEY `land_use` (`land_use`);

--
-- Indexes for table `quarterly`
--
ALTER TABLE `quarterly`
  ADD PRIMARY KEY (`quarter_id`),
  ADD UNIQUE KEY `land_tax_quarter_unique` (`land_tax_id`,`quarter_no`),
  ADD KEY `land_tax_id` (`land_tax_id`),
  ADD KEY `quarter_no` (`quarter_no`),
  ADD KEY `status` (`status`),
  ADD KEY `due_date` (`due_date`),
  ADD KEY `idx_quarterly_verification` (`verification_code`,`expires_at`),
  ADD KEY `idx_quarterly_status` (`status`);

--
-- Indexes for table `rpt_applications`
--
ALTER TABLE `rpt_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `application_type` (`application_type`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `rpt_assessment_schedule`
--
ALTER TABLE `rpt_assessment_schedule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `application_id` (`application_id`);

--
-- Indexes for table `rpt_documents`
--
ALTER TABLE `rpt_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `application_id` (`application_id`),
  ADD KEY `document_type` (`document_type`);

--
-- Indexes for table `tax_rate_config`
--
ALTER TABLE `tax_rate_config`
  ADD PRIMARY KEY (`tax_rate_id`);

--
-- Indexes for table `total_tax`
--
ALTER TABLE `total_tax`
  ADD PRIMARY KEY (`tax_id`),
  ADD KEY `land_tax_id` (`land_tax_id`),
  ADD KEY `build_tax_id` (`build_tax_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `building`
--
ALTER TABLE `building`
  MODIFY `building_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `building_assessment_tax`
--
ALTER TABLE `building_assessment_tax`
  MODIFY `build_tax_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `building_rate_config`
--
ALTER TABLE `building_rate_config`
  MODIFY `building_rate_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `land`
--
ALTER TABLE `land`
  MODIFY `land_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `land_assessment_tax`
--
ALTER TABLE `land_assessment_tax`
  MODIFY `land_tax_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `land_rate_config`
--
ALTER TABLE `land_rate_config`
  MODIFY `land_rate_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `quarterly`
--
ALTER TABLE `quarterly`
  MODIFY `quarter_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `rpt_applications`
--
ALTER TABLE `rpt_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `rpt_assessment_schedule`
--
ALTER TABLE `rpt_assessment_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `rpt_documents`
--
ALTER TABLE `rpt_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `tax_rate_config`
--
ALTER TABLE `tax_rate_config`
  MODIFY `tax_rate_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `total_tax`
--
ALTER TABLE `total_tax`
  MODIFY `tax_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `building`
--
ALTER TABLE `building`
  ADD CONSTRAINT `fk_building_application` FOREIGN KEY (`application_id`) REFERENCES `rpt_applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_building_land` FOREIGN KEY (`land_id`) REFERENCES `land` (`land_id`) ON DELETE SET NULL;

--
-- Constraints for table `building_assessment_tax`
--
ALTER TABLE `building_assessment_tax`
  ADD CONSTRAINT `fk_building_tax_building` FOREIGN KEY (`building_id`) REFERENCES `building` (`building_id`) ON DELETE CASCADE;

--
-- Constraints for table `land`
--
ALTER TABLE `land`
  ADD CONSTRAINT `fk_land_application` FOREIGN KEY (`application_id`) REFERENCES `rpt_applications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `land_assessment_tax`
--
ALTER TABLE `land_assessment_tax`
  ADD CONSTRAINT `fk_land_tax_land` FOREIGN KEY (`land_id`) REFERENCES `land` (`land_id`) ON DELETE CASCADE;

--
-- Constraints for table `quarterly`
--
ALTER TABLE `quarterly`
  ADD CONSTRAINT `fk_quarterly_land_tax` FOREIGN KEY (`land_tax_id`) REFERENCES `land_assessment_tax` (`land_tax_id`) ON DELETE CASCADE;

--
-- Constraints for table `rpt_assessment_schedule`
--
ALTER TABLE `rpt_assessment_schedule`
  ADD CONSTRAINT `rpt_assessment_schedule_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `rpt_applications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rpt_documents`
--
ALTER TABLE `rpt_documents`
  ADD CONSTRAINT `rpt_documents_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `rpt_applications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `total_tax`
--
ALTER TABLE `total_tax`
  ADD CONSTRAINT `fk_total_tax_build_tax` FOREIGN KEY (`build_tax_id`) REFERENCES `building_assessment_tax` (`build_tax_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

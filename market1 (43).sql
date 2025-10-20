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
-- Database: `market1`
--

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `stall_id` int(11) NOT NULL,
  `application_type` enum('new','renewal','transfer') NOT NULL DEFAULT 'new',
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
  `market_name` varchar(255) NOT NULL,
  `market_section` varchar(255) NOT NULL,
  `stall_number` varchar(100) NOT NULL,
  `business_name` varchar(255) NOT NULL,
  `certification_agree` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('pending','approved','rejected','cancelled','payment_phase','paid','documents_submitted','expired') NOT NULL DEFAULT 'pending',
  `application_date` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`id`, `user_id`, `stall_id`, `application_type`, `first_name`, `middle_name`, `last_name`, `gender`, `date_of_birth`, `civil_status`, `house_number`, `street`, `barangay`, `city`, `zip_code`, `contact_number`, `email`, `market_name`, `market_section`, `stall_number`, `business_name`, `certification_agree`, `status`, `application_date`, `updated_at`) VALUES
(25, 2, 26, 'new', 'Ivan ', 'Dolera', 'Anorico', 'Male', '2002-12-16', 'Single', '168', 'Lydia', 'Santa Monica', 'Quezon City', '1117', '09950281131', 'van@gmail.com', 'Nitang', 'Bakery Section', 'Stall 3', 'Foody', 1, 'paid', '2025-10-18 19:35:05', '2025-10-18 23:46:11'),
(27, 1, 23, 'new', 'Ivan', 'Dolera', 'Anorico', 'Male', '2002-12-16', 'Single', '168', 'Lydia', 'Santa Monica', 'Quezon City', '1117', '09950281131', 'van@gmail.com', 'Quezon City', 'Bakery Section', 'Stall 2', 'Siomai', 1, 'approved', '2025-10-19 00:33:00', '2025-10-19 02:12:01'),
(28, 10, 24, 'new', 'Ivan', 'Dolera', 'Anorico', 'Male', '2002-12-16', 'Single', '168', 'Lydia', 'Santa Monica', 'Quezon City', '1117', '09950281131', 'qwe@gmail.com', 'Quezon City', 'Bakery Section', 'Stall 3', 'Foody', 1, 'approved', '2025-10-20 13:50:18', '2025-10-20 13:54:29'),
(29, 12, 25, 'new', 'Luan', 'DOLERA', 'Anorico', 'Male', '2002-12-16', 'Single', '168', 'Lyida', 'Santa Barangay', 'Quezon City', '1117', '09950281131', '111@gmail.com', 'Quezon City', 'Bakery Section', 'Stall 4', 'Siomai', 1, 'approved', '2025-10-20 15:51:23', '2025-10-20 15:53:17');

-- --------------------------------------------------------

--
-- Table structure for table `application_fee`
--

CREATE TABLE `application_fee` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `application_fee` decimal(10,2) NOT NULL DEFAULT 100.00,
  `security_bond` decimal(10,2) NOT NULL DEFAULT 10000.00,
  `stall_rights_fee` decimal(10,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_date` datetime DEFAULT NULL,
  `status` enum('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
  `reference_number` varchar(100) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `verification_code` varchar(6) DEFAULT NULL,
  `verification_attempts` int(11) DEFAULT 0,
  `verified_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `application_fee`
--

INSERT INTO `application_fee` (`id`, `application_id`, `application_fee`, `security_bond`, `stall_rights_fee`, `total_amount`, `payment_date`, `status`, `reference_number`, `payment_method`, `phone_number`, `email`, `verification_code`, `verification_attempts`, `verified_at`, `expires_at`, `created_at`, `updated_at`) VALUES
(24, 25, 100.00, 10000.00, 5000.00, 15100.00, '2025-10-18 23:46:11', 'paid', 'REF-20251018-3911', 'gcash', '09950281131', 'van@gmail.com', '628529', 0, '2025-10-18 23:46:11', '2025-10-18 17:56:04', '2025-10-18 21:09:39', '2025-10-18 23:46:11'),
(27, 27, 100.00, 10000.00, 5000.00, 15100.00, '2025-10-19 00:35:53', 'paid', 'REF-20251018-8144', 'gcash', '09950281131', 'van@gmail.com', '933984', 0, '2025-10-19 00:35:53', '2025-10-18 18:45:46', '2025-10-19 00:33:29', '2025-10-19 00:35:53'),
(28, 28, 100.00, 10000.00, 5000.00, 15100.00, '2025-10-20 13:52:55', 'paid', 'REF-20251020-0931', 'gcash', '09950281131', 'van@gmail.com', '505518', 0, '2025-10-20 13:52:55', '2025-10-20 08:02:43', '2025-10-20 13:52:11', '2025-10-20 13:52:55'),
(29, 29, 100.00, 10000.00, 5000.00, 15100.00, '2025-10-20 15:52:16', 'paid', 'REF-20251020-3128', 'gcash', '09950281131', 'van@gmail.com', '702301', 0, '2025-10-20 15:52:16', '2025-10-20 10:02:03', '2025-10-20 15:51:35', '2025-10-20 15:52:16');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `document_type` enum('barangay_certificate','id_picture','lease_contract','stall_rights_certificate','business_permit') NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) NOT NULL,
  `file_extension` varchar(10) NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`id`, `application_id`, `document_type`, `file_name`, `file_path`, `file_size`, `file_extension`, `uploaded_at`) VALUES
(62, 25, 'barangay_certificate', '1.PNG', 'uploads/applications/barangay_cert_2_1760787305.png', 24965, 'png', '2025-10-18 19:35:05'),
(63, 25, 'id_picture', '1.PNG', 'uploads/applications/id_picture_2_1760787305.png', 24965, 'png', '2025-10-18 19:35:05'),
(66, 27, 'barangay_certificate', '1.PNG', 'uploads/applications/barangay_cert_1_1760805180.png', 24965, 'png', '2025-10-19 00:33:00'),
(67, 27, 'id_picture', '1.PNG', 'uploads/applications/id_picture_1_1760805180.png', 24965, 'png', '2025-10-19 00:33:00'),
(68, 27, 'lease_contract', '2.PNG', 'uploads/applications/lease_contract_27_1760809535.png', 19348, 'png', '2025-10-19 01:45:35'),
(69, 27, 'business_permit', 'Business.png', 'uploads/applications/business_permit_27_1760809554.png', 88715, 'png', '2025-10-19 01:45:54'),
(70, 28, 'barangay_certificate', 'barangay_cert_1_1760803399.png', 'uploads/applications/barangay_cert_10_1760939418.png', 24965, 'png', '2025-10-20 13:50:18'),
(71, 28, 'id_picture', 'barangay_cert_1_1760803399.png', 'uploads/applications/id_picture_10_1760939418.png', 24965, 'png', '2025-10-20 13:50:18'),
(72, 28, 'lease_contract', 'Lease_of_Contract (3).png', 'uploads/applications/lease_contract_28_1760939633.png', 35770, 'png', '2025-10-20 13:53:53'),
(73, 28, 'business_permit', 'Lease_of_Contract (3).png', 'uploads/applications/business_permit_28_1760939649.png', 35770, 'png', '2025-10-20 13:54:09'),
(74, 29, 'barangay_certificate', '1.PNG', 'uploads/applications/barangay_cert_12_1760946683.png', 24965, 'png', '2025-10-20 15:51:23'),
(75, 29, 'id_picture', '2.PNG', 'uploads/applications/id_picture_12_1760946683.png', 19348, 'png', '2025-10-20 15:51:23'),
(76, 29, 'lease_contract', '2.PNG', 'uploads/applications/lease_contract_29_1760946770.png', 19348, 'png', '2025-10-20 15:52:50'),
(77, 29, 'business_permit', 'Business.png', 'uploads/applications/business_permit_29_1760946776.png', 88715, 'png', '2025-10-20 15:52:56');

-- --------------------------------------------------------

--
-- Table structure for table `lease_contracts`
--

CREATE TABLE `lease_contracts` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `renter_id` varchar(20) NOT NULL,
  `contract_number` varchar(50) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `monthly_rent` decimal(10,2) NOT NULL,
  `status` enum('active','expired') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lease_contracts`
--

INSERT INTO `lease_contracts` (`id`, `application_id`, `renter_id`, `contract_number`, `start_date`, `end_date`, `monthly_rent`, `status`, `created_at`) VALUES
(23, 25, 'R250025', 'CNTR20250025', '2025-10-18', '2026-10-18', 5000.00, 'active', '2025-10-18 23:46:11'),
(25, 27, 'R250026', 'CNTR20250026', '2025-10-19', '2026-10-19', 1500.00, 'active', '2025-10-19 00:33:29'),
(26, 28, 'R250027', 'CNTR20250027', '2025-10-20', '2026-10-20', 5000.00, 'active', '2025-10-20 13:52:11'),
(27, 29, 'R250028', 'CNTR20250028', '2025-10-20', '2026-10-20', 5000.00, 'active', '2025-10-20 15:51:35');

-- --------------------------------------------------------

--
-- Table structure for table `maps`
--

CREATE TABLE `maps` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maps`
--

INSERT INTO `maps` (`id`, `name`, `file_path`, `created_at`, `updated_at`) VALUES
(19, 'Nitang', 'uploads/market/maps/map_1760179714_523c4ed0b4.png', '2025-10-16 09:42:49', '2025-10-16 09:42:49'),
(20, 'Quezon City', 'uploads/market/maps/map_1760338865_70a2208a46.png', '2025-10-16 09:42:49', '2025-10-16 09:42:49'),
(21, 'Unnamed Map', 'uploads/market/maps/map_1760938367_be9c941264.png', '2025-10-20 13:32:47', '2025-10-20 13:32:47');

-- --------------------------------------------------------

--
-- Table structure for table `market_clearances`
--

CREATE TABLE `market_clearances` (
  `id` int(11) NOT NULL,
  `clearance_id` varchar(20) NOT NULL,
  `renter_id` varchar(20) NOT NULL,
  `application_id` int(11) NOT NULL,
  `payment_reference` varchar(100) NOT NULL,
  `status` enum('issued','used','expired') NOT NULL DEFAULT 'issued',
  `issued_date` date NOT NULL,
  `used_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `monthly_payments`
--

CREATE TABLE `monthly_payments` (
  `id` int(11) NOT NULL,
  `renter_id` varchar(20) NOT NULL,
  `month_year` varchar(7) NOT NULL,
  `due_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','paid','overdue','cancelled') NOT NULL DEFAULT 'pending',
  `paid_date` datetime DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `verification_code` varchar(6) DEFAULT NULL,
  `verification_attempts` int(11) DEFAULT 0,
  `verified_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `late_fee` decimal(10,2) DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `monthly_payments`
--

INSERT INTO `monthly_payments` (`id`, `renter_id`, `month_year`, `due_date`, `amount`, `status`, `paid_date`, `payment_method`, `phone_number`, `email`, `verification_code`, `verification_attempts`, `verified_at`, `expires_at`, `reference_number`, `late_fee`, `created_at`, `updated_at`) VALUES
(22, 'R250026', '2025-10', '2025-10-05', 677.42, 'paid', '2025-10-19 04:53:01', 'gcash', '09950281131', 'van@gmail.com', NULL, 0, NULL, NULL, 'RENT-20251018-225301-7923', 0.00, '2025-10-19 02:12:01', '2025-10-19 04:53:01'),
(23, 'R250026', '2025-11', '2025-11-05', 1500.00, 'paid', '2025-10-19 04:55:45', 'gcash', '09950281131', 'van@gmail.com', NULL, 0, NULL, NULL, 'RENT-20251018-225545-2150', 0.00, '2025-10-19 02:12:01', '2025-10-19 04:55:45'),
(24, 'R250026', '2025-12', '2025-12-05', 1500.00, 'paid', '2025-10-20 15:17:05', 'gcash', '09950281131', 'van@gmail.com', NULL, 0, NULL, NULL, 'RENT-20251020-091705-1417', 0.00, '2025-10-19 02:12:01', '2025-10-20 15:17:05'),
(25, 'R250027', '2025-10', '2025-10-05', 1935.48, 'paid', '2025-10-20 13:55:02', 'gcash', '09950281131', 'van@gmail.com', NULL, 0, NULL, NULL, 'RENT-20251020-075502-2224', 0.00, '2025-10-20 13:54:29', '2025-10-20 13:55:02'),
(26, 'R250027', '2025-11', '2025-11-05', 5000.00, 'pending', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 0.00, '2025-10-20 13:54:29', '2025-10-20 13:54:29'),
(27, 'R250027', '2025-12', '2025-12-05', 5000.00, 'pending', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 0.00, '2025-10-20 13:54:29', '2025-10-20 13:54:29'),
(28, 'R250028', '2025-10', '2025-10-05', 1935.48, 'pending', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 0.00, '2025-10-20 15:53:17', '2025-10-20 15:53:17'),
(29, 'R250028', '2025-11', '2025-11-05', 5000.00, 'pending', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 0.00, '2025-10-20 15:53:17', '2025-10-20 15:53:17'),
(30, 'R250028', '2025-12', '2025-12-05', 5000.00, 'pending', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 0.00, '2025-10-20 15:53:17', '2025-10-20 15:53:17');

-- --------------------------------------------------------

--
-- Table structure for table `renters`
--

CREATE TABLE `renters` (
  `id` int(11) NOT NULL,
  `renter_id` varchar(20) NOT NULL,
  `application_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `stall_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `business_name` varchar(255) NOT NULL,
  `market_name` varchar(255) NOT NULL,
  `stall_number` varchar(100) NOT NULL,
  `section_name` varchar(255) NOT NULL,
  `class_name` varchar(10) NOT NULL,
  `monthly_rent` decimal(10,2) NOT NULL,
  `stall_rights_fee` decimal(10,2) NOT NULL,
  `security_bond` decimal(10,2) NOT NULL,
  `status` enum('active','inactive','suspended','terminated','expired') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `renters`
--

INSERT INTO `renters` (`id`, `renter_id`, `application_id`, `user_id`, `stall_id`, `first_name`, `middle_name`, `last_name`, `contact_number`, `email`, `business_name`, `market_name`, `stall_number`, `section_name`, `class_name`, `monthly_rent`, `stall_rights_fee`, `security_bond`, `status`, `created_at`, `updated_at`) VALUES
(40, 'R250025', 25, 2, 26, 'Ivan ', 'Dolera', 'Anorico', '09950281131', 'van@gmail.com', 'Foody', 'Nitang', 'Stall 3', 'Bakery Section', 'C', 5000.00, 5000.00, 10000.00, 'active', '2025-10-18 23:46:11', '2025-10-18 23:46:11'),
(42, 'R250026', 27, 1, 23, 'Ivan', 'Dolera', 'Anorico', '09950281131', 'van@gmail.com', 'Siomai', 'Quezon City', 'Stall 2', 'Bakery Section', 'C', 1500.00, 5000.00, 10000.00, 'active', '2025-10-19 00:33:29', '2025-10-19 00:33:29'),
(43, 'R250027', 28, 10, 24, 'Ivan', 'Dolera', 'Anorico', '09950281131', 'qwe@gmail.com', 'Foody', 'Quezon City', 'Stall 3', 'Bakery Section', 'C', 5000.00, 5000.00, 10000.00, 'active', '2025-10-20 13:52:11', '2025-10-20 13:52:11'),
(44, 'R250028', 29, 12, 25, 'Luan', 'DOLERA', 'Anorico', '09950281131', '111@gmail.com', 'Siomai', 'Quezon City', 'Stall 4', 'Bakery Section', 'C', 5000.00, 5000.00, 10000.00, 'active', '2025-10-20 15:51:35', '2025-10-20 15:51:35');

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `name`, `created_at`) VALUES
(1, 'Fruits Section', '2025-10-16 09:42:49'),
(2, 'Vegetables Section', '2025-10-16 09:42:49'),
(3, 'Meat Section', '2025-10-16 09:42:49'),
(4, 'Fish Section', '2025-10-16 09:42:49'),
(5, 'Seafood Section', '2025-10-16 09:42:49'),
(6, 'Poultry Section', '2025-10-16 09:42:49'),
(7, 'Dry Goods', '2025-10-16 09:42:49'),
(8, 'Groceries', '2025-10-16 09:42:49'),
(9, 'Clothing Section', '2025-10-16 09:42:49'),
(10, 'Electronics Section', '2025-10-16 09:42:49'),
(11, 'Footwear Section', '2025-10-16 09:42:49'),
(12, 'Food Court', '2025-10-16 09:42:49'),
(13, 'Food Stalls', '2025-10-16 09:42:49'),
(14, 'Bakery Section', '2025-10-16 09:42:49'),
(15, 'Dairy Section', '2025-10-16 09:42:49'),
(16, 'Beverages Section', '2025-10-16 09:42:49'),
(17, 'Services Area', '2025-10-16 09:42:49'),
(18, 'General Merchandise', '2025-10-16 09:42:49'),
(19, 'Hardware Section', '2025-10-16 09:42:49'),
(20, 'Furniture Section', '2025-10-16 09:42:49'),
(21, 'Beauty Products', '2025-10-16 09:42:49'),
(22, 'Pharmacy Section', '2025-10-16 09:42:49'),
(23, 'Books & Stationery', '2025-10-16 09:42:49'),
(24, 'Toys Section', '2025-10-16 09:42:49'),
(25, 'Sports Goods', '2025-10-16 09:42:49');

-- --------------------------------------------------------

--
-- Table structure for table `stalls`
--

CREATE TABLE `stalls` (
  `id` int(11) NOT NULL,
  `map_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `pos_x` int(11) NOT NULL,
  `pos_y` int(11) NOT NULL,
  `height` decimal(10,2) NOT NULL DEFAULT 0.00,
  `length` decimal(10,2) NOT NULL DEFAULT 0.00,
  `width` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('available','occupied','reserved','maintenance') NOT NULL DEFAULT 'available',
  `class_id` int(11) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stalls`
--

INSERT INTO `stalls` (`id`, `map_id`, `name`, `pos_x`, `pos_y`, `height`, `length`, `width`, `price`, `status`, `class_id`, `section_id`, `created_at`, `updated_at`) VALUES
(20, 19, 'Stall 1', 116, 45, 100.00, 20.00, 20.00, 1000.00, 'maintenance', 3, 14, '2025-10-16 09:42:49', '2025-10-18 18:56:19'),
(21, 20, 'Stall 1', 114, 45, 100.00, 100.00, 100.00, 1200.00, 'occupied', 3, 23, '2025-10-16 09:42:49', '2025-10-19 00:28:48'),
(22, 19, 'Stall 2', 116, 118, 100.00, 100.00, 100.00, 100.00, 'reserved', 3, 21, '2025-10-16 09:42:49', '2025-10-18 19:32:19'),
(23, 20, 'Stall 2', 115, 117, 100.00, 100.00, 100.00, 1500.00, 'occupied', 3, 14, '2025-10-16 09:42:49', '2025-10-19 00:35:53'),
(24, 20, 'Stall 3', 115, 184, 100.00, 100.00, 100.00, 5000.00, 'occupied', 3, 14, '2025-10-17 00:15:05', '2025-10-20 13:52:55'),
(25, 20, 'Stall 4', 116, 251, 11.00, 11.00, 11.00, 5000.00, 'occupied', 3, 14, '2025-10-17 00:26:16', '2025-10-20 15:52:16'),
(26, 19, 'Stall 3', 115, 186, 100.00, 100.00, 100.00, 5000.00, 'occupied', 3, 14, '2025-10-18 15:26:13', '2025-10-18 23:46:11'),
(27, 21, 'Stall 1', 114, 45, 100.00, 100.00, 100.00, 5000.00, 'available', 3, 21, '2025-10-20 13:32:47', '2025-10-20 13:32:47');

-- --------------------------------------------------------

--
-- Table structure for table `stall_rights`
--

CREATE TABLE `stall_rights` (
  `class_id` int(11) NOT NULL,
  `class_name` varchar(10) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stall_rights`
--

INSERT INTO `stall_rights` (`class_id`, `class_name`, `price`, `description`, `created_at`) VALUES
(1, 'A', 15000.00, 'Premium Location - High traffic area', '2025-10-16 09:42:48'),
(2, 'B', 10000.00, 'Standard Location - Medium traffic area', '2025-10-16 09:42:48'),
(3, 'C', 5000.00, 'Economy Location - Low traffic area', '2025-10-16 09:42:48');

-- --------------------------------------------------------

--
-- Table structure for table `stall_rights_issued`
--

CREATE TABLE `stall_rights_issued` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `renter_id` varchar(20) NOT NULL,
  `certificate_number` varchar(50) DEFAULT NULL,
  `class_id` int(11) NOT NULL,
  `issue_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `status` enum('active','expired') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stall_rights_issued`
--

INSERT INTO `stall_rights_issued` (`id`, `application_id`, `renter_id`, `certificate_number`, `class_id`, `issue_date`, `expiry_date`, `status`, `created_at`) VALUES
(21, 25, 'R250025', 'SRC20250025', 3, '2025-10-18', '2026-10-18', 'active', '2025-10-18 23:46:11'),
(23, 27, 'R250026', 'SRC20250026', 3, '2025-10-19', '2026-10-19', 'active', '2025-10-19 00:33:29'),
(24, 28, 'R250027', 'SRC20250027', 3, '2025-10-20', '2026-10-20', 'active', '2025-10-20 13:52:11'),
(25, 29, 'R250028', 'SRC20250028', 3, '2025-10-20', '2026-10-20', 'active', '2025-10-20 15:51:35');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_applications_stall` (`stall_id`);

--
-- Indexes for table `application_fee`
--
ALTER TABLE `application_fee`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_application_fee_application` (`application_id`),
  ADD KEY `idx_verification_code` (`verification_code`),
  ADD KEY `idx_phone_number` (`phone_number`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_documents_application` (`application_id`),
  ADD KEY `idx_document_type` (`document_type`);

--
-- Indexes for table `lease_contracts`
--
ALTER TABLE `lease_contracts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `contract_number` (`contract_number`),
  ADD KEY `application_id` (`application_id`),
  ADD KEY `renter_id` (`renter_id`);

--
-- Indexes for table `maps`
--
ALTER TABLE `maps`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `market_clearances`
--
ALTER TABLE `market_clearances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_clearance_id` (`clearance_id`),
  ADD KEY `fk_clearance_renter` (`renter_id`),
  ADD KEY `fk_clearance_application` (`application_id`);

--
-- Indexes for table `monthly_payments`
--
ALTER TABLE `monthly_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_renter_month` (`renter_id`,`month_year`),
  ADD KEY `idx_due_date` (`due_date`),
  ADD KEY `idx_payment_status` (`status`),
  ADD KEY `idx_verification_code` (`verification_code`),
  ADD KEY `idx_phone_number` (`phone_number`);

--
-- Indexes for table `renters`
--
ALTER TABLE `renters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_renter_id` (`renter_id`),
  ADD UNIQUE KEY `unique_application` (`application_id`),
  ADD UNIQUE KEY `unique_stall` (`stall_id`),
  ADD KEY `fk_renters_user` (`user_id`),
  ADD KEY `idx_renters_status` (`status`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_name` (`name`);

--
-- Indexes for table `stalls`
--
ALTER TABLE `stalls`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_stalls_map` (`map_id`),
  ADD KEY `fk_stalls_class` (`class_id`),
  ADD KEY `fk_stalls_section` (`section_id`);

--
-- Indexes for table `stall_rights`
--
ALTER TABLE `stall_rights`
  ADD PRIMARY KEY (`class_id`),
  ADD UNIQUE KEY `unique_class_name` (`class_name`);

--
-- Indexes for table `stall_rights_issued`
--
ALTER TABLE `stall_rights_issued`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `certificate_number` (`certificate_number`),
  ADD KEY `application_id` (`application_id`),
  ADD KEY `renter_id` (`renter_id`),
  ADD KEY `class_id` (`class_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `application_fee`
--
ALTER TABLE `application_fee`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;

--
-- AUTO_INCREMENT for table `lease_contracts`
--
ALTER TABLE `lease_contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `maps`
--
ALTER TABLE `maps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `market_clearances`
--
ALTER TABLE `market_clearances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `monthly_payments`
--
ALTER TABLE `monthly_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `renters`
--
ALTER TABLE `renters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `stalls`
--
ALTER TABLE `stalls`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `stall_rights`
--
ALTER TABLE `stall_rights`
  MODIFY `class_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `stall_rights_issued`
--
ALTER TABLE `stall_rights_issued`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `fk_applications_stall` FOREIGN KEY (`stall_id`) REFERENCES `stalls` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `application_fee`
--
ALTER TABLE `application_fee`
  ADD CONSTRAINT `fk_application_fee_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `fk_documents_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lease_contracts`
--
ALTER TABLE `lease_contracts`
  ADD CONSTRAINT `lease_contracts_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`),
  ADD CONSTRAINT `lease_contracts_ibfk_2` FOREIGN KEY (`renter_id`) REFERENCES `renters` (`renter_id`);

--
-- Constraints for table `market_clearances`
--
ALTER TABLE `market_clearances`
  ADD CONSTRAINT `fk_clearance_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`),
  ADD CONSTRAINT `fk_clearance_renter` FOREIGN KEY (`renter_id`) REFERENCES `renters` (`renter_id`);

--
-- Constraints for table `monthly_payments`
--
ALTER TABLE `monthly_payments`
  ADD CONSTRAINT `fk_monthly_payments_renter` FOREIGN KEY (`renter_id`) REFERENCES `renters` (`renter_id`) ON DELETE CASCADE;

--
-- Constraints for table `renters`
--
ALTER TABLE `renters`
  ADD CONSTRAINT `fk_renters_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_renters_stall` FOREIGN KEY (`stall_id`) REFERENCES `stalls` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stalls`
--
ALTER TABLE `stalls`
  ADD CONSTRAINT `fk_stalls_class` FOREIGN KEY (`class_id`) REFERENCES `stall_rights` (`class_id`),
  ADD CONSTRAINT `fk_stalls_map` FOREIGN KEY (`map_id`) REFERENCES `maps` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_stalls_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stall_rights_issued`
--
ALTER TABLE `stall_rights_issued`
  ADD CONSTRAINT `stall_rights_issued_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`),
  ADD CONSTRAINT `stall_rights_issued_ibfk_2` FOREIGN KEY (`renter_id`) REFERENCES `renters` (`renter_id`),
  ADD CONSTRAINT `stall_rights_issued_ibfk_3` FOREIGN KEY (`class_id`) REFERENCES `stall_rights` (`class_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

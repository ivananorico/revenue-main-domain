-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost: 3307
-- Generation Time: Oct 20, 2025 at 10:31 AM
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
-- Database: `citizen_portal`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_token` varchar(255) DEFAULT NULL,
  `token_expires` datetime DEFAULT NULL,
  `verification_code` varchar(10) DEFAULT NULL,
  `verification_expiry` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password_hash`, `created_at`, `is_verified`, `verification_token`, `token_expires`, `verification_code`, `verification_expiry`) VALUES
(1, 'Ivan D.Anorico', 'ivananorico123@gmail.com', '$2y$10$1jaz3d/PG6jinaR5nk9o1uZ5.u4sGawHBh/h2IVL0L0EX4tXK3QGe', '2025-10-05 00:41:16', 0, NULL, NULL, NULL, NULL),
(2, 'Ivan D. Anorico', 'van@gmail.com', '$2y$10$FKN6A3yRb.MFK9CfkhIgk.hXUzxUzUjoLOTp83gQ9KD27oZmagnji', '2025-10-05 01:16:23', 0, NULL, NULL, NULL, NULL),
(10, 'Ivan Dolera Anorico', 'qwe@gmail.com', '$2y$10$FNh/RPORnWb4mVOz2udg/OfEldZiFoR4FDSz20WfTC.V4xeS.Ly0.', '2025-10-20 01:23:41', 0, NULL, NULL, NULL, NULL),
(11, 'Ivan Dolera Anorico', 'ads@gmail.com', '$2y$10$0gQpVhB1AajKbv2hMbhjqunRAnkLlyhclARTa7zEh.TaqYddiA6zW', '2025-10-20 01:40:32', 0, NULL, NULL, NULL, NULL),
(12, 'Ivan Dolera Anorico', '111@gmail.com', '$2y$10$/6QChQtv5gY7rL54LuN/N.PjOeYYtburVQX0FEY0vXBGAwFJhV9AS', '2025-10-20 07:26:22', 0, NULL, NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

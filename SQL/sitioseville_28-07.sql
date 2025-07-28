-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 28, 2025 at 07:39 AM
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
-- Database: `sitioseville`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_accounts`
--

CREATE TABLE `admin_accounts` (
  `admin_id` int(11) NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `date_of_birth` date NOT NULL,
  `age` int(11) NOT NULL,
  `sex` enum('Male','Female','Other') NOT NULL,
  `cellphone_number` varchar(20) DEFAULT NULL,
  `landline` varchar(20) DEFAULT NULL,
  `email_address` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `street_address` varchar(100) NOT NULL,
  `street_address_2` varchar(100) DEFAULT NULL,
  `city` varchar(50) NOT NULL,
  `state_province` varchar(50) NOT NULL,
  `barangay` varchar(50) NOT NULL,
  `postal_zip_code` varchar(20) NOT NULL,
  `role` enum('Board Member','Clubhouse Staff','Security Staff') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Active','Inactive') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_accounts`
--

INSERT INTO `admin_accounts` (`admin_id`, `profile_picture`, `first_name`, `middle_name`, `last_name`, `date_of_birth`, `age`, `sex`, `cellphone_number`, `landline`, `email_address`, `password`, `street_address`, `street_address_2`, `city`, `state_province`, `barangay`, `postal_zip_code`, `role`, `created_at`, `status`) VALUES
(1, NULL, 'Emma Charlotte', 'Duerre', 'Watson', '1990-06-15', 35, 'Female', NULL, NULL, 'itsemma35@gmail.com', '$2y$10$J3zjOiR/t5ptQ8Xt8Ghz9OCWYHi.B2Lc7X3r2toivyadhGJtEiP0G', 'P396-G6V, Novaliches', NULL, 'Quezon City', 'Metro Manila', 'North Fairview', '1121', 'Clubhouse Staff', '2025-07-27 08:27:40', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `residents`
--

CREATE TABLE `residents` (
  `resident_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `block_no` varchar(10) DEFAULT NULL,
  `lot_no` varchar(10) DEFAULT NULL,
  `outstanding_balance` decimal(10,2) DEFAULT 0.00,
  `date_registered` date DEFAULT curdate()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `residents`
--

INSERT INTO `residents` (`resident_id`, `first_name`, `middle_name`, `last_name`, `email`, `password`, `contact_number`, `address`, `block_no`, `lot_no`, `outstanding_balance`, `date_registered`) VALUES
(1, 'Juan', 'Dela', 'Cruz', 'juan.delacruz@example.com', '$2y$10$iYuLGxTiA1eCTMBIGp.m1.6a8D2a.sKZTQFWU66Wkv9K3oGuCDaUC', '09171234567', 'Blk 5 Lot 12, Phase 1, Sitio Seville', '5', '12', 0.00, '2025-07-02'),
(2, 'Maria', 'Reyes', 'Santos', 'maria.santos@example.com', '$2y$10$96X2AlaG44Uo6zSphlGhC.G.RCGc4J5oJK.V313.MK0X4ZSOVOM92', '09281234567', 'Blk 8 Lot 3, Phase 2, Sitio Seville', '8', '3', 150.00, '2025-07-02'),
(3, 'Carlos', 'Andres', 'Garcia', 'carlos.garcia@example.com', '$2y$10$WyDsi8dBHntP76YBIKGKMeVgT6M5Hi545XLtr18/1si27JwhbVlG.', '09391234567', 'Blk 2 Lot 7, Phase 1, Sitio Seville', '2', '7', 50.00, '2025-07-02');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `uid` varchar(50) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `type` enum('resident','visitor') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`uid`, `first_name`, `last_name`, `contact`, `address`, `type`) VALUES
('3870333924', 'Vida', 'Farala', '101923124135', 'QC', 'visitor'),
('3870960596', 'Christian', 'Grafil', '09477007300', 'Blk 23 Lot 42, Glory St.', 'visitor'),
('3871328292', 'Ralph', 'Cadiz', '091237485', 'Quezon City', 'resident'),
('3871375252', 'Maoi', 'Madrid', '091238524253', 'QC', 'visitor'),
('3871379892', 'Christian', 'Grafil', '09477007300', 'Bulacan', 'resident');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_accounts`
--
ALTER TABLE `admin_accounts`
  ADD PRIMARY KEY (`admin_id`);

--
-- Indexes for table `residents`
--
ALTER TABLE `residents`
  ADD PRIMARY KEY (`resident_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`uid`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_accounts`
--
ALTER TABLE `admin_accounts`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `residents`
--
ALTER TABLE `residents`
  MODIFY `resident_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

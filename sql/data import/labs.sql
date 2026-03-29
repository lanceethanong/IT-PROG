-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Mar 29, 2026 at 12:14 PM
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
-- Database: `lab_res_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `labs`
--

CREATE TABLE `labs` (
  `id` varchar(24) NOT NULL,
  `class_name` varchar(191) NOT NULL,
  `number` int(11) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `labs`
--

INSERT INTO `labs` (`id`, `class_name`, `number`, `created_at`, `updated_at`) VALUES
('686010751ac5fe0c7ec6270e', 'CCPROG3', 1, '2026-03-14 10:06:27', '2026-03-14 10:06:27'),
('686011c9004cf3a3f2247d58', 'CCAPDEV', 2, '2026-03-14 10:06:27', '2026-03-14 10:06:27'),
('686011c9004cf3a3f2247d59', 'STCHUIX', 3, '2026-03-14 10:06:27', '2026-03-14 10:06:27'),
('686011c9004cf3a3f2247d5a', 'ITNET04', 4, '2026-03-14 10:06:27', '2026-03-14 10:06:27'),
('686011c9004cf3a3f2247d5b', 'CSARCH2', 5, '2026-03-14 10:06:27', '2026-03-14 10:06:27');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `labs`
--
ALTER TABLE `labs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `number` (`number`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

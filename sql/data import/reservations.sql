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
-- Table structure for table `reservations`
--


--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `time_start`, `time_end`, `user_id`, `lab_id`, `date`, `anonymity`, `status`, `created_at`, `updated_at`) VALUES
('293b44309a2b82c77adb28d0', '12:00 PM', '01:00 PM', '8a9240b83eb9ea77a26f88b9', '686010751ac5fe0c7ec6270e', '2026-03-13', 0, 'Scheduled', '2026-03-14 10:08:51', '2026-03-14 10:08:51'),
('2d1898d13fb9052c2b402f14', '11:00 AM', '12:00 PM', '688f280a5b054f0a8eb23897', '686011c9004cf3a3f2247d58', '2026-03-15', 0, 'Scheduled', '2026-03-14 11:21:01', '2026-03-14 11:21:01'),
('688f29c85b054f0a8eb238e4', '11:00 AM', '12:00 PM', '688f280a5b054f0a8eb23897', '686011c9004cf3a3f2247d59', '2025-08-04', 0, 'Scheduled', '2025-08-03 09:20:08', '2025-08-03 09:20:08'),
('688f2a015b054f0a8eb2391b', '11:00 AM', '12:30 PM', '688c1ee069ed22c51f004ff5', '686011c9004cf3a3f2247d5b', '2025-08-06', 0, 'Scheduled', '2025-08-03 09:21:05', '2025-08-03 09:21:05'),
('688f2a465b054f0a8eb23961', '11:30 AM', '02:00 PM', '688f26e15b054f0a8eb23881', '686011c9004cf3a3f2247d58', '2025-08-08', 0, 'Scheduled', '2025-08-03 09:22:14', '2025-08-03 09:22:14'),
('688f2a6d5b054f0a8eb2399c', '11:00 AM', '01:00 PM', '688f26e15b054f0a8eb23881', '686010751ac5fe0c7ec6270e', '2025-08-04', 0, 'Scheduled', '2025-08-03 09:22:53', '2025-08-03 09:22:53'),
('688f2a8a5b054f0a8eb239c7', '11:00 AM', '11:30 AM', '688f24515b054f0a8eb23875', '686011c9004cf3a3f2247d5a', '2025-08-06', 0, 'Scheduled', '2025-08-03 09:23:22', '2025-08-03 09:23:22'),
('94ee6c9ca0710fef3afc02e4', '10:30 AM', '12:30 PM', '8a9240b83eb9ea77a26f88b9', '686011c9004cf3a3f2247d58', '2026-03-16', 0, 'Scheduled', '2026-03-14 11:08:03', '2026-03-14 11:08:03'),
('9ccadb1f47a39edfa6ee3cae', '11:00 AM', '01:00 PM', '688f280a5b054f0a8eb23897', '686011c9004cf3a3f2247d58', '2026-03-17', 0, 'Scheduled', '2026-03-14 11:21:22', '2026-03-14 11:21:22'),
('b64ad2407732e0ab84b5ab92', '12:00 PM', '02:00 PM', '688f26e15b054f0a8eb23881', '686010751ac5fe0c7ec6270e', '2026-03-17', 0, 'Scheduled', '2026-03-17 09:27:18', '2026-03-17 09:33:30');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_res_user` (`user_id`),
  ADD KEY `fk_res_lab` (`lab_id`);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `fk_res_lab` FOREIGN KEY (`lab_id`) REFERENCES `labs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_res_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

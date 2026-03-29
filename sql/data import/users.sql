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
-- Table structure for table `users`
--


--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `username`, `description`, `remember`, `password`, `picture`, `role`, `created_at`, `updated_at`) VALUES
('688c1c5c69ed22c51f004fa8', 'riane123@dlsu.edu.ph', 'Riane', '', 0, '$2b$10$eN4X915y.7quVYcOcw0VN.KS6zVJy59lPP88WMaJmY3XLeDHJ0F8q', 'picture.jpg', 'Admin', '2025-08-01 01:46:04', '2025-08-01 01:48:14'),
('688c1e2969ed22c51f004fe1', 'lance123@dlsu.edu.ph', 'lance123', '', 0, '$2b$10$IP9DhLj5YbHRJTvQM7tPu.ziusiZ0Dsu5D4vZf2H63Ileo5eK6tKu', 'picture.jpg', 'Lab Technician', '2025-08-01 01:53:45', '2025-08-01 01:53:45'),
('688c1ee069ed22c51f004ff5', 'hello12345@dlsu.edu.ph', 'Hello12345', '', 0, '$2b$10$Ns3cDs1aQRkzWL8f9Ikq4ew3N3uG2jXb0UKqyG3EBIfQ2w2rvIbTi', 'picture.jpg', 'Student', '2025-08-01 01:56:48', '2025-08-01 01:57:55'),
('688f24515b054f0a8eb23875', 'jenson555@dlsu.edu.ph', 'Jenson543', '', 0, '$2b$10$M69u4vbrv7MaWEen6BTtt.Z2yv7a0D1AwaU2jNhKnsmYReIjLE8.G', 'picture.jpg', 'Student', '2025-08-03 08:56:49', '2025-08-03 08:56:49'),
('688f26e15b054f0a8eb23881', 'adam789@dlsu.edu.ph', 'adam', '', 0, '$2b$10$Vm10u0LZlXRDLK/U/Y/YNe.bwbere48bmGxB/i4b3xMcqJ3JYwJgi', 'picture.jpg', 'Student', '2025-08-03 09:07:45', '2025-08-03 09:07:45'),
('688f27165b054f0a8eb2388b', 'apdev123@dlsu.edu.ph', 'APPDEV', '', 0, '$2b$10$GxY/tNr21mVdPaVhLduQG.ByjoUlk37h77AuB3R1x2owndsnh9a.i', 'picture.jpg', 'Student', '2025-08-03 09:08:38', '2025-08-03 09:08:38'),
('688f280a5b054f0a8eb23897', 'arch2345@dlsu.edu.ph', 'arch2', '', 0, '$2b$10$DNELYMGpiysgjXjNMuXds.Bnfef2/cU44xQhLBp7a4tYWizmp/QMS', 'picture.jpg', 'Student', '2025-08-03 09:12:42', '2025-08-03 09:12:42'),
('8a9240b83eb9ea77a26f88b9', 'pawstayph@dlsu.edu.ph', 'ITPROG', 'test edit', 0, '$2y$10$aWE2ZIizb62oJgHxJjzKQ.dbNx.u/n3DPBoNqPQcQOGqmWw3ehCFO', 'picture.jpg', 'Lab Technician', '2026-03-14 10:08:36', '2026-03-17 09:58:07');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

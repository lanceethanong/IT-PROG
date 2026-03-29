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
-- Table structure for table `seat_lists`
--

CREATE TABLE `seat_lists` (
  `id` varchar(24) NOT NULL,
  `reservation_id` varchar(24) NOT NULL,
  `row_num` int(11) NOT NULL,
  `col_num` int(11) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `seat_lists`
--

INSERT INTO `seat_lists` (`id`, `reservation_id`, `row_num`, `col_num`, `created_at`, `updated_at`) VALUES
('7540e48bdd7a80d5717445a4', 'b64ad2407732e0ab84b5ab92', 2, 2, '2026-03-17 09:33:30', '2026-03-17 09:33:30');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `seat_lists`
--
ALTER TABLE `seat_lists`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_seat_res` (`reservation_id`);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `seat_lists`
--
ALTER TABLE `seat_lists`
  ADD CONSTRAINT `fk_seat_res` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

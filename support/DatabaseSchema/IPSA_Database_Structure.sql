-- phpMyAdmin SQL Dump
-- version 4.8.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 29, 2018 at 11:05 PM
-- Server version: 10.1.33-MariaDB
-- PHP Version: 7.2.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bulk_spectra`
--
CREATE DATABASE IF NOT EXISTS `bulk_spectra` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
USE `bulk_spectra`;

-- --------------------------------------------------------

--
-- Table structure for table `identifications`
--

CREATE TABLE `identifications` (
  `scan_number` int(11) NOT NULL,
  `sequence` varchar(60) NOT NULL,
  `charge` int(11) NOT NULL,
  `mods` text NOT NULL,
  `timestamp` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `mods`
--

CREATE TABLE `mods` (
  `timestamp` bigint(20) NOT NULL,
  `name` text NOT NULL,
  `mass` double NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `spectra`
--

CREATE TABLE `spectra` (
  `timestamp` bigint(20) NOT NULL,
  `scan_number` int(11) NOT NULL,
  `mz` text NOT NULL,
  `intensity` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `identifications`
--
ALTER TABLE `identifications`
  ADD KEY `timestamp` (`timestamp`,`scan_number`) USING BTREE;

--
-- Indexes for table `spectra`
--
ALTER TABLE `spectra`
  ADD KEY `timestamp` (`timestamp`,`scan_number`) USING BTREE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

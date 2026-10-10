-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 07, 2026 at 09:13 AM
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
-- Database: `atgs_devlopment`
--

-- --------------------------------------------------------

--
-- Table structure for table `availability`
--

CREATE TABLE `availability` (
  `TEACHER_ID` int(11) NOT NULL,
  `SLOT_ID` int(11) NOT NULL,
  `WEEKDAY` enum('MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY') NOT NULL,
  `STATUS` enum('AVAILABLE','ALLOTED') DEFAULT 'AVAILABLE'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `availability`
--

INSERT INTO `availability` (`TEACHER_ID`, `SLOT_ID`, `WEEKDAY`, `STATUS`) VALUES
(4, 1, 'MONDAY', 'AVAILABLE'),
(4, 1, 'TUESDAY', 'AVAILABLE'),
(4, 1, 'WEDNESDAY', 'AVAILABLE'),
(4, 1, 'THURSDAY', 'AVAILABLE'),
(4, 1, 'FRIDAY', 'AVAILABLE'),
(4, 1, 'SATURDAY', 'AVAILABLE'),
(4, 3, 'MONDAY', 'AVAILABLE'),
(4, 3, 'TUESDAY', 'AVAILABLE'),
(4, 3, 'WEDNESDAY', 'AVAILABLE'),
(4, 3, 'THURSDAY', 'AVAILABLE'),
(4, 3, 'FRIDAY', 'AVAILABLE'),
(4, 3, 'SATURDAY', 'AVAILABLE'),
(4, 4, 'MONDAY', 'AVAILABLE'),
(4, 4, 'TUESDAY', 'AVAILABLE'),
(4, 4, 'WEDNESDAY', 'AVAILABLE'),
(4, 4, 'THURSDAY', 'AVAILABLE'),
(4, 4, 'FRIDAY', 'AVAILABLE'),
(4, 4, 'SATURDAY', 'AVAILABLE'),
(4, 5, 'MONDAY', 'AVAILABLE'),
(4, 5, 'TUESDAY', 'AVAILABLE'),
(4, 5, 'WEDNESDAY', 'AVAILABLE'),
(4, 5, 'THURSDAY', 'AVAILABLE'),
(4, 5, 'FRIDAY', 'AVAILABLE'),
(4, 5, 'SATURDAY', 'AVAILABLE'),
(4, 6, 'MONDAY', 'AVAILABLE'),
(4, 6, 'TUESDAY', 'AVAILABLE'),
(4, 6, 'WEDNESDAY', 'AVAILABLE'),
(4, 6, 'THURSDAY', 'AVAILABLE'),
(4, 6, 'FRIDAY', 'AVAILABLE'),
(4, 6, 'SATURDAY', 'AVAILABLE'),
(4, 7, 'MONDAY', 'AVAILABLE'),
(4, 7, 'TUESDAY', 'AVAILABLE'),
(4, 7, 'WEDNESDAY', 'AVAILABLE'),
(4, 7, 'THURSDAY', 'AVAILABLE'),
(4, 7, 'FRIDAY', 'AVAILABLE'),
(4, 7, 'SATURDAY', 'AVAILABLE'),
(4, 8, 'MONDAY', 'AVAILABLE'),
(4, 8, 'TUESDAY', 'AVAILABLE'),
(4, 8, 'WEDNESDAY', 'AVAILABLE'),
(4, 8, 'THURSDAY', 'AVAILABLE'),
(4, 8, 'FRIDAY', 'AVAILABLE'),
(4, 8, 'SATURDAY', 'AVAILABLE'),
(4, 9, 'MONDAY', 'AVAILABLE'),
(4, 9, 'TUESDAY', 'AVAILABLE'),
(4, 9, 'WEDNESDAY', 'AVAILABLE'),
(4, 9, 'THURSDAY', 'AVAILABLE'),
(4, 9, 'FRIDAY', 'AVAILABLE'),
(4, 9, 'SATURDAY', 'AVAILABLE'),
(5, 1, 'MONDAY', 'AVAILABLE'),
(5, 1, 'TUESDAY', 'AVAILABLE'),
(5, 1, 'WEDNESDAY', 'AVAILABLE'),
(5, 1, 'THURSDAY', 'AVAILABLE'),
(5, 1, 'FRIDAY', 'AVAILABLE'),
(5, 1, 'SATURDAY', 'AVAILABLE'),
(5, 3, 'MONDAY', 'AVAILABLE'),
(5, 3, 'TUESDAY', 'AVAILABLE'),
(5, 3, 'WEDNESDAY', 'AVAILABLE'),
(5, 3, 'THURSDAY', 'AVAILABLE'),
(5, 3, 'FRIDAY', 'AVAILABLE'),
(5, 3, 'SATURDAY', 'AVAILABLE'),
(5, 4, 'MONDAY', 'AVAILABLE'),
(5, 4, 'TUESDAY', 'AVAILABLE'),
(5, 4, 'WEDNESDAY', 'AVAILABLE'),
(5, 4, 'THURSDAY', 'AVAILABLE'),
(5, 4, 'FRIDAY', 'AVAILABLE'),
(5, 4, 'SATURDAY', 'AVAILABLE'),
(5, 5, 'MONDAY', 'AVAILABLE'),
(5, 5, 'TUESDAY', 'AVAILABLE'),
(5, 5, 'WEDNESDAY', 'AVAILABLE'),
(5, 5, 'THURSDAY', 'AVAILABLE'),
(5, 5, 'FRIDAY', 'AVAILABLE'),
(5, 5, 'SATURDAY', 'AVAILABLE'),
(5, 6, 'MONDAY', 'AVAILABLE'),
(5, 6, 'TUESDAY', 'AVAILABLE'),
(5, 6, 'WEDNESDAY', 'AVAILABLE'),
(5, 6, 'THURSDAY', 'AVAILABLE'),
(5, 6, 'FRIDAY', 'AVAILABLE'),
(5, 6, 'SATURDAY', 'AVAILABLE'),
(5, 7, 'MONDAY', 'AVAILABLE'),
(5, 7, 'TUESDAY', 'AVAILABLE'),
(5, 7, 'WEDNESDAY', 'AVAILABLE'),
(5, 7, 'THURSDAY', 'AVAILABLE'),
(5, 7, 'FRIDAY', 'AVAILABLE'),
(5, 7, 'SATURDAY', 'AVAILABLE'),
(5, 8, 'MONDAY', 'AVAILABLE'),
(5, 8, 'TUESDAY', 'AVAILABLE'),
(5, 8, 'WEDNESDAY', 'AVAILABLE'),
(5, 8, 'THURSDAY', 'AVAILABLE'),
(5, 8, 'FRIDAY', 'AVAILABLE'),
(5, 8, 'SATURDAY', 'AVAILABLE'),
(5, 9, 'MONDAY', 'AVAILABLE'),
(5, 9, 'TUESDAY', 'AVAILABLE'),
(5, 9, 'WEDNESDAY', 'AVAILABLE'),
(5, 9, 'THURSDAY', 'AVAILABLE'),
(5, 9, 'FRIDAY', 'AVAILABLE'),
(5, 9, 'SATURDAY', 'AVAILABLE'),
(6, 1, 'MONDAY', 'AVAILABLE'),
(6, 1, 'TUESDAY', 'AVAILABLE'),
(6, 1, 'WEDNESDAY', 'AVAILABLE'),
(6, 1, 'THURSDAY', 'AVAILABLE'),
(6, 1, 'FRIDAY', 'AVAILABLE'),
(6, 1, 'SATURDAY', 'AVAILABLE'),
(6, 3, 'MONDAY', 'AVAILABLE'),
(6, 3, 'TUESDAY', 'AVAILABLE'),
(6, 3, 'WEDNESDAY', 'AVAILABLE'),
(6, 3, 'THURSDAY', 'AVAILABLE'),
(6, 3, 'FRIDAY', 'AVAILABLE'),
(6, 3, 'SATURDAY', 'AVAILABLE'),
(6, 4, 'MONDAY', 'AVAILABLE'),
(6, 4, 'TUESDAY', 'AVAILABLE'),
(6, 4, 'WEDNESDAY', 'AVAILABLE'),
(6, 4, 'THURSDAY', 'AVAILABLE'),
(6, 4, 'FRIDAY', 'AVAILABLE'),
(6, 4, 'SATURDAY', 'AVAILABLE'),
(6, 5, 'MONDAY', 'AVAILABLE'),
(6, 5, 'TUESDAY', 'AVAILABLE'),
(6, 5, 'WEDNESDAY', 'AVAILABLE'),
(6, 5, 'THURSDAY', 'AVAILABLE'),
(6, 5, 'FRIDAY', 'AVAILABLE'),
(6, 5, 'SATURDAY', 'AVAILABLE'),
(6, 6, 'MONDAY', 'AVAILABLE'),
(6, 6, 'TUESDAY', 'AVAILABLE'),
(6, 6, 'WEDNESDAY', 'AVAILABLE'),
(6, 6, 'THURSDAY', 'AVAILABLE'),
(6, 6, 'FRIDAY', 'AVAILABLE'),
(6, 6, 'SATURDAY', 'AVAILABLE'),
(6, 7, 'MONDAY', 'AVAILABLE'),
(6, 7, 'TUESDAY', 'AVAILABLE'),
(6, 7, 'WEDNESDAY', 'AVAILABLE'),
(6, 7, 'THURSDAY', 'AVAILABLE'),
(6, 7, 'FRIDAY', 'AVAILABLE'),
(6, 7, 'SATURDAY', 'AVAILABLE'),
(6, 8, 'MONDAY', 'AVAILABLE'),
(6, 8, 'TUESDAY', 'AVAILABLE'),
(6, 8, 'WEDNESDAY', 'AVAILABLE'),
(6, 8, 'THURSDAY', 'AVAILABLE'),
(6, 8, 'FRIDAY', 'AVAILABLE'),
(6, 8, 'SATURDAY', 'AVAILABLE'),
(6, 9, 'MONDAY', 'AVAILABLE'),
(6, 9, 'TUESDAY', 'AVAILABLE'),
(6, 9, 'WEDNESDAY', 'AVAILABLE'),
(6, 9, 'THURSDAY', 'AVAILABLE'),
(6, 9, 'FRIDAY', 'AVAILABLE'),
(6, 9, 'SATURDAY', 'AVAILABLE'),
(7, 1, 'MONDAY', 'AVAILABLE'),
(7, 1, 'TUESDAY', 'AVAILABLE'),
(7, 1, 'WEDNESDAY', 'AVAILABLE'),
(7, 1, 'THURSDAY', 'AVAILABLE'),
(7, 1, 'FRIDAY', 'AVAILABLE'),
(7, 1, 'SATURDAY', 'AVAILABLE'),
(7, 3, 'MONDAY', 'AVAILABLE'),
(7, 3, 'TUESDAY', 'AVAILABLE'),
(7, 3, 'WEDNESDAY', 'AVAILABLE'),
(7, 3, 'THURSDAY', 'AVAILABLE'),
(7, 3, 'FRIDAY', 'AVAILABLE'),
(7, 3, 'SATURDAY', 'AVAILABLE'),
(7, 4, 'MONDAY', 'AVAILABLE'),
(7, 4, 'TUESDAY', 'AVAILABLE'),
(7, 4, 'WEDNESDAY', 'AVAILABLE'),
(7, 4, 'THURSDAY', 'AVAILABLE'),
(7, 4, 'FRIDAY', 'AVAILABLE'),
(7, 4, 'SATURDAY', 'AVAILABLE'),
(7, 5, 'MONDAY', 'AVAILABLE'),
(7, 5, 'TUESDAY', 'AVAILABLE'),
(7, 5, 'WEDNESDAY', 'AVAILABLE'),
(7, 5, 'THURSDAY', 'AVAILABLE'),
(7, 5, 'FRIDAY', 'AVAILABLE'),
(7, 5, 'SATURDAY', 'AVAILABLE'),
(7, 6, 'MONDAY', 'AVAILABLE'),
(7, 6, 'TUESDAY', 'AVAILABLE'),
(7, 6, 'WEDNESDAY', 'AVAILABLE'),
(7, 6, 'THURSDAY', 'AVAILABLE'),
(7, 6, 'FRIDAY', 'AVAILABLE'),
(7, 6, 'SATURDAY', 'AVAILABLE'),
(7, 7, 'MONDAY', 'AVAILABLE'),
(7, 7, 'TUESDAY', 'AVAILABLE'),
(7, 7, 'WEDNESDAY', 'AVAILABLE'),
(7, 7, 'THURSDAY', 'AVAILABLE'),
(7, 7, 'FRIDAY', 'AVAILABLE'),
(7, 7, 'SATURDAY', 'AVAILABLE'),
(7, 8, 'MONDAY', 'AVAILABLE'),
(7, 8, 'TUESDAY', 'AVAILABLE'),
(7, 8, 'WEDNESDAY', 'AVAILABLE'),
(7, 8, 'THURSDAY', 'AVAILABLE'),
(7, 8, 'FRIDAY', 'AVAILABLE'),
(7, 8, 'SATURDAY', 'AVAILABLE'),
(7, 9, 'MONDAY', 'AVAILABLE'),
(7, 9, 'TUESDAY', 'AVAILABLE'),
(7, 9, 'WEDNESDAY', 'AVAILABLE'),
(7, 9, 'THURSDAY', 'AVAILABLE'),
(7, 9, 'FRIDAY', 'AVAILABLE'),
(7, 9, 'SATURDAY', 'AVAILABLE'),
(8, 1, 'MONDAY', 'AVAILABLE'),
(8, 1, 'TUESDAY', 'AVAILABLE'),
(8, 1, 'WEDNESDAY', 'AVAILABLE'),
(8, 1, 'THURSDAY', 'AVAILABLE'),
(8, 1, 'FRIDAY', 'AVAILABLE'),
(8, 1, 'SATURDAY', 'AVAILABLE'),
(8, 3, 'MONDAY', 'AVAILABLE'),
(8, 3, 'TUESDAY', 'AVAILABLE'),
(8, 3, 'WEDNESDAY', 'AVAILABLE'),
(8, 3, 'THURSDAY', 'AVAILABLE'),
(8, 3, 'FRIDAY', 'AVAILABLE'),
(8, 3, 'SATURDAY', 'AVAILABLE'),
(8, 4, 'MONDAY', 'AVAILABLE'),
(8, 4, 'TUESDAY', 'AVAILABLE'),
(8, 4, 'WEDNESDAY', 'AVAILABLE'),
(8, 4, 'THURSDAY', 'AVAILABLE'),
(8, 4, 'FRIDAY', 'AVAILABLE'),
(8, 4, 'SATURDAY', 'AVAILABLE'),
(8, 5, 'MONDAY', 'AVAILABLE'),
(8, 5, 'TUESDAY', 'AVAILABLE'),
(8, 5, 'WEDNESDAY', 'AVAILABLE'),
(8, 5, 'THURSDAY', 'AVAILABLE'),
(8, 5, 'FRIDAY', 'AVAILABLE'),
(8, 5, 'SATURDAY', 'AVAILABLE'),
(8, 6, 'MONDAY', 'AVAILABLE'),
(8, 6, 'TUESDAY', 'AVAILABLE'),
(8, 6, 'WEDNESDAY', 'AVAILABLE'),
(8, 6, 'THURSDAY', 'AVAILABLE'),
(8, 6, 'FRIDAY', 'AVAILABLE'),
(8, 6, 'SATURDAY', 'AVAILABLE'),
(8, 7, 'MONDAY', 'AVAILABLE'),
(8, 7, 'TUESDAY', 'AVAILABLE'),
(8, 7, 'WEDNESDAY', 'AVAILABLE'),
(8, 7, 'THURSDAY', 'AVAILABLE'),
(8, 7, 'FRIDAY', 'AVAILABLE'),
(8, 7, 'SATURDAY', 'AVAILABLE'),
(8, 8, 'MONDAY', 'AVAILABLE'),
(8, 8, 'TUESDAY', 'AVAILABLE'),
(8, 8, 'WEDNESDAY', 'AVAILABLE'),
(8, 8, 'THURSDAY', 'AVAILABLE'),
(8, 8, 'FRIDAY', 'AVAILABLE'),
(8, 8, 'SATURDAY', 'AVAILABLE'),
(8, 9, 'MONDAY', 'AVAILABLE'),
(8, 9, 'TUESDAY', 'AVAILABLE'),
(8, 9, 'WEDNESDAY', 'AVAILABLE'),
(8, 9, 'THURSDAY', 'AVAILABLE'),
(8, 9, 'FRIDAY', 'AVAILABLE'),
(8, 9, 'SATURDAY', 'AVAILABLE'),
(9, 1, 'WEDNESDAY', 'AVAILABLE'),
(9, 1, 'THURSDAY', 'AVAILABLE'),
(9, 3, 'WEDNESDAY', 'AVAILABLE'),
(9, 3, 'THURSDAY', 'AVAILABLE'),
(9, 4, 'WEDNESDAY', 'AVAILABLE'),
(9, 4, 'THURSDAY', 'AVAILABLE'),
(9, 5, 'WEDNESDAY', 'AVAILABLE'),
(9, 5, 'THURSDAY', 'AVAILABLE'),
(9, 6, 'WEDNESDAY', 'AVAILABLE'),
(9, 6, 'THURSDAY', 'AVAILABLE'),
(9, 7, 'WEDNESDAY', 'AVAILABLE'),
(9, 7, 'THURSDAY', 'AVAILABLE'),
(9, 8, 'WEDNESDAY', 'AVAILABLE'),
(9, 8, 'THURSDAY', 'AVAILABLE'),
(9, 9, 'WEDNESDAY', 'AVAILABLE'),
(9, 9, 'THURSDAY', 'AVAILABLE'),
(9, 10, 'WEDNESDAY', 'AVAILABLE'),
(9, 10, 'THURSDAY', 'AVAILABLE'),
(9, 11, 'WEDNESDAY', 'AVAILABLE'),
(9, 11, 'THURSDAY', 'AVAILABLE'),
(9, 12, 'WEDNESDAY', 'AVAILABLE'),
(9, 12, 'THURSDAY', 'AVAILABLE');

-- --------------------------------------------------------

--
-- Table structure for table `classroom`
--

CREATE TABLE `classroom` (
  `CLASSROOM_ID` int(11) NOT NULL,
  `FLOOR_NUMBER` varchar(15) NOT NULL,
  `ROOM_NUMBER` varchar(15) NOT NULL,
  `CAPACITY` int(11) DEFAULT NULL,
  `START_TIME` time DEFAULT NULL,
  `END_TIME` time DEFAULT NULL,
  `CATEGORY` enum('LECTURE HALL','IT LAB','PHYSICS LAB','CHEMISTRY LAB','BIOLOGY LAB') DEFAULT 'LECTURE HALL'
) ;

--
-- Dumping data for table `classroom`
--

INSERT INTO `classroom` (`CLASSROOM_ID`, `FLOOR_NUMBER`, `ROOM_NUMBER`, `CAPACITY`, `START_TIME`, `END_TIME`, `CATEGORY`) VALUES
(1, 'First Floor', 'IT Lab 02', 60, '07:00:00', '15:00:00', 'IT LAB'),
(2, 'First Floor', 'IT Lab 01', 60, '07:00:00', '13:00:00', 'IT LAB'),
(3, 'First Floor', 'E-Leaning Lab', 60, '07:00:00', '13:00:00', 'IT LAB'),
(4, 'First Floor', '108', 60, '07:00:00', '15:00:00', 'LECTURE HALL'),
(5, 'Second Floor', '008', 60, '07:00:00', '13:00:00', 'LECTURE HALL'),
(6, 'Fourth Floor', '401', 60, '07:00:00', '13:00:00', 'LECTURE HALL');

-- --------------------------------------------------------

--
-- Table structure for table `consists`
--

CREATE TABLE `consists` (
  `YEAR_NUMBER` int(11) NOT NULL,
  `PROGRAMME_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `consists`
--

INSERT INTO `consists` (`YEAR_NUMBER`, `PROGRAMME_ID`) VALUES
(1, 2),
(1, 3);

-- --------------------------------------------------------

--
-- Table structure for table `course`
--

CREATE TABLE `course` (
  `COURSE_ID` int(11) NOT NULL,
  `OPTIONAL_ID` int(11) DEFAULT NULL,
  `YEAR_NUMBER` int(11) DEFAULT NULL,
  `PROGRAMME_ID` int(11) DEFAULT NULL,
  `TYPE` enum('LECTURE','IT PRACTICAL','PHYSICS PRACTICAL','BIOLOGY PRACTICAL','CHEMISTRY PRACTICAL') DEFAULT NULL,
  `SEMESTER` enum('EVEN','ODD') NOT NULL,
  `LONG_NAME` varchar(100) NOT NULL,
  `SHORT_NAME` varchar(20) NOT NULL,
  `WEEKLY_LECTURES` int(11) NOT NULL DEFAULT 0,
  `ISPRACTICAL` tinyint(1) DEFAULT 0,
  `ISOPTIONAL` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course`
--

INSERT INTO `course` (`COURSE_ID`, `OPTIONAL_ID`, `YEAR_NUMBER`, `PROGRAMME_ID`, `TYPE`, `SEMESTER`, `LONG_NAME`, `SHORT_NAME`, `WEEKLY_LECTURES`, `ISPRACTICAL`, `ISOPTIONAL`) VALUES
(8, NULL, 1, 2, 'LECTURE', 'ODD', 'PPLUC', 'PPLUC', 2, 0, 0),
(9, NULL, 1, 2, 'LECTURE', 'ODD', 'MA', 'MA', 2, 0, 0),
(10, NULL, 1, 2, 'LECTURE', 'ODD', 'VM', 'VM', 2, 0, 0),
(11, NULL, 1, 2, 'LECTURE', 'ODD', 'DM', 'DM', 2, 0, 0),
(12, 15, 1, 2, 'LECTURE', 'ODD', 'ACCOUNTING', 'ACCOUNTING', 4, 0, 0),
(13, NULL, 1, 2, 'IT PRACTICAL', 'ODD', 'PPLUC MA PRACTICAL', 'PPLUC MA PRACTICAL', 2, 1, 0),
(14, NULL, 1, 2, 'IT PRACTICAL', 'ODD', 'DM PRACTICAL', 'DM PRACTICAL', 2, 1, 0),
(15, 12, 1, 2, 'LECTURE', 'ODD', 'SIT', 'SIT', 4, 0, 0),
(16, NULL, 1, 3, 'LECTURE', 'ODD', 'BNI', 'BNI', 2, 0, 0),
(17, NULL, 1, 2, 'LECTURE', 'ODD', 'DM', 'DM', 2, 0, 0),
(19, NULL, 1, 2, 'LECTURE', 'ODD', 'BANKING FOR INFORMATION TECHNOLOGY', 'BANK IT', 2, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `department`
--

CREATE TABLE `department` (
  `DEPARTMENT_ID` int(11) NOT NULL,
  `LONG_NAME` varchar(63) NOT NULL,
  `SHORT_NAME` varchar(31) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `department`
--

INSERT INTO `department` (`DEPARTMENT_ID`, `LONG_NAME`, `SHORT_NAME`) VALUES
(1, 'INFORMATION TECHNOLOGY', 'I.T.'),
(2, 'BANKING AND INSURANCE', 'B.N.I.');

-- --------------------------------------------------------

--
-- Table structure for table `division`
--

CREATE TABLE `division` (
  `DIVISION_ID` int(11) NOT NULL,
  `YEAR_NUMBER` int(11) DEFAULT NULL,
  `PROGRAMME_ID` int(11) DEFAULT NULL,
  `NAME` char(1) NOT NULL,
  `STUDENT_COUNT` int(11) DEFAULT NULL,
  `START_TIME_ID` int(11) DEFAULT NULL,
  `END_TIME_ID` int(11) DEFAULT NULL,
  `CLASSROOM_ID` int(11) DEFAULT NULL
) ;

--
-- Dumping data for table `division`
--

INSERT INTO `division` (`DIVISION_ID`, `YEAR_NUMBER`, `PROGRAMME_ID`, `NAME`, `STUDENT_COUNT`, `START_TIME_ID`, `END_TIME_ID`, `CLASSROOM_ID`) VALUES
(13, 1, 2, 'A', 60, 7, NULL, 5),
(14, 1, 2, 'B', 60, 7, NULL, 6),
(15, 1, 3, 'A', 60, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `opted_by`
--

CREATE TABLE `opted_by` (
  `COURSE_ID` int(11) NOT NULL,
  `DIVISION_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `opted_by`
--

INSERT INTO `opted_by` (`COURSE_ID`, `DIVISION_ID`) VALUES
(12, 13),
(15, 14);

-- --------------------------------------------------------

--
-- Table structure for table `programme`
--

CREATE TABLE `programme` (
  `PROGRAMME_ID` int(11) NOT NULL,
  `DEPARTMENT_ID` int(11) DEFAULT NULL,
  `LONG_NAME` varchar(63) NOT NULL,
  `SHORT_NAME` varchar(15) NOT NULL,
  `DIVISION_COUNT` int(11) DEFAULT 1
) ;

--
-- Dumping data for table `programme`
--

INSERT INTO `programme` (`PROGRAMME_ID`, `DEPARTMENT_ID`, `LONG_NAME`, `SHORT_NAME`, `DIVISION_COUNT`) VALUES
(2, 1, 'BACHELOR OF SCIENCE IN INFORMATION TECHNLOGY', 'B.SC.I.T.', 2),
(3, 2, 'BNI', 'BNI', 1);

-- --------------------------------------------------------

--
-- Table structure for table `teacher`
--

CREATE TABLE `teacher` (
  `TEACHER_ID` int(11) NOT NULL,
  `DEPARTMENT_ID` int(11) NOT NULL,
  `FIRST_NAME` varchar(15) NOT NULL,
  `LAST_NAME` varchar(15) NOT NULL,
  `ISPARTTIME` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher`
--

INSERT INTO `teacher` (`TEACHER_ID`, `DEPARTMENT_ID`, `FIRST_NAME`, `LAST_NAME`, `ISPARTTIME`) VALUES
(4, 1, 'POURNIMA', 'BHANGALE', 0),
(5, 1, 'VANDANA', 'KADAM', 0),
(6, 1, 'RAKHEE', 'RANE', 0),
(7, 1, 'NANDA', 'RUPNAR', 0),
(8, 1, 'PRANALI', 'PAWAR', 0),
(9, 2, 'BNI', 'TEACHER', 1);

-- --------------------------------------------------------

--
-- Table structure for table `teaches`
--

CREATE TABLE `teaches` (
  `WORKLOAD_ID` int(11) NOT NULL,
  `TEACHER_ID` int(11) DEFAULT NULL,
  `COURSE_ID` int(11) DEFAULT NULL,
  `DIVISION_ID` int(11) DEFAULT NULL,
  `LECTURE_COUNT` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teaches`
--

INSERT INTO `teaches` (`WORKLOAD_ID`, `TEACHER_ID`, `COURSE_ID`, `DIVISION_ID`, `LECTURE_COUNT`) VALUES
(1, 6, 8, 13, 2),
(2, 6, 8, 14, 2),
(3, 6, 15, 13, 4),
(4, 6, 15, 14, 2),
(5, 6, 13, 13, 1),
(6, 6, 13, 14, 1),
(7, 5, 9, 13, 2),
(8, 5, 9, 14, 2),
(9, 5, 13, 13, 1),
(10, 5, 13, 14, 1),
(11, 8, 10, 13, 2),
(12, 8, 10, 14, 2),
(13, 8, 11, 13, 2),
(14, 8, 11, 14, 2),
(15, 8, 14, 13, 2),
(16, 8, 14, 14, 2),
(20, 4, 12, 13, 4),
(21, 4, 12, 14, 4),
(49, 9, 19, 13, 2),
(52, 9, 19, 14, 2),
(53, 9, 16, 15, 2);

-- --------------------------------------------------------

--
-- Table structure for table `timeslot`
--

CREATE TABLE `timeslot` (
  `SLOT_ID` int(11) NOT NULL,
  `START_TIME` time NOT NULL,
  `END_TIME` time NOT NULL,
  `SLOT_TYPE` enum('BREAK','LECTURE','PRACTICAL') NOT NULL DEFAULT 'LECTURE'
) ;

--
-- Dumping data for table `timeslot`
--

INSERT INTO `timeslot` (`SLOT_ID`, `START_TIME`, `END_TIME`, `SLOT_TYPE`) VALUES
(1, '07:00:00', '08:00:00', 'LECTURE'),
(3, '08:00:00', '09:00:00', 'LECTURE'),
(4, '07:00:00', '09:00:00', 'PRACTICAL'),
(5, '09:00:00', '09:15:00', 'BREAK'),
(6, '09:15:00', '09:30:00', 'BREAK'),
(7, '09:30:00', '10:30:00', 'LECTURE'),
(8, '10:30:00', '11:30:00', 'LECTURE'),
(9, '09:30:00', '11:30:00', 'PRACTICAL'),
(10, '11:30:00', '11:45:00', 'BREAK'),
(11, '11:45:00', '12:45:00', 'LECTURE'),
(12, '12:45:00', '13:45:00', 'LECTURE'),
(13, '11:45:00', '13:45:00', 'PRACTICAL');

-- --------------------------------------------------------

--
-- Table structure for table `timetable`
--

CREATE TABLE `timetable` (
  `ALLOTMENT_ID` int(11) NOT NULL,
  `COURSE_ID` int(11) DEFAULT NULL,
  `DIVISION_ID` int(11) DEFAULT NULL,
  `CLASSROOM_ID` int(11) DEFAULT NULL,
  `SLOT_ID` int(11) DEFAULT NULL,
  `WEEKDAY` enum('MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY') DEFAULT NULL,
  `TEACHER_ID` int(11) DEFAULT NULL,
  `ACADEMIC_YEAR` varchar(7) NOT NULL,
  `SEMESTER` enum('EVEN','ODD') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `timetable`
--

INSERT INTO `timetable` (`ALLOTMENT_ID`, `COURSE_ID`, `DIVISION_ID`, `CLASSROOM_ID`, `SLOT_ID`, `WEEKDAY`, `TEACHER_ID`, `ACADEMIC_YEAR`, `SEMESTER`) VALUES
(12, 8, 13, 4, 8, 'TUESDAY', 6, '2026-27', 'ODD'),
(13, 8, 13, 5, 7, 'WEDNESDAY', 6, '2026-27', 'ODD'),
(26, 8, 14, 6, 1, 'TUESDAY', 6, '2026-27', 'ODD'),
(30, 8, 14, 6, 1, 'THURSDAY', 6, '2026-27', 'ODD'),
(19, 9, 13, 4, 3, 'FRIDAY', 5, '2026-27', 'ODD'),
(10, 9, 13, 4, 8, 'MONDAY', 5, '2026-27', 'ODD'),
(31, 9, 14, 4, 3, 'THURSDAY', 5, '2026-27', 'ODD'),
(32, 9, 14, 6, 1, 'FRIDAY', 5, '2026-27', 'ODD'),
(18, 10, 13, 5, 1, 'FRIDAY', 8, '2026-27', 'ODD'),
(23, 10, 13, 5, 7, 'SATURDAY', 8, '2026-27', 'ODD'),
(27, 10, 14, 4, 3, 'TUESDAY', 8, '2026-27', 'ODD'),
(24, 10, 14, 6, 1, 'MONDAY', 8, '2026-27', 'ODD'),
(9, 11, 13, 5, 7, 'MONDAY', 8, '2026-27', 'ODD'),
(11, 11, 13, 5, 7, 'TUESDAY', 8, '2026-27', 'ODD'),
(35, 11, 14, 6, 1, 'SATURDAY', 8, '2026-27', 'ODD'),
(34, 11, 14, 6, 7, 'FRIDAY', 8, '2026-27', 'ODD'),
(22, 12, 13, 4, 3, 'SATURDAY', 4, '2026-27', 'ODD'),
(40, 12, 13, 4, 8, 'FRIDAY', 4, '2026-27', 'ODD'),
(20, 12, 13, 5, 7, 'FRIDAY', 4, '2026-27', 'ODD'),
(43, 12, 13, 6, 7, 'MONDAY', 4, '2026-27', 'ODD'),
(42, 12, 14, NULL, 8, 'SATURDAY', 4, '2026-27', 'ODD'),
(25, 12, 14, 4, 3, 'MONDAY', 4, '2026-27', 'ODD'),
(44, 12, 14, 5, 1, 'MONDAY', 4, '2026-27', 'ODD'),
(37, 12, 14, 6, 7, 'SATURDAY', 4, '2026-27', 'ODD'),
(1, 13, 13, 1, 4, 'MONDAY', 6, '2026-27', 'ODD'),
(2, 13, 13, 1, 4, 'TUESDAY', 5, '2026-27', 'ODD'),
(5, 13, 14, 1, 9, 'MONDAY', 6, '2026-27', 'ODD'),
(6, 13, 14, 1, 9, 'TUESDAY', 5, '2026-27', 'ODD'),
(3, 14, 13, 1, 4, 'WEDNESDAY', 8, '2026-27', 'ODD'),
(4, 14, 13, 1, 4, 'THURSDAY', 8, '2026-27', 'ODD'),
(7, 14, 14, 1, 9, 'WEDNESDAY', 8, '2026-27', 'ODD'),
(8, 14, 14, 1, 9, 'THURSDAY', 8, '2026-27', 'ODD'),
(14, 15, 13, 4, 8, 'WEDNESDAY', 6, '2026-27', 'ODD'),
(17, 15, 13, 4, 8, 'THURSDAY', 6, '2026-27', 'ODD'),
(41, 15, 13, 4, 8, 'SATURDAY', 6, '2026-27', 'ODD'),
(21, 15, 13, 5, 1, 'SATURDAY', 6, '2026-27', 'ODD'),
(33, 15, 14, NULL, 3, 'FRIDAY', 6, '2026-27', 'ODD'),
(36, 15, 14, NULL, 3, 'SATURDAY', 6, '2026-27', 'ODD'),
(38, 16, 15, 4, 12, 'WEDNESDAY', 9, '2026-27', 'ODD'),
(39, 16, 15, 4, 12, 'THURSDAY', 9, '2026-27', 'ODD'),
(16, 19, 13, 5, 7, 'THURSDAY', 9, '2026-27', 'ODD'),
(15, 19, 13, 5, 11, 'WEDNESDAY', 9, '2026-27', 'ODD'),
(29, 19, 14, 4, 3, 'WEDNESDAY', 9, '2026-27', 'ODD'),
(28, 19, 14, 6, 1, 'WEDNESDAY', 9, '2026-27', 'ODD');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `USERNAME` varchar(31) NOT NULL,
  `PASSWORD` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`USERNAME`, `PASSWORD`) VALUES
('VEDBHAGAT', '$2y$10$tVLz2m2YL80acXmc4b5v5OWDw/O93FLKwNZsTT9mjD0llz1iTuZTO');

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_minimal_course`
-- (See below for the actual view)
--
CREATE TABLE `view_minimal_course` (
`COURSE_ID` int(11)
,`PROGRAMME_ID` int(11)
,`DEPARTMENT_ID` int(11)
,`PROGRAMME_NAME` varchar(15)
,`DEPARTMENT_NAME` varchar(31)
,`LONG_NAME` varchar(100)
,`SHORT_NAME` varchar(20)
,`WEEKLY_LECTURES` int(11)
,`ISPRACTICAL` tinyint(1)
,`ISOPTIONAL` tinyint(1)
,`YEAR_NUMBER` int(11)
,`SEMESTER` enum('EVEN','ODD')
,`OPTIONAL_ID` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_minimal_division`
-- (See below for the actual view)
--
CREATE TABLE `view_minimal_division` (
`DEPARTMENT_ID` int(11)
,`DEPARTMENT_NAME` varchar(31)
,`PROGRAMME_ID` int(11)
,`PROGRAMME_NAME` varchar(15)
,`YEAR_NUMBER` int(11)
,`YEAR_NAME` enum('FIRST YEAR','SECOND YEAR','THIRD YEAR','FOURTH YEAR','FIFTH YEAR')
,`DIVISION_ID` int(11)
,`DIVISION_NAME` char(1)
,`STUDENT_COUNT` int(11)
,`CLASSROOM_ID` int(11)
,`START_TIME_ID` int(11)
,`END_TIME_ID` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_minimal_optionalcourse`
-- (See below for the actual view)
--
CREATE TABLE `view_minimal_optionalcourse` (
`DEPARTMENT_ID` int(11)
,`DEPARTMENT_SHORT_NAME` varchar(31)
,`PROGRAMME_ID` int(11)
,`PROGRAMME_SHORT_NAME` varchar(15)
,`YEAR_NUMBER` int(11)
,`YEAR_NAME` enum('FIRST YEAR','SECOND YEAR','THIRD YEAR','FOURTH YEAR','FIFTH YEAR')
,`COURSE_ID` int(11)
,`SEMESTER` enum('EVEN','ODD')
,`COURSE_FULL_NAME` varchar(100)
,`COURSE_SHORT_NAME` varchar(20)
,`ISOPTIONAL` tinyint(1)
,`OPTIONAL_ID` int(11)
,`OPTIONAL_COURSE_NAME` varchar(20)
,`ISPRACTICAL` tinyint(1)
,`WEEKLY_LECTURES` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_minimal_optionalcoursemapper`
-- (See below for the actual view)
--
CREATE TABLE `view_minimal_optionalcoursemapper` (
`DEPARTMENT_ID` int(11)
,`DEPARTMENT_SHORT_NAME` varchar(31)
,`PROGRAMME_ID` int(11)
,`PROGRAMME_SHORT_NAME` varchar(15)
,`YEAR_NUMBER` int(11)
,`YEAR_NAME` enum('FIRST YEAR','SECOND YEAR','THIRD YEAR','FOURTH YEAR','FIFTH YEAR')
,`COURSE_ID` int(11)
,`SEMESTER` enum('EVEN','ODD')
,`COURSE_FULL_NAME` varchar(100)
,`COURSE_SHORT_NAME` varchar(20)
,`ISOPTIONAL` tinyint(1)
,`OPTIONAL_ID` int(11)
,`OPTIONAL_COURSE_NAME` varchar(20)
,`ISPRACTICAL` tinyint(1)
,`WEEKLY_LECTURES` int(11)
,`MAPPED_DIVISION_ID` int(11)
,`MAPPED_DIVISION_NAME` char(1)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_minimal_programme`
-- (See below for the actual view)
--
CREATE TABLE `view_minimal_programme` (
`DEPARTMENT_ID` int(11)
,`PROGRAMME_ID` int(11)
,`LONG_NAME` varchar(63)
,`SHORT_NAME` varchar(15)
,`DURATION` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_minimal_workload`
-- (See below for the actual view)
--
CREATE TABLE `view_minimal_workload` (
`WORKLOAD_ID` int(11)
,`TEACHER_ID` int(11)
,`COURSE_ID` int(11)
,`DIVISION_ID` int(11)
,`DEPARTMENT_ID` int(11)
,`DEPARTMENT_NAME` varchar(31)
,`PROGRAMME_ID` int(11)
,`PROGRAMME_NAME` varchar(15)
,`YEAR_NUMBER` int(11)
,`YEAR_NAME` enum('FIRST YEAR','SECOND YEAR','THIRD YEAR','FOURTH YEAR','FIFTH YEAR')
,`DIVISION_NAME` char(1)
,`SEMESTER` enum('EVEN','ODD')
,`COURSE_NAME` varchar(100)
,`COURSE_SHORT_NAME` varchar(20)
,`LECTURE_COUNT` int(11)
,`TEACHER_NAME` varchar(31)
);

-- --------------------------------------------------------

--
-- Table structure for table `weekday`
--

CREATE TABLE `weekday` (
  `WEEKDAY` enum('MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `weekday`
--

INSERT INTO `weekday` (`WEEKDAY`) VALUES
('MONDAY'),
('TUESDAY'),
('WEDNESDAY'),
('THURSDAY'),
('FRIDAY'),
('SATURDAY');

-- --------------------------------------------------------

--
-- Table structure for table `year`
--

CREATE TABLE `year` (
  `YEAR_NUMBER` int(11) NOT NULL,
  `YEAR_NAME` enum('FIRST YEAR','SECOND YEAR','THIRD YEAR','FOURTH YEAR','FIFTH YEAR') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `year`
--

INSERT INTO `year` (`YEAR_NUMBER`, `YEAR_NAME`) VALUES
(1, 'FIRST YEAR'),
(2, 'SECOND YEAR'),
(3, 'THIRD YEAR'),
(4, 'FOURTH YEAR'),
(5, 'FIFTH YEAR');

-- --------------------------------------------------------

--
-- Structure for view `view_minimal_course`
--
DROP TABLE IF EXISTS `view_minimal_course`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_minimal_course`  AS SELECT `c`.`COURSE_ID` AS `COURSE_ID`, `c`.`PROGRAMME_ID` AS `PROGRAMME_ID`, `p`.`DEPARTMENT_ID` AS `DEPARTMENT_ID`, `p`.`SHORT_NAME` AS `PROGRAMME_NAME`, `d`.`SHORT_NAME` AS `DEPARTMENT_NAME`, `c`.`LONG_NAME` AS `LONG_NAME`, `c`.`SHORT_NAME` AS `SHORT_NAME`, `c`.`WEEKLY_LECTURES` AS `WEEKLY_LECTURES`, `c`.`ISPRACTICAL` AS `ISPRACTICAL`, `c`.`ISOPTIONAL` AS `ISOPTIONAL`, `c`.`YEAR_NUMBER` AS `YEAR_NUMBER`, `c`.`SEMESTER` AS `SEMESTER`, `c`.`OPTIONAL_ID` AS `OPTIONAL_ID` FROM ((`course` `c` left join `programme` `p` on(`c`.`PROGRAMME_ID` = `p`.`PROGRAMME_ID`)) left join `department` `d` on(`p`.`DEPARTMENT_ID` = `d`.`DEPARTMENT_ID`)) ORDER BY `p`.`DEPARTMENT_ID` ASC, `p`.`SHORT_NAME` ASC, `c`.`YEAR_NUMBER` ASC, `c`.`SEMESTER` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `view_minimal_division`
--
DROP TABLE IF EXISTS `view_minimal_division`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_minimal_division`  AS SELECT `d`.`DEPARTMENT_ID` AS `DEPARTMENT_ID`, `d`.`SHORT_NAME` AS `DEPARTMENT_NAME`, `p`.`PROGRAMME_ID` AS `PROGRAMME_ID`, `p`.`SHORT_NAME` AS `PROGRAMME_NAME`, `y`.`YEAR_NUMBER` AS `YEAR_NUMBER`, `y`.`YEAR_NAME` AS `YEAR_NAME`, `dv`.`DIVISION_ID` AS `DIVISION_ID`, `dv`.`NAME` AS `DIVISION_NAME`, `dv`.`STUDENT_COUNT` AS `STUDENT_COUNT`, `dv`.`CLASSROOM_ID` AS `CLASSROOM_ID`, `dv`.`START_TIME_ID` AS `START_TIME_ID`, `dv`.`END_TIME_ID` AS `END_TIME_ID` FROM ((((`department` `d` join `programme` `p` on(`p`.`DEPARTMENT_ID` = `d`.`DEPARTMENT_ID`)) join `consists` `c` on(`c`.`PROGRAMME_ID` = `p`.`PROGRAMME_ID`)) join `year` `y` on(`y`.`YEAR_NUMBER` = `c`.`YEAR_NUMBER`)) left join `division` `dv` on(`dv`.`PROGRAMME_ID` = `c`.`PROGRAMME_ID` and `dv`.`YEAR_NUMBER` = `c`.`YEAR_NUMBER`)) ORDER BY `dv`.`STUDENT_COUNT` ASC, `d`.`SHORT_NAME` ASC, `p`.`SHORT_NAME` ASC, `y`.`YEAR_NUMBER` ASC, `dv`.`NAME` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `view_minimal_optionalcourse`
--
DROP TABLE IF EXISTS `view_minimal_optionalcourse`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_minimal_optionalcourse`  AS SELECT `d`.`DEPARTMENT_ID` AS `DEPARTMENT_ID`, `d`.`SHORT_NAME` AS `DEPARTMENT_SHORT_NAME`, `p`.`PROGRAMME_ID` AS `PROGRAMME_ID`, `p`.`SHORT_NAME` AS `PROGRAMME_SHORT_NAME`, `y`.`YEAR_NUMBER` AS `YEAR_NUMBER`, `y`.`YEAR_NAME` AS `YEAR_NAME`, `c`.`COURSE_ID` AS `COURSE_ID`, `c`.`SEMESTER` AS `SEMESTER`, `c`.`LONG_NAME` AS `COURSE_FULL_NAME`, `c`.`SHORT_NAME` AS `COURSE_SHORT_NAME`, `c`.`ISOPTIONAL` AS `ISOPTIONAL`, `c`.`OPTIONAL_ID` AS `OPTIONAL_ID`, `op`.`SHORT_NAME` AS `OPTIONAL_COURSE_NAME`, `c`.`ISPRACTICAL` AS `ISPRACTICAL`, `c`.`WEEKLY_LECTURES` AS `WEEKLY_LECTURES` FROM ((((`course` `c` left join `course` `op` on(`op`.`COURSE_ID` = `c`.`OPTIONAL_ID`)) left join `programme` `p` on(`c`.`PROGRAMME_ID` = `p`.`PROGRAMME_ID`)) left join `department` `d` on(`p`.`DEPARTMENT_ID` = `d`.`DEPARTMENT_ID`)) left join `year` `y` on(`c`.`YEAR_NUMBER` = `y`.`YEAR_NUMBER`)) WHERE `c`.`ISOPTIONAL` = 1 AND (`c`.`OPTIONAL_ID` is null OR `c`.`COURSE_ID` < `c`.`OPTIONAL_ID`) ORDER BY `d`.`DEPARTMENT_ID` ASC, `p`.`PROGRAMME_ID` ASC, `y`.`YEAR_NUMBER` ASC, `c`.`SEMESTER` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `view_minimal_optionalcoursemapper`
--
DROP TABLE IF EXISTS `view_minimal_optionalcoursemapper`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_minimal_optionalcoursemapper`  AS SELECT `d`.`DEPARTMENT_ID` AS `DEPARTMENT_ID`, `d`.`SHORT_NAME` AS `DEPARTMENT_SHORT_NAME`, `p`.`PROGRAMME_ID` AS `PROGRAMME_ID`, `p`.`SHORT_NAME` AS `PROGRAMME_SHORT_NAME`, `y`.`YEAR_NUMBER` AS `YEAR_NUMBER`, `y`.`YEAR_NAME` AS `YEAR_NAME`, `c`.`COURSE_ID` AS `COURSE_ID`, `c`.`SEMESTER` AS `SEMESTER`, `c`.`LONG_NAME` AS `COURSE_FULL_NAME`, `c`.`SHORT_NAME` AS `COURSE_SHORT_NAME`, `c`.`ISOPTIONAL` AS `ISOPTIONAL`, `c`.`OPTIONAL_ID` AS `OPTIONAL_ID`, `op`.`SHORT_NAME` AS `OPTIONAL_COURSE_NAME`, `c`.`ISPRACTICAL` AS `ISPRACTICAL`, `c`.`WEEKLY_LECTURES` AS `WEEKLY_LECTURES`, `ob`.`DIVISION_ID` AS `MAPPED_DIVISION_ID`, `dv`.`NAME` AS `MAPPED_DIVISION_NAME` FROM ((((((`course` `c` left join `course` `op` on(`op`.`COURSE_ID` = `c`.`OPTIONAL_ID`)) left join `opted_by` `ob` on(`ob`.`COURSE_ID` = `c`.`COURSE_ID`)) left join `division` `dv` on(`dv`.`DIVISION_ID` = `ob`.`DIVISION_ID`)) join `programme` `p` on(`c`.`PROGRAMME_ID` = `p`.`PROGRAMME_ID`)) join `department` `d` on(`p`.`DEPARTMENT_ID` = `d`.`DEPARTMENT_ID`)) join `year` `y` on(`c`.`YEAR_NUMBER` = `y`.`YEAR_NUMBER`)) WHERE `c`.`ISOPTIONAL` = 1 AND `c`.`OPTIONAL_ID` is not null ORDER BY `d`.`DEPARTMENT_ID` ASC, `p`.`PROGRAMME_ID` ASC, `y`.`YEAR_NUMBER` ASC, `c`.`SEMESTER` ASC, `c`.`COURSE_ID` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `view_minimal_programme`
--
DROP TABLE IF EXISTS `view_minimal_programme`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_minimal_programme`  AS SELECT `p`.`DEPARTMENT_ID` AS `DEPARTMENT_ID`, `p`.`PROGRAMME_ID` AS `PROGRAMME_ID`, `p`.`LONG_NAME` AS `LONG_NAME`, `p`.`SHORT_NAME` AS `SHORT_NAME`, count(`c`.`PROGRAMME_ID`) AS `DURATION` FROM (`programme` `p` left join `consists` `c` on(`p`.`PROGRAMME_ID` = `c`.`PROGRAMME_ID`)) GROUP BY `p`.`DEPARTMENT_ID`, `p`.`PROGRAMME_ID`, `p`.`LONG_NAME`, `p`.`SHORT_NAME` ORDER BY `p`.`LONG_NAME` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `view_minimal_workload`
--
DROP TABLE IF EXISTS `view_minimal_workload`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_minimal_workload`  AS SELECT `t`.`WORKLOAD_ID` AS `WORKLOAD_ID`, `t`.`TEACHER_ID` AS `TEACHER_ID`, `t`.`COURSE_ID` AS `COURSE_ID`, `t`.`DIVISION_ID` AS `DIVISION_ID`, `d`.`DEPARTMENT_ID` AS `DEPARTMENT_ID`, `d`.`SHORT_NAME` AS `DEPARTMENT_NAME`, `p`.`PROGRAMME_ID` AS `PROGRAMME_ID`, `p`.`SHORT_NAME` AS `PROGRAMME_NAME`, `y`.`YEAR_NUMBER` AS `YEAR_NUMBER`, `y`.`YEAR_NAME` AS `YEAR_NAME`, `dv`.`NAME` AS `DIVISION_NAME`, `c`.`SEMESTER` AS `SEMESTER`, `c`.`LONG_NAME` AS `COURSE_NAME`, `c`.`SHORT_NAME` AS `COURSE_SHORT_NAME`, `t`.`LECTURE_COUNT` AS `LECTURE_COUNT`, concat(`tr`.`FIRST_NAME`,' ',`tr`.`LAST_NAME`) AS `TEACHER_NAME` FROM ((((((`teaches` `t` join `teacher` `tr` on(`t`.`TEACHER_ID` = `tr`.`TEACHER_ID`)) join `course` `c` on(`t`.`COURSE_ID` = `c`.`COURSE_ID`)) join `division` `dv` on(`t`.`DIVISION_ID` = `dv`.`DIVISION_ID`)) join `programme` `p` on(`c`.`PROGRAMME_ID` = `p`.`PROGRAMME_ID`)) join `department` `d` on(`p`.`DEPARTMENT_ID` = `d`.`DEPARTMENT_ID`)) join `year` `y` on(`c`.`YEAR_NUMBER` = `y`.`YEAR_NUMBER`)) ORDER BY `d`.`LONG_NAME` ASC, `p`.`LONG_NAME` ASC, `y`.`YEAR_NUMBER` ASC, `dv`.`NAME` ASC, `c`.`SEMESTER` ASC, `c`.`LONG_NAME` ASC, concat(`tr`.`FIRST_NAME`,' ',`tr`.`LAST_NAME`) ASC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `availability`
--
ALTER TABLE `availability`
  ADD PRIMARY KEY (`TEACHER_ID`,`SLOT_ID`,`WEEKDAY`),
  ADD UNIQUE KEY `TEACHER_ID` (`TEACHER_ID`,`SLOT_ID`,`WEEKDAY`),
  ADD KEY `fk_slotId_avlbtTbl` (`SLOT_ID`),
  ADD KEY `fk_wkdy_avlbtTbl` (`WEEKDAY`);

--
-- Indexes for table `classroom`
--
ALTER TABLE `classroom`
  ADD PRIMARY KEY (`CLASSROOM_ID`),
  ADD UNIQUE KEY `ROOM_NUMBER` (`ROOM_NUMBER`);

--
-- Indexes for table `consists`
--
ALTER TABLE `consists`
  ADD PRIMARY KEY (`YEAR_NUMBER`,`PROGRAMME_ID`),
  ADD KEY `fk_pgrmId_cnstTbl` (`PROGRAMME_ID`);

--
-- Indexes for table `course`
--
ALTER TABLE `course`
  ADD PRIMARY KEY (`COURSE_ID`),
  ADD KEY `fk_pgrmId_crseTbl` (`PROGRAMME_ID`),
  ADD KEY `fk_yearId_crseTbl` (`YEAR_NUMBER`),
  ADD KEY `fk_opnlId_crseTbl` (`OPTIONAL_ID`);

--
-- Indexes for table `department`
--
ALTER TABLE `department`
  ADD PRIMARY KEY (`DEPARTMENT_ID`);

--
-- Indexes for table `division`
--
ALTER TABLE `division`
  ADD PRIMARY KEY (`DIVISION_ID`),
  ADD UNIQUE KEY `NAME` (`NAME`,`YEAR_NUMBER`,`PROGRAMME_ID`),
  ADD KEY `fk_yrId_dvsnTbl` (`YEAR_NUMBER`),
  ADD KEY `fk_pgrmId_dvsnTbl` (`PROGRAMME_ID`),
  ADD KEY `fk_clsrmId_dvsnTbl` (`CLASSROOM_ID`),
  ADD KEY `fk_sTmeId_dvsnTbl` (`START_TIME_ID`),
  ADD KEY `fk_eTmeId_dvsnTbl` (`END_TIME_ID`);

--
-- Indexes for table `opted_by`
--
ALTER TABLE `opted_by`
  ADD PRIMARY KEY (`COURSE_ID`,`DIVISION_ID`),
  ADD KEY `fk_dvsnId_optdByTbl` (`DIVISION_ID`);

--
-- Indexes for table `programme`
--
ALTER TABLE `programme`
  ADD PRIMARY KEY (`PROGRAMME_ID`),
  ADD KEY `fk_deptId_pgrmTbl` (`DEPARTMENT_ID`);

--
-- Indexes for table `teacher`
--
ALTER TABLE `teacher`
  ADD PRIMARY KEY (`TEACHER_ID`),
  ADD KEY `fk_deptId_tchrTbl` (`DEPARTMENT_ID`);

--
-- Indexes for table `teaches`
--
ALTER TABLE `teaches`
  ADD PRIMARY KEY (`WORKLOAD_ID`),
  ADD UNIQUE KEY `TEACHER_ID` (`TEACHER_ID`,`COURSE_ID`,`DIVISION_ID`),
  ADD KEY `fk_crseId_tchsTbl` (`COURSE_ID`),
  ADD KEY `fk_dvsn_tchsTbl` (`DIVISION_ID`);

--
-- Indexes for table `timeslot`
--
ALTER TABLE `timeslot`
  ADD PRIMARY KEY (`SLOT_ID`);

--
-- Indexes for table `timetable`
--
ALTER TABLE `timetable`
  ADD PRIMARY KEY (`ALLOTMENT_ID`),
  ADD UNIQUE KEY `COURSE_ID` (`COURSE_ID`,`DIVISION_ID`,`CLASSROOM_ID`,`SLOT_ID`,`WEEKDAY`,`TEACHER_ID`,`ACADEMIC_YEAR`,`SEMESTER`),
  ADD KEY `fk_dvsnId_tTbl` (`DIVISION_ID`),
  ADD KEY `fk_clsrmId_tTbl` (`CLASSROOM_ID`),
  ADD KEY `fk_slotId_tTbl` (`SLOT_ID`),
  ADD KEY `fk_wkdy_tTbl` (`WEEKDAY`),
  ADD KEY `fk_tchrId_tTbl` (`TEACHER_ID`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`USERNAME`);

--
-- Indexes for table `weekday`
--
ALTER TABLE `weekday`
  ADD PRIMARY KEY (`WEEKDAY`);

--
-- Indexes for table `year`
--
ALTER TABLE `year`
  ADD PRIMARY KEY (`YEAR_NUMBER`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `classroom`
--
ALTER TABLE `classroom`
  MODIFY `CLASSROOM_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `course`
--
ALTER TABLE `course`
  MODIFY `COURSE_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `department`
--
ALTER TABLE `department`
  MODIFY `DEPARTMENT_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `division`
--
ALTER TABLE `division`
  MODIFY `DIVISION_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `programme`
--
ALTER TABLE `programme`
  MODIFY `PROGRAMME_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `teacher`
--
ALTER TABLE `teacher`
  MODIFY `TEACHER_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `teaches`
--
ALTER TABLE `teaches`
  MODIFY `WORKLOAD_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `timeslot`
--
ALTER TABLE `timeslot`
  MODIFY `SLOT_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `timetable`
--
ALTER TABLE `timetable`
  MODIFY `ALLOTMENT_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `availability`
--
ALTER TABLE `availability`
  ADD CONSTRAINT `fk_slotId_avlbtTbl` FOREIGN KEY (`SLOT_ID`) REFERENCES `timeslot` (`SLOT_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tchrId_avlbtTbl` FOREIGN KEY (`TEACHER_ID`) REFERENCES `teacher` (`TEACHER_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_wkdy_avlbtTbl` FOREIGN KEY (`WEEKDAY`) REFERENCES `weekday` (`WEEKDAY`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `consists`
--
ALTER TABLE `consists`
  ADD CONSTRAINT `fk_pgrmId_cnstTbl` FOREIGN KEY (`PROGRAMME_ID`) REFERENCES `programme` (`PROGRAMME_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_yearId_cnstTbl` FOREIGN KEY (`YEAR_NUMBER`) REFERENCES `year` (`YEAR_NUMBER`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `course`
--
ALTER TABLE `course`
  ADD CONSTRAINT `fk_opnlId_crseTbl` FOREIGN KEY (`OPTIONAL_ID`) REFERENCES `course` (`COURSE_ID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pgrmId_crseTbl` FOREIGN KEY (`PROGRAMME_ID`) REFERENCES `programme` (`PROGRAMME_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_yearId_crseTbl` FOREIGN KEY (`YEAR_NUMBER`) REFERENCES `year` (`YEAR_NUMBER`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `division`
--
ALTER TABLE `division`
  ADD CONSTRAINT `fk_clsrmId_dvsnTbl` FOREIGN KEY (`CLASSROOM_ID`) REFERENCES `classroom` (`CLASSROOM_ID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_eTmeId_dvsnTbl` FOREIGN KEY (`END_TIME_ID`) REFERENCES `timeslot` (`SLOT_ID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pgrmId_dvsnTbl` FOREIGN KEY (`PROGRAMME_ID`) REFERENCES `programme` (`PROGRAMME_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sTmeId_dvsnTbl` FOREIGN KEY (`START_TIME_ID`) REFERENCES `timeslot` (`SLOT_ID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_yrId_dvsnTbl` FOREIGN KEY (`YEAR_NUMBER`) REFERENCES `year` (`YEAR_NUMBER`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `opted_by`
--
ALTER TABLE `opted_by`
  ADD CONSTRAINT `fk_crseId_optdByTbl` FOREIGN KEY (`COURSE_ID`) REFERENCES `course` (`COURSE_ID`),
  ADD CONSTRAINT `fk_dvsnId_optdByTbl` FOREIGN KEY (`DIVISION_ID`) REFERENCES `division` (`DIVISION_ID`);

--
-- Constraints for table `programme`
--
ALTER TABLE `programme`
  ADD CONSTRAINT `fk_deptId_pgrmTbl` FOREIGN KEY (`DEPARTMENT_ID`) REFERENCES `department` (`DEPARTMENT_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `teacher`
--
ALTER TABLE `teacher`
  ADD CONSTRAINT `fk_deptId_tchrTbl` FOREIGN KEY (`DEPARTMENT_ID`) REFERENCES `department` (`DEPARTMENT_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `teaches`
--
ALTER TABLE `teaches`
  ADD CONSTRAINT `fk_crseId_tchsTbl` FOREIGN KEY (`COURSE_ID`) REFERENCES `course` (`COURSE_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dvsn_tchsTbl` FOREIGN KEY (`DIVISION_ID`) REFERENCES `division` (`DIVISION_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tchrId_tchsTbl` FOREIGN KEY (`TEACHER_ID`) REFERENCES `teacher` (`TEACHER_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `timetable`
--
ALTER TABLE `timetable`
  ADD CONSTRAINT `fk_clsrmId_tTbl` FOREIGN KEY (`CLASSROOM_ID`) REFERENCES `classroom` (`CLASSROOM_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_crseId_tTbl` FOREIGN KEY (`COURSE_ID`) REFERENCES `course` (`COURSE_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dvsnId_tTbl` FOREIGN KEY (`DIVISION_ID`) REFERENCES `division` (`DIVISION_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_slotId_tTbl` FOREIGN KEY (`SLOT_ID`) REFERENCES `timeslot` (`SLOT_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tchrId_tTbl` FOREIGN KEY (`TEACHER_ID`) REFERENCES `teacher` (`TEACHER_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_wkdy_tTbl` FOREIGN KEY (`WEEKDAY`) REFERENCES `weekday` (`WEEKDAY`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

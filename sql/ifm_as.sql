-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql202.byetcluster.com
-- Generation Time: Dec 26, 2025 at 06:41 AM
-- Server version: 11.4.7-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_40761776_ifm_as`
--

-- --------------------------------------------------------

--
-- Table structure for table `tbladmin`
--

CREATE TABLE `tbladmin` (
  `adminId` int(11) NOT NULL,
  `firstName` varchar(100) NOT NULL,
  `lastName` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `dateCreated` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbladmin`
--

INSERT INTO `tbladmin` (`adminId`, `firstName`, `lastName`, `email`, `password`, `dateCreated`) VALUES
(1, 'System', 'Admin', 'admin@ifm.ac.tz', '$2y$10$QD6zv4c2rYGkEyL3m268/.7atAO2is1am/EvfwAQ5NxMy7ua/UtZW', '2025-12-12 15:16:42');

-- --------------------------------------------------------

--
-- Table structure for table `tblattendance`
--

CREATE TABLE `tblattendance` (
  `attendanceId` int(11) NOT NULL,
  `assignmentId` int(11) NOT NULL,
  `teacherId` int(11) NOT NULL,
  `dateTaken` date NOT NULL,
  `timeTaken` time DEFAULT curtime()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblattendance`
--

INSERT INTO `tblattendance` (`attendanceId`, `assignmentId`, `teacherId`, `dateTaken`, `timeTaken`) VALUES
(6, 12, 1, '2025-12-25', '15:25:47'),
(7, 13, 1, '2025-12-25', '15:42:17'),
(11, 12, 1, '2025-12-26', '22:18:16'),
(21, 20, 1, '2025-12-26', '22:41:10'),
(22, 25, 1, '2025-12-26', '02:20:30'),
(23, 13, 1, '2025-12-26', '02:41:35');

-- --------------------------------------------------------

--
-- Table structure for table `tblattendance_record`
--

CREATE TABLE `tblattendance_record` (
  `recordId` int(11) NOT NULL,
  `attendanceId` int(11) NOT NULL,
  `studentId` int(11) NOT NULL,
  `status` enum('Present','Absent','Late','Excused') DEFAULT 'Absent',
  `method` enum('Manual','QR') DEFAULT 'Manual',
  `deviceFingerprint` varchar(64) DEFAULT NULL,
  `createdAt` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblattendance_record`
--

INSERT INTO `tblattendance_record` (`recordId`, `attendanceId`, `studentId`, `status`, `method`, `deviceFingerprint`, `createdAt`) VALUES
(5, 6, 2, 'Present', 'Manual', NULL, '2025-12-26 10:50:23'),
(6, 7, 2, 'Present', 'Manual', NULL, '2025-12-26 10:50:23'),
(7, 7, 1, 'Absent', 'Manual', NULL, '2025-12-26 10:50:23'),
(14, 21, 1, 'Present', 'QR', '000000000ccac60dTW96aWxsYS81LjAgKExpbnV4OyBBbmRy360820', '2025-12-26 10:50:23'),
(15, 21, 2, 'Absent', 'Manual', NULL, '2025-12-26 10:50:23'),
(16, 21, 4, 'Absent', 'Manual', NULL, '2025-12-26 10:50:23'),
(17, 22, 1, 'Late', 'Manual', NULL, '2025-12-26 10:50:23'),
(18, 23, 1, 'Present', 'QR', '000000000ccac60dTW96aWxsYS81LjAgKExpbnV4OyBBbmRy360820', '2025-12-26 10:50:23');

-- --------------------------------------------------------

--
-- Table structure for table `tblclass`
--

CREATE TABLE `tblclass` (
  `classId` int(11) NOT NULL,
  `className` varchar(100) NOT NULL,
  `yearLevel` enum('1','2','3') NOT NULL,
  `semester` varchar(10) DEFAULT NULL,
  `dateCreated` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblclass`
--

INSERT INTO `tblclass` (`classId`, `className`, `yearLevel`, `semester`, `dateCreated`) VALUES
(1, 'BSc IT', '1', 'Sem1', '2025-12-12 15:16:42'),
(2, 'BSc IT', '2', 'Sem1', '2025-12-12 15:16:42'),
(3, 'BSc IT', '3', 'Sem1', '2025-12-12 15:16:42'),
(4, 'Bsc BIRM', '1', 'semester 1', '2025-12-25 12:58:33');

-- --------------------------------------------------------

--
-- Table structure for table `tbldevice_attendance`
--

CREATE TABLE `tbldevice_attendance` (
  `id` int(11) NOT NULL,
  `attendanceId` int(11) NOT NULL,
  `deviceFingerprint` varchar(64) NOT NULL,
  `studentId` int(11) NOT NULL,
  `createdAt` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbldevice_attendance`
--

INSERT INTO `tbldevice_attendance` (`id`, `attendanceId`, `deviceFingerprint`, `studentId`, `createdAt`) VALUES
(2, 21, '000000000ccac60dTW96aWxsYS81LjAgKExpbnV4OyBBbmRy360820', 1, '2025-12-26 06:47:46'),
(3, 23, '000000000ccac60dTW96aWxsYS81LjAgKExpbnV4OyBBbmRy360820', 1, '2025-12-26 10:42:54');

-- --------------------------------------------------------

--
-- Table structure for table `tblstudent`
--

CREATE TABLE `tblstudent` (
  `studentId` int(11) NOT NULL,
  `admissionNo` varchar(100) NOT NULL,
  `firstName` varchar(100) NOT NULL,
  `lastName` varchar(100) NOT NULL,
  `otherName` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `classId` int(11) NOT NULL,
  `dateCreated` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblstudent`
--

INSERT INTO `tblstudent` (`studentId`, `admissionNo`, `firstName`, `lastName`, `otherName`, `email`, `password`, `classId`, `dateCreated`) VALUES
(1, 'STU001', 'Alice', 'Brown', NULL, 'student@ifm.ac.tz', '$2y$10$QD6zv4c2rYGkEyL3m268/.7atAO2is1am/EvfwAQ5NxMy7ua/UtZW', 1, '2025-12-12 15:16:42'),
(2, '1122', 'benny', 'ai', NULL, '1@gmail.com', '$2y$10$LR1R8ria4NY.TgVvkzTU9uO4Kckf6BJLqZXE4S9DXxB.EjYCfZgA6', 1, '2025-12-12 18:44:07'),
(3, '1234', 'benny', 'maurus', 'kuku', '6@gmail.com', '$2y$10$6yYe1qNo8mbLb.C3wmlnE.Ff3dRKR1PFu76Ayj0a4EKLbND9.9Bve', 4, '2025-12-25 13:00:26'),
(4, '1123', 'Test', 'Binding ', NULL, '2@gmal.com', '$2y$10$ZLR2XshH1z6wUkw96RkDxu.TudBDqAAUMNtoErhTxojAyCDE.kpBe', 1, '2025-12-25 20:00:52');

-- --------------------------------------------------------

--
-- Table structure for table `tblstudent_subject`
--

CREATE TABLE `tblstudent_subject` (
  `id` int(11) NOT NULL,
  `studentId` int(11) NOT NULL,
  `subjectId` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblstudent_subject`
--

INSERT INTO `tblstudent_subject` (`id`, `studentId`, `subjectId`) VALUES
(5, 1, 1),
(6, 1, 2),
(4, 1, 3),
(8, 2, 1),
(9, 2, 2),
(7, 2, 3),
(14, 3, 1),
(15, 3, 2),
(13, 3, 3),
(17, 4, 1),
(18, 4, 2),
(16, 4, 3);

-- --------------------------------------------------------

--
-- Table structure for table `tblsubject`
--

CREATE TABLE `tblsubject` (
  `subjectId` int(11) NOT NULL,
  `subjectCode` varchar(50) NOT NULL,
  `subjectName` varchar(255) NOT NULL,
  `creditHours` int(11) DEFAULT 3,
  `dateCreated` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblsubject`
--

INSERT INTO `tblsubject` (`subjectId`, `subjectCode`, `subjectName`, `creditHours`, `dateCreated`) VALUES
(1, 'IT101', 'Introduction to IT', 3, '2025-12-12 15:16:42'),
(2, 'NW201', 'Computer Networking', 3, '2025-12-12 15:16:42'),
(3, 'DB301', 'Database Systems', 3, '2025-12-12 15:16:42');

-- --------------------------------------------------------

--
-- Table structure for table `tblteacher`
--

CREATE TABLE `tblteacher` (
  `teacherId` int(11) NOT NULL,
  `firstName` varchar(100) NOT NULL,
  `lastName` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phoneNo` varchar(50) DEFAULT NULL,
  `dateCreated` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblteacher`
--

INSERT INTO `tblteacher` (`teacherId`, `firstName`, `lastName`, `email`, `password`, `phoneNo`, `dateCreated`) VALUES
(1, 'John', 'Doe', '1@gmail.com', '$2y$10$JIw273PcyKju06aOw9jlRu/y.5fxSYkryV972aPBtLkLKHM9zec7S', '', '2025-12-12 15:16:42');

-- --------------------------------------------------------

--
-- Table structure for table `tblteacher_subject_class`
--

CREATE TABLE `tblteacher_subject_class` (
  `id` int(11) NOT NULL,
  `teacherId` int(11) NOT NULL,
  `subjectId` int(11) NOT NULL,
  `classId` int(11) NOT NULL,
  `topic` varchar(255) DEFAULT NULL,
  `scheduleTime` time DEFAULT NULL,
  `endTime` time DEFAULT NULL,
  `dayOfWeek` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') DEFAULT NULL,
  `qrToken` varchar(255) DEFAULT NULL,
  `qrExpiry` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblteacher_subject_class`
--

INSERT INTO `tblteacher_subject_class` (`id`, `teacherId`, `subjectId`, `classId`, `topic`, `scheduleTime`, `endTime`, `dayOfWeek`, `qrToken`, `qrExpiry`) VALUES
(12, 1, 1, 1, 'final', '15:18:00', '15:20:00', 'Thursday', 'ab49877fb8c4e4c08b064eca8ce61cd0_1766730190', '2025-12-26 09:23:15'),
(13, 1, 1, 1, 'finalize', '14:14:00', '14:40:00', 'Friday', 'bbbb8e134d236b4d62311e348cc1a63e_1766747089', '2025-12-26 14:04:54'),
(20, 1, 2, 1, '', '09:37:00', '09:50:00', 'Friday', 'd76a1b0e0361855dee9189602c8d05de_1766741864', '2025-12-26 12:37:49'),
(25, 1, 3, 1, 'New', '13:34:00', '13:50:00', 'Friday', '791c8aeeeb474e7816d18fd5778beef6_1766745571', '2025-12-26 13:39:36');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbladmin`
--
ALTER TABLE `tbladmin`
  ADD PRIMARY KEY (`adminId`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `tblattendance`
--
ALTER TABLE `tblattendance`
  ADD PRIMARY KEY (`attendanceId`),
  ADD UNIQUE KEY `unique_attendance_day` (`assignmentId`,`dateTaken`),
  ADD KEY `teacherId` (`teacherId`);

--
-- Indexes for table `tblattendance_record`
--
ALTER TABLE `tblattendance_record`
  ADD PRIMARY KEY (`recordId`),
  ADD UNIQUE KEY `unique_student_attendance` (`attendanceId`,`studentId`),
  ADD KEY `studentId` (`studentId`);

--
-- Indexes for table `tblclass`
--
ALTER TABLE `tblclass`
  ADD PRIMARY KEY (`classId`);

--
-- Indexes for table `tbldevice_attendance`
--
ALTER TABLE `tbldevice_attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_device_lecture` (`attendanceId`,`deviceFingerprint`),
  ADD KEY `idx_attendance` (`attendanceId`),
  ADD KEY `idx_device` (`deviceFingerprint`),
  ADD KEY `studentId` (`studentId`);

--
-- Indexes for table `tblstudent`
--
ALTER TABLE `tblstudent`
  ADD PRIMARY KEY (`studentId`),
  ADD UNIQUE KEY `admissionNo` (`admissionNo`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `classId` (`classId`);

--
-- Indexes for table `tblstudent_subject`
--
ALTER TABLE `tblstudent_subject`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_subject` (`studentId`,`subjectId`),
  ADD KEY `subjectId` (`subjectId`);

--
-- Indexes for table `tblsubject`
--
ALTER TABLE `tblsubject`
  ADD PRIMARY KEY (`subjectId`),
  ADD UNIQUE KEY `subjectCode` (`subjectCode`);

--
-- Indexes for table `tblteacher`
--
ALTER TABLE `tblteacher`
  ADD PRIMARY KEY (`teacherId`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `tblteacher_subject_class`
--
ALTER TABLE `tblteacher_subject_class`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_schedule` (`teacherId`,`subjectId`,`classId`,`dayOfWeek`,`scheduleTime`),
  ADD KEY `subjectId` (`subjectId`),
  ADD KEY `classId` (`classId`),
  ADD KEY `idx_teacher_subject_class` (`teacherId`,`subjectId`,`classId`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbladmin`
--
ALTER TABLE `tbladmin`
  MODIFY `adminId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tblattendance`
--
ALTER TABLE `tblattendance`
  MODIFY `attendanceId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `tblattendance_record`
--
ALTER TABLE `tblattendance_record`
  MODIFY `recordId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `tblclass`
--
ALTER TABLE `tblclass`
  MODIFY `classId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tbldevice_attendance`
--
ALTER TABLE `tbldevice_attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tblstudent`
--
ALTER TABLE `tblstudent`
  MODIFY `studentId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tblstudent_subject`
--
ALTER TABLE `tblstudent_subject`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `tblsubject`
--
ALTER TABLE `tblsubject`
  MODIFY `subjectId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tblteacher`
--
ALTER TABLE `tblteacher`
  MODIFY `teacherId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tblteacher_subject_class`
--
ALTER TABLE `tblteacher_subject_class`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tblattendance`
--
ALTER TABLE `tblattendance`
  ADD CONSTRAINT `fk_attendance_assignment` FOREIGN KEY (`assignmentId`) REFERENCES `tblteacher_subject_class` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tblattendance_ibfk_assignment` FOREIGN KEY (`assignmentId`) REFERENCES `tblteacher_subject_class` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tblattendance_ibfk_teacher` FOREIGN KEY (`teacherId`) REFERENCES `tblteacher` (`teacherId`) ON DELETE CASCADE;

--
-- Constraints for table `tblattendance_record`
--
ALTER TABLE `tblattendance_record`
  ADD CONSTRAINT `tblattendance_record_ibfk_1` FOREIGN KEY (`attendanceId`) REFERENCES `tblattendance` (`attendanceId`) ON DELETE CASCADE,
  ADD CONSTRAINT `tblattendance_record_ibfk_2` FOREIGN KEY (`studentId`) REFERENCES `tblstudent` (`studentId`) ON DELETE CASCADE;

--
-- Constraints for table `tbldevice_attendance`
--
ALTER TABLE `tbldevice_attendance`
  ADD CONSTRAINT `tbldevice_attendance_ibfk_1` FOREIGN KEY (`attendanceId`) REFERENCES `tblattendance` (`attendanceId`) ON DELETE CASCADE,
  ADD CONSTRAINT `tbldevice_attendance_ibfk_2` FOREIGN KEY (`studentId`) REFERENCES `tblstudent` (`studentId`) ON DELETE CASCADE;

--
-- Constraints for table `tblstudent`
--
ALTER TABLE `tblstudent`
  ADD CONSTRAINT `tblstudent_ibfk_1` FOREIGN KEY (`classId`) REFERENCES `tblclass` (`classId`) ON DELETE CASCADE;

--
-- Constraints for table `tblstudent_subject`
--
ALTER TABLE `tblstudent_subject`
  ADD CONSTRAINT `tblstudent_subject_ibfk_1` FOREIGN KEY (`studentId`) REFERENCES `tblstudent` (`studentId`) ON DELETE CASCADE,
  ADD CONSTRAINT `tblstudent_subject_ibfk_2` FOREIGN KEY (`subjectId`) REFERENCES `tblsubject` (`subjectId`) ON DELETE CASCADE;

--
-- Constraints for table `tblteacher_subject_class`
--
ALTER TABLE `tblteacher_subject_class`
  ADD CONSTRAINT `tblteacher_subject_class_ibfk_1` FOREIGN KEY (`teacherId`) REFERENCES `tblteacher` (`teacherId`) ON DELETE CASCADE,
  ADD CONSTRAINT `tblteacher_subject_class_ibfk_2` FOREIGN KEY (`subjectId`) REFERENCES `tblsubject` (`subjectId`) ON DELETE CASCADE,
  ADD CONSTRAINT `tblteacher_subject_class_ibfk_3` FOREIGN KEY (`classId`) REFERENCES `tblclass` (`classId`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

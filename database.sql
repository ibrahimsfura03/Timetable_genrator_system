-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

CREATE DATABASE IF NOT EXISTS `university_timetable` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `university_timetable`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `timetable_entries`;
DROP TABLE IF EXISTS `timetable`;
DROP TABLE IF EXISTS `generated_timetables`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `courses`;
DROP TABLE IF EXISTS `time_slots`;
DROP TABLE IF EXISTS `halls`;
DROP TABLE IF EXISTS `levels`;
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `system_settings`;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` int DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `fullname` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `role` enum('Admin','Lecturer') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `departments` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `department_name` varchar(100) NOT NULL,
  `description` text,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `levels` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `level_code` varchar(20) NOT NULL,
  `level_name` varchar(50) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `halls` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `hall_name` varchar(50) NOT NULL,
  `capacity` int NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `time_slots` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `duration` varchar(20) DEFAULT '1 Hour',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `courses` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `course_code` varchar(20) NOT NULL,
  `course_title` varchar(100) NOT NULL,
  `department_id` int NOT NULL,
  `level_id` int NOT NULL,
  `credit_units` int DEFAULT 3,
  `duration` varchar(20) DEFAULT '1 Hour',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  KEY `department_id` (`department_id`),
  KEY `level_id` (`level_id`),
  CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `courses_ibfk_2` FOREIGN KEY (`level_id`) REFERENCES `levels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `students` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `registration_number` varchar(50) NOT NULL UNIQUE,
  `full_name` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `department_id` int NOT NULL,
  `level_id` int NOT NULL,
  `status` enum('pending','approved','rejected','blocked') DEFAULT 'pending',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  KEY `department_id` (`department_id`),
  KEY `level_id` (`level_id`),
  CONSTRAINT `students_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `students_ibfk_2` FOREIGN KEY (`level_id`) REFERENCES `levels` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `generated_timetables` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `timetable_name` varchar(100) NOT NULL,
  `department_id` int NOT NULL,
  `level_id` int NOT NULL,
  `semester` int DEFAULT 1,
  `is_published` tinyint(1) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `generation_date` timestamp DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  KEY `department_id` (`department_id`),
  KEY `level_id` (`level_id`),
  CONSTRAINT `generated_timetables_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `generated_timetables_ibfk_2` FOREIGN KEY (`level_id`) REFERENCES `levels` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `timetable` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `course_id` int NOT NULL,
  `hall_id` int NOT NULL,
  `day_of_week` varchar(20) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  KEY `course_id` (`course_id`),
  KEY `hall_id` (`hall_id`),
  CONSTRAINT `timetable_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  CONSTRAINT `timetable_ibfk_2` FOREIGN KEY (`hall_id`) REFERENCES `halls` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `timetable_entries` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `timetable_id` int NOT NULL,
  `course_id` int NOT NULL,
  `hall_id` int NOT NULL,
  `time_slot_id` int NOT NULL,
  `day_of_week` varchar(20) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  KEY `timetable_id` (`timetable_id`),
  KEY `course_id` (`course_id`),
  KEY `hall_id` (`hall_id`),
  KEY `time_slot_id` (`time_slot_id`),
  CONSTRAINT `timetable_entries_ibfk_1` FOREIGN KEY (`timetable_id`) REFERENCES `generated_timetables` (`id`),
  CONSTRAINT `timetable_entries_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  CONSTRAINT `timetable_entries_ibfk_3` FOREIGN KEY (`hall_id`) REFERENCES `halls` (`id`),
  CONSTRAINT `timetable_entries_ibfk_4` FOREIGN KEY (`time_slot_id`) REFERENCES `time_slots` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `system_settings` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `setting_key` varchar(100) NOT NULL UNIQUE,
  `setting_value` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`fullname`, `email`, `password`, `role`, `status`) VALUES ('Administrator', 'admin@auk.com', 'admin123', 'Admin', 'active');

INSERT INTO `departments` (`department_name`, `description`, `status`) VALUES ('Computer Science', 'Computer Science Department', 'active'), ('Software Engineering', 'Software Engineering Department', 'active'), ('Business Administration', 'Business Administration Department', 'active'), ('Political Science', 'Political Science Department', 'active'), ('Mathematics', 'Mathematics Department', 'active'), ('Physics', 'Physics Department', 'active'), ('English Language', 'English Language Department', 'active'), ('History', 'History Department', 'active');

INSERT INTO `levels` (`level_code`, `level_name`, `status`) VALUES ('100', '100 Level', 'active'), ('200', '200 Level', 'active'), ('300', '300 Level', 'active'), ('400', '400 Level', 'active'), ('500', '500 Level', 'active');

INSERT INTO `halls` (`hall_name`, `capacity`, `status`) VALUES ('LT1', 150, 'active'), ('LT2', 150, 'active'), ('LT3', 120, 'active'), ('LT4', 120, 'active'), ('LT5', 100, 'active'), ('LT6', 100, 'active'), ('A101', 50, 'active'), ('A102', 50, 'active'), ('A103', 45, 'active'), ('A104', 45, 'active'), ('Lab1', 30, 'active'), ('Lab2', 30, 'active'), ('S1', 25, 'active'), ('S2', 25, 'active');

INSERT INTO `time_slots` (`start_time`, `end_time`, `duration`, `status`) VALUES ('08:00:00', '09:00:00', '1 Hour', 'active'), ('09:00:00', '10:00:00', '1 Hour', 'active'), ('10:00:00', '11:00:00', '1 Hour', 'active'), ('11:00:00', '12:00:00', '1 Hour', 'active'), ('12:00:00', '13:00:00', '1 Hour', 'active'), ('13:00:00', '14:00:00', '1 Hour', 'active'), ('14:00:00', '15:00:00', '1 Hour', 'active'), ('15:00:00', '16:00:00', '1 Hour', 'active');

INSERT INTO `courses` (`course_code`, `course_title`, `department_id`, `level_id`, `credit_units`, `duration`, `status`) VALUES ('CS101', 'Introduction to Programming', 1, 1, 3, '1 Hour', 'active'), ('CS102', 'Computer Fundamentals', 1, 1, 3, '1 Hour', 'active'), ('CS103', 'Digital Logic', 1, 1, 3, '1 Hour', 'active'), ('CS104', 'Mathematics for Computing', 1, 1, 3, '1 Hour', 'active'), ('CS201', 'Data Structures', 1, 2, 3, '1 Hour', 'active'), ('CS202', 'Database Systems', 1, 2, 3, '1 Hour', 'active'), ('CS203', 'Web Development', 1, 2, 3, '1 Hour', 'active'), ('CS204', 'Software Engineering I', 1, 2, 3, '1 Hour', 'active'), ('CS301', 'Artificial Intelligence', 1, 3, 3, '1 Hour', 'active'), ('CS302', 'Cloud Computing', 1, 3, 3, '1 Hour', 'active'), ('CS303', 'Cybersecurity', 1, 3, 3, '1 Hour', 'active'), ('CS304', 'Machine Learning', 1, 3, 3, '1 Hour', 'active'), ('CS401', 'Advanced Algorithms', 1, 4, 3, '1 Hour', 'active'), ('CS402', 'Mobile Application Development', 1, 4, 3, '1 Hour', 'active'), ('CS403', 'Network Programming', 1, 4, 3, '1 Hour', 'active'), ('SE101', 'Introduction to SE', 2, 1, 3, '1 Hour', 'active'), ('SE102', 'Programming Fundamentals', 2, 1, 3, '1 Hour', 'active'), ('SE201', 'Object-Oriented Programming', 2, 2, 3, '1 Hour', 'active'), ('SE202', 'Software Architecture', 2, 2, 3, '1 Hour', 'active'), ('SE301', 'Software Testing', 2, 3, 3, '1 Hour', 'active'), ('SE302', 'Mobile Development', 2, 3, 3, '1 Hour', 'active'), ('SE401', 'DevOps Engineering', 2, 4, 3, '1 Hour', 'active'), ('SE402', 'Enterprise Software Design', 2, 4, 3, '1 Hour', 'active'), ('BUS101', 'Business Management', 3, 1, 3, '1 Hour', 'active'), ('BUS102', 'Economics I', 3, 1, 3, '1 Hour', 'active'), ('BUS201', 'Accounting I', 3, 2, 3, '1 Hour', 'active'), ('BUS202', 'Marketing Management', 3, 2, 3, '1 Hour', 'active'), ('BUS301', 'Financial Management', 3, 3, 3, '1 Hour', 'active'), ('BUS302', 'Organizational Behavior', 3, 3, 3, '1 Hour', 'active'), ('BUS401', 'Strategic Management', 3, 4, 3, '1 Hour', 'active'), ('MATH101', 'Calculus I', 5, 1, 3, '1 Hour', 'active'), ('MATH102', 'Linear Algebra', 5, 1, 3, '1 Hour', 'active'), ('MATH201', 'Calculus II', 5, 2, 3, '1 Hour', 'active'), ('MATH202', 'Differential Equations', 5, 2, 3, '1 Hour', 'active'), ('MATH301', 'Real Analysis', 5, 3, 3, '1 Hour', 'active'), ('MATH302', 'Abstract Algebra', 5, 3, 3, '1 Hour', 'active'), ('PHY101', 'Physics I', 6, 1, 3, '1 Hour', 'active'), ('PHY102', 'Physics Lab I', 6, 1, 2, '1 Hour', 'active'), ('PHY201', 'Physics II', 6, 2, 3, '1 Hour', 'active'), ('PHY202', 'Physics Lab II', 6, 2, 2, '1 Hour', 'active'), ('ENG101', 'English Communication', 7, 1, 3, '1 Hour', 'active'), ('ENG102', 'Literature I', 7, 1, 3, '1 Hour', 'active'), ('ENG201', 'Advanced Writing', 7, 2, 3, '1 Hour', 'active'), ('ENG202', 'Literary Analysis', 7, 2, 3, '1 Hour', 'active'), ('HIS101', 'World History', 8, 1, 3, '1 Hour', 'active'), ('HIS102', 'African History', 8, 1, 3, '1 Hour', 'active'), ('HIS201', 'Nigerian History', 8, 2, 3, '1 Hour', 'active'), ('HIS202', 'Medieval History', 8, 2, 3, '1 Hour', 'active');

INSERT INTO `students` (`registration_number`, `full_name`, `password`, `department_id`, `level_id`, `status`) VALUES ('CS001', 'Ahmad Musa', 'password123', 1, 1, 'approved'), ('CS002', 'Aisha Hassan', 'password123', 1, 1, 'approved'), ('CS003', 'Mohammed Ibrahim', 'password123', 1, 2, 'approved'), ('CS004', 'Fatimah Ali', 'password123', 1, 2, 'approved'), ('SE001', 'Usman Adamu', 'password123', 2, 1, 'approved'), ('SE002', 'Zainab Sani', 'password123', 2, 1, 'approved'), ('SE003', 'Hassan Yusuf', 'password123', 2, 2, 'pending'), ('BUS001', 'Amina Muhammad', 'password123', 3, 1, 'approved'), ('BUS002', 'Karim Toure', 'password123', 3, 1, 'approved'), ('MATH001', 'Noor Ahmed', 'password123', 5, 1, 'approved'), ('PHY001', 'Rashid Omar', 'password123', 6, 1, 'approved'), ('ENG001', 'Layla Hassan', 'password123', 7, 1, 'approved'), ('HIS001', 'Ibrahim Sani', 'password123', 8, 1, 'approved');

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

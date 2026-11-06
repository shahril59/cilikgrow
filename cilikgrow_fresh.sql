CREATE DATABASE IF NOT EXISTS `cilikgrow_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `cilikgrow_db`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `progress_records`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `students`;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- students
-- ---------------------------------------------------------------------
CREATE TABLE `students` (
  `student_id`     int(11)      NOT NULL AUTO_INCREMENT,
  `student_name`   varchar(100) NOT NULL,
  `mykid`          varchar(20)  NOT NULL,
  `gender`         enum('Male','Female') DEFAULT NULL,
  `class_name`     varchar(50)  NOT NULL,
  `date_of_birth`  date         DEFAULT NULL,
  `address`        text         DEFAULT NULL,
  `guardian_phone` varchar(20)  DEFAULT NULL,
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `uq_students_mykid` (`mykid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- users (Management / Teacher / Parent)
-- ---------------------------------------------------------------------
CREATE TABLE `users` (
  `user_id`    int(11)      NOT NULL AUTO_INCREMENT,
  `username`   varchar(50)  NOT NULL,
  `password`   varchar(255) NOT NULL,
  `role`       enum('Teacher','Management','Parent') NOT NULL,
  `full_name`  varchar(100) NOT NULL,
  `email`      varchar(100) DEFAULT NULL,
  `student_id` int(11)      DEFAULT NULL,
  `phone`      varchar(15)  DEFAULT NULL,
  `status`     varchar(10)  NOT NULL DEFAULT 'Active',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  KEY `fk_user_student` (`student_id`),
  CONSTRAINT `fk_user_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- progress_records
-- ---------------------------------------------------------------------
CREATE TABLE `progress_records` (
  `progress_id`    int(11)     NOT NULL AUTO_INCREMENT,
  `student_id`     int(11)     NOT NULL,
  `academic_term`  varchar(50) NOT NULL,
  `reading_grade`  varchar(5)  DEFAULT NULL,
  `writing_grade`  varchar(5)  DEFAULT NULL,
  `counting_grade` varchar(5)  DEFAULT NULL,
  `behavior_grade` varchar(5)  DEFAULT NULL,
  `teacher_notes`  text        DEFAULT NULL,
  PRIMARY KEY (`progress_id`),
  UNIQUE KEY `uq_progress_student_term` (`student_id`,`academic_term`),
  CONSTRAINT `progress_records_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- attendance
-- ---------------------------------------------------------------------
CREATE TABLE `attendance` (
  `attendance_id`   int(11)     NOT NULL AUTO_INCREMENT,
  `student_id`      int(11)     NOT NULL,
  `attendance_date` date        NOT NULL,
  `status`          varchar(15) NOT NULL DEFAULT 'Present',
  `remarks`         varchar(255) DEFAULT NULL,
  PRIMARY KEY (`attendance_id`),
  UNIQUE KEY `uq_attendance_student_date` (`student_id`,`attendance_date`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================================
-- DATA
-- =====================================================================

INSERT INTO `students` (`student_id`, `student_name`, `mykid`, `gender`, `class_name`, `date_of_birth`, `address`, `guardian_phone`) VALUES
(1,  'Adam Rayyan',                 '210415-10-0123', 'Male',   'Kasih (5 years)',  '2021-04-15', 'Pedas, Negeri Sembilan', '0123334455'),
(2,  'Nur Aleesya',                 '210722-10-0245', 'Female', 'Kasih (5 years)',  '2021-07-22', 'Pedas, Negeri Sembilan', '0124445566'),
(3,  'Haziq Danial',                '200930-10-0356', 'Male',   'Sayang (6 years)', '2020-09-30', 'Pedas, Negeri Sembilan', '0125556677'),
(50, 'Muhammad Hakimi bin Azlan',   '210122-05-0561', 'Male',   'Kasih (5 years)',  '2021-01-22', 'Pedas, Negeri Sembilan', '0123456701'),
(51, 'Nurul Aisyah binti Razak',    '210318-05-0672', 'Female', 'Kasih (5 years)',  '2021-03-18', 'Pedas, Negeri Sembilan', '0123456702'),
(52, 'Aiman Hafiz bin Johari',      '210507-05-0783', 'Male',   'Kasih (5 years)',  '2021-05-07', 'Pedas, Negeri Sembilan', '0123456703'),
(53, 'Siti Balqis binti Roslan',    '210811-05-0894', 'Female', 'Kasih (5 years)',  '2021-08-11', 'Pedas, Negeri Sembilan', '0123456704'),
(54, 'Danish Irfan bin Sulaiman',   '211025-05-0905', 'Male',   'Kasih (5 years)',  '2021-10-25', 'Pedas, Negeri Sembilan', '0123456705'),
(55, 'Aina Sofea binti Mazlan',     '200214-05-1016', 'Female', 'Sayang (6 years)', '2020-02-14', 'Pedas, Negeri Sembilan', '0123456706'),
(56, 'Harith Iskandar bin Kamarul', '200630-05-1127', 'Male',   'Sayang (6 years)', '2020-06-30', 'Pedas, Negeri Sembilan', '0123456707'),
(57, 'Nur Qistina binti Yusof',     '200902-05-1238', 'Female', 'Sayang (6 years)', '2020-09-02', 'Pedas, Negeri Sembilan', '0123456708'),
(58, 'Zikri Amsyar bin Abdullah',   '201120-05-1349', 'Male',   'Sayang (6 years)', '2020-11-20', 'Pedas, Negeri Sembilan', '0123456709'),
(59, 'Maisarah binti Hashim',       '201228-05-1450', 'Female', 'Sayang (6 years)', '2020-12-28', 'Pedas, Negeri Sembilan', '0123456710');

INSERT INTO `users` (`user_id`, `username`, `password`, `role`, `full_name`, `email`, `student_id`, `phone`, `status`) VALUES
(1, 'roseliza', '$2y$10$2rwCYeZRN7XjPqorTvG2yuz2uNkCoR3x6WTQfHk0pqpF7u6psJt.u', 'Management', 'Puan Roseliza', 'roseliza@tadika.my', NULL, NULL, 'Active'),
(2, 'cikgufarah', '$2y$10$PGqV43j.QKvmNvfe6NaSD.WwoSkOva9zoSMW0KJAGY3AhYcN0Pz1e', 'Teacher',    'Cikgu Farah',   'farah@tadika.my',    NULL, NULL, 'Active'),
(3, 'adamrayyan', '$2y$10$e2lxt.LeoViTa2iEm9dhI.JQwW06UHTdPDrmmo.Iog6mRfYE/u5S.', 'Parent',     'Siti Aminah',   'aminah@email.com',   1,    NULL, 'Active'),
(4, 'nuraleesya', '$2y$10$fqujegXXiH/9O8AHwZY8U.QgFf45UOHDU0shSqfTPD7aE9sHXS5jG', 'Parent',     'Ahmad Zaki',    'zaki@email.com',     2,    NULL, 'Active'),
(5, 'haziqdanial', '$2y$10$vQFLNPOskzr0lhm/D152xepx6gCQ5axqvzchMusscvWmXbduQmwn6', 'Parent',     'Kamal Azman',   'kamal@email.com',    3,    NULL, 'Active');

-- Continue auto-increment counters after the highest used IDs
ALTER TABLE `students`         AUTO_INCREMENT = 65;
ALTER TABLE `users`            AUTO_INCREMENT = 6;
ALTER TABLE `progress_records` AUTO_INCREMENT = 1;
ALTER TABLE `attendance`       AUTO_INCREMENT = 1;

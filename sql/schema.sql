-- Database Schema for School Service Monitoring System
-- Database: OOPapi2

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `uuid` BINARY(16) NOT NULL,
  `role` ENUM('admin','student','driver','parent') NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE IF NOT EXISTS `drivers` (
  `user_uuid` BINARY(16) NOT NULL,
  `max_students` TINYINT(4) DEFAULT 7,
  `code` CHAR(6) DEFAULT NULL,
  `lat` DECIMAL(10,8) DEFAULT NULL,
  `lng` DECIMAL(11,8) DEFAULT NULL,
  `location_updated` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`user_uuid`),
  UNIQUE KEY `code` (`code`),
  CONSTRAINT `fk_drivers_users` FOREIGN KEY (`user_uuid`) REFERENCES `users` (`uuid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE IF NOT EXISTS `students` (
  `user_uuid` BINARY(16) NOT NULL,
  `driver_uuid` BINARY(16) DEFAULT NULL,
  `anon_id` CHAR(8) DEFAULT NULL,
  PRIMARY KEY (`user_uuid`),
  UNIQUE KEY `anon_id` (`anon_id`),
  KEY `fk_students_drivers` (`driver_uuid`),
  CONSTRAINT `fk_students_users` FOREIGN KEY (`user_uuid`) REFERENCES `users` (`uuid`) ON DELETE CASCADE,
  CONSTRAINT `fk_students_drivers` FOREIGN KEY (`driver_uuid`) REFERENCES `drivers` (`user_uuid`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `parents`
--

CREATE TABLE IF NOT EXISTS `parents` (
  `user_uuid` BINARY(16) NOT NULL,
  PRIMARY KEY (`user_uuid`),
  CONSTRAINT `fk_parents_users` FOREIGN KEY (`user_uuid`) REFERENCES `users` (`uuid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `student_parents`
--

CREATE TABLE IF NOT EXISTS `student_parents` (
  `student_uuid` BINARY(16) NOT NULL,
  `parent_uuid` BINARY(16) NOT NULL,
  PRIMARY KEY (`student_uuid`,`parent_uuid`),
  KEY `fk_sp_parents` (`parent_uuid`),
  CONSTRAINT `fk_sp_students` FOREIGN KEY (`student_uuid`) REFERENCES `students` (`user_uuid`) ON DELETE CASCADE,
  CONSTRAINT `fk_sp_parents` FOREIGN KEY (`parent_uuid`) REFERENCES `parents` (`user_uuid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;

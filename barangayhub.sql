-- ============================================================
--  BarangayHub Database
--  Database: barangayhub
--  Engine:   InnoDB | Charset: utf8mb4 | Collation: utf8mb4_general_ci
-- ============================================================

CREATE DATABASE IF NOT EXISTS `barangayhub`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE `barangayhub`;

-- ------------------------------------------------------------
--  Disable FK checks during setup
-- ------------------------------------------------------------
SET FOREIGN_KEY_CHECKS = 0;


-- ============================================================
--  TABLE: admin
-- ============================================================
CREATE TABLE `admin` (
  `admin_id`        INT(11)                             NOT NULL AUTO_INCREMENT,
  `username`        VARCHAR(50)                         NOT NULL,
  `PASSWORD`        VARCHAR(255)                        NOT NULL,
  `email`           VARCHAR(100)                        NOT NULL,
  `first_name`      VARCHAR(50)                         NOT NULL,
  `last_name`       VARCHAR(50)                         NOT NULL,
  `ROLE`            ENUM('Captain','Secretary','Staff') DEFAULT 'Staff',
  `contact_number`  VARCHAR(20)                         DEFAULT NULL,
  `profile_picture` VARCHAR(255)                        DEFAULT NULL,
  `last_login`      DATETIME                            DEFAULT NULL,
  `STATUS`          ENUM('active','inactive')           DEFAULT 'active',
  `created_at`      DATETIME                            DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME                            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email`    (`email`),
  KEY `idx_status`      (`STATUS`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ============================================================
--  TABLE: document_type
-- ============================================================
CREATE TABLE `document_type` (
  `type_id`         INT(11)       NOT NULL AUTO_INCREMENT,
  `NAME`            VARCHAR(100)  NOT NULL,
  `DESCRIPTION`     TEXT          DEFAULT NULL,
  `base_fee`        DECIMAL(8,2)  DEFAULT 0.00,
  `processing_days` INT(11)       DEFAULT 3,
  `requirements`    TEXT          DEFAULT NULL,
  `created_at`      DATETIME      DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`type_id`),
  KEY `idx_name` (`NAME`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ============================================================
--  TABLE: resident
-- ============================================================
CREATE TABLE `resident` (
  `resident_id`          INT(11)                                         NOT NULL AUTO_INCREMENT,
  `resident_code`        VARCHAR(20)                                     NOT NULL,
  `full_name`            VARCHAR(100)                                    NOT NULL,
  `email`                VARCHAR(100)                                    DEFAULT NULL,
  `birthdate`            DATE                                            DEFAULT NULL,
  `gender`               ENUM('male','female','other')                   DEFAULT NULL,
  `civil_status`         ENUM('single','married','widowed','separated')  DEFAULT 'single',
  `address`              TEXT                                            NOT NULL,
  `year_of_residency`    YEAR(4)                                         DEFAULT NULL,
  `contact_number`       VARCHAR(20)                                     NOT NULL,
  `emergency_contact`    VARCHAR(100)                                    DEFAULT NULL,
  `emergency_number`     VARCHAR(20)                                     DEFAULT NULL,
  `family_count`         INT(11)                                         DEFAULT 1,
  `monthly_income`       DECIMAL(12,2)                                   DEFAULT 0.00,
  `occupation`           VARCHAR(100)                                    DEFAULT NULL,
  `voter_status`         ENUM('yes','no')                                DEFAULT 'no',
  `senior_citizen_status` ENUM('yes','no')                               DEFAULT 'no',
  `registration_date`    DATETIME                                        DEFAULT CURRENT_TIMESTAMP,
  `STATUS`               ENUM('active','inactive')                       DEFAULT 'active',
  `created_at`           DATETIME                                        DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME                                        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`resident_id`),
  UNIQUE KEY `resident_code`     (`resident_code`),
  KEY `idx_full_name`            (`full_name`),
  KEY `idx_contact_number`       (`contact_number`),
  KEY `idx_status`               (`STATUS`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ============================================================
--  TABLE: request
-- ============================================================
CREATE TABLE `request` (
  `request_id`       INT(11)                                              NOT NULL AUTO_INCREMENT,
  `request_code`     VARCHAR(50)                                          NOT NULL,
  `resident_id`      INT(11)                                              DEFAULT NULL,
  `resident_email`   VARCHAR(100)                                         NOT NULL,
  `resident_name`    VARCHAR(100)                                         NOT NULL,
  `resident_contact` VARCHAR(20)                                          NOT NULL,
  `resident_address` TEXT                                                 NOT NULL,
  `document_type_id` INT(11)                                              NOT NULL,
  `purpose`          TEXT                                                 NOT NULL,
  `specific_purpose` TEXT                                                 DEFAULT NULL,
  `urgency_level`    ENUM('low','medium','high','urgent')                 DEFAULT 'medium',
  `STATUS`           ENUM('pending','approved','completed','rejected')    DEFAULT 'pending',
  `fee_amount`       DECIMAL(8,2)                                         DEFAULT 0.00,
  `fee_paid`         TINYINT(1)                                           DEFAULT 0,
  `request_date`     DATETIME                                             DEFAULT CURRENT_TIMESTAMP,
  `processed_date`   DATETIME                                             DEFAULT NULL,
  `processed_by`     INT(11)                                              DEFAULT NULL,
  `pickup_date`      DATETIME                                             DEFAULT NULL,
  `rejection_reason` TEXT                                                 DEFAULT NULL,
  `created_at`       DATETIME                                             DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME                                             DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`request_id`),
  UNIQUE KEY `request_code`    (`request_code`),
  KEY `idx_resident_id`        (`resident_id`),
  KEY `idx_status`             (`STATUS`),
  KEY `idx_request_date`       (`request_date`),
  KEY `document_type_id`       (`document_type_id`),
  KEY `processed_by`           (`processed_by`),
  CONSTRAINT `request_ibfk_1` FOREIGN KEY (`document_type_id`) REFERENCES `document_type` (`type_id`) ON DELETE CASCADE,
  CONSTRAINT `request_ibfk_2` FOREIGN KEY (`processed_by`)     REFERENCES `admin`         (`admin_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ============================================================
--  TABLE: document
-- ============================================================
CREATE TABLE `document` (
  `document_id`     INT(11)       NOT NULL AUTO_INCREMENT,
  `request_id`      INT(11)       NOT NULL,
  `file_name`       VARCHAR(255)  NOT NULL,
  `file_path`       VARCHAR(500)  NOT NULL,
  `file_size`       INT(11)       DEFAULT NULL,
  `generation_date` DATETIME      DEFAULT CURRENT_TIMESTAMP,
  `generated_by`    INT(11)       NOT NULL,
  `created_at`      DATETIME      DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`document_id`),
  KEY `idx_request_id` (`request_id`),
  KEY `generated_by`   (`generated_by`),
  CONSTRAINT `document_ibfk_1` FOREIGN KEY (`request_id`)   REFERENCES `request` (`request_id`) ON DELETE CASCADE,
  CONSTRAINT `document_ibfk_2` FOREIGN KEY (`generated_by`) REFERENCES `admin`   (`admin_id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ============================================================
--  TABLE: notification
-- ============================================================
CREATE TABLE `notification` (
  `notification_id` INT(11)                          NOT NULL AUTO_INCREMENT,
  `resident_id`     INT(11)                          DEFAULT NULL,
  `request_id`      INT(11)                          DEFAULT NULL,
  `recipient_email` VARCHAR(100)                     NOT NULL,
  `SUBJECT`         VARCHAR(255)                     NOT NULL,
  `message`         TEXT                             NOT NULL,
  `STATUS`          ENUM('pending','sent','failed')  DEFAULT 'pending',
  `sent_time`       DATETIME                         DEFAULT NULL,
  `created_at`      DATETIME                         DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  KEY `idx_resident_id` (`resident_id`),
  KEY `idx_request_id`  (`request_id`),
  KEY `idx_status`      (`STATUS`),
  CONSTRAINT `notification_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `request` (`request_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ============================================================
--  TABLE: request_history
-- ============================================================
CREATE TABLE `request_history` (
  `history_id`  INT(11)      NOT NULL AUTO_INCREMENT,
  `request_id`  INT(11)      NOT NULL,
  `admin_id`    INT(11)      NOT NULL,
  `ACTION`      VARCHAR(50)  NOT NULL,
  `old_status`  VARCHAR(20)  DEFAULT NULL,
  `new_status`  VARCHAR(20)  NOT NULL,
  `notes`       TEXT         DEFAULT NULL,
  `change_date` DATETIME     DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`history_id`),
  KEY `idx_request_id`  (`request_id`),
  KEY `idx_change_date` (`change_date`),
  KEY `admin_id`        (`admin_id`),
  CONSTRAINT `request_history_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `request` (`request_id`) ON DELETE CASCADE,
  CONSTRAINT `request_history_ibfk_2` FOREIGN KEY (`admin_id`)   REFERENCES `admin`   (`admin_id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ============================================================
--  SAMPLE DATA
--  Note: Admin passwords are bcrypt hashes of 'Admin@1234'
--        Change these before deploying to production.
-- ============================================================

-- ------------------------------------------------------------
--  admin
-- ------------------------------------------------------------
INSERT INTO `admin`
  (`admin_id`, `username`, `PASSWORD`, `email`, `first_name`, `last_name`, `ROLE`, `contact_number`, `STATUS`)
VALUES
  (1, 'captain_reyes',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'captain.reyes@barangayhub.com',   'Ricardo',  'Reyes',   'Captain',   '09171234501', 'active'),
  (2, 'secretary_santos','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'secretary.santos@barangayhub.com','Maria',    'Santos',  'Secretary', '09171234502', 'active'),
  (3, 'staff_garcia',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff.garcia@barangayhub.com',    'Jose',     'Garcia',  'Staff',     '09171234503', 'active');


-- ------------------------------------------------------------
--  document_type
-- ------------------------------------------------------------
INSERT INTO `document_type`
  (`type_id`, `NAME`, `DESCRIPTION`, `base_fee`, `processing_days`, `requirements`)
VALUES
  (1, 'Barangay Clearance',       'General-purpose clearance issued by the barangay.',          50.00, 2, 'Valid ID, Proof of Residency'),
  (2, 'Certificate of Residency', 'Certifies that the applicant is a resident of the barangay.',30.00, 2, 'Valid ID, Utility Bill'),
  (3, 'Certificate of Indigency', 'Certifies that the applicant belongs to an indigent family.', 0.00, 1, 'Valid ID, Proof of Income');


-- ------------------------------------------------------------
--  resident
-- ------------------------------------------------------------
INSERT INTO `resident`
  (`resident_id`, `resident_code`, `full_name`, `email`, `birthdate`, `gender`, `civil_status`,
   `address`, `year_of_residency`, `contact_number`, `emergency_contact`, `emergency_number`,
   `family_count`, `monthly_income`, `occupation`, `voter_status`, `senior_citizen_status`, `STATUS`)
VALUES
  (1, 'RES-0001', 'Juan dela Cruz',    'juan.delacruz@email.com',    '1990-03-15', 'male',   'married',
   'Block 1 Lot 2, Barangay Sample, City', 2010, '09181234501', 'Maria dela Cruz',  '09181234511', 4, 18000.00, 'Tricycle Driver', 'yes', 'no',  'active'),
  (2, 'RES-0002', 'Ana Reyes',         'ana.reyes@email.com',        '1985-07-22', 'female', 'single',
   'Block 3 Lot 5, Barangay Sample, City', 2015, '09181234502', 'Luis Reyes',       '09181234512', 1, 25000.00, 'Teacher',         'yes', 'no',  'active'),
  (3, 'RES-0003', 'Pedro Villanueva',  'pedro.villanueva@email.com', '1955-11-04', 'male',   'widowed',
   'Block 5 Lot 8, Barangay Sample, City', 2000, '09181234503', 'Carlo Villanueva', '09181234513', 2,  5000.00, 'Retired',         'yes', 'yes', 'active'),
  (4, 'RES-0004', 'Luz Bautista',      'luz.bautista@email.com',     '2000-01-30', 'female', 'single',
   'Block 2 Lot 4, Barangay Sample, City', 2020, '09181234504', 'Rosa Bautista',    '09181234514', 3,  8000.00, 'Student',         'no',  'no',  'active'),
  (5, 'RES-0005', 'Carlos Mendoza',    'carlos.mendoza@email.com',   '1978-09-18', 'male',   'married',
   'Block 7 Lot 1, Barangay Sample, City', 2005, '09181234505', 'Nina Mendoza',     '09181234515', 5, 32000.00, 'Engineer',        'yes', 'no',  'active');


-- ------------------------------------------------------------
--  request
-- ------------------------------------------------------------
INSERT INTO `request`
  (`request_id`, `request_code`, `resident_id`, `resident_email`, `resident_name`,
   `resident_contact`, `resident_address`, `document_type_id`, `purpose`,
   `urgency_level`, `STATUS`, `fee_amount`, `fee_paid`,
   `request_date`, `processed_date`, `processed_by`)
VALUES
  (1, 'REQ-2024-0001', 1, 'juan.delacruz@email.com', 'Juan dela Cruz',   '09181234501',
   'Block 1 Lot 2, Barangay Sample, City', 1, 'Employment requirement',
   'medium', 'completed', 50.00, 1, '2024-01-10 09:00:00', '2024-01-11 10:30:00', 2),

  (2, 'REQ-2024-0002', 2, 'ana.reyes@email.com',     'Ana Reyes',        '09181234502',
   'Block 3 Lot 5, Barangay Sample, City', 2, 'Bank account opening',
   'low',    'completed', 30.00, 1, '2024-01-12 10:00:00', '2024-01-13 11:00:00', 3),

  (3, 'REQ-2024-0003', 3, 'pedro.villanueva@email.com','Pedro Villanueva','09181234503',
   'Block 5 Lot 8, Barangay Sample, City', 3, 'Medical assistance application',
   'high',   'approved',   0.00, 0, '2024-01-15 08:30:00', '2024-01-15 14:00:00', 2),

  (4, 'REQ-2024-0004', 4, 'luz.bautista@email.com',  'Luz Bautista',     '09181234504',
   'Block 2 Lot 4, Barangay Sample, City', 1, 'Scholarship application',
   'urgent', 'pending',   50.00, 0, '2024-01-18 13:00:00', NULL, NULL),

  (5, 'REQ-2024-0005', 5, 'carlos.mendoza@email.com','Carlos Mendoza',   '09181234505',
   'Block 7 Lot 1, Barangay Sample, City', 2, 'Loan application requirement',
   'medium', 'rejected',  30.00, 0, '2024-01-20 15:00:00', '2024-01-21 09:00:00', 2);


-- ------------------------------------------------------------
--  document
-- ------------------------------------------------------------
INSERT INTO `document`
  (`document_id`, `request_id`, `file_name`, `file_path`, `file_size`, `generation_date`, `generated_by`)
VALUES
  (1, 1, 'REQ-2024-0001_barangay_clearance.pdf',       'documents/2024/01/REQ-2024-0001_barangay_clearance.pdf',       45200, '2024-01-11 10:30:00', 2),
  (2, 2, 'REQ-2024-0002_certificate_of_residency.pdf', 'documents/2024/01/REQ-2024-0002_certificate_of_residency.pdf', 38400, '2024-01-13 11:00:00', 3),
  (3, 3, 'REQ-2024-0003_certificate_of_indigency.pdf', 'documents/2024/01/REQ-2024-0003_certificate_of_indigency.pdf', 41000, '2024-01-15 14:00:00', 2);


-- ------------------------------------------------------------
--  notification
-- ------------------------------------------------------------
INSERT INTO `notification`
  (`notification_id`, `resident_id`, `request_id`, `recipient_email`, `SUBJECT`, `message`, `STATUS`, `sent_time`)
VALUES
  (1, 1, 1, 'juan.delacruz@email.com',   'Your request REQ-2024-0001 has been completed',
   'Good day! Your request for Barangay Clearance (REQ-2024-0001) has been completed and is ready for pickup.',
   'sent', '2024-01-11 10:35:00'),

  (2, 2, 2, 'ana.reyes@email.com',       'Your request REQ-2024-0002 has been completed',
   'Good day! Your request for Certificate of Residency (REQ-2024-0002) has been completed and is ready for pickup.',
   'sent', '2024-01-13 11:05:00'),

  (3, 5, 5, 'carlos.mendoza@email.com',  'Your request REQ-2024-0005 has been rejected',
   'We regret to inform you that your request REQ-2024-0005 has been rejected. Please visit the barangay hall for more information.',
   'sent', '2024-01-21 09:10:00');


-- ------------------------------------------------------------
--  request_history
-- ------------------------------------------------------------
INSERT INTO `request_history`
  (`history_id`, `request_id`, `admin_id`, `ACTION`, `old_status`, `new_status`, `notes`, `change_date`)
VALUES
  (1, 1, 2, 'status_update', 'pending',  'approved',  'Documents verified. Processing clearance.',      '2024-01-10 14:00:00'),
  (2, 1, 2, 'status_update', 'approved', 'completed', 'Document generated and ready for pickup.',       '2024-01-11 10:30:00'),
  (3, 2, 3, 'status_update', 'pending',  'approved',  'Residency confirmed in records.',                '2024-01-12 15:00:00'),
  (4, 2, 3, 'status_update', 'approved', 'completed', 'Document generated and ready for pickup.',       '2024-01-13 11:00:00'),
  (5, 5, 2, 'status_update', 'pending',  'rejected',  'Incomplete supporting documents submitted.',     '2024-01-21 09:00:00');


-- ------------------------------------------------------------
--  Re-enable FK checks
-- ------------------------------------------------------------
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  End of barangayhub.sql
-- ============================================================

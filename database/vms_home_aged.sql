-- ============================================================
--  VMS Home for the Aged – Full Database Import
--  Import this file in phpMyAdmin, then visit create_admin.php
-- ============================================================

CREATE DATABASE IF NOT EXISTS `vms_home_aged`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `vms_home_aged`;

-- ────────────────────────────────────────────────────────────
-- Table: users
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `username`    VARCHAR(50)   NOT NULL,
    `password`    VARCHAR(255)  NOT NULL,
    `full_name`   VARCHAR(100)  NOT NULL,
    `role`        ENUM('admin','receptionist') NOT NULL DEFAULT 'receptionist',
    `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
    `last_login`  DATETIME      NULL,
    `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────
-- Table: residents
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `residents` (
    `id`                         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `full_name`                  VARCHAR(100)  NOT NULL,
    `room_number`                VARCHAR(10)   NOT NULL,
    `date_of_birth`              DATE          NULL,
    `gender`                     ENUM('Male','Female','Other') NULL,
    `emergency_contact_name`     VARCHAR(100)  NULL,
    `emergency_contact_phone`    VARCHAR(20)   NULL,
    `emergency_contact_relation` VARCHAR(50)   NULL,
    `medical_notes`              TEXT          NULL,
    `admission_date`             DATE          NULL,
    `status`                     ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    `created_at`                 TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                 TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────
-- Table: visitors
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `visitors` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `full_name`     VARCHAR(100)  NOT NULL,
    `id_type`       ENUM('National ID','Passport','Driver''s License','Senior Citizen ID','Other') NOT NULL,
    `id_number`     VARCHAR(50)   NOT NULL,
    `contact_phone` VARCHAR(20)   NULL,
    `address`       TEXT          NULL,
    `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────
-- Table: visit_logs
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `visit_logs` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `visitor_id`       INT UNSIGNED  NOT NULL,
    `resident_id`      INT UNSIGNED  NOT NULL,
    `relationship`     VARCHAR(100)  NULL,
    `purpose`          VARCHAR(255)  NULL,
    `num_companions`   TINYINT       NOT NULL DEFAULT 0,
    `check_in_time`    DATETIME      NOT NULL,
    `check_out_time`   DATETIME      NULL,
    `checked_in_by`    INT UNSIGNED  NOT NULL,
    `checked_out_by`   INT UNSIGNED  NULL,
    `duration_minutes` SMALLINT      NULL,
    `notes`            TEXT          NULL,
    `status`           ENUM('Checked In','Checked Out') NOT NULL DEFAULT 'Checked In',
    `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_visitor`  (`visitor_id`),
    KEY `idx_resident` (`resident_id`),
    KEY `idx_checkin`  (`check_in_time`),
    KEY `idx_status`   (`status`),
    CONSTRAINT `fk_vl_visitor`      FOREIGN KEY (`visitor_id`)    REFERENCES `visitors`(`id`),
    CONSTRAINT `fk_vl_resident`     FOREIGN KEY (`resident_id`)   REFERENCES `residents`(`id`),
    CONSTRAINT `fk_vl_checkedinby`  FOREIGN KEY (`checked_in_by`) REFERENCES `users`(`id`),
    CONSTRAINT `fk_vl_checkedoutby` FOREIGN KEY (`checked_out_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────
-- Table: visit_companions
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `visit_companions` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `visit_log_id` INT UNSIGNED  NOT NULL,
    `full_name`    VARCHAR(100)  NOT NULL,
    `relationship` VARCHAR(100)  NOT NULL,
    `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_vc_visit_log` FOREIGN KEY (`visit_log_id`) REFERENCES `visit_logs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────
-- Table: visitor_restrictions
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `visitor_restrictions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `resident_id` INT UNSIGNED NOT NULL,
    `requested_by_name` VARCHAR(100) NOT NULL,
    `requested_by_relation` VARCHAR(50) NOT NULL,
    `contact_info` VARCHAR(100) NULL,
    `restriction_date` DATE NOT NULL,
    `reason` TEXT NOT NULL,
    `allowed_visitors` TEXT NULL,
    `allowed_relationships` VARCHAR(255) NULL,
    `bypass_code` VARCHAR(50) NULL,
    `status` ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_vr_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
-- Sample Residents (6 demo records)
-- ────────────────────────────────────────────────────────────
INSERT INTO `residents`
    (`full_name`, `room_number`, `date_of_birth`, `gender`,
     `emergency_contact_name`, `emergency_contact_phone`, `emergency_contact_relation`,
     `admission_date`, `status`)
VALUES
    ('Maria Dela Cruz',    '101', '1940-03-15', 'Female', 'Jose Dela Cruz',    '09171234561', 'Son',      '2020-01-10', 'Active'),
    ('Roberto Santos',     '102', '1935-07-22', 'Male',   'Ana Santos',        '09181234562', 'Daughter', '2019-06-05', 'Active'),
    ('Lourdes Reyes',      '103', '1943-11-08', 'Female', 'Carlos Reyes',      '09191234563', 'Nephew',   '2021-03-18', 'Active'),
    ('Eduardo Villanueva', '104', '1938-05-30', 'Male',   'Sofia Villanueva',  '09201234564', 'Spouse',   '2018-09-22', 'Active'),
    ('Remedios Garcia',    '105', '1945-09-12', 'Female', 'Mark Garcia',       '09211234565', 'Son',      '2022-07-01', 'Active'),
    ('Andres Magbanua',    '106', '1932-12-01', 'Male',   'Luz Magbanua',      '09221234566', 'Daughter', '2017-04-14', 'Active');

-- NOTE: Admin & Receptionist users are created by visiting:
--       http://localhost/vms2/create_admin.php
--       (delete that file after running it once)

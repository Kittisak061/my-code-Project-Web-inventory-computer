-- DeviceHub IT Management — MySQL Schema v5
-- phpMyAdmin → Import → เลือกไฟล์นี้ → Go
CREATE DATABASE IF NOT EXISTS `devicehub` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `devicehub`;

-- ── DEVICES ──────────────────────────────────────────────────
DROP TABLE IF EXISTS `devices`;
CREATE TABLE `devices` (
  `id` VARCHAR(20) NOT NULL,
  `fixed_asset_no` VARCHAR(50) DEFAULT NULL,
  `status` ENUM('online','offline','maintenance') NOT NULL DEFAULT 'online',
  `ip` VARCHAR(20) DEFAULT NULL,
  `computer_name` VARCHAR(100) DEFAULT NULL,
  `username` VARCHAR(100) DEFAULT NULL,
  `workgroup` VARCHAR(100) DEFAULT NULL,
  `use_dhcp` TINYINT(1) DEFAULT 0,
  `mac` VARCHAR(20) DEFAULT NULL,
  `type` ENUM('Desktop PC','Laptop','Server','Tablet','Printer','Other') NOT NULL DEFAULT 'Desktop PC',
  `pic` VARCHAR(100) DEFAULT NULL,
  `dept` VARCHAR(100) DEFAULT NULL,
  `location` VARCHAR(150) DEFAULT NULL,
  `year_purchased` YEAR DEFAULT NULL,
  `brand` VARCHAR(80) DEFAULT NULL,
  `model` VARCHAR(100) DEFAULT NULL,
  `cpu` VARCHAR(100) DEFAULT NULL,
  `cpu_speed` DECIMAL(5,2) DEFAULT NULL,
  `ram_mb` INT DEFAULT NULL,
  `serial_no` VARCHAR(100) DEFAULT NULL,
  `service_tag` VARCHAR(80) DEFAULT NULL,
  `hdd_gb` INT DEFAULT NULL,
  `hdd_name` VARCHAR(100) DEFAULT NULL,
  `ssd_gb` INT DEFAULT NULL,
  `ssd_name` VARCHAR(100) DEFAULT NULL,
  `monitor_brand` VARCHAR(80) DEFAULT NULL,
  `monitor_model` VARCHAR(100) DEFAULT NULL,
  `monitor_spec` VARCHAR(150) DEFAULT NULL,
  `monitor_serial` VARCHAR(80) DEFAULT NULL,
  `hw_id_cpu` VARCHAR(100) DEFAULT NULL,
  `hw_id_monitor` VARCHAR(100) DEFAULT NULL,
  `hw_id_lan` VARCHAR(100) DEFAULT NULL,
  `hw_id_wireless` VARCHAR(100) DEFAULT NULL,
  `win_version` VARCHAR(50) DEFAULT NULL,
  `win_bit` ENUM('32','64') DEFAULT NULL,
  `win_license` ENUM('OEM','Retail','Volume','MAK','KMS','') DEFAULT NULL,
  `win_key` VARCHAR(50) DEFAULT NULL,
  `office_version` VARCHAR(50) DEFAULT NULL,
  `office_license` ENUM('OEM','Retail','Volume','MAK','KMS','O365','') DEFAULT NULL,
  `office_key` VARCHAR(50) DEFAULT NULL,
  `av_name` VARCHAR(80) DEFAULT NULL,
  `av_version` VARCHAR(50) DEFAULT NULL,
  `sw_hrm` TINYINT(1) DEFAULT 0,
  `sw_sap` TINYINT(1) DEFAULT 0,
  `sw_stock` TINYINT(1) DEFAULT 0,
  `sw_garoon` TINYINT(1) DEFAULT 0,
  `sw_team` TINYINT(1) DEFAULT 0,
  `sw_other` VARCHAR(200) DEFAULT NULL,
  `access_internet` TINYINT(1) DEFAULT 0,
  `access_email` TINYINT(1) DEFAULT 0,
  `email` VARCHAR(150) DEFAULT NULL,
  `logon` VARCHAR(100) DEFAULT NULL,
  `remark` TEXT DEFAULT NULL,
  `updated_by` VARCHAR(80) DEFAULT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── EMAIL ACCOUNTS ────────────────────────────────────────────
DROP TABLE IF EXISTS `email_accounts`;
CREATE TABLE `email_accounts` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `dept` VARCHAR(100) DEFAULT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `email_old` VARCHAR(150) DEFAULT NULL,
  `computer_name` VARCHAR(100) DEFAULT NULL,
  `computer_name_old` VARCHAR(100) DEFAULT NULL,
  `name` VARCHAR(100) NOT NULL,
  `password` VARCHAR(200) DEFAULT NULL,
  `status_user_email` ENUM('active','inactive','suspended') DEFAULT 'active',
  `user_computer` VARCHAR(100) DEFAULT NULL,
  `mfa_email_user` TINYINT(1) DEFAULT 0,
  `mfa_ipad_app` TINYINT(1) DEFAULT 0,
  `mfa_enable_admin` TINYINT(1) DEFAULT 0,
  `mfa_status` ENUM('enabled','disabled','pending') DEFAULT 'disabled',
  `mfa_announcement` DATE DEFAULT NULL,
  `login_outlook` TINYINT(1) DEFAULT 0,
  `login_web_browser` TINYINT(1) DEFAULT 0,
  `login_ms_team` TINYINT(1) DEFAULT 0,
  `login_ipad` TINYINT(1) DEFAULT 0,
  `login_iphone` TINYINT(1) DEFAULT 0,
  `onedrive_list` VARCHAR(200) DEFAULT NULL,
  `quota` INT DEFAULT 50,
  `used` INT DEFAULT 0,
  `type` VARCHAR(30) DEFAULT 'บุคคล',
  `note` TEXT DEFAULT NULL,
  `update_date` DATE DEFAULT NULL,
  `update_by` VARCHAR(80) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── USERS (มี password column) ────────────────────────────────
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(60) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL DEFAULT '',
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `dept` VARCHAR(100) DEFAULT NULL,
  `role` ENUM('admin','user') DEFAULT 'user',
  `phone` VARCHAR(20) DEFAULT NULL,
  `devices` INT DEFAULT 0,
  `status` ENUM('active','inactive') DEFAULT 'active',
  `last_login` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── REPAIR TICKETS ────────────────────────────────────────────
DROP TABLE IF EXISTS `repair_tickets`;
CREATE TABLE `repair_tickets` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `ticket` VARCHAR(20) DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `device` VARCHAR(100) DEFAULT NULL,
  `reporter` VARCHAR(100) DEFAULT NULL,
  `priority` ENUM('high','medium','low') DEFAULT 'medium',
  `status` ENUM('open','in_progress','resolved') DEFAULT 'open',
  `tech` VARCHAR(100) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── SEED: DEVICES ─────────────────────────────────────────────
INSERT INTO `devices` (`id`,`fixed_asset_no`,`status`,`ip`,`computer_name`,`username`,`mac`,`type`,`pic`,`dept`,`year_purchased`,`brand`,`model`,`cpu`,`ram_mb`,`serial_no`,`win_version`,`win_bit`,`win_license`,`office_version`,`av_name`,`access_internet`,`access_email`,`email`) VALUES
('DEV-0001','FA-2024-001','online','192.168.1.10','PC-IT-001','somchai','AA:BB:CC:01','Desktop PC','สมชาย ใจดี','AD-IT',2022,'Dell','OptiPlex 7090','Intel Core i7-11700',16384,'SN001','Windows 11 Pro','64','OEM','Office 2021','Symantec',1,1,'somchai@company.com'),
('DEV-0002','FA-2024-002','online','192.168.1.22','LT-ACC-005','suda','AA:BB:CC:02','Laptop','สุดา มีสุข','BC CENTER - PURCHASE',2023,'HP','EliteBook 840 G9','Intel Core i5-1235U',8192,'SN002','Windows 11 Pro','64','OEM','Office 2021','Symantec',1,1,'suda@company.com'),
('DEV-0003','FA-2022-001','online','192.168.1.1','SRV-MAIN-01','admin','AA:BB:CC:03','Server','นพดล ดูแลดี','AD-IT',2021,'HP','ProLiant DL380','Intel Xeon Silver',65536,'SN003','Windows Server 2019','64','Volume',NULL,'Symantec',0,0,'server@company.com'),
('DEV-0004','FA-2021-003','offline','192.168.1.35','PC-FIN-003','wichai','AA:BB:CC:04','Desktop PC','วิชัย งามดี','AD - GA',2021,'Lenovo','ThinkCentre M70q','Intel Core i5-10400',8192,'SN004','Windows 10 Pro','64','OEM','Office 2019','Kaspersky',1,0,'wichai@company.com'),
('DEV-0005','FA-2023-004','online','192.168.1.48','LT-HR-002','panadda','AA:BB:CC:05','Laptop','ปนัดดา เก่งมาก','AD - HRD',2023,'Lenovo','ThinkPad E15','Intel Core i5-1235U',8192,'SN005','Windows 11 Pro','64','OEM','Office 365','Symantec',1,1,'panadda@company.com'),
('DEV-0006','FA-2020-002','maintenance','192.168.1.52','PC-MKT-007','arpa','AA:BB:CC:06','Desktop PC','อาภา รักงาน','MARKETING',2020,'Acer','Veriton X2640G','Intel Core i3-7100',4096,'SN006','Windows 10 Pro','64','OEM','Office 2016','Kaspersky',1,1,'arpa@company.com'),
('DEV-0007','FA-2023-005','online','192.168.1.61','LT-IT-003','nopadol','AA:BB:CC:07','Laptop','นพดล ดูแลดี','AD-IT',2023,'Dell','Latitude 5530','Intel Core i7-1265U',16384,'SN007','Windows 11 Pro','64','Volume','Office 2021','Symantec',1,1,'nopadol@company.com'),
('DEV-0008','FA-2022-006','online','192.168.1.74','PC-OPS-010','kamonchonok','AA:BB:CC:08','Desktop PC','กมลชนก ทำงานดี','PRODUCTION DIVISION 1',2022,'HP','ProDesk 400 G7','Intel Core i5-10500',8192,'SN008','Windows 11 Pro','64','OEM','Office 2021','Symantec',1,1,'kamonchonok@company.com');

-- ── SEED: EMAIL ACCOUNTS ──────────────────────────────────────
INSERT INTO `email_accounts` (`email`,`name`,`dept`,`type`,`quota`,`used`,`status_user_email`,`computer_name`,`mfa_email_user`,`mfa_enable_admin`,`mfa_status`,`login_outlook`,`login_web_browser`,`login_ms_team`) VALUES
('somchai@company.com','สมชาย ใจดี','AD-IT','บุคคล',50,12,'active','PC-IT-001',1,1,'enabled',1,1,1),
('suda@company.com','สุดา มีสุข','BC CENTER - PURCHASE','บุคคล',50,28,'active','LT-ACC-005',1,1,'enabled',1,1,0),
('wichai@company.com','วิชัย งามดี','AD - GA','บุคคล',50,5,'suspended','PC-FIN-003',0,0,'disabled',0,0,0),
('panadda@company.com','ปนัดดา เก่งมาก','AD - HRD','บุคคล',50,19,'active','LT-HR-002',1,1,'pending',1,1,1),
('arpa@company.com','อาภา รักงาน','MARKETING','บุคคล',50,35,'active','PC-MKT-007',0,0,'disabled',1,1,1),
('nopadol@company.com','นพดล ดูแลดี','AD-IT','บุคคล',100,44,'active','LT-IT-003',1,1,'enabled',1,1,1),
('it-group@company.com','กลุ่ม IT','AD-IT','กลุ่ม',200,88,'active',NULL,0,0,'disabled',0,0,0),
('server@company.com','ระบบ Server','AD-IT','ระบบ',500,201,'active','SRV-MAIN-01',0,0,'disabled',0,0,0);

/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
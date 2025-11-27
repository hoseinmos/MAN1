-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 04, 2025 at 08:28 PM
-- Server version: 5.7.40
-- PHP Version: 8.0.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `secure_login`
--

-- --------------------------------------------------------

--
-- Table structure for table `access_codes`
--

DROP TABLE IF EXISTS `access_codes`;
CREATE TABLE IF NOT EXISTS `access_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;

--
-- Dumping data for table `access_codes`
--

INSERT INTO `access_codes` (`id`, `code`, `description`, `active`, `created_at`) VALUES
(1, 'akbari', 'مدیر سیستم', 1, '2025-04-11 15:10:31'),
(2, 'admin', 'حسین مصطفائی فر ', 1, '2025-04-11 15:10:31'),
(3, 'admin1', 'مسئول انبار', 1, '2025-04-11 15:10:31'),
(4, '5023', 'مجید صادقی', 1, '2025-04-18 13:28:45'),
(5, '6382', 'مسئول تعمیرات', 1, '2025-05-03 02:51:33');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

DROP TABLE IF EXISTS `announcements`;
CREATE TABLE IF NOT EXISTS `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `importance` enum('normal','important','critical') NOT NULL DEFAULT 'normal',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `expire_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `importance`, `active`, `created_by`, `created_at`, `updated_at`, `expire_date`) VALUES
(1, 'بازاریابی نیاز داریم', 'هر پشتیبان 10 عدد', 'critical', 1, 1, '2025-05-04 12:36:29', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `device_repairs`
--

DROP TABLE IF EXISTS `device_repairs`;
CREATE TABLE IF NOT EXISTS `device_repairs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `terminal_serial` varchar(100) NOT NULL,
  `adapter_serial` varchar(100) DEFAULT NULL,
  `is_terminal_damaged` tinyint(1) NOT NULL DEFAULT '0',
  `is_adapter_damaged` tinyint(1) NOT NULL DEFAULT '0',
  `damage_description` text NOT NULL,
  `reported_by` int(11) NOT NULL,
  `status` enum('pending','in_progress','repaired','replaced','returned','healthy') NOT NULL DEFAULT 'pending',
  `technician_id` int(11) DEFAULT NULL,
  `technician_notes` text,
  `repair_date` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `reported_by` (`reported_by`),
  KEY `technician_id` (`technician_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8 COMMENT='جدول تعمیرات دستگاه‌ها';

--
-- Dumping data for table `device_repairs`
--

INSERT INTO `device_repairs` (`id`, `terminal_serial`, `adapter_serial`, `is_terminal_damaged`, `is_adapter_damaged`, `damage_description`, `reported_by`, `status`, `technician_id`, `technician_notes`, `repair_date`, `created_at`, `updated_at`) VALUES
(1, '9000000', '200000000', 1, 1, 'fhjhv=باتری مشکل دارد', 2, 'pending', NULL, NULL, NULL, '2025-05-03 02:58:22', NULL),
(2, '90000381688', '00000', 1, 1, 'باتری دستگاه و صفحه نمایش مشکل دارد', 5, 'pending', NULL, NULL, NULL, '2025-05-03 07:09:56', NULL),
(3, '90000381688', '2222', 1, 0, '2222222222222', 5, 'pending', NULL, NULL, NULL, '2025-05-03 07:14:24', NULL),
(4, '1000000', '11000', 1, 0, '// در فایل device_report.js یا JavaScrات گزارش..', 2, 'pending', NULL, NULL, NULL, '2025-05-03 07:15:59', NULL),
(5, '50000000', '0212151', 1, 1, 'باتری خراب است', 2, 'pending', NULL, NULL, NULL, '2025-05-03 07:21:30', NULL),
(6, '5c368878', '0', 1, 1, 'جای سوکت شارژ خراب است', 2, 'pending', NULL, NULL, NULL, '2025-05-03 07:24:56', NULL),
(7, '900000', '555555555555', 1, 1, '5555555', 3, 'pending', NULL, NULL, NULL, '2025-05-04 03:21:16', NULL),
(8, '900000000', '12255151', 1, 0, 'ندارد', 2, 'repaired', 5, '1', '2025-05-05 00:00:00', '2025-05-04 03:33:39', '2025-05-04 13:03:14'),
(9, '90000000', '222222222222222', 1, 1, 'http://localhost/device_report.php', 2, 'pending', NULL, NULL, NULL, '2025-05-04 13:25:57', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `issue_tracking`
--

DROP TABLE IF EXISTS `issue_tracking`;
CREATE TABLE IF NOT EXISTS `issue_tracking` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `issue_id` int(11) NOT NULL,
  `status` varchar(20) NOT NULL,
  `comment` text NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `issue_id` (`issue_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `login_logs`
--

DROP TABLE IF EXISTS `login_logs`;
CREATE TABLE IF NOT EXISTS `login_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `access_code` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `success` tinyint(1) NOT NULL,
  `attempt_time` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `access_code` (`access_code`),
  KEY `ip_address` (`ip_address`)
) ENGINE=InnoDB AUTO_INCREMENT=267 DEFAULT CHARSET=utf8;

--
-- Dumping data for table `login_logs`
--

INSERT INTO `login_logs` (`id`, `access_code`, `ip_address`, `success`, `attempt_time`) VALUES
(226, 'admin', '::1', 1, '2025-05-03 02:15:56'),
(227, 'admin1', '127.0.0.1', 1, '2025-05-03 02:29:56'),
(228, '50233', '127.0.0.1', 0, '2025-05-03 02:30:19'),
(229, '5023', '127.0.0.1', 1, '2025-05-03 02:30:24'),
(230, 'admin1', '::1', 1, '2025-05-03 02:46:13'),
(231, 'شیئهد', '::1', 0, '2025-05-03 02:57:55'),
(232, 'admin', '::1', 1, '2025-05-03 02:58:02'),
(233, 'admin1', '127.0.0.1', 1, '2025-05-03 02:58:53'),
(234, '6382', '::1', 1, '2025-05-03 07:05:37'),
(235, 'admin1', '127.0.0.1', 1, '2025-05-03 07:12:03'),
(236, 'admin', '127.0.0.1', 1, '2025-05-03 07:12:45'),
(237, 'admin', '::1', 1, '2025-05-03 07:15:36'),
(238, 'admin1', '127.0.0.1', 1, '2025-05-03 07:21:47'),
(239, 'admin1', '127.0.0.1', 1, '2025-05-03 12:03:10'),
(240, 'admin', '::1', 1, '2025-05-03 12:03:43'),
(241, 'admin1', '127.0.0.1', 1, '2025-05-03 12:24:15'),
(242, 'admin1', '::1', 1, '2025-05-03 12:27:53'),
(243, 'admin', '::1', 1, '2025-05-03 22:28:46'),
(244, 'admin1', '::1', 1, '2025-05-03 22:28:59'),
(245, 'admin1', '::1', 1, '2025-05-03 22:37:42'),
(246, 'admin', '::1', 1, '2025-05-04 03:01:44'),
(247, 'admin1', '127.0.0.1', 1, '2025-05-04 03:08:45'),
(248, 'admin', '127.0.0.1', 1, '2025-05-04 03:21:36'),
(249, 'admin1', '::1', 1, '2025-05-04 03:26:19'),
(250, 'شیئهد', '::1', 0, '2025-05-04 03:30:32'),
(251, 'admin', '::1', 1, '2025-05-04 03:30:39'),
(252, 'admin1', '127.0.0.1', 1, '2025-05-04 03:31:30'),
(253, '5023', '127.0.0.1', 1, '2025-05-04 03:34:23'),
(254, '6382', '127.0.0.1', 0, '2025-05-04 03:34:34'),
(255, '6382', '127.0.0.1', 1, '2025-05-04 03:34:40'),
(256, 'admin1', '127.0.0.1', 1, '2025-05-04 03:35:35'),
(257, '6382', '::1', 1, '2025-05-04 08:20:15'),
(258, 'admin', '127.0.0.1', 1, '2025-05-04 08:21:57'),
(259, 'admin', '::1', 1, '2025-05-04 08:23:04'),
(260, 'admin1', '::1', 1, '2025-05-04 08:23:15'),
(261, '6382', '::1', 1, '2025-05-04 08:24:30'),
(262, 'akbari', '::1', 1, '2025-05-04 12:33:46'),
(263, '6382', '::1', 1, '2025-05-04 12:36:50'),
(264, '6382', '::1', 1, '2025-05-04 12:39:34'),
(265, 'admin', '::1', 1, '2025-05-04 13:22:25'),
(266, '6382', '127.0.0.1', 1, '2025-05-04 13:26:32');

-- --------------------------------------------------------

--
-- Table structure for table `marketing`
--

DROP TABLE IF EXISTS `marketing`;
CREATE TABLE IF NOT EXISTS `marketing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `national_code` varchar(10) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `birth_date` date DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `business_type` varchar(100) DEFAULT NULL,
  `device_model` varchar(100) DEFAULT NULL,
  `terminal_count` int(11) DEFAULT '1',
  `store_name` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `mobile` varchar(11) NOT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `tax_code` varchar(20) DEFAULT NULL,
  `address` text,
  `bank_name` varchar(100) DEFAULT NULL,
  `branch_code` varchar(20) DEFAULT NULL,
  `account_number` varchar(30) DEFAULT NULL,
  `sheba_number` varchar(26) DEFAULT NULL,
  `support_person` varchar(100) DEFAULT NULL,
  `national_card_image` varchar(255) DEFAULT NULL,
  `business_license_image` varchar(255) DEFAULT NULL,
  `birth_certificate_image` varchar(255) DEFAULT NULL,
  `other_documents_image` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `status_description` text,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `national_code` (`national_code`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `paper_rolls`
--

DROP TABLE IF EXISTS `paper_rolls`;
CREATE TABLE IF NOT EXISTS `paper_rolls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `terminal_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `delivery_date` date NOT NULL,
  `description` text,
  `user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `terminal_id` (`terminal_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=209 DEFAULT CHARSET=utf8;

--
-- Dumping data for table `paper_rolls`
--

INSERT INTO `paper_rolls` (`id`, `terminal_id`, `quantity`, `delivery_date`, `description`, `user_id`, `created_at`) VALUES
(202, 1, 1, '2025-05-03', '', 2, '2025-05-03 02:16:17'),
(203, 4, 10, '2025-05-03', '', 2, '2025-05-03 02:17:09'),
(204, 5, 10, '2025-05-03', '', 4, '2025-05-03 02:30:38'),
(205, 5, 1, '2025-05-03', '', 4, '2025-05-03 02:30:54'),
(206, 1, 100, '2025-05-03', '', 2, '2025-05-03 02:59:28'),
(207, 1, 1, '2025-05-04', '', 2, '2025-05-04 03:09:05'),
(208, 3, 10, '2025-05-04', '', 2, '2025-05-04 03:23:50');

-- --------------------------------------------------------

--
-- Table structure for table `repair_history`
--

DROP TABLE IF EXISTS `repair_history`;
CREATE TABLE IF NOT EXISTS `repair_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `repair_id` int(11) NOT NULL,
  `status` enum('pending','in_progress','repaired','replaced','returned') NOT NULL,
  `notes` text,
  `user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `repair_id` (`repair_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8 COMMENT='تاریخچه تغییرات تعمیرات';

--
-- Dumping data for table `repair_history`
--

INSERT INTO `repair_history` (`id`, `repair_id`, `status`, `notes`, `user_id`, `created_at`) VALUES
(1, 1, 'pending', 'گزارش خرابی دستگاه ثبت شد.', 2, '2025-05-03 02:58:22'),
(2, 2, 'pending', 'گزارش خرابی دستگاه ثبت شد.', 5, '2025-05-03 07:09:56'),
(3, 3, 'pending', 'گزارش خرابی دستگاه ثبت شد.', 5, '2025-05-03 07:14:24'),
(4, 4, 'pending', 'گزارش خرابی دستگاه ثبت شد.', 2, '2025-05-03 07:15:59'),
(5, 5, 'pending', 'گزارش خرابی دستگاه ثبت شد.', 2, '2025-05-03 07:21:30'),
(6, 6, 'pending', 'گزارش خرابی دستگاه ثبت شد.', 2, '2025-05-03 07:24:56'),
(7, 7, 'pending', 'گزارش خرابی دستگاه ثبت شد.', 3, '2025-05-04 03:21:16'),
(8, 8, 'pending', 'گزارش خرابی دستگاه ثبت شد.', 2, '2025-05-04 03:33:39'),
(9, 8, 'repaired', '1', 5, '2025-05-04 13:03:14'),
(10, 9, 'pending', 'گزارش خرابی دستگاه ثبت شد.', 2, '2025-05-04 13:25:57');

-- --------------------------------------------------------

--
-- Table structure for table `roll_assignments`
--

DROP TABLE IF EXISTS `roll_assignments`;
CREATE TABLE IF NOT EXISTS `roll_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `assign_date` date NOT NULL,
  `description` text,
  `assigned_by` int(11) NOT NULL,
  `confirmed` tinyint(1) NOT NULL DEFAULT '0',
  `confirm_date` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `assigned_by` (`assigned_by`),
  KEY `confirmed` (`confirmed`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8 COMMENT='جدول تخصیص رول کاغذ به کاربران';

--
-- Dumping data for table `roll_assignments`
--

INSERT INTO `roll_assignments` (`id`, `user_id`, `quantity`, `assign_date`, `description`, `assigned_by`, `confirmed`, `confirm_date`, `created_at`, `updated_at`) VALUES
(15, 2, 100, '2025-05-05', 'به درخواست خانم حیدری', 3, 1, '2025-05-04 03:31:02', '2025-05-04 03:26:44', '2025-05-04 03:31:02'),
(16, 2, 100, '2025-05-05', '', 3, 1, '2025-05-04 03:36:12', '2025-05-04 03:35:48', '2025-05-04 03:36:12'),
(17, 2, 100, '2025-05-05', '', 3, 1, '2025-05-04 03:36:08', '2025-05-04 03:35:57', '2025-05-04 03:36:08'),
(18, 2, 100, '2025-05-05', '', 3, 1, '2025-05-04 08:23:45', '2025-05-04 08:23:30', '2025-05-04 08:23:45');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `last_activity` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_id` (`session_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=241 DEFAULT CHARSET=utf8;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `session_id`, `user_id`, `ip_address`, `last_activity`) VALUES
(207, 'ml5lbqaf5lnnfmadkcj298vlim', 3, '::1', '2025-05-03 02:55:30'),
(208, 'rkrct00q82a52skfv7jhr9465q', 2, '::1', '2025-05-03 03:09:13'),
(209, '591ln9uqk9pv1j8cnjv0qkr2sv', 3, '127.0.0.1', '2025-05-03 03:06:32'),
(210, 'cl1dn1t5f1o5ok8gjs85o2hnhm', 5, '::1', '2025-05-03 07:15:11'),
(213, '9pjat1t09f6o1so7bc78fng5lp', 2, '::1', '2025-05-03 09:55:12'),
(214, 'uvd3oeo62m5494vobpq1p6al4s', 3, '127.0.0.1', '2025-05-03 07:26:40'),
(217, 'u7og0d6i5ivm2vshiib9m4ga45', 3, '127.0.0.1', '2025-05-03 12:30:48'),
(218, '5m3llrpnhgng9odcuqpl2n8dab', 3, '::1', '2025-05-03 12:27:57'),
(221, '8jfnmgrln10nqe6c4luiov5d46', 3, '::1', '2025-05-03 22:44:04'),
(224, 'ckd7kfuu7hh2js5qcg0847kdlj', 2, '127.0.0.1', '2025-05-04 03:27:16'),
(225, 'av0jsu2kq4r56heqbgbtlp4nqa', 3, '::1', '2025-05-04 03:27:45'),
(226, '76nh9o518kc2rm99n0ucjd784m', 2, '::1', '2025-05-04 03:38:51'),
(230, 'bflhjjlpgfio0c8c6bvfib8cbg', 3, '127.0.0.1', '2025-05-04 03:38:43'),
(232, '1b3vfllb9ugc1eminqdop0r9v5', 2, '127.0.0.1', '2025-05-04 08:33:41'),
(235, '499juo1vog4egvehqn85b57bff', 5, '::1', '2025-05-04 08:36:04'),
(238, 'hn7qvhpcus00d0b41e6r7r0cgl', 5, '::1', '2025-05-04 13:21:50'),
(239, 'j1k7jnm3n1l0vj96mkcfimcm1b', 2, '::1', '2025-05-04 13:25:57'),
(240, 'fmgj8436hqcgfd4207pvsbd9tn', 5, '127.0.0.1', '2025-05-04 13:26:47');

-- --------------------------------------------------------

--
-- Table structure for table `system_updates`
--

DROP TABLE IF EXISTS `system_updates`;
CREATE TABLE IF NOT EXISTS `system_updates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `update_type` varchar(50) NOT NULL,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `description` text,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8;

--
-- Dumping data for table `system_updates`
--

INSERT INTO `system_updates` (`id`, `update_type`, `update_time`, `description`, `updated_by`) VALUES
(1, 'database', '2025-03-21 03:07:45', 'به‌روزرسانی اولیه دیتابیس', NULL),
(2, 'database', '2025-04-21 03:19:52', 'سلام اطلاعات بروز شد', 1),
(3, 'database', '2025-04-21 03:50:38', '', 1),
(4, 'database', '2025-04-21 03:51:50', '', 1),
(5, 'database', '2025-04-21 07:34:09', '1404/2/1 03:19:52	سلام اطلاعات بروز شد	مدیر سیستم', 1),
(6, 'database', '2025-05-04 12:34:00', '', 1);

-- --------------------------------------------------------

--
-- Table structure for table `terminals`
--

DROP TABLE IF EXISTS `terminals`;
CREATE TABLE IF NOT EXISTS `terminals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `terminal_number` varchar(50) NOT NULL COMMENT 'شماره پایانه',
  `bank` varchar(100) NOT NULL COMMENT 'بانک',
  `store_name` varchar(255) NOT NULL COMMENT 'نام فروشگاه',
  `device_model` varchar(100) DEFAULT NULL COMMENT 'مدل دستگاه',
  `device_type` varchar(100) DEFAULT NULL COMMENT 'نوع دستگاه',
  `support_person` varchar(100) DEFAULT NULL COMMENT 'پشتیبان',
  `quarterly_transactions` int(11) DEFAULT '0' COMMENT 'تراکنش سه ماهه',
  `current_month_transactions` int(11) DEFAULT '0' COMMENT 'تراکنش ماه جاری',
  `previous_day_transactions` int(11) DEFAULT '0' COMMENT 'تراکنش روز قبل',
  `status` varchar(20) NOT NULL DEFAULT 'فعال' COMMENT 'وضعیت',
  `roll_count` int(11) DEFAULT '0' COMMENT 'تعداد رول',
  `description` text COMMENT 'توضیحات',
  `pm_date` date DEFAULT NULL COMMENT 'تاریخ PM',
  `terminal_group` varchar(100) DEFAULT NULL COMMENT 'گروه ترمینال',
  `merchant` varchar(255) DEFAULT NULL COMMENT 'پذیرنده',
  `indicator` varchar(100) DEFAULT NULL COMMENT 'اندیکاتور',
  `account_holder` varchar(255) DEFAULT NULL COMMENT 'نام دارنده حساب',
  `modem_mac` varchar(100) DEFAULT NULL COMMENT 'مک مودم',
  `daily_transaction_deviation` float DEFAULT '0' COMMENT 'انحراف تراکنش روزانه',
  `monthly_transaction_deviation` float DEFAULT '0' COMMENT 'انحراف تراکنش ماهیانه',
  `pinpad_serial` varchar(100) DEFAULT NULL COMMENT 'سریال پین پد',
  `two_day_transactions` int(11) DEFAULT '0' COMMENT 'تراکنش 2روز',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `pm_description` text COMMENT 'توضیحات پی ام',
  `missing_documents` text COMMENT 'نقص مدارک',
  `returned_documents` text COMMENT 'برگشتی مدارک',
  PRIMARY KEY (`id`),
  UNIQUE KEY `terminal_number` (`terminal_number`),
  KEY `store_name` (`store_name`),
  KEY `status` (`status`),
  KEY `bank` (`bank`),
  KEY `support_person` (`support_person`),
  KEY `terminal_group` (`terminal_group`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 COMMENT='جدول پایانه‌های فروش';

--
-- Dumping data for table `terminals`
--

INSERT INTO `terminals` (`id`, `terminal_number`, `bank`, `store_name`, `device_model`, `device_type`, `support_person`, `quarterly_transactions`, `current_month_transactions`, `previous_day_transactions`, `status`, `roll_count`, `description`, `pm_date`, `terminal_group`, `merchant`, `indicator`, `account_holder`, `modem_mac`, `daily_transaction_deviation`, `monthly_transaction_deviation`, `pinpad_serial`, `two_day_transactions`, `created_at`, `updated_at`, `pm_description`, `missing_documents`, `returned_documents`) VALUES
(1, '28280010', 'بانک ملت', 'فروشگاه رضایی', 'PAX S80', 'GPRS', 'حسین مصطفائی فر ', 3200, 950, 35, 'فعال', 11, 'پایانه فعال با تراکنش مناسب', '1404-02-15', 'فروشگاهی', 'آقای رضایی', 'A12', 'رضا رضایی', '00:1A:2B:3C:4D:5E', 5.2, 3.8, 'SP2023456789', 65, '2025-04-15 14:37:12', '2025-04-27 00:00:52', 'نصب شده در تاریخ ۱۴۰۴/۰۲/۱۵ توسط محمد امینی', 'استعلام هویت پذیرنده، تصویر کارت ملی', 'فرم قرارداد تجارت الکترونیک نیاز به اصلاح دارد'),
(2, '28280011', 'بانک ملی', 'سوپرمارکت احمدی', 'Verifone VX520', 'ADSL', 'حسین مصطفائی فر ', 2800, 720, 28, 'فعال', 1, 'نیاز به بررسی کارت خوان', '1404-03-10', 'سوپرمارکت', 'آقای احمدی', 'B34', 'محمد احمدی', '00:2C:3D:4E:5F:6G', 2.1, 4.5, 'SP1987654321', 53, '2025-04-15 14:37:12', '2025-04-27 00:00:54', 'سرویس دوره‌ای انجام شده در تاریخ ۱۴۰۴/۰۱/۲۰', 'گواهی مالیاتی', 'فرم درخواست پایانه نیاز به مهر شرکت دارد'),
(3, '28280012', 'بانک سپه', 'رستوران نایب', 'Ingenico ICT220', 'GPRS', 'حسین مصطفائی فر ', 5200, 1400, 42, 'غیرفعال', 1, 'نیاز به تعویض سیمکارت', '1404-01-20', 'رستوران', 'خانم نایبی', 'C56', 'سعید نایبی', '00:3D:4E:5F:6G:7H', 8.3, 6.2, 'SP7654321098', 0, '2025-04-15 14:37:12', '2025-04-27 00:00:58', 'نیاز به بازدید فوری دارد', 'اساسنامه شرکت، مدارک هویتی مدیرعامل', 'قرارداد ناقص است'),
(4, '28280013', 'بانک پارسیان', 'فروشگاه لوازم خانگی محمدی', 'PAX D210', 'ADSL', 'حسین مصطفائی فر ', 4100, 1100, 38, 'فعال', 1, 'پایانه جدید نصب شده', '1404-02-25', 'لوازم خانگی', 'آقای محمدی', 'D78', 'علی محمدی', '00:4E:5F:6G:7H:8I', 1.8, 2.5, 'SP5678901234', 72, '2025-04-15 14:37:12', '2025-04-27 00:00:57', 'پایانه جدید نصب شده - کارکرد عادی', '', ''),
(5, '28280014', 'بانک صادرات', 'داروخانه سلامت', 'Verifone VX675', 'GPRS', 'مجید صادقی', 1800, 560, 20, 'فعال', 301, 'کارت‌خوان سیار', '1404-03-05', 'پزشکی', 'دکتر سعیدی', 'E90', 'حسن سعیدی', '00:5F:6G:7H:8I:9J', 3.4, 2.9, 'SP6789012345', 38, '2025-04-15 14:37:12', '2025-04-27 14:33:54', 'نیاز به تعویض سیم‌کارت در سرویس بعدی', 'گواهی پروانه کسب', 'قرارداد نیاز به امضای جدید دارد');

-- --------------------------------------------------------

--
-- Table structure for table `terminal_issues`
--

DROP TABLE IF EXISTS `terminal_issues`;
CREATE TABLE IF NOT EXISTS `terminal_issues` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `terminal_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `comments` text,
  `priority` varchar(20) NOT NULL DEFAULT 'medium',
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `terminal_id` (`terminal_id`),
  KEY `status` (`status`),
  KEY `priority` (`priority`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `user_devices`
--

DROP TABLE IF EXISTS `user_devices`;
CREATE TABLE IF NOT EXISTS `user_devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `device_name` varchar(200) DEFAULT NULL,
  `browser` varchar(100) DEFAULT NULL,
  `os` varchar(100) DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `login_count` int(11) DEFAULT '1',
  `first_login` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=21 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `user_devices`
--

INSERT INTO `user_devices` (`id`, `user_id`, `device_type`, `device_name`, `browser`, `os`, `ip_address`, `login_count`, `first_login`, `last_login`) VALUES
(17, 5, 'Desktop', 'Windows 10', 'Microsoft Edge', 'Windows 10', '::1', 5, '2025-05-03 07:05:37', '2025-05-04 12:39:34'),
(16, 3, 'Desktop', 'Windows 10', 'Microsoft Edge', 'Windows 10', '::1', 6, '2025-05-03 02:46:13', '2025-05-04 08:23:15'),
(15, 4, 'Desktop', 'Windows 10', 'Mozilla Firefox', 'Windows 10', '127.0.0.1', 2, '2025-05-03 02:30:24', '2025-05-04 03:34:23'),
(14, 3, 'Desktop', 'Windows 10', 'Mozilla Firefox', 'Windows 10', '127.0.0.1', 9, '2025-05-03 02:29:56', '2025-05-04 03:35:35'),
(13, 2, 'Desktop', 'Windows 10', 'Microsoft Edge', 'Windows 10', '::1', 9, '2025-05-03 02:15:56', '2025-05-04 13:22:25'),
(18, 2, 'Desktop', 'Windows 10', 'Mozilla Firefox', 'Windows 10', '127.0.0.1', 3, '2025-05-03 07:12:45', '2025-05-04 08:21:57'),
(19, 5, 'Desktop', 'Windows 10', 'Mozilla Firefox', 'Windows 10', '127.0.0.1', 2, '2025-05-04 03:34:40', '2025-05-04 13:26:32');

-- --------------------------------------------------------

--
-- Table structure for table `user_logs`
--

DROP TABLE IF EXISTS `user_logs`;
CREATE TABLE IF NOT EXISTS `user_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `action_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `log_time` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=487 DEFAULT CHARSET=utf8;

--
-- Dumping data for table `user_logs`
--

INSERT INTO `user_logs` (`id`, `user_id`, `action`, `action_id`, `ip_address`, `log_time`) VALUES
(427, 2, 'submit_roll', 202, '::1', '2025-05-03 02:16:17'),
(428, 2, 'submit_roll', 203, '::1', '2025-05-03 02:17:09'),
(429, 3, 'logout', NULL, '127.0.0.1', '2025-05-03 02:30:13'),
(430, 4, 'submit_roll', 204, '127.0.0.1', '2025-05-03 02:30:38'),
(431, 4, 'submit_roll', 205, '127.0.0.1', '2025-05-03 02:30:54'),
(432, 2, 'logout', NULL, '::1', '2025-05-03 02:45:59'),
(433, 3, 'assign_roll', 1, '::1', '2025-05-03 02:51:52'),
(434, 3, 'assign_roll', 2, '::1', '2025-05-03 02:52:10'),
(435, 3, 'assign_roll', 3, '::1', '2025-05-03 02:55:52'),
(436, 2, 'report_repair', 1, '::1', '2025-05-03 02:58:22'),
(437, 4, 'logout', NULL, '127.0.0.1', '2025-05-03 02:58:45'),
(438, 3, 'assign_roll', 4, '127.0.0.1', '2025-05-03 02:59:06'),
(439, 2, 'submit_roll', 206, '::1', '2025-05-03 02:59:28'),
(440, 5, 'report_repair', 2, '::1', '2025-05-03 07:09:56'),
(441, 3, 'assign_roll', 5, '127.0.0.1', '2025-05-03 07:12:21'),
(442, 3, 'logout', NULL, '127.0.0.1', '2025-05-03 07:12:37'),
(443, 5, 'report_repair', 3, '::1', '2025-05-03 07:14:24'),
(444, 2, 'report_repair', 4, '::1', '2025-05-03 07:15:59'),
(445, 2, 'report_repair', 5, '::1', '2025-05-03 07:21:30'),
(446, 2, 'logout', NULL, '127.0.0.1', '2025-05-03 07:21:40'),
(447, 2, 'report_repair', 6, '::1', '2025-05-03 07:24:56'),
(448, 3, 'assign_roll', 6, '127.0.0.1', '2025-05-03 12:03:23'),
(449, 3, 'assign_roll', 7, '127.0.0.1', '2025-05-03 12:12:50'),
(450, 3, 'logout', NULL, '127.0.0.1', '2025-05-03 12:24:08'),
(451, 2, 'logout', NULL, '::1', '2025-05-03 12:27:42'),
(452, 3, 'assign_roll', 8, '127.0.0.1', '2025-05-03 12:30:28'),
(453, 2, 'logout', NULL, '::1', '2025-05-03 22:28:51'),
(454, 3, 'assign_roll', 9, '::1', '2025-05-03 22:35:59'),
(455, 3, 'logout', NULL, '::1', '2025-05-03 22:37:20'),
(456, 3, 'assign_roll', 10, '::1', '2025-05-03 22:43:40'),
(457, 3, 'assign_roll', 11, '::1', '2025-05-03 22:44:14'),
(458, 3, 'assign_roll', 12, '127.0.0.1', '2025-05-04 03:08:55'),
(459, 2, 'submit_roll', 207, '::1', '2025-05-04 03:09:05'),
(460, 3, 'assign_roll', 13, '127.0.0.1', '2025-05-04 03:11:24'),
(461, 3, 'assign_roll', 14, '127.0.0.1', '2025-05-04 03:19:00'),
(462, 3, 'report_repair', 7, '127.0.0.1', '2025-05-04 03:21:16'),
(463, 3, 'logout', NULL, '127.0.0.1', '2025-05-04 03:21:31'),
(464, 2, 'submit_roll', 208, '::1', '2025-05-04 03:23:50'),
(465, 2, 'confirm_roll', 14, '127.0.0.1', '2025-05-04 03:25:07'),
(466, 2, 'logout', NULL, '::1', '2025-05-04 03:26:12'),
(467, 3, 'assign_roll', 15, '::1', '2025-05-04 03:26:44'),
(468, 2, 'confirm_roll', 15, '::1', '2025-05-04 03:31:02'),
(469, 2, 'report_repair', 8, '::1', '2025-05-04 03:33:39'),
(470, 3, 'logout', NULL, '127.0.0.1', '2025-05-04 03:34:14'),
(471, 4, 'logout', NULL, '127.0.0.1', '2025-05-04 03:34:29'),
(472, 5, 'logout', NULL, '127.0.0.1', '2025-05-04 03:35:26'),
(473, 3, 'assign_roll', 16, '127.0.0.1', '2025-05-04 03:35:48'),
(474, 3, 'assign_roll', 17, '127.0.0.1', '2025-05-04 03:35:57'),
(475, 2, 'confirm_roll', 17, '::1', '2025-05-04 03:36:08'),
(476, 2, 'confirm_roll', 16, '::1', '2025-05-04 03:36:12'),
(477, 5, 'logout', NULL, '::1', '2025-05-04 08:22:53'),
(478, 2, 'logout', NULL, '::1', '2025-05-04 08:23:09'),
(479, 3, 'assign_roll', 18, '::1', '2025-05-04 08:23:30'),
(480, 2, 'confirm_roll', 18, '127.0.0.1', '2025-05-04 08:23:45'),
(481, 3, 'logout', NULL, '::1', '2025-05-04 08:24:25'),
(482, 1, 'database_update', NULL, '::1', '2025-05-04 12:34:00'),
(483, 1, 'create_announcement', NULL, '::1', '2025-05-04 12:36:29'),
(484, 1, 'logout', NULL, '::1', '2025-05-04 12:36:40'),
(485, 5, 'logout', NULL, '::1', '2025-05-04 12:37:16'),
(486, 2, 'report_repair', 9, '::1', '2025-05-04 13:25:57');

--
-- Constraints for dumped tables
--

--
-- Constraints for table `device_repairs`
--
ALTER TABLE `device_repairs`
  ADD CONSTRAINT `fk_device_repairs_reported_by` FOREIGN KEY (`reported_by`) REFERENCES `access_codes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_device_repairs_technician` FOREIGN KEY (`technician_id`) REFERENCES `access_codes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `repair_history`
--
ALTER TABLE `repair_history`
  ADD CONSTRAINT `fk_repair_history_repair` FOREIGN KEY (`repair_id`) REFERENCES `device_repairs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_repair_history_user` FOREIGN KEY (`user_id`) REFERENCES `access_codes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `roll_assignments`
--
ALTER TABLE `roll_assignments`
  ADD CONSTRAINT `fk_roll_assignments_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `access_codes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_roll_assignments_user` FOREIGN KEY (`user_id`) REFERENCES `access_codes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

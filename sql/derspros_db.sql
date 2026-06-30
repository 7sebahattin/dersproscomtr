-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Anamakine: localhost:3306
-- Üretim Zamanı: 23 Mar 2026, 20:26:02
-- Sunucu sürümü: 10.6.24-MariaDB-cll-lve-log
-- PHP Sürümü: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `derspros_db`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` mediumtext NOT NULL,
  `target_role` varchar(20) DEFAULT 'all',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `duration` int(11) DEFAULT 30,
  `private_note` text DEFAULT NULL,
  `public_note` text DEFAULT NULL,
  `status` enum('active','cancelled','completed') DEFAULT 'active',
  `is_recurring` tinyint(1) DEFAULT 0,
  `group_id` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `student_note` text DEFAULT NULL,
  `student_public_note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `appointments`
--

INSERT INTO `appointments` (`id`, `teacher_id`, `student_id`, `appointment_date`, `appointment_time`, `duration`, `private_note`, `public_note`, `status`, `is_recurring`, `group_id`, `created_at`, `student_note`, `student_public_note`) VALUES
(1, 1, 2, '2025-12-30', '15:00:00', 30, 'sss', 'gel', 'active', 0, NULL, '2025-12-30 12:46:28', 'ssss', NULL),
(2, 1, 3, '2025-12-30', '15:30:00', 30, 'aaa', 'gel', 'active', 0, NULL, '2025-12-30 12:48:38', NULL, NULL),
(3, 1, 3, '2025-12-30', '15:15:00', 0, '', 'gel', 'cancelled', 0, NULL, '2025-12-30 12:54:30', NULL, NULL),
(4, 1, 2, '2025-12-31', '15:55:00', 30, '', '', 'active', 0, NULL, '2025-12-30 12:55:16', '', NULL),
(5, 1, 3, '2025-12-31', '18:30:00', 30, 'sss', 'gel', 'active', 0, NULL, '2025-12-30 13:29:40', NULL, NULL),
(6, 1, 2, '2026-01-03', '12:22:00', 30, '', 'deneme 123', 'cancelled', 0, NULL, '2026-01-02 10:30:31', 'hocam geç kalacam', NULL),
(7, 1, 3, '2026-01-03', '11:30:00', 30, '', '', 'active', 0, NULL, '2026-01-02 10:37:29', NULL, NULL),
(8, 1, 2, '2026-01-09', '16:00:00', 30, '', '', 'active', 1, 'grp_6957a140380b8', '2026-01-02 10:43:12', 'gitmem', NULL),
(9, 1, 2, '2026-01-16', '16:00:00', 30, '', '', 'active', 1, 'grp_6957a140380b8', '2026-01-02 10:43:12', NULL, NULL),
(10, 1, 2, '2026-01-23', '16:00:00', 30, '', '', 'active', 1, 'grp_6957a140380b8', '2026-01-02 10:43:12', NULL, NULL),
(11, 1, 2, '2026-01-30', '16:00:00', 30, '', '', 'active', 1, 'grp_6957a140380b8', '2026-01-02 10:43:12', NULL, NULL),
(12, 1, 3, '2026-01-02', '18:30:00', 30, '', '', 'active', 0, NULL, '2026-01-02 10:43:33', NULL, NULL),
(13, 1, 2, '2026-01-03', '15:55:00', 30, NULL, 'geç gelme', 'cancelled', 0, NULL, '2026-01-02 10:43:51', 'gitme', NULL),
(14, 1, 3, '2026-01-02', '21:00:00', 30, NULL, NULL, 'active', 0, NULL, '2026-01-02 13:16:53', NULL, NULL),
(15, 1, 2, '2026-01-02', '23:02:00', 30, NULL, NULL, 'active', 0, NULL, '2026-01-02 15:39:53', NULL, NULL),
(16, 1, 2, '2026-01-04', '11:11:00', 30, 'sss aaa aaa', NULL, 'active', 0, NULL, '2026-01-03 08:40:45', NULL, NULL),
(17, 1, 2, '2026-01-03', '14:02:00', 30, NULL, NULL, 'active', 0, NULL, '2026-01-03 11:02:31', NULL, NULL),
(18, 1, 2, '2026-01-06', '15:55:00', 45, NULL, NULL, 'active', 1, 'grp_695ccceb52f22', '2026-01-06 08:50:51', NULL, NULL),
(19, 1, 2, '2026-01-13', '15:55:00', 45, NULL, NULL, 'active', 1, 'grp_695ccceb52f22', '2026-01-06 08:50:51', NULL, NULL),
(20, 1, 2, '2026-01-20', '15:55:00', 45, NULL, NULL, 'cancelled', 1, 'grp_695ccceb52f22', '2026-01-06 08:50:51', NULL, NULL),
(21, 1, 2, '2026-01-27', '15:55:00', 45, NULL, NULL, 'active', 1, 'grp_695ccceb52f22', '2026-01-06 08:50:51', NULL, NULL),
(22, 1, 2, '2026-02-02', '19:00:00', 30, NULL, NULL, 'active', 1, 'grp_698076896d3de', '2026-02-02 10:03:53', NULL, NULL),
(23, 1, 2, '2026-02-09', '19:00:00', 30, NULL, NULL, 'active', 1, 'grp_698076896d3de', '2026-02-02 10:03:53', NULL, NULL),
(24, 1, 2, '2026-02-16', '19:00:00', 30, NULL, NULL, 'active', 1, 'grp_698076896d3de', '2026-02-02 10:03:53', NULL, NULL),
(25, 1, 2, '2026-02-23', '19:00:00', 30, NULL, NULL, 'active', 1, 'grp_698076896d3de', '2026-02-02 10:03:53', NULL, NULL),
(26, 1, 2, '2026-02-03', '20:08:00', 30, NULL, NULL, 'active', 0, NULL, '2026-02-02 14:05:20', NULL, NULL),
(27, 1, 2, '2026-02-01', '18:08:00', 30, NULL, NULL, 'active', 0, NULL, '2026-02-02 14:07:13', NULL, NULL),
(28, 1, 2, '2026-02-07', '19:03:00', 30, NULL, NULL, 'active', 0, NULL, '2026-02-02 15:02:19', NULL, NULL),
(29, 1, 2, '2026-02-11', '03:19:00', 30, NULL, NULL, 'active', 0, NULL, '2026-02-04 23:18:14', NULL, NULL),
(30, 1, 2, '2026-02-05', '23:30:00', 30, NULL, NULL, 'active', 0, NULL, '2026-02-05 14:24:25', NULL, NULL),
(31, 1, 2, '2026-03-09', '12:52:00', 30, NULL, NULL, 'active', 1, 'grp_69abf5747b974', '2026-03-07 09:52:52', NULL, NULL),
(32, 1, 2, '2026-03-16', '12:52:00', 30, NULL, NULL, 'active', 1, 'grp_69abf5747b974', '2026-03-07 09:52:52', NULL, NULL),
(33, 1, 2, '2026-03-23', '12:52:00', 30, NULL, NULL, 'active', 1, 'grp_69abf5747b974', '2026-03-07 09:52:52', NULL, NULL),
(34, 1, 2, '2026-03-30', '12:52:00', 30, NULL, NULL, 'active', 1, 'grp_69abf5747b974', '2026-03-07 09:52:52', NULL, NULL),
(35, 1, 2, '2026-03-18', '16:03:00', 30, NULL, NULL, 'active', 0, NULL, '2026-03-18 09:59:46', NULL, NULL);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `appointment_messages`
--

CREATE TABLE `appointment_messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `sender_user_id` int(11) NOT NULL,
  `sender_role` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_read_by_teacher` tinyint(1) NOT NULL DEFAULT 0,
  `is_read_by_student` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `appointment_messages`
--

INSERT INTO `appointment_messages` (`id`, `appointment_id`, `sender_user_id`, `sender_role`, `message`, `created_at`, `is_read_by_teacher`, `is_read_by_student`) VALUES
(1, 13, 2, 'student', 'selam hocammm', '2026-01-02 16:15:48', 1, 1),
(2, 13, 1, 'teacher', 'selam evlat', '2026-01-02 16:16:18', 1, 1),
(3, 13, 2, 'student', 'tsk', '2026-01-02 16:19:56', 1, 1),
(4, 13, 1, 'teacher', 'npraolba', '2026-01-02 16:42:57', 1, 1),
(5, 13, 2, 'student', 'okeyyy', '2026-01-02 16:43:19', 1, 1),
(6, 13, 1, 'teacher', 'hadi gel artık', '2026-01-02 16:57:58', 1, 1),
(7, 13, 2, 'student', 'tamam hocam', '2026-01-02 16:58:21', 1, 1),
(8, 12, 1, 'teacher', 'selam', '2026-01-02 18:28:20', 1, 0),
(9, 13, 2, 'student', 'hocam', '2026-01-02 18:36:46', 1, 1),
(10, 12, 1, 'teacher', 'sa', '2026-01-02 18:38:35', 1, 0),
(11, 13, 1, 'teacher', 'evet', '2026-01-02 18:38:52', 1, 1),
(12, 16, 1, 'teacher', 'test', '2026-01-03 11:41:01', 1, 1),
(13, 16, 2, 'student', 'tamam hocam', '2026-01-03 11:42:34', 1, 1),
(14, 13, 1, 'teacher', 'selam dostum', '2026-01-03 11:57:58', 1, 1),
(15, 13, 1, 'teacher', 'nasıl gidiyor', '2026-01-03 11:58:03', 1, 1),
(16, 16, 1, 'teacher', 'tskr', '2026-01-03 11:58:20', 1, 1),
(17, 13, 2, 'student', 'iyi hocam sağl', '2026-01-03 11:58:51', 1, 1),
(18, 16, 2, 'student', 'aaa', '2026-01-03 11:59:01', 1, 1),
(19, 16, 2, 'student', 'aaa', '2026-01-03 11:59:02', 1, 1),
(20, 16, 2, 'student', 'aaa', '2026-01-03 11:59:04', 1, 1),
(21, 13, 1, 'teacher', 'tamm', '2026-01-03 12:06:17', 1, 1),
(22, 13, 2, 'student', 'eyv', '2026-01-03 12:06:35', 1, 1),
(23, 13, 6, 'parent', 'hocam', '2026-01-03 12:07:47', 1, 1),
(24, 7, 1, 'teacher', 'selam', '2026-01-03 13:58:22', 1, 0),
(25, 13, 1, 'teacher', 'merhaba', '2026-01-03 13:58:30', 1, 1),
(26, 13, 2, 'student', 'merhaba', '2026-01-03 13:59:12', 1, 1),
(27, 7, 1, 'teacher', 'sa', '2026-01-03 14:15:10', 1, 0),
(28, 17, 1, 'teacher', 'sa', '2026-01-03 14:15:16', 1, 1),
(29, 17, 2, 'student', 'as', '2026-01-03 14:15:34', 1, 1),
(30, 17, 1, 'teacher', 'tamamam', '2026-01-03 14:27:11', 1, 1),
(31, 17, 2, 'student', 'ses 123', '2026-01-03 14:27:30', 1, 1),
(32, 8, 2, 'student', 'hocam', '2026-01-08 11:17:54', 1, 1),
(33, 8, 1, 'teacher', 'ne', '2026-01-08 11:37:24', 1, 1),
(34, 8, 1, 'teacher', 'aaa', '2026-01-08 13:56:29', 1, 1),
(35, 8, 2, 'student', 'tabb', '2026-01-08 14:06:59', 1, 1),
(36, 8, 2, 'student', 'saasa', '2026-01-08 15:16:50', 1, 1),
(37, 8, 2, 'student', 'hocam', '2026-01-09 16:09:46', 1, 1),
(38, 8, 1, 'teacher', 'evet', '2026-01-09 16:10:38', 1, 1),
(39, 8, 2, 'student', 'smsm geldi mi', '2026-01-09 16:12:13', 1, 1),
(40, 8, 2, 'student', 'hocam', '2026-01-09 16:15:05', 1, 1),
(41, 8, 2, 'student', 'hocam haklısınız', '2026-01-09 16:19:04', 1, 1),
(42, 8, 1, 'teacher', 'aşkım seni seviyorum :)', '2026-01-09 16:19:42', 1, 1),
(43, 8, 2, 'student', 'bende', '2026-01-09 16:22:24', 1, 1),
(44, 8, 2, 'student', 'selam da', '2026-01-09 16:25:24', 1, 1),
(45, 8, 2, 'student', 'seasad', '2026-01-09 16:30:46', 1, 1),
(46, 8, 1, 'teacher', 'Selam', '2026-01-09 17:51:24', 1, 1),
(47, 8, 1, 'teacher', 'Aşkım seni seviyorum', '2026-01-09 17:55:02', 1, 1),
(48, 9, 2, 'student', 'hocam', '2026-01-14 12:50:46', 1, 1),
(49, 9, 1, 'teacher', 'efendim', '2026-01-14 12:51:08', 1, 1),
(50, 9, 2, 'student', 'iyyiyim', '2026-01-14 13:56:12', 1, 1),
(51, 9, 1, 'teacher', 'güzel', '2026-01-14 13:56:29', 1, 1),
(52, 9, 1, 'teacher', 'ne oldu', '2026-01-14 15:55:50', 1, 1),
(53, 9, 2, 'student', 'yok bişi', '2026-01-14 15:56:15', 1, 1),
(54, 10, 2, 'student', 'hocam', '2026-01-19 17:50:03', 1, 1),
(55, 21, 2, 'student', 'Sercan', '2026-01-26 14:25:10', 1, 1);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `appointment_requests`
--

CREATE TABLE `appointment_requests` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `requester_user_id` int(11) NOT NULL,
  `requester_role` enum('student','parent') NOT NULL,
  `type` enum('cancel','reschedule') NOT NULL,
  `message` mediumtext DEFAULT NULL,
  `proposed_date` date DEFAULT NULL,
  `proposed_time` time DEFAULT NULL,
  `proposed_duration` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `teacher_response` mediumtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `decided_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `appointment_requests`
--

INSERT INTO `appointment_requests` (`id`, `appointment_id`, `requester_user_id`, `requester_role`, `type`, `message`, `proposed_date`, `proposed_time`, `proposed_duration`, `status`, `teacher_response`, `created_at`, `decided_at`) VALUES
(1, 1, 2, 'student', 'reschedule', 'geç gelecem', '2025-12-30', '15:33:00', 30, 'rejected', 'o saatte doluyum ama sen', '2025-12-30 17:45:44', '2025-12-30 17:53:01'),
(2, 6, 2, 'student', 'reschedule', '', '2026-01-02', '16:00:00', 30, 'approved', '', '2026-01-02 13:31:00', '2026-01-02 13:31:34'),
(3, 6, 2, 'student', 'reschedule', 'geç kalacam', '2026-01-02', '18:00:00', NULL, 'approved', 'tamam', '2026-01-02 13:41:49', '2026-01-02 13:42:14'),
(4, 6, 2, 'student', 'reschedule', NULL, '2026-01-02', '18:30:00', NULL, 'rejected', '', '2026-01-02 13:44:26', '2026-01-02 13:44:47'),
(5, 6, 2, 'student', 'reschedule', 'aaaaaa', '2026-01-03', '12:22:00', NULL, 'approved', '', '2026-01-02 14:32:23', '2026-01-02 14:42:56'),
(6, 13, 2, 'student', 'reschedule', NULL, '2026-01-03', '12:22:00', NULL, 'approved', '', '2026-01-02 17:01:25', '2026-01-02 17:01:45'),
(7, 13, 2, 'student', 'reschedule', NULL, '2026-01-03', '15:55:00', NULL, 'approved', '', '2026-01-03 14:00:04', '2026-01-03 14:26:33'),
(8, 17, 2, 'student', 'cancel', 'gelmeyecem', NULL, NULL, NULL, 'rejected', '', '2026-01-03 14:15:45', '2026-01-03 14:26:32'),
(9, 20, 2, 'student', 'cancel', NULL, NULL, NULL, NULL, 'approved', '', '2026-01-14 15:54:51', '2026-01-14 15:55:23');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `click_logs`
--

CREATE TABLE `click_logs` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `click_logs`
--

INSERT INTO `click_logs` (`id`, `teacher_id`, `student_id`, `created_at`) VALUES
(1, 1, 2, '2026-01-08 12:17:56'),
(2, 1, 2, '2026-01-08 13:13:10');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `coaching_relationships`
--

CREATE TABLE `coaching_relationships` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `lesson_price` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `coaching_relationships`
--

INSERT INTO `coaching_relationships` (`id`, `teacher_id`, `student_id`, `lesson_price`) VALUES
(1, 1, 2, 1000.00),
(13, 19, 20, 0.00);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `coaching_subjects`
--

CREATE TABLE `coaching_subjects` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` varchar(50) DEFAULT 'Genel',
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `coaching_subjects`
--

INSERT INTO `coaching_subjects` (`id`, `name`, `category`, `created_by`) VALUES
(1, 'Ders Adı', 'Alan (Kategori)', NULL),
(2, 'Türkçe', 'LGS', NULL),
(3, 'Matematik', 'LGS', NULL),
(4, 'Fen Bilimleri', 'LGS', NULL),
(5, 'T.C. İnkılap Tarihi ve Atatürkçülük', 'LGS', NULL),
(6, 'Din Kültürü ve Ahlak Bilgisi', 'LGS', NULL),
(7, 'İngilizce', 'LGS', NULL),
(8, 'Türkçe', 'TYT', NULL),
(9, 'Matematik', 'TYT', NULL),
(10, 'Geometri', 'TYT', NULL),
(11, 'Fizik', 'TYT', NULL),
(12, 'Kimya', 'TYT', NULL),
(13, 'Biyoloji', 'TYT', NULL),
(14, 'Tarih', 'TYT', NULL),
(15, 'Coğrafya', 'TYT', NULL),
(16, 'Matematik', 'AYT', NULL),
(17, 'Geometri', 'AYT', NULL),
(18, 'Fizik', 'AYT', NULL),
(19, 'Kimya', 'AYT', NULL),
(20, 'Biyoloji', 'AYT', NULL),
(21, 'Edebiyat', 'AYT', NULL),
(22, 'Tarih', 'AYT', NULL),
(23, 'Coğrafya', 'AYT', NULL),
(24, 'Felsefe Grubu', 'AYT', NULL),
(25, 'Din Kültürü', 'AYT', NULL),
(26, 'Matematik 10. Sınıf', 'Genel', 19);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `coaching_topics`
--

CREATE TABLE `coaching_topics` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `coaching_topics`
--

INSERT INTO `coaching_topics` (`id`, `subject_id`, `name`, `created_by`, `is_public`) VALUES
(2, 2, 'Sözcükte ve Söz Öbeklerinde Anlam', NULL, 1),
(3, 2, 'Cümlede Anlam', NULL, 1),
(4, 2, 'Paragrafta Anlam', NULL, 1),
(5, 2, 'Sözel Mantık ve Muhakeme', NULL, 1),
(6, 2, 'Fiilimsiler', NULL, 1),
(7, 2, 'Cümlenin Ögeleri', NULL, 1),
(8, 2, 'Fiilde Çatı', NULL, 1),
(9, 2, 'Cümle Türleri', NULL, 1),
(10, 2, 'Yazım Kuralları ve Noktalama İşaretleri', NULL, 1),
(11, 2, 'Metin Türleri ve Söz Sanatları', NULL, 1),
(12, 3, 'Çarpanlar ve Katlar', NULL, 1),
(13, 3, 'Üslü İfadeler', NULL, 1),
(14, 3, 'Kareköklü İfadeler', NULL, 1),
(15, 3, 'Veri Analizi', NULL, 1),
(16, 3, 'Basit Olayların Olma Olasılığı', NULL, 1),
(17, 3, 'Cebirsel İfadeler ve Özdeşlikler', NULL, 1),
(18, 3, 'Doğrusal Denklemler', NULL, 1),
(19, 3, 'Eşitsizlikler', NULL, 1),
(20, 3, 'Üçgenler', NULL, 1),
(21, 3, 'Eşlik ve Benzerlik', NULL, 1),
(22, 3, 'Dönüşüm Geometrisi', NULL, 1),
(23, 3, 'Geometrik Cisimler', NULL, 1),
(24, 4, 'Mevsimler ve İklim', NULL, 1),
(25, 4, 'DNA ve Genetik Kod', NULL, 1),
(26, 4, 'Basınç', NULL, 1),
(27, 4, 'Madde ve Endüstri', NULL, 1),
(28, 4, 'Basit Makineler', NULL, 1),
(29, 4, 'Enerji Dönüşümleri ve Çevre Bilimi', NULL, 1),
(30, 4, 'Elektrik Yükleri ve Elektrik Enerjisi', NULL, 1),
(31, 5, 'Bir Kahraman Doğuyor', NULL, 1),
(32, 5, 'Milli Uyanış: Bağımsızlık Yolunda Atılan Adımlar', NULL, 1),
(33, 5, 'Ya İstiklal Ya Ölüm!', NULL, 1),
(34, 5, 'Atatürkçülük ve Çağdaşlaşan Türkiye', NULL, 1),
(35, 5, 'Demokratikleşme Çabaları', NULL, 1),
(36, 5, 'Atatürk Dönemi Türk Dış Politikası', NULL, 1),
(37, 5, 'Atatürk\'ün Ölümü ve Sonrası', NULL, 1),
(38, 6, 'Kader İnancı', NULL, 1),
(39, 6, 'Zekât ve Sadaka', NULL, 1),
(40, 6, 'Din ve Hayat', NULL, 1),
(41, 6, 'Hz. Muhammed\'in Örnekliği', NULL, 1),
(42, 6, 'Kur\'an-ı Kerim ve Özellikleri', NULL, 1),
(43, 7, 'Friendship', NULL, 1),
(44, 7, 'Teen Life', NULL, 1),
(45, 7, 'In the Kitchen', NULL, 1),
(46, 7, 'On the Phone', NULL, 1),
(47, 7, 'The Internet', NULL, 1),
(48, 7, 'Adventures', NULL, 1),
(49, 7, 'Tourism', NULL, 1),
(50, 7, 'Chores', NULL, 1),
(51, 7, 'Science', NULL, 1),
(52, 7, 'Natural Forces', NULL, 1),
(54, 8, 'Sözcükte Anlam', NULL, 1),
(55, 8, 'Cümlede Anlam', NULL, 1),
(56, 8, 'Paragrafta Anlam', NULL, 1),
(57, 8, 'Ses Bilgisi', NULL, 1),
(58, 8, 'Yazım Kuralları', NULL, 1),
(59, 8, 'Noktalama İşaretleri', NULL, 1),
(60, 8, 'Sözcükte Yapı', NULL, 1),
(61, 8, 'Sözcük Türleri (İsim, Sıfat, Zamir, Zarf, Edat)', NULL, 1),
(62, 8, 'Fiiller', NULL, 1),
(63, 8, 'Fiilimsiler', NULL, 1),
(64, 8, 'Cümlenin Ögeleri', NULL, 1),
(65, 8, 'Fiil Çatısı', NULL, 1),
(66, 8, 'Cümle Türleri', NULL, 1),
(67, 8, 'Anlatım Bozuklukları', NULL, 1),
(68, 9, 'Temel Kavramlar', NULL, 1),
(69, 9, 'Sayı Basamakları', NULL, 1),
(70, 9, 'Bölme ve Bölünebilme', NULL, 1),
(71, 9, 'OBEB - OKEK', NULL, 1),
(72, 9, 'Rasyonel Sayılar', NULL, 1),
(73, 9, 'Basit Eşitsizlikler', NULL, 1),
(74, 9, 'Mutlak Değer', NULL, 1),
(75, 9, 'Üslü Sayılar', NULL, 1),
(76, 9, 'Köklü Sayılar', NULL, 1),
(77, 9, 'Çarpanlara Ayırma', NULL, 1),
(78, 9, 'Oran - Orantı', NULL, 1),
(79, 9, 'Problemler (Sayı, Kesir, Yaş, İşçi, Hız vb.)', NULL, 1),
(80, 9, 'Kümeler - Kartezyen Çarpım', NULL, 1),
(81, 9, 'Mantık', NULL, 1),
(82, 9, 'Fonksiyonlar', NULL, 1),
(83, 9, 'Polinomlar', NULL, 1),
(84, 9, 'İkinci Dereceden Denklemler', NULL, 1),
(85, 9, 'Permütasyon - Kombinasyon', NULL, 1),
(86, 9, 'Olasılık', NULL, 1),
(87, 9, 'Veri - İstatistik', NULL, 1),
(88, 10, 'Doğruda ve Üçgende Açılar', NULL, 1),
(89, 10, 'Özel Üçgenler (Dik, İkizkenar, Eşkenar)', NULL, 1),
(90, 10, 'Açıortay ve Kenarortay', NULL, 1),
(91, 10, 'Üçgende Alan ve Benzerlik', NULL, 1),
(92, 10, 'Açı - Kenar Bağıntıları', NULL, 1),
(93, 10, 'Çokgenler', NULL, 1),
(94, 10, 'Dörtgenler (Kare, Dikdörtgen, Yamuk vb.)', NULL, 1),
(95, 10, 'Çember ve Daire', NULL, 1),
(96, 10, 'Analitik Geometri', NULL, 1),
(97, 10, 'Katı Cisimler (Prizma, Piramit, Küre)', NULL, 1),
(98, 11, 'Fizik Bilimine Giriş', NULL, 1),
(99, 11, 'Madde ve Özellikleri', NULL, 1),
(100, 11, 'Hareket ve Kuvvet', NULL, 1),
(101, 11, 'İş, Güç ve Enerji', NULL, 1),
(102, 11, 'Isı, Sıcaklık ve Genleşme', NULL, 1),
(103, 11, 'Elektrostatik', NULL, 1),
(104, 11, 'Elektrik ve Manyetizma', NULL, 1),
(105, 11, 'Basınç ve Kaldırma Kuvveti', NULL, 1),
(106, 11, 'Dalgalar', NULL, 1),
(107, 11, 'Optik', NULL, 1),
(108, 12, 'Kimya Bilimi', NULL, 1),
(109, 12, 'Atom ve Periyodik Sistem', NULL, 1),
(110, 12, 'Kimyasal Türler Arası Etkileşimler', NULL, 1),
(111, 12, 'Maddenin Halleri', NULL, 1),
(112, 12, 'Doğa ve Kimya', NULL, 1),
(113, 12, 'Kimyanın Temel Kanunları ve Hesaplamalar', NULL, 1),
(114, 12, 'Karışımlar', NULL, 1),
(115, 12, 'Asitler, Bazlar ve Tuzlar', NULL, 1),
(116, 12, 'Kimya Her Yerde', NULL, 1),
(117, 13, 'Yaşam Bilimi Biyoloji', NULL, 1),
(118, 13, 'Canlıların Ortak Özellikleri', NULL, 1),
(119, 13, 'Canlıların Temel Bileşenleri', NULL, 1),
(120, 13, 'Hücre ve Organelleri', NULL, 1),
(121, 13, 'Canlıların Sınıflandırılması', NULL, 1),
(122, 13, 'Hücre Bölünmeleri (Mitoz - Mayoz)', NULL, 1),
(123, 13, 'Kalıtım', NULL, 1),
(124, 13, 'Ekosistem Ekolojisi ve Güncel Çevre Sorunları', NULL, 1),
(125, 14, 'Tarih ve Zaman', NULL, 1),
(126, 14, 'İnsanlığın İlk Dönemleri', NULL, 1),
(127, 14, 'Orta Çağ\'da Dünya', NULL, 1),
(128, 14, 'İlk ve Orta Çağlarda Türk Dünyası', NULL, 1),
(129, 14, 'İslam Medeniyetinin Doğuşu ve İlk İslam Devletleri', NULL, 1),
(130, 14, 'Türklerin İslamiyet\'i Kabulü ve İlk Türk İslam Devletleri', NULL, 1),
(131, 14, 'Yerleşme ve Devletleşme Sürecinde Selçuklu Türkiyesi', NULL, 1),
(132, 14, 'Beylikten Devlete Osmanlı Siyaseti', NULL, 1),
(133, 14, 'Dünya Gücü Osmanlı', NULL, 1),
(134, 14, 'Değişim Çağında Avrupa ve Osmanlı', NULL, 1),
(135, 14, 'Uluslararası İlişkilerde Denge Stratejisi (1774-1914)', NULL, 1),
(136, 14, 'Devrimler Çağında Değişen Devlet-Toplum İlişkileri', NULL, 1),
(137, 14, 'XX. Yüzyıl Başlarında Osmanlı Devleti ve Dünya', NULL, 1),
(138, 14, 'Milli Mücadele', NULL, 1),
(139, 14, 'Atatürkçülük ve Türk İnkılabı', NULL, 1),
(140, 15, 'Doğa ve İnsan', NULL, 1),
(141, 15, 'Dünya\'nın Şekli ve Hareketleri', NULL, 1),
(142, 15, 'Coğrafi Konum', NULL, 1),
(143, 15, 'Harita Bilgisi', NULL, 1),
(144, 15, 'Atmosfer ve İklim', NULL, 1),
(145, 15, 'Yerin Şekillenmesi (İç ve Dış Kuvvetler)', NULL, 1),
(146, 15, 'Su - Toprak - Bitki', NULL, 1),
(147, 15, 'Nüfus ve Yerleşme', NULL, 1),
(148, 15, 'Ekonomik Faaliyetler', NULL, 1),
(149, 15, 'Bölgeler', NULL, 1),
(150, 15, 'Ulaşım Yolları', NULL, 1),
(151, 15, 'Doğal Afetler', NULL, 1),
(152, 16, 'Polinomlar', NULL, 1),
(153, 16, 'İkinci Dereceden Denklemler', NULL, 1),
(154, 16, 'İkinci Dereceden Eşitsizlikler', NULL, 1),
(155, 16, 'Parabol', NULL, 1),
(156, 16, 'Trigonometri', NULL, 1),
(157, 16, 'Logaritma', NULL, 1),
(158, 16, 'Diziler', NULL, 1),
(159, 16, 'Limit ve Süreklilik', NULL, 1),
(160, 16, 'Türev', NULL, 1),
(161, 16, 'İntegral', NULL, 1),
(162, 17, 'Doğruda ve Üçgende Açılar', NULL, 1),
(163, 17, 'Özel Üçgenler', NULL, 1),
(164, 17, 'Açıortay - Kenarortay', NULL, 1),
(165, 17, 'Üçgende Benzerlik ve Alan', NULL, 1),
(166, 17, 'Çokgenler ve Dörtgenler', NULL, 1),
(167, 17, 'Çember ve Daire', NULL, 1),
(168, 17, 'Analitik Geometri (Nokta, Doğru, Çember)', NULL, 1),
(169, 17, 'Dönüşüm Geometrisi', NULL, 1),
(170, 17, 'Katı Cisimler', NULL, 1),
(171, 18, 'Vektörler', NULL, 1),
(172, 18, 'Bağıl Hareket', NULL, 1),
(173, 18, 'Newton\'un Hareket Yasaları', NULL, 1),
(174, 18, 'Bir Boyutta Sabit İvmeli Hareket', NULL, 1),
(175, 18, 'İki Boyutta Hareket (Atışlar)', NULL, 1),
(176, 18, 'Enerji ve Hareket', NULL, 1),
(177, 18, 'İtme ve Momentum', NULL, 1),
(178, 18, 'Tork ve Denge', NULL, 1),
(179, 18, 'Kütle ve Ağırlık Merkezi', NULL, 1),
(180, 18, 'Elektriksel Kuvvet ve Alan', NULL, 1),
(181, 18, 'Elektriksel Potansiyel', NULL, 1),
(182, 18, 'Düzgün Elektrik Alan ve Sığa', NULL, 1),
(183, 18, 'Manyetizma ve Elektromanyetik İndüklenme', NULL, 1),
(184, 18, 'Alternatif Akım ve Transformatörler', NULL, 1),
(185, 18, 'Çembersel Hareket', NULL, 1),
(186, 18, 'Basit Harmonik Hareket', NULL, 1),
(187, 18, 'Dalga Mekaniği (Kırınım, Girişim, Doppler)', NULL, 1),
(188, 18, 'Atom Fiziğine Giriş ve Radyoaktivite', NULL, 1),
(189, 18, 'Modern Fizik', NULL, 1),
(190, 18, 'Modern Fiziğin Teknolojideki Uygulamaları', NULL, 1),
(191, 19, 'Modern Atom Teorisi', NULL, 1),
(192, 19, 'Gazlar', NULL, 1),
(193, 19, 'Sıvı Çözeltiler ve Çözünürlük', NULL, 1),
(194, 19, 'Kimyasal Tepkimelerde Enerji', NULL, 1),
(195, 19, 'Kimyasal Tepkimelerde Hız', NULL, 1),
(196, 19, 'Kimyasal Tepkimelerde Denge', NULL, 1),
(197, 19, 'Asit ve Baz Dengeleri', NULL, 1),
(198, 19, 'Çözünürlük Dengesi (KÇÇ)', NULL, 1),
(199, 19, 'Kimya ve Elektrik (Elektrokimya)', NULL, 1),
(200, 19, 'Karbon Kimyasına Giriş', NULL, 1),
(201, 19, 'Organik Bileşikler', NULL, 1),
(202, 20, 'Sinir Sistemi', NULL, 1),
(203, 20, 'Endokrin Sistem', NULL, 1),
(204, 20, 'Duyu Organları', NULL, 1),
(205, 20, 'Destek ve Hareket Sistemi', NULL, 1),
(206, 20, 'Sindirim Sistemi', NULL, 1),
(207, 20, 'Dolaşım Sistemi', NULL, 1),
(208, 20, 'Solunum Sistemi', NULL, 1),
(209, 20, 'Üriner Sistem', NULL, 1),
(210, 20, 'Üreme Sistemi ve Embriyonik Gelişim', NULL, 1),
(211, 20, 'Komünite ve Popülasyon Ekolojisi', NULL, 1),
(212, 20, 'Genden Proteine (Protein Sentezi)', NULL, 1),
(213, 20, 'Canlılarda Enerji Dönüşümleri (Fotosentez - Solunum)', NULL, 1),
(214, 20, 'Bitki Biyolojisi', NULL, 1),
(215, 20, 'Canlılar ve Çevre', NULL, 1),
(216, 21, 'Güzel Sanatlar ve Edebiyat', NULL, 1),
(217, 21, 'Metinlerin Sınıflandırılması', NULL, 1),
(218, 21, 'Şiir Bilgisi', NULL, 1),
(219, 21, 'Söz Sanatları', NULL, 1),
(220, 21, 'İslamiyet Öncesi Türk Edebiyatı', NULL, 1),
(221, 21, 'Geçiş Dönemi Eserleri', NULL, 1),
(222, 21, 'Halk Edebiyatı', NULL, 1),
(223, 21, 'Divan Edebiyatı', NULL, 1),
(224, 21, 'Edebi Akımlar', NULL, 1),
(225, 21, 'Tanzimat Edebiyatı', NULL, 1),
(226, 21, 'Servet-i Fünun Edebiyatı', NULL, 1),
(227, 21, 'Fecr-i Ati Edebiyatı', NULL, 1),
(228, 21, 'Milli Edebiyat Dönemi', NULL, 1),
(229, 21, 'Cumhuriyet Dönemi Türk Edebiyatı', NULL, 1),
(230, 22, 'Tarih Bilimi', NULL, 1),
(231, 22, 'İlk Çağ Uygarlıkları', NULL, 1),
(232, 22, 'İlk Türk Devletleri', NULL, 1),
(233, 22, 'İslam Tarihi ve Uygarlığı', NULL, 1),
(234, 22, 'Türk-İslam Devletleri', NULL, 1),
(235, 22, 'Türkiye Tarihi', NULL, 1),
(236, 22, 'Beylikten Devlete Osmanlı', NULL, 1),
(237, 22, 'Dünya Gücü Osmanlı', NULL, 1),
(238, 22, 'Osmanlı Kültür ve Medeniyeti', NULL, 1),
(239, 22, 'Yeni ve Yakın Çağ\'da Avrupa', NULL, 1),
(240, 22, 'En Uzun Yüzyıl (Dağılma Dönemi)', NULL, 1),
(241, 22, 'Milli Mücadele Dönemi', NULL, 1),
(242, 22, 'Atatürkçülük ve Türk İnkılabı', NULL, 1),
(243, 22, 'Türk Dış Politikası', NULL, 1),
(244, 22, 'Çağdaş Türk ve Dünya Tarihi', NULL, 1),
(245, 23, 'Biyoçeşitlilik ve Ekosistem', NULL, 1),
(246, 23, 'Ekstrem Doğa Olayları', NULL, 1),
(247, 23, 'Şehirlerin Fonksiyonları ve Etki Alanları', NULL, 1),
(248, 23, 'Türkiye\'nin Nüfus Politikaları', NULL, 1),
(249, 23, 'Türkiye\'nin Ekonomik Coğrafyası', NULL, 1),
(250, 23, 'Türkiye\'nin İşlevsel Bölgeleri ve Kalkınma Projeleri', NULL, 1),
(251, 23, 'Küresel Ticaret ve Turizm', NULL, 1),
(252, 23, 'Uluslararası Örgütler', NULL, 1),
(253, 23, 'Çevre ve Toplum', NULL, 1);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `exams`
--

CREATE TABLE `exams` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `category` enum('TYT','AYT') NOT NULL,
  `pdf_file` varchar(255) NOT NULL,
  `answer_key` varchar(255) DEFAULT NULL,
  `is_online` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `exam_results`
--

CREATE TABLE `exam_results` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `date` date NOT NULL,
  `category` enum('TYT','AYT') NOT NULL,
  `total_net` float DEFAULT 0,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`))
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `grades`
--

CREATE TABLE `grades` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `grades`
--

INSERT INTO `grades` (`id`, `name`, `slug`) VALUES
(1, '9. Sınıf', '9-sinif'),
(2, '10. Sınıf', '10-sinif'),
(3, '11. Sınıf', '11-sinif'),
(4, '12. Sınıf', '12-sinif'),
(5, 'TYT', 'tyt'),
(6, 'AYT', 'ayt'),
(7, 'LGS', 'lgs');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `live_chat_messages`
--

CREATE TABLE `live_chat_messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(4) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `live_chat_messages`
--

INSERT INTO `live_chat_messages` (`id`, `sender_id`, `receiver_id`, `message`, `is_read`, `created_at`) VALUES
(1, 2, 1, 'hocam merhaba', 1, '2026-01-09 14:31:10'),
(2, 1, 2, 'merhaba çocuğum', 1, '2026-01-09 14:31:52'),
(3, 2, 1, 'hocamm', 1, '2026-01-09 14:56:38'),
(4, 1, 2, 'efendim', 1, '2026-01-09 14:56:53'),
(5, 1, 2, 'noldu', 1, '2026-01-09 16:10:49'),
(6, 1, 2, 'noldu', 1, '2026-01-09 16:10:57'),
(7, 2, 1, 'hiç', 1, '2026-01-09 16:11:56'),
(8, 2, 1, 'hocam', 1, '2026-01-09 16:15:14'),
(9, 2, 1, 'hocam', 1, '2026-01-09 16:16:39'),
(10, 1, 2, 'seni çok seviyorum', 1, '2026-01-09 16:19:54'),
(11, 2, 1, 'bende', 1, '2026-01-09 16:22:13'),
(12, 2, 1, 'merhaba', 1, '2026-01-09 16:25:33'),
(13, 2, 1, 'sa', 1, '2026-01-09 16:31:36'),
(14, 2, 1, 'salamamasas', 1, '2026-01-09 16:34:50'),
(15, 2, 1, 'sonnn', 1, '2026-01-09 16:35:20'),
(16, 2, 1, 'sadafssaf', 1, '2026-01-09 16:37:18'),
(17, 2, 1, 'tamam', 1, '2026-01-12 11:57:46'),
(20, 12, 7, 'saaaam', 1, '2026-01-12 13:49:45'),
(21, 2, 1, 'hocam', 1, '2026-01-14 13:56:58'),
(22, 1, 2, 'efefe', 1, '2026-01-14 13:57:43'),
(23, 2, 1, 'sasa', 1, '2026-01-14 14:03:43'),
(24, 1, 2, 'harika', 1, '2026-01-14 14:08:22');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `materials`
--

CREATE TABLE `materials` (
  `id` int(11) NOT NULL,
  `grade_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `type` varchar(50) DEFAULT 'other',
  `description` text DEFAULT NULL,
  `material_type` enum('video','pdf') NOT NULL,
  `video_url` varchar(200) DEFAULT NULL,
  `pdf_file` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `file_path` varchar(500) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Tablo döküm verisi `materials`
--

INSERT INTO `materials` (`id`, `grade_id`, `title`, `type`, `description`, `material_type`, `video_url`, `pdf_file`, `created_at`, `file_path`) VALUES
(3, 1, 'aaa', 'pdf', NULL, 'video', NULL, NULL, '2025-12-27 20:27:22', 'uploads/1766856442_HSEYN_CAN_FZK_SINAVINA_HAZIRLANIYOR_.pdf'),
(4, 1, 'aaa', 'pdf', NULL, 'video', NULL, NULL, '2025-12-27 20:28:10', 'uploads/1766856490_HSEYN_CAN_FZK_SINAVINA_HAZIRLANIYOR_.pdf');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `packages`
--

CREATE TABLE `packages` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `discount_price` decimal(10,2) DEFAULT NULL,
  `features` mediumtext DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `parent_relationships`
--

CREATE TABLE `parent_relationships` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `parent_relationships`
--

INSERT INTO `parent_relationships` (`id`, `parent_id`, `student_id`, `created_at`) VALUES
(1, 6, 2, '2025-12-29 09:04:52');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` varchar(255) NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('odenmedi','odendi','gecikmis') DEFAULT 'odenmedi',
  `paid_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Tablo döküm verisi `payments`
--

INSERT INTO `payments` (`id`, `student_id`, `teacher_id`, `amount`, `description`, `due_date`, `status`, `paid_date`) VALUES
(596, 2, 1, 0.00, '31.12.2025 Tarihli Seans', '2025-12-31', 'odendi', NULL),
(597, 2, 1, 2900.00, '09.01.2026 Tarihli Seans', '2026-01-09', 'odendi', NULL),
(598, 2, 1, 2900.00, '16.01.2026 Tarihli Seans', '2026-01-16', 'odendi', NULL),
(600, 2, 1, 2900.00, '30.01.2026 Tarihli Seans', '2026-01-30', 'odendi', NULL),
(601, 2, 1, 2900.00, '02.01.2026 Tarihli Seans', '2026-01-02', 'odendi', NULL),
(602, 2, 1, 2900.00, '04.01.2026 Tarihli Seans', '2026-01-04', 'odendi', NULL),
(603, 2, 1, 2900.00, '03.01.2026 Tarihli Seans', '2026-01-03', 'odendi', NULL),
(604, 2, 1, 2900.00, '06.01.2026 Tarihli Seans', '2026-01-06', 'odendi', NULL),
(605, 2, 1, 2900.00, '13.01.2026 Tarihli Seans', '2026-01-13', 'odendi', NULL),
(607, 2, 1, 2900.00, '02.02.2026 Tarihli Seans', '2026-02-02', 'odendi', NULL),
(609, 2, 1, 2900.00, '01.02.2026 Tarihli Seans', '2026-02-01', 'odendi', NULL),
(610, 2, 1, 2900.00, '03.02.2026 Tarihli Seans', '2026-02-03', 'odendi', NULL),
(614, 2, 1, 2000.00, '05.02.2026 Tarihli Seans', '2026-02-05', 'odendi', NULL),
(617, 2, 1, 0.00, '30.12.2025 Tarihli Seans', '2025-12-30', 'odendi', NULL),
(618, 2, 1, 2000.00, '23.01.2026 Tarihli Seans', '2026-01-23', 'odendi', NULL),
(619, 2, 1, 2000.00, '27.01.2026 Tarihli Seans', '2026-01-27', 'odendi', NULL),
(620, 2, 1, 2000.00, '07.02.2026 Tarihli Seans', '2026-02-07', 'odenmedi', NULL),
(621, 2, 1, 1000.00, '09.02.2026 Tarihli Seans', '2026-02-09', 'odenmedi', NULL),
(622, 2, 1, 1000.00, '11.02.2026 Tarihli Seans', '2026-02-11', 'odenmedi', NULL),
(623, 2, 1, 1000.00, '16.02.2026 Tarihli Seans', '2026-02-16', 'odenmedi', NULL),
(624, 2, 1, 1000.00, '23.02.2026 Tarihli Seans', '2026-02-23', 'odenmedi', NULL),
(625, 2, 1, 1000.00, '09.03.2026 Tarihli Seans', '2026-03-09', 'odenmedi', NULL),
(626, 2, 1, 1000.00, '16.03.2026 Tarihli Seans', '2026-03-16', 'odendi', NULL);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `quiz_results`
--

CREATE TABLE `quiz_results` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `exam_name` varchar(255) DEFAULT NULL,
  `category` varchar(50) DEFAULT 'TYT',
  `correct_count` int(11) DEFAULT 0,
  `wrong_count` int(11) DEFAULT 0,
  `empty_count` int(11) DEFAULT 0,
  `total_net` float DEFAULT 0,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `date_taken` timestamp NOT NULL DEFAULT current_timestamp(),
  `exam_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `quiz_results`
--

INSERT INTO `quiz_results` (`id`, `student_id`, `exam_name`, `category`, `correct_count`, `wrong_count`, `empty_count`, `total_net`, `details`, `date_taken`, `exam_id`) VALUES
(2, 2, 'fes', 'TYT', 5, 6, 0, 37.25, '{\"Türkçe\":{\"d\":3,\"y\":0,\"n\":3},\"Tarih\":{\"d\":35,\"y\":3,\"n\":34.25}}', '2025-12-27 21:00:00', 3),
(3, 2, 'tyt test', 'TYT', 0, 14, 6, -3.5, NULL, '2025-12-28 17:32:13', 1),
(5, 2, 'asd', 'AYT', 0, 0, 0, 15, '{\"Matematik\":{\"d\":5,\"y\":0,\"n\":5},\"Fizik\":{\"d\":5,\"y\":0,\"n\":5},\"Kimya\":{\"d\":5,\"y\":0,\"n\":5}}', '2025-12-28 18:02:27', 2),
(8, 2, 'TYT SINIFI', 'TYT', 0, 0, 0, 47, '{\"Türkçe\":{\"d\":22,\"y\":0,\"n\":22},\"Tarih\":{\"d\":1,\"y\":0,\"n\":1},\"Matematik\":{\"d\":22,\"y\":0,\"n\":22},\"Fizik\":{\"d\":1,\"y\":0,\"n\":1},\"Kimya\":{\"d\":1,\"y\":0,\"n\":1}}', '2025-12-28 21:00:00', NULL),
(9, 2, 'özdebir', 'AYT', 0, 0, 0, 24, '{\"Matematik\":{\"d\":22,\"y\":0,\"n\":22},\"Fizik\":{\"d\":1,\"y\":0,\"n\":1},\"Kimya\":{\"d\":1,\"y\":0,\"n\":1}}', '2025-12-28 21:00:00', NULL),
(10, 2, 'nunu', 'TYT', 2, 8, 1, 0, NULL, '2025-12-30 08:36:32', 6),
(11, 2, 'zodi', 'TYT', 0, 0, 0, 25.75, '{\"Türkçe\":{\"d\":22,\"y\":0,\"n\":22},\"Tarih\":{\"d\":5,\"y\":0,\"n\":5},\"Din\":{\"d\":0,\"y\":5,\"n\":-1.25}}', '2026-01-02 21:00:00', NULL),
(12, 2, 'KaRaRGaH', 'TYT', 0, 0, 0, 24, '{\"Türkçe\":{\"d\":6,\"y\":0,\"n\":6},\"Sosyal\":{\"d\":6,\"y\":0,\"n\":6},\"Matematik\":{\"d\":6,\"y\":0,\"n\":6},\"Fen\":{\"d\":6,\"y\":0,\"n\":6}}', '2026-01-02 21:00:00', NULL),
(13, 2, '3d deneme ttt', 'AYT', 0, 0, 0, 39, '{\"Matematik\":{\"d\":33,\"y\":0,\"n\":33},\"Fizik\":{\"d\":2,\"y\":0,\"n\":2},\"Kimya\":{\"d\":2,\"y\":0,\"n\":2},\"Biyoloji\":{\"d\":2,\"y\":0,\"n\":2}}', '2026-01-02 21:00:00', NULL),
(14, 2, 'özdebir', 'TYT', 7, 4, 0, 6, NULL, '2026-01-03 13:27:13', 7),
(15, 2, 'Tetete', 'TYT', 0, 0, 0, 24, '{\"Türkçe\":{\"d\":3,\"y\":0,\"n\":3},\"Sosyal\":{\"d\":5,\"y\":0,\"n\":5},\"Matematik\":{\"d\":7,\"y\":0,\"n\":7},\"Fen\":{\"d\":9,\"y\":0,\"n\":9}}', '2026-01-02 21:00:00', NULL),
(16, 2, '555', 'TYT', 0, 0, 0, 15, '{\"Türkçe\":{\"d\":5,\"y\":0,\"n\":5},\"Sosyal\":{\"d\":5,\"y\":0,\"n\":5},\"Matematik\":{\"d\":5,\"y\":0,\"n\":5}}', '2026-01-02 21:00:00', NULL),
(17, 2, 'Ateş', 'TYT', 0, 0, 0, 49.5, '{\"Türkçe\":{\"d\":33,\"y\":2,\"n\":32.5},\"Sosyal\":{\"d\":3,\"y\":0,\"n\":3},\"Matematik\":{\"d\":3,\"y\":0,\"n\":3},\"Fen\":{\"d\":11,\"y\":0,\"n\":11}}', '2026-01-02 21:00:00', NULL),
(18, 2, 'LİMİT TYT', 'TYT', 5, 6, 0, 3.5, NULL, '2026-01-05 13:35:21', 8);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `schedule_items`
--

CREATE TABLE `schedule_items` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `topic_id` int(11) DEFAULT NULL,
  `custom_subject` varchar(100) DEFAULT '',
  `custom_topic` varchar(200) DEFAULT '',
  `action_type` enum('soru','konu') DEFAULT 'soru',
  `amount` int(11) NOT NULL,
  `target_amount` int(11) DEFAULT NULL,
  `status` enum('bekliyor','yapildi','yarim','yapilmadi') DEFAULT 'bekliyor',
  `time_note` varchar(20) DEFAULT NULL,
  `item_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `schedule_items`
--

INSERT INTO `schedule_items` (`id`, `student_id`, `date`, `topic_id`, `custom_subject`, `custom_topic`, `action_type`, `amount`, `target_amount`, `status`, `time_note`, `item_order`) VALUES
(2, 2, '2025-12-27', 45, 'aaa', 'aaa', 'soru', 331, NULL, 'yapildi', '', 0),
(3, 2, '2025-12-27', 13, '', '', 'soru', 55, NULL, 'yapilmadi', NULL, 0),
(4, 2, '2025-12-27', 45, '', '', 'konu', 111, NULL, 'yapildi', NULL, 0),
(5, 2, '2025-12-24', 45, '', '', 'konu', 111, NULL, 'yapildi', NULL, 0),
(6, 2, '2025-12-27', 63, '', '', 'soru', 20, NULL, 'yapildi', NULL, 0),
(8, 2, '2025-12-25', 61, '', '', 'konu', 6, NULL, 'yapildi', '11:11', 0),
(9, 2, '2025-12-23', 61, '', '', 'soru', 222, NULL, 'yapildi', '', 0),
(10, 2, '2025-12-23', 67, '', '', 'soru', 22, NULL, 'yapildi', '', 0),
(11, 2, '2025-12-22', 67, '', '', 'soru', 22, NULL, 'yapildi', '', 0),
(12, 2, '2025-12-24', 67, '', '', 'soru', 22, NULL, 'yapildi', '', 0),
(13, 2, '2025-12-24', 62, '', '', 'konu', 33, NULL, 'yapildi', '20:00', 0),
(14, 2, '2025-12-30', 62, '', '', 'soru', 33, NULL, 'yapildi', '', 0),
(15, 2, '2026-01-07', 62, '', '', 'soru', 6, NULL, 'yapildi', '', 0),
(16, 2, '2026-01-02', 46, '', '', 'konu', 11, NULL, 'yapildi', '', 0),
(18, 3, '2025-12-31', 62, '', '', 'soru', 333, NULL, 'bekliyor', '', 0),
(19, 2, '2025-12-29', 64, '', '', 'soru', 34, NULL, 'yapildi', '', 0),
(20, 2, '2025-12-29', 16, '', '', 'konu', 33, NULL, 'yapildi', '03:03', 0),
(21, 2, '2026-01-02', 46, '', '', 'soru', 33, NULL, 'yapildi', '', 0),
(22, 2, '2025-12-29', 17, '', '', 'soru', 44, NULL, 'yapildi', '', 0),
(23, 2, '2025-12-28', 63, '', '', 'soru', 33, NULL, 'bekliyor', '', 0),
(24, 2, '2025-12-28', 63, '', '', 'soru', 33, NULL, 'yarim', '', 0),
(25, 2, '2025-12-30', 88, '', '', 'soru', 1, NULL, 'yapilmadi', '', 0),
(29, 2, '2026-01-03', 63, '', '', 'konu', 3, NULL, 'yapildi', '', 0),
(30, 2, '2026-01-01', 87, '', '', 'soru', 4, NULL, 'yapildi', '', 0),
(32, 2, '2025-12-29', 62, '', '', 'soru', 30, NULL, 'yapildi', '', 0),
(33, 2, '2026-01-08', 96, '', '', 'soru', 33, NULL, 'yapildi', '', 0),
(34, 2, '2026-01-05', 64, '', '', 'konu', 20, NULL, 'yarim', '', 0),
(35, 2, '2026-01-01', 62, '', '', 'konu', 11, NULL, 'yapildi', '', 0),
(36, 2, '2026-01-06', 91, '', '', 'soru', 100, NULL, 'yapildi', '', 0),
(37, 2, '2026-01-01', 88, '', '', 'soru', 11, NULL, 'yapildi', '', 0),
(38, 2, '2025-12-31', 91, '', '', 'konu', 5, NULL, 'yapildi', '', 0),
(39, 2, '2025-12-30', 88, '', '', 'soru', 111, NULL, 'yapildi', '', 0),
(40, 2, '2025-12-31', 89, '', '', 'konu', 111, NULL, 'bekliyor', '', 0),
(41, 2, '2025-12-31', 47, '', '', 'soru', 100, NULL, 'bekliyor', '', 0),
(42, 2, '2026-01-02', 17, '', '', 'konu', 90, NULL, 'bekliyor', '15:00', 0),
(43, 2, '2026-01-02', 88, '', '', 'soru', 111, NULL, 'bekliyor', '', 0),
(44, 2, '2026-01-09', 89, '', '', 'soru', 100, NULL, 'yapildi', '', 0),
(50, 2, '2026-01-06', 90, '', '', 'konu', 150, NULL, 'yapildi', '', 0),
(51, 2, '2026-01-08', 90, '', '', 'soru', 22, NULL, 'yapildi', '', 0),
(52, 3, '2026-01-04', 62, '', '', 'konu', 1, NULL, 'bekliyor', '', 0),
(53, 2, '2026-01-04', 78, '', '', 'konu', 33, NULL, 'bekliyor', '', 0),
(54, 2, '2026-01-10', 64, '', '', 'konu', 5, NULL, 'yapildi', '', 0),
(55, 2, '2026-01-12', 63, '', '', 'konu', 50, NULL, 'yarim', '', 0),
(56, 2, '2026-01-11', 89, '', '', 'konu', 30, NULL, 'yapildi', '', 0),
(57, 2, '2026-01-08', 63, '', '', 'konu', 33, NULL, 'yapildi', '', 0),
(58, 2, '2026-01-10', 88, '', '', 'soru', 222, NULL, 'yapildi', '', 0),
(59, 2, '2026-01-10', 47, '', '', 'soru', 200, NULL, 'yapildi', '18:05', 0),
(60, 2, '2026-01-11', 17, '', '', 'konu', 90, NULL, 'yapildi', '', 0),
(61, 2, '2026-01-06', 17, '', '', 'konu', 33, NULL, 'yapildi', '', 0),
(62, 2, '2026-01-08', 12, '', '', 'konu', 22, NULL, 'yapildi', '', 0),
(63, 3, '2026-01-05', 17, '', '', 'soru', 100, NULL, 'yapildi', '', 0),
(64, 3, '2026-01-06', 46, '', '', 'konu', 90, NULL, 'yapildi', '', 0),
(65, 3, '2026-01-07', 62, '', '', 'soru', 100, NULL, 'yapildi', '', 0),
(66, 3, '2026-01-08', 62, '', '', 'konu', 115, NULL, 'yapildi', '', 0),
(67, 3, '2026-01-09', 41, '', '', 'soru', 50, NULL, 'yarim', '', 0),
(68, 2, '2026-01-09', 65, '', '', 'soru', 200, NULL, 'yapildi', '', 0),
(69, 2, '2026-01-12', 29, '', '', 'konu', 1, NULL, 'yapildi', '', 0),
(70, 2, '2026-01-12', 83, '', '', 'soru', 111, NULL, 'bekliyor', '', 0),
(71, 2, '2026-01-07', 83, '', '', 'soru', 111, NULL, 'bekliyor', '', 0),
(72, 2, '2026-01-07', 83, '', '', 'soru', 111, NULL, 'bekliyor', '', 0),
(73, 2, '2026-01-09', 65, '', '', 'soru', 200, NULL, 'yapildi', '', 0),
(74, 2, '2026-01-13', 12, '', '', 'konu', 22, NULL, 'yapildi', '', 0),
(75, 2, '2026-01-06', 83, '', '', 'soru', 111, NULL, 'bekliyor', '', 0),
(76, 2, '2026-01-08', 46, '', '', 'soru', 200, NULL, 'bekliyor', '', 0),
(77, 2, '2026-01-11', 46, '', '', 'soru', 200, NULL, 'bekliyor', '', 0),
(78, 2, '2026-01-09', 46, '', '', 'soru', 200, NULL, 'yarim', '', 0),
(79, 2, '2026-01-09', 46, '', '', 'soru', 200, NULL, 'yapildi', '', 0),
(80, 2, '2026-01-13', 46, '', '', 'soru', 200, NULL, 'yapildi', '', 0),
(82, 2, '2026-01-13', 83, '', '', 'soru', 111, NULL, 'bekliyor', '', 0),
(83, 2, '2026-01-11', 83, '', '', 'soru', 111, NULL, 'bekliyor', '', 0),
(84, 2, '2026-01-13', 83, '', '', 'soru', 111, NULL, 'bekliyor', '', 0),
(85, 2, '2026-01-07', 83, '', '', 'soru', 111, NULL, 'bekliyor', '', 0),
(86, 2, '2026-01-09', 83, '', '', 'soru', 111, NULL, 'yapildi', '', 0),
(87, 2, '2026-01-14', 46, '', '', 'soru', 200, NULL, 'yapildi', '', 0),
(88, 2, '2026-01-07', 46, '', '', 'soru', 200, NULL, 'bekliyor', '', 0),
(90, 2, '2026-01-11', 63, '', '', 'konu', 50, NULL, 'yarim', '', 0),
(91, 2, '2026-01-14', 63, '', '', 'konu', 50, NULL, 'yapildi', '', 0),
(92, 2, '2026-01-13', 130, '', '', 'soru', 333, NULL, 'yapildi', '', 0),
(93, 3, '2026-01-10', 141, '', '', 'soru', 33, NULL, 'bekliyor', '', 0),
(94, 2, '2026-01-15', 137, '', '', 'konu', 90, NULL, 'yapildi', '14:27', 0),
(95, 2, '2026-01-15', 130, '', '', 'soru', 333, NULL, 'yapildi', '', 0),
(96, 2, '2026-01-17', 71, '', '', 'soru', 222, NULL, 'yapildi', '', 0),
(97, 2, '2026-01-15', 71, '', '', 'soru', 222, NULL, 'yapildi', '', 0),
(98, 2, '2026-01-14', 83, '', '', 'soru', 111, NULL, 'yapildi', '', 0),
(99, 2, '2026-01-16', 9, '', '', 'soru', 200, NULL, 'yapilmadi', '', 0),
(100, 2, '2026-01-16', 137, '', '', 'konu', 91, NULL, 'yapildi', '14:27', 0),
(101, 2, '2026-01-16', 130, '', '', 'soru', 333, NULL, 'yapildi', '', 0),
(102, 2, '2026-01-17', 71, '', '', 'konu', 30, NULL, 'yapilmadi', '', 0),
(103, 2, '2026-01-22', 97, '', '', 'soru', 50, NULL, 'yapildi', '', 0),
(104, 2, '2026-01-17', 71, '', '', 'konu', 30, NULL, 'bekliyor', '', 0),
(105, 2, '2026-01-18', 79, '', '', 'soru', 280, NULL, 'bekliyor', '', 0),
(106, 2, '2026-01-18', 71, '', '', 'konu', 30, NULL, 'bekliyor', '', 0),
(108, 2, '2026-01-18', 83, '', '', 'soru', 111, NULL, 'bekliyor', '', 0),
(109, 2, '2026-01-14', 136, '', '', 'soru', 33, NULL, 'bekliyor', '', 0),
(113, 2, '2026-01-22', 135, '', '', 'konu', 30, NULL, 'yapildi', '', 0),
(114, 2, '2026-01-26', 135, '', '', 'konu', 40, 30, 'yapildi', '', 0),
(115, 2, '2026-01-23', 79, '', '', 'soru', 333, NULL, 'yapildi', '', 0),
(122, 2, '2026-01-20', 45, '', '', 'soru', 11, NULL, 'yapildi', '', 0),
(123, 2, '2026-01-25', 45, '', '', 'soru', 13, NULL, 'yapildi', '', 0),
(124, 2, '2026-01-20', 71, '', '', 'konu', 33, NULL, 'yapildi', '', 0),
(125, 2, '2026-01-24', 17, '', '', 'konu', 65, 60, 'yapildi', '', 0),
(126, 2, '2026-01-26', 135, '', '', 'konu', 55, 30, 'yapildi', '', 0),
(128, 2, '2026-01-19', 135, '', '', 'konu', 30, NULL, 'yarim', '', 0),
(129, 2, '2026-01-23', 135, '', '', 'konu', 31, NULL, 'yapildi', '', 0),
(130, 2, '2026-01-24', 135, '', '', 'konu', 33, NULL, 'yapildi', '', 0),
(131, 2, '2026-01-25', 135, '', '', 'konu', 30, 30, 'yapildi', '', 0),
(132, 2, '2026-01-19', 83, '', '', 'konu', 99, NULL, 'bekliyor', '', 0),
(133, 2, '2026-01-21', 64, '', '', 'soru', 333, NULL, 'bekliyor', '', 0),
(134, 2, '2026-01-27', 71, '', '', 'konu', 50, 45, 'yapildi', '', 0),
(135, 2, '2026-01-28', 135, '', '', 'konu', 30, NULL, 'yapildi', '', 0),
(136, 2, '2026-01-27', 135, '', '', 'konu', 35, 30, 'yapildi', '', 0),
(137, 2, '2026-01-29', 135, '', '', 'konu', 30, NULL, 'bekliyor', '', 0),
(139, 2, '2026-01-27', 45, '', '', 'soru', 11, 11, 'yapildi', '', 0),
(141, 2, '2026-01-29', 45, '', '', 'soru', 11, NULL, 'bekliyor', '', 0),
(142, 2, '2026-01-30', 71, '', '', 'konu', 45, NULL, 'bekliyor', '', 0),
(143, 2, '2026-01-28', 71, '', '', 'konu', 45, 45, 'yapildi', '', 0),
(144, 2, '2026-01-29', 71, '', '', 'konu', 45, NULL, 'bekliyor', '', 0),
(145, 2, '2026-01-29', 71, '', '', 'konu', 45, NULL, 'bekliyor', '', 0),
(146, 2, '2026-01-29', 71, '', '', 'konu', 45, NULL, 'bekliyor', '', 0),
(147, 2, '2026-01-25', 62, '', '', 'soru', 11, NULL, 'bekliyor', '', 0),
(148, 2, '2026-01-26', 130, '', '', 'konu', 95, 90, 'yapildi', '', 0),
(149, 2, '2026-01-28', 62, '', '', 'soru', 11, NULL, 'bekliyor', '', 0),
(150, 2, '2026-01-27', 130, '', '', 'konu', 90, NULL, 'bekliyor', '', 0),
(151, 2, '2026-01-24', 130, '', '', 'konu', 90, NULL, 'yapilmadi', '', 0),
(152, 2, '2026-01-23', 44, '', '', 'soru', 50, 20, 'yapildi', '', 0),
(153, 2, '2026-01-27', 68, '', '', 'soru', 30, NULL, 'bekliyor', '', 0),
(154, 2, '2026-01-25', 201, '', '', 'soru', 10, NULL, 'bekliyor', '', 0),
(155, 2, '2026-01-24', 106, '', '', 'soru', 20, NULL, 'bekliyor', '', 0),
(156, 2, '2026-02-01', 92, '', '', 'soru', 20, 20, 'yapildi', '', 0),
(157, 2, '2026-02-01', 171, '', '', 'konu', 30, 30, 'yapildi', '', 0),
(158, 2, '2026-02-01', 59, '', '', 'soru', 30, NULL, 'bekliyor', '', 0),
(160, 2, '2026-02-02', 118, '', '', 'soru', 30, 30, 'yapildi', '', 0),
(161, 2, '2026-02-02', 246, '', '', 'soru', 10, 10, 'yapildi', '', 0),
(162, 2, '2026-02-03', 118, '', '', 'soru', 30, 30, 'yapildi', '', 0),
(164, 2, '2026-02-04', 118, '', '', 'soru', 30, 30, 'yapildi', '', 0),
(166, 2, '2026-02-03', 246, '', '', 'soru', 10, 10, 'yapildi', '', 0),
(167, 2, '2026-02-04', 246, '', '', 'soru', 10, NULL, 'bekliyor', '', 0),
(168, 2, '2026-02-05', 246, '', '', 'soru', 10, 10, 'yapildi', '', 0),
(169, 2, '2026-02-06', 246, '', '', 'soru', 10, 10, 'yapildi', '', 0),
(170, 2, '2026-02-07', 246, '', '', 'soru', 15, 10, 'yapildi', '', 0),
(171, 2, '2026-02-09', 246, '', '', 'soru', 10, 10, 'yapildi', '', 0),
(172, 2, '2026-02-02', 99, '', '', 'soru', 25, NULL, 'bekliyor', '', 0),
(173, 2, '2026-02-03', 99, '', '', 'soru', 25, 25, 'yapildi', '', 0),
(174, 2, '2026-02-04', 99, '', '', 'soru', 25, 25, 'yapildi', '', 0),
(175, 2, '2026-02-05', 99, '', '', 'soru', 25, 25, 'yapildi', '', 0),
(176, 2, '2026-02-06', 99, '', '', 'soru', 25, 25, 'yapildi', '', 0),
(177, 2, '2026-02-07', 99, '', '', 'soru', 25, 25, 'yapildi', '', 0),
(178, 2, '2026-02-08', 99, '', '', 'soru', 25, 25, 'yapildi', '', 0),
(179, 2, '2026-02-03', 70, '', '', 'konu', 60, 60, 'yapildi', '', 0),
(180, 2, '2026-02-02', 70, '', '', 'konu', 60, NULL, 'bekliyor', '', 0),
(181, 2, '2026-02-04', 70, '', '', 'konu', 60, NULL, 'bekliyor', '', 0),
(182, 2, '2026-02-05', 70, '', '', 'konu', 60, 60, 'yapildi', '', 0),
(183, 2, '2026-02-08', 70, '', '', 'konu', 65, 60, 'yarim', '', 1),
(185, 2, '2026-02-07', 70, '', '', 'konu', 60, 60, 'yapildi', '', 0),
(186, 2, '2026-02-05', 72, '', '', 'konu', 60, 60, 'yapildi', '', 0),
(187, 2, '2026-02-07', 72, '', '', 'konu', 60, 60, 'yapildi', '', 0),
(188, 2, '2026-02-03', 112, '', '', 'konu', 40, 40, 'yapildi', '', 0),
(189, 2, '2026-02-04', 112, '', '', 'konu', 40, 40, 'yapildi', '', 0),
(190, 2, '2026-02-06', 112, '', '', 'konu', 40, 40, 'yapildi', '', 0),
(191, 2, '2026-02-05', 112, '', '', 'konu', 40, 40, 'yapildi', '', 0),
(192, 2, '2026-02-07', 112, '', '', 'konu', 40, 40, 'yapildi', '', 0),
(193, 2, '2026-02-03', 112, '', '', 'konu', 40, NULL, 'bekliyor', '', 0),
(194, 2, '2026-02-08', 112, '', '', 'konu', 40, 40, 'yapildi', '', 2),
(195, 2, '2026-02-08', 99, '', '', 'soru', 25, 25, 'yapildi', '', 3),
(197, 2, '2026-02-04', 112, '', '', 'konu', 40, 40, 'yapildi', '', 0),
(198, 2, '2026-02-05', 112, '', '', 'konu', 40, 40, 'yapildi', '', 0),
(199, 2, '2026-02-02', 112, '', '', 'konu', 40, NULL, 'yapildi', '', 0),
(200, 2, '2026-02-04', 70, '', '', 'konu', 60, 60, 'yapildi', '', 0),
(204, 2, '2026-02-08', 112, '', '', 'konu', 40, 40, 'yapildi', '', 4),
(205, 2, '2026-02-05', 70, '', '', 'konu', 60, 60, 'yapildi', '', 0),
(206, 2, '2026-02-06', 70, '', '', 'konu', 60, 60, 'yapildi', '', 0),
(207, 2, '2026-02-08', 70, '', '', 'konu', 60, NULL, 'bekliyor', '', 5),
(209, 2, '2026-02-12', 99, '', '', 'soru', 25, 25, 'yapildi', '', 1),
(210, 2, '2026-02-09', 70, '', '', 'konu', 60, 60, 'yapildi', '', 0),
(211, 2, '2026-02-11', 119, '', '', 'konu', 40, NULL, 'bekliyor', '', 2),
(212, 2, '2026-02-10', 119, '', '', 'konu', 40, 40, 'yapildi', '', 0),
(214, 2, '2026-02-09', 119, '', '', 'konu', 40, 40, 'yapildi', '', 0),
(215, 2, '2026-02-10', 94, '', '', 'soru', 15, 15, 'yapildi', '', 2),
(216, 2, '2026-02-13', 94, '', '', 'soru', 15, NULL, 'bekliyor', '', 2),
(217, 2, '2026-02-11', 94, '', '', 'soru', 15, NULL, 'bekliyor', '', 0),
(218, 2, '2026-02-11', 70, '', '', 'konu', 60, NULL, 'bekliyor', '', 1),
(219, 2, '2026-02-10', 70, '', '', 'konu', 60, NULL, 'bekliyor', '', 3),
(220, 2, '2026-02-12', 70, '', '', 'konu', 60, 60, 'yapildi', '', 0),
(221, 2, '2026-02-13', 70, '', '', 'konu', 60, NULL, 'bekliyor', '', 0),
(222, 2, '2026-02-13', 99, '', '', 'soru', 25, NULL, 'bekliyor', '', 1),
(223, 2, '2026-02-14', 94, '', '', 'soru', 15, 15, 'yapildi', '', 0),
(224, 2, '2026-02-14', 158, '', '', 'soru', 50, 50, 'yapildi', '', 0),
(225, 2, '2026-02-14', 99, '', '', 'soru', 25, NULL, 'bekliyor', '', 0),
(226, 2, '2026-02-06', 72, '', '', 'konu', 60, 60, 'yapildi', '', 0),
(227, 2, '2026-02-09', 70, '', '', 'soru', 24, 24, 'yapildi', '', 0),
(228, 2, '2026-02-12', 112, '', '', 'konu', 40, 40, 'yarim', '', 0),
(229, 2, '2026-02-15', 117, '', '', 'soru', 25, 25, 'yapildi', '', 0),
(230, 2, '2026-02-16', 117, '', '', 'soru', 25, 25, 'yapildi', '', 0),
(231, 2, '2026-02-17', 117, '', '', 'soru', 25, 25, 'yapildi', '', 0),
(232, 2, '2026-02-18', 117, '', '', 'soru', 25, NULL, 'bekliyor', '', 0),
(233, 2, '2026-02-19', 117, '', '', 'soru', 25, 25, 'yapildi', '', 0),
(234, 2, '2026-02-20', 117, '', '', 'soru', 25, 25, 'yapildi', '', 0),
(235, 2, '2026-02-21', 117, '', '', 'soru', 25, 25, 'yapildi', '', 0),
(236, 2, '2026-02-15', 172, '', '', 'konu', 30, 30, 'yapildi', '', 0),
(237, 2, '2026-02-16', 172, '', '', 'konu', 30, 30, 'yapildi', '', 0),
(238, 2, '2026-02-17', 172, '', '', 'konu', 30, 30, 'yapildi', '', 0),
(239, 2, '2026-02-18', 172, '', '', 'konu', 30, NULL, 'bekliyor', '', 0),
(240, 2, '2026-02-19', 172, '', '', 'konu', 30, 30, 'yapildi', '', 0),
(241, 2, '2026-02-20', 172, '', '', 'konu', 30, 30, 'yapildi', '', 0),
(242, 2, '2026-02-21', 172, '', '', 'konu', 30, 30, 'yapildi', '', 0),
(243, 2, '2026-02-15', 68, '', '', 'soru', 20, 20, 'yapildi', '', 0),
(244, 2, '2026-02-16', 68, '', '', 'soru', 20, 20, 'yapildi', '', 0),
(245, 2, '2026-02-17', 68, '', '', 'soru', 20, 20, 'yapildi', '', 0),
(246, 2, '2026-02-18', 68, '', '', 'soru', 20, NULL, 'bekliyor', '', 0),
(247, 2, '2026-02-19', 68, '', '', 'soru', 20, 20, 'yapildi', '', 0),
(248, 2, '2026-02-20', 68, '', '', 'soru', 20, 20, 'yapildi', '', 0),
(249, 2, '2026-02-21', 68, '', '', 'soru', 20, 20, 'yapildi', '', 0),
(250, 2, '2026-02-15', 152, '', '', 'konu', 40, NULL, 'bekliyor', '', 0),
(251, 2, '2026-02-16', 152, '', '', 'konu', 40, 40, 'yapildi', '', 0),
(252, 2, '2026-02-17', 152, '', '', 'konu', 50, 40, 'yapildi', '', 0),
(253, 2, '2026-02-18', 152, '', '', 'konu', 40, NULL, 'bekliyor', '', 0),
(254, 2, '2026-02-19', 152, '', '', 'konu', 40, 40, 'yapildi', '', 0),
(255, 2, '2026-02-20', 152, '', '', 'konu', 40, 40, 'yapildi', '', 0),
(256, 2, '2026-02-21', 152, '', '', 'konu', 40, 40, 'yapildi', '', 0),
(257, 2, '2026-02-22', 117, '', '', 'soru', 25, NULL, 'bekliyor', '', 0),
(258, 2, '2026-02-22', 172, '', '', 'konu', 30, NULL, 'bekliyor', '', 0),
(259, 2, '2026-02-22', 68, '', '', 'soru', 20, NULL, 'bekliyor', '', 0),
(260, 2, '2026-02-22', 152, '', '', 'konu', 40, NULL, 'bekliyor', '', 0),
(261, 2, '2026-02-25', 120, '', '', 'soru', 25, NULL, 'bekliyor', '', 0),
(262, 2, '2026-02-25', 245, '', '', 'soru', 30, NULL, 'bekliyor', '', 0),
(263, 2, '2026-02-25', 73, '', '', 'konu', 50, NULL, 'bekliyor', '', 0),
(264, 2, '2026-02-26', 98, '', '', 'soru', 50, NULL, 'bekliyor', '', 0),
(265, 2, '2026-02-26', 108, '', '', 'soru', 25, NULL, 'bekliyor', '', 0),
(266, 2, '2026-02-26', 93, '', '', 'soru', 75, NULL, 'bekliyor', '', 0),
(267, 2, '2026-02-26', 153, '', '', 'konu', 30, NULL, 'bekliyor', '', 0),
(268, 2, '2026-03-07', 93, '', '', 'soru', 20, NULL, 'bekliyor', '', 0),
(269, 2, '2026-03-07', 203, '', '', 'konu', 40, NULL, 'bekliyor', '', 1),
(270, 2, '2026-03-07', 101, '', '', 'soru', 20, NULL, 'bekliyor', '', 2),
(271, 2, '2026-03-18', 120, '', '', 'konu', 90, 90, 'yapildi', '15:00', 0),
(272, 2, '2026-03-20', 153, '', '', 'konu', 30, NULL, 'bekliyor', '19:30', 0);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `schedule_logs`
--

CREATE TABLE `schedule_logs` (
  `id` int(11) NOT NULL,
  `schedule_item_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `old_amount` int(11) DEFAULT NULL,
  `new_amount` int(11) DEFAULT NULL,
  `change_type` varchar(50) DEFAULT 'amount_update',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `student_topic_targets`
--

CREATE TABLE `student_topic_targets` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `topic_id` int(11) NOT NULL,
  `target_count` int(11) DEFAULT 0,
  `completed_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `subscription_packages`
--

CREATE TABLE `subscription_packages` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `features` mediumtext NOT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `subscription_packages`
--

INSERT INTO `subscription_packages` (`id`, `name`, `price`, `features`, `is_active`) VALUES
(1, 'Video Eğitim Paketi', 750.00, 'Tüm Konu Anlatım Videoları\nPDF Ders Notları\nSoru Çözüm Videoları\n7/24 Erişim', 1),
(2, 'Tam Kapsamlı Koçluk', 2500.00, 'Kişiye Özel Haftalık Program\nBirebir Öğrenci Takibi\nHaftalık Zoom Görüşmesi (30 Dk)\nDeneme Analizleri\nWhatsapp Soru Hattı', 1),
(3, 'Soru Çözüm Kampı', 1200.00, 'Canlı Soru Çözüm Dersleri\nKunduz/Benzeri Soru Hakkı\nÇıkmış Sorular Analizi\nSınav Stratejileri', 1);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `teacher_exams`
--

CREATE TABLE `teacher_exams` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `category` varchar(50) DEFAULT 'TYT',
  `file_path` varchar(500) NOT NULL,
  `is_online` tinyint(1) DEFAULT 0,
  `visible_to` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `answer_key` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `teacher_exams`
--

INSERT INTO `teacher_exams` (`id`, `teacher_id`, `title`, `category`, `file_path`, `is_online`, `visible_to`, `created_at`, `answer_key`) VALUES
(2, 1, 'asd', 'AYT', 'uploads/exams/69516168afcbf_asd.pdf', 0, 'all', '2025-12-28 16:57:12', NULL),
(3, 1, 'fes', 'TYT', 'uploads/exams/695165a66f4a4_fes.pdf', 1, 'all', '2025-12-28 17:15:18', 'ABABACDABAC'),
(4, 1, 'asdasd', 'AYT', 'uploads/exams/695165b53142b_asdasd.pdf', 0, 'all', '2025-12-28 17:15:33', ''),
(5, 1, 'serkan', 'TYT', 'uploads/exams/695166a64921e_serkan.pdf', 1, '[\"3\"]', '2025-12-28 17:19:34', 'ABABACDABAC'),
(6, 1, 'nunu', 'TYT', 'uploads/exams/695169f66f4c5_nunu.pdf', 1, 'all', '2025-12-28 17:33:42', 'ABABACDABAC'),
(7, 1, 'özdebir', 'TYT', 'uploads/exams/69590171254af_zdebir.pdf', 1, 'all', '2026-01-03 11:45:53', 'AACAACDAABA'),
(8, 1, 'LİMİT TYT', 'TYT', 'uploads/exams/695bbd270a6d2_LMTTYT.pdf', 1, 'all', '2026-01-05 13:31:19', 'AACAACDAABA');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `teacher_profiles`
--

CREATE TABLE `teacher_profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `bio` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `teacher_student_unlocks`
--

CREATE TABLE `teacher_student_unlocks` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `unlocked_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `teacher_student_unlocks`
--

INSERT INTO `teacher_student_unlocks` (`id`, `teacher_id`, `student_id`, `unlocked_at`) VALUES
(1, 1, 2, '2026-01-09 14:31:41'),
(2, 1, 12, '2026-01-12 13:47:27');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(150) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `password` varchar(128) NOT NULL,
  `first_name` varchar(150) NOT NULL,
  `last_name` varchar(150) NOT NULL,
  `email` varchar(254) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_staff` tinyint(1) DEFAULT 0,
  `is_superuser` tinyint(1) DEFAULT 0,
  `date_joined` datetime DEFAULT current_timestamp(),
  `phone` varchar(20) DEFAULT NULL,
  `parent_name` varchar(100) DEFAULT NULL,
  `parent_phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','teacher','student','parent') DEFAULT 'student',
  `school_level` varchar(20) DEFAULT 'Lise',
  `branch` varchar(100) DEFAULT 'Genel',
  `bio` text DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `whatsapp_clicks` int(11) DEFAULT 0,
  `credits` int(11) DEFAULT 0,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `membership_expires_at` datetime DEFAULT NULL,
  `is_public_instructor` tinyint(1) DEFAULT 1,
  `current_streak` int(11) DEFAULT 0,
  `max_streak` int(11) DEFAULT 0,
  `last_streak_date` date DEFAULT NULL,
  `freeze_count` int(11) DEFAULT 0,
  `last_repair_date` date DEFAULT NULL,
  `prev_streak` int(11) DEFAULT 0,
  `last_broken_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `users`
--

INSERT INTO `users` (`id`, `username`, `full_name`, `password`, `first_name`, `last_name`, `email`, `is_active`, `is_staff`, `is_superuser`, `date_joined`, `phone`, `parent_name`, `parent_phone`, `role`, `school_level`, `branch`, `bio`, `photo_path`, `whatsapp_clicks`, `credits`, `reset_token`, `reset_expires`, `membership_expires_at`, `is_public_instructor`, `current_streak`, `max_streak`, `last_streak_date`, `freeze_count`, `last_repair_date`, `prev_streak`, `last_broken_date`) VALUES
(1, '7sebahattin', NULL, '$2y$10$MgL/Gl4ob2ftDYd5Y21e8OJJOAQ19JCr2pVZH5ovfX8WKMImjQAym', 'Sebahattin', 'Çakmak', '7sebahattin@gmail.com', 1, 0, 0, '2025-12-27 16:33:08', '05395025214', NULL, NULL, 'teacher', 'Lise', 'Eğitim Koçu', '', 'uploads/profiles/profile_1_1767874524.png', 2, 3, NULL, NULL, '2027-10-31 16:17:00', 1, 0, 0, NULL, 0, NULL, 0, NULL),
(2, 'nurgul', NULL, '$2y$10$opKERCXy9zIkAzNW.tOHc.Jmiggfmg0IW/HhPQx/IG3KWkvxOc3ca', 'Nurgul', 'Çakmak', 'Nurgulmsc@gmail.com', 1, 0, 0, '2025-12-27 17:23:50', '05079181982', 'feride', '05395025214', 'student', 'Lise', 'Genel', NULL, 'uploads/profiles/profile_2_1767874595.png', 0, 0, NULL, NULL, '2028-09-23 16:17:00', 1, 2, 8, '2026-03-18', 0, NULL, 1, '2026-03-02'),
(3, 'serkan', 'serkan', '$2y$10$no3A0peA6QQf1E8hWSnHIuPHjx0Rp7nA9eyS8xB./V/nDD6HRWe0e', 'serkan', 'çakmak', 'serkan@derspros.com', 1, 0, 0, '2025-12-27 17:50:37', '', NULL, NULL, 'student', 'Lise', 'Genel', NULL, NULL, 0, 0, NULL, NULL, NULL, 1, 0, 0, NULL, 0, NULL, 0, NULL),
(6, 'melis', NULL, '$2y$10$OBE/zV9U93bxZmT/y2CiUOC3lJwwLOxHaM6li.MCETkPPJcnmZ9iy', 'melis', 'çakmak', 'melis@derspros.com', 1, 0, 0, '2025-12-29 12:02:08', '05395025266', NULL, NULL, 'parent', 'Lise', 'Genel', NULL, NULL, 0, 0, NULL, NULL, '2028-10-23 20:39:00', 1, 0, 0, NULL, 0, NULL, 0, NULL),
(17, 'adminsebo', NULL, '$2y$10$9GWghFpwnfjw6xbprYTqH.Bry3y3ndPeX2VpJHsLDB0EfYsjDxt62', 'Sebahattin', 'Çakmak', 'sebahattin@derspros.com.tr', 1, 1, 1, '2026-02-16 17:50:33', '05395025214', NULL, NULL, 'admin', 'Lise', 'Genel', NULL, NULL, 0, 0, NULL, NULL, NULL, 1, 0, 0, NULL, 0, NULL, 0, NULL),
(19, 'Şerife', NULL, '$2y$10$byH28K8mYobgUgk6wu6iCOskESsOmRlQyhUr03bwN5K6xAc7N7rm2', 'Şerife', 'Ünal', 'unalsrfunal@gmail.com', 1, 0, 0, '2026-03-20 16:48:59', '05454929902', NULL, NULL, 'teacher', 'Lise', 'Genel', NULL, NULL, 0, 0, NULL, NULL, '2026-09-20 22:09:00', 1, 0, 0, NULL, 0, NULL, 0, NULL),
(20, 'Test', NULL, '$2y$10$kDJqhRyw33iOwj1MEiqB0e3tFHWb210g5EnMKJ9qW70Mq9DdfALiW', 'Test', 'Test', 'test@derspros.com.tr', 1, 0, 0, '2026-03-20 22:11:36', '', NULL, NULL, 'student', 'Lise', 'Genel', NULL, NULL, 0, 0, NULL, NULL, '2026-09-20 22:10:00', 1, 1, 1, '2026-03-23', 0, NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `zoom_sessions`
--

CREATE TABLE `zoom_sessions` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `start_time` datetime NOT NULL,
  `link` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `grade_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Tablo döküm verisi `zoom_sessions`
--

INSERT INTO `zoom_sessions` (`id`, `title`, `start_time`, `link`, `description`, `grade_id`) VALUES
(1, 'Matematik', '2026-01-08 15:33:00', 'https://inherne.net/erstes-gastatelier-in-der-kuenstlerzeche/', NULL, 6);

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_teacher_date` (`teacher_id`,`appointment_date`),
  ADD KEY `idx_student` (`student_id`);

--
-- Tablo için indeksler `appointment_messages`
--
ALTER TABLE `appointment_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_app` (`appointment_id`),
  ADD KEY `idx_app_created` (`appointment_id`,`created_at`),
  ADD KEY `idx_unread_teacher` (`appointment_id`,`is_read_by_teacher`),
  ADD KEY `idx_unread_student` (`appointment_id`,`is_read_by_student`);

--
-- Tablo için indeksler `appointment_requests`
--
ALTER TABLE `appointment_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `requester_user_id` (`requester_user_id`),
  ADD KEY `status` (`status`);

--
-- Tablo için indeksler `click_logs`
--
ALTER TABLE `click_logs`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `coaching_relationships`
--
ALTER TABLE `coaching_relationships`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_coaching` (`teacher_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Tablo için indeksler `coaching_subjects`
--
ALTER TABLE `coaching_subjects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Tablo için indeksler `coaching_topics`
--
ALTER TABLE `coaching_topics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Tablo için indeksler `exams`
--
ALTER TABLE `exams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Tablo için indeksler `exam_results`
--
ALTER TABLE `exam_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Tablo için indeksler `grades`
--
ALTER TABLE `grades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Tablo için indeksler `live_chat_messages`
--
ALTER TABLE `live_chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Tablo için indeksler `materials`
--
ALTER TABLE `materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `grade_id` (`grade_id`);

--
-- Tablo için indeksler `packages`
--
ALTER TABLE `packages`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `parent_relationships`
--
ALTER TABLE `parent_relationships`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `quiz_results`
--
ALTER TABLE `quiz_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Tablo için indeksler `schedule_items`
--
ALTER TABLE `schedule_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `topic_id` (`topic_id`);

--
-- Tablo için indeksler `schedule_logs`
--
ALTER TABLE `schedule_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `schedule_item_id` (`schedule_item_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Tablo için indeksler `student_topic_targets`
--
ALTER TABLE `student_topic_targets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `topic_id` (`topic_id`);

--
-- Tablo için indeksler `subscription_packages`
--
ALTER TABLE `subscription_packages`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `teacher_exams`
--
ALTER TABLE `teacher_exams`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `teacher_profiles`
--
ALTER TABLE `teacher_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Tablo için indeksler `teacher_student_unlocks`
--
ALTER TABLE `teacher_student_unlocks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `teacher_id` (`teacher_id`,`student_id`);

--
-- Tablo için indeksler `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Tablo için indeksler `zoom_sessions`
--
ALTER TABLE `zoom_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_zoom_grade` (`grade_id`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- Tablo için AUTO_INCREMENT değeri `appointment_messages`
--
ALTER TABLE `appointment_messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- Tablo için AUTO_INCREMENT değeri `appointment_requests`
--
ALTER TABLE `appointment_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Tablo için AUTO_INCREMENT değeri `click_logs`
--
ALTER TABLE `click_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Tablo için AUTO_INCREMENT değeri `coaching_relationships`
--
ALTER TABLE `coaching_relationships`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Tablo için AUTO_INCREMENT değeri `coaching_subjects`
--
ALTER TABLE `coaching_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- Tablo için AUTO_INCREMENT değeri `coaching_topics`
--
ALTER TABLE `coaching_topics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=275;

--
-- Tablo için AUTO_INCREMENT değeri `exams`
--
ALTER TABLE `exams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `exam_results`
--
ALTER TABLE `exam_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `grades`
--
ALTER TABLE `grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Tablo için AUTO_INCREMENT değeri `live_chat_messages`
--
ALTER TABLE `live_chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- Tablo için AUTO_INCREMENT değeri `materials`
--
ALTER TABLE `materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Tablo için AUTO_INCREMENT değeri `packages`
--
ALTER TABLE `packages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Tablo için AUTO_INCREMENT değeri `parent_relationships`
--
ALTER TABLE `parent_relationships`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Tablo için AUTO_INCREMENT değeri `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=627;

--
-- Tablo için AUTO_INCREMENT değeri `quiz_results`
--
ALTER TABLE `quiz_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Tablo için AUTO_INCREMENT değeri `schedule_items`
--
ALTER TABLE `schedule_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=273;

--
-- Tablo için AUTO_INCREMENT değeri `schedule_logs`
--
ALTER TABLE `schedule_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `student_topic_targets`
--
ALTER TABLE `student_topic_targets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `subscription_packages`
--
ALTER TABLE `subscription_packages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Tablo için AUTO_INCREMENT değeri `teacher_exams`
--
ALTER TABLE `teacher_exams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Tablo için AUTO_INCREMENT değeri `teacher_profiles`
--
ALTER TABLE `teacher_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `teacher_student_unlocks`
--
ALTER TABLE `teacher_student_unlocks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Tablo için AUTO_INCREMENT değeri `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Tablo için AUTO_INCREMENT değeri `zoom_sessions`
--
ALTER TABLE `zoom_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

--
-- Tablo kısıtlamaları `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `appointment_requests`
--
ALTER TABLE `appointment_requests`
  ADD CONSTRAINT `appointment_requests_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `coaching_relationships`
--
ALTER TABLE `coaching_relationships`
  ADD CONSTRAINT `coaching_relationships_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `coaching_relationships_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `coaching_topics`
--
ALTER TABLE `coaching_topics`
  ADD CONSTRAINT `coaching_topics_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `coaching_subjects` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `exams`
--
ALTER TABLE `exams`
  ADD CONSTRAINT `exams_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `exam_results`
--
ALTER TABLE `exam_results`
  ADD CONSTRAINT `exam_results_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `materials`
--
ALTER TABLE `materials`
  ADD CONSTRAINT `materials_ibfk_1` FOREIGN KEY (`grade_id`) REFERENCES `grades` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `quiz_results`
--
ALTER TABLE `quiz_results`
  ADD CONSTRAINT `quiz_results_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `schedule_items`
--
ALTER TABLE `schedule_items`
  ADD CONSTRAINT `schedule_items_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedule_items_ibfk_2` FOREIGN KEY (`topic_id`) REFERENCES `coaching_topics` (`id`) ON DELETE SET NULL;

--
-- Tablo kısıtlamaları `student_topic_targets`
--
ALTER TABLE `student_topic_targets`
  ADD CONSTRAINT `student_topic_targets_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_topic_targets_ibfk_2` FOREIGN KEY (`topic_id`) REFERENCES `coaching_topics` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `teacher_profiles`
--
ALTER TABLE `teacher_profiles`
  ADD CONSTRAINT `teacher_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `zoom_sessions`
--
ALTER TABLE `zoom_sessions`
  ADD CONSTRAINT `fk_zoom_grade` FOREIGN KEY (`grade_id`) REFERENCES `grades` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

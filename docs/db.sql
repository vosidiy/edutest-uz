-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: Oct 11, 2026 at 06:02 PM
-- Server version: 8.0.44
-- PHP Version: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `edutest`
--

-- --------------------------------------------------------

--
-- Table structure for table `attempts`
--

CREATE TABLE `attempts` (
  `id` bigint UNSIGNED NOT NULL,
  `quiz_id` bigint UNSIGNED NOT NULL,
  `paper_id` bigint UNSIGNED NOT NULL,
  `public_id` char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `token_hash` binary(32) NOT NULL,
  `start_key` char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `shuffle_seed` char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(254) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `ip` varchar(45) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `agent` varchar(512) DEFAULT NULL,
  `status` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT 'in_progress',
  `started_at` datetime(6) NOT NULL,
  `last_activity_at` datetime(6) NOT NULL,
  `client_activity_at` datetime(6) NOT NULL,
  `late_sync` tinyint(1) NOT NULL DEFAULT '0',
  `expires_at` datetime(6) NOT NULL,
  `deadline_reason` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `finished_at` datetime(6) DEFAULT NULL,
  `ended_reason` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `score` int UNSIGNED DEFAULT NULL,
  `max_score` int UNSIGNED NOT NULL,
  `percent` decimal(5,2) DEFAULT NULL
) ;

--
-- Dumping data for table `attempts`
--

INSERT INTO `attempts` (`id`, `quiz_id`, `paper_id`, `public_id`, `token_hash`, `start_key`, `shuffle_seed`, `name`, `email`, `phone`, `ip`, `agent`, `status`, `started_at`, `last_activity_at`, `client_activity_at`, `late_sync`, `expires_at`, `deadline_reason`, `finished_at`, `ended_reason`, `score`, `max_score`, `percent`) VALUES
(1, 1, 6, '005f7bc5c75e3f334ba359c148d136c8', 0xda456a5bb3c4ac6b0f7d61b2897040b319695307451520c10e9e18c14539d5fc, '1cb9f1c50319429361381ac47f9b0d35', '344ac99366ced0362e403a555f73580e', 'Muslim Vosidiy', NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'abandoned', '2026-10-11 15:39:54.551615', '2026-10-11 15:39:54.551615', '2026-10-11 15:39:54.551615', 0, '2026-10-11 15:41:54.551615', 'timer_expired', '2026-10-11 15:41:54.551615', 'timer_expired', 0, 3, 0.00),
(2, 1, 9, 'ac40537093d1c59d72bff3f2b3a8ebe4', 0x9e367bf1685343d06194badeb7146ff83b4283bec4aa08e7bc6199183eaa7bd7, '8cf07a1d1401984d1147eb9e558e7da5', '56c912a48ea069485aabdfc130bd7859', 'Muso', NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'abandoned', '2026-10-11 17:18:03.415648', '2026-10-11 17:18:03.415648', '2026-10-11 17:18:03.415648', 0, '2026-10-11 17:20:03.415648', 'timer_expired', '2026-10-11 17:20:03.415648', 'timer_expired', 0, 3, 0.00),
(3, 1, 9, 'd2389df4253ab4e64c0d5cf6aece90ab', 0xd5b7698832acdf1ee9054a96d199420e18c697b3f73d6b0b14914252db4d3582, '6b47123bc61ede22069084b20a66b2da', '1aa6930ac7736c353eb7e1aa941ebbca', 'Muso2', NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'abandoned', '2026-10-11 17:20:54.726039', '2026-10-11 17:20:54.726039', '2026-10-11 17:20:54.726039', 0, '2026-10-11 17:22:54.726039', 'timer_expired', '2026-10-11 17:22:54.726039', 'timer_expired', 0, 3, 0.00),
(4, 1, 9, 'eaa7a688301df2de19ae1fbef30dd5f8', 0x5a0b6d6565f7d596a9366db05b8dd6db90938d389eb0e3c7fe0367486d17435a, '9c67859e587270bc8a0a89fe1b6bceb4', 'a6b49508fb88834a9d27ed9958e9065a', 'Muso 3', NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'completed', '2026-10-11 17:22:39.118112', '2026-10-11 17:39:56.264693', '2026-10-11 17:22:49.108000', 1, '2026-10-11 17:24:39.118112', 'timer_expired', '2026-10-11 17:22:49.108000', 'completed', 3, 3, 100.00),
(5, 1, 12, '6f0f7622a33769bd974110cd673bffbe', 0x2ca8835ddc0fd5ebc020fc0b72c1c96c1fac140b2e921b1c734177fdeeb5840b, '6f32af77da96b28de66698034bd54a74', '5cda1b0a8406435aaeb779ca0dd415fc', 'Sora', NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'completed', '2026-10-11 17:41:18.620629', '2026-10-11 17:41:39.863041', '2026-10-11 17:41:39.692000', 0, '2026-10-11 17:43:18.620629', 'timer_expired', '2026-10-11 17:41:39.692000', 'completed', 2, 4, 50.00),
(6, 1, 12, 'b4f13ee9bcb66391a083121e43cd337d', 0xf2334ed3b2373e4dfaf7facd3792c14f0b5063338420df0b8d37e4e87e8e8c3a, 'd25b0db3fc9a66a59ff00c53b9ef8e88', 'ad73818c044b1a85eb77e451b6dd5eda', 'Sora 2', NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'completed', '2026-10-11 17:42:10.842494', '2026-10-11 17:42:28.459888', '2026-10-11 17:42:28.292000', 0, '2026-10-11 17:44:10.842494', 'timer_expired', '2026-10-11 17:42:28.292000', 'completed', 1, 4, 25.00),
(7, 1, 12, '0c42233983f2ae2b8826504c08d69ae8', 0x08e1c4b37263eb8fdcb68983484c62ed0ce7839d06ba4dfe76d24d3d63ffde1c, '2a13379567e178daa13b16ecc6058257', '5b2d428ce4fec8b1d0368fda47d19c9b', 'Sora 3', NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'completed', '2026-10-11 17:42:44.489075', '2026-10-11 17:44:13.614822', '2026-10-11 17:43:42.482000', 0, '2026-10-11 17:44:44.489075', 'timer_expired', '2026-10-11 17:43:42.482000', 'completed', 2, 4, 50.00),
(8, 1, 12, '2c6d62d969e1bec956a51b1f6d89d6ae', 0x4a97fc97578d788a9f59369b5c3fe416e9ad37a26837b49882b3e9054632fd9c, '405899f1dd0540d7f4e2db51fbd23d37', '98aa48e7712969e65fa63f3606475ae5', 'Sora 123', NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'completed', '2026-10-11 17:45:00.372898', '2026-10-11 17:45:47.928388', '2026-10-11 17:45:43.717000', 0, '2026-10-11 17:47:00.372898', 'timer_expired', '2026-10-11 17:45:43.717000', 'completed', 3, 4, 75.00),
(9, 1, 12, '083d4f7be91a821d238e8a73a9d261ff', 0x59d4fc1e3a44de3506cf3d1bf4b0d4c67f1b9fc45cbc7cf957dcdf81dcea9744, '4c00dbe28b5149d8e8b208598956bed4', 'b6b862ed8d66bed1bbfcbf04e06728cc', 'Sora Aaa', NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'completed', '2026-10-11 17:46:33.547278', '2026-10-11 17:46:43.654721', '2026-10-11 17:46:43.559000', 0, '2026-10-11 17:48:33.547278', 'timer_expired', '2026-10-11 17:46:43.559000', 'quit', 0, 4, 0.00),
(10, 1, 12, '95a11bf44b12e99372bfb3daa151fa13', 0xc19e36b6432c8b62e2899c7949c7c5601e57e408a55639312bfa094b53469917, 'dd3b44494c997f5b436d6c3fafdebfed', '6f61a24f67e450e306809feba8430f11', 'Muso', NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'abandoned', '2026-10-11 17:47:03.076310', '2026-10-11 17:47:07.662761', '2026-10-11 17:47:07.576000', 0, '2026-10-11 17:49:03.076310', 'timer_expired', '2026-10-11 17:49:03.076310', 'timer_expired', 0, 4, 0.00),
(11, 1, 12, '404be53eefc6208951cc94e0d31bfb04', 0x4a67353f84bde27399f0f8fb6bf946728c30eea97ee7c70a3e5d962f036f17b7, '2941192e5cf3c279afa19110c74db2ce', 'bea668ca49bd1d66193a1dfe23696377', 'Muso mmm', NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'completed', '2026-10-11 17:48:03.595555', '2026-10-11 17:56:24.302896', '2026-10-11 17:48:18.993000', 1, '2026-10-11 17:50:03.595555', 'timer_expired', '2026-10-11 17:50:03.595555', 'timer_expired', 0, 4, 0.00),
(12, 1, 12, 'e9292b9d4c91387c02da107c7579c3d2', 0x29ff6ae24b5455969b279b78aaa5fea3af504fcae48620056f2db6a4d667ea3e, 'e0d378adce1f0e45bd387bfd2e9f5215', '3d6eccb95d31d2283adb0596ea148b92', 'Cheesecake', NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'completed', '2026-10-11 17:56:29.255901', '2026-10-11 17:58:29.461991', '2026-10-11 17:57:11.142000', 1, '2026-10-11 17:58:29.255901', 'timer_expired', '2026-10-11 17:58:29.255901', 'timer_expired', 0, 4, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `attempt_answers`
--

CREATE TABLE `attempt_answers` (
  `id` bigint UNSIGNED NOT NULL,
  `attempt_id` bigint UNSIGNED NOT NULL,
  `question_id` bigint UNSIGNED NOT NULL,
  `pos` smallint UNSIGNED NOT NULL,
  `presented_option_codes` json NOT NULL,
  `status` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT 'not_reached',
  `selected_option_codes` json DEFAULT NULL,
  `text_answer` varchar(500) DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `answered_at` datetime(6) DEFAULT NULL,
  `client_answered_at` datetime(6) DEFAULT NULL
) ;

--
-- Dumping data for table `attempt_answers`
--

INSERT INTO `attempt_answers` (`id`, `attempt_id`, `question_id`, `pos`, `presented_option_codes`, `status`, `selected_option_codes`, `text_answer`, `is_correct`, `answered_at`, `client_answered_at`) VALUES
(1, 1, 1, 1, '[]', 'not_reached', NULL, NULL, NULL, NULL, NULL),
(2, 1, 2, 2, '[\"3e5396f6f773f2ce\", \"89b9b9969ae9b319\", \"618712cb886e4cc1\"]', 'not_reached', NULL, NULL, NULL, NULL, NULL),
(3, 1, 3, 3, '[\"0028cb82cf6d60fe\", \"21b69adc91de9818\", \"5d79e892a97f4f96\"]', 'not_reached', NULL, NULL, NULL, NULL, NULL),
(4, 2, 2, 1, '[\"618712cb886e4cc1\", \"89b9b9969ae9b319\", \"3e5396f6f773f2ce\"]', 'not_reached', NULL, NULL, NULL, NULL, NULL),
(5, 2, 1, 2, '[]', 'not_reached', NULL, NULL, NULL, NULL, NULL),
(6, 2, 3, 3, '[\"21b69adc91de9818\", \"0028cb82cf6d60fe\", \"5d79e892a97f4f96\"]', 'not_reached', NULL, NULL, NULL, NULL, NULL),
(7, 3, 1, 1, '[]', 'not_reached', NULL, NULL, NULL, NULL, NULL),
(8, 3, 3, 2, '[\"5d79e892a97f4f96\", \"21b69adc91de9818\", \"0028cb82cf6d60fe\"]', 'not_reached', NULL, NULL, NULL, NULL, NULL),
(9, 3, 2, 3, '[\"89b9b9969ae9b319\", \"3e5396f6f773f2ce\", \"618712cb886e4cc1\"]', 'not_reached', NULL, NULL, NULL, NULL, NULL),
(10, 4, 2, 1, '[\"618712cb886e4cc1\", \"89b9b9969ae9b319\", \"3e5396f6f773f2ce\"]', 'answered', '[\"618712cb886e4cc1\"]', NULL, 1, '2026-10-11 17:39:55.969257', '2026-10-11 17:22:41.385000'),
(11, 4, 1, 2, '[]', 'answered', NULL, 'alaykum', 1, '2026-10-11 17:39:56.090669', '2026-10-11 17:22:45.573000'),
(12, 4, 3, 3, '[\"0028cb82cf6d60fe\", \"21b69adc91de9818\", \"5d79e892a97f4f96\"]', 'answered', '[\"21b69adc91de9818\", \"5d79e892a97f4f96\"]', NULL, 1, '2026-10-11 17:39:56.183696', '2026-10-11 17:22:49.108000'),
(13, 5, 2, 1, '[\"89b9b9969ae9b319\", \"3e5396f6f773f2ce\", \"618712cb886e4cc1\"]', 'answered', '[\"618712cb886e4cc1\"]', NULL, 1, '2026-10-11 17:41:31.641646', '2026-10-11 17:41:31.554000'),
(14, 5, 4, 2, '[\"e203d07aa8a889a7\", \"5dde5822394f085d\", \"5e3b8567caccba5d\"]', 'answered', '[\"e203d07aa8a889a7\"]', NULL, 1, '2026-10-11 17:41:35.710466', '2026-10-11 17:41:35.624000'),
(15, 5, 1, 3, '[]', 'skipped', NULL, NULL, 0, '2026-10-11 17:41:37.532534', '2026-10-11 17:41:37.441000'),
(16, 5, 3, 4, '[\"0028cb82cf6d60fe\", \"5d79e892a97f4f96\", \"21b69adc91de9818\"]', 'skipped', NULL, NULL, 0, '2026-10-11 17:41:39.783704', '2026-10-11 17:41:39.692000'),
(17, 6, 3, 1, '[\"0028cb82cf6d60fe\", \"5d79e892a97f4f96\", \"21b69adc91de9818\"]', 'answered', '[\"21b69adc91de9818\"]', NULL, 0, '2026-10-11 17:42:16.274615', '2026-10-11 17:42:16.184000'),
(18, 6, 2, 2, '[\"3e5396f6f773f2ce\", \"89b9b9969ae9b319\", \"618712cb886e4cc1\"]', 'skipped', NULL, NULL, 0, '2026-10-11 17:42:20.691859', '2026-10-11 17:42:20.604000'),
(19, 6, 1, 3, '[]', 'skipped', NULL, NULL, 0, '2026-10-11 17:42:23.681364', '2026-10-11 17:42:23.589000'),
(20, 6, 4, 4, '[\"5e3b8567caccba5d\", \"e203d07aa8a889a7\", \"5dde5822394f085d\"]', 'answered', '[\"e203d07aa8a889a7\"]', NULL, 1, '2026-10-11 17:42:28.381010', '2026-10-11 17:42:28.292000'),
(21, 7, 2, 1, '[\"618712cb886e4cc1\", \"3e5396f6f773f2ce\", \"89b9b9969ae9b319\"]', 'answered', '[\"618712cb886e4cc1\"]', NULL, 1, '2026-10-11 17:42:49.944768', '2026-10-11 17:42:49.859000'),
(22, 7, 4, 2, '[\"5e3b8567caccba5d\", \"5dde5822394f085d\", \"e203d07aa8a889a7\"]', 'answered', '[\"e203d07aa8a889a7\"]', NULL, 1, '2026-10-11 17:43:10.693879', '2026-10-11 17:43:10.608000'),
(23, 7, 3, 3, '[\"0028cb82cf6d60fe\", \"21b69adc91de9818\", \"5d79e892a97f4f96\"]', 'answered', '[\"0028cb82cf6d60fe\"]', NULL, 0, '2026-10-11 17:44:07.284384', '2026-10-11 17:43:38.077000'),
(24, 7, 1, 4, '[]', 'answered', NULL, 'asdas', 0, '2026-10-11 17:44:13.529398', '2026-10-11 17:43:42.481000'),
(25, 8, 3, 1, '[\"21b69adc91de9818\", \"0028cb82cf6d60fe\", \"5d79e892a97f4f96\"]', 'answered', '[\"0028cb82cf6d60fe\", \"21b69adc91de9818\", \"5d79e892a97f4f96\"]', NULL, 0, '2026-10-11 17:45:07.920812', '2026-10-11 17:45:07.791000'),
(26, 8, 1, 2, '[]', 'answered', NULL, 'alaykum', 1, '2026-10-11 17:45:13.324079', '2026-10-11 17:45:13.228000'),
(27, 8, 4, 3, '[\"e203d07aa8a889a7\", \"5dde5822394f085d\", \"5e3b8567caccba5d\"]', 'answered', '[\"e203d07aa8a889a7\"]', NULL, 1, '2026-10-11 17:45:37.840463', '2026-10-11 17:45:31.375000'),
(28, 8, 2, 4, '[\"618712cb886e4cc1\", \"3e5396f6f773f2ce\", \"89b9b9969ae9b319\"]', 'answered', '[\"618712cb886e4cc1\"]', NULL, 1, '2026-10-11 17:45:47.825893', '2026-10-11 17:45:43.717000'),
(29, 9, 2, 1, '[\"618712cb886e4cc1\", \"3e5396f6f773f2ce\", \"89b9b9969ae9b319\"]', 'not_reached', NULL, NULL, NULL, NULL, NULL),
(30, 9, 3, 2, '[\"21b69adc91de9818\", \"0028cb82cf6d60fe\", \"5d79e892a97f4f96\"]', 'not_reached', NULL, NULL, NULL, NULL, NULL),
(31, 9, 4, 3, '[\"e203d07aa8a889a7\", \"5e3b8567caccba5d\", \"5dde5822394f085d\"]', 'not_reached', NULL, NULL, NULL, NULL, NULL),
(32, 9, 1, 4, '[]', 'not_reached', NULL, NULL, NULL, NULL, NULL),
(33, 10, 2, 1, '[\"89b9b9969ae9b319\", \"3e5396f6f773f2ce\", \"618712cb886e4cc1\"]', 'answered', '[\"89b9b9969ae9b319\"]', NULL, 0, '2026-10-11 17:47:07.662761', '2026-10-11 17:47:07.576000'),
(34, 10, 1, 2, '[]', 'not_reached', NULL, NULL, NULL, NULL, NULL),
(35, 10, 3, 3, '[\"21b69adc91de9818\", \"5d79e892a97f4f96\", \"0028cb82cf6d60fe\"]', 'not_reached', NULL, NULL, NULL, NULL, NULL),
(36, 10, 4, 4, '[\"5e3b8567caccba5d\", \"5dde5822394f085d\", \"e203d07aa8a889a7\"]', 'not_reached', NULL, NULL, NULL, NULL, NULL),
(37, 11, 1, 1, '[]', 'skipped', NULL, NULL, 0, '2026-10-11 17:48:16.802197', '2026-10-11 17:48:16.709000'),
(38, 11, 2, 2, '[\"618712cb886e4cc1\", \"3e5396f6f773f2ce\", \"89b9b9969ae9b319\"]', 'skipped', NULL, NULL, 0, '2026-10-11 17:48:17.914493', '2026-10-11 17:48:17.826000'),
(39, 11, 4, 3, '[\"5e3b8567caccba5d\", \"5dde5822394f085d\", \"e203d07aa8a889a7\"]', 'skipped', NULL, NULL, 0, '2026-10-11 17:48:19.080659', '2026-10-11 17:48:18.993000'),
(40, 11, 3, 4, '[\"21b69adc91de9818\", \"5d79e892a97f4f96\", \"0028cb82cf6d60fe\"]', 'not_reached', NULL, NULL, NULL, NULL, NULL),
(41, 12, 1, 1, '[]', 'skipped', NULL, NULL, 0, '2026-10-11 17:57:09.512728', '2026-10-11 17:57:09.407000'),
(42, 12, 4, 2, '[\"e203d07aa8a889a7\", \"5dde5822394f085d\", \"5e3b8567caccba5d\"]', 'skipped', NULL, NULL, 0, '2026-10-11 17:57:11.230769', '2026-10-11 17:57:11.142000'),
(43, 12, 2, 3, '[\"3e5396f6f773f2ce\", \"618712cb886e4cc1\", \"89b9b9969ae9b319\"]', 'not_reached', NULL, NULL, NULL, NULL, NULL),
(44, 12, 3, 4, '[\"0028cb82cf6d60fe\", \"5d79e892a97f4f96\", \"21b69adc91de9818\"]', 'not_reached', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `cheat_events`
--

CREATE TABLE `cheat_events` (
  `id` bigint UNSIGNED NOT NULL,
  `attempt_id` bigint UNSIGNED NOT NULL,
  `event_key` char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `type` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `happened_at` datetime(6) DEFAULT NULL,
  `received_at` datetime(6) NOT NULL,
  `duration_ms` int UNSIGNED DEFAULT NULL,
  `data` json NOT NULL
) ;

-- --------------------------------------------------------

--
-- Table structure for table `practice_keys`
--

CREATE TABLE `practice_keys` (
  `quiz_id` bigint UNSIGNED NOT NULL,
  `paper_id` bigint UNSIGNED NOT NULL,
  `request_key` char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `expires_at` datetime(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `practice_keys`
--

INSERT INTO `practice_keys` (`quiz_id`, `paper_id`, `request_key`, `expires_at`) VALUES
(1, 4, '02bc3deca244ca148078722e7d0eabfa', '2026-10-12 15:27:50.646577'),
(1, 4, '052ab50abf8192a5988383cb98b66e35', '2026-10-12 15:31:05.849669'),
(1, 3, '102e1af978d487348ba708132b7b3873', '2026-10-12 15:01:44.084282'),
(1, 3, '2a4f901a6c5b514237bc4e07f4588bfd', '2026-10-12 14:48:53.938213'),
(1, 2, '2d6eb97aa8f0f8e61efcf7ca329debef', '2026-10-12 12:49:56.629152'),
(1, 4, '40a4842ab4a10014ba3c80d64cf0a178', '2026-10-12 15:34:00.169851'),
(1, 3, '42b99f122436e454ba264c2503cebd6c', '2026-10-12 14:47:18.504796'),
(1, 4, '48c5e5236a61a2c019cd9a8ac46f7306', '2026-10-12 15:37:12.513983'),
(1, 3, '4e9e984a8c0cb902a0c6486b6611d6cb', '2026-10-12 15:14:28.744877'),
(1, 8, '5ac78439cb3464236a1b3dbdb3fd11d4', '2026-10-12 16:42:26.934140'),
(1, 3, '5b176db6d3e60f0c1429235fc70890f6', '2026-10-12 15:26:29.364430'),
(1, 3, '71ffa8c5ec4e2a10998b569e86096e76', '2026-10-12 15:24:26.033393'),
(1, 2, '829c23f7c6225861dde6c35ecf814158', '2026-10-12 13:33:05.321952'),
(1, 3, '8b1b2690fa3d649be31f1707e7add4f8', '2026-10-12 15:26:57.065912'),
(1, 8, '8eb01f702cc326101930cd9bf93e69a2', '2026-10-12 16:41:31.118103'),
(1, 2, '9550cd9e80ba5e7540680d11f37286c0', '2026-10-12 14:29:22.404841'),
(1, 8, '96ba907440eb4818ee8abc4b50f2a89e', '2026-10-12 17:16:14.208655'),
(1, 3, '9f9586a544565266606608bb51797695', '2026-10-12 14:44:49.207919'),
(1, 3, 'a465c4af1d7bdbaad8594be270ab87d6', '2026-10-12 14:43:16.243717'),
(1, 2, 'a63984cfa0a861037e1422679da50b4e', '2026-10-12 14:34:05.714178'),
(1, 2, 'a88558b731c4d991699505974f2e6f2f', '2026-10-12 14:41:14.413860'),
(1, 2, 'a907b049837707474b380c23ffd94c6c', '2026-10-12 14:30:40.292413'),
(1, 3, 'b615ce7ec9aaed45c8e4bf9516fe0dd7', '2026-10-12 14:51:25.842971'),
(1, 8, 'bcd8efb8735e0f6f42a856e1e5483210', '2026-10-12 16:41:45.662009'),
(1, 3, 'c1b423bd2fdfbd1c399f890fb1a9af9c', '2026-10-12 15:06:22.722431'),
(1, 2, 'ca3f05c5bfc8f5c25d28e60ae43ca9fa', '2026-10-12 13:31:23.368837'),
(1, 10, 'd3811c2092ce344d901e3809710e88f2', '2026-10-12 17:40:00.908026'),
(1, 8, 'e7a715b9874a8cd32e0a8529bc2781a3', '2026-10-12 17:15:52.981504'),
(1, 8, 'f1b3fd3ba6a378c9b0e746dcd550bd58', '2026-10-12 16:43:17.892538');

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

CREATE TABLE `questions` (
  `id` bigint UNSIGNED NOT NULL,
  `quiz_id` bigint UNSIGNED NOT NULL,
  `pos` smallint UNSIGNED NOT NULL,
  `type` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `content` text NOT NULL,
  `media_type` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `media_src` varchar(1000) DEFAULT NULL,
  `explanation` text,
  `text_answers` json DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL
) ;

--
-- Dumping data for table `questions`
--

INSERT INTO `questions` (`id`, `quiz_id`, `pos`, `type`, `content`, `media_type`, `media_src`, `explanation`, `text_answers`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'short_text', 'Assamalu ___?', NULL, NULL, NULL, '[\"alaykum\"]', '2026-10-11 12:43:04.373328', '2026-10-11 17:41:02.355121'),
(2, 1, 2, 'single_choice', 'Which is animal', NULL, NULL, NULL, NULL, '2026-10-11 12:43:11.646527', '2026-10-11 17:41:02.356003'),
(3, 1, 3, 'multi_select', 'Which is insect', NULL, NULL, NULL, NULL, '2026-10-11 12:46:49.247795', '2026-10-11 17:41:02.358462'),
(4, 1, 4, 'single_choice', 'Xayr ingliz tilida', NULL, NULL, NULL, NULL, '2026-10-11 17:40:42.223171', '2026-10-11 17:41:02.361299');

-- --------------------------------------------------------

--
-- Table structure for table `question_options`
--

CREATE TABLE `question_options` (
  `id` bigint UNSIGNED NOT NULL,
  `question_id` bigint UNSIGNED NOT NULL,
  `pos` smallint UNSIGNED NOT NULL,
  `code` char(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `content` text NOT NULL,
  `media_type` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `media_src` varchar(1000) DEFAULT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL
) ;

--
-- Dumping data for table `question_options`
--

INSERT INTO `question_options` (`id`, `question_id`, `pos`, `code`, `content`, `media_type`, `media_src`, `is_correct`, `created_at`, `updated_at`) VALUES
(3, 2, 1, '618712cb886e4cc1', 'Donkey', NULL, NULL, 1, '2026-10-11 12:43:11.647199', '2026-10-11 17:41:02.357473'),
(4, 2, 2, '3e5396f6f773f2ce', 'Car', NULL, NULL, 0, '2026-10-11 12:43:11.647578', '2026-10-11 17:41:02.357807'),
(11, 2, 3, '89b9b9969ae9b319', 'Phone', NULL, NULL, 0, '2026-10-11 12:45:52.063095', '2026-10-11 17:41:02.358138'),
(14, 3, 1, '5d79e892a97f4f96', 'Ants', NULL, NULL, 1, '2026-10-11 12:46:52.225510', '2026-10-11 17:41:02.360308'),
(15, 3, 2, '21b69adc91de9818', 'Bugs', NULL, NULL, 1, '2026-10-11 12:46:52.225920', '2026-10-11 17:41:02.360645'),
(16, 3, 3, '0028cb82cf6d60fe', 'Fishes', NULL, NULL, 0, '2026-10-11 12:47:06.312202', '2026-10-11 17:41:02.360971'),
(17, 4, 1, 'e203d07aa8a889a7', 'Bye', NULL, NULL, 1, '2026-10-11 17:40:42.223841', '2026-10-11 17:41:02.362799'),
(18, 4, 2, '5e3b8567caccba5d', 'Hi', NULL, NULL, 0, '2026-10-11 17:40:42.224208', '2026-10-11 17:41:02.363390'),
(19, 4, 3, '5dde5822394f085d', 'Well', NULL, NULL, 0, '2026-10-11 17:40:58.823985', '2026-10-11 17:41:02.363883');

-- --------------------------------------------------------

--
-- Table structure for table `quizzes`
--

CREATE TABLE `quizzes` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `public_id` char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `share_token` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `mode` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT 'assessment',
  `status` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT 'draft',
  `listed` tinyint(1) NOT NULL DEFAULT '0',
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `cover_src` varchar(1000) DEFAULT NULL,
  `revision` int UNSIGNED NOT NULL DEFAULT '1',
  `version` int UNSIGNED NOT NULL DEFAULT '1',
  `current_paper_id` bigint UNSIGNED DEFAULT NULL,
  `time_limit_sec` int UNSIGNED DEFAULT NULL,
  `opens_at` datetime(6) DEFAULT NULL,
  `closes_at` datetime(6) DEFAULT NULL,
  `passcode_hash` varchar(255) DEFAULT NULL,
  `email_mode` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT 'optional',
  `phone_mode` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT 'hidden',
  `shuffle_questions` tinyint(1) NOT NULL DEFAULT '0',
  `shuffle_options` tinyint(1) NOT NULL DEFAULT '0',
  `feedback` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT 'at_end',
  `show_score` tinyint(1) NOT NULL DEFAULT '1',
  `show_answers` tinyint(1) NOT NULL DEFAULT '0',
  `show_explain` tinyint(1) NOT NULL DEFAULT '0',
  `cheat_check` tinyint(1) NOT NULL DEFAULT '0',
  `practice_starts` bigint UNSIGNED NOT NULL DEFAULT '0',
  `published_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL,
  `deleted_at` datetime(6) DEFAULT NULL
) ;

--
-- Dumping data for table `quizzes`
--

INSERT INTO `quizzes` (`id`, `user_id`, `public_id`, `share_token`, `mode`, `status`, `listed`, `title`, `description`, `cover_src`, `revision`, `version`, `current_paper_id`, `time_limit_sec`, `opens_at`, `closes_at`, `passcode_hash`, `email_mode`, `phone_mode`, `shuffle_questions`, `shuffle_options`, `feedback`, `show_score`, `show_answers`, `show_explain`, `cheat_check`, `practice_starts`, `published_at`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, '89784e0f4d34123f3880b166e2a2c3d1', '298990088', 'assessment', 'published', 0, 'English for beginner', '', NULL, 64, 76, 12, 120, NULL, NULL, NULL, 'hidden', 'hidden', 1, 1, 'at_end', 1, 0, 0, 0, 29, '2026-10-11 12:47:28.052323', '2026-10-11 12:32:54.905187', '2026-10-11 18:02:13.789526', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `quiz_papers`
--

CREATE TABLE `quiz_papers` (
  `id` bigint UNSIGNED NOT NULL,
  `quiz_id` bigint UNSIGNED NOT NULL,
  `public_id` char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `revision` int UNSIGNED NOT NULL,
  `passcode_hash` varchar(255) DEFAULT NULL,
  `definition` json NOT NULL,
  `created_at` datetime(6) NOT NULL
) ;

--
-- Dumping data for table `quiz_papers`
--

INSERT INTO `quiz_papers` (`id`, `quiz_id`, `public_id`, `revision`, `passcode_hash`, `definition`, `created_at`) VALUES
(1, 1, '0836875f18b14047c4ef6631e83113db', 32, NULL, '{\"quiz\": {\"mode\": \"practice\", \"cover\": null, \"title\": \"English for beginner\", \"opensAt\": null, \"closesAt\": null, \"feedback\": \"at_end\", \"emailMode\": \"hidden\", \"phoneMode\": \"hidden\", \"showScore\": true, \"cheatCheck\": false, \"shareToken\": \"298990088\", \"description\": \"\", \"showAnswers\": false, \"showExplain\": false, \"timeLimitSec\": null, \"timingPolicy\": \"client_deadline_late_sync_v1\", \"scoringPolicy\": \"all_or_nothing_v1\", \"shuffleOptions\": false, \"passcodeRequired\": false, \"shuffleQuestions\": false}, \"questions\": [{\"id\": \"1\", \"type\": \"short_text\", \"media\": null, \"content\": \"Assamalu ___?\", \"options\": [], \"position\": 1, \"explanation\": \"\", \"correctCodes\": [], \"acceptedAnswers\": [\"alaykum\", \"Alekum\"]}, {\"id\": \"2\", \"type\": \"single_choice\", \"media\": null, \"content\": \"Which is animal\", \"options\": [{\"id\": \"3\", \"code\": \"618712cb886e4cc1\", \"media\": null, \"content\": \"Donkey\", \"position\": 1, \"isCorrect\": true}, {\"id\": \"4\", \"code\": \"3e5396f6f773f2ce\", \"media\": null, \"content\": \"Car\", \"position\": 2, \"isCorrect\": false}, {\"id\": \"11\", \"code\": \"89b9b9969ae9b319\", \"media\": null, \"content\": \"Phone\", \"position\": 3, \"isCorrect\": false}], \"position\": 2, \"explanation\": \"\", \"correctCodes\": [\"618712cb886e4cc1\"], \"acceptedAnswers\": []}, {\"id\": \"3\", \"type\": \"multi_select\", \"media\": null, \"content\": \"Which is insect\", \"options\": [{\"id\": \"14\", \"code\": \"5d79e892a97f4f96\", \"media\": null, \"content\": \"Ants\", \"position\": 1, \"isCorrect\": true}, {\"id\": \"15\", \"code\": \"21b69adc91de9818\", \"media\": null, \"content\": \"Bugs\", \"position\": 2, \"isCorrect\": true}, {\"id\": \"16\", \"code\": \"0028cb82cf6d60fe\", \"media\": null, \"content\": \"Fishes\", \"position\": 3, \"isCorrect\": false}], \"position\": 3, \"explanation\": \"\", \"correctCodes\": [\"5d79e892a97f4f96\", \"21b69adc91de9818\"], \"acceptedAnswers\": []}], \"schemaVersion\": 3}', '2026-10-11 12:47:28.051033'),
(2, 1, '7c0d790243ccd0b0c113d7c418343284', 34, NULL, '{\"quiz\": {\"mode\": \"practice\", \"cover\": null, \"title\": \"English for beginner\", \"opensAt\": null, \"closesAt\": null, \"feedback\": \"at_end\", \"emailMode\": \"hidden\", \"phoneMode\": \"hidden\", \"showScore\": true, \"cheatCheck\": false, \"shareToken\": \"298990088\", \"description\": \"\", \"showAnswers\": false, \"showExplain\": false, \"timeLimitSec\": null, \"timingPolicy\": \"client_deadline_late_sync_v1\", \"scoringPolicy\": \"all_or_nothing_v1\", \"shuffleOptions\": true, \"passcodeRequired\": false, \"shuffleQuestions\": true}, \"questions\": [{\"id\": \"1\", \"type\": \"short_text\", \"media\": null, \"content\": \"Assamalu ___?\", \"options\": [], \"position\": 1, \"explanation\": \"\", \"correctCodes\": [], \"acceptedAnswers\": [\"alaykum\", \"Alekum\"]}, {\"id\": \"2\", \"type\": \"single_choice\", \"media\": null, \"content\": \"Which is animal\", \"options\": [{\"id\": \"3\", \"code\": \"618712cb886e4cc1\", \"media\": null, \"content\": \"Donkey\", \"position\": 1, \"isCorrect\": true}, {\"id\": \"4\", \"code\": \"3e5396f6f773f2ce\", \"media\": null, \"content\": \"Car\", \"position\": 2, \"isCorrect\": false}, {\"id\": \"11\", \"code\": \"89b9b9969ae9b319\", \"media\": null, \"content\": \"Phone\", \"position\": 3, \"isCorrect\": false}], \"position\": 2, \"explanation\": \"\", \"correctCodes\": [\"618712cb886e4cc1\"], \"acceptedAnswers\": []}, {\"id\": \"3\", \"type\": \"multi_select\", \"media\": null, \"content\": \"Which is insect\", \"options\": [{\"id\": \"14\", \"code\": \"5d79e892a97f4f96\", \"media\": null, \"content\": \"Ants\", \"position\": 1, \"isCorrect\": true}, {\"id\": \"15\", \"code\": \"21b69adc91de9818\", \"media\": null, \"content\": \"Bugs\", \"position\": 2, \"isCorrect\": true}, {\"id\": \"16\", \"code\": \"0028cb82cf6d60fe\", \"media\": null, \"content\": \"Fishes\", \"position\": 3, \"isCorrect\": false}], \"position\": 3, \"explanation\": \"\", \"correctCodes\": [\"5d79e892a97f4f96\", \"21b69adc91de9818\"], \"acceptedAnswers\": []}], \"schemaVersion\": 3}', '2026-10-11 12:48:13.329430'),
(3, 1, '2712588b0a19a13dde2dc36c470e78bc', 42, NULL, '{\"quiz\": {\"mode\": \"practice\", \"cover\": null, \"title\": \"English for beginner\", \"opensAt\": null, \"closesAt\": null, \"feedback\": \"at_end\", \"emailMode\": \"hidden\", \"phoneMode\": \"hidden\", \"showScore\": true, \"cheatCheck\": false, \"shareToken\": \"298990088\", \"description\": \"\", \"showAnswers\": false, \"showExplain\": false, \"timeLimitSec\": 120, \"timingPolicy\": \"client_deadline_late_sync_v1\", \"scoringPolicy\": \"all_or_nothing_v1\", \"shuffleOptions\": true, \"passcodeRequired\": false, \"shuffleQuestions\": true}, \"questions\": [{\"id\": \"1\", \"type\": \"short_text\", \"media\": null, \"content\": \"Assamalu ___?\", \"options\": [], \"position\": 1, \"explanation\": \"\", \"correctCodes\": [], \"acceptedAnswers\": [\"alaykum\"]}, {\"id\": \"2\", \"type\": \"single_choice\", \"media\": null, \"content\": \"Which is animal\", \"options\": [{\"id\": \"3\", \"code\": \"618712cb886e4cc1\", \"media\": null, \"content\": \"Donkey\", \"position\": 1, \"isCorrect\": true}, {\"id\": \"4\", \"code\": \"3e5396f6f773f2ce\", \"media\": null, \"content\": \"Car\", \"position\": 2, \"isCorrect\": false}, {\"id\": \"11\", \"code\": \"89b9b9969ae9b319\", \"media\": null, \"content\": \"Phone\", \"position\": 3, \"isCorrect\": false}], \"position\": 2, \"explanation\": \"\", \"correctCodes\": [\"618712cb886e4cc1\"], \"acceptedAnswers\": []}, {\"id\": \"3\", \"type\": \"multi_select\", \"media\": null, \"content\": \"Which is insect\", \"options\": [{\"id\": \"14\", \"code\": \"5d79e892a97f4f96\", \"media\": null, \"content\": \"Ants\", \"position\": 1, \"isCorrect\": true}, {\"id\": \"15\", \"code\": \"21b69adc91de9818\", \"media\": null, \"content\": \"Bugs\", \"position\": 2, \"isCorrect\": true}, {\"id\": \"16\", \"code\": \"0028cb82cf6d60fe\", \"media\": null, \"content\": \"Fishes\", \"position\": 3, \"isCorrect\": false}], \"position\": 3, \"explanation\": \"\", \"correctCodes\": [\"5d79e892a97f4f96\", \"21b69adc91de9818\"], \"acceptedAnswers\": []}], \"schemaVersion\": 3}', '2026-10-11 14:42:28.413883'),
(4, 1, '0049cf1a57cbbc7ff3049978258842ca', 45, NULL, '{\"quiz\": {\"mode\": \"practice\", \"cover\": null, \"title\": \"English for beginner\", \"opensAt\": null, \"closesAt\": null, \"feedback\": \"after_each\", \"emailMode\": \"hidden\", \"phoneMode\": \"hidden\", \"showScore\": true, \"cheatCheck\": false, \"shareToken\": \"298990088\", \"description\": \"\", \"showAnswers\": true, \"showExplain\": true, \"timeLimitSec\": 120, \"timingPolicy\": \"client_deadline_late_sync_v1\", \"scoringPolicy\": \"all_or_nothing_v1\", \"shuffleOptions\": true, \"passcodeRequired\": false, \"shuffleQuestions\": true}, \"questions\": [{\"id\": \"1\", \"type\": \"short_text\", \"media\": null, \"content\": \"Assamalu ___?\", \"options\": [], \"position\": 1, \"explanation\": \"\", \"correctCodes\": [], \"acceptedAnswers\": [\"alaykum\"]}, {\"id\": \"2\", \"type\": \"single_choice\", \"media\": null, \"content\": \"Which is animal\", \"options\": [{\"id\": \"3\", \"code\": \"618712cb886e4cc1\", \"media\": null, \"content\": \"Donkey\", \"position\": 1, \"isCorrect\": true}, {\"id\": \"4\", \"code\": \"3e5396f6f773f2ce\", \"media\": null, \"content\": \"Car\", \"position\": 2, \"isCorrect\": false}, {\"id\": \"11\", \"code\": \"89b9b9969ae9b319\", \"media\": null, \"content\": \"Phone\", \"position\": 3, \"isCorrect\": false}], \"position\": 2, \"explanation\": \"\", \"correctCodes\": [\"618712cb886e4cc1\"], \"acceptedAnswers\": []}, {\"id\": \"3\", \"type\": \"multi_select\", \"media\": null, \"content\": \"Which is insect\", \"options\": [{\"id\": \"14\", \"code\": \"5d79e892a97f4f96\", \"media\": null, \"content\": \"Ants\", \"position\": 1, \"isCorrect\": true}, {\"id\": \"15\", \"code\": \"21b69adc91de9818\", \"media\": null, \"content\": \"Bugs\", \"position\": 2, \"isCorrect\": true}, {\"id\": \"16\", \"code\": \"0028cb82cf6d60fe\", \"media\": null, \"content\": \"Fishes\", \"position\": 3, \"isCorrect\": false}], \"position\": 3, \"explanation\": \"\", \"correctCodes\": [\"5d79e892a97f4f96\", \"21b69adc91de9818\"], \"acceptedAnswers\": []}], \"schemaVersion\": 3}', '2026-10-11 15:27:42.637706'),
(5, 1, '0993c787c6b1660a9eea34a3fbf23de5', 47, NULL, '{\"quiz\": {\"mode\": \"assessment\", \"cover\": null, \"title\": \"English for beginner\", \"opensAt\": null, \"closesAt\": null, \"feedback\": \"after_each\", \"emailMode\": \"hidden\", \"phoneMode\": \"hidden\", \"showScore\": true, \"cheatCheck\": true, \"shareToken\": \"298990088\", \"description\": \"\", \"showAnswers\": true, \"showExplain\": true, \"timeLimitSec\": 120, \"timingPolicy\": \"client_deadline_late_sync_v1\", \"scoringPolicy\": \"all_or_nothing_v1\", \"shuffleOptions\": true, \"passcodeRequired\": false, \"shuffleQuestions\": true}, \"questions\": [{\"id\": \"1\", \"type\": \"short_text\", \"media\": null, \"content\": \"Assamalu ___?\", \"options\": [], \"position\": 1, \"explanation\": \"\", \"correctCodes\": [], \"acceptedAnswers\": [\"alaykum\"]}, {\"id\": \"2\", \"type\": \"single_choice\", \"media\": null, \"content\": \"Which is animal\", \"options\": [{\"id\": \"3\", \"code\": \"618712cb886e4cc1\", \"media\": null, \"content\": \"Donkey\", \"position\": 1, \"isCorrect\": true}, {\"id\": \"4\", \"code\": \"3e5396f6f773f2ce\", \"media\": null, \"content\": \"Car\", \"position\": 2, \"isCorrect\": false}, {\"id\": \"11\", \"code\": \"89b9b9969ae9b319\", \"media\": null, \"content\": \"Phone\", \"position\": 3, \"isCorrect\": false}], \"position\": 2, \"explanation\": \"\", \"correctCodes\": [\"618712cb886e4cc1\"], \"acceptedAnswers\": []}, {\"id\": \"3\", \"type\": \"multi_select\", \"media\": null, \"content\": \"Which is insect\", \"options\": [{\"id\": \"14\", \"code\": \"5d79e892a97f4f96\", \"media\": null, \"content\": \"Ants\", \"position\": 1, \"isCorrect\": true}, {\"id\": \"15\", \"code\": \"21b69adc91de9818\", \"media\": null, \"content\": \"Bugs\", \"position\": 2, \"isCorrect\": true}, {\"id\": \"16\", \"code\": \"0028cb82cf6d60fe\", \"media\": null, \"content\": \"Fishes\", \"position\": 3, \"isCorrect\": false}], \"position\": 3, \"explanation\": \"\", \"correctCodes\": [\"5d79e892a97f4f96\", \"21b69adc91de9818\"], \"acceptedAnswers\": []}], \"schemaVersion\": 3}', '2026-10-11 15:38:26.935511'),
(6, 1, '7f864cbbe77e627c2a0e79d3e6a26851', 49, NULL, '{\"quiz\": {\"mode\": \"assessment\", \"cover\": null, \"title\": \"English for beginner\", \"opensAt\": null, \"closesAt\": null, \"feedback\": \"at_end\", \"emailMode\": \"hidden\", \"phoneMode\": \"hidden\", \"showScore\": true, \"cheatCheck\": true, \"shareToken\": \"298990088\", \"description\": \"\", \"showAnswers\": false, \"showExplain\": false, \"timeLimitSec\": 120, \"timingPolicy\": \"client_deadline_late_sync_v1\", \"scoringPolicy\": \"all_or_nothing_v1\", \"shuffleOptions\": true, \"passcodeRequired\": false, \"shuffleQuestions\": true}, \"questions\": [{\"id\": \"1\", \"type\": \"short_text\", \"media\": null, \"content\": \"Assamalu ___?\", \"options\": [], \"position\": 1, \"explanation\": \"\", \"correctCodes\": [], \"acceptedAnswers\": [\"alaykum\"]}, {\"id\": \"2\", \"type\": \"single_choice\", \"media\": null, \"content\": \"Which is animal\", \"options\": [{\"id\": \"3\", \"code\": \"618712cb886e4cc1\", \"media\": null, \"content\": \"Donkey\", \"position\": 1, \"isCorrect\": true}, {\"id\": \"4\", \"code\": \"3e5396f6f773f2ce\", \"media\": null, \"content\": \"Car\", \"position\": 2, \"isCorrect\": false}, {\"id\": \"11\", \"code\": \"89b9b9969ae9b319\", \"media\": null, \"content\": \"Phone\", \"position\": 3, \"isCorrect\": false}], \"position\": 2, \"explanation\": \"\", \"correctCodes\": [\"618712cb886e4cc1\"], \"acceptedAnswers\": []}, {\"id\": \"3\", \"type\": \"multi_select\", \"media\": null, \"content\": \"Which is insect\", \"options\": [{\"id\": \"14\", \"code\": \"5d79e892a97f4f96\", \"media\": null, \"content\": \"Ants\", \"position\": 1, \"isCorrect\": true}, {\"id\": \"15\", \"code\": \"21b69adc91de9818\", \"media\": null, \"content\": \"Bugs\", \"position\": 2, \"isCorrect\": true}, {\"id\": \"16\", \"code\": \"0028cb82cf6d60fe\", \"media\": null, \"content\": \"Fishes\", \"position\": 3, \"isCorrect\": false}], \"position\": 3, \"explanation\": \"\", \"correctCodes\": [\"5d79e892a97f4f96\", \"21b69adc91de9818\"], \"acceptedAnswers\": []}], \"schemaVersion\": 3}', '2026-10-11 15:38:46.919981'),
(7, 1, '2fdb05bd0c672e9e042ed098b39af722', 51, NULL, '{\"quiz\": {\"mode\": \"assessment\", \"cover\": {\"key\": \"78b0354dd836f4536c0e77f5616ccf2d\", \"src\": \"quiz-media/1/1/dce8e66497f8f76fd2afbd452f6872210e62060a.jpg\", \"type\": \"image\"}, \"title\": \"English for beginner\", \"opensAt\": null, \"closesAt\": null, \"feedback\": \"at_end\", \"emailMode\": \"hidden\", \"phoneMode\": \"hidden\", \"showScore\": true, \"cheatCheck\": false, \"shareToken\": \"298990088\", \"description\": \"\", \"showAnswers\": false, \"showExplain\": false, \"timeLimitSec\": 120, \"timingPolicy\": \"client_deadline_late_sync_v1\", \"scoringPolicy\": \"all_or_nothing_v1\", \"shuffleOptions\": true, \"passcodeRequired\": false, \"shuffleQuestions\": true}, \"questions\": [{\"id\": \"1\", \"type\": \"short_text\", \"media\": null, \"content\": \"Assamalu ___?\", \"options\": [], \"position\": 1, \"explanation\": \"\", \"correctCodes\": [], \"acceptedAnswers\": [\"alaykum\"]}, {\"id\": \"2\", \"type\": \"single_choice\", \"media\": null, \"content\": \"Which is animal\", \"options\": [{\"id\": \"3\", \"code\": \"618712cb886e4cc1\", \"media\": null, \"content\": \"Donkey\", \"position\": 1, \"isCorrect\": true}, {\"id\": \"4\", \"code\": \"3e5396f6f773f2ce\", \"media\": null, \"content\": \"Car\", \"position\": 2, \"isCorrect\": false}, {\"id\": \"11\", \"code\": \"89b9b9969ae9b319\", \"media\": null, \"content\": \"Phone\", \"position\": 3, \"isCorrect\": false}], \"position\": 2, \"explanation\": \"\", \"correctCodes\": [\"618712cb886e4cc1\"], \"acceptedAnswers\": []}, {\"id\": \"3\", \"type\": \"multi_select\", \"media\": null, \"content\": \"Which is insect\", \"options\": [{\"id\": \"14\", \"code\": \"5d79e892a97f4f96\", \"media\": null, \"content\": \"Ants\", \"position\": 1, \"isCorrect\": true}, {\"id\": \"15\", \"code\": \"21b69adc91de9818\", \"media\": null, \"content\": \"Bugs\", \"position\": 2, \"isCorrect\": true}, {\"id\": \"16\", \"code\": \"0028cb82cf6d60fe\", \"media\": null, \"content\": \"Fishes\", \"position\": 3, \"isCorrect\": false}], \"position\": 3, \"explanation\": \"\", \"correctCodes\": [\"5d79e892a97f4f96\", \"21b69adc91de9818\"], \"acceptedAnswers\": []}], \"schemaVersion\": 3}', '2026-10-11 16:30:21.433472'),
(8, 1, '81ea3b4194ca83a721969ea5ff4cc6c3', 52, NULL, '{\"quiz\": {\"mode\": \"practice\", \"cover\": {\"key\": \"2be6f6e8e6cf4c458527f1f2d856deb1\", \"src\": \"quiz-media/1/1/dce8e66497f8f76fd2afbd452f6872210e62060a.jpg\", \"type\": \"image\"}, \"title\": \"English for beginner\", \"opensAt\": null, \"closesAt\": null, \"feedback\": \"at_end\", \"emailMode\": \"hidden\", \"phoneMode\": \"hidden\", \"showScore\": true, \"cheatCheck\": false, \"shareToken\": \"298990088\", \"description\": \"\", \"showAnswers\": false, \"showExplain\": false, \"timeLimitSec\": 120, \"timingPolicy\": \"client_deadline_late_sync_v1\", \"scoringPolicy\": \"all_or_nothing_v1\", \"shuffleOptions\": true, \"passcodeRequired\": false, \"shuffleQuestions\": true}, \"questions\": [{\"id\": \"1\", \"type\": \"short_text\", \"media\": null, \"content\": \"Assamalu ___?\", \"options\": [], \"position\": 1, \"explanation\": \"\", \"correctCodes\": [], \"acceptedAnswers\": [\"alaykum\"]}, {\"id\": \"2\", \"type\": \"single_choice\", \"media\": null, \"content\": \"Which is animal\", \"options\": [{\"id\": \"3\", \"code\": \"618712cb886e4cc1\", \"media\": null, \"content\": \"Donkey\", \"position\": 1, \"isCorrect\": true}, {\"id\": \"4\", \"code\": \"3e5396f6f773f2ce\", \"media\": null, \"content\": \"Car\", \"position\": 2, \"isCorrect\": false}, {\"id\": \"11\", \"code\": \"89b9b9969ae9b319\", \"media\": null, \"content\": \"Phone\", \"position\": 3, \"isCorrect\": false}], \"position\": 2, \"explanation\": \"\", \"correctCodes\": [\"618712cb886e4cc1\"], \"acceptedAnswers\": []}, {\"id\": \"3\", \"type\": \"multi_select\", \"media\": null, \"content\": \"Which is insect\", \"options\": [{\"id\": \"14\", \"code\": \"5d79e892a97f4f96\", \"media\": null, \"content\": \"Ants\", \"position\": 1, \"isCorrect\": true}, {\"id\": \"15\", \"code\": \"21b69adc91de9818\", \"media\": null, \"content\": \"Bugs\", \"position\": 2, \"isCorrect\": true}, {\"id\": \"16\", \"code\": \"0028cb82cf6d60fe\", \"media\": null, \"content\": \"Fishes\", \"position\": 3, \"isCorrect\": false}], \"position\": 3, \"explanation\": \"\", \"correctCodes\": [\"5d79e892a97f4f96\", \"21b69adc91de9818\"], \"acceptedAnswers\": []}], \"schemaVersion\": 3}', '2026-10-11 16:38:21.375769'),
(9, 1, '8e9e5501f6abd57ac9b5153867e85e44', 53, NULL, '{\"quiz\": {\"mode\": \"assessment\", \"cover\": {\"key\": \"d405bd1e2f3c4a166df6dfc15080ba83\", \"src\": \"quiz-media/1/1/dce8e66497f8f76fd2afbd452f6872210e62060a.jpg\", \"type\": \"image\"}, \"title\": \"English for beginner\", \"opensAt\": null, \"closesAt\": null, \"feedback\": \"at_end\", \"emailMode\": \"hidden\", \"phoneMode\": \"hidden\", \"showScore\": true, \"cheatCheck\": false, \"shareToken\": \"298990088\", \"description\": \"\", \"showAnswers\": false, \"showExplain\": false, \"timeLimitSec\": 120, \"timingPolicy\": \"client_deadline_late_sync_v1\", \"scoringPolicy\": \"all_or_nothing_v1\", \"shuffleOptions\": true, \"passcodeRequired\": false, \"shuffleQuestions\": true}, \"questions\": [{\"id\": \"1\", \"type\": \"short_text\", \"media\": null, \"content\": \"Assamalu ___?\", \"options\": [], \"position\": 1, \"explanation\": \"\", \"correctCodes\": [], \"acceptedAnswers\": [\"alaykum\"]}, {\"id\": \"2\", \"type\": \"single_choice\", \"media\": null, \"content\": \"Which is animal\", \"options\": [{\"id\": \"3\", \"code\": \"618712cb886e4cc1\", \"media\": null, \"content\": \"Donkey\", \"position\": 1, \"isCorrect\": true}, {\"id\": \"4\", \"code\": \"3e5396f6f773f2ce\", \"media\": null, \"content\": \"Car\", \"position\": 2, \"isCorrect\": false}, {\"id\": \"11\", \"code\": \"89b9b9969ae9b319\", \"media\": null, \"content\": \"Phone\", \"position\": 3, \"isCorrect\": false}], \"position\": 2, \"explanation\": \"\", \"correctCodes\": [\"618712cb886e4cc1\"], \"acceptedAnswers\": []}, {\"id\": \"3\", \"type\": \"multi_select\", \"media\": null, \"content\": \"Which is insect\", \"options\": [{\"id\": \"14\", \"code\": \"5d79e892a97f4f96\", \"media\": null, \"content\": \"Ants\", \"position\": 1, \"isCorrect\": true}, {\"id\": \"15\", \"code\": \"21b69adc91de9818\", \"media\": null, \"content\": \"Bugs\", \"position\": 2, \"isCorrect\": true}, {\"id\": \"16\", \"code\": \"0028cb82cf6d60fe\", \"media\": null, \"content\": \"Fishes\", \"position\": 3, \"isCorrect\": false}], \"position\": 3, \"explanation\": \"\", \"correctCodes\": [\"5d79e892a97f4f96\", \"21b69adc91de9818\"], \"acceptedAnswers\": []}], \"schemaVersion\": 3}', '2026-10-11 17:16:36.606521'),
(10, 1, 'ee1974896dd22b2546c396a8b704eb8b', 54, NULL, '{\"quiz\": {\"mode\": \"practice\", \"cover\": {\"key\": \"d0222909c3552d6fbbf8723e4727315b\", \"src\": \"quiz-media/1/1/dce8e66497f8f76fd2afbd452f6872210e62060a.jpg\", \"type\": \"image\"}, \"title\": \"English for beginner\", \"opensAt\": null, \"closesAt\": null, \"feedback\": \"at_end\", \"emailMode\": \"hidden\", \"phoneMode\": \"hidden\", \"showScore\": true, \"cheatCheck\": false, \"shareToken\": \"298990088\", \"description\": \"\", \"showAnswers\": false, \"showExplain\": false, \"timeLimitSec\": 120, \"timingPolicy\": \"client_deadline_late_sync_v1\", \"scoringPolicy\": \"all_or_nothing_v1\", \"shuffleOptions\": true, \"passcodeRequired\": false, \"shuffleQuestions\": true}, \"questions\": [{\"id\": \"1\", \"type\": \"short_text\", \"media\": null, \"content\": \"Assamalu ___?\", \"options\": [], \"position\": 1, \"explanation\": \"\", \"correctCodes\": [], \"acceptedAnswers\": [\"alaykum\"]}, {\"id\": \"2\", \"type\": \"single_choice\", \"media\": null, \"content\": \"Which is animal\", \"options\": [{\"id\": \"3\", \"code\": \"618712cb886e4cc1\", \"media\": null, \"content\": \"Donkey\", \"position\": 1, \"isCorrect\": true}, {\"id\": \"4\", \"code\": \"3e5396f6f773f2ce\", \"media\": null, \"content\": \"Car\", \"position\": 2, \"isCorrect\": false}, {\"id\": \"11\", \"code\": \"89b9b9969ae9b319\", \"media\": null, \"content\": \"Phone\", \"position\": 3, \"isCorrect\": false}], \"position\": 2, \"explanation\": \"\", \"correctCodes\": [\"618712cb886e4cc1\"], \"acceptedAnswers\": []}, {\"id\": \"3\", \"type\": \"multi_select\", \"media\": null, \"content\": \"Which is insect\", \"options\": [{\"id\": \"14\", \"code\": \"5d79e892a97f4f96\", \"media\": null, \"content\": \"Ants\", \"position\": 1, \"isCorrect\": true}, {\"id\": \"15\", \"code\": \"21b69adc91de9818\", \"media\": null, \"content\": \"Bugs\", \"position\": 2, \"isCorrect\": true}, {\"id\": \"16\", \"code\": \"0028cb82cf6d60fe\", \"media\": null, \"content\": \"Fishes\", \"position\": 3, \"isCorrect\": false}], \"position\": 3, \"explanation\": \"\", \"correctCodes\": [\"5d79e892a97f4f96\", \"21b69adc91de9818\"], \"acceptedAnswers\": []}], \"schemaVersion\": 3}', '2026-10-11 17:23:48.462772'),
(11, 1, '1e4d73a22f894bdb3a35c24051326d15', 55, NULL, '{\"quiz\": {\"mode\": \"assessment\", \"cover\": {\"key\": \"19c18559f64b646291318276f4227d64\", \"src\": \"quiz-media/1/1/dce8e66497f8f76fd2afbd452f6872210e62060a.jpg\", \"type\": \"image\"}, \"title\": \"English for beginner\", \"opensAt\": null, \"closesAt\": null, \"feedback\": \"at_end\", \"emailMode\": \"hidden\", \"phoneMode\": \"hidden\", \"showScore\": true, \"cheatCheck\": false, \"shareToken\": \"298990088\", \"description\": \"\", \"showAnswers\": false, \"showExplain\": false, \"timeLimitSec\": 120, \"timingPolicy\": \"client_deadline_late_sync_v1\", \"scoringPolicy\": \"all_or_nothing_v1\", \"shuffleOptions\": true, \"passcodeRequired\": false, \"shuffleQuestions\": true}, \"questions\": [{\"id\": \"1\", \"type\": \"short_text\", \"media\": null, \"content\": \"Assamalu ___?\", \"options\": [], \"position\": 1, \"explanation\": \"\", \"correctCodes\": [], \"acceptedAnswers\": [\"alaykum\"]}, {\"id\": \"2\", \"type\": \"single_choice\", \"media\": null, \"content\": \"Which is animal\", \"options\": [{\"id\": \"3\", \"code\": \"618712cb886e4cc1\", \"media\": null, \"content\": \"Donkey\", \"position\": 1, \"isCorrect\": true}, {\"id\": \"4\", \"code\": \"3e5396f6f773f2ce\", \"media\": null, \"content\": \"Car\", \"position\": 2, \"isCorrect\": false}, {\"id\": \"11\", \"code\": \"89b9b9969ae9b319\", \"media\": null, \"content\": \"Phone\", \"position\": 3, \"isCorrect\": false}], \"position\": 2, \"explanation\": \"\", \"correctCodes\": [\"618712cb886e4cc1\"], \"acceptedAnswers\": []}, {\"id\": \"3\", \"type\": \"multi_select\", \"media\": null, \"content\": \"Which is insect\", \"options\": [{\"id\": \"14\", \"code\": \"5d79e892a97f4f96\", \"media\": null, \"content\": \"Ants\", \"position\": 1, \"isCorrect\": true}, {\"id\": \"15\", \"code\": \"21b69adc91de9818\", \"media\": null, \"content\": \"Bugs\", \"position\": 2, \"isCorrect\": true}, {\"id\": \"16\", \"code\": \"0028cb82cf6d60fe\", \"media\": null, \"content\": \"Fishes\", \"position\": 3, \"isCorrect\": false}], \"position\": 3, \"explanation\": \"\", \"correctCodes\": [\"5d79e892a97f4f96\", \"21b69adc91de9818\"], \"acceptedAnswers\": []}], \"schemaVersion\": 3}', '2026-10-11 17:40:32.925056'),
(12, 1, '8d863aee3b27ebf1794345f37bdccdab', 63, NULL, '{\"quiz\": {\"mode\": \"assessment\", \"cover\": {\"key\": \"4ebdd4ec5c777e54725be406eca9449f\", \"src\": \"quiz-media/1/1/dce8e66497f8f76fd2afbd452f6872210e62060a.jpg\", \"type\": \"image\"}, \"title\": \"English for beginner\", \"opensAt\": null, \"closesAt\": null, \"feedback\": \"at_end\", \"emailMode\": \"hidden\", \"phoneMode\": \"hidden\", \"showScore\": true, \"cheatCheck\": false, \"shareToken\": \"298990088\", \"description\": \"\", \"showAnswers\": false, \"showExplain\": false, \"timeLimitSec\": 120, \"timingPolicy\": \"client_deadline_late_sync_v1\", \"scoringPolicy\": \"all_or_nothing_v1\", \"shuffleOptions\": true, \"passcodeRequired\": false, \"shuffleQuestions\": true}, \"questions\": [{\"id\": \"1\", \"type\": \"short_text\", \"media\": null, \"content\": \"Assamalu ___?\", \"options\": [], \"position\": 1, \"explanation\": \"\", \"correctCodes\": [], \"acceptedAnswers\": [\"alaykum\"]}, {\"id\": \"2\", \"type\": \"single_choice\", \"media\": null, \"content\": \"Which is animal\", \"options\": [{\"id\": \"3\", \"code\": \"618712cb886e4cc1\", \"media\": null, \"content\": \"Donkey\", \"position\": 1, \"isCorrect\": true}, {\"id\": \"4\", \"code\": \"3e5396f6f773f2ce\", \"media\": null, \"content\": \"Car\", \"position\": 2, \"isCorrect\": false}, {\"id\": \"11\", \"code\": \"89b9b9969ae9b319\", \"media\": null, \"content\": \"Phone\", \"position\": 3, \"isCorrect\": false}], \"position\": 2, \"explanation\": \"\", \"correctCodes\": [\"618712cb886e4cc1\"], \"acceptedAnswers\": []}, {\"id\": \"3\", \"type\": \"multi_select\", \"media\": null, \"content\": \"Which is insect\", \"options\": [{\"id\": \"14\", \"code\": \"5d79e892a97f4f96\", \"media\": null, \"content\": \"Ants\", \"position\": 1, \"isCorrect\": true}, {\"id\": \"15\", \"code\": \"21b69adc91de9818\", \"media\": null, \"content\": \"Bugs\", \"position\": 2, \"isCorrect\": true}, {\"id\": \"16\", \"code\": \"0028cb82cf6d60fe\", \"media\": null, \"content\": \"Fishes\", \"position\": 3, \"isCorrect\": false}], \"position\": 3, \"explanation\": \"\", \"correctCodes\": [\"5d79e892a97f4f96\", \"21b69adc91de9818\"], \"acceptedAnswers\": []}, {\"id\": \"4\", \"type\": \"single_choice\", \"media\": null, \"content\": \"Xayr ingliz tilida\", \"options\": [{\"id\": \"17\", \"code\": \"e203d07aa8a889a7\", \"media\": null, \"content\": \"Bye\", \"position\": 1, \"isCorrect\": true}, {\"id\": \"18\", \"code\": \"5e3b8567caccba5d\", \"media\": null, \"content\": \"Hi\", \"position\": 2, \"isCorrect\": false}, {\"id\": \"19\", \"code\": \"5dde5822394f085d\", \"media\": null, \"content\": \"Well\", \"position\": 3, \"isCorrect\": false}], \"position\": 4, \"explanation\": \"\", \"correctCodes\": [\"e203d07aa8a889a7\"], \"acceptedAnswers\": []}], \"schemaVersion\": 3}', '2026-10-11 17:41:04.164043');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL,
  `email` varchar(254) NOT NULL,
  `password_hash` varchar(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `display_name` varchar(120) NOT NULL,
  `phone` varchar(16) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `bio` varchar(1000) NOT NULL DEFAULT '',
  `timezone` varchar(64) NOT NULL DEFAULT 'Asia/Tashkent',
  `public_page` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `password_reset_hash` binary(32) DEFAULT NULL,
  `password_reset_expires_at` datetime(6) DEFAULT NULL,
  `last_login_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL,
  `deleted_at` datetime(6) DEFAULT NULL
) ;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `display_name`, `phone`, `bio`, `timezone`, `public_page`, `active`, `password_reset_hash`, `password_reset_expires_at`, `last_login_at`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'vosidiy@gmail.com', '$2y$10$DymvSbOYHw2w03Kb6gVvUO75Fv4nh/CAv8yoCg7CCisDG7GI2MRLS', 'Muslim', '+998946875461', '', 'Asia/Tashkent', 0, 1, NULL, NULL, '2026-10-11 12:32:10.000000', '2026-10-11 12:32:10.000000', '2026-10-11 12:32:10.000000', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attempts`
--
ALTER TABLE `attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_attempts_public_id` (`public_id`),
  ADD UNIQUE KEY `uq_attempts_token_hash` (`token_hash`),
  ADD UNIQUE KEY `uq_attempts_quiz_start_key` (`quiz_id`,`start_key`),
  ADD UNIQUE KEY `uq_attempts_id_quiz` (`id`,`quiz_id`),
  ADD KEY `fk_attempts_paper` (`paper_id`,`quiz_id`),
  ADD KEY `ix_attempt_report` (`quiz_id`,`status`,`started_at`),
  ADD KEY `ix_attempt_expiry` (`status`,`expires_at`);

--
-- Indexes for table `attempt_answers`
--
ALTER TABLE `attempt_answers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_attempt_answers_question` (`attempt_id`,`question_id`),
  ADD UNIQUE KEY `uq_attempt_answers_position` (`attempt_id`,`pos`);

--
-- Indexes for table `cheat_events`
--
ALTER TABLE `cheat_events`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cheat_events_attempt_event_key` (`attempt_id`,`event_key`),
  ADD KEY `ix_cheat_timeline` (`attempt_id`,`received_at`);

--
-- Indexes for table `practice_keys`
--
ALTER TABLE `practice_keys`
  ADD PRIMARY KEY (`quiz_id`,`request_key`),
  ADD KEY `ix_practice_expiry` (`expires_at`),
  ADD KEY `fk_practice_keys_paper` (`paper_id`,`quiz_id`);

--
-- Indexes for table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_questions_quiz_pos` (`quiz_id`,`pos`),
  ADD UNIQUE KEY `uq_questions_id_quiz` (`id`,`quiz_id`);

--
-- Indexes for table `question_options`
--
ALTER TABLE `question_options`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_question_options_question_pos` (`question_id`,`pos`),
  ADD UNIQUE KEY `uq_question_options_question_code` (`question_id`,`code`);

--
-- Indexes for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_quizzes_public_id` (`public_id`),
  ADD UNIQUE KEY `uq_quizzes_share_token` (`share_token`),
  ADD KEY `ix_quiz_user` (`user_id`,`status`,`deleted_at`,`updated_at`),
  ADD KEY `ix_quiz_listed` (`user_id`,`listed`,`status`,`deleted_at`,`published_at`),
  ADD KEY `ix_quiz_current_paper` (`current_paper_id`,`id`);

--
-- Indexes for table `quiz_papers`
--
ALTER TABLE `quiz_papers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_quiz_papers_public_id` (`public_id`),
  ADD UNIQUE KEY `uq_quiz_papers_quiz_revision` (`quiz_id`,`revision`),
  ADD UNIQUE KEY `uq_quiz_papers_id_quiz` (`id`,`quiz_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `ix_users_public` (`public_page`,`active`,`deleted_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attempts`
--
ALTER TABLE `attempts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attempt_answers`
--
ALTER TABLE `attempt_answers`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cheat_events`
--
ALTER TABLE `cheat_events`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `question_options`
--
ALTER TABLE `question_options`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quiz_papers`
--
ALTER TABLE `quiz_papers`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attempts`
--
ALTER TABLE `attempts`
  ADD CONSTRAINT `fk_attempts_paper` FOREIGN KEY (`paper_id`,`quiz_id`) REFERENCES `quiz_papers` (`id`, `quiz_id`),
  ADD CONSTRAINT `fk_attempts_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`);

--
-- Constraints for table `attempt_answers`
--
ALTER TABLE `attempt_answers`
  ADD CONSTRAINT `fk_attempt_answers_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `attempts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cheat_events`
--
ALTER TABLE `cheat_events`
  ADD CONSTRAINT `fk_cheat_events_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `attempts` (`id`);

--
-- Constraints for table `practice_keys`
--
ALTER TABLE `practice_keys`
  ADD CONSTRAINT `fk_practice_keys_paper` FOREIGN KEY (`paper_id`,`quiz_id`) REFERENCES `quiz_papers` (`id`, `quiz_id`),
  ADD CONSTRAINT `fk_practice_keys_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`);

--
-- Constraints for table `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `fk_questions_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`);

--
-- Constraints for table `question_options`
--
ALTER TABLE `question_options`
  ADD CONSTRAINT `fk_question_options_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`);

--
-- Constraints for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD CONSTRAINT `fk_quizzes_current_paper` FOREIGN KEY (`current_paper_id`,`id`) REFERENCES `quiz_papers` (`id`, `quiz_id`),
  ADD CONSTRAINT `fk_quizzes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `quiz_papers`
--
ALTER TABLE `quiz_papers`
  ADD CONSTRAINT `fk_quiz_papers_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

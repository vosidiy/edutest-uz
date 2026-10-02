-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: Oct 02, 2026 at 04:30 AM
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
(1, 1, 1, '8a845e32d11986169a4c2883c1845c78', 0x8d464e6814531187619a04c29c8864a1766f7dcd42109e0aba6bef5421ec1bb6, '4e586f0b1fe5e1b6d6eb3c03f148f97e', '8f48dfb12fdedb4e9a7eda3dd46c07d0', 'asd', NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'in_progress', '2026-10-01 17:20:03.735941', '2026-10-01 17:20:03.735941', '2026-10-01 17:20:03.735941', 0, '2026-10-02 01:20:03.735941', 'stale_timeout', NULL, NULL, NULL, 1, NULL),
(2, 1, 1, '96db43c3462edd9466399c296afc30df', 0xadb726d1b83ea5cd1ceb9d956146fa4d47d69bcf8b4044ad9ed949180b7db5f1, '03f75c9d320e062cdaa16a169deaa609', 'effa6a25f81a11b894e360ca6a2eee7b', 'asd', NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'completed', '2026-10-01 17:20:18.012579', '2026-10-01 17:23:39.315392', '2026-10-01 17:23:39.156000', 0, '2026-10-02 01:23:39.156000', 'stale_timeout', '2026-10-01 17:23:39.156000', 'completed', 1, 1, 100.00),
(3, 1, 1, 'c1dce48842a70b8c9198ab8208ae043e', 0x288cfb560f6be8c58811740641e585cbd0a786900a946de50206b5252286f3c2, 'f702b77e96493e4851fdfdf1649c431b', 'c10f701a3a41bb40f89e4b03e90ed3a4', 'Muslim Vosidiy', NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'completed', '2026-10-01 17:23:48.551627', '2026-10-01 17:35:44.753147', '2026-10-01 17:35:44.641000', 0, '2026-10-02 01:35:44.641000', 'stale_timeout', '2026-10-01 17:35:44.641000', 'completed', 0, 1, 0.00),
(4, 1, 1, '045e7ce2dcfb98ed28eaf7dcdf0f5242', 0xa39a1bd0029dff58fbfff4a35e0106ebd89171c32737bd047aa1273d1075ac4e, '9b1a1612111681bd683760971e37a62d', '6599c946fdd0b7c4fc8759e0ff5cd219', 'Muslim Vosidiy', NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'completed', '2026-10-01 17:35:52.758255', '2026-10-01 17:36:13.254199', '2026-10-01 17:36:13.160000', 0, '2026-10-02 01:36:13.160000', 'stale_timeout', '2026-10-01 17:36:13.160000', 'completed', 0, 1, 0.00),
(5, 1, 2, '0d3fa182f703a8c01b61f00bbbe60aea', 0x4812ed19390b3eb28e1bec19aa1613118c48c62bc3fce99513e9b57f27b4db6a, '2a13764a4ff72b8ccced72e0e2d29d9a', '8de49e86ac202a2076a03038fd721bb5', 'Muslim Vosidiy', 'vosidiy@gmail.com', NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'completed', '2026-10-01 17:36:38.105181', '2026-10-01 17:38:39.500255', '2026-10-01 17:38:39.333000', 0, '2026-10-02 01:38:39.333000', 'stale_timeout', '2026-10-01 17:38:39.333000', 'completed', 2, 3, 66.67),
(6, 1, 2, 'c36da952f869d0b69adaced20fa98263', 0xb788f6c7abd5c045abc0e186f3dc39e808996e57350232ee79d9bc2372f49782, 'c06b8e560c4f4ab555d3de54eb70a74b', '3d548f5af77db205a766c2163c8941ee', 'Muslim Vosidiy', NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'completed', '2026-10-01 17:38:44.836468', '2026-10-01 17:39:18.363641', '2026-10-01 17:39:18.172000', 0, '2026-10-02 01:39:18.172000', 'stale_timeout', '2026-10-01 17:39:18.172000', 'completed', 0, 3, 0.00),
(7, 1, 2, 'fdf65b57b472d5efcfbd98e805bbea34', 0xd580da8d80c842abadf47d2f12dcbc6a8f097abc5b51b02cd9be53fcf00545e3, '29d2bec7b0143e2d2b7b698357df6d3d', '3bab3827dcdc22a195792ad9ccf89bd8', 'Muslim Vosidiy', NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'completed', '2026-10-01 17:39:30.119542', '2026-10-01 17:39:56.454951', '2026-10-01 17:39:44.921000', 0, '2026-10-02 01:39:44.921000', 'stale_timeout', '2026-10-01 17:39:44.921000', 'completed', 2, 3, 66.67),
(8, 1, 2, 'c180f7b03681190f7312c0908c6f7e08', 0x9b162f47b0862fe32ff8625b09878c0b056de6bc57fb6509347336f0f34f18b5, '1fb3a25dfdc0c485871a59895114e7d3', 'e536acc0ba329e411cd3e12dfad493d2', 'Muslim Vosidiy', NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'completed', '2026-10-01 17:42:39.160806', '2026-10-01 17:43:46.682005', '2026-10-01 17:43:46.511000', 0, '2026-10-02 01:43:46.511000', 'stale_timeout', '2026-10-01 17:43:46.511000', 'completed', 1, 3, 33.33),
(9, 1, 2, '6ea9ee6f520f2f8c12b9f0c70946e7e1', 0x48cb9d6034d44867bb466f89debcd055cfc1177f6fd7b9dc4b75f2dad92b1e2a, 'b30011f402350b972fa02e6221b77602', 'c1f60cc88cdd01168471354781e69751', 'Sora', NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'completed', '2026-10-01 17:44:50.915419', '2026-10-01 17:50:00.023588', '2026-10-01 17:49:59.830000', 0, '2026-10-02 01:49:59.830000', 'stale_timeout', '2026-10-01 17:49:59.830000', 'completed', 1, 3, 33.33),
(10, 1, 2, '7e4cb1ffe226c886634d3a8a394cfd57', 0x04360a98dadf602fafece2bd1567cd9900136a8230399ed5ef445f4eda9d4513, 'ae0c32b33238444b3e5a3ddc8513f8cf', 'e7b1e94c6688a4762c3159c6c5e95cc0', 'Muslim Vosidiy', NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'completed', '2026-10-01 17:50:13.683019', '2026-10-01 17:52:27.617940', '2026-10-01 17:52:23.103000', 0, '2026-10-02 01:52:23.103000', 'stale_timeout', '2026-10-01 17:52:23.103000', 'completed', 0, 3, 0.00),
(11, 1, 2, '1a64700d4300c3d46afc3a96e41cd267', 0x116e940f4291a369b100cd35afc8d9bf14045be30afb7e982732adc6c6c21ac0, 'bda214227eaa8ddf2839f0a8ba435de5', '79448602c67f5dd4a13b5ec2d91a8b36', 'Muso 2', NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'completed', '2026-10-01 17:52:57.467649', '2026-10-01 17:54:09.345192', '2026-10-01 17:54:09.246000', 0, '2026-10-02 01:54:09.246000', 'stale_timeout', '2026-10-01 17:54:09.246000', 'completed', 2, 3, 66.67),
(12, 1, 2, '9db23b829aa82dd34aa99c602bf0e5d7', 0x8d07a755089af8e107a1efe79900aafca83aa4c156422ce9a81b67863ab96b35, '10420605a707891c5c7fcb01c647f490', '8f41294022933e19d5057582dd62517b', 'Muso 3', NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'completed', '2026-10-01 17:54:35.032062', '2026-10-01 17:56:53.459576', '2026-10-01 17:56:53.316000', 0, '2026-10-02 01:56:53.316000', 'stale_timeout', '2026-10-01 17:56:53.316000', 'completed', 1, 3, 33.33),
(13, 1, 2, 'c5b659beaade1151a448a3791cb08f17', 0x9bb130281aad89099807d8627f2ec291a926944fef9b0da7a9f7915a5a1a4c3e, '012f73c4aa57a6a2146889208fd17e39', '435bd5337145fcfcaed1727216f46adb', 'Sora 11', NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'completed', '2026-10-01 17:57:02.707180', '2026-10-01 18:02:57.856446', '2026-10-01 18:02:57.716000', 0, '2026-10-02 02:02:57.716000', 'stale_timeout', '2026-10-01 18:02:57.716000', 'completed', 1, 3, 33.33);

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
(1, 1, 1, 1, '[\"99a6c6ed0550439e\", \"36881375d0cf7f34\"]', 'not_reached', NULL, NULL, NULL, NULL, NULL),
(2, 2, 1, 1, '[\"99a6c6ed0550439e\", \"36881375d0cf7f34\"]', 'answered', '[\"99a6c6ed0550439e\"]', NULL, 1, '2026-10-01 17:23:39.234633', '2026-10-01 17:23:39.156000'),
(3, 3, 1, 1, '[\"99a6c6ed0550439e\", \"36881375d0cf7f34\"]', 'skipped', NULL, NULL, 0, '2026-10-01 17:35:44.683402', '2026-10-01 17:35:44.641000'),
(4, 4, 1, 1, '[\"99a6c6ed0550439e\", \"36881375d0cf7f34\"]', 'answered', '[\"36881375d0cf7f34\"]', NULL, 0, '2026-10-01 17:36:13.192696', '2026-10-01 17:36:13.160000'),
(5, 5, 1, 1, '[\"99a6c6ed0550439e\", \"36881375d0cf7f34\"]', 'answered', '[\"36881375d0cf7f34\"]', NULL, 0, '2026-10-01 17:37:05.061108', '2026-10-01 17:37:04.949000'),
(6, 5, 2, 2, '[\"467e5a54d2bd9ec4\", \"d1cdac69c77398e3\"]', 'answered', '[\"467e5a54d2bd9ec4\"]', NULL, 1, '2026-10-01 17:37:43.010318', '2026-10-01 17:37:42.976000'),
(7, 5, 3, 3, '[\"d1400767282c48c9\", \"43d19caa7ae82340\", \"768d620e994d6b99\", \"cbf24a3bb09d0392\"]', 'answered', '[\"d1400767282c48c9\"]', NULL, 1, '2026-10-01 17:38:39.413214', '2026-10-01 17:38:39.333000'),
(8, 6, 1, 1, '[\"99a6c6ed0550439e\", \"36881375d0cf7f34\"]', 'skipped', NULL, NULL, 0, '2026-10-01 17:38:54.420646', '2026-10-01 17:38:54.288000'),
(9, 6, 2, 2, '[\"467e5a54d2bd9ec4\", \"d1cdac69c77398e3\"]', 'answered', '[\"d1cdac69c77398e3\"]', NULL, 0, '2026-10-01 17:39:04.970149', '2026-10-01 17:39:04.880000'),
(10, 6, 3, 3, '[\"d1400767282c48c9\", \"43d19caa7ae82340\", \"768d620e994d6b99\", \"cbf24a3bb09d0392\"]', 'answered', '[\"cbf24a3bb09d0392\"]', NULL, 0, '2026-10-01 17:39:18.296421', '2026-10-01 17:39:18.172000'),
(11, 7, 1, 1, '[\"99a6c6ed0550439e\", \"36881375d0cf7f34\"]', 'answered', '[\"99a6c6ed0550439e\"]', NULL, 1, '2026-10-01 17:39:56.004792', '2026-10-01 17:39:39.951000'),
(12, 7, 2, 2, '[\"467e5a54d2bd9ec4\", \"d1cdac69c77398e3\"]', 'answered', '[\"d1cdac69c77398e3\"]', NULL, 0, '2026-10-01 17:39:56.178563', '2026-10-01 17:39:42.552000'),
(13, 7, 3, 3, '[\"d1400767282c48c9\", \"43d19caa7ae82340\", \"768d620e994d6b99\", \"cbf24a3bb09d0392\"]', 'answered', '[\"d1400767282c48c9\"]', NULL, 1, '2026-10-01 17:39:56.299263', '2026-10-01 17:39:44.921000'),
(14, 8, 1, 1, '[\"99a6c6ed0550439e\", \"36881375d0cf7f34\"]', 'answered', '[\"36881375d0cf7f34\"]', NULL, 0, '2026-10-01 17:43:03.022746', '2026-10-01 17:43:02.629000'),
(15, 8, 2, 2, '[\"467e5a54d2bd9ec4\", \"d1cdac69c77398e3\"]', 'answered', '[\"467e5a54d2bd9ec4\"]', NULL, 1, '2026-10-01 17:43:36.090063', '2026-10-01 17:43:35.987000'),
(16, 8, 3, 3, '[\"d1400767282c48c9\", \"43d19caa7ae82340\", \"768d620e994d6b99\", \"cbf24a3bb09d0392\"]', 'answered', '[\"cbf24a3bb09d0392\"]', NULL, 0, '2026-10-01 17:43:46.611355', '2026-10-01 17:43:46.511000'),
(17, 9, 1, 1, '[\"99a6c6ed0550439e\", \"36881375d0cf7f34\"]', 'answered', '[\"99a6c6ed0550439e\"]', NULL, 1, '2026-10-01 17:47:25.100007', '2026-10-01 17:47:25.069000'),
(18, 9, 2, 2, '[\"467e5a54d2bd9ec4\", \"d1cdac69c77398e3\"]', 'answered', '[\"d1cdac69c77398e3\"]', NULL, 0, '2026-10-01 17:48:37.061273', '2026-10-01 17:48:36.971000'),
(19, 9, 3, 3, '[\"d1400767282c48c9\", \"43d19caa7ae82340\", \"768d620e994d6b99\", \"cbf24a3bb09d0392\"]', 'answered', '[\"43d19caa7ae82340\"]', NULL, 0, '2026-10-01 17:49:59.919240', '2026-10-01 17:49:59.830000'),
(20, 10, 1, 1, '[\"99a6c6ed0550439e\", \"36881375d0cf7f34\"]', 'skipped', NULL, NULL, 0, '2026-10-01 17:52:12.193750', '2026-10-01 17:52:12.112000'),
(21, 10, 2, 2, '[\"467e5a54d2bd9ec4\", \"d1cdac69c77398e3\"]', 'answered', '[\"d1cdac69c77398e3\"]', NULL, 0, '2026-10-01 17:52:27.454061', '2026-10-01 17:52:21.603000'),
(22, 10, 3, 3, '[\"d1400767282c48c9\", \"43d19caa7ae82340\", \"768d620e994d6b99\", \"cbf24a3bb09d0392\"]', 'answered', '[\"768d620e994d6b99\"]', NULL, 0, '2026-10-01 17:52:27.517717', '2026-10-01 17:52:23.103000'),
(23, 11, 1, 1, '[\"99a6c6ed0550439e\", \"36881375d0cf7f34\"]', 'answered', '[\"99a6c6ed0550439e\"]', NULL, 1, '2026-10-01 17:53:00.038687', '2026-10-01 17:52:59.961000'),
(24, 11, 2, 2, '[\"467e5a54d2bd9ec4\", \"d1cdac69c77398e3\"]', 'answered', '[\"467e5a54d2bd9ec4\"]', NULL, 1, '2026-10-01 17:54:06.807016', '2026-10-01 17:54:06.778000'),
(25, 11, 3, 3, '[\"d1400767282c48c9\", \"43d19caa7ae82340\", \"768d620e994d6b99\", \"cbf24a3bb09d0392\"]', 'answered', '[\"cbf24a3bb09d0392\"]', NULL, 0, '2026-10-01 17:54:09.286356', '2026-10-01 17:54:09.246000'),
(26, 12, 1, 1, '[\"99a6c6ed0550439e\", \"36881375d0cf7f34\"]', 'answered', '[\"99a6c6ed0550439e\"]', NULL, 1, '2026-10-01 17:54:41.968755', '2026-10-01 17:54:41.887000'),
(27, 12, 2, 2, '[\"467e5a54d2bd9ec4\", \"d1cdac69c77398e3\"]', 'answered', '[\"d1cdac69c77398e3\"]', NULL, 0, '2026-10-01 17:56:12.056904', '2026-10-01 17:56:11.970000'),
(28, 12, 3, 3, '[\"d1400767282c48c9\", \"43d19caa7ae82340\", \"768d620e994d6b99\", \"cbf24a3bb09d0392\"]', 'answered', '[\"43d19caa7ae82340\"]', NULL, 0, '2026-10-01 17:56:53.397368', '2026-10-01 17:56:53.316000'),
(29, 13, 1, 1, '[\"99a6c6ed0550439e\", \"36881375d0cf7f34\"]', 'answered', '[\"99a6c6ed0550439e\"]', NULL, 1, '2026-10-01 17:57:48.218832', '2026-10-01 17:57:48.140000'),
(30, 13, 2, 2, '[\"467e5a54d2bd9ec4\", \"d1cdac69c77398e3\"]', 'skipped', NULL, NULL, 0, '2026-10-01 18:02:56.791923', '2026-10-01 18:02:56.715000'),
(31, 13, 3, 3, '[\"d1400767282c48c9\", \"43d19caa7ae82340\", \"768d620e994d6b99\", \"cbf24a3bb09d0392\"]', 'skipped', NULL, NULL, 0, '2026-10-01 18:02:57.761625', '2026-10-01 18:02:57.716000');

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
(1, 1, 1, 'single_choice', 'Salomga javob nima? ', NULL, NULL, NULL, NULL, '2026-10-01 17:10:26.833874', '2026-10-01 17:14:40.534538'),
(2, 1, 2, 'single_choice', 'Xayrlashish inglizchada ', NULL, NULL, NULL, NULL, '2026-10-01 17:12:07.412421', '2026-10-01 17:14:40.537279'),
(3, 1, 3, 'single_choice', 'Hayvon qaysi', NULL, NULL, NULL, NULL, '2026-10-01 17:13:11.078581', '2026-10-01 17:14:40.539871'),
(4, 2, 1, 'single_choice', '', NULL, NULL, NULL, NULL, '2026-10-01 17:13:56.554138', '2026-10-01 17:14:07.342902');

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
(1, 1, 1, '99a6c6ed0550439e', 'Alaykum', NULL, NULL, 1, '2026-10-01 17:10:26.836450', '2026-10-01 17:14:40.536399'),
(2, 1, 2, '36881375d0cf7f34', 'Valaykum', NULL, NULL, 0, '2026-10-01 17:10:26.836855', '2026-10-01 17:14:40.536809'),
(3, 2, 1, '467e5a54d2bd9ec4', 'Bye', NULL, NULL, 1, '2026-10-01 17:12:07.413183', '2026-10-01 17:14:40.538923'),
(4, 2, 2, 'd1cdac69c77398e3', 'Hi', NULL, NULL, 0, '2026-10-01 17:12:07.413844', '2026-10-01 17:14:40.539433'),
(5, 3, 1, 'd1400767282c48c9', 'it', NULL, NULL, 1, '2026-10-01 17:13:11.079509', '2026-10-01 17:14:40.541763'),
(6, 3, 2, '43d19caa7ae82340', 'baliq', NULL, NULL, 0, '2026-10-01 17:13:11.079827', '2026-10-01 17:14:40.542274'),
(7, 3, 3, '768d620e994d6b99', 'chumoli', NULL, NULL, 0, '2026-10-01 17:13:11.080158', '2026-10-01 17:14:40.542650'),
(8, 3, 4, 'cbf24a3bb09d0392', 'qong\'iz', NULL, NULL, 0, '2026-10-01 17:13:11.080593', '2026-10-01 17:14:40.543004'),
(9, 4, 1, '1c0c92a76968551b', '', NULL, NULL, 1, '2026-10-01 17:13:56.554981', '2026-10-01 17:14:07.344995'),
(10, 4, 2, '82169f9328c625f7', '', NULL, NULL, 0, '2026-10-01 17:13:56.555304', '2026-10-01 17:14:07.345336');

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
  `instructions` text NOT NULL,
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

INSERT INTO `quizzes` (`id`, `user_id`, `public_id`, `share_token`, `mode`, `status`, `listed`, `title`, `description`, `instructions`, `cover_src`, `revision`, `version`, `current_paper_id`, `time_limit_sec`, `opens_at`, `closes_at`, `passcode_hash`, `email_mode`, `phone_mode`, `shuffle_questions`, `shuffle_options`, `feedback`, `show_score`, `show_answers`, `show_explain`, `cheat_check`, `practice_starts`, `published_at`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'e701eed148aa0776ff00a262ef47b07e', '086773311', 'assessment', 'published', 0, 'Algebra', '', '', NULL, 10, 12, 2, NULL, NULL, NULL, NULL, 'optional', 'hidden', 0, 0, 'at_end', 1, 0, 0, 0, 0, '2026-10-01 17:11:06.580052', '2026-10-01 17:10:11.727387', '2026-10-01 17:36:23.460184', NULL),
(2, 1, 'f5a5d4423c93fd02913e121461f64e01', '479069256', 'assessment', 'draft', 0, 'Test 1', '', '', NULL, 4, 4, NULL, NULL, NULL, NULL, NULL, 'optional', 'hidden', 0, 0, 'at_end', 1, 0, 0, 0, 0, NULL, '2026-10-01 17:13:32.765059', '2026-10-01 17:14:07.341144', NULL),
(3, 1, '524545221204c098f60ac5a83cbf0eb1', '427643576', 'assessment', 'draft', 0, 'asdas', '', '', NULL, 1, 1, NULL, NULL, NULL, NULL, NULL, 'optional', 'hidden', 0, 0, 'at_end', 1, 0, 0, 0, 0, NULL, '2026-10-01 17:15:05.827329', '2026-10-01 17:15:05.827329', NULL);

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
(1, 1, '3f8c7188da19911bae2d7eb6ab5ed417', 5, NULL, '{\"quiz\": {\"mode\": \"assessment\", \"cover\": null, \"title\": \"Algebra\", \"opensAt\": null, \"closesAt\": null, \"feedback\": \"at_end\", \"emailMode\": \"optional\", \"phoneMode\": \"hidden\", \"showScore\": true, \"cheatCheck\": false, \"shareToken\": \"086773311\", \"description\": \"\", \"showAnswers\": false, \"showExplain\": false, \"instructions\": \"\", \"timeLimitSec\": null, \"timingPolicy\": \"client_deadline_late_sync_v1\", \"scoringPolicy\": \"all_or_nothing_v1\", \"shuffleOptions\": false, \"passcodeRequired\": false, \"shuffleQuestions\": false}, \"questions\": [{\"id\": \"1\", \"type\": \"single_choice\", \"media\": null, \"content\": \"Salomga javob\", \"options\": [{\"id\": \"1\", \"code\": \"99a6c6ed0550439e\", \"media\": null, \"content\": \"Alaykum\", \"position\": 1, \"isCorrect\": true}, {\"id\": \"2\", \"code\": \"36881375d0cf7f34\", \"media\": null, \"content\": \"Valaykum\", \"position\": 2, \"isCorrect\": false}], \"position\": 1, \"explanation\": \"\", \"correctCodes\": [\"99a6c6ed0550439e\"], \"acceptedAnswers\": []}], \"schemaVersion\": 3}', '2026-10-01 17:11:06.577339'),
(2, 1, 'cde04bbf9dc6a4c62a3b1cd362e71eb8', 10, NULL, '{\"quiz\": {\"mode\": \"assessment\", \"cover\": null, \"title\": \"Algebra\", \"opensAt\": null, \"closesAt\": null, \"feedback\": \"at_end\", \"emailMode\": \"optional\", \"phoneMode\": \"hidden\", \"showScore\": true, \"cheatCheck\": false, \"shareToken\": \"086773311\", \"description\": \"\", \"showAnswers\": false, \"showExplain\": false, \"instructions\": \"\", \"timeLimitSec\": null, \"timingPolicy\": \"client_deadline_late_sync_v1\", \"scoringPolicy\": \"all_or_nothing_v1\", \"shuffleOptions\": false, \"passcodeRequired\": false, \"shuffleQuestions\": false}, \"questions\": [{\"id\": \"1\", \"type\": \"single_choice\", \"media\": null, \"content\": \"Salomga javob nima? \", \"options\": [{\"id\": \"1\", \"code\": \"99a6c6ed0550439e\", \"media\": null, \"content\": \"Alaykum\", \"position\": 1, \"isCorrect\": true}, {\"id\": \"2\", \"code\": \"36881375d0cf7f34\", \"media\": null, \"content\": \"Valaykum\", \"position\": 2, \"isCorrect\": false}], \"position\": 1, \"explanation\": \"\", \"correctCodes\": [\"99a6c6ed0550439e\"], \"acceptedAnswers\": []}, {\"id\": \"2\", \"type\": \"single_choice\", \"media\": null, \"content\": \"Xayrlashish inglizchada \", \"options\": [{\"id\": \"3\", \"code\": \"467e5a54d2bd9ec4\", \"media\": null, \"content\": \"Bye\", \"position\": 1, \"isCorrect\": true}, {\"id\": \"4\", \"code\": \"d1cdac69c77398e3\", \"media\": null, \"content\": \"Hi\", \"position\": 2, \"isCorrect\": false}], \"position\": 2, \"explanation\": \"\", \"correctCodes\": [\"467e5a54d2bd9ec4\"], \"acceptedAnswers\": []}, {\"id\": \"3\", \"type\": \"single_choice\", \"media\": null, \"content\": \"Hayvon qaysi\", \"options\": [{\"id\": \"5\", \"code\": \"d1400767282c48c9\", \"media\": null, \"content\": \"it\", \"position\": 1, \"isCorrect\": true}, {\"id\": \"6\", \"code\": \"43d19caa7ae82340\", \"media\": null, \"content\": \"baliq\", \"position\": 2, \"isCorrect\": false}, {\"id\": \"7\", \"code\": \"768d620e994d6b99\", \"media\": null, \"content\": \"chumoli\", \"position\": 3, \"isCorrect\": false}, {\"id\": \"8\", \"code\": \"cbf24a3bb09d0392\", \"media\": null, \"content\": \"qong\'iz\", \"position\": 4, \"isCorrect\": false}], \"position\": 3, \"explanation\": \"\", \"correctCodes\": [\"d1400767282c48c9\"], \"acceptedAnswers\": []}], \"schemaVersion\": 3}', '2026-10-01 17:36:23.466257');

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
(1, 'vosidiy@gmail.com', '$2y$10$tXUJP68LOPj2CejhOwyYzOQq6GQmHbXbeTwooLk029vacfpXmG5cW', 'Muslim', '+998946875461', '', 'Asia/Tashkent', 0, 1, NULL, NULL, '2026-10-01 17:09:52.000000', '2026-10-01 17:09:52.000000', '2026-10-01 17:09:52.000000', NULL);

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

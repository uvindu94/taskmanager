-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 30, 2025 at 03:07 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `task_tracker`
--

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `project` varchar(255) NOT NULL,
  `sales_officer` varchar(255) DEFAULT NULL,
  `project_contacts` longtext DEFAULT NULL,
  `remarks` longtext DEFAULT NULL,
  `completion` double DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `status` enum('not_yet_start','ongoing','waiting_for_customer_info') DEFAULT 'not_yet_start'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `project`, `sales_officer`, `project_contacts`, `remarks`, `completion`, `due_date`, `created_at`, `updated_at`, `created_by`, `status`) VALUES
(4, 'Demacs', '', '', '', 100, '2025-03-25 11:53:00', '2025-09-25 11:53:35', '2025-09-25 11:53:35', 4, 'not_yet_start'),
(5, 'HK Holdings', 'Shane', '', '', 100, '2025-05-15 11:53:00', '2025-09-25 11:54:03', '2025-09-25 02:27:11', 4, 'not_yet_start'),
(6, 'Sun Construction', 'Shane', '', '', 100, '2025-03-25 11:54:00', '2025-09-25 11:54:26', '2025-09-25 02:37:58', 4, 'not_yet_start'),
(7, 'Dissanayaka Engineering', 'harsha', '', '', 95, '2025-03-14 11:54:00', '2025-09-25 11:55:01', '2025-09-26 06:26:22', 4, 'not_yet_start'),
(8, 'Supreme Court', 'PSM Yuresha', '0712205100', 'web done.... case management on going', 70, '2025-10-10 12:29:00', '2025-09-25 02:00:00', '2025-09-25 02:00:00', 19, 'ongoing'),
(9, 'Adz.lk', 'Rupasinghe', '0712205100', 'project completed', 100, '2024-12-31 12:30:00', '2025-09-25 02:00:40', '2025-09-25 02:00:40', 19, 'ongoing'),
(10, 'ETRERNA', 'Dean', '', '', 100, '2025-03-01 12:54:00', '2025-09-25 02:24:30', '2025-09-25 02:24:30', 4, 'not_yet_start'),
(11, 'Gift vouchers', 'direct', '0712205100', 'final stage', 100, '2025-10-02 12:54:00', '2025-09-25 02:24:32', '2025-09-25 10:14:59', 19, 'ongoing'),
(12, 'EDB', 'Direct', '', '', 100, '2025-07-01 12:55:00', '2025-09-25 02:25:47', '2025-09-25 02:25:47', 4, 'not_yet_start'),
(13, 'Kurunegala Pradeshiya saba', 'PSM ', '0711579374', 'Kurunegala Upeksha', 95, '2025-04-30 12:55:00', '2025-09-25 02:26:26', '2025-09-25 02:26:26', 8, 'waiting_for_customer_info'),
(14, 'pro-worx', 'Direct', '', '', 100, NULL, '2025-09-25 02:26:32', '2025-09-25 02:26:32', 15, 'not_yet_start'),
(15, 'Analyst Department', 'Direct', '0767454075 - Mr.Chathuranga ', 'Completed', 100, NULL, '2025-09-25 02:26:50', '2025-09-25 02:26:50', 10, 'not_yet_start'),
(16, 'Exel Holdings', 'Harsha', '0714159132 - Diluka\r\n0718738133 - Harsha', '', 50, '2025-10-02 12:56:00', '2025-09-25 02:26:57', '2025-09-25 03:21:48', 9, 'ongoing'),
(17, 'Tuktuk', 'PSM Mithun', '0719702473', 'https://srilankatuktuk.com/', 100, NULL, '2025-09-25 02:27:56', '2025-09-25 03:06:22', 12, 'waiting_for_customer_info'),
(18, 'SLAITO', 'Direct', '', '', 80, '2025-12-01 14:04:00', '2025-09-25 02:35:20', '2025-09-26 06:25:50', 4, 'ongoing'),
(19, 'Lakdeera ', 'Lesly Deniyawatte', '', '', 95, NULL, '2025-09-25 02:37:17', '2025-09-26 06:26:38', 4, 'not_yet_start'),
(20, 'New Rathmini', '', '', '', 100, NULL, '2025-09-25 02:37:36', '2025-09-25 02:37:36', 15, 'not_yet_start'),
(21, 'Golden Wings', 'Harindra Rupasinghe', '0718738138', 'https://testing.sltdigitalweb.lk/golden_wings', 95, NULL, '2025-09-25 02:38:01', '2025-09-25 02:52:05', 12, 'waiting_for_customer_info'),
(22, 'Alucomass', 'Harsha Jayawikrama', '0718738133', 'https://sltdirectory.lk/alucomass/', 95, NULL, '2025-09-25 02:38:37', '2025-09-25 02:56:11', 12, 'waiting_for_customer_info'),
(23, 'Rathmini Hardware', 'Sanjaya Wijerathne', '0718738145', 'https://testing.sltdigitalweb.lk/RathminiHardware/', 95, NULL, '2025-09-25 02:39:41', '2025-09-25 02:55:28', 12, 'waiting_for_customer_info'),
(24, 'Canin Lanka', 'Lasly Deniyawatte', '0718738139', '', 0, NULL, '2025-09-25 02:39:59', '2025-09-25 02:53:25', 12, 'not_yet_start'),
(25, 'AB Naturals', 'Shane Deshabandu Perera', '0718738137', '', 95, NULL, '2025-09-25 02:41:24', '2025-09-25 02:54:31', 12, 'waiting_for_customer_info'),
(26, 'AIC Campus', 'Isuru Vitharana', '076-8268166 - Mr. Arun', 'Completed. But they request new additional features', 100, NULL, '2025-09-25 02:42:54', '2025-09-25 02:42:54', 10, 'not_yet_start'),
(27, 'Helix Engineering', 'Rajith', '0773691730', '', 95, NULL, '2025-09-25 02:45:06', '2025-09-25 02:45:06', 15, 'waiting_for_customer_info'),
(28, 'Samarasingha Auto Part House', 'Sri Kantha', '0761780808 - Prasadi\r\n0775646486 - Sasika', 'https://sltdigital.site/saph/\r\n\r\nPending from digital team -> Add google map loacation', 95, '2025-10-04 13:13:00', '2025-09-25 02:45:19', '2025-09-25 02:45:19', 9, 'ongoing'),
(29, 'Fiducia', 'Harsha Jayawickrama', '0718738133\r\n', 'https://testing.sltdigitalweb.lk/Fiducia_Shipping/', 100, NULL, '2025-09-25 02:45:43', '2025-09-25 03:16:06', 13, 'not_yet_start'),
(30, 'Universal Granite ', 'Direct', '', '', 100, NULL, '2025-09-25 02:46:31', '2025-09-25 03:16:36', 13, 'not_yet_start'),
(31, 'Film Corporation', 'Rajith ', '071-4437796 - Ms.Sameeka', 'https://sltdigital.site/film_corporation/', 80, NULL, '2025-09-25 02:46:38', '2025-09-25 22:58:43', 10, 'ongoing'),
(32, 'Astrology', 'Deshabandu Shane Perera', '0718738137\r\n', 'This has not been added to the live server because the customer has not completed the payment yet.', 95, NULL, '2025-09-25 02:47:40', '2025-09-25 03:19:12', 13, 'waiting_for_customer_info'),
(33, 'Ultimate Water', 'PSM Buddhika', 'Buddhika - +94 70 250 8666\r\nRanga - +94 77 362 1476', 'https://testing.sltdigitalweb.lk/ultimate-hydro/', 95, '2025-10-02 13:17:00', '2025-09-25 02:47:48', '2025-09-25 02:47:48', 9, 'waiting_for_customer_info'),
(34, 'Lakmina Lanka', 'Rupal Priyanga', '0718513962\r\n', 'https://testing.sltdigitalweb.lk/lakmina_lanka/', 95, NULL, '2025-09-25 02:48:26', '2025-09-25 03:20:10', 13, 'waiting_for_customer_info'),
(35, 'Sai Luxmi Constructions', 'Nomesh', '071-2719712  Mr.Nomesh', 'https://testing.sltdigitalweb.lk/sai-luxmi/', 70, NULL, '2025-09-25 02:49:08', '2025-09-25 03:21:25', 10, 'waiting_for_customer_info'),
(36, 'Dana ManPower Agency', 'Prasanna ', 'Prasanna - +94 71 873 8130\r\nJoy - +94 76 254 9626\r\nKishan - +94 70 366 3000', 'https://sltdigitalweb.lk/danamanpower/', 95, NULL, '2025-09-25 02:49:57', '2025-09-25 02:49:57', 9, 'waiting_for_customer_info'),
(37, 'jaydeck Admin Panel', '', '', '', 100, NULL, '2025-09-25 02:50:05', '2025-09-25 02:50:05', 13, 'not_yet_start'),
(38, 'CSIAP Admin Panel', '', '', '', 100, NULL, '2025-09-25 02:51:04', '2025-09-25 02:51:04', 13, 'waiting_for_customer_info'),
(39, 'Navodya Distributors', 'Mr. Shen ', '0773087970 - MR. Chandima', 'https://testing.sltdigitalweb.lk/navodya/', 85, NULL, '2025-09-25 02:51:05', '2025-09-25 23:01:17', 10, 'ongoing'),
(40, 'Pest Control Colombo', 'Mr. Prasanna', '', 'https://testing.sltdigitalweb.lk/pest-control/', 45, NULL, '2025-09-25 02:53:05', '2025-09-25 23:03:53', 10, 'waiting_for_customer_info'),
(41, 'Anti Malaria Campine', 'Psm', '', 'http://sltdigitalweb.lk/malaria_control/', 70, NULL, '2025-09-25 02:53:23', '2025-09-25 02:53:23', 13, 'ongoing'),
(42, 'Gem and Jewellery ', 'Rajitha', 'Rajitha - 710830706\r\nSutharshan - +94 75 434 7886', 'https://testing.sltdigitalweb.lk/gem/', 90, NULL, '2025-09-25 02:53:54', '2025-09-25 02:53:54', 9, 'waiting_for_customer_info'),
(43, 'Special Olympics', 'Direct', '077-8177726 Mr.Sanjaya', 'https://testing.sltdigitalweb.lk/special-olympics/', 95, NULL, '2025-09-25 02:54:31', '2025-09-25 02:54:31', 10, 'waiting_for_customer_info'),
(44, 'L H Warnapala Group', 'Mr.Shen', '', 'https://testing.sltdigitalweb.lk/lhwarnapalav1/', 40, NULL, '2025-09-25 02:55:39', '2025-09-25 03:01:16', 10, 'waiting_for_customer_info'),
(45, 'Blackpool Coffee', 'Prasanna ', 'Prasanna - 718738130\r\nHasitha - +94 77 277 2763', 'https://blackpoolcoffee.com/', 100, NULL, '2025-09-25 02:56:55', '2025-09-25 02:56:55', 9, 'not_yet_start'),
(46, 'Balapitiya PS', 'PSM', 'Sadali - +94 76 945 6350', 'https://sltdigitalweb.lk/balapitiya/?nocach=1\r\n\r\nhttp://balapitiya.ps.gov.lk/', 80, NULL, '2025-09-25 02:58:11', '2025-09-25 02:58:11', 9, 'waiting_for_customer_info'),
(47, 'HVA Lanka Export Pvt Ltd', '', '077-3574753 Sherami', 'https://sltdigital.site/hva/', 80, NULL, '2025-09-25 02:58:11', '2025-09-25 23:03:19', 10, 'ongoing'),
(48, 'Srilak Fish market', 'PSM Mithun', '', '', 50, NULL, '2025-09-25 02:59:24', '2025-09-25 22:58:14', 10, 'ongoing'),
(49, 'Teeda', 'Mr.Shalinda', '077-2451182 Ms. Manjula', 'https://sltdigitalweb.lk/teeda/login.php', 95, NULL, '2025-09-25 03:06:39', '2025-09-25 23:02:00', 10, 'waiting_for_customer_info'),
(50, 'Pannala PS', 'PSM Shalinda', '+94 71 376 7744 - Shalinda\r\n0726555116 - Anuruddhika', 'https://pannalaps.lk/', 100, NULL, '2025-09-25 04:20:41', '2025-09-25 04:20:41', 9, 'not_yet_start'),
(51, 'Atlas Trade Center', 'Imran Imamdeen', '718738125 - Imran Imamdeen\r\n', 'https://atlastrd.lk/?nocache=1', 100, NULL, '2025-09-25 04:23:05', '2025-09-25 04:24:30', 9, 'not_yet_start'),
(52, 'Lahiru Food Products ', '', '', '', 0, NULL, '2025-09-29 23:13:36', '2025-09-29 23:13:36', 15, 'not_yet_start');

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `assigned_to` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `status` enum('pending','in_progress','completed') DEFAULT 'pending',
  `priority` varchar(255) NOT NULL,
  `due_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `title`, `description`, `assigned_to`, `created_by`, `status`, `priority`, `due_date`, `created_at`, `updated_at`) VALUES
(6, 'UI for marine summit', 'need a  professional ui for the project', 16, 19, 'completed', 'low', '2025-09-25', '2025-09-25 07:30:31', '2025-09-27 18:06:34'),
(7, 'add', 'aareyewyweyweryweye', 19, 8, 'completed', 'low', '2025-09-27', '2025-09-25 07:39:04', '2025-09-27 18:06:34'),
(8, 'Mathura Engineers UI V2', 'Mathura Engineers requested some UI changes', 11, 11, 'completed', 'low', '2025-09-25', '2025-09-25 07:49:35', '2025-09-27 18:06:34'),
(9, 'ceynor update', '01.Add the new project details to the project tab.\r\n02.Remove current vacancies and add new vacancy - Refrigeration Engineering consultant. \r\n', 4, 19, 'completed', 'low', '2025-09-25', '2025-09-25 07:52:43', '2025-09-27 18:06:34'),
(10, 'design UI for srilak website', 'need unique design according to ui proposed by us to sri lak', 10, 8, 'completed', 'low', '2025-09-26', '2025-09-25 07:57:36', '2025-09-29 05:02:12'),
(11, 'Gem and jwellery translations', 'please finalize gem and jwellery sinhala and tamil translations', 9, 8, 'completed', 'low', '2025-10-02', '2025-09-25 07:58:41', '2025-09-27 18:06:34'),
(12, 'Exel Holdings', 'The products page needs to be changed', 9, 9, 'completed', 'low', '2025-10-03', '2025-09-25 09:26:07', '2025-09-27 18:06:34'),
(13, 'Tuk tuk book a meeting issue', 'is there any  issue?? customer mentioned that in the group', 12, 19, 'completed', 'low', '2025-09-25', '2025-09-25 09:54:26', '2025-09-27 18:06:34'),
(14, 'duplicate case mgt from 1165 server for court of appeal', 'duplicate case mgt from 1165 server for court of appeal', 4, 19, 'completed', 'low', '2025-09-25', '2025-09-25 10:13:42', '2025-09-27 18:06:34'),
(15, 'Gem & Jewellery website', 'Check, IPG', 9, 9, 'completed', 'low', '2025-10-03', '2025-09-25 10:20:17', '2025-09-27 18:06:34'),
(16, 'fix issue of certificate request issue supremecourt', 'custom case numbers should not to be enter', 19, 19, 'completed', 'low', '2025-09-25', '2025-09-25 10:21:09', '2025-09-27 18:06:34'),
(17, 'test gem and jwellery server', 'horizon vpn should test and should inform  the customer about sever', 19, 19, 'in_progress', 'low', '2025-09-25', '2025-09-25 10:22:31', '2025-09-27 18:06:34'),
(18, 'gem and jwellery ipg interfaces and test ipg', 'ipg should test and interfaces should be arrange', 9, 19, 'in_progress', 'low', '2025-10-03', '2025-09-25 10:23:43', '2025-09-27 18:06:34'),
(19, 'Images for HVA website', 'images for hero sections and logo with text ', 16, 10, 'completed', 'low', '2025-09-26', '2025-09-25 10:28:19', '2025-09-27 18:06:34'),
(20, 'call maskeliya and need a proposal', 'call to customer : +94 77 224 3434\n\nask samanthika to make it\n\nThey want do web revamp for St. Clair’s tea', 14, 19, 'in_progress', 'low', '2025-10-03', '2025-09-25 10:28:55', '2025-09-29 22:41:40'),
(21, 'Replace Image', 'Replace the car image with a tuk-tuk', 14, 12, 'completed', 'low', '2025-09-26', '2025-09-25 10:37:40', '2025-09-27 18:06:34'),
(22, 'Tender Change', 'BOQ Management changes ', 16, 5, 'completed', 'low', '2025-09-25', '2025-09-25 10:38:39', '2025-09-27 18:06:34'),
(23, 'Images for navodya distributors website', 'product images size 700*800, with white background.\r\n', 11, 10, 'completed', 'low', '2025-09-26', '2025-09-25 10:41:08', '2025-09-27 18:06:34'),
(24, 'Check fleethub emails', ' reverse DNS record and I have assisted with that.', 9, 9, 'completed', 'low', '2025-09-26', '2025-09-25 12:00:28', '2025-09-27 18:06:34'),
(25, 'send 6 months maintenance reports for labour min', 'send 6 months maintenance reports for labour min april to today', 19, 19, 'completed', 'low', '2025-09-25', '2025-09-25 13:13:54', '2025-09-27 18:06:34'),
(26, 'Education Minsirty web site rewamp', 'Call 0714407620 Mr.pradeep ministry of education and get requirment and prepare the proposal', 8, 20, 'in_progress', 'low', '2025-09-26', '2025-09-25 17:40:45', '2025-09-27 18:06:34'),
(27, 'Need diagram replicate', 'need server architectcure diagram photo in digital foramat', 14, 19, 'completed', 'low', '2025-09-26', '2025-09-26 04:43:44', '2025-09-27 18:06:34'),
(28, 'Heavenly proposal send', 'enter the price i have sent you via whatsapp and send', 6, 19, 'completed', 'low', '2025-09-26', '2025-09-26 04:50:02', '2025-09-30 04:18:09'),
(29, 'Call for interviews', 'Business Analyst interviews', 9, 9, 'completed', 'low', '2025-09-26', '2025-09-26 04:57:24', '2025-09-27 18:06:34'),
(30, 'udpate case types and next action types in the db', 'replace action types and case types for appeal court and add the link as remark', 4, 19, 'completed', 'low', '2025-09-26', '2025-09-26 05:10:10', '2025-09-27 18:06:34'),
(31, 'udpate supreme court judges portfolios', 'add new judges profiles receive via email', 19, 19, 'completed', 'low', '2025-09-26', '2025-09-26 05:52:27', '2025-09-27 18:06:34'),
(32, 'need a logo for  taskmanager', 'professional eye catching logo needed', 14, 19, 'completed', 'low', '2025-09-26', '2025-09-26 05:55:12', '2025-09-27 18:06:34'),
(33, 'EDB Updates', 'Updated e-brochure with new sponsors will be share with you soon once received from the PR team. Please replace it in web portal once remove.\r\n\r\nAdd this updated agenda items to the Agenda page in the web. This is the updated one so far. And shall we remove the early bird discount section from the web since it\'s already expired now. Great if you can replace something else with those captions since its background has been added a good look to the web.', 4, 4, 'completed', 'low', '2025-09-26', '2025-09-26 05:55:33', '2025-09-27 18:06:34'),
(34, 'Send Google Analytics to your live websites', 'Send google analytics before 30th of september', 13, 9, 'completed', 'low', '2025-09-30', '2025-09-26 05:56:08', '2025-09-27 18:06:34'),
(35, 'Send Google Analytics to your live websites', 'Send google analytics before 30th of september', 15, 9, 'completed', 'low', '2025-09-30', '2025-09-26 05:56:40', '2025-09-30 03:37:35'),
(36, 'Send Google Analytics to your live websites', 'Send google analytics before 30th of september', 12, 9, 'completed', 'low', '2025-09-30', '2025-09-26 05:56:57', '2025-09-27 18:06:34'),
(37, 'Supreme Court Enrollment System Changes', 'Reserving dates changes', 4, 4, 'completed', 'low', '2025-09-26', '2025-09-26 05:57:06', '2025-09-27 18:06:34'),
(38, 'Send Google Analytics to your live websites', 'Send google analytics before 30th of september', 4, 9, 'in_progress', 'low', '2025-09-30', '2025-09-26 05:57:25', '2025-09-27 18:06:34'),
(39, 'Send Google Analytics to your live websites', 'Send google analytics before 30th of september', 10, 9, 'completed', 'low', '2025-09-30', '2025-09-26 05:57:55', '2025-09-27 18:06:34'),
(40, 'Send google analytics', 'send google analytics to you live sites', 9, 9, 'completed', 'low', '2025-09-26', '2025-09-26 06:03:19', '2025-09-27 18:06:34'),
(41, 'Send Google Analytics to your live websites', 'Send google analytics before 30th of september', 9, 9, 'completed', 'low', '2025-09-30', '2025-09-26 06:04:27', '2025-09-29 06:07:33'),
(42, 'call tudawe trading and ask for payment status', 'call tudawe manesh and ask for payment status... ', 19, 19, 'pending', 'low', '2025-09-26', '2025-09-26 06:19:54', '2025-09-27 18:06:34'),
(43, 'Gem and Jewellery website', 'I recieved only these details, \r\nMerchant ID TEST700182200500\r\nPassword   - Boc@Gjrti\r\nCurrency - LKR\r\n', 9, 9, 'in_progress', 'low', '2025-10-03', '2025-09-26 10:25:13', '2025-09-29 05:12:56'),
(44, 'Mathura Engineer web', 'Mathura Engineer web site proposal ', 8, 8, 'pending', 'low', '2025-09-29', '2025-09-26 11:13:50', '2025-09-29 09:57:17'),
(45, 'geotrans ', 'geotrans  meeting monday 3 pm', 19, 8, 'in_progress', 'low', '2025-09-29', '2025-09-26 11:18:46', '2025-09-28 07:06:34'),
(46, 'NAPVCW Emails', 'NAPVCW Emails create new emails', 8, 8, 'completed', 'low', '2025-09-26', '2025-09-26 11:20:13', '2025-09-30 04:38:21'),
(47, 'lakmina lanka', 'lakmina lanka images and content', 13, 8, 'completed', 'low', '2025-09-29', '2025-09-26 11:21:23', '2025-09-29 06:51:57'),
(48, 'Lassana events ', 'Lassana events send a mail ', 8, 8, 'completed', 'low', '2025-09-28', '2025-09-27 02:13:21', '2025-09-28 07:17:48'),
(49, 'create diagrams for  supremecourt website', 'create diagrams for  supremecourt website requested by mr.asela', 14, 19, 'completed', 'low', '2025-09-27', '2025-09-27 11:13:21', '2025-09-27 18:06:34'),
(50, 'create diagrams for supremecourt website', 'create diagrams using power point ', 16, 14, 'completed', 'low', '2025-09-28', '2025-09-27 17:30:41', '2025-09-27 18:38:56'),
(51, 'Analyst dept site email issues', '_Please check configs of analyst dept submisions forms... i got email from users they have submitted to  their website', 10, 19, 'completed', 'high', '2025-09-29', '2025-09-28 07:05:08', '2025-09-29 07:19:29'),
(52, 'Lake house meeting ', 'lahiru and  my self should visit lakehouse meeting at 11am tuesday', 8, 19, 'pending', 'high', '2025-09-30', '2025-09-28 07:07:44', '2025-09-28 07:07:44'),
(53, 'Lake house meeting ', 'lahiru and  my self should visit lakehouse meeting at 11am tuesday', 19, 19, 'pending', 'high', '2025-09-30', '2025-09-28 07:07:44', '2025-09-28 07:07:44'),
(54, 'Lalaka websites', 'check all lalaka websites and give me progress of them.', 8, 20, 'pending', 'high', '2025-09-29', '2025-09-28 19:24:06', '2025-09-28 19:24:06'),
(55, 'ultimate web final notice', 'call ultimate web pepole and budhika from kandy PSM and request to get the detail and finished within this week', 9, 20, 'completed', 'high', '2025-09-30', '2025-09-28 19:42:44', '2025-09-29 05:10:32'),
(56, 'ultimate web final notice', 'call ultimate web pepole and budhika from kandy PSM and request to get the detail and finished within this week', 8, 20, 'in_progress', 'high', '2025-09-30', '2025-09-28 19:42:44', '2025-09-29 11:10:23'),
(57, 'jaffna library tender possibility check', 'jaffna library tender possibility check', 19, 19, 'pending', 'high', '2025-10-01', '2025-09-29 04:05:53', '2025-09-29 04:05:53'),
(58, 'HVA website updates', 'some content and design updates', 10, 10, 'completed', 'medium', '2025-09-29', '2025-09-29 05:22:15', '2025-09-29 09:50:59'),
(59, 'need icons in tamil', 'https://adz.lk/wp-content/uploads/2023/12/Group-78.png\r\nhttps://adz.lk/wp-content/uploads/2023/12/Group-77.png\r\nhttps://adz.lk/wp-content/uploads/2023/12/Group-76.png\r\nhttps://adz.lk/wp-content/uploads/2023/12/Group-75.png\r\nhttps://adz.lk/wp-content/uploads/2023/12/Group-74.png\r\nhttps://adz.lk/wp-content/uploads/2023/12/Group-73.png\r\nhttps://adz.lk/wp-content/uploads/2023/12/Group-72.png\r\nhttps://adz.lk/wp-content/uploads/2023/12/Group-71.png\r\nhttps://adz.lk/wp-content/uploads/2023/12/Group-70.png\r\nhttps://adz.lk/wp-content/uploads/2023/12/Group-69.png\r\nhttps://adz.lk/wp-content/uploads/2023/12/Group-68.png\r\nhttps://adz.lk/wp-content/uploads/2023/12/Group-67.png\r\nhttps://adz.lk/wp-content/uploads/2023/12/Group-66.png\r\n\r\nabove icons in tamil... just replace name with tamil content', 14, 19, 'completed', 'high', '2025-09-29', '2025-09-29 08:33:42', '2025-09-29 09:50:54'),
(60, 'ETF Board Tender', 'Renewal Of Licenses/Subscriptions ETFB Website - ETF/PROC/A/2025/103', 5, 5, 'pending', 'medium', '2025-10-03', '2025-09-29 08:52:32', '2025-09-29 08:52:32'),
(61, 'EDB Updates', 'Registered Users updates', 4, 4, 'completed', 'medium', '2025-09-29', '2025-09-29 09:05:28', '2025-09-29 10:55:37'),
(62, 'SMS gateway integrate', 'Supreme court student enrollment form SMS gateway integrate ', 4, 4, 'completed', 'high', '2025-09-29', '2025-09-29 09:06:55', '2025-09-29 09:07:15'),
(63, 'navodya distributors website updates', 'add product images recently shared', 10, 10, 'completed', 'medium', '2025-09-29', '2025-09-29 09:53:21', '2025-09-29 11:00:29'),
(64, 'Send an email for Ultimate water', 'Ultimate water confirmation', 9, 9, 'completed', 'high', '2025-09-30', '2025-09-29 09:56:32', '2025-09-29 12:37:17'),
(65, 'Need Geotrans UI', 'Need Geotrans UI Design', 11, 8, 'pending', 'medium', '2025-10-03', '2025-09-29 11:15:36', '2025-09-30 04:06:29'),
(66, 'Ministry of Health & Mass Media Tender', 'Ministry of Health official website maintenance - HP/HI/A/07/2020-Temp2', 5, 5, 'pending', 'medium', '2025-10-17', '2025-09-30 03:58:52', '2025-09-30 03:58:52'),
(67, 'Develop a 5-page website and an admin panel for \"Lahiru Food Products.\"', 'Lahiru Food Products Website & Admin Panel\r\nScope\r\n\r\nPublic-facing 5-page website\r\n\r\nAdmin panel for managing content (products, pages, etc.)\r\n\r\nDatabase integration (MySQL preferred)', 15, 4, 'completed', 'medium', '2025-10-12', '2025-09-30 04:06:47', '2025-09-30 08:09:56'),
(68, 'test', 'gdfgdfgdfg', 19, 16, 'completed', 'medium', '2025-09-30', '2025-09-30 07:53:34', '2025-09-30 08:10:14'),
(69, 'fdsfsdf', 'sdfsdfsdfsdfsd', 19, 16, 'completed', 'low', '2025-09-30', '2025-09-30 08:07:08', '2025-09-30 08:09:38'),
(70, 'dfsfsdgdfgfdg', 'fdgdfgdfhghgh', 19, 16, 'in_progress', 'medium', '2025-10-10', '2025-09-30 08:07:30', '2025-09-30 09:35:05'),
(71, 'dsfsdf', 'sdfdsfdsfsd', 19, 16, 'pending', 'low', '2025-09-30', '2025-09-30 08:12:02', '2025-09-30 08:12:16'),
(72, 'fsdfsdfdsf dsf fsdf', 'fsdfdsfgfhghfg', 14, 16, 'pending', 'low', '2025-09-30', '2025-09-30 09:05:15', '2025-09-30 09:05:15');

-- --------------------------------------------------------

--
-- Table structure for table `task_history`
--

CREATE TABLE `task_history` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `performed_by` int(11) NOT NULL,
  `performed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_history`
--

INSERT INTO `task_history` (`id`, `task_id`, `action`, `performed_by`, `performed_at`) VALUES
(12, 6, 'Task created and assigned to user ID 14', 19, '2025-09-25 07:30:31'),
(13, 6, 'Status changed to \'in_progress\'', 14, '2025-09-25 07:31:44'),
(14, 6, 'Status changed to \'completed\'', 14, '2025-09-25 07:32:04'),
(15, 6, 'Task forwarded to Chenura Silva', 14, '2025-09-25 07:32:47'),
(16, 7, 'Task created and assigned to user ID 5', 8, '2025-09-25 07:39:04'),
(17, 7, 'Task forwarded to uvindu anuradha', 5, '2025-09-25 07:41:48'),
(18, 8, 'Task created and assigned to user ID 11', 11, '2025-09-25 07:49:35'),
(19, 8, 'Status changed to \'in_progress\'', 11, '2025-09-25 07:50:06'),
(20, 9, 'Task created and assigned to user ID 4', 19, '2025-09-25 07:52:43'),
(21, 10, 'Task created and assigned to user ID 10', 8, '2025-09-25 07:57:36'),
(22, 11, 'Task created and assigned to user ID 9', 8, '2025-09-25 07:58:41'),
(23, 10, 'Status changed to \'in_progress\'', 10, '2025-09-25 08:17:36'),
(24, 11, 'Status changed to \'completed\'', 9, '2025-09-25 09:19:01'),
(25, 12, 'Task created and assigned to user ID 9', 9, '2025-09-25 09:26:07'),
(26, 13, 'Task created and assigned to user ID 12', 19, '2025-09-25 09:54:26'),
(27, 7, 'Status changed to \'completed\'', 19, '2025-09-25 09:54:49'),
(28, 14, 'Task created and assigned to user ID 4', 19, '2025-09-25 10:13:42'),
(29, 15, 'Task created and assigned to user ID 9', 9, '2025-09-25 10:20:17'),
(30, 16, 'Task created and assigned to user ID 19', 19, '2025-09-25 10:21:09'),
(31, 16, 'Status changed to \'in_progress\'', 19, '2025-09-25 10:21:22'),
(32, 16, 'Status changed to \'completed\'', 19, '2025-09-25 10:21:27'),
(33, 17, 'Task created and assigned to user ID 19', 19, '2025-09-25 10:22:31'),
(34, 17, 'Status changed to \'in_progress\'', 19, '2025-09-25 10:22:36'),
(35, 18, 'Task created and assigned to user ID 9', 19, '2025-09-25 10:23:43'),
(36, 8, 'Status changed to \'completed\'', 11, '2025-09-25 10:27:15'),
(37, 19, 'Task created and assigned to user ID 16', 10, '2025-09-25 10:28:19'),
(38, 20, 'Task created and assigned to user ID 8', 19, '2025-09-25 10:28:55'),
(39, 13, 'Status changed to \'completed\'', 12, '2025-09-25 10:34:49'),
(40, 21, 'Task created and assigned to user ID 14', 12, '2025-09-25 10:37:40'),
(41, 22, 'Task created and assigned to user ID 16', 5, '2025-09-25 10:38:39'),
(42, 22, 'Status changed to \'in_progress\'', 16, '2025-09-25 10:39:38'),
(43, 22, 'Status changed to \'completed\'', 16, '2025-09-25 10:40:37'),
(44, 23, 'Task created and assigned to user ID 11', 10, '2025-09-25 10:41:08'),
(45, 19, 'Status changed to \'in_progress\'', 16, '2025-09-25 10:41:32'),
(46, 20, 'Task forwarded to Damika Bowattedeniya', 8, '2025-09-25 10:49:30'),
(47, 24, 'Task created and assigned to user ID 9', 9, '2025-09-25 12:00:28'),
(48, 20, 'Status changed to \'in_progress\'', 19, '2025-09-25 12:57:37'),
(49, 20, 'Added remark: ask from lahiru about the cutomer requirement clea...', 19, '2025-09-25 12:57:37'),
(50, 20, 'Status changed to \'pending\'', 19, '2025-09-25 12:57:57'),
(51, 7, 'Status changed to \'pending\'', 19, '2025-09-25 13:01:23'),
(52, 7, 'Status changed to \'completed\'', 19, '2025-09-25 13:01:44'),
(53, 24, 'Status changed to \'in_progress\'', 19, '2025-09-25 13:12:21'),
(54, 24, 'Added remark: DNS records are in cloudflare and update here what...', 19, '2025-09-25 13:12:21'),
(55, 24, 'Status changed to \'pending\'', 19, '2025-09-25 13:12:28'),
(56, 25, 'Task created and assigned to user ID 19', 19, '2025-09-25 13:13:54'),
(57, 25, 'Status changed to \'completed\'', 19, '2025-09-25 13:14:07'),
(58, 25, 'Added remark: send to Information & Technology Unit - MOL&FE <it...', 19, '2025-09-25 13:14:07'),
(59, 26, 'Task created and assigned to user ID 8', 20, '2025-09-25 17:40:45'),
(60, 23, 'Status changed to \'in_progress\'', 11, '2025-09-26 04:17:04'),
(61, 20, 'Status changed to \'completed\'', 14, '2025-09-26 04:20:59'),
(62, 20, 'Status changed to \'pending\'', 14, '2025-09-26 04:21:19'),
(63, 21, 'Status changed to \'completed\'', 14, '2025-09-26 04:21:33'),
(64, 14, 'Status changed to \'completed\'', 4, '2025-09-26 04:40:29'),
(65, 9, 'Status changed to \'completed\'', 4, '2025-09-26 04:40:36'),
(66, 27, 'Task created and assigned to user ID 14', 19, '2025-09-26 04:43:44'),
(67, 28, 'Task created and assigned to user ID 6', 19, '2025-09-26 04:50:02'),
(68, 24, 'Status changed to \'in_progress\'', 9, '2025-09-26 04:56:31'),
(69, 24, 'Added remark: I have updated the hostname from 50-6-171-19.blueh...', 9, '2025-09-26 04:56:31'),
(70, 15, 'Status changed to \'completed\'', 9, '2025-09-26 04:56:49'),
(71, 29, 'Task created and assigned to user ID 9', 9, '2025-09-26 04:57:24'),
(72, 27, 'Status changed to \'in_progress\'', 14, '2025-09-26 05:05:11'),
(73, 30, 'Task created and assigned to user ID 4', 19, '2025-09-26 05:10:10'),
(74, 26, 'Status changed to \'in_progress\'', 8, '2025-09-26 05:12:12'),
(75, 26, 'Added remark: he wll cl latter', 8, '2025-09-26 05:12:12'),
(76, 9, 'Status changed to \'pending\'', 19, '2025-09-26 05:24:27'),
(77, 9, 'Added remark: customer said updates are not completed', 19, '2025-09-26 05:24:27'),
(78, 31, 'Task created and assigned to user ID 19', 19, '2025-09-26 05:52:27'),
(79, 31, 'Status changed to \'in_progress\'', 19, '2025-09-26 05:52:35'),
(80, 24, 'Status changed to \'completed\'', 9, '2025-09-26 05:52:52'),
(81, 24, 'Added remark: Issue is with sltds.lk email, other lk emails it r...', 9, '2025-09-26 05:52:52'),
(82, 32, 'Task created and assigned to user ID 14', 19, '2025-09-26 05:55:12'),
(83, 33, 'Task created and assigned to user ID 4', 4, '2025-09-26 05:55:33'),
(84, 33, 'Status changed to \'completed\'', 4, '2025-09-26 05:55:41'),
(85, 9, 'Status changed to \'completed\'', 4, '2025-09-26 05:55:59'),
(86, 34, 'Task created and assigned to user ID 13', 9, '2025-09-26 05:56:08'),
(87, 35, 'Task created and assigned to user ID 15', 9, '2025-09-26 05:56:40'),
(88, 36, 'Task created and assigned to user ID 12', 9, '2025-09-26 05:56:57'),
(89, 37, 'Task created and assigned to user ID 4', 4, '2025-09-26 05:57:06'),
(90, 38, 'Task created and assigned to user ID 4', 9, '2025-09-26 05:57:25'),
(91, 39, 'Task created and assigned to user ID 10', 9, '2025-09-26 05:57:55'),
(92, 40, 'Task created and assigned to user ID 9', 9, '2025-09-26 06:03:19'),
(93, 40, 'Status changed to \'completed\'', 9, '2025-09-26 06:03:42'),
(94, 41, 'Task created and assigned to user ID 9', 9, '2025-09-26 06:04:27'),
(95, 42, 'Task created and assigned to user ID 9', 19, '2025-09-26 06:19:54'),
(96, 32, 'Status changed to \'in_progress\'', 19, '2025-09-26 06:37:07'),
(97, 32, 'Added remark: need a favicon immediately', 19, '2025-09-26 06:37:07'),
(98, 32, 'Status changed to \'pending\'', 19, '2025-09-26 06:37:43'),
(99, 31, 'Status changed to \'completed\'', 19, '2025-09-26 06:51:32'),
(100, 27, 'Status changed to \'completed\'', 14, '2025-09-26 07:34:06'),
(101, 32, 'Status changed to \'in_progress\'', 14, '2025-09-26 07:38:16'),
(102, 20, 'Task forwarded to Chenura Silva', 14, '2025-09-26 08:14:22'),
(103, 19, 'Status changed to \'completed\'', 16, '2025-09-26 08:26:11'),
(104, 36, 'Status changed to \'completed\'', 12, '2025-09-26 08:27:44'),
(105, 36, 'Added remark: Sent Google Analytics.', 12, '2025-09-26 08:27:44'),
(106, 38, 'Status changed to \'completed\'', 4, '2025-09-26 08:33:17'),
(107, 38, 'Status changed to \'pending\'', 4, '2025-09-26 08:33:28'),
(108, 37, 'Status changed to \'completed\'', 4, '2025-09-26 08:33:35'),
(109, 20, 'Status changed to \'in_progress\'', 16, '2025-09-26 08:37:03'),
(110, 39, 'Status changed to \'completed\'', 10, '2025-09-26 09:14:18'),
(111, 32, 'Status changed to \'completed\'', 14, '2025-09-26 09:30:39'),
(112, 30, 'Status changed to \'completed\'', 4, '2025-09-26 10:13:36'),
(113, 38, 'Status changed to \'in_progress\'', 4, '2025-09-26 10:13:54'),
(114, 12, 'Status changed to \'completed\'', 9, '2025-09-26 10:16:53'),
(115, 29, 'Status changed to \'completed\'', 9, '2025-09-26 10:17:06'),
(116, 18, 'Status changed to \'in_progress\'', 9, '2025-09-26 10:20:24'),
(117, 18, 'Added remark: Merchant ID TEST700182200500\r\nPassword   - Boc@Gjr...', 9, '2025-09-26 10:20:24'),
(118, 18, 'Status changed to \'pending\'', 9, '2025-09-26 10:20:59'),
(119, 18, 'Status changed to \'in_progress\'', 9, '2025-09-26 10:21:51'),
(120, 18, 'Added remark: I only recieved this details, these are not enough...', 9, '2025-09-26 10:21:51'),
(121, 23, 'Status changed to \'completed\'', 11, '2025-09-26 10:24:48'),
(122, 43, 'Task created and assigned to user ID 19', 9, '2025-09-26 10:25:13'),
(123, 20, 'Status changed to \'pending\'', 19, '2025-09-26 11:11:30'),
(124, 20, 'Added remark: This is not a ecommerce site.. but need to show pr...', 19, '2025-09-26 11:11:30'),
(125, 20, 'Status changed to \'in_progress\'', 19, '2025-09-26 11:11:56'),
(126, 44, 'Task created and assigned to user ID 5', 8, '2025-09-26 11:13:50'),
(127, 43, 'Task forwarded to Yohani Abeykoon', 19, '2025-09-26 11:17:53'),
(128, 43, 'Added remark: tell them this is not enough to proceed and let th...', 19, '2025-09-26 11:17:53'),
(129, 28, 'Status changed to \'in_progress\'', 6, '2025-09-26 11:18:17'),
(130, 45, 'Task created and assigned to user ID 19', 8, '2025-09-26 11:18:46'),
(131, 46, 'Task created and assigned to user ID 8', 8, '2025-09-26 11:20:13'),
(132, 47, 'Task created and assigned to user ID 13', 8, '2025-09-26 11:21:23'),
(133, 34, 'Status changed to \'completed\'', 13, '2025-09-26 11:36:50'),
(134, 34, 'Added remark: All projects are currently ongoing as they have no...', 13, '2025-09-26 11:36:50'),
(135, 48, 'Task created and assigned to user ID 8', 8, '2025-09-27 02:13:21'),
(136, 49, 'Task created and assigned to user ID 14', 19, '2025-09-27 11:13:21'),
(137, 42, 'Task forwarded to uvindu anuradha', 19, '2025-09-27 11:14:50'),
(138, 42, 'Added remark: i have tried to call but he didnt answer on friday', 19, '2025-09-27 11:14:50'),
(139, 49, 'Status changed to \'in_progress\'', 14, '2025-09-27 16:30:01'),
(140, 49, 'Status changed to \'completed\'', 14, '2025-09-27 17:26:57'),
(141, 50, 'Task created and assigned to user ID 16', 14, '2025-09-27 17:30:41'),
(142, 50, 'Status changed to \'in_progress\'', 16, '2025-09-27 18:02:29'),
(143, 50, 'Status changed to \'completed\'', 16, '2025-09-27 18:38:56'),
(144, 51, 'Task created with priority \'high\' and assigned to user ID 10', 19, '2025-09-28 07:05:08'),
(145, 45, 'Status changed to \'in_progress\'', 19, '2025-09-28 07:06:34'),
(146, 45, 'Added remark: dean and you are going to it right!!!! um unable t...', 19, '2025-09-28 07:06:34'),
(147, 52, 'Task created with priority \'high\' and assigned to user ID 8', 19, '2025-09-28 07:07:44'),
(148, 53, 'Task created with priority \'high\' and assigned to user ID 19', 19, '2025-09-28 07:07:44'),
(149, 48, 'Status changed to \'completed\'', 8, '2025-09-28 07:17:48'),
(150, 54, 'Task created with priority \'high\' and assigned to user ID 8', 20, '2025-09-28 19:24:06'),
(151, 55, 'Task created with priority \'high\' and assigned to user ID 9', 20, '2025-09-28 19:42:44'),
(152, 56, 'Task created with priority \'high\' and assigned to user ID 8', 20, '2025-09-28 19:42:44'),
(153, 47, 'Status changed to \'in_progress\'', 13, '2025-09-29 03:43:14'),
(154, 51, 'Status changed to \'in_progress\'', 10, '2025-09-29 04:03:17'),
(155, 57, 'Task created with priority \'high\' and assigned to user ID 19', 19, '2025-09-29 04:05:53'),
(156, 10, 'Status changed to \'completed\'', 10, '2025-09-29 05:02:12'),
(157, 55, 'Status changed to \'completed\'', 9, '2025-09-29 05:10:32'),
(158, 55, 'Added remark: I spoke with the client and Buddhika, and the clie...', 9, '2025-09-29 05:10:32'),
(159, 43, 'Status changed to \'in_progress\'', 9, '2025-09-29 05:12:56'),
(160, 43, 'Added remark: I already emailed them on Friday, and they said th...', 9, '2025-09-29 05:12:56'),
(161, 58, 'Task created with priority \'medium\' and assigned to user ID 10', 10, '2025-09-29 05:22:15'),
(162, 58, 'Status changed to \'in_progress\'', 10, '2025-09-29 05:58:20'),
(163, 41, 'Status changed to \'completed\'', 9, '2025-09-29 06:07:33'),
(164, 47, 'Status changed to \'completed\'', 13, '2025-09-29 06:51:57'),
(165, 47, 'Added remark: https://testing.sltdigitalweb.lk/lakmina_lanka/', 13, '2025-09-29 06:51:57'),
(166, 51, 'Status changed to \'completed\'', 10, '2025-09-29 07:19:29'),
(167, 59, 'Task created with priority \'high\' and assigned to user ID 14', 19, '2025-09-29 08:33:42'),
(168, 44, 'Task forwarded to Damika Bowattedeniya', 5, '2025-09-29 08:40:26'),
(169, 44, 'Added remark: UI is pending', 5, '2025-09-29 08:40:26'),
(170, 60, 'Task created with priority \'medium\' and assigned to user ID 5', 5, '2025-09-29 08:52:32'),
(171, 61, 'Task created with priority \'medium\' and assigned to user ID 4', 4, '2025-09-29 09:05:28'),
(172, 62, 'Task created with priority \'high\' and assigned to user ID 4', 4, '2025-09-29 09:06:55'),
(173, 62, 'Status changed to \'completed\'', 4, '2025-09-29 09:07:15'),
(174, 59, 'Status changed to \'completed\'', 14, '2025-09-29 09:50:54'),
(175, 58, 'Status changed to \'completed\'', 10, '2025-09-29 09:50:59'),
(176, 63, 'Task created with priority \'medium\' and assigned to user ID 10', 10, '2025-09-29 09:53:21'),
(177, 64, 'Task created with priority \'high\' and assigned to user ID 9', 9, '2025-09-29 09:56:32'),
(178, 44, 'Task forwarded to Lahiru Sulochana\r\n', 14, '2025-09-29 09:57:17'),
(179, 44, 'Added remark: anupa allrady do that', 14, '2025-09-29 09:57:17'),
(180, 63, 'Status changed to \'in_progress\'', 10, '2025-09-29 10:32:05'),
(181, 61, 'Status changed to \'completed\'', 4, '2025-09-29 10:55:37'),
(182, 63, 'Status changed to \'completed\'', 10, '2025-09-29 11:00:29'),
(183, 56, 'Status changed to \'in_progress\'', 8, '2025-09-29 11:10:23'),
(184, 56, 'Added remark: i called buddika', 8, '2025-09-29 11:10:23'),
(185, 65, 'Task created with priority \'medium\' and assigned to user ID 14', 8, '2025-09-29 11:15:36'),
(186, 64, 'Status changed to \'completed\'', 9, '2025-09-29 12:37:17'),
(187, 20, 'Task forwarded to Damika Bowattedeniya', 16, '2025-09-29 22:41:40'),
(188, 20, 'Added remark: Created Homepage and forward to Damika', 16, '2025-09-29 22:41:40'),
(189, 35, 'Status changed to \'completed\'', 15, '2025-09-30 03:37:35'),
(190, 35, 'Added remark: There are no active live sites at the moment.', 15, '2025-09-30 03:37:35'),
(191, 66, 'Task created with priority \'medium\' and assigned to user ID 5', 5, '2025-09-30 03:58:52'),
(192, 65, 'Task forwarded to Anupa Helaruwan', 14, '2025-09-30 04:06:29'),
(193, 67, 'Task created with priority \'medium\' and assigned to user ID 15', 4, '2025-09-30 04:06:47'),
(194, 28, 'Status changed to \'completed\'', 6, '2025-09-30 04:18:09'),
(195, 67, 'Status changed to \'in_progress\'', 15, '2025-09-30 04:28:52'),
(196, 46, 'Status changed to \'completed\'', 8, '2025-09-30 04:38:21'),
(197, 68, 'Task created with priority \'medium\' and assigned to user ID 16', 16, '2025-09-30 07:53:34'),
(198, 68, 'Status changed to \'in_progress\'', 16, '2025-09-30 07:53:46'),
(199, 68, 'Task forwarded to uvindu anuradha', 16, '2025-09-30 07:54:05'),
(200, 69, 'Task created with priority \'low\' and assigned to user ID 16', 16, '2025-09-30 08:07:08'),
(201, 70, 'Task created with priority \'medium\' and assigned to user ID 16', 16, '2025-09-30 08:07:30'),
(202, 70, 'Status changed to \'completed\'', 16, '2025-09-30 08:07:40'),
(203, 69, 'Task forwarded to uvindu anuradha', 16, '2025-09-30 08:08:22'),
(204, 69, 'Added remark: testzzzzz', 16, '2025-09-30 08:08:22'),
(205, 69, 'Status changed to \'completed\'', 19, '2025-09-30 08:09:38'),
(206, 69, 'Added remark: done', 19, '2025-09-30 08:09:38'),
(207, 67, 'Status changed to \'completed\'', 19, '2025-09-30 08:09:56'),
(208, 68, 'Status changed to \'completed\'', 19, '2025-09-30 08:10:14'),
(209, 71, 'Task created with priority \'low\' and assigned to user ID 16', 16, '2025-09-30 08:12:02'),
(210, 71, 'Task forwarded to uvindu anuradha', 16, '2025-09-30 08:12:16'),
(211, 71, 'Added remark: fsdfsd', 16, '2025-09-30 08:12:16'),
(212, 70, 'Status changed to \'completed\'', 16, '2025-09-30 08:45:56'),
(213, 70, 'Added remark: dsds', 16, '2025-09-30 08:45:56'),
(214, 70, 'Status changed to \'completed\'', 16, '2025-09-30 08:46:04'),
(215, 70, 'Added remark: fsdfsd', 16, '2025-09-30 08:46:04'),
(216, 70, 'Status changed to \'completed\'', 16, '2025-09-30 08:46:22'),
(217, 70, 'Added remark: dsdf', 16, '2025-09-30 08:46:22'),
(218, 70, 'Status changed to \'completed\'', 16, '2025-09-30 08:46:30'),
(219, 70, 'Added remark: fffff', 16, '2025-09-30 08:46:30'),
(220, 70, 'Status changed to \'completed\'', 16, '2025-09-30 08:46:41'),
(221, 70, 'Added remark: fffff', 16, '2025-09-30 08:46:41'),
(222, 70, 'Status changed to \'completed\'', 16, '2025-09-30 08:46:54'),
(223, 70, 'Added remark: euuuuuuuuuuuuuuuuuuuu', 16, '2025-09-30 08:46:54'),
(224, 70, 'Status changed to \'completed\'', 16, '2025-09-30 08:47:20'),
(225, 70, 'Added remark: roih', 16, '2025-09-30 08:47:20'),
(226, 70, 'Status changed to \'forward_to\'', 16, '2025-09-30 08:49:28'),
(227, 70, 'Added remark: dsf', 16, '2025-09-30 08:49:28'),
(228, 70, 'Status changed to \'pending\'', 16, '2025-09-30 08:57:00'),
(229, 70, 'Added remark: dfsfsd', 16, '2025-09-30 08:57:00'),
(230, 70, 'Status changed to \'in_progress\'', 16, '2025-09-30 08:57:09'),
(231, 70, 'Due date changed from Sep 30, 2025 to Oct 10, 2025', 16, '2025-09-30 09:02:47'),
(232, 70, 'Added remark: test', 16, '2025-09-30 09:03:03'),
(233, 72, 'Task created with priority \'low\' and assigned to user ID Damika Bowattedeniya', 16, '2025-09-30 09:05:15'),
(234, 70, 'Task forwarded to uvindu anuradha', 16, '2025-09-30 09:35:05');

-- --------------------------------------------------------

--
-- Table structure for table `task_remarks`
--

CREATE TABLE `task_remarks` (
  `id` int(11) NOT NULL,
  `task_id` int(11) DEFAULT NULL,
  `remark` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_remarks`
--

INSERT INTO `task_remarks` (`id`, `task_id`, `remark`, `created_by`, `created_at`) VALUES
(7, 20, 'ask from lahiru about the cutomer requirement clearly first', 19, '2025-09-25 12:57:37'),
(8, 24, 'DNS records are in cloudflare and update here what was the bluehost chat replpy???', 19, '2025-09-25 13:12:21'),
(9, 25, 'send to Information & Technology Unit - MOL&FE <itlabourmin@gmail.com>', 19, '2025-09-25 13:14:07'),
(10, 24, 'I have updated the hostname from 50-6-171-19.bluehost.com to server.fleethub.lk and also set the PTR record from the server side.\r\n\r\nIn addition, I updated the DNS record as follows:\r\n\r\nType: A\r\n\r\nName: server\r\n\r\nIPv4: 50.6.171.19\r\n\r\nProxy status: DNS only\r\n\r\nHowever, it still doesn’t seem to be working, and there are no bounced messages either.', 9, '2025-09-26 04:56:31'),
(11, 26, 'he wll cl latter', 8, '2025-09-26 05:12:12'),
(12, 9, 'customer said updates are not completed', 19, '2025-09-26 05:24:27'),
(13, 24, 'Issue is with sltds.lk email, other lk emails it recieved and send.', 9, '2025-09-26 05:52:52'),
(14, 32, 'need a favicon immediately', 19, '2025-09-26 06:37:07'),
(15, 36, 'Sent Google Analytics.', 12, '2025-09-26 08:27:44'),
(16, 18, 'Merchant ID TEST700182200500\r\nPassword   - Boc@Gjrti\r\nCurrency - LKR', 9, '2025-09-26 10:20:24'),
(17, 18, 'I only recieved this details, these are not enough for payment gateway\r\n\r\nMerchant ID TEST700182200500\r\nPassword   - Boc@Gjrti\r\nCurrency - LKR', 9, '2025-09-26 10:21:51'),
(18, 20, 'This is not a ecommerce site.. but need to show prices', 19, '2025-09-26 11:11:30'),
(19, 43, 'tell them this is not enough to proceed and let them know what need more!!!', 19, '2025-09-26 11:17:53'),
(20, 34, 'All projects are currently ongoing as they have not yet been deployed to the live server.', 13, '2025-09-26 11:36:50'),
(21, 42, 'i have tried to call but he didnt answer on friday', 19, '2025-09-27 11:14:50'),
(22, 45, 'dean and you are going to it right!!!! um unable to come', 19, '2025-09-28 07:06:34'),
(23, 55, 'I spoke with the client and Buddhika, and the client confirmed that he is satisfied with the website. He want to proceed with going live and mentioned that he will provide an update regarding the payments and domain shortly within today.', 9, '2025-09-29 05:10:32'),
(24, 43, 'I already emailed them on Friday, and they said they would send the pending data.', 9, '2025-09-29 05:12:56'),
(25, 47, 'https://testing.sltdigitalweb.lk/lakmina_lanka/', 13, '2025-09-29 06:51:57'),
(26, 44, 'UI is pending', 5, '2025-09-29 08:40:26'),
(27, 44, 'anupa allrady do that', 14, '2025-09-29 09:57:17'),
(28, 56, 'i called buddika', 8, '2025-09-29 11:10:23'),
(29, 20, 'Created Homepage and forward to Damika', 16, '2025-09-29 22:41:40'),
(30, 35, 'There are no active live sites at the moment.', 15, '2025-09-30 03:37:35'),
(31, 69, 'testzzzzz', 16, '2025-09-30 08:08:22'),
(32, 69, 'done', 19, '2025-09-30 08:09:38'),
(33, 71, 'fsdfsd', 16, '2025-09-30 08:12:16'),
(34, 70, 'dsds', 16, '2025-09-30 08:45:56'),
(35, 70, 'fsdfsd', 16, '2025-09-30 08:46:04'),
(36, 70, 'dsdf', 16, '2025-09-30 08:46:22'),
(37, 70, 'fffff', 16, '2025-09-30 08:46:30'),
(38, 70, 'fffff', 16, '2025-09-30 08:46:41'),
(39, 70, 'euuuuuuuuuuuuuuuuuuuu', 16, '2025-09-30 08:46:54'),
(40, 70, 'roih', 16, '2025-09-30 08:47:20'),
(41, 70, 'dsf', 16, '2025-09-30 08:49:28'),
(42, 70, 'dfsfsd', 16, '2025-09-30 08:57:00'),
(43, 70, 'test', 16, '2025-09-30 09:03:03');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('Business Analyst','UI Developer','Intern Web Developer','Associate Web Developer','Senior Web Developer','Team Lead','Assistant Manager','Manager') NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `role`, `email`, `created_at`) VALUES
(4, 'lakmewan99@gmail.com', '$2y$10$FhOegRPfTF03RCIlp3pzBucmFO7SpDFLjMEhvmqB7hrJ5TWEehC3.', 'Lakmewan Gayantha', 'Associate Web Developer', 'lakmewan99@gmail.com', '2025-09-25 06:22:19'),
(5, 'piyumi@sltds.lk', '$2y$10$WVCgoH/Q4TR9KGMrocwT2.f1HE783dziwsk5CDm.fC0fmOmco4Gde', 'Piyumi Fonseka', 'Business Analyst', 'piyumi@sltds.lk', '2025-09-25 06:26:42'),
(6, 'tharushiabesinghe9941@gmail.com', '$2y$10$GXjsre5wSeaQxOFp4TYj7OCtFiozQ3/DqUdd7TUjaToG.WrdD4Y7e', 'Tharushi Abeysinghe', 'Business Analyst', 'tharushiabesinghe9941@gmail.com', '2025-09-25 06:28:37'),
(8, 'lahirus@sltds.lk', '$2y$10$/Eq5pYQ1jP392CiK1v3oC.rpy5jN7yoftrrSriG/6BWPd4BDb3euK', 'Lahiru Sulochana\r\n', 'Team Lead', 'lahirus@sltds.lk', '2025-09-25 06:30:05'),
(9, 'yohania@sltds.lk', '$2y$10$irNjQN.Ak1RRw4ct75MV6eI7wosMQw9wkObL.rJMzaa/bPhhO4lWC', 'Yohani Abeykoon', 'Associate Web Developer', 'yohania@sltds.lk', '2025-09-25 06:32:59'),
(10, 'lakmiu@sltds.lk', '$2y$10$63xHZj/Emq/mJ18Pbf4CHeFFd1l2rBmBbaDwCoFFpI9Es2ImHcFHO', 'Lakmi Uresha', 'Associate Web Developer', 'lakmiu@sltds.lk', '2025-09-25 06:32:59'),
(11, 'anupahelaruwan2000@gmail.com', '$2y$10$Mo4BSTMtYSkCBP17c3QTIe7yv5oBX7CvqeG8CldMRJa8kQCrmCE5a', 'Anupa Helaruwan', 'UI Developer', 'anupahelaruwan2000@gmail.com', '2025-09-25 06:35:21'),
(12, 'kumodiworking@gmail.com', '$2y$10$2CeyGadXOZlQR/kcUpg3uOWJgXhKf7KsoTNDcN1dUmZbxO/aBdAvu', 'Kumodi Sandanima', 'Intern Web Developer', 'kumodiworking@gmail.com', '2025-09-25 06:35:21'),
(13, 'aravindaviraj99@gmail.com', '$2y$10$ypuFF9.sVCOYBDsiH9QTo.xE0XWxIxN81XeMleuKZJgDPcyi766J.', 'Aravinda Viraj', 'Intern Web Developer', 'aravindaviraj99@gmail.com', '2025-09-25 06:38:00'),
(14, 'damikan@sltds.lk', '$2y$10$0xrda53sOi1nTRpdX20H1OmFakHakejhrJPSuxjfQ7gHQ2QYoN/J.', 'Damika Bowattedeniya', 'UI Developer', 'damikan@sltds.lk', '2025-09-25 06:38:00'),
(15, 'chandali2000111@gmail.com', '$2y$10$a.UsbJx8OgLVaJq59cFjSepWByGg8fSx/vSNBskT/2/JptgtPhrV.', 'Chandali Dassanayake', 'Intern Web Developer', 'chandali2000111@gmail.com', '2025-09-25 06:43:14'),
(16, 'chenura55@gmail.com', '$2y$10$iaXps6uwL07dFd7yEM4k/uYDBaWPqx.HT6RtUhLuGUOQ/zYzb8Oku', 'Chenura Silva', 'UI Developer', 'chenura55@gmail.com', '2025-09-25 06:43:14'),
(19, 'uvindua@sltds.lk', '$2y$10$eQs5QtYJTpGfUVtt7x.IVu6ILfZCHktKhZNn0CpsziAurQNnwClLW', 'uvindu anuradha', 'Assistant Manager', 'uvindua@sltds.lk', '2025-09-25 06:47:24'),
(20, 'danushka@sltds.lk', '$2y$10$lN6i1PxCFUXUigfpsm/TXeTTnBAQPS/fE6yE3.YTDUYxaNO9fNGvK', 'Danushka Gangoda', 'Manager', 'danushka@sltds.lk', '2025-09-25 06:47:24');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `task_history`
--
ALTER TABLE `task_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_id` (`task_id`),
  ADD KEY `performed_by` (`performed_by`);

--
-- Indexes for table `task_remarks`
--
ALTER TABLE `task_remarks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_id` (`task_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `task_history`
--
ALTER TABLE `task_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=235;

--
-- AUTO_INCREMENT for table `task_remarks`
--
ALTER TABLE `task_remarks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `task_history`
--
ALTER TABLE `task_history`
  ADD CONSTRAINT `task_history_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`),
  ADD CONSTRAINT `task_history_ibfk_2` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `task_remarks`
--
ALTER TABLE `task_remarks`
  ADD CONSTRAINT `task_remarks_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`),
  ADD CONSTRAINT `task_remarks_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

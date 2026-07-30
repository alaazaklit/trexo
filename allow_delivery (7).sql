-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Feb 16, 2025 at 09:25 AM
-- Server version: 8.2.0
-- PHP Version: 8.3.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `allow_delivery`
--

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

DROP TABLE IF EXISTS `addresses`;
CREATE TABLE IF NOT EXISTS `addresses` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED DEFAULT NULL,
  `address_line1` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_line2` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `postal_code` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitude` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `longitude` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `order_id` int DEFAULT NULL,
  `direction` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `addresses`
--

INSERT INTO `addresses` (`id`, `user_id`, `address_line1`, `address_line2`, `city`, `state`, `postal_code`, `country`, `latitude`, `longitude`, `created_at`, `updated_at`, `deleted_at`, `order_id`, `direction`) VALUES
(1, 2, 'Beirut, Lebanon', '', '', '', '', '', '33.557067339686', '35.372947845608', '2025-02-07 06:56:00', '2025-02-07 06:56:00', NULL, 1, 'start_address'),
(2, 2, 'Tripoli, Lebanon', '', '', '', '', '', '33.893789404378', '35.501776691526', '2025-02-07 06:56:00', '2025-02-07 06:56:00', NULL, 1, 'destination_address'),
(3, 2, 'Saida souks, أسواق صيدا, Fakhreddine, Sidon, Lebanon', '', '', '', '', '', '33.56478188407', '35.374433286488', '2025-02-07 07:04:20', '2025-02-07 07:04:20', NULL, 2, 'start_address'),
(4, 2, 'Tripoli, Lebanon', '', '', '', '', '', '33.893789404378', '35.501776691526', '2025-02-07 07:04:20', '2025-02-07 07:04:20', NULL, 2, 'destination_address'),
(5, 8, 'Saida souks, أسواق صيدا, Fakhreddine, Sidon, Lebanon', '', '', '', '', '', '33.564780602766', '35.37443311885', '2025-02-09 15:05:19', '2025-02-09 15:05:19', NULL, 3, 'start_address'),
(6, 8, 'Tripoli, Lebanon', '', '', '', '', '', '33.893789404378', '35.501776691526', '2025-02-09 15:05:19', '2025-02-09 15:05:19', NULL, 3, 'destination_address'),
(7, 1, 'Saida souks, أسواق صيدا, Fakhreddine, Sidon, Lebanon', '', '', '', '', '', '33.564780602766', '35.37443311885', '2025-02-15 11:01:46', '2025-02-15 11:01:46', NULL, 4, 'start_address'),
(8, 1, 'Tripoli, Lebanon', '', '', '', '', '', '33.893789404378', '35.501776691526', '2025-02-15 11:01:46', '2025-02-15 11:01:46', NULL, 4, 'destination_address'),
(9, 2, 'Saida souks, أسواق صيدا, Fakhreddine, Sidon, Lebanon', '', '', '', '', '', '33.564780602766', '35.37443311885', '2025-02-15 16:04:18', '2025-02-15 16:04:18', NULL, 5, 'start_address'),
(10, 2, 'Tripoli, Lebanon', '', '', '', '', '', '33.893789404378', '35.501776691526', '2025-02-15 16:04:18', '2025-02-15 16:04:18', NULL, 5, 'destination_address'),
(11, 2, 'Saida souks, أسواق صيدا, Fakhreddine, Sidon, Lebanon', '', '', '', '', '', '33.564780602766', '35.37443311885', '2025-02-16 05:15:20', '2025-02-16 05:15:20', NULL, 6, 'start_address'),
(12, 2, 'Tripoli, Lebanon', '', '', '', '', '', '33.893789404378', '35.501776691526', '2025-02-16 05:15:20', '2025-02-16 05:15:20', NULL, 6, 'destination_address'),
(13, 2, 'Saida souks, أسواق صيدا, Fakhreddine, Sidon, Lebanon', '', '', '', '', '', '33.564780602766', '35.37443311885', '2025-02-16 07:07:58', '2025-02-16 07:07:58', NULL, 7, 'start_address'),
(14, 2, 'Tripoli, Lebanon', '', '', '', '', '', '33.893789404378', '35.501776691526', '2025-02-16 07:07:58', '2025-02-16 07:07:58', NULL, 7, 'destination_address');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` int UNSIGNED DEFAULT NULL,
  `order` int NOT NULL DEFAULT '1',
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_slug_unique` (`slug`),
  KEY `categories_parent_id_foreign` (`parent_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `parent_id`, `order`, `name`, `slug`, `created_at`, `updated_at`) VALUES
(1, NULL, 1, 'Category 1', 'category-1', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(2, NULL, 1, 'Category 2', 'category-2', '2024-08-25 17:07:06', '2024-08-25 17:07:06');

-- --------------------------------------------------------

--
-- Table structure for table `data_rows`
--

DROP TABLE IF EXISTS `data_rows`;
CREATE TABLE IF NOT EXISTS `data_rows` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `data_type_id` int UNSIGNED NOT NULL,
  `field` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `required` tinyint(1) NOT NULL DEFAULT '0',
  `browse` tinyint(1) NOT NULL DEFAULT '1',
  `read` tinyint(1) NOT NULL DEFAULT '1',
  `edit` tinyint(1) NOT NULL DEFAULT '1',
  `add` tinyint(1) NOT NULL DEFAULT '1',
  `delete` tinyint(1) NOT NULL DEFAULT '1',
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `order` int NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `data_rows_data_type_id_foreign` (`data_type_id`)
) ENGINE=MyISAM AUTO_INCREMENT=204 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `data_rows`
--

INSERT INTO `data_rows` (`id`, `data_type_id`, `field`, `type`, `display_name`, `required`, `browse`, `read`, `edit`, `add`, `delete`, `details`, `order`, `created_at`, `updated_at`) VALUES
(1, 1, 'id', 'number', 'ID', 1, 0, 0, 0, 0, 0, '{}', 1, NULL, '2024-09-01 05:27:30'),
(2, 1, 'name', 'text', 'Name', 1, 1, 1, 1, 1, 1, '{}', 2, NULL, '2024-09-01 05:27:30'),
(3, 1, 'email', 'text', 'Email', 1, 1, 1, 1, 1, 1, '{}', 3, NULL, '2024-09-01 05:27:30'),
(4, 1, 'password', 'password', 'Password', 1, 0, 0, 1, 1, 0, '{}', 4, NULL, '2024-09-01 05:27:30'),
(5, 1, 'remember_token', 'text', 'Remember Token', 0, 0, 0, 0, 0, 0, '{}', 5, NULL, '2024-09-01 05:27:30'),
(6, 1, 'created_at', 'timestamp', 'Created At', 0, 1, 1, 0, 0, 0, '{}', 6, NULL, '2024-09-01 05:27:30'),
(7, 1, 'updated_at', 'timestamp', 'Updated At', 0, 0, 0, 0, 0, 0, '{}', 7, NULL, '2024-09-01 05:27:30'),
(8, 1, 'avatar', 'image', 'Avatar', 0, 1, 1, 1, 1, 1, '{}', 8, NULL, '2024-09-01 05:27:30'),
(9, 1, 'user_belongsto_role_relationship', 'relationship', 'Role', 0, 1, 1, 1, 1, 0, '{\"model\":\"TCG\\\\Voyager\\\\Models\\\\Role\",\"table\":\"roles\",\"type\":\"belongsTo\",\"column\":\"role_id\",\"key\":\"id\",\"label\":\"display_name\",\"pivot_table\":\"roles\",\"pivot\":\"0\",\"taggable\":\"0\"}', 10, NULL, '2024-09-01 05:27:30'),
(10, 1, 'user_belongstomany_role_relationship', 'relationship', 'Roles', 0, 1, 1, 1, 1, 0, '{\"model\":\"TCG\\\\Voyager\\\\Models\\\\Role\",\"table\":\"roles\",\"type\":\"belongsToMany\",\"column\":\"id\",\"key\":\"id\",\"label\":\"display_name\",\"pivot_table\":\"user_roles\",\"pivot\":\"1\",\"taggable\":\"0\"}', 11, NULL, NULL),
(11, 1, 'settings', 'hidden', 'Settings', 0, 0, 0, 0, 0, 0, '{}', 12, NULL, '2024-09-01 05:27:30'),
(12, 2, 'id', 'number', 'ID', 1, 0, 0, 0, 0, 0, NULL, 1, NULL, NULL),
(13, 2, 'name', 'text', 'Name', 1, 1, 1, 1, 1, 1, NULL, 2, NULL, NULL),
(14, 2, 'created_at', 'timestamp', 'Created At', 0, 0, 0, 0, 0, 0, NULL, 3, NULL, NULL),
(15, 2, 'updated_at', 'timestamp', 'Updated At', 0, 0, 0, 0, 0, 0, NULL, 4, NULL, NULL),
(16, 3, 'id', 'number', 'ID', 1, 0, 0, 0, 0, 0, NULL, 1, NULL, NULL),
(17, 3, 'name', 'text', 'Name', 1, 1, 1, 1, 1, 1, NULL, 2, NULL, NULL),
(18, 3, 'created_at', 'timestamp', 'Created At', 0, 0, 0, 0, 0, 0, NULL, 3, NULL, NULL),
(19, 3, 'updated_at', 'timestamp', 'Updated At', 0, 0, 0, 0, 0, 0, NULL, 4, NULL, NULL),
(20, 3, 'display_name', 'text', 'Display Name', 1, 1, 1, 1, 1, 1, NULL, 5, NULL, NULL),
(21, 1, 'role_id', 'text', 'Role', 0, 1, 1, 1, 1, 1, '{}', 9, NULL, '2024-09-01 05:27:30'),
(22, 4, 'table_name', 'text', 'Table Name', 0, 1, 1, 1, 1, 1, '{\"required\":true}', 1, NULL, NULL),
(23, 4, 'column_name', 'text', 'Column Name', 0, 1, 1, 1, 1, 1, '{\"required\":true}', 2, NULL, NULL),
(24, 4, 'foreign_key', 'number', 'Foreign Key', 0, 1, 1, 1, 1, 1, '{\"required\":true}', 3, NULL, NULL),
(25, 4, 'locale', 'text', 'Locale', 0, 1, 1, 1, 1, 1, '{\"required\":true}', 4, NULL, NULL),
(26, 4, 'value', 'textarea', 'Value', 0, 1, 1, 1, 1, 1, '{\"required\":true}', 5, NULL, NULL),
(126, 1114, 'rating', 'text', 'Rating', 0, 1, 1, 1, 1, 1, '{}', 4, NULL, NULL),
(125, 1114, 'product_id', 'text', 'Product Id', 0, 1, 1, 1, 1, 1, '{}', 3, NULL, NULL),
(124, 1114, 'user_id', 'text', 'User Id', 0, 1, 1, 1, 1, 1, '{}', 2, NULL, NULL),
(123, 1114, 'id', 'text', 'Id', 1, 0, 0, 0, 0, 0, '{}', 1, NULL, NULL),
(31, 6, 'user_id', 'number', 'User ID', 0, 1, 1, 1, 1, 1, '{\"required\":true}', 1, '2024-08-25 19:51:07', '2024-08-25 19:51:07'),
(32, 6, 'title', 'text', 'Title', 0, 1, 1, 1, 1, 1, '{\"required\":true}', 2, '2024-08-25 19:51:07', '2024-08-25 19:51:07'),
(33, 6, 'message', 'textarea', 'Message', 0, 1, 1, 1, 1, 1, '{\"required\":true}', 3, '2024-08-25 19:51:07', '2024-08-25 19:51:07'),
(34, 7, 'user_id', 'number', 'User ID', 0, 1, 1, 1, 1, 1, '{\"required\":true}', 1, '2024-08-25 20:03:28', '2024-08-25 20:03:28'),
(35, 7, 'start_location', 'text', 'Start Location', 0, 1, 1, 1, 1, 1, '{\"required\":true}', 2, '2024-08-25 20:03:28', '2024-08-25 20:03:28'),
(36, 7, 'end_location', 'text', 'End Location', 0, 1, 1, 1, 1, 1, '{\"required\":true}', 3, '2024-08-25 20:03:28', '2024-08-25 20:03:28'),
(37, 7, 'distance', 'number', 'Distance', 0, 1, 1, 1, 1, 1, '{\"required\":true}', 4, '2024-08-25 20:03:28', '2024-08-25 20:03:28'),
(38, 7, 'fare', 'number', 'Fare', 0, 1, 1, 1, 1, 1, '{\"required\":true}', 5, '2024-08-25 20:03:28', '2024-08-25 20:03:28'),
(39, 7, 'status', 'text', 'Status', 0, 1, 1, 1, 1, 1, '{\"required\":true}', 6, '2024-08-25 20:03:28', '2024-08-25 20:03:28'),
(40, 8, 'id', 'number', 'ID', 1, 0, 0, 0, 0, 0, '{}', 1, NULL, '2024-09-01 06:30:57'),
(41, 8, 'parent_id', 'select_dropdown', 'Parent', 0, 0, 1, 1, 1, 1, '{\"default\":\"\",\"null\":\"\",\"options\":{\"\":\"-- None --\"},\"relationship\":{\"key\":\"id\",\"label\":\"name\"}}', 2, NULL, NULL),
(42, 8, 'order', 'text', 'Order', 1, 1, 1, 1, 1, 1, '{\"default\":1}', 3, NULL, NULL),
(43, 8, 'name', 'text', 'Name', 1, 1, 1, 1, 1, 1, '{}', 4, NULL, '2024-09-01 06:30:57'),
(44, 8, 'slug', 'text', 'Slug', 1, 1, 1, 1, 1, 1, '{\"slugify\":{\"origin\":\"name\"}}', 5, NULL, NULL),
(45, 8, 'created_at', 'timestamp', 'Created At', 0, 0, 1, 0, 0, 0, '{}', 6, NULL, '2024-09-01 06:30:57'),
(46, 8, 'updated_at', 'timestamp', 'Updated At', 0, 0, 0, 0, 0, 0, '{}', 7, NULL, '2024-09-01 06:30:57'),
(47, 9, 'id', 'number', 'ID', 1, 0, 0, 0, 0, 0, NULL, 1, NULL, NULL),
(48, 9, 'author_id', 'text', 'Author', 1, 0, 1, 1, 0, 1, NULL, 2, NULL, NULL),
(49, 9, 'category_id', 'text', 'Category', 1, 0, 1, 1, 1, 0, NULL, 3, NULL, NULL),
(50, 9, 'title', 'text', 'Title', 1, 1, 1, 1, 1, 1, NULL, 4, NULL, NULL),
(51, 9, 'excerpt', 'text_area', 'Excerpt', 1, 0, 1, 1, 1, 1, NULL, 5, NULL, NULL),
(52, 9, 'body', 'rich_text_box', 'Body', 1, 0, 1, 1, 1, 1, NULL, 6, NULL, NULL),
(53, 9, 'image', 'image', 'Post Image', 0, 1, 1, 1, 1, 1, '{\"resize\":{\"width\":\"1000\",\"height\":\"null\"},\"quality\":\"70%\",\"upsize\":true,\"thumbnails\":[{\"name\":\"medium\",\"scale\":\"50%\"},{\"name\":\"small\",\"scale\":\"25%\"},{\"name\":\"cropped\",\"crop\":{\"width\":\"300\",\"height\":\"250\"}}]}', 7, NULL, NULL),
(54, 9, 'slug', 'text', 'Slug', 1, 0, 1, 1, 1, 1, '{\"slugify\":{\"origin\":\"title\",\"forceUpdate\":true},\"validation\":{\"rule\":\"unique:posts,slug\"}}', 8, NULL, NULL),
(55, 9, 'meta_description', 'text_area', 'Meta Description', 1, 0, 1, 1, 1, 1, NULL, 9, NULL, NULL),
(56, 9, 'meta_keywords', 'text_area', 'Meta Keywords', 1, 0, 1, 1, 1, 1, NULL, 10, NULL, NULL),
(57, 9, 'status', 'select_dropdown', 'Status', 1, 1, 1, 1, 1, 1, '{\"default\":\"DRAFT\",\"options\":{\"PUBLISHED\":\"published\",\"DRAFT\":\"draft\",\"PENDING\":\"pending\"}}', 11, NULL, NULL),
(58, 9, 'created_at', 'timestamp', 'Created At', 0, 1, 1, 0, 0, 0, NULL, 12, NULL, NULL),
(59, 9, 'updated_at', 'timestamp', 'Updated At', 0, 0, 0, 0, 0, 0, NULL, 13, NULL, NULL),
(60, 9, 'seo_title', 'text', 'SEO Title', 0, 1, 1, 1, 1, 1, NULL, 14, NULL, NULL),
(61, 9, 'featured', 'checkbox', 'Featured', 1, 1, 1, 1, 1, 1, NULL, 15, NULL, NULL),
(62, 10, 'id', 'number', 'ID', 1, 0, 0, 0, 0, 0, NULL, 1, NULL, NULL),
(63, 10, 'author_id', 'text', 'Author', 1, 0, 0, 0, 0, 0, NULL, 2, NULL, NULL),
(64, 10, 'title', 'text', 'Title', 1, 1, 1, 1, 1, 1, NULL, 3, NULL, NULL),
(65, 10, 'excerpt', 'text_area', 'Excerpt', 1, 0, 1, 1, 1, 1, NULL, 4, NULL, NULL),
(66, 10, 'body', 'rich_text_box', 'Body', 1, 0, 1, 1, 1, 1, NULL, 5, NULL, NULL),
(67, 10, 'slug', 'text', 'Slug', 1, 0, 1, 1, 1, 1, '{\"slugify\":{\"origin\":\"title\"},\"validation\":{\"rule\":\"unique:pages,slug\"}}', 6, NULL, NULL),
(68, 10, 'meta_description', 'text', 'Meta Description', 1, 0, 1, 1, 1, 1, NULL, 7, NULL, NULL),
(69, 10, 'meta_keywords', 'text', 'Meta Keywords', 1, 0, 1, 1, 1, 1, NULL, 8, NULL, NULL),
(70, 10, 'status', 'select_dropdown', 'Status', 1, 1, 1, 1, 1, 1, '{\"default\":\"INACTIVE\",\"options\":{\"INACTIVE\":\"INACTIVE\",\"ACTIVE\":\"ACTIVE\"}}', 9, NULL, NULL),
(71, 10, 'created_at', 'timestamp', 'Created At', 1, 1, 1, 0, 0, 0, NULL, 10, NULL, NULL),
(72, 10, 'updated_at', 'timestamp', 'Updated At', 1, 0, 0, 0, 0, 0, NULL, 11, NULL, NULL),
(73, 10, 'image', 'image', 'Page Image', 0, 1, 1, 1, 1, 1, NULL, 12, NULL, NULL),
(74, 11, 'id', 'text', 'Id', 1, 0, 0, 0, 0, 0, '{}', 1, NULL, NULL),
(114, 1113, 'id', 'text', 'Id', 1, 0, 0, 0, 0, 0, '{}', 1, NULL, NULL),
(109, 11, 'end_location', 'text', 'End Location', 0, 1, 1, 1, 1, 1, '{}', 9, NULL, NULL),
(110, 11, 'start_time', 'timestamp', 'Start Time', 0, 1, 1, 1, 1, 1, '{}', 10, NULL, NULL),
(111, 11, 'end_time', 'timestamp', 'End Time', 0, 1, 1, 1, 1, 1, '{}', 11, NULL, NULL),
(112, 11, 'fare', 'text', 'Fare', 0, 1, 1, 1, 1, 1, '{}', 12, NULL, NULL),
(113, 11, 'status', 'text', 'Status', 0, 1, 1, 1, 1, 1, '{}', 13, NULL, NULL),
(93, 11, 'id', 'number', 'ID', 1, 1, 1, 0, 0, 0, '{}', 1, '2024-09-01 06:29:58', '2024-09-01 06:29:58'),
(83, 11, 'created_at', 'timestamp', 'Created At', 0, 1, 1, 1, 0, 1, '{}', 10, NULL, NULL),
(84, 11, 'updated_at', 'timestamp', 'Updated At', 0, 0, 0, 0, 0, 0, '{}', 11, NULL, NULL),
(85, 11, 'trip_hasone_address_relationship', 'relationship', 'addresses', 0, 1, 1, 1, 1, 1, '{\"model\":\"App\\\\Models\\\\Address\",\"table\":\"addresses\",\"type\":\"hasOne\",\"column\":\"id\",\"key\":\"id\",\"label\":\"id\",\"pivot_table\":\"addresses\",\"pivot\":\"0\",\"taggable\":\"0\"}', 12, NULL, '2024-09-01 06:24:44'),
(86, 1, 'phone', 'text', 'Phone', 0, 1, 1, 1, 1, 1, '{}', 4, NULL, '2025-02-02 12:35:30'),
(87, 1, 'email_verified_at', 'timestamp', 'Email Verified At', 0, 1, 1, 1, 1, 1, '{}', 7, NULL, NULL),
(88, 1112, 'id', 'text', 'Id', 1, 0, 0, 0, 0, 0, '{}', 1, NULL, NULL),
(89, 1112, 'created_at', 'timestamp', 'Created At', 0, 1, 1, 1, 0, 1, '{}', 2, NULL, NULL),
(90, 1112, 'updated_at', 'timestamp', 'Updated At', 0, 0, 0, 0, 0, 0, '{}', 3, NULL, NULL),
(91, 1112, 'deleted_at', 'timestamp', 'Deleted At', 0, 1, 1, 1, 1, 1, '{}', 4, NULL, NULL),
(92, 11, 'deleted_at', 'timestamp', 'Deleted At', 0, 1, 1, 1, 1, 1, '{}', 4, NULL, NULL),
(108, 11, 'start_location', 'text', 'Start Location', 0, 1, 1, 1, 1, 1, '{}', 8, NULL, NULL),
(107, 11, 'vehicle_id', 'text', 'Vehicle Id', 0, 1, 1, 1, 1, 1, '{}', 7, NULL, NULL),
(105, 11, 'user_id', 'text', 'User Id', 0, 1, 1, 1, 1, 1, '{}', 5, NULL, NULL),
(106, 11, 'driver_id', 'text', 'Driver Id', 0, 1, 1, 1, 1, 1, '{}', 6, NULL, NULL),
(103, 11, 'created_at', 'timestamp', 'Created At', 0, 1, 1, 0, 0, 0, '{}', 11, '2024-09-01 06:29:58', '2024-09-01 06:29:58'),
(104, 11, 'updated_at', 'timestamp', 'Updated At', 0, 1, 1, 0, 0, 0, '{}', 12, '2024-09-01 06:29:58', '2024-09-01 06:29:58'),
(115, 1113, 'make', 'text', 'Make', 1, 1, 1, 1, 1, 1, '{}', 2, NULL, NULL),
(116, 1113, 'model', 'text', 'Model', 1, 1, 1, 1, 1, 1, '{}', 3, NULL, NULL),
(117, 1113, 'year', 'text', 'Year', 1, 1, 1, 1, 1, 1, '{}', 4, NULL, NULL),
(118, 1113, 'license_plate', 'text', 'License Plate', 1, 1, 1, 1, 1, 1, '{}', 5, NULL, NULL),
(119, 1113, 'color', 'text', 'Color', 0, 1, 1, 1, 1, 1, '{}', 6, NULL, NULL),
(120, 1113, 'deleted_at', 'timestamp', 'Deleted At', 0, 1, 1, 1, 1, 1, '{}', 7, NULL, NULL),
(121, 1113, 'created_at', 'timestamp', 'Created At', 0, 1, 1, 1, 0, 1, '{}', 8, NULL, NULL),
(122, 1113, 'updated_at', 'timestamp', 'Updated At', 0, 0, 0, 0, 0, 0, '{}', 9, NULL, NULL),
(127, 1114, 'review', 'text', 'Review', 0, 1, 1, 1, 1, 1, '{}', 5, NULL, NULL),
(128, 1114, 'created_at', 'timestamp', 'Created At', 0, 1, 1, 1, 0, 1, '{}', 6, NULL, NULL),
(129, 1114, 'updated_at', 'timestamp', 'Updated At', 0, 0, 0, 0, 0, 0, '{}', 7, NULL, NULL),
(130, 1114, 'deleted_at', 'timestamp', 'Deleted At', 0, 1, 1, 1, 1, 1, '{}', 8, NULL, NULL),
(131, 1115, 'id', 'text', 'Id', 1, 0, 0, 0, 0, 0, '{}', 0, NULL, '2025-02-04 18:36:16'),
(132, 1115, 'user_id', 'text', 'User Id', 0, 1, 1, 1, 1, 1, '{}', 2, NULL, NULL),
(133, 1115, 'address_line1', 'text', 'Address Line1', 0, 1, 1, 1, 1, 1, '{}', 3, NULL, '2025-02-04 18:36:16'),
(134, 1115, 'address_line2', 'text', 'Address Line2', 0, 1, 1, 1, 1, 1, '{}', 4, NULL, NULL),
(135, 1115, 'city', 'text', 'City', 0, 1, 1, 1, 1, 1, '{}', 5, NULL, '2025-02-04 18:36:16'),
(136, 1115, 'state', 'text', 'State', 1, 1, 1, 1, 1, 1, '{}', 6, NULL, NULL),
(137, 1115, 'postal_code', 'text', 'Postal Code', 1, 1, 1, 1, 1, 1, '{}', 7, NULL, NULL),
(138, 1115, 'country', 'text', 'Country', 1, 1, 1, 1, 1, 1, '{}', 8, NULL, NULL),
(139, 1115, 'latitude', 'text', 'Latitude', 0, 1, 1, 1, 1, 1, '{}', 9, NULL, NULL),
(140, 1115, 'longitude', 'text', 'Longitude', 0, 1, 1, 1, 1, 1, '{}', 10, NULL, NULL),
(141, 1115, 'created_at', 'timestamp', 'Created At', 0, 1, 1, 1, 0, 1, '{}', 11, NULL, NULL),
(142, 1115, 'updated_at', 'timestamp', 'Updated At', 0, 0, 0, 0, 0, 0, '{}', 12, NULL, NULL),
(143, 1115, 'deleted_at', 'timestamp', 'Deleted At', 0, 1, 1, 1, 1, 1, '{}', 13, NULL, NULL),
(144, 1116, 'id', 'text', 'Id', 1, 0, 0, 0, 0, 0, '{}', 1, NULL, NULL),
(145, 1116, 'user_id', 'text', 'User Id', 1, 1, 1, 1, 1, 1, '{}', 2, NULL, NULL),
(146, 1116, 'license_number', 'text', 'License Number', 1, 1, 1, 1, 1, 1, '{}', 3, NULL, NULL),
(147, 1116, 'vehicle_id', 'text', 'Vehicle Id', 0, 1, 1, 1, 1, 1, '{}', 4, NULL, NULL),
(148, 1116, 'rating', 'text', 'Rating', 1, 1, 1, 1, 1, 1, '{}', 5, NULL, NULL),
(149, 1116, 'created_at', 'timestamp', 'Created At', 0, 1, 1, 1, 0, 1, '{}', 6, NULL, NULL),
(150, 1116, 'updated_at', 'timestamp', 'Updated At', 0, 0, 0, 0, 0, 0, '{}', 7, NULL, NULL),
(151, 1117, 'id', 'text', 'Id', 1, 0, 0, 0, 0, 0, '{}', 1, NULL, NULL),
(152, 1117, 'user_id', 'text', 'User Id', 1, 1, 1, 1, 1, 1, '{}', 2, NULL, NULL),
(153, 1117, 'license_number', 'text', 'License Number', 1, 1, 1, 1, 1, 1, '{}', 3, NULL, NULL),
(154, 1117, 'vehicle_id', 'text', 'Vehicle Id', 0, 1, 1, 1, 1, 1, '{}', 4, NULL, NULL),
(155, 1117, 'rating', 'text', 'Rating', 1, 1, 1, 1, 1, 1, '{}', 5, NULL, NULL),
(156, 1117, 'created_at', 'timestamp', 'Created At', 0, 1, 1, 1, 0, 1, '{}', 6, NULL, NULL),
(157, 1117, 'updated_at', 'timestamp', 'Updated At', 0, 0, 0, 0, 0, 0, '{}', 7, NULL, NULL),
(158, 1117, 'deleted_at', 'timestamp', 'Deleted At', 0, 1, 1, 1, 1, 1, '{}', 8, NULL, NULL),
(159, 1118, 'id', 'text', 'Id', 1, 0, 0, 0, 0, 0, '{}', 1, NULL, NULL),
(160, 1118, 'user_id', 'text', 'User Id', 1, 1, 1, 1, 1, 1, '{}', 2, NULL, NULL),
(161, 1118, 'amount', 'text', 'Amount', 1, 1, 1, 1, 1, 1, '{}', 3, NULL, NULL),
(162, 1118, 'payment_method', 'text', 'Payment Method', 1, 1, 1, 1, 1, 1, '{}', 4, NULL, NULL),
(163, 1118, 'created_at', 'timestamp', 'Created At', 0, 1, 1, 1, 0, 1, '{}', 5, NULL, NULL),
(164, 1118, 'updated_at', 'timestamp', 'Updated At', 0, 0, 0, 0, 0, 0, '{}', 6, NULL, NULL),
(165, 1118, 'deleted_at', 'timestamp', 'Deleted At', 0, 1, 1, 1, 1, 1, '{}', 7, NULL, NULL),
(166, 1119, 'id', 'text', 'Id', 1, 0, 0, 0, 0, 0, '{}', 0, NULL, '2025-02-04 18:45:59'),
(167, 1119, 'type', 'text', 'Type', 0, 1, 1, 1, 1, 1, '{}', 2, NULL, NULL),
(168, 1119, 'created_at', 'timestamp', 'Created At', 0, 1, 1, 1, 0, 1, '{}', 3, NULL, NULL),
(169, 1119, 'updated_at', 'timestamp', 'Updated At', 0, 0, 0, 0, 0, 0, '{}', 4, NULL, NULL),
(170, 1119, 'deleted_at', 'timestamp', 'Deleted At', 0, 0, 0, 0, 0, 0, '{}', 5, NULL, NULL),
(171, 1119, 'day', 'text', 'Day', 0, 1, 1, 1, 1, 1, '{}', 6, NULL, NULL),
(172, 1119, 'time_from', 'time', 'Time From', 0, 1, 1, 1, 1, 1, '{}', 7, NULL, NULL),
(173, 1119, 'time_to', 'time', 'Time To', 0, 1, 1, 1, 1, 1, '{}', 8, NULL, NULL),
(174, 1119, 'schedule_belongsto_address_relationship', 'relationship', 'addresses', 0, 1, 1, 1, 1, 1, '{\"model\":\"App\\\\Models\\\\Address\",\"table\":\"addresses\",\"type\":\"belongsTo\",\"column\":\"address_id\",\"key\":\"id\",\"label\":\"address_line1\",\"pivot_table\":\"addresses\",\"pivot\":\"0\",\"taggable\":\"0\"}', 9, NULL, '2025-02-02 06:20:37'),
(175, 1119, 'address_id', 'text', 'Address Id', 0, 1, 1, 1, 1, 1, '{}', 9, NULL, NULL),
(176, 1119, 'date', 'text', 'Date', 0, 1, 1, 1, 1, 1, '{}', 10, NULL, NULL),
(177, 1119, 'start_address', 'text', 'Start Address', 0, 1, 1, 1, 1, 1, '{}', 11, NULL, NULL),
(178, 1119, 'destination_address', 'text', 'Destination Address', 0, 1, 1, 1, 1, 1, '{}', 12, NULL, NULL),
(179, 1119, 'user_id', 'text', 'User Id', 0, 1, 1, 1, 1, 1, '{}', 13, NULL, NULL),
(180, 1, 'api_token', 'text', 'Api Token', 0, 1, 1, 1, 1, 1, '{}', 13, NULL, NULL),
(181, 1, 'type', 'text', 'Type', 0, 1, 1, 1, 1, 1, '{}', 14, NULL, NULL),
(182, 1120, 'id', 'text', 'Id', 1, 0, 0, 0, 0, 0, '{}', 1, NULL, NULL),
(183, 1120, 'user_id', 'text', 'User Id', 0, 1, 1, 1, 1, 1, '{}', 2, NULL, NULL),
(184, 1120, 'created_at', 'timestamp', 'Created At', 0, 1, 1, 1, 0, 1, '{}', 3, NULL, NULL),
(185, 1120, 'updated_at', 'timestamp', 'Updated At', 0, 0, 0, 0, 0, 0, '{}', 4, NULL, NULL),
(186, 1120, 'deleted_at', 'timestamp', 'Deleted At', 0, 1, 1, 1, 1, 1, '{}', 5, NULL, NULL),
(187, 1120, 'description', 'text_area', 'Description', 0, 1, 1, 1, 1, 1, '{}', 6, NULL, NULL),
(188, 1120, 'order_belongsto_user_relationship', 'relationship', 'users', 0, 1, 1, 1, 1, 1, '{\"model\":\"App\\\\Models\\\\User\",\"table\":\"users\",\"type\":\"belongsTo\",\"column\":\"user_id\",\"key\":\"id\",\"label\":\"name\",\"pivot_table\":\"addresses\",\"pivot\":\"0\",\"taggable\":\"0\"}', 7, NULL, '2025-02-04 18:34:11'),
(189, 1115, 'address_belongsto_order_relationship', 'relationship', 'orders', 0, 1, 1, 1, 1, 1, '{\"model\":\"App\\\\Order\",\"table\":\"orders\",\"type\":\"belongsTo\",\"column\":\"order_id\",\"key\":\"id\",\"label\":\"id\",\"pivot_table\":\"addresses\",\"pivot\":\"0\",\"taggable\":\"0\"}', 14, NULL, '2025-02-04 18:36:16'),
(190, 1115, 'order_id', 'text', 'Order Id', 0, 1, 1, 1, 1, 1, '{}', 14, NULL, NULL),
(191, 1119, 'schedule_belongsto_order_relationship', 'relationship', 'orders', 0, 1, 1, 1, 1, 1, '{\"model\":\"App\\\\Order\",\"table\":\"orders\",\"type\":\"belongsTo\",\"column\":\"order_id\",\"key\":\"id\",\"label\":\"id\",\"pivot_table\":\"addresses\",\"pivot\":\"0\",\"taggable\":\"0\"}', 14, NULL, '2025-02-04 18:45:59'),
(192, 1119, 'order_id', 'text', 'Order Id', 0, 1, 1, 1, 1, 1, '{}', 14, NULL, NULL),
(193, 1115, 'direction', 'text', 'Direction', 0, 1, 1, 1, 1, 1, '{}', 15, NULL, NULL),
(194, 1120, 'status', 'text', 'Status', 0, 1, 1, 1, 1, 1, '{}', 7, NULL, NULL),
(195, 1121, 'id', 'text', 'Id', 1, 0, 0, 0, 0, 0, '{}', 1, NULL, NULL),
(196, 1121, 'code', 'text', 'Code', 0, 1, 1, 1, 1, 1, '{}', 2, NULL, NULL),
(197, 1121, 'created_at', 'timestamp', 'Created At', 0, 1, 1, 1, 0, 1, '{}', 3, NULL, NULL),
(198, 1121, 'updated_at', 'timestamp', 'Updated At', 0, 0, 0, 0, 0, 0, '{}', 4, NULL, NULL),
(199, 1121, 'deleted_at', 'timestamp', 'Deleted At', 0, 1, 1, 1, 1, 1, '{}', 5, NULL, NULL),
(200, 1121, 'user_id', 'text', 'User Id', 0, 1, 1, 1, 1, 1, '{}', 6, NULL, NULL),
(201, 1121, 'type', 'text', 'Type', 0, 1, 1, 1, 1, 1, '{}', 7, NULL, NULL),
(202, 1121, 'expires_at', 'timestamp', 'Expires At', 0, 1, 1, 1, 1, 1, '{}', 8, NULL, NULL),
(203, 1121, 'used', 'text', 'Used', 0, 1, 1, 1, 1, 1, '{}', 9, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `data_types`
--

DROP TABLE IF EXISTS `data_types`;
CREATE TABLE IF NOT EXISTS `data_types` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name_singular` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name_plural` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `policy_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `controller` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `generate_permissions` tinyint(1) NOT NULL DEFAULT '0',
  `server_side` tinyint NOT NULL DEFAULT '0',
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `data_types_name_unique` (`name`),
  UNIQUE KEY `data_types_slug_unique` (`slug`)
) ENGINE=MyISAM AUTO_INCREMENT=1122 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `data_types`
--

INSERT INTO `data_types` (`id`, `name`, `slug`, `display_name_singular`, `display_name_plural`, `icon`, `model_name`, `policy_name`, `controller`, `description`, `generate_permissions`, `server_side`, `details`, `created_at`, `updated_at`) VALUES
(1, 'users', 'users', 'User', 'Users', 'voyager-person', 'TCG\\Voyager\\Models\\User', 'TCG\\Voyager\\Policies\\UserPolicy', 'TCG\\Voyager\\Http\\Controllers\\VoyagerUserController', NULL, 1, 0, '{\"order_column\":null,\"order_display_column\":null,\"order_direction\":\"desc\",\"default_search_key\":null,\"scope\":null}', '2024-08-25 15:07:33', '2025-02-02 10:35:30'),
(2, 'menus', 'menus', 'Menu', 'Menus', 'voyager-list', 'TCG\\Voyager\\Models\\Menu', NULL, '', '', 1, 0, NULL, '2024-08-25 15:07:33', '2024-08-25 15:07:33'),
(3, 'roles', 'roles', 'Role', 'Roles', 'voyager-lock', 'TCG\\Voyager\\Models\\Role', NULL, 'TCG\\Voyager\\Http\\Controllers\\VoyagerRoleController', '', 1, 0, NULL, '2024-08-25 15:07:33', '2024-08-25 15:07:33'),
(1115, 'addresses', 'addresses', 'Address', 'Addresses', NULL, 'App\\Address', NULL, NULL, NULL, 1, 0, '{\"order_column\":null,\"order_display_column\":null,\"order_direction\":\"asc\",\"default_search_key\":null,\"scope\":null}', '2024-09-01 03:48:48', '2025-02-07 05:15:46'),
(1114, 'ratings', 'ratings', 'Rating', 'Ratings', NULL, 'App\\Rating', NULL, NULL, NULL, 1, 0, '{\"order_column\":null,\"order_display_column\":null,\"order_direction\":\"asc\",\"default_search_key\":null}', '2024-09-01 03:42:35', '2024-09-01 03:42:35'),
(8, 'categories', 'categories', 'Category', 'Categories', 'voyager-categories', 'TCG\\Voyager\\Models\\Category', NULL, NULL, NULL, 1, 0, '{\"order_column\":null,\"order_display_column\":null,\"order_direction\":\"desc\",\"default_search_key\":null,\"scope\":null}', '2024-08-25 17:07:06', '2024-09-01 03:30:57'),
(9, 'posts', 'posts', 'Post', 'Posts', 'voyager-news', 'TCG\\Voyager\\Models\\Post', 'TCG\\Voyager\\Policies\\PostPolicy', '', '', 1, 0, NULL, '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(10, 'pages', 'pages', 'Page', 'Pages', 'voyager-file-text', 'TCG\\Voyager\\Models\\Page', NULL, '', '', 1, 0, NULL, '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(1113, 'vehicles', 'vehicles', 'Vehicle', 'Vehicles', NULL, 'App\\Vehicle', NULL, NULL, NULL, 1, 0, '{\"order_column\":null,\"order_display_column\":null,\"order_direction\":\"asc\",\"default_search_key\":null}', '2024-09-01 03:39:18', '2024-09-01 03:39:18'),
(11, 'trips', 'trips', 'Trip', 'Trips', NULL, 'App\\Trip', NULL, NULL, NULL, 1, 0, '{\"order_column\":null,\"order_display_column\":null,\"order_direction\":\"asc\",\"default_search_key\":null,\"scope\":null}', '2024-09-01 03:22:50', '2024-09-01 03:35:02'),
(1117, 'drivers', 'drivers', 'Driver', 'Drivers', NULL, 'App\\Driver', NULL, NULL, NULL, 1, 0, '{\"order_column\":null,\"order_display_column\":null,\"order_direction\":\"asc\",\"default_search_key\":null}', '2024-09-01 04:51:59', '2024-09-01 04:51:59'),
(1118, 'payments', 'payments', 'Payment', 'Payments', NULL, 'App\\Payment', NULL, NULL, NULL, 1, 0, '{\"order_column\":null,\"order_display_column\":null,\"order_direction\":\"asc\",\"default_search_key\":null}', '2024-09-01 04:54:42', '2024-09-01 04:54:42'),
(1119, 'schedules', 'schedules', 'Schedule', 'Schedules', NULL, 'App\\Schedule', NULL, NULL, NULL, 1, 0, '{\"order_column\":null,\"order_display_column\":null,\"order_direction\":\"asc\",\"default_search_key\":null,\"scope\":null}', '2025-02-01 16:47:27', '2025-02-04 16:45:59'),
(1120, 'orders', 'orders', 'Order', 'Orders', NULL, 'App\\Order', NULL, NULL, NULL, 1, 0, '{\"order_column\":null,\"order_display_column\":null,\"order_direction\":\"asc\",\"default_search_key\":null,\"scope\":null}', '2025-02-04 16:30:57', '2025-02-07 06:41:16'),
(1121, 'verification_codes', 'verification-codes', 'Verification Code', 'Verification Codes', NULL, 'App\\VerificationCode', NULL, NULL, NULL, 1, 0, '{\"order_column\":null,\"order_display_column\":null,\"order_direction\":\"asc\",\"default_search_key\":null}', '2025-02-10 18:33:32', '2025-02-10 18:33:32');

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

DROP TABLE IF EXISTS `drivers`;
CREATE TABLE IF NOT EXISTS `drivers` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `license_number` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vehicle_id` bigint UNSIGNED DEFAULT NULL,
  `rating` decimal(10,0) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `drivers_license_number_unique` (`license_number`),
  KEY `drivers_user_id_index` (`user_id`),
  KEY `drivers_vehicle_id_index` (`vehicle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `menus`
--

DROP TABLE IF EXISTS `menus`;
CREATE TABLE IF NOT EXISTS `menus` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `menus_name_unique` (`name`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `menus`
--

INSERT INTO `menus` (`id`, `name`, `created_at`, `updated_at`) VALUES
(1, 'admin', '2024-08-25 15:07:33', '2024-08-25 15:07:33');

-- --------------------------------------------------------

--
-- Table structure for table `menu_items`
--

DROP TABLE IF EXISTS `menu_items`;
CREATE TABLE IF NOT EXISTS `menu_items` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `menu_id` int UNSIGNED DEFAULT NULL,
  `title` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `target` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '_self',
  `icon_class` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_id` int DEFAULT NULL,
  `order` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `route` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parameters` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `menu_items_menu_id_foreign` (`menu_id`)
) ENGINE=MyISAM AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `menu_items`
--

INSERT INTO `menu_items` (`id`, `menu_id`, `title`, `url`, `target`, `icon_class`, `color`, `parent_id`, `order`, `created_at`, `updated_at`, `route`, `parameters`) VALUES
(1, 1, 'Dashboard', '', '_self', 'voyager-boat', NULL, NULL, 1, '2024-08-25 15:07:33', '2024-08-25 15:07:33', 'voyager.dashboard', NULL),
(2, 1, 'Media', '', '_self', 'voyager-images', NULL, NULL, 4, '2024-08-25 15:07:33', '2024-09-01 02:27:48', 'voyager.media.index', NULL),
(3, 1, 'Users', '', '_self', 'voyager-person', NULL, NULL, 3, '2024-08-25 15:07:33', '2024-08-25 15:07:33', 'voyager.users.index', NULL),
(4, 1, 'Roles', '', '_self', 'voyager-lock', NULL, NULL, 2, '2024-08-25 15:07:33', '2024-08-25 15:07:33', 'voyager.roles.index', NULL),
(5, 1, 'Tools', '', '_self', 'voyager-tools', NULL, NULL, 8, '2024-08-25 15:07:33', '2024-09-01 02:27:48', NULL, NULL),
(6, 1, 'Menu Builder', '', '_self', 'voyager-list', NULL, 5, 1, '2024-08-25 15:07:33', '2024-09-01 02:27:48', 'voyager.menus.index', NULL),
(7, 1, 'Database', '', '_self', 'voyager-data', NULL, 5, 2, '2024-08-25 15:07:33', '2024-09-01 02:27:48', 'voyager.database.index', NULL),
(8, 1, 'Compass', '', '_self', 'voyager-compass', NULL, 5, 3, '2024-08-25 15:07:33', '2024-09-01 02:27:48', 'voyager.compass.index', NULL),
(9, 1, 'BREAD', '', '_self', 'voyager-bread', NULL, 5, 4, '2024-08-25 15:07:33', '2024-09-01 02:27:48', 'voyager.bread.index', NULL),
(10, 1, 'Settings', '', '_self', 'voyager-settings', '#000000', NULL, 10, '2024-08-25 15:07:33', '2024-09-01 02:27:48', 'voyager.settings.index', 'null'),
(11, 1, 'Categories', '', '_self', 'voyager-categories', '#000000', NULL, 7, '2024-08-25 17:07:06', '2024-09-01 02:27:48', 'voyager.categories.index', 'null'),
(12, 1, 'Posts', '', '_self', 'voyager-news', NULL, NULL, 5, '2024-08-25 17:07:06', '2024-09-01 02:27:48', 'voyager.posts.index', NULL),
(13, 1, 'Pages', '', '_self', 'voyager-file-text', NULL, NULL, 6, '2024-08-25 17:07:06', '2024-09-01 02:27:48', 'voyager.pages.index', NULL),
(16, 1, 'Trips', '', '_self', NULL, NULL, NULL, 11, '2024-09-01 03:22:50', '2024-09-01 03:22:50', 'voyager.trips.index', NULL),
(17, 1, 'Vehicles', '', '_self', NULL, NULL, NULL, 12, '2024-09-01 03:39:18', '2024-09-01 03:39:18', 'voyager.vehicles.index', NULL),
(18, 1, 'Ratings', '', '_self', NULL, NULL, NULL, 13, '2024-09-01 03:42:35', '2024-09-01 03:42:35', 'voyager.ratings.index', NULL),
(19, 1, 'Addresses', '', '_self', NULL, NULL, NULL, 14, '2024-09-01 03:48:48', '2024-09-01 03:48:48', 'voyager.addresses.index', NULL),
(20, 1, 'Drivers', '', '_self', NULL, NULL, NULL, 15, '2024-09-01 04:51:59', '2024-09-01 04:51:59', 'voyager.drivers.index', NULL),
(21, 1, 'Payments', '', '_self', NULL, NULL, NULL, 16, '2024-09-01 04:54:42', '2024-09-01 04:54:42', 'voyager.payments.index', NULL),
(22, 1, 'Schedules', '', '_self', NULL, NULL, NULL, 17, '2025-02-01 16:47:27', '2025-02-01 16:47:27', 'voyager.schedules.index', NULL),
(23, 1, 'Orders', '', '_self', NULL, NULL, NULL, 18, '2025-02-04 16:30:57', '2025-02-04 16:30:57', 'voyager.orders.index', NULL),
(24, 1, 'Verification Codes', '', '_self', NULL, NULL, NULL, 19, '2025-02-10 18:33:32', '2025-02-10 18:33:32', 'voyager.verification-codes.index', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(5, '2014_10_12_000000_create_users_table', 1),
(6, '2014_10_12_100000_create_password_reset_tokens_table', 1),
(7, '2019_08_19_000000_create_failed_jobs_table', 1),
(8, '2019_12_14_000001_create_personal_access_tokens_table', 1),
(9, '2016_01_01_000000_add_voyager_user_fields', 2),
(10, '2016_01_01_000000_create_data_types_table', 2),
(11, '2016_05_19_173453_create_menu_table', 2),
(12, '2016_10_21_190000_create_roles_table', 2),
(13, '2016_10_21_190000_create_settings_table', 2),
(46, '2016_11_30_141208_create_permission_role_table', 15),
(45, '2016_11_30_135954_create_permission_table', 14),
(16, '2016_12_26_201236_data_types__add__server_side', 2),
(17, '2017_01_13_000000_add_route_to_menu_items_table', 2),
(18, '2017_01_14_005015_create_translations_table', 3),
(19, '2017_01_15_000000_make_table_name_nullable_in_permissions_table', 3),
(20, '2017_03_06_000000_add_controller_to_data_types_table', 3),
(21, '2017_04_21_000000_add_order_to_data_rows_table', 3),
(22, '2017_07_05_210000_add_policyname_to_data_types_table', 3),
(23, '2017_08_05_000000_add_group_to_settings_table', 3),
(24, '2017_11_26_013050_add_user_role_relationship', 3),
(25, '2017_11_26_015000_create_user_roles_table', 3),
(26, '2018_03_11_000000_add_user_settings', 3),
(27, '2018_03_14_000000_add_details_to_data_types_table', 3),
(28, '2018_03_16_000000_make_settings_value_nullable', 3),
(29, '2024_08_25_184706_create_addresses_table', 4),
(30, '2024_08_25_185230_create_trips_table', 5),
(31, '2024_08_25_191302_create_vehicles_table', 6),
(32, '2024_08_25_191304_create_payments_table', 6),
(33, '2024_08_25_191433_create_vehicles_table', 7),
(34, '2024_08_25_191446_create_payments_table', 8),
(35, '2024_08_25_194553_create_ratings_table', 9),
(36, '2024_08_25_194557_create_notifications_table', 9),
(37, '2016_01_01_000000_create_pages_table', 10),
(38, '2016_01_01_000000_create_posts_table', 10),
(39, '2016_02_15_204651_create_categories_table', 10),
(40, '2017_04_11_000000_alter_post_nullable_fields_table', 10),
(41, '2024_08_25_194558_create_drivers_table', 11),
(42, '2024_09_01_095010_add_api_token_to_users_table', 12),
(43, '2024_09_06_052107_update_translations_table', 13),
(47, '2024_09_07_103723_create_permission_tables', 15);

-- --------------------------------------------------------

--
-- Table structure for table `model_has_permissions`
--

DROP TABLE IF EXISTS `model_has_permissions`;
CREATE TABLE IF NOT EXISTS `model_has_permissions` (
  `permission_id` bigint UNSIGNED NOT NULL,
  `model_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint UNSIGNED NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `model_has_roles`
--

DROP TABLE IF EXISTS `model_has_roles`;
CREATE TABLE IF NOT EXISTS `model_has_roles` (
  `role_id` bigint UNSIGNED NOT NULL,
  `model_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `title` varchar(181) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `description` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `created_at`, `updated_at`, `deleted_at`, `description`, `status`) VALUES
(1, 2, '2025-02-07 06:56:00', '2025-02-07 06:56:00', NULL, '', 'waiting_driver_response'),
(2, 2, '2025-02-07 07:04:20', '2025-02-07 07:04:20', NULL, '', 'waiting_driver_response'),
(3, 8, '2025-02-09 15:05:19', '2025-02-09 15:05:19', NULL, '', 'waiting_driver_response'),
(4, 1, '2025-02-15 11:01:46', '2025-02-15 11:01:46', NULL, '', 'waiting_driver_response'),
(5, 2, '2025-02-15 16:04:18', '2025-02-15 16:04:18', NULL, '', 'waiting_driver_response'),
(6, 2, '2025-02-16 05:15:20', '2025-02-16 05:15:20', NULL, '', 'waiting_driver_response'),
(7, 2, '2025-02-16 07:07:58', '2025-02-16 07:07:58', NULL, '', 'waiting_driver_response');

-- --------------------------------------------------------

--
-- Table structure for table `pages`
--

DROP TABLE IF EXISTS `pages`;
CREATE TABLE IF NOT EXISTS `pages` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `author_id` int NOT NULL,
  `title` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `excerpt` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `image` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `meta_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `meta_keywords` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('ACTIVE','INACTIVE') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INACTIVE',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pages_slug_unique` (`slug`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pages`
--

INSERT INTO `pages` (`id`, `author_id`, `title`, `excerpt`, `body`, `image`, `slug`, `meta_description`, `meta_keywords`, `status`, `created_at`, `updated_at`) VALUES
(1, 0, 'Hello World', 'Hang the jib grog grog blossom grapple dance the hempen jig gangway pressgang bilge rat to go on account lugger. Nelsons folly gabion line draught scallywag fire ship gaff fluke fathom case shot. Sea Legs bilge rat sloop matey gabion long clothes run a shot across the bow Gold Road cog league.', '<p>Hello World. Scallywag grog swab Cat o\'nine tails scuttle rigging hardtack cable nipper Yellow Jack. Handsomely spirits knave lad killick landlubber or just lubber deadlights chantey pinnace crack Jennys tea cup. Provost long clothes black spot Yellow Jack bilged on her anchor league lateen sail case shot lee tackle.</p>\n<p>Ballast spirits fluke topmast me quarterdeck schooner landlubber or just lubber gabion belaying pin. Pinnace stern galleon starboard warp carouser to go on account dance the hempen jig jolly boat measured fer yer chains. Man-of-war fire in the hole nipperkin handsomely doubloon barkadeer Brethren of the Coast gibbet driver squiffy.</p>', 'pages/page1.jpg', 'hello-world', 'Yar Meta Description', 'Keyword1, Keyword2', 'ACTIVE', '2024-08-25 17:07:06', '2024-08-25 17:07:06');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
CREATE TABLE IF NOT EXISTS `payments` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `amount` decimal(10,0) NOT NULL,
  `payment_method` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payments_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `table_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `permissions_key_index` (`key`)
) ENGINE=MyISAM AUTO_INCREMENT=91 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `key`, `table_name`, `created_at`, `updated_at`) VALUES
(1, 'browse_admin', NULL, '2024-08-25 15:07:33', '2024-08-25 15:07:33'),
(2, 'browse_bread', NULL, '2024-08-25 15:07:33', '2024-08-25 15:07:33'),
(3, 'browse_database', NULL, '2024-08-25 15:07:33', '2024-08-25 15:07:33'),
(4, 'browse_media', NULL, '2024-08-25 15:07:33', '2024-08-25 15:07:33'),
(5, 'browse_compass', NULL, '2024-08-25 15:07:33', '2024-08-25 15:07:33'),
(6, 'browse_menus', 'menus', '2024-08-25 15:07:33', '2024-08-25 15:07:33'),
(7, 'read_menus', 'menus', '2024-08-25 15:07:33', '2024-08-25 15:07:33'),
(8, 'edit_menus', 'menus', '2024-08-25 15:07:33', '2024-08-25 15:07:33'),
(9, 'add_menus', 'menus', '2024-08-25 15:07:33', '2024-08-25 15:07:33'),
(10, 'delete_menus', 'menus', '2024-08-25 15:07:33', '2024-08-25 15:07:33'),
(11, 'browse_roles', 'roles', '2024-08-25 15:07:33', '2024-08-25 15:07:33'),
(12, 'read_roles', 'roles', '2024-08-25 15:07:33', '2024-08-25 15:07:33'),
(13, 'edit_roles', 'roles', '2024-08-25 15:07:33', '2024-08-25 15:07:33'),
(14, 'add_roles', 'roles', '2024-08-25 15:07:33', '2024-08-25 15:07:33'),
(15, 'delete_roles', 'roles', '2024-08-25 15:07:33', '2024-08-25 15:07:33'),
(16, 'browse_users', 'users', '2024-08-25 15:07:33', '2024-08-25 15:07:33'),
(17, 'read_users', 'users', '2024-08-25 15:07:33', '2024-08-25 15:07:33'),
(18, 'edit_users', 'users', '2024-08-25 15:07:33', '2024-08-25 15:07:33'),
(19, 'add_users', 'users', '2024-08-25 15:07:33', '2024-08-25 15:07:33'),
(20, 'delete_users', 'users', '2024-08-25 15:07:33', '2024-08-25 15:07:33'),
(21, 'browse_settings', 'settings', '2024-08-25 15:07:33', '2024-08-25 15:07:33'),
(22, 'read_settings', 'settings', '2024-08-25 15:07:33', '2024-08-25 15:07:33'),
(23, 'edit_settings', 'settings', '2024-08-25 15:07:33', '2024-08-25 15:07:33'),
(24, 'add_settings', 'settings', '2024-08-25 15:07:33', '2024-08-25 15:07:33'),
(25, 'delete_settings', 'settings', '2024-08-25 15:07:33', '2024-08-25 15:07:33'),
(26, 'browse_categories', 'categories', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(27, 'read_categories', 'categories', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(28, 'edit_categories', 'categories', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(29, 'add_categories', 'categories', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(30, 'delete_categories', 'categories', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(31, 'browse_posts', 'posts', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(32, 'read_posts', 'posts', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(33, 'edit_posts', 'posts', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(34, 'add_posts', 'posts', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(35, 'delete_posts', 'posts', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(36, 'browse_pages', 'pages', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(37, 'read_pages', 'pages', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(38, 'edit_pages', 'pages', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(39, 'add_pages', 'pages', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(40, 'delete_pages', 'pages', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(41, 'browse_trips', 'trips', '2024-08-27 11:00:55', '2024-08-27 11:00:55'),
(42, 'read_trips', 'trips', '2024-08-27 11:00:55', '2024-08-27 11:00:55'),
(43, 'edit_trips', 'trips', '2024-08-27 11:00:55', '2024-08-27 11:00:55'),
(44, 'add_trips', 'trips', '2024-08-27 11:00:55', '2024-08-27 11:00:55'),
(45, 'delete_trips', 'trips', '2024-08-27 11:00:55', '2024-08-27 11:00:55'),
(46, 'browse_vehicles', 'vehicles', '2024-09-01 03:39:18', '2024-09-01 03:39:18'),
(47, 'read_vehicles', 'vehicles', '2024-09-01 03:39:18', '2024-09-01 03:39:18'),
(48, 'edit_vehicles', 'vehicles', '2024-09-01 03:39:18', '2024-09-01 03:39:18'),
(49, 'add_vehicles', 'vehicles', '2024-09-01 03:39:18', '2024-09-01 03:39:18'),
(50, 'delete_vehicles', 'vehicles', '2024-09-01 03:39:18', '2024-09-01 03:39:18'),
(51, 'browse_ratings', 'ratings', '2024-09-01 03:42:35', '2024-09-01 03:42:35'),
(52, 'read_ratings', 'ratings', '2024-09-01 03:42:35', '2024-09-01 03:42:35'),
(53, 'edit_ratings', 'ratings', '2024-09-01 03:42:35', '2024-09-01 03:42:35'),
(54, 'add_ratings', 'ratings', '2024-09-01 03:42:35', '2024-09-01 03:42:35'),
(55, 'delete_ratings', 'ratings', '2024-09-01 03:42:35', '2024-09-01 03:42:35'),
(56, 'browse_addresses', 'addresses', '2024-09-01 03:48:48', '2024-09-01 03:48:48'),
(57, 'read_addresses', 'addresses', '2024-09-01 03:48:48', '2024-09-01 03:48:48'),
(58, 'edit_addresses', 'addresses', '2024-09-01 03:48:48', '2024-09-01 03:48:48'),
(59, 'add_addresses', 'addresses', '2024-09-01 03:48:48', '2024-09-01 03:48:48'),
(60, 'delete_addresses', 'addresses', '2024-09-01 03:48:48', '2024-09-01 03:48:48'),
(70, 'delete_drivers', 'drivers', '2024-09-01 04:51:59', '2024-09-01 04:51:59'),
(69, 'add_drivers', 'drivers', '2024-09-01 04:51:59', '2024-09-01 04:51:59'),
(68, 'edit_drivers', 'drivers', '2024-09-01 04:51:59', '2024-09-01 04:51:59'),
(67, 'read_drivers', 'drivers', '2024-09-01 04:51:59', '2024-09-01 04:51:59'),
(66, 'browse_drivers', 'drivers', '2024-09-01 04:51:59', '2024-09-01 04:51:59'),
(71, 'browse_payments', 'payments', '2024-09-01 04:54:42', '2024-09-01 04:54:42'),
(72, 'read_payments', 'payments', '2024-09-01 04:54:42', '2024-09-01 04:54:42'),
(73, 'edit_payments', 'payments', '2024-09-01 04:54:42', '2024-09-01 04:54:42'),
(74, 'add_payments', 'payments', '2024-09-01 04:54:42', '2024-09-01 04:54:42'),
(75, 'delete_payments', 'payments', '2024-09-01 04:54:42', '2024-09-01 04:54:42'),
(76, 'browse_schedules', 'schedules', '2025-02-01 16:47:27', '2025-02-01 16:47:27'),
(77, 'read_schedules', 'schedules', '2025-02-01 16:47:27', '2025-02-01 16:47:27'),
(78, 'edit_schedules', 'schedules', '2025-02-01 16:47:27', '2025-02-01 16:47:27'),
(79, 'add_schedules', 'schedules', '2025-02-01 16:47:27', '2025-02-01 16:47:27'),
(80, 'delete_schedules', 'schedules', '2025-02-01 16:47:27', '2025-02-01 16:47:27'),
(81, 'browse_orders', 'orders', '2025-02-04 16:30:57', '2025-02-04 16:30:57'),
(82, 'read_orders', 'orders', '2025-02-04 16:30:57', '2025-02-04 16:30:57'),
(83, 'edit_orders', 'orders', '2025-02-04 16:30:57', '2025-02-04 16:30:57'),
(84, 'add_orders', 'orders', '2025-02-04 16:30:57', '2025-02-04 16:30:57'),
(85, 'delete_orders', 'orders', '2025-02-04 16:30:57', '2025-02-04 16:30:57'),
(86, 'browse_verification_codes', 'verification_codes', '2025-02-10 18:33:32', '2025-02-10 18:33:32'),
(87, 'read_verification_codes', 'verification_codes', '2025-02-10 18:33:32', '2025-02-10 18:33:32'),
(88, 'edit_verification_codes', 'verification_codes', '2025-02-10 18:33:32', '2025-02-10 18:33:32'),
(89, 'add_verification_codes', 'verification_codes', '2025-02-10 18:33:32', '2025-02-10 18:33:32'),
(90, 'delete_verification_codes', 'verification_codes', '2025-02-10 18:33:32', '2025-02-10 18:33:32');

-- --------------------------------------------------------

--
-- Table structure for table `permission_role`
--

DROP TABLE IF EXISTS `permission_role`;
CREATE TABLE IF NOT EXISTS `permission_role` (
  `permission_id` bigint UNSIGNED NOT NULL,
  `role_id` bigint UNSIGNED NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `permission_role_permission_id_index` (`permission_id`),
  KEY `permission_role_role_id_index` (`role_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permission_role`
--

INSERT INTO `permission_role` (`permission_id`, `role_id`) VALUES
(1, 1),
(2, 1),
(3, 1),
(4, 1),
(5, 1),
(6, 1),
(7, 1),
(8, 1),
(9, 1),
(10, 1),
(11, 1),
(12, 1),
(13, 1),
(14, 1),
(15, 1),
(16, 1),
(17, 1),
(18, 1),
(19, 1),
(20, 1),
(21, 1),
(22, 1),
(23, 1),
(24, 1),
(25, 1),
(26, 1),
(27, 1),
(28, 1),
(29, 1),
(30, 1),
(31, 1),
(32, 1),
(33, 1),
(34, 1),
(35, 1),
(36, 1),
(37, 1),
(38, 1),
(39, 1),
(40, 1),
(41, 1),
(42, 1),
(43, 1),
(44, 1),
(45, 1),
(46, 1),
(47, 1),
(48, 1),
(49, 1),
(50, 1),
(51, 1),
(52, 1),
(53, 1),
(54, 1),
(55, 1),
(56, 1),
(57, 1),
(58, 1),
(59, 1),
(60, 1),
(66, 1),
(67, 1),
(68, 1),
(69, 1),
(70, 1),
(71, 1),
(72, 1),
(73, 1),
(74, 1),
(75, 1),
(76, 1),
(77, 1),
(78, 1),
(79, 1),
(80, 1),
(81, 1),
(82, 1),
(83, 1),
(84, 1),
(85, 1),
(86, 1),
(87, 1),
(88, 1),
(89, 1),
(90, 1);

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint UNSIGNED NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=MyISAM AUTO_INCREMENT=75 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `personal_access_tokens`
--

INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES
(1, 'App\\Models\\User', 1, 'MyAppToken', '313bd68a4676b440f7ef2d4053e1528874077f90be0a438bbd74522e8740ae1f', '[\"*\"]', NULL, NULL, '2024-07-08 05:48:50', '2024-07-08 05:48:50'),
(2, 'App\\Models\\User', 1, 'MyAppToken', 'abd9b9e9a44c4a6035f57341387c595b15ca3b8144341d7f6a8232acc03fbb39', '[\"*\"]', NULL, NULL, '2024-07-08 05:53:52', '2024-07-08 05:53:52'),
(3, 'App\\Models\\User', 1, 'MyAppToken', 'ec2478790d39cf26f33bce2423db1c24b740f2817571237d438419b56090d9fc', '[\"*\"]', NULL, NULL, '2024-07-08 05:55:32', '2024-07-08 05:55:32'),
(4, 'App\\Models\\User', 1, 'MyAppToken', '4f4a77cddfe5a3c34e0a3c52fd6fcb6fc1ae4929d9e491621a74fdbbf3d41cc8', '[\"*\"]', NULL, NULL, '2024-07-08 05:56:28', '2024-07-08 05:56:28'),
(5, 'App\\Models\\User', 1, 'MyAppToken', '87d74ce64a032b3ec59984b8bac69fb0e553280761a35e87b40f519fb7a0cb85', '[\"*\"]', NULL, NULL, '2024-07-08 06:13:44', '2024-07-08 06:13:44'),
(6, 'App\\Models\\User', 1, 'MyAppToken', 'aba46d70b6b30518b608d58cd4843f86d3aa137604bd0e8a79c0927fd548db52', '[\"*\"]', NULL, NULL, '2024-07-08 06:14:33', '2024-07-08 06:14:33'),
(7, 'App\\Models\\User', 1, 'MyAppToken', 'b7cf2ce11daeaa8ae3ac42e1584eab539ae017403c9b47b162f8bfa5ca25bfea', '[\"*\"]', NULL, NULL, '2024-07-08 06:17:31', '2024-07-08 06:17:31'),
(8, 'App\\Models\\User', 1, 'MyAppToken', '0d04cf7a786253b11a94806ef8246345c78b877899933598da4195b915788136', '[\"*\"]', NULL, NULL, '2024-07-08 06:20:50', '2024-07-08 06:20:50'),
(9, 'App\\Models\\User', 1, 'MyAppToken', '669751d6f5a0c87bd61829e151b737e9ce8c35bb62ea98eb33e4ed06a089e0cc', '[\"*\"]', NULL, NULL, '2024-07-08 06:20:50', '2024-07-08 06:20:50'),
(10, 'App\\Models\\User', 1, 'MyAppToken', '5ed945aece827a59dc7c101de10c03a143868b38aed74c82fa12e73c53daf308', '[\"*\"]', NULL, NULL, '2024-07-08 06:21:59', '2024-07-08 06:21:59'),
(11, 'App\\Models\\User', 1, 'MyAppToken', '791c6f3594bf9ed1edf68fb71bd7c075e46478f22b1e5fe57ad47ef16bb11066', '[\"*\"]', NULL, NULL, '2024-07-08 06:21:59', '2024-07-08 06:21:59'),
(12, 'App\\Models\\User', 1, 'MyAppToken', '5b312d6ddd6e896b300295b355fa75e3212ee5cd7b9bb83cdb902af935c9affd', '[\"*\"]', NULL, NULL, '2024-07-08 06:23:29', '2024-07-08 06:23:29'),
(13, 'App\\Models\\User', 1, 'MyAppToken', 'ca6eb952b7b313072cc0679b10a2363b2238b6bf3c9d95029bb5cd807404a91f', '[\"*\"]', NULL, NULL, '2024-07-08 06:23:29', '2024-07-08 06:23:29'),
(14, 'App\\Models\\User', 1, 'MyAppToken', 'e42e7fa7fa175eb5c44b4eb2c4d25829a353478155f8a5bfd3306966e96b5835', '[\"*\"]', NULL, NULL, '2024-07-08 06:25:15', '2024-07-08 06:25:15'),
(15, 'App\\Models\\User', 1, 'MyAppToken', 'e48a5a2a27997345a46836cdcff3cd0649b8f0339d22b5b3c58439e391813675', '[\"*\"]', NULL, NULL, '2024-07-08 06:25:15', '2024-07-08 06:25:15'),
(16, 'App\\Models\\User', 1, 'MyAppToken', '82a7e9645ab504b885c68618434a2474e1fe1b4fca87dc59ea1ca26f5e9d5947', '[\"*\"]', NULL, NULL, '2024-07-08 06:26:00', '2024-07-08 06:26:00'),
(17, 'App\\Models\\User', 1, 'MyAppToken', '68e651e48ae69a730b5e21149f0d03c9fdc7013cd12b09d0b0509b7ad4e26b21', '[\"*\"]', NULL, NULL, '2024-07-08 06:26:00', '2024-07-08 06:26:00'),
(18, 'App\\Models\\User', 1, 'MyAppToken', 'da9c5beb6133061c0348e5930f5a399677a72d1e2f1e8f041f97a9950be3d235', '[\"*\"]', NULL, NULL, '2024-07-08 06:34:11', '2024-07-08 06:34:11'),
(19, 'App\\Models\\User', 1, 'MyAppToken', '2daa8152d761930119843e863b15c2ea8bbab443da2ccf305ff9bebf49af4898', '[\"*\"]', NULL, NULL, '2024-07-08 06:34:11', '2024-07-08 06:34:11'),
(20, 'App\\Models\\User', 1, 'MyAppToken', 'f6c9faec1b23418132f242f6f7ec542bd688a06f28b8f3ec0fd0492b881e8979', '[\"*\"]', NULL, NULL, '2024-07-08 06:35:09', '2024-07-08 06:35:09'),
(21, 'App\\Models\\User', 1, 'MyAppToken', '157ec60309575b2e6c9af75565fe5ec5a7f197ef0122732aa1d25a059de06f09', '[\"*\"]', NULL, NULL, '2024-07-08 06:35:09', '2024-07-08 06:35:09'),
(22, 'App\\Models\\User', 1, 'MyAppToken', '66560aa528abde41c543bf1451f421cce5b4801e8b19137043c2476340e8682b', '[\"*\"]', NULL, NULL, '2024-07-08 06:38:21', '2024-07-08 06:38:21'),
(23, 'App\\Models\\User', 1, 'MyAppToken', '97952139765eb2c2b0d8668ea1e242f8554c1317a9458f61136bcd677b070bb5', '[\"*\"]', NULL, NULL, '2024-07-08 06:38:21', '2024-07-08 06:38:21'),
(24, 'App\\Models\\User', 1, 'MyAppToken', '3b0fcb53ceb6e3a66736824ea19b8a73eab16ad142ad5f52a08fdb257e8fef49', '[\"*\"]', NULL, NULL, '2024-07-08 06:39:22', '2024-07-08 06:39:22'),
(25, 'App\\Models\\User', 1, 'MyAppToken', '2d2758d54a175f20a8c9450a7751eab2f928496330d3280a59af10bf625039c0', '[\"*\"]', NULL, NULL, '2024-07-08 06:39:22', '2024-07-08 06:39:22'),
(26, 'App\\Models\\User', 1, 'MyAppToken', '0d99ec84b809982b253e01366ecd088a0db1a7d688a0d20ee4cc3513423a6fc2', '[\"*\"]', NULL, NULL, '2024-08-25 02:07:30', '2024-08-25 02:07:30'),
(27, 'App\\Models\\User', 1, 'MyAppToken', '98bb15a0a8a83f65b7cba33d80f3a19264e79d04f9abdcd7893f76bcff36674f', '[\"*\"]', NULL, NULL, '2024-08-25 02:07:30', '2024-08-25 02:07:30'),
(28, 'App\\Models\\User', 1, 'MyAppToken', '3ec8188f18ae3ddbb3d39998d47fb2a315cb99ac3450b9a0000131d800afa15d', '[\"*\"]', NULL, NULL, '2024-08-25 02:07:52', '2024-08-25 02:07:52'),
(29, 'App\\Models\\User', 1, 'MyAppToken', '07e54fd05ae46374ee5d37259e4452dd77a6eac1b713399f7e2a01e5f03e8791', '[\"*\"]', NULL, NULL, '2024-08-25 02:07:53', '2024-08-25 02:07:53'),
(30, 'App\\Models\\User', 1, 'MyAppToken', 'a7515e0cbea77f9d6c681d5f042f1590c6ab2cfda26eb285116043197ec89d1c', '[\"*\"]', NULL, NULL, '2024-08-25 02:08:06', '2024-08-25 02:08:06'),
(31, 'App\\Models\\User', 1, 'MyAppToken', 'a40d58391863a1bb1df60162e7e477f4b0a6c551e52d63d2f4aa114201eb3663', '[\"*\"]', NULL, NULL, '2024-08-25 02:08:06', '2024-08-25 02:08:06'),
(32, 'App\\Models\\User', 2, 'MyAppToken', '83480093490fa495db21e29f03f58fabccaba3e5fa7531eab4cd4e18d36500cf', '[\"*\"]', NULL, NULL, '2024-08-25 02:19:41', '2024-08-25 02:19:41'),
(33, 'App\\Models\\User', 1, 'MyAppToken', '4126512da59cf947b8c45a279bc0c6c575b8e1e0c1507b6214a874e5241fa303', '[\"*\"]', NULL, NULL, '2024-08-25 02:44:10', '2024-08-25 02:44:10'),
(34, 'App\\Models\\User', 1, 'MyAppToken', 'c8d9c9ae6dc2dfc8618fdbc4b3a6166b04c5bd185031d51ffac11d7da4e489c0', '[\"*\"]', NULL, NULL, '2024-08-25 02:44:10', '2024-08-25 02:44:10'),
(35, 'App\\Models\\User', 1, 'MyAppToken', '55550519395202e4209a7bb951aa2423ca930ac59d79bac10e6b2437aa4e3e97', '[\"*\"]', NULL, NULL, '2024-08-25 02:44:39', '2024-08-25 02:44:39'),
(36, 'App\\Models\\User', 1, 'MyAppToken', 'ea4b14bfba3c0af833cdae114c7aa7c617783314d00210007fa09d720eeda544', '[\"*\"]', NULL, NULL, '2024-08-25 02:44:39', '2024-08-25 02:44:39'),
(37, 'App\\Models\\User', 1, 'MyAppToken', 'bf11dec5c41a3d03ca080fed0f3d7a4671dce164a49040f068b2c6a64752100c', '[\"*\"]', NULL, NULL, '2024-08-25 02:50:38', '2024-08-25 02:50:38'),
(38, 'App\\Models\\User', 1, 'MyAppToken', '86b1b4d60b850c29a4221dfe783c517cc6b0ce9b785920799f98d90ae550947c', '[\"*\"]', NULL, NULL, '2024-08-25 02:50:38', '2024-08-25 02:50:38'),
(39, 'App\\Models\\User', 1, 'MyAppToken', 'ecb989a20a2c7d542531f50b987c8f34846ebe970dc5f89223809c8781871c2f', '[\"*\"]', NULL, NULL, '2024-08-25 02:54:12', '2024-08-25 02:54:12'),
(40, 'App\\Models\\User', 1, 'MyAppToken', '33c75886409b01028bf0c727c47342baf1d2b14fc2dbc9f69843142d8422c98e', '[\"*\"]', NULL, NULL, '2024-08-25 02:54:12', '2024-08-25 02:54:12'),
(41, 'App\\Models\\User', 1, 'MyAppToken', '8abbf5e205e49727e9596687c28c27aba2cd87239c41b91351237ac962983379', '[\"*\"]', NULL, NULL, '2024-08-25 02:54:53', '2024-08-25 02:54:53'),
(42, 'App\\Models\\User', 1, 'MyAppToken', 'e4327e18b4a520fa1a66e1de9c6c60afe6d40d5582ec678740ba678f5aed142f', '[\"*\"]', NULL, NULL, '2024-08-25 02:54:53', '2024-08-25 02:54:53'),
(43, 'App\\Models\\User', 1, 'MyAppToken', 'bf3ffdd02d127aa7d2fd5343d2bc33f0d8ca321f2193b3336b82ba4bb296fbe0', '[\"*\"]', NULL, NULL, '2024-08-25 12:00:26', '2024-08-25 12:00:26'),
(44, 'App\\Models\\User', 1, 'MyAppToken', 'c00e0691121fdf61e4a5208de2020e7f85769ea97a6d21a9247da572b920f6b6', '[\"*\"]', NULL, NULL, '2024-08-25 12:00:26', '2024-08-25 12:00:26'),
(45, 'App\\Models\\User', 1, 'MyAppToken', '5e851230bc3e6816825a417be5d8440005dc00a98a0628f390caee4b91343608', '[\"*\"]', NULL, NULL, '2024-08-25 12:00:44', '2024-08-25 12:00:44'),
(46, 'App\\Models\\User', 1, 'MyAppToken', '91f50f16a31640bdfa9b5f554f87e4ae9ef121c46dab338bb60f6a7f1e38ec80', '[\"*\"]', NULL, NULL, '2024-08-25 12:00:44', '2024-08-25 12:00:44'),
(47, 'App\\Models\\User', 1, 'MyAppToken', '15900c227035e5ce1f9e10d3e2f017a30c2e9429732b1b61185a15786fe83e21', '[\"*\"]', NULL, NULL, '2024-09-01 05:20:54', '2024-09-01 05:20:54'),
(48, 'App\\Models\\User', 1, 'MyAppToken', 'cffdd1be91bd15f967932c66c3ca98f87a26639902057dad1f56c4bef0cd9dad', '[\"*\"]', NULL, NULL, '2024-09-01 05:20:54', '2024-09-01 05:20:54'),
(49, 'App\\Models\\User', 1, 'MyAppToken', '8f61c3bd9f05521aba75cb98400def38fd685af93a56624a484e23b8c9a7cc63', '[\"*\"]', NULL, NULL, '2024-09-01 05:26:14', '2024-09-01 05:26:14'),
(50, 'App\\Models\\User', 1, 'MyAppToken', 'b385cbc92cab3582e09da19c4595eece34e3029fa07e165368ef0b07ca87870a', '[\"*\"]', NULL, NULL, '2024-09-01 05:26:14', '2024-09-01 05:26:14'),
(51, 'App\\Models\\User', 1, 'MyAppToken', '00845a29a6dcf7caf858a9f213668b2472cacd6ae06948f51ec439444c41e322', '[\"*\"]', NULL, NULL, '2024-09-01 05:48:35', '2024-09-01 05:48:35'),
(52, 'App\\Models\\User', 1, 'MyAppToken', 'e3181758ff1e7c1e918c0a4de239620dc20f04948a0f1bf68fd12d0a41522a66', '[\"*\"]', '2024-09-01 06:00:32', NULL, '2024-09-01 05:48:35', '2024-09-01 06:00:32'),
(53, 'App\\Models\\User', 1, 'MyAppToken', '3541c0d2bf16fddd1d416b6480aa90564bdf4c40f71052b6175022381757f59c', '[\"*\"]', NULL, NULL, '2024-09-01 06:55:50', '2024-09-01 06:55:50'),
(54, 'App\\Models\\User', 1, 'MyAppToken', 'd06608fe24a2c8df389a878ed7ec0d888c319c9d7c58129e37b00b7f38ad4528', '[\"*\"]', NULL, NULL, '2024-09-01 06:55:50', '2024-09-01 06:55:50'),
(55, 'App\\Models\\User', 1, 'MyAppToken', '0826a371cecda4d2880941621f205b6c5e5d27ef35e2cb9e0d5864e9038c3fba', '[\"*\"]', NULL, NULL, '2024-09-01 07:29:47', '2024-09-01 07:29:47'),
(56, 'App\\Models\\User', 1, 'MyAppToken', '538686011dccbe9091247695d5e561bd4c8aaa32c67a2f4a09f37fe03818589a', '[\"*\"]', NULL, NULL, '2024-09-01 07:29:47', '2024-09-01 07:29:47'),
(57, 'App\\Models\\User', 1, 'MyAppToken', 'ff227e448230ad19c087bb414b1df6336ab25e655bfc75e65509130099649d8f', '[\"*\"]', NULL, NULL, '2024-09-01 07:37:36', '2024-09-01 07:37:36'),
(58, 'App\\Models\\User', 1, 'MyAppToken', '843a27329b368d819fc1c5b03342e68488733f6ded1fcda407a5befe68338e9c', '[\"*\"]', NULL, NULL, '2024-09-01 07:37:36', '2024-09-01 07:37:36'),
(59, 'App\\Models\\User', 1, 'MyAppToken', '28b1288e73c16d9aad5af647a2531435b86b9de3b18189bfd7622d42d4da003f', '[\"*\"]', NULL, NULL, '2024-09-01 07:38:30', '2024-09-01 07:38:30'),
(60, 'App\\Models\\User', 1, 'MyAppToken', 'b807f5fe29a13203da64108fa8005a110d99db0162553b1515c74b0bbd3c2db9', '[\"*\"]', NULL, NULL, '2024-09-01 07:38:30', '2024-09-01 07:38:30'),
(61, 'App\\Models\\User', 1, 'MyAppToken', '352cb957331f91c1b01a00612657c079653d86941f49bdf67e3b8d81d8f9e534', '[\"*\"]', NULL, NULL, '2024-09-01 07:42:49', '2024-09-01 07:42:49'),
(62, 'App\\Models\\User', 1, 'MyAppToken', '8ee786441f4cff97dc345e54e4b2738efd6f36bab04bcab95857ab0c89546f77', '[\"*\"]', NULL, NULL, '2024-09-01 07:42:49', '2024-09-01 07:42:49'),
(63, 'App\\Models\\User', 1, 'MyAppToken', 'ceff9693269d03f8275ac3c873997651d3da2ada13fc44a44d4cca36dee93ef6', '[\"*\"]', NULL, NULL, '2024-09-01 08:28:07', '2024-09-01 08:28:07'),
(64, 'App\\Models\\User', 1, 'MyAppToken', 'a7fdade0d7fe284989130f564f7cc314a82a1470d32dfbebd996b1b606607249', '[\"*\"]', NULL, NULL, '2024-09-01 08:28:07', '2024-09-01 08:28:07'),
(65, 'App\\Models\\User', 1, 'MyAppToken', 'c500c4a844da9b18823ec376aaae85b8a8de53de49f8f5c76380d49995e18822', '[\"*\"]', NULL, NULL, '2024-09-01 08:48:09', '2024-09-01 08:48:09'),
(66, 'App\\Models\\User', 1, 'MyAppToken', '48ad37435d6122f15727a6124650097c056212383965675d2782577320249442', '[\"*\"]', NULL, NULL, '2024-09-01 08:48:09', '2024-09-01 08:48:09'),
(67, 'App\\Models\\User', 1, 'MyAppToken', 'a8c55193eccb7a72c8993bf74a07be92be1451ae273a30ee12e0ae8a5e0041fa', '[\"*\"]', NULL, NULL, '2024-09-01 08:48:33', '2024-09-01 08:48:33'),
(68, 'App\\Models\\User', 1, 'MyAppToken', '142b79a2393c30257492fc96b12a3decf531fcf4e9a3962ff6e6c6e3bf3c798d', '[\"*\"]', NULL, NULL, '2024-09-01 08:48:54', '2024-09-01 08:48:54'),
(69, 'App\\Models\\User', 1, 'MyAppToken', '7a22bf5af3be8cd4682d693604ac20145c213af4c77d1395355368abd2fd725a', '[\"*\"]', NULL, NULL, '2024-09-01 09:03:12', '2024-09-01 09:03:12'),
(70, 'App\\Models\\User', 3, 'MyAppToken', '7c7de510c3753cee7351c49044150c69bda69a926ae02233b2b66606665a5689', '[\"*\"]', NULL, NULL, '2024-09-01 09:03:36', '2024-09-01 09:03:36'),
(71, 'App\\Models\\User', 3, 'MyAppToken', '37205e3f21ecbb8da4ae3a33ce74ec7f43338e0c8f9e240bfb91e40efe59801c', '[\"*\"]', NULL, NULL, '2024-09-01 09:05:09', '2024-09-01 09:05:09'),
(72, 'App\\Models\\User', 3, 'MyAppToken', '16042aa966483e2334c7f8fad405c22c0307ec3d7420e29c3277c95f73fbd339', '[\"*\"]', NULL, NULL, '2024-09-01 09:05:09', '2024-09-01 09:05:09'),
(73, 'App\\Models\\User', 3, 'MyAppToken', '2c07c8fee65e3a04cbc8c149e0589369f9c23b669194a5acde754daf68cd6adc', '[\"*\"]', NULL, NULL, '2024-09-01 09:05:31', '2024-09-01 09:05:31'),
(74, 'App\\Models\\User', 3, 'MyAppToken', '8aa0c356912c314b304660322b2308bd15212534cc45056248e9a251df42aedb', '[\"*\"]', NULL, NULL, '2024-09-01 09:05:31', '2024-09-01 09:05:31');

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

DROP TABLE IF EXISTS `posts`;
CREATE TABLE IF NOT EXISTS `posts` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `author_id` int NOT NULL,
  `category_id` int DEFAULT NULL,
  `title` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `seo_title` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `excerpt` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `meta_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `meta_keywords` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('PUBLISHED','DRAFT','PENDING') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `featured` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `posts_slug_unique` (`slug`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`id`, `author_id`, `category_id`, `title`, `seo_title`, `excerpt`, `body`, `image`, `slug`, `meta_description`, `meta_keywords`, `status`, `featured`, `created_at`, `updated_at`) VALUES
(1, 0, NULL, 'Lorem Ipsum Post', NULL, 'This is the excerpt for the Lorem Ipsum Post', '<p>This is the body of the lorem ipsum post</p>', 'posts/post1.jpg', 'lorem-ipsum-post', 'This is the meta description', 'keyword1, keyword2, keyword3', 'PUBLISHED', 0, '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(2, 0, NULL, 'My Sample Post', NULL, 'This is the excerpt for the sample Post', '<p>This is the body for the sample post, which includes the body.</p>\n                <h2>We can use all kinds of format!</h2>\n                <p>And include a bunch of other stuff.</p>', 'posts/post2.jpg', 'my-sample-post', 'Meta Description for sample post', 'keyword1, keyword2, keyword3', 'PUBLISHED', 0, '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(3, 0, NULL, 'Latest Post', NULL, 'This is the excerpt for the latest post', '<p>This is the body for the latest post</p>', 'posts/post3.jpg', 'latest-post', 'This is the meta description', 'keyword1, keyword2, keyword3', 'PUBLISHED', 0, '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(4, 0, NULL, 'Yarr Post', NULL, 'Reef sails nipperkin bring a spring upon her cable coffer jury mast spike marooned Pieces of Eight poop deck pillage. Clipper driver coxswain galleon hempen halter come about pressgang gangplank boatswain swing the lead. Nipperkin yard skysail swab lanyard Blimey bilge water ho quarter Buccaneer.', '<p>Swab deadlights Buccaneer fire ship square-rigged dance the hempen jig weigh anchor cackle fruit grog furl. Crack Jennys tea cup chase guns pressgang hearties spirits hogshead Gold Road six pounders fathom measured fer yer chains. Main sheet provost come about trysail barkadeer crimp scuttle mizzenmast brig plunder.</p>\n<p>Mizzen league keelhaul galleon tender cog chase Barbary Coast doubloon crack Jennys tea cup. Blow the man down lugsail fire ship pinnace cackle fruit line warp Admiral of the Black strike colors doubloon. Tackle Jack Ketch come about crimp rum draft scuppers run a shot across the bow haul wind maroon.</p>\n<p>Interloper heave down list driver pressgang holystone scuppers tackle scallywag bilged on her anchor. Jack Tar interloper draught grapple mizzenmast hulk knave cable transom hogshead. Gaff pillage to go on account grog aft chase guns piracy yardarm knave clap of thunder.</p>', 'posts/post4.jpg', 'yarr-post', 'this be a meta descript', 'keyword1, keyword2, keyword3', 'PUBLISHED', 0, '2024-08-25 17:07:06', '2024-08-25 17:07:06');

-- --------------------------------------------------------

--
-- Table structure for table `ratings`
--

DROP TABLE IF EXISTS `ratings`;
CREATE TABLE IF NOT EXISTS `ratings` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint DEFAULT NULL,
  `product_id` bigint DEFAULT NULL,
  `rating` tinyint DEFAULT NULL,
  `review` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_unique` (`name`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `display_name`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'Administrator', '2024-08-25 15:07:33', '2024-08-25 15:07:33'),
(2, 'user', 'Normal User', '2024-08-25 15:07:33', '2024-08-25 15:07:33');

-- --------------------------------------------------------

--
-- Table structure for table `role_has_permissions`
--

DROP TABLE IF EXISTS `role_has_permissions`;
CREATE TABLE IF NOT EXISTS `role_has_permissions` (
  `permission_id` bigint UNSIGNED NOT NULL,
  `role_id` bigint UNSIGNED NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

DROP TABLE IF EXISTS `schedules`;
CREATE TABLE IF NOT EXISTS `schedules` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `day` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `time_from` time DEFAULT NULL,
  `time_to` time DEFAULT NULL,
  `address_id` int DEFAULT NULL,
  `date` date DEFAULT NULL,
  `start_address` int DEFAULT NULL,
  `destination_address` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `order_id` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order` int NOT NULL DEFAULT '1',
  `group` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_key_unique` (`key`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `key`, `display_name`, `value`, `details`, `type`, `order`, `group`) VALUES
(1, 'site.title', 'Site Title', 'Site Title', '', 'text', 1, 'Site'),
(2, 'site.description', 'Site Description', 'Site Description', '', 'text', 2, 'Site'),
(3, 'site.logo', 'Site Logo', '', '', 'image', 3, 'Site'),
(4, 'site.google_analytics_tracking_id', 'Google Analytics Tracking ID', '', '', 'text', 4, 'Site'),
(5, 'admin.bg_image', 'Admin Background Image', '', '', 'image', 5, 'Admin'),
(6, 'admin.title', 'Admin Title', 'Voyager', '', 'text', 1, 'Admin'),
(7, 'admin.description', 'Admin Description', 'Welcome to Voyager. The Missing Admin for Laravel', '', 'text', 2, 'Admin'),
(8, 'admin.loader', 'Admin Loader', '', '', 'image', 3, 'Admin'),
(9, 'admin.icon_image', 'Admin Icon Image', '', '', 'image', 4, 'Admin'),
(10, 'admin.google_analytics_client_id', 'Google Analytics Client ID (used for admin dashboard)', '', '', 'text', 1, 'Admin');

-- --------------------------------------------------------

--
-- Table structure for table `translations`
--

DROP TABLE IF EXISTS `translations`;
CREATE TABLE IF NOT EXISTS `translations` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `table_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `column_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `foreign_key` int UNSIGNED NOT NULL,
  `locale` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `translations`
--

INSERT INTO `translations` (`id`, `table_name`, `column_name`, `foreign_key`, `locale`, `value`, `created_at`, `updated_at`) VALUES
(1, 'data_types', 'display_name_singular', 9, 'pt', 'Post', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(2, 'data_types', 'display_name_singular', 10, 'pt', 'Página', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(3, 'data_types', 'display_name_singular', 1, 'pt', 'Utilizador', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(4, 'data_types', 'display_name_singular', 8, 'pt', 'Categoria', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(5, 'data_types', 'display_name_singular', 2, 'pt', 'Menu', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(6, 'data_types', 'display_name_singular', 3, 'pt', 'Função', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(7, 'data_types', 'display_name_plural', 9, 'pt', 'Posts', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(8, 'data_types', 'display_name_plural', 10, 'pt', 'Páginas', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(9, 'data_types', 'display_name_plural', 1, 'pt', 'Utilizadores', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(10, 'data_types', 'display_name_plural', 8, 'pt', 'Categorias', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(11, 'data_types', 'display_name_plural', 2, 'pt', 'Menus', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(12, 'data_types', 'display_name_plural', 3, 'pt', 'Funções', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(13, 'categories', 'slug', 1, 'pt', 'categoria-1', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(14, 'categories', 'name', 1, 'pt', 'Categoria 1', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(15, 'categories', 'slug', 2, 'pt', 'categoria-2', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(16, 'categories', 'name', 2, 'pt', 'Categoria 2', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(17, 'pages', 'title', 1, 'pt', 'Olá Mundo', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(18, 'pages', 'slug', 1, 'pt', 'ola-mundo', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(19, 'pages', 'body', 1, 'pt', '<p>Olá Mundo. Scallywag grog swab Cat o\'nine tails scuttle rigging hardtack cable nipper Yellow Jack. Handsomely spirits knave lad killick landlubber or just lubber deadlights chantey pinnace crack Jennys tea cup. Provost long clothes black spot Yellow Jack bilged on her anchor league lateen sail case shot lee tackle.</p>\r\n<p>Ballast spirits fluke topmast me quarterdeck schooner landlubber or just lubber gabion belaying pin. Pinnace stern galleon starboard warp carouser to go on account dance the hempen jig jolly boat measured fer yer chains. Man-of-war fire in the hole nipperkin handsomely doubloon barkadeer Brethren of the Coast gibbet driver squiffy.</p>', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(20, 'menu_items', 'title', 1, 'pt', 'Painel de Controle', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(21, 'menu_items', 'title', 2, 'pt', 'Media', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(22, 'menu_items', 'title', 12, 'pt', 'Publicações', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(23, 'menu_items', 'title', 3, 'pt', 'Utilizadores', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(24, 'menu_items', 'title', 11, 'pt', 'Categorias', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(25, 'menu_items', 'title', 13, 'pt', 'Páginas', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(26, 'menu_items', 'title', 4, 'pt', 'Funções', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(27, 'menu_items', 'title', 5, 'pt', 'Ferramentas', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(28, 'menu_items', 'title', 6, 'pt', 'Menus', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(29, 'menu_items', 'title', 7, 'pt', 'Base de dados', '2024-08-25 17:07:06', '2024-08-25 17:07:06'),
(30, 'menu_items', 'title', 10, 'pt', 'Configurações', '2024-08-25 17:07:06', '2024-08-25 17:07:06');

-- --------------------------------------------------------

--
-- Table structure for table `trips`
--

DROP TABLE IF EXISTS `trips`;
CREATE TABLE IF NOT EXISTS `trips` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `driver_id` int DEFAULT NULL,
  `vehicle_id` int DEFAULT NULL,
  `start_location` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `end_location` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `fare` int DEFAULT NULL,
  `status` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` bigint UNSIGNED DEFAULT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'users/default.png',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `settings` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `api_token` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_verified` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_api_token_unique` (`api_token`),
  KEY `users_role_id_foreign` (`role_id`)
) ENGINE=MyISAM AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role_id`, `name`, `phone`, `email`, `avatar`, `email_verified_at`, `password`, `remember_token`, `settings`, `created_at`, `updated_at`, `api_token`, `type`, `is_verified`) VALUES
(1, 1, 'alaa2@gmail.com', '71381098', 'alaa2@gmail.com', 'users/default.png', NULL, '$2y$10$md6K22vzeE9PDdgTn5B6Zu8nNU5/pRX4XotzX9DsKbrt.pjHwHSqW', NULL, NULL, '2024-07-08 05:48:50', '2025-02-15 10:46:17', NULL, 'seller', NULL),
(2, 1, 'admin', '71333333', 'admin@gmail.com', 'users/1738406126.jpg', NULL, '$2y$10$vCTyFPhuNM7kFiJP63ujJeeeClGw4cxxiH6A3wQMW5FLWVUORbe12', 'NgdCS3g9wdMXFdGzMCfECsWbbS5jR5qvmsUgmyj76fyaAhkZC0Z3ET9CO5yC', '{\"locale\":\"en\"}', '2024-08-25 02:19:41', '2025-02-16 07:07:33', NULL, 'seller', NULL),
(60, 2, 'test', '76587896', NULL, 'users/default.png', NULL, '$2y$10$5QJyFuNGqF/htviB/7AX3eCjXGQYrLekU1PboEnT9L5Eu8wLMxqZC', NULL, NULL, '2025-02-10 19:55:01', '2025-02-10 19:55:01', NULL, NULL, NULL),
(61, 2, 'test', '76587296', NULL, 'users/default.png', NULL, '$2y$10$pdqH8vmiOozxBdMGhXUi4uT3hiA9sxGOXXZPsIWCM4RhVq07JvS4a', NULL, NULL, '2025-02-10 19:56:29', '2025-02-10 19:57:05', NULL, NULL, 1),
(62, 2, 'test', '76577296', NULL, 'users/default.png', NULL, '$2y$10$WomhM5oxylaYVMNYK8b3zO32Yah57pxVCiG8VYxJWPX4WyZg13gKq', NULL, NULL, '2025-02-10 19:58:15', '2025-02-10 19:58:15', NULL, NULL, NULL),
(63, 2, 'test', '71223354', NULL, 'users/default.png', NULL, '$2y$10$VnOhLbI.3dkYrBnV54PTfuFET.H43oEWRPClM.lcMG4BABE1824eK', NULL, NULL, '2025-02-10 20:02:09', '2025-02-10 20:02:25', NULL, NULL, 1),
(64, 2, 'test', '71227354', NULL, 'users/default.png', NULL, '$2y$10$YStCbadnMhiDJGQiqcomouWwysGXPt.h1cOpqQtnAbKAiyfTLR95S', NULL, NULL, '2025-02-10 20:03:08', '2025-02-10 20:03:22', NULL, 'seller', 1),
(65, 2, 'test', '71223376', NULL, 'users/default.png', NULL, '$2y$10$rgQqGxdtAMn3VMri/xpGj..PoNKAhjzuuVPy63Ip1wH3MbVf//IrO', NULL, NULL, '2025-02-10 20:04:04', '2025-02-10 20:04:49', NULL, 'seller', 1),
(66, 2, 'test', '71445566', NULL, 'users/default.png', NULL, '$2y$10$atqZ7xJowT3Lbafnf1dTie7f/NO1fq9LBY0NKdaQ9jALriM0.wSfO', NULL, NULL, '2025-02-15 03:51:19', '2025-02-15 03:51:19', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

DROP TABLE IF EXISTS `user_roles`;
CREATE TABLE IF NOT EXISTS `user_roles` (
  `user_id` bigint UNSIGNED NOT NULL,
  `role_id` bigint UNSIGNED NOT NULL,
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `user_roles_user_id_index` (`user_id`),
  KEY `user_roles_role_id_index` (`role_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

DROP TABLE IF EXISTS `vehicles`;
CREATE TABLE IF NOT EXISTS `vehicles` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `make` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `year` year NOT NULL,
  `license_plate` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `verification_codes`
--

DROP TABLE IF EXISTS `verification_codes`;
CREATE TABLE IF NOT EXISTS `verification_codes` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `type` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `used` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `verification_codes`
--

INSERT INTO `verification_codes` (`id`, `code`, `created_at`, `updated_at`, `deleted_at`, `user_id`, `type`, `expires_at`, `used`) VALUES
(17, '920984', '2025-02-10 19:55:01', '2025-02-10 19:55:01', NULL, 60, 'email', '2025-02-10 20:05:01', 0),
(18, '290345', '2025-02-10 19:56:29', '2025-02-15 04:01:00', NULL, 61, 'email', '2025-02-15 04:01:00', 1),
(19, '947458', '2025-02-10 19:58:15', '2025-02-10 19:58:15', NULL, 62, 'email', '2025-02-10 20:08:15', 0),
(20, '410858', '2025-02-10 20:02:09', '2025-02-10 20:02:25', NULL, 63, 'email', '2025-02-10 20:12:09', 1),
(21, '718840', '2025-02-10 20:03:08', '2025-02-10 20:03:19', NULL, 64, 'email', '2025-02-10 20:13:08', 1),
(22, '285604', '2025-02-10 20:04:04', '2025-02-10 20:04:35', NULL, 65, 'email', '2025-02-10 20:14:04', 1),
(23, '336375', '2025-02-15 03:51:19', '2025-02-15 03:51:19', NULL, 66, 'email', '2025-02-15 04:01:19', 0);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

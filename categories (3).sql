-- phpMyAdmin SQL Dump
-- version 5.1.1deb5ubuntu1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 20, 2025 at 09:06 AM
-- Server version: 8.0.43-0ubuntu0.22.04.1
-- PHP Version: 8.4.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `colala`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` bigint UNSIGNED NOT NULL,
  `parent_id` bigint UNSIGNED DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `color` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `parent_id`, `title`, `image`, `created_at`, `updated_at`, `color`) VALUES
(1, NULL, 'Gaming', 'category/u6YQNnCqPy2E2lFJgXjoA8tnSTSUzEKDVE8PkGmL.jpg', '2025-09-20 11:51:21', '2025-10-16 12:45:53', 'red'),
(2, 1, 'GTA V', 'category/AFZwEdSE6OzoYnZWLCb9HGExQRNY6T94FxX8ItrH.jpg', '2025-09-20 12:50:07', '2025-10-16 13:03:12', '#B9191933'),
(3, 1, 'Racing', 'category/6G2Cf2tcp7vosBQ6cxAT7iBq1dALVmp0pF3Zyrrj.jpg', '2025-09-20 18:15:02', '2025-10-16 13:06:42', '#B9191933'),
(4, 3, 'Iphone', 'category/dpuE8U7OG3L5xHiWhUX91VpSUx2TaD8A802tN6px.png', '2025-09-20 18:15:15', '2025-09-20 18:15:15', '#C7E1E1'),
(5, 3, 'Iphone 13', 'category/KWGow2RYxhcDSUbW5Q2rVBYI7C1A8JwVj4lXXtqH.png', '2025-09-20 18:15:25', '2025-09-20 18:15:25', '#C7E1E1'),
(6, 1, 'Shooting', 'category/NeDKWC1X9P12FQy7iCH2dzevIdtkbFapaCme6UhO.jpg', '2025-09-20 18:16:43', '2025-10-16 13:08:12', '#B9191933'),
(7, NULL, 'Fashion', 'category/jIyXA5h3PDDKpRsfrIhkhERmwUR93nouLRzOVFXq.png', '2025-09-20 18:55:57', '2025-09-20 18:55:57', '#0000FF33'),
(8, 7, 'Cloth', 'category/yMEzZjp1wrk9VO654AuWrZiONayAcYYSz1OqAFsx.png', '2025-09-20 18:56:50', '2025-10-16 12:35:40', '#C7E1E1'),
(9, 8, '3M', 'category/IyabIsxQX4OyFgeY8HhE4Ywc8tgduF97DWyBLre6.png', '2025-09-20 18:57:25', '2025-09-20 18:57:25', '#C7E1E1'),
(10, NULL, 'Electronics', 'category/FpJZRPmtSPH87KuOFo3DzacENSzey9XwIRxkZpCP.png', '2025-09-20 18:58:21', '2025-09-20 18:58:21', '#00800033'),
(11, 10, 'iFruit', 'category/TC0lI8zSE4kmOhMESAMUEhqsuenW8cm5eElpA2Ek.jpg', '2025-09-20 18:58:33', '2025-10-16 13:15:58', '#B9191933'),
(12, 10, 'iTop', 'category/przWrTEqOgeksKvIm9d5IwlwfATkhwYgwV6BzTlA.jpg', '2025-09-20 18:58:34', '2025-10-16 13:12:42', '#B9191933'),
(13, 12, 'Samsung', 'category/yUd0Clpt5u33HKJqe8FtZBkO35ngYx6M6OhXLfiX.png', '2025-09-20 18:58:49', '2025-09-20 18:58:49', '#C7E1E1'),
(14, NULL, 'Home', 'category/3mxRlbk9suLyetyUtj38p4zWvFNTkiEqEShTn8CI.png', '2025-09-20 18:59:28', '2025-10-16 12:50:41', '#B9191933'),
(15, 14, 'Sweet', 'category/bbGngcOXinMfsKGxymeK5pMLWcP5FAeso4pG8eIU.jpg', '2025-09-20 18:59:45', '2025-10-16 13:17:16', '#B9191933'),
(16, 15, 'Apple', 'category/LuQkej9Y3IdjJeIVhpm9CfpKuijFyC6Zpe53Yt3H.png', '2025-09-20 19:00:02', '2025-09-20 19:00:02', '#C7E1E1'),
(17, NULL, 'Services', 'category/DR6vY2muM2EpZLCvQgRBOtDnwUKuHlaQ1pH53NqL.png', '2025-09-20 19:00:42', '2025-09-20 19:00:42', '#FFFF0033'),
(18, 17, 'Car Service', 'category/cekOPJhq1jd968Fv05uWma8kW5lGuVSmdywMZ611.jpg', '2025-09-20 19:00:54', '2025-10-16 13:22:00', '#B9191933'),
(19, 18, 'Car Service', 'category/hvqV8gvwG4l5ZAvsf7EXuSLOtpPQQIsZU8zg9OOq.jpg', '2025-09-20 19:01:10', '2025-10-16 13:20:37', '#B9191933'),
(21, NULL, 'Wellness', 'category/CdL7SAivTq15PXvltbk7KerRTmYF9D8Sn4OsnDQg.png', '2025-10-16 12:53:18', '2025-10-16 12:53:18', '#FFFF0033'),
(22, NULL, 'Computing', 'category/iErg2oCmIqHJeqeoWgBMwNr3iAJFetV9mf30Nx0e.png', '2025-10-16 12:54:04', '2025-10-16 12:54:04', '#FFFF0033'),
(23, NULL, 'Beauty', 'category/sgPjB2wLiGyUeCQOubMWJLXTFwfSURjGQalCgWnj.png', '2025-10-16 12:54:47', '2025-10-16 12:54:47', '#FFFF0033'),
(24, 21, 'Brain Wellness', 'category/ciBni90DOpIDQ5ZXl5CWghqaj52ogMvTm45HOu19.jpg', '2025-10-16 13:24:45', '2025-10-16 13:24:45', '#C7E1E1'),
(25, 22, 'CPU', 'category/9YWt9GDAOGAQlGemF0a68Ug5UPP5nQJZKC2JvGbH.jpg', '2025-10-16 13:26:17', '2025-10-16 13:26:17', '#C7E1E1'),
(26, 23, 'Eye', 'category/90vx9b9tjEBYLN8f7bJppERcZvgSobW6IImkm6vm.jpg', '2025-10-16 13:27:34', '2025-10-16 13:27:34', '#C7E1E1'),
(27, 23, 'Pink', 'category/M6ZhXt6yV74K3fpdD9gnlDsc3nr8ql6ipf7fkGX6.jpg', '2025-10-19 13:09:46', '2025-10-19 13:09:46', '#e53ea8'),
(28, 28, 'Tables', 'category/g8CUJLi5eKX4XTj6pgx4UhKn5Ez53HOS0sWeqSZQ.jpg', '2025-10-20 06:51:15', '2025-10-20 06:52:29', '#E53E3E'),
(267, NULL, 'Vehicles', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(268, 267, 'Cars', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(269, 267, 'Motorcycles & Scooters', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(270, 267, 'Buses & Microbuses', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(271, 267, 'Trucks & Trailers', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(272, 267, 'Vehicle Parts & Accessories', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(273, 267, 'Construction & Heavy Machinery', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(274, 267, 'Watercraft & Boats', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(275, 267, 'Car Services', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(276, NULL, 'Property', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(277, 276, 'New Builds', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(278, 276, 'Houses & Apartments For Rent', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(279, 276, 'Houses & Apartments For Sale', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(280, 276, 'Short Let', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(281, 276, 'Land & Plots For Sale', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(282, 276, 'Land & Plots for Rent', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(283, 276, 'Event Centres, Venues & Workstations', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(284, 276, 'Commercial Property for Rent', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(285, 276, 'Commercial Property for Sale', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(286, 276, 'Real Estate Agents & Services', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(287, 276, 'Property Valuation & Surveying', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(288, NULL, 'Mobile Phones & Tablets', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(289, 288, 'Mobile Phones', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(290, 288, 'Tablets', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(291, 288, 'Accessories for Phones & Tablets', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(292, 288, 'Smart Watches', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(293, 288, 'Headphones', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(294, 10, 'Laptops & Computers', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(295, 10, 'TV & DVD Equipment', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(296, 10, 'Televisions & Home Theater', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(297, 10, 'Audio & Music Equipment', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(298, 10, 'Headphones', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(299, 10, 'Photo & Video Cameras', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(300, 10, 'Security & Surveillance', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(301, 10, 'Video Game Consoles', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(302, 10, 'Video Games', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(303, 10, 'Printers & Scanners', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(304, 10, 'Computer Monitors', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(305, 10, 'Computer Hardware', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(306, 10, 'Computer Accessories', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(307, 10, 'Networking Products', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(308, 10, 'Accessories & Supplies for Electronics', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(309, 10, 'Software', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(310, NULL, 'Home, Furniture & Appliances', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(311, 310, 'Furniture', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(312, 310, 'Lighting', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(313, 310, 'Storage & Organization', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(314, 310, 'Home Accessories', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(315, 310, 'Home Appliances', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(316, 310, 'Kitchen Appliances', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(317, 310, 'Kitchenware & Cookware', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(318, 310, 'Household Chemicals', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(319, 310, 'Garden Supplies', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(320, 310, 'Generators & Solar', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(321, NULL, 'Solar & Power Solutions', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(322, 321, 'Solar Panels', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(323, 321, 'Inverters', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(324, 321, 'Solar Batteries', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(325, 321, 'Charge Controllers', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(326, 321, 'Solar Lights & Kits', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(327, 321, 'UPS & Stabilizers', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(328, 7, 'Women\'s Fashion', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(329, 328, 'Women\'s Clothing', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(330, 328, 'Women\'s Shoes', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(331, 328, 'Women\'s Bags', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(332, 328, 'Women\'s Jewelry', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(333, 328, 'Women\'s Watches', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(334, 328, 'Women\'s Clothing Accessories', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(335, 328, 'Women\'s Wedding Wear & Accessories', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(336, 7, 'Men\'s Fashion', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(337, 336, 'Men\'s Clothing', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(338, 336, 'Men\'s Shoes', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(339, 336, 'Men\'s Bags', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(340, 336, 'Men\'s Jewelry', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(341, 336, 'Men\'s Watches', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(342, 336, 'Men\'s Clothing Accessories', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(343, 336, 'Men\'s Wedding Wear & Accessories', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(344, 7, 'Kids\' Fashion', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(345, 344, 'Children\'s Clothing', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(346, 344, 'Children\'s Shoes', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(347, 344, 'Babies & Kids Accessories', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(348, NULL, 'Jewelry & Watches', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(349, 348, 'Gold Jewelry', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(350, 348, 'Silver Jewelry', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(351, 348, 'Diamond & Gemstones', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(352, 348, 'Luxury Watches', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(353, 348, 'Fashion Watches', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(354, 348, 'Wedding Rings & Bands', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(355, NULL, 'Luggage, Bags & Travel', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(356, 355, 'Suitcases & Travel Bags', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(357, 355, 'Backpacks', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(358, 355, 'Handbags & Wallets', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(359, 355, 'Travel Accessories', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(360, 355, 'Umbrellas', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(361, NULL, 'Health & Beauty', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(362, 361, 'Hair Beauty', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(363, 361, 'Face Care', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(364, 361, 'Oral Care', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(365, 361, 'Body Care', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(366, 361, 'Fragrance', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(367, 361, 'Makeup', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(368, 361, 'Sexual Wellness', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(369, 361, 'Tools & Accessories', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(370, 361, 'Vitamins & Supplements', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(371, 361, 'Massagers', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(372, 361, 'Medical Supplies', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(373, 361, 'Wheelchairs & Mobility Aids', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(374, 361, 'Blood Pressure Monitors', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(375, 361, 'Glucometers & Test Strips', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(376, 361, 'First Aid & PPE', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(377, NULL, 'Wedding & Events', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(378, 377, 'Wedding Gowns & Suits', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(379, 377, 'Wedding Accessories', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(380, 377, 'Event Decorations', NULL, '2025-11-20 09:05:53', '2025-11-20 09:05:53', NULL),
(381, 377, 'Catering Equipment', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(382, 377, 'Event Planning Services', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(383, NULL, 'Repair & Construction', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(384, 383, 'Electrical Equipment', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(385, 383, 'Building Materials & Supplies', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(386, 383, 'Plumbing & Water Systems', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(387, 383, 'Electrical Hand Tools', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(388, 383, 'Hand Tools', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(389, 383, 'Measuring & Testing Tools', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(390, 383, 'Hardware & Fasteners', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(391, 383, 'Doors & Security', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(392, 383, 'Windows & Glass', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(393, 383, 'Other Repair & Construction Items', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(394, 383, 'Automotive Tools & Garage Equipment', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(395, NULL, 'Commercial Equipment & Tools', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(396, 395, 'Medical Equipment & Supplies', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(397, 395, 'Safety Equipment & Protective Gear', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(398, 395, 'Manufacturing Equipment', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(399, 395, 'Manufacturing Materials & Supplies', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(400, 395, 'Retail & Store Equipment', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(401, 395, 'Restaurant & Catering Equipment', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(402, 395, 'Stationery & Office Equipment', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(403, 395, 'Salon & Beauty Equipment', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(404, 395, 'Printing & Graphics Equipment', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(405, 395, 'Stage & Event Equipment', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(406, NULL, 'Industrial Machinery', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(407, 406, 'Industrial Generators', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(408, 406, 'Welding Machines', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(409, 406, 'Borehole Drilling Equipment', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(410, 406, 'Factory Machines', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(411, 406, 'Packaging Machines', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(412, NULL, 'Office & Stationery', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(413, 412, 'Office Furniture', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(414, 412, 'Stationery & Supplies', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(415, 412, 'Printer Ink & Toner', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(416, 412, 'Office Electronics', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(417, NULL, 'School Supplies & Uniforms', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(418, 417, 'School Uniforms', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(419, 417, 'School Bags', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(420, 417, 'Textbooks & Educational Materials', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(421, 417, 'Stationery', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(422, NULL, 'Leisure, Arts & Entertainment', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(423, 422, 'Sports Equipment', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(424, 422, 'Massagers', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(425, 422, 'Musical Instruments & Gear', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(426, 422, 'Books & Table Games', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(427, 422, 'Arts, Crafts & Awards', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(428, 422, 'Outdoor Gear', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(429, 422, 'Smoking Accessories', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(430, 422, 'Music & Video', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(431, 422, 'Fitness & Personal Training Services', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(432, NULL, 'Babies & Kids', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(433, 432, 'Toys, Games & Bikes', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(434, 432, 'Action Figures & Dolls', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(435, 432, 'Board Games & Puzzles', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(436, 432, 'Drones & RC Toys', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(437, 432, 'Children\'s Furniture', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(438, 432, 'Children\'s Clothing', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(439, 432, 'Children\'s Shoes', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(440, 432, 'Babies & Kids Accessories', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(441, 432, 'Baby Gear & Equipment', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(442, 432, 'Care & Feeding', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(443, 432, 'Maternity & Pregnancy', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(444, 432, 'Transport & Safety', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(445, 432, 'Playground Equipment', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(446, NULL, 'Food, Agriculture & Farming', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(447, 446, 'Food & Beverages', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(448, 446, 'Farm Animals', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(449, 446, 'Feeds, Supplements & Seeds', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(450, 446, 'Farm Machinery & Equipment', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(451, NULL, 'Animals & Pets', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(452, 451, 'Pet\'s Accessories', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(453, 451, 'Cats & Kittens', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(454, 451, 'Dogs & Puppies', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(455, 451, 'Fish', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(456, 451, 'Birds', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(457, 451, 'Other Animals', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(458, 451, 'Pet Services', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(459, NULL, 'Supermarket & Groceries', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(460, 459, 'Food Cupboard', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(461, 459, 'Beverages', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(462, 459, 'Household Cleaning', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(463, 459, 'Laundry & Cleaning', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(464, 459, 'Baby Products', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(465, 459, 'Wine, Spirits & Tobacco', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(466, NULL, 'Sporting Goods & Fitness', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(467, 466, 'Exercise & Fitness Equipment', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(468, 466, 'Sports Clothing', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(469, 466, 'Outdoor & Adventure', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(470, 466, 'Team Sports', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(471, NULL, 'Gifts & Hampers', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(472, 471, 'Gift Sets & Boxes', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(473, 471, 'Corporate Gifts', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(474, 471, 'Birthday & Anniversary Gifts', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(475, 471, 'Christmas & Festive Hampers', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(476, 1, 'Consoles & Accessories', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(477, 1, 'Video Games', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(478, NULL, 'Books, Movies & Music', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(479, 478, 'Books', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(480, 478, 'Musical Instruments', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(481, 478, 'Movies & TV Shows', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(482, NULL, 'Virtual & Digital Products', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(483, 482, 'Airtime & Mobile Data', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(484, 482, 'Gift Cards & Vouchers', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(485, 482, 'Software Licenses', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(486, 482, 'Online Courses & eBooks', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(487, 482, 'Streaming Subscriptions', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(488, NULL, 'Automotive', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(489, 488, 'Car Accessories', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL),
(490, 488, 'Motorcycle Parts & Accessories', NULL, '2025-11-20 09:05:54', '2025-11-20 09:05:54', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `categories_parent_id_foreign` (`parent_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=491;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

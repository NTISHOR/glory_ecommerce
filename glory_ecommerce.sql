-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 03, 2026 at 05:24 PM
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
-- Database: `glory_ecommerce`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_activity_logs`
--

CREATE TABLE `admin_activity_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_activity_logs`
--

INSERT INTO `admin_activity_logs` (`id`, `admin_id`, `action`, `description`, `ip_address`, `created_at`) VALUES
(1, 1, 'UPDATE_ADMIN_PERMISSIONS', 'Updated permissions for administrator Augustine Ntishor Akpotu (gloryagbor1@gmail.com). Granted permissions: manage_vendors, manage_customers, manage_products, manage_categories, manage_orders, manage_payments, view_reports, view_activity_logs, manage_settings.', '::1', '2026-10-02 21:32:13'),
(2, 1, 'UPDATE_ADMIN_PERMISSIONS', 'Updated permissions for administrator Augustine Ntishor Akpotu (gloryagbor1@gmail.com). Granted permissions: manage_vendors, manage_customers, manage_products, manage_categories, manage_orders, manage_payments, view_reports, view_activity_logs, manage_settings.', '::1', '2026-10-02 21:32:14'),
(3, 1, 'UPDATE_ADMIN_PERMISSIONS', 'Updated permissions for administrator Augustine Ntishor Akpotu (gloryagbor1@gmail.com). Granted permissions: manage_vendors, manage_customers, manage_products, manage_categories, manage_orders, manage_payments, view_reports, view_activity_logs, manage_settings.', '::1', '2026-10-02 21:32:15'),
(4, 1, 'UPDATE_ADMIN_PERMISSIONS', 'Updated permissions for administrator Augustine Ntishor Akpotu (gloryagbor1@gmail.com). Granted permissions: manage_vendors, manage_customers, manage_products, manage_categories, manage_orders, manage_payments, view_reports, view_activity_logs, manage_settings.', '::1', '2026-10-02 21:32:15'),
(5, 1, 'UPDATE_ADMIN_PERMISSIONS', 'Updated permissions for administrator Augustine Ntishor Akpotu (gloryagbor1@gmail.com). Granted permissions: manage_vendors, manage_customers, manage_products, manage_categories, manage_orders, manage_payments, view_reports, view_activity_logs, manage_settings.', '::1', '2026-10-02 21:34:32'),
(6, 1, 'CREATE_VENDOR', 'Created vendor account for Glory Agbor (gloryagbor2@gmail.com) with store \'GLORY AGBOR\'.', '::1', '2026-10-02 22:09:12'),
(7, 1, 'CHANGE_VENDOR_VERIFICATION', 'Changed vendor Glory Agbor\'s verification status from pending to verified.', '::1', '2026-10-02 22:22:50'),
(8, 1, 'CHANGE_VENDOR_VERIFICATION', 'Changed vendor Glory Agbor\'s verification status from verified to rejected.', '::1', '2026-10-02 22:25:13'),
(9, 1, 'CHANGE_VENDOR_VERIFICATION', 'Changed vendor Glory Agbor\'s verification status from rejected to verified.', '::1', '2026-10-02 22:25:21'),
(10, 1, 'CHANGE_VENDOR_STATUS', 'Changed vendor Glory Agbor\'s account status from active to suspended.', '::1', '2026-10-02 22:25:35'),
(11, 1, 'CHANGE_VENDOR_STATUS', 'Changed vendor Glory Agbor\'s account status from suspended to active.', '::1', '2026-10-02 22:25:39'),
(12, 1, 'CREATE_CUSTOMER', 'Created customer account for Agbor Glory (gloryagbor3@gmail.com).', '::1', '2026-10-02 22:39:21'),
(13, 1, 'CHANGE_CUSTOMER_STATUS', 'Changed Agbor Glory\'s status from active to inactive.', '::1', '2026-10-03 07:02:16'),
(14, 1, 'CHANGE_CUSTOMER_STATUS', 'Changed Agbor Glory\'s status from inactive to suspended.', '::1', '2026-10-03 07:02:23'),
(15, 1, 'CHANGE_CUSTOMER_STATUS', 'Changed Agbor Glory\'s status from suspended to active.', '::1', '2026-10-03 07:02:27'),
(16, 1, 'DELETE_CUSTOMER', 'Permanently deleted customer: Agbor Glory (gloryagbor3@gmail.com)', '::1', '2026-10-03 07:06:56'),
(17, 1, 'CHANGE_VENDOR_STATUS', 'Changed vendor GLORY AG\'s account status from inactive to active.', '::1', '2026-10-03 09:11:25'),
(18, 1, 'CHANGE_VENDOR_VERIFICATION', 'Changed vendor GLORY AG\'s verification status from pending to verified.', '::1', '2026-10-03 09:11:59'),
(19, 1, 'CHANGE_VENDOR_STATUS', 'Changed vendor GLORY AG\'s account status from inactive to active.', '::1', '2026-10-03 09:12:01'),
(20, 1, 'CREATE_CUSTOMER', 'Created customer account for PEACE A (peacea@gmail.com).', '::1', '2026-10-03 09:13:03'),
(21, 1, 'CREATE_CATEGORY', 'Created category: VEGETABLES.', '::1', '2026-10-03 10:40:13'),
(22, 1, 'UPDATE_ORDER_STATUS', 'Order #3 status changed from pending to processing.', '::1', '2026-10-03 12:09:51'),
(23, 1, 'UPDATE_ORDER_STATUS', 'Order #3 status changed from processing to delivered.', '::1', '2026-10-03 12:12:56'),
(24, 1, 'UPDATE_ORDER_STATUS', 'Order #3 status changed from delivered to processing.', '::1', '2026-10-03 12:42:14'),
(25, 1, 'UPDATE_ORDER_STATUS', 'Order #3 status changed from processing to delivered.', '::1', '2026-10-03 12:47:42'),
(26, 1, 'UPDATE_PAYMENT_STATUS', 'Payment for order GM-20261003140810-6844 changed from pending to paid.', '::1', '2026-10-03 12:52:33'),
(27, 1, 'UPDATE_ORDER_STATUS', 'Order #2 status changed from pending to shipped.', '::1', '2026-10-03 13:03:15'),
(28, 1, 'UPDATE_ORDER_STATUS', 'Order #2 status changed from shipped to delivered.', '::1', '2026-10-03 13:23:10'),
(29, 1, 'UPDATE_PAYMENT_STATUS', 'Payment for order GM-20261003140118-8473 changed from pending to paid.', '::1', '2026-10-03 13:23:38'),
(30, 1, 'UPDATE_PAYMENT_STATUS', 'Payment for order GM-20261003140022-3264 changed from pending to paid.', '::1', '2026-10-03 13:23:43'),
(31, 1, 'UPDATE_PAYMENT_STATUS', 'Payment for order GM-20261003140118-8473 changed from paid to refunded.', '::1', '2026-10-03 13:53:35'),
(32, 1, 'Approved Product Change Request', 'Approved change request #1 for product #2', '::1', '2026-10-03 14:41:18'),
(33, 1, 'Rejected Product Change Request', 'Rejected change request #2 for product #2', '::1', '2026-10-03 14:51:17');

-- --------------------------------------------------------

--
-- Table structure for table `admin_permissions`
--

CREATE TABLE `admin_permissions` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `permission` varchar(100) NOT NULL,
  `granted_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_permissions`
--

INSERT INTO `admin_permissions` (`id`, `admin_id`, `permission`, `granted_by`, `created_at`) VALUES
(37, 2, 'manage_vendors', 1, '2026-10-02 21:34:32'),
(38, 2, 'manage_customers', 1, '2026-10-02 21:34:32'),
(39, 2, 'manage_products', 1, '2026-10-02 21:34:32'),
(40, 2, 'manage_categories', 1, '2026-10-02 21:34:32'),
(41, 2, 'manage_orders', 1, '2026-10-02 21:34:32'),
(42, 2, 'manage_payments', 1, '2026-10-02 21:34:32'),
(43, 2, 'view_reports', 1, '2026-10-02 21:34:32'),
(44, 2, 'view_activity_logs', 1, '2026-10-02 21:34:32'),
(45, 2, 'manage_settings', 1, '2026-10-02 21:34:32');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'VEGETABLES', 'FOOD', 'active', '2026-10-03 10:40:13', '2026-10-03 10:40:13');

-- --------------------------------------------------------

--
-- Table structure for table `customer_profiles`
--

CREATE TABLE `customer_profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other','prefer_not_to_say') DEFAULT 'prefer_not_to_say',
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_profiles`
--

INSERT INTO `customer_profiles` (`id`, `user_id`, `date_of_birth`, `gender`, `address`, `city`, `state`, `country`, `profile_picture`, `created_at`, `updated_at`) VALUES
(2, 7, '2026-09-10', 'female', '99C Old Odukpani Road, Ikot Ansa, Calabar.', 'Calabar', 'cross river', 'Nigeria', 'uploads/customers/customer_7_1791022151.jpg', '2026-10-03 09:13:03', '2026-10-03 10:18:22');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `delivery_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('pay_on_delivery','bank_transfer','card') NOT NULL DEFAULT 'pay_on_delivery',
  `payment_status` enum('pending','paid','failed','refunded') DEFAULT 'pending',
  `payment_verified_by` int(11) DEFAULT NULL,
  `payment_verified_at` datetime DEFAULT NULL,
  `refunded_by` int(11) DEFAULT NULL,
  `refunded_at` datetime DEFAULT NULL,
  `refund_reason` text DEFAULT NULL,
  `order_status` enum('pending','confirmed','processing','shipped','delivered','cancelled') DEFAULT 'pending',
  `vendor_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `delivery_address` text NOT NULL,
  `customer_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `customer_id`, `subtotal`, `delivery_fee`, `discount`, `total_amount`, `payment_method`, `payment_status`, `payment_verified_by`, `payment_verified_at`, `refunded_by`, `refunded_at`, `refund_reason`, `order_status`, `vendor_confirmed`, `delivery_address`, `customer_note`, `created_at`, `updated_at`) VALUES
(1, 'GM-20261003140022-3264', 7, 222222.00, 0.00, 0.00, 222222.00, 'pay_on_delivery', 'paid', 1, '2026-10-03 14:23:43', NULL, NULL, NULL, 'pending', 0, '99C Old Odukpani Road, Ikot Ansa, Calabar., Calabar, cross river, Nigeria', NULL, '2026-10-03 12:00:22', '2026-10-03 13:23:43'),
(2, 'GM-20261003140118-8473', 7, 222222.00, 0.00, 0.00, 222222.00, 'pay_on_delivery', 'refunded', 1, '2026-10-03 14:23:38', 1, '2026-10-03 14:53:34', 'Customer Requested a Refund', 'delivered', 0, '99C Old Odukpani Road, Ikot Ansa, Calabar., Calabar, cross river, Nigeria', NULL, '2026-10-03 12:01:18', '2026-10-03 13:53:34'),
(3, 'GM-20261003140810-6844', 7, 1111.00, 0.00, 0.00, 1111.00, 'bank_transfer', 'paid', NULL, NULL, NULL, NULL, NULL, 'delivered', 0, '99C Old Odukpani Road, Ikot Ansa, Calabar., Calabar, cross river, Nigeria', NULL, '2026-10-03 12:08:10', '2026-10-03 12:52:33');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `fulfillment_status` enum('pending','confirmed','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `vendor_id`, `product_name`, `quantity`, `unit_price`, `subtotal`, `fulfillment_status`, `created_at`) VALUES
(1, 1, 2, 3, 'VEGETABLES', 1, 222222.00, 222222.00, 'pending', '2026-10-03 12:00:22'),
(2, 2, 2, 3, 'VEGETABLES', 1, 222222.00, 222222.00, 'pending', '2026-10-03 12:01:18'),
(3, 3, 1, 3, 'VEGETABLES', 1, 1111.00, 1111.00, 'delivered', '2026-10-03 12:08:10');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `vendor_id`, `name`, `description`, `price`, `image`, `category_id`, `stock`, `status`, `created_at`, `updated_at`) VALUES
(1, 3, 'VEGETABLES', 'FOOD', 1111.00, 'uploads/products/product_3_1791024072_20d8e70423.jpg', 1, 11110, 'approved', '2026-10-03 10:41:12', '2026-10-03 12:08:10'),
(2, 3, 'VEGETABLES', 'NHVCAAAA', 2223422.00, 'uploads/products/product_3_1791026700_190cf1f911.jpg', 1, 1, '', '2026-10-03 11:25:01', '2026-10-03 14:46:58');

-- --------------------------------------------------------

--
-- Table structure for table `product_change_requests`
--

CREATE TABLE `product_change_requests` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `requested_name` varchar(255) DEFAULT NULL,
  `requested_description` text DEFAULT NULL,
  `requested_price` decimal(10,2) DEFAULT NULL,
  `requested_stock` int(11) DEFAULT NULL,
  `requested_category_id` int(11) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_change_requests`
--

INSERT INTO `product_change_requests` (`id`, `product_id`, `vendor_id`, `requested_name`, `requested_description`, `requested_price`, `requested_stock`, `requested_category_id`, `reason`, `status`, `reviewed_by`, `reviewed_at`, `review_note`, `created_at`, `updated_at`) VALUES
(1, 2, 3, 'VEGETABLES', 'NHVCJTTHCHJ', 2223422.00, 1, NULL, 'Test1', 'approved', 1, '2026-10-03 15:41:18', NULL, '2026-10-03 14:33:18', '2026-10-03 14:41:18'),
(2, 2, 3, 'VEGETABLES', 'NHVCJTTH', 2223422.00, 1, NULL, 'G', 'rejected', 1, '2026-10-03 15:51:17', 'YTFHGBJ,NKL', '2026-10-03 14:46:32', '2026-10-03 14:51:17');

-- --------------------------------------------------------

--
-- Table structure for table `product_reviews`
--

CREATE TABLE `product_reviews` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `review_text` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'store_name', 'GloryMarket', '2026-10-03 08:33:03'),
(2, 'store_email', '', '2026-10-03 08:33:03'),
(3, 'store_phone', '', '2026-10-03 08:33:03'),
(4, 'store_address', '', '2026-10-03 08:33:03'),
(5, 'currency', 'NGN', '2026-10-03 08:33:03'),
(6, 'delivery_fee', '0', '2026-10-03 08:33:03'),
(7, 'store_status', 'active', '2026-10-03 08:33:03'),
(8, 'maintenance_mode', '0', '2026-10-03 08:33:03');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('super_admin','admin','vendor','customer') NOT NULL DEFAULT 'customer',
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `email_verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `phone`, `profile_picture`, `password`, `role`, `status`, `email_verified_at`, `created_at`, `updated_at`) VALUES
(1, 'Glory Agbor', 'gloryagbor@gmail.com', '08035460123', NULL, '$2y$10$X7eW0l9aIdDc2doFFjFT3eYRB5lb2AkaGo..zcGT9dLsNnyin6O6G', 'super_admin', 'active', NULL, '2026-10-02 19:50:28', '2026-10-03 09:10:38'),
(2, 'Augustine Ntishor Akpotu', 'gloryagbor1@gmail.com', '07064820492', NULL, '$2y$10$4FO9bmOR2qCYJgegyLJG3elGZby95GiDzdeI5yr.PcjaSxJZ.9AqC', 'admin', 'active', NULL, '2026-10-02 21:06:23', '2026-10-02 21:06:23'),
(3, 'Glory Agbor', 'gloryagbor2@gmail.com', '07064820492', 'uploads/vendors/profiles/vendor_3_f417b39ccbb591ec8284bfc879704ea1.jpg', '$2y$10$ZEAe.bZhoWUgX2UbkJQQGurd6GyCBtz4dFAvrvNKUY3nJ2wmnRWaC', 'vendor', 'active', NULL, '2026-10-02 22:09:12', '2026-10-03 11:08:29'),
(7, 'PEACE A', 'peacea@gmail.com', '0706412354', NULL, '$2y$10$WTRevqHPIAyPHF9aJrhNJOLBRML3ag4dOEAGzLWfdjby8bW6suboC', 'customer', 'active', NULL, '2026-10-03 09:13:03', '2026-10-03 10:18:22');

-- --------------------------------------------------------

--
-- Table structure for table `vendor_order_confirmations`
--

CREATE TABLE `vendor_order_confirmations` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vendor_order_confirmations`
--

INSERT INTO `vendor_order_confirmations` (`id`, `order_id`, `vendor_id`, `confirmed_at`, `created_at`) VALUES
(1, 3, 3, '2026-10-03 13:34:24', '2026-10-03 12:34:24');

-- --------------------------------------------------------

--
-- Table structure for table `vendor_profiles`
--

CREATE TABLE `vendor_profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `store_name` varchar(150) NOT NULL,
  `store_slug` varchar(180) NOT NULL,
  `business_description` text DEFAULT NULL,
  `business_phone` varchar(20) DEFAULT NULL,
  `business_email` varchar(150) DEFAULT NULL,
  `business_address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Nigeria',
  `logo` varchar(255) DEFAULT NULL,
  `banner` varchar(255) DEFAULT NULL,
  `verification_status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vendor_profiles`
--

INSERT INTO `vendor_profiles` (`id`, `user_id`, `store_name`, `store_slug`, `business_description`, `business_phone`, `business_email`, `business_address`, `city`, `state`, `country`, `logo`, `banner`, `verification_status`, `created_at`, `updated_at`) VALUES
(1, 3, 'GLORY AGBOR', 'STORE', 'Seller', '09066124000', 'ntishor64@gmail.com', '99C Old Odukpani Road, Ikot Ansa, Calabar.', 'Calabar', 'Cross River State', 'Nigeria', NULL, NULL, 'verified', '2026-10-02 22:09:12', '2026-10-02 22:25:21');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_activity_logs`
--
ALTER TABLE `admin_activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_activity` (`admin_id`),
  ADD KEY `idx_activity_date` (`created_at`);

--
-- Indexes for table `admin_permissions`
--
ALTER TABLE `admin_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_admin_permission` (`admin_id`,`permission`),
  ADD KEY `fk_permission_granted_by` (`granted_by`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_category_name` (`name`);

--
-- Indexes for table `customer_profiles`
--
ALTER TABLE `customer_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `fk_orders_customer` (`customer_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_order_items_order` (`order_id`),
  ADD KEY `fk_order_items_product` (`product_id`),
  ADD KEY `fk_order_items_vendor` (`vendor_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_change_requests`
--
ALTER TABLE `product_change_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `reviewed_by` (`reviewed_by`);

--
-- Indexes for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_customer_product` (`customer_id`,`product_id`),
  ADD KEY `idx_product_status` (`product_id`,`status`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_order` (`order_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `vendor_order_confirmations`
--
ALTER TABLE `vendor_order_confirmations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_vendor_order` (`order_id`,`vendor_id`),
  ADD KEY `vendor_id` (`vendor_id`);

--
-- Indexes for table `vendor_profiles`
--
ALTER TABLE `vendor_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `store_slug` (`store_slug`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_activity_logs`
--
ALTER TABLE `admin_activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `admin_permissions`
--
ALTER TABLE `admin_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customer_profiles`
--
ALTER TABLE `customer_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `product_change_requests`
--
ALTER TABLE `product_change_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `vendor_order_confirmations`
--
ALTER TABLE `vendor_order_confirmations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `vendor_profiles`
--
ALTER TABLE `vendor_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_activity_logs`
--
ALTER TABLE `admin_activity_logs`
  ADD CONSTRAINT `fk_activity_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `admin_permissions`
--
ALTER TABLE `admin_permissions`
  ADD CONSTRAINT `fk_permission_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_permission_granted_by` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `customer_profiles`
--
ALTER TABLE `customer_profiles`
  ADD CONSTRAINT `fk_customer_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fk_order_items_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `product_change_requests`
--
ALTER TABLE `product_change_requests`
  ADD CONSTRAINT `product_change_requests_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_change_requests_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_change_requests_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD CONSTRAINT `fk_review_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_review_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_review_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vendor_order_confirmations`
--
ALTER TABLE `vendor_order_confirmations`
  ADD CONSTRAINT `vendor_order_confirmations_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vendor_order_confirmations_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vendor_profiles`
--
ALTER TABLE `vendor_profiles`
  ADD CONSTRAINT `fk_vendor_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

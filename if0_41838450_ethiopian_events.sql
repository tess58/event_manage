-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql104.infinityfree.com
-- Generation Time: May 12, 2026 at 05:35 AM
-- Server version: 11.4.10-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_41838450_ethiopian_events`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `qr_code` varchar(120) NOT NULL,
  `status` enum('pending','confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `ticket_number` varchar(40) DEFAULT NULL,
  `qr_code_data` text DEFAULT NULL,
  `validation_token` varchar(128) DEFAULT NULL,
  `payment_status` varchar(30) NOT NULL DEFAULT 'paid',
  `check_in_status` varchar(30) NOT NULL DEFAULT 'pending',
  `checked_in_at` datetime DEFAULT NULL,
  `ticket_type` varchar(50) NOT NULL DEFAULT 'Regular'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `user_id`, `event_id`, `qr_code`, `status`, `created_at`, `is_used`, `ticket_number`, `qr_code_data`, `validation_token`, `payment_status`, `check_in_status`, `checked_in_at`, `ticket_type`) VALUES
(1, 3, 1, 'QRTICKET-AB1234', 'cancelled', '2026-05-05 09:27:36', 0, NULL, NULL, NULL, 'paid', 'cancelled', '2026-05-08 03:09:48', 'Regular'),
(2, 3, 2, 'QRTICKET-BC2345', 'confirmed', '2026-05-05 09:27:36', 0, NULL, NULL, NULL, 'paid', 'pending', NULL, 'Regular');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`) VALUES
(2, 'Business'),
(6, 'fjiovjeo'),
(3, 'Food'),
(1, 'Music'),
(4, 'Sports');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(180) NOT NULL,
  `description` text NOT NULL,
  `date` date NOT NULL,
  `location` varchar(120) NOT NULL,
  `organizer_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `status` enum('published','draft') NOT NULL DEFAULT 'published',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `attendance_count` int(11) NOT NULL DEFAULT 0,
  `lifecycle_status` varchar(20) DEFAULT NULL,
  `event_time` time DEFAULT NULL,
  `capacity` int(11) DEFAULT 0,
  `registration_deadline` date DEFAULT NULL,
  `ticket_type` varchar(50) DEFAULT 'Regular'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `title`, `description`, `date`, `location`, `organizer_id`, `category_id`, `image_url`, `status`, `created_at`, `is_featured`, `attendance_count`, `lifecycle_status`, `event_time`, `capacity`, `registration_deadline`, `ticket_type`) VALUES
(1, 'Ethiopian Music Festival', 'Experience live music and cultural performances from Ethiopia\'s best artists.', '2025-05-29', 'Addis Ababa', 2, 1, 'https://images.unsplash.com/photo-1497032628192-86f99bcd76bc?auto=format&fit=crop&w=1200&q=80', 'published', '2026-05-05 09:27:36', 0, 1, NULL, NULL, 0, NULL, 'Regular'),
(2, 'Entrepreneur Summit 2025', 'Network with founders, investors, and business leaders at this high-energy summit.', '2025-06-10', 'Addis Ababa', 2, 2, 'https://images.unsplash.com/photo-1515169067865-5387ec356754?auto=format&fit=crop&w=1200&q=80', 'published', '2026-05-05 09:27:36', 0, 0, NULL, NULL, 0, NULL, 'Regular'),
(3, 'Ethiopian Food Festival', 'Taste authentic dishes, meet chefs, and enjoy a culinary celebration.', '2025-05-30', 'Bahir Dar', 2, 3, 'https://images.unsplash.com/photo-1498654896293-37aacf113fd9?auto=format&fit=crop&w=1200&q=80', 'published', '2026-05-05 09:27:36', 0, 0, NULL, NULL, 0, NULL, 'Regular'),
(4, 'Great Ethiopian Run', 'Join the city race and celebrate wellness with thousands of runners.', '2025-08-12', 'Addis Ababa', 2, 2, 'https://images.unsplash.com/photo-1521412644187-c49fa049e84d?auto=format&fit=crop&w=1200&q=80', 'published', '2026-05-05 09:27:36', 1, 0, NULL, NULL, 0, NULL, 'Regular');

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `favorites`
--

INSERT INTO `favorites` (`id`, `user_id`, `event_id`, `created_at`) VALUES
(1, 3, 1, '2026-05-08 09:37:47');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(30) NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 2, 'Organizer Panel Ready', 'You can now manage updates and announcements.', 'info', 0, '2026-05-08 10:00:29'),
(2, 2, 'Organizer Panel Ready', 'You can now manage updates and announcements.', 'info', 0, '2026-05-08 10:00:47'),
(3, 2, 'Announcement Drafted', 'Kwksjsjsjs', 'info', 0, '2026-05-08 10:00:47'),
(4, 2, 'Organizer Panel Ready', 'You can now manage updates and announcements.', 'info', 0, '2026-05-08 10:01:13'),
(5, 2, 'Organizer Panel Ready', 'You can now manage updates and announcements.', 'info', 0, '2026-05-08 10:01:18'),
(6, 2, 'Announcement Drafted', 'Kwksjsjsjs', 'info', 0, '2026-05-08 10:01:18'),
(7, 2, 'Organizer Panel Ready', 'You can now manage updates and announcements.', 'info', 1, '2026-05-08 10:01:22'),
(8, 2, 'Announcement Drafted', 'Kakalamamaydysvsban', 'info', 1, '2026-05-08 10:01:22'),
(9, 2, 'Organizer Panel Ready', 'You can now manage updates and announcements.', 'info', 0, '2026-05-08 10:01:34'),
(10, 2, 'Organizer Panel Ready', 'You can now manage updates and announcements.', 'info', 0, '2026-05-08 10:09:24'),
(11, 2, 'Organizer Panel Ready', 'You can now manage updates and announcements.', 'info', 0, '2026-05-11 18:38:17'),
(12, 2, 'Organizer Panel Ready', 'You can now manage updates and announcements.', 'info', 0, '2026-05-11 18:40:26');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `organizer_reply` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `user_id`, `event_id`, `rating`, `comment`, `created_at`, `organizer_reply`) VALUES
(1, 3, 1, 5, 'Amazing energy and live performances. Very memorable!', '2026-05-05 09:27:36', 'Thank you'),
(2, 3, 3, 4, 'Great food and welcoming atmosphere.', '2026-05-05 09:27:36', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','organizer','user') NOT NULL DEFAULT 'user',
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_blocked` tinyint(1) NOT NULL DEFAULT 0,
  `profile_image` varchar(255) DEFAULT NULL,
  `organization_name` varchar(150) DEFAULT NULL,
  `contact_info` varchar(150) DEFAULT NULL,
  `social_links` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `status`, `created_at`, `is_blocked`, `profile_image`, `organization_name`, `contact_info`, `social_links`) VALUES
(1, 'Admin User', 'admin@example.com', '$2y$10$ueb7YBkWxSp3EVsEoSWude4/bzFC6ID7ZTSfvYu53oQfPQnRTq1ce', 'admin', 'approved', '2026-05-05 09:27:36', 0, NULL, NULL, NULL, NULL),
(2, 'Event Organizer', 'organizer@example.com', '$2y$10$6WC7gWfgzcHkBaDQaOwyce3mMK6yEEGC1XYqnG9ZGAl4MFSkCFCcW', 'organizer', 'approved', '2026-05-05 09:27:36', 0, NULL, NULL, NULL, NULL),
(3, 'John Doe', 'user@example.com', '$2y$10$6WC7gWfgzcHkBaDQaOwyce3mMK6yEEGC1XYqnG9ZGAl4MFSkCFCcW', 'user', 'approved', '2026-05-05 09:27:36', 0, NULL, NULL, NULL, NULL),
(4, 'tess', 'tesfahunyosef8@gmail.com', '$2y$10$kPtZq/q126gV2UEBDT/NduKFvI877xwmZGHeVXIEK/Fa8fdSje5.6', 'user', 'approved', '2026-05-05 09:34:26', 0, NULL, NULL, NULL, NULL),
(13, 'tesfahun yosef', 'tess@gmail.com', '$2y$10$qOGFvPMj7w7sMGH9OLdB0.TiBs4V3KTnQk0yz33C.E.zs6PkDm6TS', 'user', 'approved', '2026-05-05 12:49:19', 0, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_notification_settings`
--

CREATE TABLE `user_notification_settings` (
  `user_id` int(11) NOT NULL,
  `booking_confirmation` tinyint(1) NOT NULL DEFAULT 1,
  `event_reminders` tinyint(1) NOT NULL DEFAULT 1,
  `event_updates` tinyint(1) NOT NULL DEFAULT 1,
  `cancellation_alerts` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_notification_settings`
--

INSERT INTO `user_notification_settings` (`user_id`, `booking_confirmation`, `event_reminders`, `event_updates`, `cancellation_alerts`, `updated_at`) VALUES
(3, 1, 1, 1, 1, '2026-05-08 10:02:14');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `organizer_id` (`organizer_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_event` (`user_id`,`event_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_notification_settings`
--
ALTER TABLE `user_notification_settings`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`organizer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `events_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Constraints for table `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_notification_settings`
--
ALTER TABLE `user_notification_settings`
  ADD CONSTRAINT `user_notification_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

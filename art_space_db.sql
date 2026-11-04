-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 04, 2026 at 01:55 PM
-- Server version: 10.11.16-MariaDB
-- PHP Version: 8.3.36

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `art_space_db` 
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `art_space_db`;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `art_space_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `artworks`
--

DROP TABLE IF EXISTS `artworks`;
CREATE TABLE IF NOT EXISTS `artworks` (
  `artwork_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`artwork_id`),
  KEY `idx_artworks_user` (`user_id`),
  KEY `idx_artworks_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `artworks`
--

INSERT INTO `artworks` (`artwork_id`, `user_id`, `title`, `description`, `category`, `image_path`, `created_at`) VALUES
(2, 2, 'cute momonga', 'i love momonga so much', 'Digital Drawing', 'uploads/6e35f98c12be4c61421fd385ec6cde49.jpg', '2026-10-29 11:56:50'),
(3, 2, 'chiikawa chilling', 'chiikawa is so chill', 'Digital Drawing', 'uploads/1e95a70b9e84bdbf5b3406697688c149.jpg', '2026-10-29 12:02:44'),
(6, 5, 'Legally an Adult', 'Legally not a kid.', 'Digital Drawing', 'uploads/37405190bcaa181cdcae3ffb8767f22e.png', '2026-11-04 12:46:42');

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

DROP TABLE IF EXISTS `comments`;
CREATE TABLE IF NOT EXISTS `comments` (
  `comment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `artwork_id` int(10) UNSIGNED NOT NULL,
  `comment_text` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`comment_id`),
  KEY `idx_comments_artwork` (`artwork_id`),
  KEY `fk_comments_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `comments`
--

INSERT INTO `comments` (`comment_id`, `user_id`, `artwork_id`, `comment_text`, `created_at`) VALUES
(2, 3, 2, 'soooo cute!!!', '2026-10-29 11:57:45'),
(3, 4, 2, 'this is awesome', '2026-10-29 14:35:45'),
(4, 5, 2, 'Peak ahh', '2026-11-04 12:47:12');

-- --------------------------------------------------------

--
-- Table structure for table `ratings`
--

DROP TABLE IF EXISTS `ratings`;
CREATE TABLE IF NOT EXISTS `ratings` (
  `rating_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `artwork_id` int(10) UNSIGNED NOT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`rating_id`),
  UNIQUE KEY `unique_user_artwork` (`user_id`,`artwork_id`),
  KEY `idx_ratings_artwork` (`artwork_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ratings`
--

INSERT INTO `ratings` (`rating_id`, `user_id`, `artwork_id`, `rating`, `created_at`, `updated_at`) VALUES
(2, 3, 2, 5, '2026-10-29 11:57:32', '2026-10-29 11:57:36'),
(4, 3, 3, 5, '2026-10-29 12:03:16', '2026-10-29 12:44:18'),
(8, 4, 2, 5, '2026-10-29 14:35:28', '2026-10-29 14:35:28'),
(9, 5, 2, 5, '2026-11-04 12:47:03', '2026-11-04 12:47:03');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `bio` text DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `bio`, `profile_picture`, `created_at`) VALUES
(1, 'alp', 'alfguisinga15@gmail.com', '$2y$12$wKWTSh2b.H5vLr6SFgzk5uC5342J0RCDh9xlLWnwzPBbpJHNO6AZi', NULL, NULL, '2026-10-29 10:20:09'),
(2, 'alpp', 'roseblink1204@gmail.com', '$2y$12$zwU3Ld5qfstUQlNUxg.yPe.BpOdIcyY8DvzxrALar81GRBz7oLWui', 'i love momonga', 'uploads/profile_pictures/4486f4bce440dd4995d663261932436b.png', '2026-10-29 10:46:40'),
(3, 'momonger', 'guisingaalframesesaninion@gmail.com', '$2y$12$/FNCcXbQ3o6mRhILQQ8FgOTec4tAoEKtBSwvbWDsvGoTV4uYWrUYe', NULL, NULL, '2026-10-29 11:31:34'),
(4, 'chimaw', 'chimmynana8@gmail.com', '$2y$12$onFjexd0RdBW7p2SEhphmO2zx2BaCppkFTK6/Xo.eqryMGuBHaLP2', NULL, NULL, '2026-10-29 14:35:11'),
(5, 'Test', 'test@gmail.com', '$2y$10$SIwO1Ic1LUZ5I1msyXk2Tup216Jgp3BiPngALqhu4saQqU87YT6IG', NULL, NULL, '2026-11-04 12:39:42');

--
-- Constraints for dumped tables
--

--
-- Constraints for table `artworks`
--
ALTER TABLE `artworks`
  ADD CONSTRAINT `fk_artworks_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `fk_comments_artwork` FOREIGN KEY (`artwork_id`) REFERENCES `artworks` (`artwork_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `ratings`
--
ALTER TABLE `ratings`
  ADD CONSTRAINT `fk_ratings_artwork` FOREIGN KEY (`artwork_id`) REFERENCES `artworks` (`artwork_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ratings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

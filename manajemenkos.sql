-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 13 Nov 2026 pada 00.24
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `manajemenkos`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `penghuni`
--

CREATE TABLE IF NOT EXISTS `penghuni` (
  `id_penghuni` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `nama_penghuni` varchar(100) NOT NULL,
  `no_telepon` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_penghuni`),
  UNIQUE KEY `penghuni_user_id_unique` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `penghuni`
--

INSERT INTO `penghuni` (`id_penghuni`, `user_id`, `nama_penghuni`, `no_telepon`, `email`, `alamat`, `created_at`, `updated_at`) VALUES
(1, 2, 'Penghuni Uji', '081234567890', 'penghuni@gmail.com', 'Cirebon', '2026-11-05 02:21:22', '2026-11-05 02:21:22'),
(2, NULL, 'Teh Erin', '+62 853-1852-8347', 'erin@gmail.com', 'Cirebon', NULL, NULL),
(3, NULL, 'Teh Azizah', '+62 896-7788-3738', 'azizah@gmail.com', 'Cirebon', NULL, NULL),
(4, NULL, 'Aca', '+62 838-3285-0093', 'aca@gmail.com', 'Cirebon', NULL, NULL),
(5, NULL, 'Risma', '+62 822-4260-0038', 'risma@gmail.com', 'Cirebon', NULL, NULL),
(6, NULL, 'Syifa', '+62 895-6350-08600', 'syifa@gmail.com', 'Cirebon', NULL, NULL),
(7, NULL, 'Indah', '+62 831-2016-4396', 'indah@gmail.com', 'Cirebon', NULL, NULL),
(8, NULL, 'Nunu', '+62 838-7955-5420', 'nunu@gmail.com', 'Kalibuntu', NULL, NULL),
(9, NULL, 'Syahra', '+62 823-1656-0422', 'syahra@gmail.com', 'Cirebon', NULL, NULL),
(10, NULL, 'Nunun', '+62 812-1414-5457', 'nunun@gmail.com', 'Cirebon', NULL, NULL),
(11, NULL, 'Jijah', '+62 838-2524-8419', 'jijah@gmail.com', 'Kalibuntu', NULL, NULL),
(12, NULL, 'Elza', '+62 838-4146-0957', 'elza@gmail.com', 'Losari', NULL, NULL),
(13, NULL, 'Salsa', '+62 831-2919-9063', 'salsa@gmail.com', 'Cirebon', NULL, NULL),
(14, NULL, 'Dyas', '+62 822-8678-2116', 'dyas@gmail.com', 'Cirebon', NULL, NULL),
(16, NULL, 'cici', NULL, NULL, NULL, '2026-11-12 15:49:37', '2026-11-12 15:49:37');

-- --------------------------------------------------------

--
-- Struktur dari tabel `penghunian`
--

CREATE TABLE IF NOT EXISTS `penghunian` (
  `id_penghunian` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_kamar` bigint(20) UNSIGNED NOT NULL,
  `id_penghuni` bigint(20) UNSIGNED NOT NULL,
  `tanggal_mulai` date DEFAULT NULL,
  `tanggal_selesai` date DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'aktif',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `penyewa_utama` tinyint(1) NOT NULL DEFAULT 0,
  `skema_sewa` varchar(255) DEFAULT NULL,
  `total_sewa` decimal(12,2) DEFAULT NULL,
  `dp_minimum` decimal(12,2) DEFAULT NULL,
  `tenggat_pelunasan` date DEFAULT NULL,
  `status_pembayaran` varchar(20) NOT NULL DEFAULT 'belum_lunas',
  PRIMARY KEY (`id_penghunian`),
  KEY `penghunian_id_kamar_foreign` (`id_kamar`),
  KEY `penghunian_id_penghuni_foreign` (`id_penghuni`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `penghunian`
--

INSERT INTO `penghunian` (`id_penghunian`, `id_kamar`, `id_penghuni`, `tanggal_mulai`, `tanggal_selesai`, `status`, `created_at`, `updated_at`, `penyewa_utama`, `skema_sewa`, `total_sewa`, `dp_minimum`, `tenggat_pelunasan`, `status_pembayaran`) VALUES
(1, 2, 2, '2020-07-13', NULL, 'aktif', '2026-11-05 02:21:22', '2026-11-11 19:41:32', 1, NULL, NULL, NULL, NULL, 'belum_lunas'),
(2, 4, 3, '2024-08-25', NULL, 'aktif', '2026-11-05 02:21:22', '2026-11-05 02:21:22', 0, NULL, NULL, NULL, NULL, 'belum_lunas'),
(3, 5, 4, '2026-08-22', NULL, 'aktif', '2026-11-05 02:21:22', '2026-11-05 02:21:22', 0, NULL, NULL, NULL, NULL, 'belum_lunas'),
(4, 5, 5, '2026-08-22', NULL, 'aktif', '2026-11-05 02:21:22', '2026-11-05 02:21:22', 0, NULL, NULL, NULL, NULL, 'belum_lunas'),
(5, 6, 6, '2026-08-17', NULL, 'aktif', '2026-11-05 02:21:22', '2026-11-05 02:21:22', 0, NULL, NULL, NULL, NULL, 'belum_lunas'),
(6, 8, 7, '2026-08-15', NULL, 'aktif', '2026-11-05 02:21:22', '2026-11-05 02:21:22', 0, NULL, NULL, NULL, NULL, 'belum_lunas'),
(7, 8, 8, '2026-08-15', NULL, 'aktif', '2026-11-05 02:21:22', '2026-11-05 02:21:22', 0, NULL, NULL, NULL, NULL, 'belum_lunas'),
(8, 12, 9, '2025-08-20', NULL, 'aktif', '2026-11-05 02:21:22', '2026-11-05 02:21:22', 0, NULL, NULL, NULL, NULL, 'belum_lunas'),
(9, 12, 10, '2025-08-20', NULL, 'aktif', '2026-11-05 02:21:22', '2026-11-05 02:21:22', 0, NULL, NULL, NULL, NULL, 'belum_lunas'),
(10, 13, 11, '2024-08-25', NULL, 'aktif', '2026-11-05 02:21:22', '2026-11-05 02:21:22', 0, NULL, NULL, NULL, NULL, 'belum_lunas'),
(11, 13, 12, '2024-08-25', NULL, 'aktif', '2026-11-05 02:21:22', '2026-11-05 02:21:22', 0, NULL, NULL, NULL, NULL, 'belum_lunas'),
(12, 22, 13, '2026-09-30', NULL, 'aktif', '2026-11-05 02:21:22', '2026-11-05 02:21:22', 0, NULL, NULL, NULL, NULL, 'belum_lunas'),
(13, 23, 14, '2025-08-20', NULL, 'aktif', '2026-11-05 02:21:22', '2026-11-05 02:21:22', 0, NULL, NULL, NULL, NULL, 'belum_lunas'),
(15, 1, 16, NULL, NULL, 'aktif', '2026-11-12 15:49:37', '2026-11-12 15:49:47', 1, 'tahunan', 5000000.00, 300000.00, NULL, 'belum_lunas');

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `penghuni`
--
ALTER TABLE `penghuni`
  ADD CONSTRAINT `penghuni_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `penghunian`
--
ALTER TABLE `penghunian`
  ADD CONSTRAINT `penghunian_id_kamar_foreign` FOREIGN KEY (`id_kamar`) REFERENCES `kamar` (`id_kamar`) ON DELETE CASCADE,
  ADD CONSTRAINT `penghunian_id_penghuni_foreign` FOREIGN KEY (`id_penghuni`) REFERENCES `penghuni` (`id_penghuni`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

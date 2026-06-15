-- ============================================================================
--  PATCH cPanel  —  database: anugra43_obe
--  Menyamakan DB produksi (snapshot 04-Jun, dump anugra43_obe.sql) dengan
--  revisi aplikasi terbaru (08-Jun).
--
--  CARA PAKAI (phpMyAdmin, TANPA Terminal):
--    1. Import dulu file anugra43_obe.sql ke database anugra43_obe (jika belum).
--    2. Buka phpMyAdmin -> pilih database `anugra43_obe`.
--    3. Klik tab "SQL" -> tempel SELURUH isi file ini -> klik "Go/Kirim".
--
--  >> Jalankan CUKUP SEKALI setelah import. <<
--  (Aman & hanya menambah: kolom bobot + isi bobot + 4 tabel baru.
--   Data nilai/kelas yang sudah ada TIDAK diubah/dihapus.)
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1) Kolom bobot yang HILANG  (akar bug "total nilai tidak muncul")
-- ----------------------------------------------------------------------------
ALTER TABLE `obe_cpmk`
  ADD COLUMN `percentage`    decimal(5,2) NOT NULL DEFAULT 0.00 AFTER `description`,
  ADD COLUMN `meeting_start` tinyint UNSIGNED NULL              AFTER `percentage`,
  ADD COLUMN `meeting_end`   tinyint UNSIGNED NULL              AFTER `meeting_start`;

ALTER TABLE `obe_indikator`
  ADD COLUMN `percentage` decimal(5,2) NOT NULL DEFAULT 0.00 AFTER `description`;

-- ----------------------------------------------------------------------------
-- 2) Isi bobot CPMK: dibagi rata per mata kuliah  (generik, mengikuti data apa pun)
-- ----------------------------------------------------------------------------
UPDATE `obe_cpmk` c
JOIN (SELECT `course_id`, COUNT(*) AS n FROM `obe_cpmk` GROUP BY `course_id`) g
  ON g.`course_id` = c.`course_id`
SET c.`percentage` = ROUND(100 / g.n, 2);

-- ----------------------------------------------------------------------------
-- 3) Isi bobot Indikator (Sub-CPMK): dibagi rata per CPMK
-- ----------------------------------------------------------------------------
UPDATE `obe_indikator` i
JOIN (SELECT `cpmk_id`, COUNT(*) AS n FROM `obe_indikator` GROUP BY `cpmk_id`) g
  ON g.`cpmk_id` = i.`cpmk_id`
SET i.`percentage` = ROUND(100 / g.n, 2);

-- ----------------------------------------------------------------------------
-- 4) Rentang pertemuan (kosmetik) untuk data pada dump ini.
--    Jika nanti data CPMK berbeda, cukup buka & simpan ulang CPMK dari menu
--    Kaprodi — sistem akan menghitung ulang pertemuan otomatis.
-- ----------------------------------------------------------------------------
UPDATE `obe_cpmk` SET `meeting_start` = 1,  `meeting_end` = 5  WHERE `id` IN (6, 9);
UPDATE `obe_cpmk` SET `meeting_start` = 6,  `meeting_end` = 10 WHERE `id` IN (7, 10);
UPDATE `obe_cpmk` SET `meeting_start` = 11, `meeting_end` = 15 WHERE `id` IN (8, 11);
UPDATE `obe_cpmk` SET `meeting_start` = 1,  `meeting_end` = 8  WHERE `id` = 12;
UPDATE `obe_cpmk` SET `meeting_start` = 9,  `meeting_end` = 16 WHERE `id` = 13;

-- ----------------------------------------------------------------------------
-- 5) Tabel dimensi BARU: Bahan Kajian + pivot (PL×CPL, CPL×BK, MK×BK)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `obe_bahan_kajian` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `program_studi_id` bigint UNSIGNED DEFAULT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `obe_bahan_kajian_program_studi_id_foreign` (`program_studi_id`),
  CONSTRAINT `obe_bahan_kajian_program_studi_id_foreign` FOREIGN KEY (`program_studi_id`) REFERENCES `obe_program_studi` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `obe_cpl_bahan_kajian` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `cpl_id` bigint UNSIGNED NOT NULL,
  `bahan_kajian_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `obe_cpl_bk_unique` (`cpl_id`, `bahan_kajian_id`),
  KEY `obe_cpl_bk_bahan_kajian_id_foreign` (`bahan_kajian_id`),
  CONSTRAINT `obe_cpl_bk_cpl_id_foreign` FOREIGN KEY (`cpl_id`) REFERENCES `obe_cpl` (`id`) ON DELETE CASCADE,
  CONSTRAINT `obe_cpl_bk_bahan_kajian_id_foreign` FOREIGN KEY (`bahan_kajian_id`) REFERENCES `obe_bahan_kajian` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `obe_mata_kuliah_bahan_kajian` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_id` bigint UNSIGNED NOT NULL,
  `bahan_kajian_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `obe_mk_bk_unique` (`course_id`, `bahan_kajian_id`),
  KEY `obe_mk_bk_bahan_kajian_id_foreign` (`bahan_kajian_id`),
  CONSTRAINT `obe_mk_bk_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `obe_mata_kuliah` (`id`) ON DELETE CASCADE,
  CONSTRAINT `obe_mk_bk_bahan_kajian_id_foreign` FOREIGN KEY (`bahan_kajian_id`) REFERENCES `obe_bahan_kajian` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `obe_profil_lulusan_cpl` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `graduate_profile_id` bigint UNSIGNED NOT NULL,
  `cpl_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `obe_pl_cpl_unique` (`graduate_profile_id`, `cpl_id`),
  KEY `obe_pl_cpl_cpl_id_foreign` (`cpl_id`),
  CONSTRAINT `obe_pl_cpl_graduate_profile_id_foreign` FOREIGN KEY (`graduate_profile_id`) REFERENCES `obe_profil_lulusan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `obe_pl_cpl_cpl_id_foreign` FOREIGN KEY (`cpl_id`) REFERENCES `obe_cpl` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6) Tandai 5 migrasi sebagai SUDAH dijalankan (batch 6) supaya Laravel
--    tidak mencoba menjalankannya lagi bila kelak `php artisan migrate` dipanggil.
-- ----------------------------------------------------------------------------
INSERT INTO `migrations` (`migration`, `batch`) VALUES
('2026_06_06_100000_create_obe_bahan_kajian_table', 6),
('2026_06_06_100100_create_obe_cpl_bahan_kajian_table', 6),
('2026_06_06_100200_create_obe_mata_kuliah_bahan_kajian_table', 6),
('2026_06_06_100300_create_obe_profil_lulusan_cpl_table', 6),
('2026_06_07_161424_restore_percentage_and_meetings_on_obe_cpmk_indikator', 6);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
--  SELESAI. Cek cepat (opsional): jalankan query berikut, harus muncul kolom
--  percentage berisi 33.33 / 50.00 dst, BUKAN kosong:
--    SELECT id, code, percentage, meeting_start, meeting_end FROM obe_cpmk;
--    SELECT id, cpmk_id, percentage FROM obe_indikator;
-- ============================================================================

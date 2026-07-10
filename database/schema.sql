-- ============================================================
-- DigitLKS — Database Schema
-- Versi: 1.0
-- Dibuat: 2026-04
-- ============================================================
-- Import file ini melalui phpMyAdmin atau jalankan via MySQL CLI:
--   mysql -u username -p nama_database < schema.sql
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- Tabel 1: students
-- Master data siswa yang terdaftar oleh setiap guru/admin.
-- NIS dipakai sebagai identitas saat mengikuti ujian.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `students` (
    `id`            INT          NOT NULL AUTO_INCREMENT,
    `guru_username` VARCHAR(100) NOT NULL COMMENT 'Username admin/guru pemilik data',
    `nis`           VARCHAR(50)  NOT NULL COMMENT 'Nomor Induk Siswa',
    `nama`          VARCHAR(255) NOT NULL COMMENT 'Nama lengkap siswa',
    `kelas`         VARCHAR(50)  NOT NULL DEFAULT '' COMMENT 'Kelas siswa, contoh: 6A / IX-B / XII IPA',
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_siswa` (`guru_username`, `nis`) COMMENT 'Satu NIS unik per guru'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel 2: student_results
-- Rekap hasil pengerjaan soal oleh siswa.
-- Data disimpan otomatis setelah siswa mengklik "Kumpulkan".
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `student_results` (
    `id`             INT          NOT NULL AUTO_INCREMENT,
    `guru_username`  VARCHAR(100) NOT NULL COMMENT 'Username guru pemilik soal',
    `soal_id`        VARCHAR(255) NOT NULL COMMENT 'Nama file JSON soal (batch_xxx.json)',
    `soal_judul`     VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Judul soal saat dikerjakan',
    `mata_pelajaran` VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'Mata pelajaran',
    -- Identitas siswa
    `nama_siswa`     VARCHAR(255) NOT NULL COMMENT 'Nama siswa dari tabel students',
    `nis`            VARCHAR(50)  NOT NULL DEFAULT '' COMMENT 'NIS siswa',
    -- Hasil evaluasi
    `skor`           DECIMAL(5,2) NOT NULL DEFAULT 0 COMMENT 'Skor akhir (0-100)',
    `total_soal`     INT          NOT NULL DEFAULT 0,
    `jumlah_benar`   INT          NOT NULL DEFAULT 0,
    `jumlah_salah`   INT          NOT NULL DEFAULT 0,
    -- Waktu
    `waktu_kumpul`   DATETIME     NOT NULL COMMENT 'Waktu siswa mengumpulkan jawaban (UTC)',
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    -- Mencegah duplikasi jika halaman di-reload setelah submit
    UNIQUE KEY `uq_result` (`soal_id`, `nama_siswa`, `nis`),
    -- Index untuk mempercepat query laporan
    KEY `idx_guru`  (`guru_username`),
    KEY `idx_nis`   (`nis`),
    KEY `idx_mapel` (`mata_pelajaran`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Catatan Penggunaan:
-- 1. Tabel di atas juga akan dibuat OTOMATIS oleh aplikasi
--    saat koneksi MySQL pertama berhasil (via migrate()).
-- 2. File ini berguna sebagai referensi atau import manual
--    di phpMyAdmin jika user DB tidak punya hak CREATE TABLE.
-- 3. Pastikan charset database = utf8mb4 agar emoji & karakter
--    khusus tersimpan dengan benar.
-- ============================================================

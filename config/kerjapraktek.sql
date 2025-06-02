-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: May 27, 2025 at 06:48 PM
-- Server version: 8.4.3
-- PHP Version: 8.3.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `kerja_praktek`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_prodi`
--

CREATE TABLE `admin_prodi` (
  `id_admin` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `nama_admin` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email_admin` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_prodi`
--

INSERT INTO `admin_prodi` (`id_admin`, `username`, `password`, `nama_admin`, `email_admin`, `created_at`, `updated_at`) VALUES
(1, 'admin_utama', 'hashed_password_1', 'Admin Utama Kampus', 'admin.utama@kampus.ac.id', '2025-05-27 18:37:59', '2025-05-27 18:37:59'),
(2, 'admin_fakultas_teknik', 'hashed_password_2', 'Admin Fakultas Teknik', 'admin.ft@kampus.ac.id', '2025-05-27 18:37:59', '2025-05-27 18:37:59'),
(3, 'staf_prodi_if', 'hashed_password_3', 'Staf Prodi Informatika', 'staf.if@kampus.ac.id', '2025-05-27 18:37:59', '2025-05-27 18:37:59'),
(4, 'staf_prodi_si', 'hashed_password_4', 'Staf Prodi Sistem Informasi', 'staf.si@kampus.ac.id', '2025-05-27 18:37:59', '2025-05-27 18:37:59'),
(5, 'akademik_01', 'hashed_password_5', 'Staf Akademik 01', 'akad.01@kampus.ac.id', '2025-05-27 18:37:59', '2025-05-27 18:37:59'),
(6, 'akademik_02', 'hashed_password_6', 'Staf Akademik 02', 'akad.02@kampus.ac.id', '2025-05-27 18:37:59', '2025-05-27 18:37:59'),
(7, 'koordinator_kp', 'hashed_password_7', 'Koordinator KP Pusat', 'koor.kp@kampus.ac.id', '2025-05-27 18:37:59', '2025-05-27 18:37:59'),
(8, 'sekretariat_ft', 'hashed_password_8', 'Sekretariat Fakultas Teknik', 'sekre.ft@kampus.ac.id', '2025-05-27 18:37:59', '2025-05-27 18:37:59'),
(9, 'helpdesk_akademik', 'hashed_password_9', 'Helpdesk Akademik', 'help.akad@kampus.ac.id', '2025-05-27 18:37:59', '2025-05-27 18:37:59'),
(10, 'support_kampus', 'hashed_password_10', 'Support Sistem Kampus', 'support.kampus@kampus.ac.id', '2025-05-27 18:37:59', '2025-05-27 18:37:59');

-- --------------------------------------------------------

--
-- Table structure for table `bimbingan_kp`
--

CREATE TABLE `bimbingan_kp` (
  `id_bimbingan` int NOT NULL,
  `id_pengajuan` int NOT NULL,
  `nip_pembimbing` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal_bimbingan` datetime NOT NULL,
  `topik_bimbingan` text COLLATE utf8mb4_general_ci,
  `catatan_mahasiswa` text COLLATE utf8mb4_general_ci,
  `catatan_dosen` text COLLATE utf8mb4_general_ci,
  `file_lampiran_mahasiswa` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `file_lampiran_dosen` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status_bimbingan` enum('diajukan_mahasiswa','direview_dosen','selesai') COLLATE utf8mb4_general_ci DEFAULT 'diajukan_mahasiswa',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bimbingan_kp`
--

INSERT INTO `bimbingan_kp` (`id_bimbingan`, `id_pengajuan`, `nip_pembimbing`, `tanggal_bimbingan`, `topik_bimbingan`, `catatan_mahasiswa`, `catatan_dosen`, `file_lampiran_mahasiswa`, `file_lampiran_dosen`, `status_bimbingan`, `created_at`, `updated_at`) VALUES
(11, 1, 'NIP19801101', '2025-07-15 10:00:00', 'Review Bab 1: Pendahuluan', 'Draft awal pendahuluan.', 'Perjelas rumusan masalah.', 'path/to/bimb1_mhs_1.pdf', NULL, 'selesai', '2025-05-27 18:47:49', '2025-05-27 18:47:49'),
(12, 1, 'NIP19801101', '2025-08-01 14:30:00', 'Pembahasan Desain UI Aplikasi', 'Mockup UI sudah dibuat.', 'Pastikan konsistensi desain.', NULL, 'path/to/bimb1_dsn_rev1.txt', 'selesai', '2025-05-27 18:47:49', '2025-05-27 18:47:49'),
(13, 2, 'NIP19821202', '2025-07-20 09:00:00', 'Metodologi Pengumpulan Data', 'Rencana wawancara dan kuesioner.', 'Validasi instrumen dulu.', 'path/to/bimb2_mhs_1.docx', NULL, 'selesai', '2025-05-27 18:47:49', '2025-05-27 18:47:49'),
(14, 3, 'NIP19750103', '2025-06-30 11:00:00', 'Diskusi Arsitektur Sistem', 'Draft arsitektur sistem kontrol.', 'Pertimbangkan aspek keamanan.', NULL, NULL, 'direview_dosen', '2025-05-27 18:47:49', '2025-05-27 18:47:49'),
(15, 4, 'NIP19850204', '2025-08-10 13:00:00', 'Pembahasan Awal Proposal KP', 'Pemaparan rencana studi kasus.', 'Fokuskan pada satu aspek cloud.', 'path/to/bimb4_mhs_draft.pdf', NULL, 'diajukan_mahasiswa', '2025-05-27 18:47:49', '2025-05-27 18:47:49'),
(16, 5, 'NIP19900406', '2025-08-20 10:00:00', 'Konfirmasi Topik dan Pembimbingan', 'Permohonan bimbingan.', 'Topik menarik, silakan dilanjutkan.', NULL, NULL, 'selesai', '2025-05-27 18:47:49', '2025-05-27 18:47:49'),
(17, 7, 'NIP19880507', '2025-10-15 15:00:00', 'Review Draft Laporan Akhir KP', 'Laporan KP hampir selesai.', 'Revisi bagian analisis hasil.', 'path/to/bimb7_mhs_lap.pdf', 'path/to/bimb7_dsn_rev.txt', 'selesai', '2025-05-27 18:47:49', '2025-05-27 18:47:49'),
(18, 8, 'NIP19790608', '2025-08-25 16:00:00', 'Penyempurnaan Judul KP', 'Beberapa alternatif judul.', 'Judul ke-3 lebih spesifik.', NULL, NULL, 'direview_dosen', '2025-05-27 18:47:49', '2025-05-27 18:47:49'),
(19, 1, 'NIP19801101', '2025-09-05 09:30:00', 'Finalisasi Bab 2 & 3', 'Perbaikan Bab Tinjauan Pustaka & Metodologi.', 'Sudah cukup baik, lanjutkan.', 'path/to/bimb1_mhs_bab23.pdf', NULL, 'selesai', '2025-05-27 18:47:49', '2025-05-27 18:47:49'),
(20, 2, 'NIP19821202', '2025-08-15 11:00:00', 'Progres Analisis Data Awal', 'Data primer sudah terkumpul sebagian.', 'Perhatikan etika penelitian.', NULL, NULL, 'selesai', '2025-05-27 18:47:49', '2025-05-27 18:47:49');

-- --------------------------------------------------------

--
-- Table structure for table `dokumen_kp`
--

CREATE TABLE `dokumen_kp` (
  `id_dokumen` int NOT NULL,
  `id_pengajuan` int NOT NULL,
  `uploader_id` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `tipe_uploader` enum('mahasiswa','dosen','admin') COLLATE utf8mb4_general_ci NOT NULL,
  `nama_dokumen` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `jenis_dokumen` enum('ktm','khs','proposal_kp','surat_pengantar_kp','surat_balasan_perusahaan','laporan_kemajuan','draft_laporan_akhir','laporan_akhir_final','lembar_pengesahan','sertifikat_kp','form_penilaian_perusahaan','form_penilaian_dosen','lainnya') COLLATE utf8mb4_general_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `deskripsi` text COLLATE utf8mb4_general_ci,
  `status_verifikasi_dokumen` enum('pending','disetujui','revisi_diperlukan','ditolak') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `catatan_verifikator` text COLLATE utf8mb4_general_ci,
  `tanggal_upload` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dokumen_kp`
--

INSERT INTO `dokumen_kp` (`id_dokumen`, `id_pengajuan`, `uploader_id`, `tipe_uploader`, `nama_dokumen`, `jenis_dokumen`, `file_path`, `deskripsi`, `status_verifikasi_dokumen`, `catatan_verifikator`, `tanggal_upload`) VALUES
(1, 1, 'NIM24001', 'mahasiswa', 'KTM Ahmad Zulkifli', 'ktm', 'path/to/ktm_NIM24001.pdf', 'Kartu Tanda Mahasiswa Aktif', 'disetujui', 'Verified', '2025-05-27 18:48:15'),
(2, 1, 'NIM24001', 'mahasiswa', 'Proposal KP E-Learning', 'proposal_kp', 'path/to/proposal_NIM24001.pdf', 'Proposal untuk KP di GlobalTech', 'disetujui', 'Sesuai template', '2025-05-27 18:48:15'),
(3, 2, 'NIM24002', 'mahasiswa', 'KHS Bella Swan', 'khs', 'path/to/khs_NIM24002.pdf', 'Transkrip Nilai Sementara', 'disetujui', 'Lengkap', '2025-05-27 18:48:15'),
(4, 3, 'NIP19750103', 'dosen', 'Form Nilai dari CV. Manufaktur Andalan', 'form_penilaian_perusahaan', 'path/to/nilai_NIM23003.pdf', 'Nilai dari pembimbing lapangan Charlie', 'disetujui', 'Diterima', '2025-05-27 18:48:15'),
(5, 1, 'staf_prodi_if', 'admin', 'Surat Pengantar KP Ahmad Z.', 'surat_pengantar_kp', 'path/to/gen_sp_NIM24001.pdf', 'Surat Pengantar resmi dari Prodi', 'disetujui', 'Sudah dikirim ke perusahaan', '2025-05-27 18:48:15'),
(6, 7, 'NIM24007', 'mahasiswa', 'Laporan Akhir KP Gojo Satoru (Final)', 'laporan_akhir_final', 'path/to/lapfinal_NIM24007.pdf', 'Laporan akhir KP yang sudah disetujui.', 'disetujui', 'OK', '2025-05-27 18:48:15'),
(7, 7, 'NIM24007', 'mahasiswa', 'Sertifikat KP dari CV. Manufaktur Andalan', 'sertifikat_kp', 'path/to/sertif_NIM24007.jpg', 'Sertifikat telah menyelesaikan KP', 'pending', 'Menunggu verifikasi', '2025-05-27 18:48:15'),
(8, 4, 'NIM23004', 'mahasiswa', 'Draft Proposal Studi Keamanan Data', 'proposal_kp', 'path/to/draftprop_NIM23004.pdf', 'Draft awal untuk review dosen', 'revisi_diperlukan', 'Tambahkan studi literatur yang relevan', '2025-05-27 18:48:15'),
(9, 5, 'NIM24005', 'mahasiswa', 'Surat Balasan dari PT. Konstruksi Bangsa', 'surat_balasan_perusahaan', 'path/to/balasan_NIM24005.pdf', 'Konfirmasi penerimaan KP', 'pending', NULL, '2025-05-27 18:48:15'),
(10, 10, 'NIM22010', 'mahasiswa', 'Silabus Studi Independen AR', 'lainnya', 'path/to/silabus_NIM22010.pdf', 'Rancangan materi studi independen AR', 'disetujui', 'Lengkap dan jelas', '2025-05-27 18:48:15');

-- --------------------------------------------------------

--
-- Table structure for table `dosen_pembimbing`
--

CREATE TABLE `dosen_pembimbing` (
  `nip` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `nama_dosen` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `status_akun` enum('active','inactive') COLLATE utf8mb4_general_ci DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dosen_pembimbing`
--

INSERT INTO `dosen_pembimbing` (`nip`, `password`, `nama_dosen`, `email`, `status_akun`, `created_at`, `updated_at`) VALUES
('NIP19700305', 'pass_dosen_5', 'Dr. Ir. Naruto Uzumaki, M.Sc.', 'naruto.u@kampus.ac.id', 'inactive', '2025-05-27 18:38:51', '2025-05-27 18:38:51'),
('NIP19750103', 'pass_dosen_3', 'Dr. Michael Scofield, S.T., M.Eng.', 'michael.s@kampus.ac.id', 'active', '2025-05-27 18:38:51', '2025-05-27 18:38:51'),
('NIP19790608', 'pass_dosen_8', 'Quinn Fabray, S.Kom., Ph.D.', 'quinn.f@kampus.ac.id', 'active', '2025-05-27 18:38:51', '2025-05-27 18:38:51'),
('NIP19801101', 'pass_dosen_1', 'Dr. Ir. Kenjaku, M.Sc.', 'kenjaku.dosen@kampus.ac.id', 'active', '2025-05-27 18:38:51', '2025-05-27 18:38:51'),
('NIP19821202', 'pass_dosen_2', 'Prof. Dr. Lisa Cuddy, M.Kom.', 'lisa.cuddy@kampus.ac.id', 'active', '2025-05-27 18:38:51', '2025-05-27 18:38:51'),
('NIP19850204', 'pass_dosen_4', 'Mikasa Ackerman, S.Kom., M.Cs.', 'mikasa.a@kampus.ac.id', 'active', '2025-05-27 18:38:51', '2025-05-27 18:38:51'),
('NIP19860810', 'pass_dosen_10', 'Sakura Haruno, S.T., M.Sc.', 'sakura.h@kampus.ac.id', 'active', '2025-05-27 18:38:51', '2025-05-27 18:38:51'),
('NIP19880507', 'pass_dosen_7', 'Dr. Peter Bishop, M.Eng.', 'peter.b@kampus.ac.id', 'active', '2025-05-27 18:38:51', '2025-05-27 18:38:51'),
('NIP19900406', 'pass_dosen_6', 'Olivia Dunham, S.T., M.Kom.', 'olivia.d@kampus.ac.id', 'active', '2025-05-27 18:38:51', '2025-05-27 18:38:51'),
('NIP19920709', 'pass_dosen_9', 'Roronoa Zoro, S.SI., M.T.', 'roronoa.z@kampus.ac.id', 'active', '2025-05-27 18:38:51', '2025-05-27 18:38:51');

-- --------------------------------------------------------

--
-- Table structure for table `logbook`
--

CREATE TABLE `logbook` (
  `id_logbook` int NOT NULL,
  `id_pengajuan` int NOT NULL,
  `tanggal_kegiatan` date NOT NULL,
  `jam_mulai` time DEFAULT NULL,
  `jam_selesai` time DEFAULT NULL,
  `uraian_kegiatan` text COLLATE utf8mb4_general_ci NOT NULL,
  `status_verifikasi_logbook` enum('pending','disetujui','revisi_minor','revisi_mayor') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `catatan_pembimbing_logbook` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `logbook`
--

INSERT INTO `logbook` (`id_logbook`, `id_pengajuan`, `tanggal_kegiatan`, `jam_mulai`, `jam_selesai`, `uraian_kegiatan`, `status_verifikasi_logbook`, `catatan_pembimbing_logbook`, `created_at`, `updated_at`) VALUES
(11, 1, '2025-07-02', '09:00:00', '17:00:00', 'Briefing awal dengan mentor perusahaan, pengenalan proyek.', 'disetujui', 'Sip, bagus.', '2025-05-27 18:48:24', '2025-05-27 18:48:24'),
(12, 1, '2025-07-03', '09:00:00', '17:00:00', 'Mempelajari dokumentasi sistem e-learning existing.', 'disetujui', 'Fokus pada alur utama dulu.', '2025-05-27 18:48:24', '2025-05-27 18:48:24'),
(13, 3, '2025-06-16', '08:30:00', '16:30:00', 'Observasi lini produksi dan identifikasi masalah kualitas.', 'pending', NULL, '2025-05-27 18:48:24', '2025-05-27 18:48:24'),
(14, 3, '2025-06-17', '08:30:00', '16:30:00', 'Wawancara dengan kepala bagian produksi.', 'revisi_minor', 'Lampirkan catatan hasil wawancara.', '2025-05-27 18:48:24', '2025-05-27 18:48:24'),
(15, 7, '2025-07-21', '09:00:00', '17:00:00', 'Pengumpulan data waktu siklus produksi.', 'disetujui', 'Data valid.', '2025-05-27 18:48:24', '2025-05-27 18:48:24'),
(16, 7, '2025-07-22', '09:00:00', '17:00:00', 'Analisis bottleneck pada alur produksi.', 'disetujui', 'Pertajam analisisnya.', '2025-05-27 18:48:24', '2025-05-27 18:48:24'),
(17, 1, '2025-07-04', '09:00:00', '17:00:00', 'Perancangan basis data untuk modul kuis.', 'disetujui', 'Struktur tabel sudah baik.', '2025-05-27 18:48:24', '2025-05-27 18:48:24'),
(18, 1, '2025-07-05', '09:00:00', '17:00:00', 'Coding fitur registrasi dan login pengguna.', 'pending', NULL, '2025-05-27 18:48:24', '2025-05-27 18:48:24'),
(19, 3, '2025-06-18', '08:30:00', '16:30:00', 'Studi literatur tentang metode Six Sigma.', 'revisi_mayor', 'Kaitkan lebih erat dengan kasus di perusahaan.', '2025-05-27 18:48:24', '2025-05-27 18:48:24'),
(20, 7, '2025-07-23', '09:00:00', '17:00:00', 'Diskusi solusi perbaikan dengan tim internal perusahaan.', 'disetujui', 'Catat semua masukan.', '2025-05-27 18:48:24', '2025-05-27 18:48:24');

-- --------------------------------------------------------

--
-- Table structure for table `mahasiswa`
--

CREATE TABLE `mahasiswa` (
  `nim` varchar(15) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `nama` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `no_hp` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `prodi` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `angkatan` int DEFAULT NULL,
  `status_akun` enum('pending_verification','active','suspended') COLLATE utf8mb4_general_ci DEFAULT 'pending_verification',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mahasiswa`
--

INSERT INTO `mahasiswa` (`nim`, `password`, `nama`, `email`, `no_hp`, `prodi`, `angkatan`, `status_akun`, `created_at`, `updated_at`) VALUES
('NIM22006', 'pass_mhs_6', 'Fiona Glenanne', 'fiona.g@student.kampus.ac.id', '081200000006', 'Teknik Informatika', 2022, 'suspended', '2025-05-27 18:38:35', '2025-05-27 18:38:35'),
('NIM22010', 'pass_mhs_10', 'Jolyne Cujoh', 'jolyne.c@student.kampus.ac.id', '081200000010', 'Teknik Elektro', 2022, 'active', '2025-05-27 18:38:35', '2025-05-27 18:38:35'),
('NIM23003', 'pass_mhs_3', 'Charlie Brown', 'charlie.b@student.kampus.ac.id', '081200000003', 'Teknik Informatika', 2023, 'active', '2025-05-27 18:38:35', '2025-05-27 18:38:35'),
('NIM23004', 'pass_mhs_4', 'Diana Prince', 'diana.p@student.kampus.ac.id', '081200000004', 'Sistem Informasi', 2023, 'pending_verification', '2025-05-27 18:38:35', '2025-05-27 18:38:35'),
('NIM23008', 'pass_mhs_8', 'Hinata Hyuga', 'hinata.h@student.kampus.ac.id', '081200000008', 'Sistem Informasi', 2023, 'active', '2025-05-27 18:38:35', '2025-05-27 18:38:35'),
('NIM24001', 'pass_mhs_1', 'Ahmad Zulkifli', 'ahmad.z@student.kampus.ac.id', '081200000001', 'Teknik Informatika', 2024, 'active', '2025-05-27 18:38:35', '2025-05-27 18:38:35'),
('NIM24002', 'pass_mhs_2', 'Bella Swan', 'bella.s@student.kampus.ac.id', '081200000002', 'Sistem Informasi', 2024, 'active', '2025-05-27 18:38:35', '2025-05-27 18:38:35'),
('NIM24005', 'pass_mhs_5', 'Eren Yeager', 'eren.y@student.kampus.ac.id', '081200000005', 'Teknik Elektro', 2024, 'active', '2025-05-27 18:38:35', '2025-05-27 18:38:35'),
('NIM24007', 'pass_mhs_7', 'Gojo Satoru', 'gojo.s@student.kampus.ac.id', '081200000007', 'Teknik Mesin', 2024, 'active', '2025-05-27 18:38:35', '2025-05-27 18:38:35'),
('NIM24009', 'pass_mhs_9', 'Itadori Yuji', 'itadori.y@student.kampus.ac.id', '081200000009', 'Teknik Informatika', 2024, 'active', '2025-05-27 18:38:35', '2025-05-27 18:38:35');

-- --------------------------------------------------------

--
-- Table structure for table `nilai_kp`
--

CREATE TABLE `nilai_kp` (
  `id_nilai` int NOT NULL,
  `id_pengajuan` int NOT NULL,
  `nilai_pembimbing_lapangan` float DEFAULT NULL,
  `catatan_pembimbing_lapangan` text COLLATE utf8mb4_general_ci,
  `nilai_dosen_pembimbing` float DEFAULT NULL,
  `catatan_dosen_pembimbing` text COLLATE utf8mb4_general_ci,
  `nilai_penguji1_seminar` float DEFAULT NULL,
  `catatan_penguji1_seminar` text COLLATE utf8mb4_general_ci,
  `nilai_penguji2_seminar` float DEFAULT NULL,
  `catatan_penguji2_seminar` text COLLATE utf8mb4_general_ci,
  `nilai_akhir_angka` decimal(5,2) DEFAULT NULL,
  `nilai_akhir_huruf` varchar(3) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_final` tinyint(1) DEFAULT '0',
  `tanggal_input_nilai` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pengajuan_kp`
--

CREATE TABLE `pengajuan_kp` (
  `id_pengajuan` int NOT NULL,
  `nim` varchar(15) COLLATE utf8mb4_general_ci NOT NULL,
  `id_perusahaan` int DEFAULT NULL,
  `judul_kp` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `deskripsi_kp` text COLLATE utf8mb4_general_ci,
  `tanggal_pengajuan` date NOT NULL,
  `tanggal_mulai_rencana` date DEFAULT NULL,
  `tanggal_selesai_rencana` date DEFAULT NULL,
  `status_pengajuan` enum('draft','diajukan_mahasiswa','diverifikasi_dospem','disetujui_dospem','ditolak_dospem','menunggu_konfirmasi_perusahaan','diterima_perusahaan','ditolak_perusahaan','penentuan_dospem_kp','kp_berjalan','selesai_pelaksanaan','laporan_disetujui','selesai_dinilai','dibatalkan') COLLATE utf8mb4_general_ci DEFAULT 'draft',
  `nip_dosen_pembimbing_kp` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `catatan_admin` text COLLATE utf8mb4_general_ci,
  `catatan_dosen` text COLLATE utf8mb4_general_ci,
  `surat_pengantar_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `surat_balasan_perusahaan_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pengajuan_kp`
--

INSERT INTO `pengajuan_kp` (`id_pengajuan`, `nim`, `id_perusahaan`, `judul_kp`, `deskripsi_kp`, `tanggal_pengajuan`, `tanggal_mulai_rencana`, `tanggal_selesai_rencana`, `status_pengajuan`, `nip_dosen_pembimbing_kp`, `catatan_admin`, `catatan_dosen`, `surat_pengantar_path`, `surat_balasan_perusahaan_path`, `created_at`, `updated_at`) VALUES
(1, 'NIM24001', 1, 'Pengembangan Aplikasi Mobile E-Learning', 'Membuat aplikasi mobile untuk PT. Global Teknologi Indonesia.', '2025-03-01', '2025-07-01', '2025-10-01', 'kp_berjalan', 'NIP19801101', 'Dokumen lengkap', 'Judul disetujui, silakan lanjut', 'path/to/sp_NIM24001.pdf', 'path/to/sb_NIM24001.pdf', '2025-05-27 18:42:36', '2025-05-27 18:45:45'),
(2, 'NIM24002', 2, 'Analisis Sistem Informasi Akuntansi', 'Menganalisis SIA di Nusantara Corporation Tbk.', '2025-03-05', '2025-07-10', '2025-10-10', 'diterima_perusahaan', 'NIP19821202', NULL, 'Proposal sudah baik', 'path/to/sp_NIM24002.pdf', 'path/to/sb_NIM24002.pdf', '2025-05-27 18:42:36', '2025-05-27 18:45:53'),
(3, 'NIM23003', 4, 'Rancang Bangun Sistem Kontrol Kualitas Produk', 'Implementasi sistem kontrol di CV. Manufaktur Andalan.', '2025-02-15', '2025-06-15', '2025-09-15', 'kp_berjalan', 'NIP19750103', 'KHS belum update', 'Perbaiki metodologi penelitian', 'path/to/sp_NIM23003.pdf', NULL, '2025-05-27 18:42:36', '2025-05-27 18:45:58'),
(4, 'NIM23004', 1, 'Studi Keamanan Data pada Sistem Cloud', 'Studi kasus keamanan pada PT. Global Teknologi Indonesia.', '2025-04-01', '2025-08-01', '2025-11-01', 'diverifikasi_dospem', 'NIP19850204', NULL, NULL, 'path/to/sp_NIM23004.pdf', NULL, '2025-05-27 18:42:36', '2025-05-27 18:45:49'),
(5, 'NIM24005', 7, 'Desain Jaringan Listrik Gedung Bertingkat', 'Proyek desain untuk PT. Konstruksi Bangsa.', '2025-04-10', '2025-08-10', '2025-11-10', 'diajukan_mahasiswa', 'NIP19900406', NULL, 'Menunggu persetujuan', 'path/to/sp_NIM24005.pdf', NULL, '2025-05-27 18:42:36', '2025-05-27 18:46:07'),
(6, 'NIM22006', 9, 'Pengembangan Karakter 3D untuk Animasi', 'Membuat model karakter untuk Studio Animasi Kreatif.', '2025-05-01', '2025-09-01', '2025-12-01', 'penentuan_dospem_kp', NULL, 'Mahasiswa belum memilih dospem', NULL, 'path/to/sp_NIM22006.pdf', 'path/to/sb_NIM22006.pdf', '2025-05-27 18:42:36', '2025-05-27 18:46:09'),
(7, 'NIM24007', 4, 'Implementasi Lean Manufacturing', 'Penerapan Lean di CV. Manufaktur Andalan.', '2025-03-20', '2025-07-20', '2025-10-20', 'selesai_pelaksanaan', 'NIP19880507', 'KP selesai, tunggu laporan', 'Baik sekali', 'path/to/sp_NIM24007.pdf', 'path/to/sb_NIM24007.pdf', '2025-05-27 18:42:36', '2025-05-27 18:46:01'),
(8, 'NIM23008', 2, 'Sistem Rekomendasi Produk Berbasis AI', 'Menggunakan AI untuk Nusantara Corporation Tbk.', '2025-05-15', '2025-08-15', '2025-11-15', 'disetujui_dospem', 'NIP19790608', NULL, 'Judul OK', 'path/to/sp_NIM23008.pdf', NULL, '2025-05-27 18:42:36', '2025-05-27 18:45:56'),
(9, 'NIM24009', 6, 'Desain Ulang Antarmuka Pengguna Situs Web', 'Redesign UI/UX untuk PT. Layanan Digital Prima.', '2025-06-01', '2025-10-01', '2026-01-01', 'draft', 'NIP19920709', NULL, NULL, NULL, NULL, '2025-05-27 18:42:36', '2025-05-27 18:46:04'),
(10, 'NIM22010', NULL, 'Riset Aplikasi Augmented Reality untuk Edukasi', 'Studi independen AR.', '2025-07-10', '2025-11-01', '2026-02-01', 'diajukan_mahasiswa', 'NIP19860810', 'Perusahaan belum ada', NULL, 'path/to/sp_NIM22010.pdf', NULL, '2025-05-27 18:42:36', '2025-05-27 18:45:26');

-- --------------------------------------------------------

--
-- Table structure for table `perusahaan`
--

CREATE TABLE `perusahaan` (
  `id_perusahaan` int NOT NULL,
  `email_perusahaan` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `password_perusahaan` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `nama_perusahaan` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `alamat` text COLLATE utf8mb4_general_ci,
  `bidang` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `kontak_person_nama` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `kontak_person_email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `kontak_person_no_hp` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status_akun` enum('pending_approval','active','inactive') COLLATE utf8mb4_general_ci DEFAULT 'pending_approval',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `perusahaan`
--

INSERT INTO `perusahaan` (`id_perusahaan`, `email_perusahaan`, `password_perusahaan`, `nama_perusahaan`, `alamat`, `bidang`, `kontak_person_nama`, `kontak_person_email`, `kontak_person_no_hp`, `status_akun`, `created_at`, `updated_at`) VALUES
(1, 'admin@layanandigitalprima.com', 'comp_pass_6', 'PT. Layanan Digital Prima', 'Jl. Digital No. 606, Medan', 'Digital Services', 'Mr. Alex Tan', 'alex.tan@layanandigitalprima.com', '085200001006', 'active', '2025-05-27 18:39:07', '2025-05-27 18:40:55'),
(2, 'hr@globaltech.id', 'comp_pass_1', 'PT. Global Teknologi Indonesia', 'Jl. Merdeka No. 101, Jakarta', 'IT Solutions', 'Dewi HR', 'dewi.hr@globaltech.id', '085200001001', 'active', '2025-05-27 18:39:07', '2025-05-27 18:41:31'),
(3, 'career@nusantaracorp.com', 'comp_pass_2', 'Nusantara Corporation Tbk', 'Jl. Pahlawan No. 202, Surabaya', 'Multinational Conglomerate', 'Bapak Cahya', 'cahya.career@nusantaracorp.com', '085200001002', 'active', '2025-05-27 18:39:07', '2025-05-27 18:41:35'),
(4, 'info@startupinovatif.co', 'comp_pass_3', 'Startup Inovatif Maju', 'Jl. Cendrawasih No. 303, Bandung', 'Software Development', 'Ibu Fitri', 'fitri@startupinovatif.co', '085200001003', 'pending_approval', '2025-05-27 18:39:07', '2025-05-27 18:41:39'),
(5, 'kontak@manufakturandal.biz', 'comp_pass_4', 'CV. Manufaktur Andalan', 'Jl. Industri No. 404, Semarang', 'Manufacturing', 'Pak Eko Wijaya', 'eko.kontak@manufakturandal.biz', '085200001004', 'active', '2025-05-27 18:39:07', '2025-05-27 18:41:43'),
(6, 'recruitment@mediajaya.net', 'comp_pass_5', 'Media Jaya Network', 'Jl. Pers No. 505, Yogyakarta', 'Media & Publishing', 'Rina Recruiter', 'rina@mediajaya.net', '085200001005', 'inactive', '2025-05-27 18:39:07', '2025-05-27 18:41:48'),
(7, 'hrd@konstruksibangsa.co.id', 'comp_pass_7', 'PT. Konstruksi Bangsa', 'Jl. Pembangunan No. 707, Makassar', 'Construction', 'Anita HRD', 'anita.hrd@konstruksibangsa.co.id', '085200001007', 'active', '2025-05-27 18:39:07', '2025-05-27 18:41:57'),
(8, 'karir@energisumberdaya.com', 'comp_pass_8', 'Energi Sumberdaya Utama', 'Jl. Tambang No. 808, Balikpapan', 'Energy & Resources', 'Bapak Toni Santoso', 'toni.karir@energisumberdaya.com', '085200001008', 'pending_approval', '2025-05-27 18:39:07', '2025-05-27 18:42:00'),
(9, 'support@studioanimasi.id', 'comp_pass_9', 'Studio Animasi Kreatif', 'Jl. Kartun No. 909, Denpasar', 'Animation & Creative', 'Vina Manager', 'vina.support@studioanimasi.id', '085200001009', 'active', '2025-05-27 18:39:07', '2025-05-27 18:42:04'),
(10, 'partner@konsultanbisnis.asia', 'comp_pass_10', 'Konsultan Bisnis Asia Pasifik', 'Jl. Internasional No. 1010, Batam', 'Business Consulting', 'Rendy Partner', 'rendy@konsultanbisnis.asia', '085200001010', 'active', '2025-05-27 18:39:07', '2025-05-27 18:42:09');

-- --------------------------------------------------------

--
-- Table structure for table `seminar_kp`
--

CREATE TABLE `seminar_kp` (
  `id_seminar` int NOT NULL,
  `id_pengajuan` int NOT NULL,
  `tanggal_pengajuan_seminar` date DEFAULT NULL,
  `status_kelayakan_seminar` enum('pending_verifikasi','layak_seminar','belum_layak') COLLATE utf8mb4_general_ci DEFAULT 'pending_verifikasi',
  `catatan_kelayakan` text COLLATE utf8mb4_general_ci,
  `tanggal_seminar` datetime DEFAULT NULL,
  `tempat_seminar` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nip_dosen_penguji1` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nip_dosen_penguji2` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status_pelaksanaan_seminar` enum('dijadwalkan','selesai','dibatalkan','ditunda') COLLATE utf8mb4_general_ci DEFAULT 'dijadwalkan',
  `catatan_hasil_seminar` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `seminar_kp`
--

INSERT INTO `seminar_kp` (`id_seminar`, `id_pengajuan`, `tanggal_pengajuan_seminar`, `status_kelayakan_seminar`, `catatan_kelayakan`, `tanggal_seminar`, `tempat_seminar`, `nip_dosen_penguji1`, `nip_dosen_penguji2`, `status_pelaksanaan_seminar`, `catatan_hasil_seminar`, `created_at`, `updated_at`) VALUES
(1, 1, '2025-09-20', 'layak_seminar', 'Semua syarat terpenuhi.', '2025-10-05 10:00:00', 'Aula Gedung A Lt.3', 'NIP19821202', 'NIP19750103', 'selesai', 'Mahasiswa sangat menguasai materi presentasi.', '2025-05-27 18:48:31', '2025-05-27 18:48:31'),
(2, 2, '2025-09-25', 'layak_seminar', 'Laporan dan logbook OK.', '2025-10-12 14:00:00', 'Ruang Seminar Online 1', 'NIP19801101', 'NIP19850204', 'selesai', 'Hasil analisis baik, presentasi jelas.', '2025-05-27 18:48:31', '2025-05-27 18:48:31'),
(3, 3, '2025-09-01', 'belum_layak', 'Logbook belum lengkap semua.', NULL, NULL, 'NIP19801101', NULL, 'ditunda', 'Diminta melengkapi logbook harian.', '2025-05-27 18:48:31', '2025-05-27 18:48:31'),
(4, 4, '2025-10-15', 'pending_verifikasi', 'Baru diajukan, menunggu verifikasi dospem.', NULL, NULL, NULL, NULL, 'dijadwalkan', NULL, '2025-05-27 18:48:31', '2025-05-27 18:48:31'),
(5, 5, NULL, 'pending_verifikasi', NULL, NULL, NULL, NULL, NULL, 'dijadwalkan', NULL, '2025-05-27 18:48:31', '2025-05-27 18:48:31'),
(6, 6, '2025-11-20', 'layak_seminar', 'Siap untuk dijadwalkan.', '2025-12-05 09:00:00', 'Ruang Kreatif Lt.2', 'NIP19700305', 'NIP19880507', 'selesai', 'Perlu sedikit penyesuaian pada kesimpulan laporan.', '2025-05-27 18:48:31', '2025-05-27 18:48:31'),
(7, 7, '2025-10-10', 'layak_seminar', 'Laporan akhir sudah disetujui pembimbing.', '2025-10-25 13:00:00', 'Auditorium Utama', 'NIP19900406', 'NIP19920709', 'selesai', 'Pelaksanaan KP dan laporan sangat baik.', '2025-05-27 18:48:31', '2025-05-27 18:48:31'),
(8, 8, '2025-10-30', 'pending_verifikasi', 'Menunggu acc laporan dari dospem.', NULL, NULL, NULL, NULL, 'dijadwalkan', NULL, '2025-05-27 18:48:31', '2025-05-27 18:48:31'),
(9, 9, NULL, 'pending_verifikasi', NULL, NULL, NULL, NULL, NULL, 'dijadwalkan', NULL, '2025-05-27 18:48:31', '2025-05-27 18:48:31'),
(10, 10, '2026-01-15', 'layak_seminar', 'Laporan studi independen diterima.', '2026-02-05 11:00:00', 'Online via Platform Kampus', 'NIP19801101', 'NIP19821202', 'selesai', 'Cukup memuaskan.', '2025-05-27 18:48:31', '2025-05-27 18:48:31'),
(11, 1, '2025-09-20', 'layak_seminar', 'Semua syarat terpenuhi.', '2025-10-05 10:00:00', 'Aula Gedung A Lt.3', 'NIP19821202', 'NIP19750103', 'selesai', 'Mahasiswa sangat menguasai materi presentasi.', '2025-05-27 18:48:39', '2025-05-27 18:48:39'),
(12, 2, '2025-09-25', 'layak_seminar', 'Laporan dan logbook OK.', '2025-10-12 14:00:00', 'Ruang Seminar Online 1', 'NIP19801101', 'NIP19850204', 'selesai', 'Hasil analisis baik, presentasi jelas.', '2025-05-27 18:48:39', '2025-05-27 18:48:39'),
(13, 3, '2025-09-01', 'belum_layak', 'Logbook belum lengkap semua.', NULL, NULL, 'NIP19801101', NULL, 'ditunda', 'Diminta melengkapi logbook harian.', '2025-05-27 18:48:39', '2025-05-27 18:48:39'),
(14, 4, '2025-10-15', 'pending_verifikasi', 'Baru diajukan, menunggu verifikasi dospem.', NULL, NULL, NULL, NULL, 'dijadwalkan', NULL, '2025-05-27 18:48:39', '2025-05-27 18:48:39'),
(15, 5, NULL, 'pending_verifikasi', NULL, NULL, NULL, NULL, NULL, 'dijadwalkan', NULL, '2025-05-27 18:48:39', '2025-05-27 18:48:39'),
(16, 6, '2025-11-20', 'layak_seminar', 'Siap untuk dijadwalkan.', '2025-12-05 09:00:00', 'Ruang Kreatif Lt.2', 'NIP19700305', 'NIP19880507', 'selesai', 'Perlu sedikit penyesuaian pada kesimpulan laporan.', '2025-05-27 18:48:39', '2025-05-27 18:48:39'),
(17, 7, '2025-10-10', 'layak_seminar', 'Laporan akhir sudah disetujui pembimbing.', '2025-10-25 13:00:00', 'Auditorium Utama', 'NIP19900406', 'NIP19920709', 'selesai', 'Pelaksanaan KP dan laporan sangat baik.', '2025-05-27 18:48:39', '2025-05-27 18:48:39'),
(18, 8, '2025-10-30', 'pending_verifikasi', 'Menunggu acc laporan dari dospem.', NULL, NULL, NULL, NULL, 'dijadwalkan', NULL, '2025-05-27 18:48:39', '2025-05-27 18:48:39'),
(19, 9, NULL, 'pending_verifikasi', NULL, NULL, NULL, NULL, NULL, 'dijadwalkan', NULL, '2025-05-27 18:48:39', '2025-05-27 18:48:39'),
(20, 10, '2026-01-15', 'layak_seminar', 'Laporan studi independen diterima.', '2026-02-05 11:00:00', 'Online via Platform Kampus', 'NIP19801101', 'NIP19821202', 'selesai', 'Cukup memuaskan.', '2025-05-27 18:48:39', '2025-05-27 18:48:39');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_prodi`
--
ALTER TABLE `admin_prodi`
  ADD PRIMARY KEY (`id_admin`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email_admin` (`email_admin`);

--
-- Indexes for table `bimbingan_kp`
--
ALTER TABLE `bimbingan_kp`
  ADD PRIMARY KEY (`id_bimbingan`),
  ADD KEY `id_pengajuan` (`id_pengajuan`),
  ADD KEY `nip_pembimbing` (`nip_pembimbing`);

--
-- Indexes for table `dokumen_kp`
--
ALTER TABLE `dokumen_kp`
  ADD PRIMARY KEY (`id_dokumen`),
  ADD KEY `id_pengajuan` (`id_pengajuan`);

--
-- Indexes for table `dosen_pembimbing`
--
ALTER TABLE `dosen_pembimbing`
  ADD PRIMARY KEY (`nip`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `logbook`
--
ALTER TABLE `logbook`
  ADD PRIMARY KEY (`id_logbook`),
  ADD KEY `id_pengajuan` (`id_pengajuan`);

--
-- Indexes for table `mahasiswa`
--
ALTER TABLE `mahasiswa`
  ADD PRIMARY KEY (`nim`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `nilai_kp`
--
ALTER TABLE `nilai_kp`
  ADD PRIMARY KEY (`id_nilai`),
  ADD UNIQUE KEY `id_pengajuan` (`id_pengajuan`);

--
-- Indexes for table `pengajuan_kp`
--
ALTER TABLE `pengajuan_kp`
  ADD PRIMARY KEY (`id_pengajuan`),
  ADD KEY `nim` (`nim`),
  ADD KEY `id_perusahaan` (`id_perusahaan`),
  ADD KEY `nip_dosen_pembimbing_kp` (`nip_dosen_pembimbing_kp`);

--
-- Indexes for table `perusahaan`
--
ALTER TABLE `perusahaan`
  ADD PRIMARY KEY (`id_perusahaan`),
  ADD UNIQUE KEY `email_perusahaan` (`email_perusahaan`);

--
-- Indexes for table `seminar_kp`
--
ALTER TABLE `seminar_kp`
  ADD PRIMARY KEY (`id_seminar`),
  ADD KEY `id_pengajuan` (`id_pengajuan`),
  ADD KEY `nip_dosen_penguji1` (`nip_dosen_penguji1`),
  ADD KEY `nip_dosen_penguji2` (`nip_dosen_penguji2`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_prodi`
--
ALTER TABLE `admin_prodi`
  MODIFY `id_admin` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `bimbingan_kp`
--
ALTER TABLE `bimbingan_kp`
  MODIFY `id_bimbingan` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `dokumen_kp`
--
ALTER TABLE `dokumen_kp`
  MODIFY `id_dokumen` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `logbook`
--
ALTER TABLE `logbook`
  MODIFY `id_logbook` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `nilai_kp`
--
ALTER TABLE `nilai_kp`
  MODIFY `id_nilai` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pengajuan_kp`
--
ALTER TABLE `pengajuan_kp`
  MODIFY `id_pengajuan` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `perusahaan`
--
ALTER TABLE `perusahaan`
  MODIFY `id_perusahaan` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=213;

--
-- AUTO_INCREMENT for table `seminar_kp`
--
ALTER TABLE `seminar_kp`
  MODIFY `id_seminar` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bimbingan_kp`
--
ALTER TABLE `bimbingan_kp`
  ADD CONSTRAINT `bimbingan_kp_ibfk_1` FOREIGN KEY (`id_pengajuan`) REFERENCES `pengajuan_kp` (`id_pengajuan`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `bimbingan_kp_ibfk_2` FOREIGN KEY (`nip_pembimbing`) REFERENCES `dosen_pembimbing` (`nip`) ON UPDATE CASCADE;

--
-- Constraints for table `dokumen_kp`
--
ALTER TABLE `dokumen_kp`
  ADD CONSTRAINT `dokumen_kp_ibfk_1` FOREIGN KEY (`id_pengajuan`) REFERENCES `pengajuan_kp` (`id_pengajuan`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `logbook`
--
ALTER TABLE `logbook`
  ADD CONSTRAINT `logbook_ibfk_1` FOREIGN KEY (`id_pengajuan`) REFERENCES `pengajuan_kp` (`id_pengajuan`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `nilai_kp`
--
ALTER TABLE `nilai_kp`
  ADD CONSTRAINT `nilai_kp_ibfk_1` FOREIGN KEY (`id_pengajuan`) REFERENCES `pengajuan_kp` (`id_pengajuan`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `pengajuan_kp`
--
ALTER TABLE `pengajuan_kp`
  ADD CONSTRAINT `pengajuan_kp_ibfk_1` FOREIGN KEY (`nim`) REFERENCES `mahasiswa` (`nim`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `pengajuan_kp_ibfk_2` FOREIGN KEY (`id_perusahaan`) REFERENCES `perusahaan` (`id_perusahaan`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `pengajuan_kp_ibfk_3` FOREIGN KEY (`nip_dosen_pembimbing_kp`) REFERENCES `dosen_pembimbing` (`nip`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `seminar_kp`
--
ALTER TABLE `seminar_kp`
  ADD CONSTRAINT `seminar_kp_ibfk_1` FOREIGN KEY (`id_pengajuan`) REFERENCES `pengajuan_kp` (`id_pengajuan`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `seminar_kp_ibfk_2` FOREIGN KEY (`nip_dosen_penguji1`) REFERENCES `dosen_pembimbing` (`nip`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `seminar_kp_ibfk_3` FOREIGN KEY (`nip_dosen_penguji2`) REFERENCES `dosen_pembimbing` (`nip`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

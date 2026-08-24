-- LUCU (Aplikasi Untuk Cuti) - Pengadilan Agama Rantau
-- Schema diadaptasi dari MACOA (PTA Sulawesi Barat), struktur & nama kolom
-- dipertahankan agar output/logic identik. Data pegawai asli TIDAK disalin
-- (PII instansi lain) - diganti data dummy untuk keperluan development.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- --------------------------------------------------------

CREATE TABLE `golongan` (
  `id_golongan` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama_golongan` varchar(255) NOT NULL,
  PRIMARY KEY (`id_golongan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `golongan` (`id_golongan`, `nama_golongan`) VALUES
(1, 'IV/a'), (2, 'IV/b'), (3, 'IV/c'), (4, 'IV/d'), (5, 'IV/e'),
(6, 'III/a'), (7, 'III/b'), (8, 'III/c'), (9, 'III/d'),
(10, 'II/a'), (11, 'II/b'), (12, 'II/c'), (13, 'II/d');

-- --------------------------------------------------------

CREATE TABLE `jabatan` (
  `id_jabatan` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama_jabatan` varchar(225) NOT NULL,
  `id_atasan` int(11) UNSIGNED DEFAULT NULL COMMENT 'jabatan yg approve cuti jabatan ini; NULL = puncak, auto-approve',
  `is_pejabat_pppk` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'jabatan ini pemberi izin cuti akhir buat pegawai PPPK (harus persis 1 baris bernilai 1)',
  PRIMARY KEY (`id_jabatan`),
  KEY `id_atasan` (`id_atasan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hierarki approval cuti PA Rantau. Default masuk akal untuk struktur
-- pengadilan agama kelas umum; sesuaikan id_atasan lewat admin kalau beda.
-- Kedalaman rantai maksimal 3 (staf -> kasubag -> panitera/sekretaris -> ketua)
-- biar pas sama 3 kolom approval yg ada di cuti_pegawai.
INSERT INTO `jabatan` (`id_jabatan`, `nama_jabatan`, `id_atasan`) VALUES
(1, 'KETUA', NULL),
(2, 'WAKIL KETUA', 1),
(3, 'HAKIM', 1),
(5, 'PANITERA', 1),
(6, 'SEKRETARIS', 1),
(7, 'PANITERA MUDA HUKUM', 5),
(8, 'PANITERA MUDA GUGATAN', 5),
(9, 'PANITERA MUDA PERMOHONAN', 5),
(11, 'KEPALA BAGIAN UMUM DAN KEUANGAN', 6),
(12, 'KEPALA BAGIAN PERENCANAAN DAN KEPEGAWAIAN', 6),
(13, 'PANITERA PENGGANTI', 5),
(14, 'JURUSITA', 5),
(15, 'JURUSITA PENGGANTI', 5),
(16, 'KASUBAG KEPEGAWAIAN DAN TI', 6),
(17, 'KASUBAG RENPROG DAN ANGGARAN', 6),
(18, 'KASUBAG KEUANGAN DAN PELAPORAN', 6),
(19, 'KASUBAG TATA USAHA DAN RUMAH TANGGA', 6),
(20, 'KLEREK - PENGOLAH DATA DAN INFORMASI', 16),
(21, 'OPERATOR - TEKNISI SARANA DAN PRASARANA', 19),
(22, 'KLEREK - PENGELOLA PENANGANAN PERKARA', 7),
(23, 'KLEREK - PENGADMINISTRASI PERKANTORAN', 19),
(24, 'OPERATOR - PENATA LAYANAN OPERASIONAL', 17);

-- Sekretaris = pemberi izin cuti akhir buat pegawai PPPK (harus persis 1 baris)
UPDATE `jabatan` SET `is_pejabat_pppk` = 1 WHERE `id_jabatan` = 6;

-- RESTRICT, bukan SET NULL: kalau jabatan yg dihapus masih jadi atasan
-- jabatan lain, SET NULL bakal diam-diam bikin jabatan itu "naik" ke puncak
-- (auto-approve) tanpa admin sadar. Wajib pindahin id_atasan anaknya dulu.
ALTER TABLE `jabatan`
  ADD CONSTRAINT `jabatan_ibfk_1` FOREIGN KEY (`id_atasan`) REFERENCES `jabatan` (`id_jabatan`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- --------------------------------------------------------

CREATE TABLE `pegawai` (
  `id_pegawai` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama_pegawai` varchar(225) NOT NULL,
  `nip` varchar(225) NOT NULL,
  `id_jabatan` int(11) UNSIGNED NOT NULL,
  `id_golongan` int(11) UNSIGNED NOT NULL,
  `jenis_asn` enum('PNS','PPPK') NOT NULL DEFAULT 'PNS' COMMENT 'nentuin pejabat pemberi izin cuti akhir: PNS->Ketua, PPPK->jabatan is_pejabat_pppk',
  `unit_kerja` varchar(225) NOT NULL DEFAULT 'Pengadilan Agama Rantau',
  `tmt_pegawai` date DEFAULT NULL,
  `hak_cuti_tahunan` int(2) NOT NULL DEFAULT 12,
  `hak_cuti_sakit` int(2) NOT NULL DEFAULT 0,
  `hak_cuti_penting` int(2) NOT NULL DEFAULT 0,
  `no_telp` varchar(15) NOT NULL DEFAULT '',
  PRIMARY KEY (`id_pegawai`),
  UNIQUE KEY `nip_unique` (`nip`),
  KEY `id_jabatan` (`id_jabatan`),
  KEY `id_golongan` (`id_golongan`),
  -- RESTRICT, bukan CASCADE kayak MACOA: MACOA hapus 1 baris golongan/jabatan
  -- bakal ikut ngehapus SEMUA pegawai yang pakai golongan/jabatan itu.
  -- Di sini hapus ditolak selama masih ada pegawai yang mereferensikannya.
  CONSTRAINT `pegawai_ibfk_1` FOREIGN KEY (`id_golongan`) REFERENCES `golongan` (`id_golongan`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `pegawai_ibfk_2` FOREIGN KEY (`id_jabatan`) REFERENCES `jabatan` (`id_jabatan`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Data dummy untuk development lokal - BUKAN data pegawai sungguhan.
-- Mengisi 1 orang per jabatan yang dilewati rantai approval staf (20 -> 16 -> 6 -> 1)
-- dan jalur panitera (22 -> 7 -> 5 -> [Ketua/Sekretaris tergantung jenis_asn])
-- biar alur pengajuan cuti bisa dites lengkap end-to-end, PNS maupun PPPK.
INSERT INTO `pegawai` (`nama_pegawai`, `nip`, `id_jabatan`, `id_golongan`, `jenis_asn`, `unit_kerja`, `tmt_pegawai`, `hak_cuti_tahunan`, `hak_cuti_sakit`, `hak_cuti_penting`, `no_telp`) VALUES
('Contoh Ketua, S.H., M.H.', '190000000000000001', 1, 5, 'PNS', 'Pengadilan Agama Rantau', '2015-01-01', 12, 0, 0, ''),
('Contoh Panitera, S.H.', '190000000000000002', 5, 3, 'PNS', 'Pengadilan Agama Rantau', '2018-03-01', 12, 0, 0, ''),
('Contoh Sekretaris, S.H.', '190000000000000004', 6, 4, 'PNS', 'Pengadilan Agama Rantau', '2017-05-01', 12, 0, 0, ''),
('Contoh Kasubag TI, A.Md.', '190000000000000005', 16, 9, 'PNS', 'Pengadilan Agama Rantau', '2019-02-01', 12, 0, 0, ''),
('Contoh Panmud Hukum, S.H.', '190000000000000007', 7, 3, 'PNS', 'Pengadilan Agama Rantau', '2016-04-01', 12, 0, 0, ''),
('Contoh Staf, A.Md.', '190000000000000003', 20, 10, 'PNS', 'Pengadilan Agama Rantau', '2021-06-01', 12, 6, 6, ''),
('Contoh Staf PPPK, A.Md.', '190000000000000006', 22, 10, 'PPPK', 'Pengadilan Agama Rantau', '2022-01-01', 12, 6, 6, '');

-- --------------------------------------------------------

CREATE TABLE `user` (
  `id_user` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `nip` varchar(225) NOT NULL COMMENT 'referensi ke pegawai.nip, bukan buat login',
  `password` varchar(255) NOT NULL COMMENT 'bcrypt hash (password_hash)',
  `role` enum('Admin','User') NOT NULL DEFAULT 'User',
  `foto` varchar(225) DEFAULT NULL,
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `username` (`username`),
  KEY `nip` (`nip`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- password dummy: "rahasia123" (di-hash bcrypt saat seed dijalankan lewat PHP, lihat database/seed.php)

-- --------------------------------------------------------

CREATE TABLE `cuti_pegawai` (
  `id_cutipegawai` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pegawai` int(11) UNSIGNED NOT NULL,
  `jenis_cuti` varchar(225) NOT NULL,
  `alasan_cuti` varchar(225) NOT NULL,
  `lama_cuti` varchar(225) NOT NULL,
  `ket_lama_cuti` varchar(225) NOT NULL,
  `dari_tanggal` varchar(225) NOT NULL,
  `sampai_dengan` varchar(225) NOT NULL,
  `panmud_kasubag` varchar(225) DEFAULT NULL,
  `panitera_sekretaris` varchar(225) DEFAULT NULL,
  `ketua` varchar(225) DEFAULT NULL,
  `app_panmud_kasubag` int(2) NOT NULL DEFAULT 0,
  `app_panitera_sekretaris` int(2) NOT NULL DEFAULT 0,
  `app_ketua` int(2) NOT NULL DEFAULT 0,
  `status_cuti` varchar(225) NOT NULL,
  `ket_status_cuti` varchar(225) NOT NULL,
  `sisa_cuti` int(3) NOT NULL,
  `tgl_pengajuan` varchar(20) NOT NULL,
  `masa_kerja` varchar(20) NOT NULL,
  `delegasi` varchar(225) NOT NULL DEFAULT '',
  `alamat_cuti` varchar(500) NOT NULL,
  `berkas` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id_cutipegawai`),
  KEY `id_pegawai` (`id_pegawai`),
  CONSTRAINT `cuti_pegawai_ibfk_1` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawai` (`id_pegawai`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

CREATE TABLE `permohonan_cuti` (
  `id_permohonan` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pegawai` int(11) UNSIGNED NOT NULL,
  `berkas_permohonan` varchar(255) NOT NULL,
  `tanggal_permohonan` date NOT NULL,
  `keterangan` varchar(50) NOT NULL,
  PRIMARY KEY (`id_permohonan`),
  KEY `id_pegawai` (`id_pegawai`),
  CONSTRAINT `permohonan_cuti_ibfk_1` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawai` (`id_pegawai`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

CREATE TABLE `kgb_pegawai` (
  `id_kgbpegawai` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pegawai` int(11) UNSIGNED NOT NULL,
  `kgb_terakhir` varchar(225) NOT NULL,
  `kgb_datang` varchar(225) NOT NULL,
  `keterangan` varchar(225) NOT NULL,
  `timestamp` varchar(225) NOT NULL,
  PRIMARY KEY (`id_kgbpegawai`),
  KEY `id_pegawai` (`id_pegawai`),
  CONSTRAINT `kgb_pegawai_ibfk_1` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawai` (`id_pegawai`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

CREATE TABLE `knp_pegawai` (
  `id_knppegawai` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pegawai` int(11) UNSIGNED NOT NULL,
  `knp_terakhir` varchar(225) NOT NULL,
  `knp_datang` varchar(225) NOT NULL,
  `keterangan` varchar(225) NOT NULL,
  `pensiun` varchar(225) NOT NULL,
  `timestamp` varchar(225) NOT NULL,
  PRIMARY KEY (`id_knppegawai`),
  KEY `id_pegawai` (`id_pegawai`),
  CONSTRAINT `knp_pegawai_ibfk_1` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawai` (`id_pegawai`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

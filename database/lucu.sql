-- LUCU (Aplikasi Untuk Cuti) - Pengadilan Agama Rantau
-- Schema diadaptasi dari MACOA (PTA Sulawesi Barat), struktur & nama kolom
-- dipertahankan agar output/logic identik. Data pegawai asli TIDAK disalin
-- (PII instansi lain) - diganti data dummy untuk keperluan development.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- --------------------------------------------------------

-- Golongan PNS (format 'IV/a' dst, Peraturan Gaji PNS) dan PPPK (format
-- Romawi polos 'I'..'XVII', skala gaji PPPK per Perpres 98/2020) beda
-- struktur total & gak collision penamaan - satu tabel aman ditumpuk,
-- kolom jenis_asn buat filter mana yg cocok buat pegawai yg mana.
CREATE TABLE `golongan` (
  `id_golongan` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama_golongan` varchar(255) NOT NULL,
  `jenis_asn` enum('PNS','PPPK') NOT NULL DEFAULT 'PNS',
  PRIMARY KEY (`id_golongan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `golongan` (`id_golongan`, `nama_golongan`, `jenis_asn`) VALUES
(1, 'IV/a', 'PNS'), (2, 'IV/b', 'PNS'), (3, 'IV/c', 'PNS'), (4, 'IV/d', 'PNS'), (5, 'IV/e', 'PNS'),
(6, 'III/a', 'PNS'), (7, 'III/b', 'PNS'), (8, 'III/c', 'PNS'), (9, 'III/d', 'PNS'),
(10, 'II/a', 'PNS'), (11, 'II/b', 'PNS'), (12, 'II/c', 'PNS'), (13, 'II/d', 'PNS'),
(14, 'I', 'PPPK'), (15, 'II', 'PPPK'), (16, 'III', 'PPPK'), (17, 'IV', 'PPPK'),
(18, 'V', 'PPPK'), (19, 'VI', 'PPPK'), (20, 'VII', 'PPPK'), (21, 'VIII', 'PPPK'),
(22, 'IX', 'PPPK'), (23, 'X', 'PPPK'), (24, 'XI', 'PPPK'), (25, 'XII', 'PPPK'),
(26, 'XIII', 'PPPK'), (27, 'XIV', 'PPPK'), (28, 'XV', 'PPPK'), (29, 'XVI', 'PPPK'), (30, 'XVII', 'PPPK');

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
  `hak_cuti_tahunan` int(2) NOT NULL DEFAULT 12 COMMENT 'sisa cuti tahunan (N) - tahun berjalan',
  `cuti_tahunan_n1` int(2) NOT NULL DEFAULT 0 COMMENT 'sisa cuti tahunan tahun lalu (N-1), starter diisi admin, selanjutnya di-roll otomatis - lihat cuti_tahunan_rollover_jika_perlu()',
  `cuti_tahunan_n2` int(2) NOT NULL DEFAULT 0 COMMENT 'sisa cuti tahunan 2 tahun lalu (N-2), sda',
  `cuti_tahunan_rollover_tahun` int(4) NOT NULL DEFAULT 2026 COMMENT 'tahun terakhir kali N/N-1/N-2 di-roll, cegah rollover dobel',
  `hak_cuti_sakit` int(2) NOT NULL DEFAULT 14 COMMENT 'kredit cuti sakit TAHUNAN (bukan per-pengajuan) - 14 hari, hangus kalau gak kepake (reset tiap tahun, gak akumulasi kayak cuti tahunan) - lihat cuti_sakit_reset_jika_perlu(). Lewat kuota TETAP disetujui, cuma dikasih catatan potongan TUKIN, gak diblok.',
  `cuti_sakit_reset_tahun` int(4) NOT NULL DEFAULT 2026 COMMENT 'tahun terakhir hak_cuti_sakit di-reset ke kredit tahunan, cegah reset dobel',
  `hak_cuti_penting` int(2) NOT NULL DEFAULT 0,
  `no_telp` varchar(15) NOT NULL DEFAULT '',
  `tanda_tangan_path` varchar(255) DEFAULT NULL COMMENT 'relatif ke uploads/ttd/ - gambar tanda tangan buat cetak_cuti.php',
  PRIMARY KEY (`id_pegawai`),
  -- (191) - kolomnya varchar(225) tapi indeks utf8mb4 kepanjangan (225*4=900
  -- byte) buat batas 767 byte di InnoDB row format lama; NIP asli max 18
  -- digit jadi 191 char lebih dari cukup. Sama kayak pola `user.nip(191)`.
  UNIQUE KEY `nip_unique` (`nip`(191)),
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
('Contoh Ketua, S.H., M.H.', '190000000000000001', 1, 5, 'PNS', 'Pengadilan Agama Rantau', '2015-01-01', 12, 14, 0, ''),
('Contoh Panitera, S.H.', '190000000000000002', 5, 3, 'PNS', 'Pengadilan Agama Rantau', '2018-03-01', 12, 14, 0, ''),
('Contoh Sekretaris, S.H.', '190000000000000004', 6, 4, 'PNS', 'Pengadilan Agama Rantau', '2017-05-01', 12, 14, 0, ''),
('Contoh Kasubag TI, A.Md.', '190000000000000005', 16, 9, 'PNS', 'Pengadilan Agama Rantau', '2019-02-01', 12, 14, 0, ''),
('Contoh Panmud Hukum, S.H.', '190000000000000007', 7, 3, 'PNS', 'Pengadilan Agama Rantau', '2016-04-01', 12, 14, 0, ''),
('Contoh Staf, A.Md.', '190000000000000003', 20, 10, 'PNS', 'Pengadilan Agama Rantau', '2021-06-01', 12, 14, 6, ''),
('Contoh Staf PPPK, A.Md.', '190000000000000006', 22, 10, 'PPPK', 'Pengadilan Agama Rantau', '2022-01-01', 12, 14, 6, '');

-- --------------------------------------------------------

CREATE TABLE `user` (
  `id_user` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `nip` varchar(225) NOT NULL COMMENT 'referensi ke pegawai.nip, bukan buat login',
  `password` varchar(255) NOT NULL COMMENT 'bcrypt hash (password_hash)',
  `failed_attempts` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'reset ke 0 tiap login sukses',
  `locked_until` datetime DEFAULT NULL COMMENT 'akun dikunci sementara sampai jam ini kalau gagal login beruntun',
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
  `dari_tanggal_iso` date NOT NULL COMMENT 'sama kayak dari_tanggal tapi format DATE asli, dipakai buat query (cth. cek riwayat Cuti Besar 5 tahun terakhir) - dari_tanggal (teks Indonesia) gak bisa di-sort/filter',
  `sampai_dengan_iso` date NOT NULL COMMENT 'sama kayak sampai_dengan tapi format DATE asli, dipakai buat cek siapa yg lagi cuti hari ini',
  `panmud_kasubag` varchar(225) DEFAULT NULL,
  `panitera_sekretaris` varchar(225) DEFAULT NULL,
  `ketua` varchar(225) DEFAULT NULL,
  `app_panmud_kasubag` int(2) NOT NULL DEFAULT 0,
  `app_panitera_sekretaris` int(2) NOT NULL DEFAULT 0,
  `app_ketua` int(2) NOT NULL DEFAULT 0,
  `ttd_manual_panmud_kasubag` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'dicentang approver saat approve: tunda TTD digital, cetak dulu TTD basah manual - override tanda_tangan_path profil cuma buat pengajuan ini',
  `ttd_manual_panitera_sekretaris` tinyint(1) NOT NULL DEFAULT 0,
  `ttd_manual_ketua` tinyint(1) NOT NULL DEFAULT 0,
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

-- Jabatan dobel/Plh/Plt - dipakai cuti_resolve_pegawai_by_jabatan() buat
-- ngalihin approval cuti ke pelaksana Plh/Plt selama periode aktif, TANPA
-- ngubah id_jabatan asli si pelaksana (jabatan aslinya tetap, cuma nambah
-- wewenang approval jabatan lain buat sementara).
CREATE TABLE `plh_jabatan` (
  `id_plh` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_jabatan` int(11) UNSIGNED NOT NULL COMMENT 'jabatan yang di-Plh/Plt-kan (bukan jabatan asli si Plh)',
  `id_pegawai` int(11) UNSIGNED NOT NULL COMMENT 'pegawai yang jadi Plh/Plt',
  `jenis` varchar(10) NOT NULL DEFAULT 'Plh' COMMENT 'Plh (Pelaksana Harian) atau Plt (Pelaksana Tugas)',
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date DEFAULT NULL COMMENT 'NULL = belum ditentukan / sampai dicabut manual admin',
  `keterangan` varchar(255) DEFAULT NULL COMMENT 'mis. nomor Surat Perintah PLH dari AURA',
  `dibuat_pada` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_plh`),
  KEY `id_jabatan` (`id_jabatan`),
  KEY `id_pegawai` (`id_pegawai`),
  CONSTRAINT `plh_jabatan_ibfk_1` FOREIGN KEY (`id_jabatan`) REFERENCES `jabatan` (`id_jabatan`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `plh_jabatan_ibfk_2` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawai` (`id_pegawai`) ON DELETE CASCADE ON UPDATE CASCADE
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
  -- MACOA nyimpen ini varchar padahal isinya udah ISO date dari <input type="date">
  -- (gak ada konversi ke teks Indonesia kayak tabel cuti) - di sini dibetulin jadi
  -- DATE asli biar bisa dihitung (+2 tahun) & di-query (siapa yg jatuh tempo).
  `kgb_terakhir` date NOT NULL,
  `kgb_datang` date NOT NULL COMMENT 'default kgb_terakhir + 2 tahun, admin bisa override (mis. tertunda krn hukuman disiplin)',
  `keterangan` varchar(225) NOT NULL DEFAULT '',
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_kgbpegawai`),
  KEY `id_pegawai` (`id_pegawai`),
  CONSTRAINT `kgb_pegawai_ibfk_1` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawai` (`id_pegawai`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

CREATE TABLE `knp_pegawai` (
  `id_knppegawai` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pegawai` int(11) UNSIGNED NOT NULL,
  -- sama kayak kgb_pegawai: MACOA nyimpen tanggal varchar padahal isinya ISO
  -- date, dibetulin jadi DATE asli. Kolom 'keterangan' di MACOA sebenarnya
  -- dropdown pilih golongan tujuan (label-nya aja salah) - di sini dipecah
  -- jadi id_golongan_tujuan (FK, benar2 relasional) + catatan (teks bebas).
  `knp_terakhir` date NOT NULL,
  `knp_datang` date NOT NULL COMMENT 'default knp_terakhir + 4 tahun (siklus KP reguler)',
  `id_golongan_tujuan` int(11) UNSIGNED NOT NULL,
  `catatan` varchar(255) NOT NULL DEFAULT '',
  `pensiun` date DEFAULT NULL COMMENT 'proyeksi tanggal BUP (Batas Usia Pensiun)',
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_knppegawai`),
  KEY `id_pegawai` (`id_pegawai`),
  KEY `id_golongan_tujuan` (`id_golongan_tujuan`),
  CONSTRAINT `knp_pegawai_ibfk_1` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawai` (`id_pegawai`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `knp_pegawai_ibfk_2` FOREIGN KEY (`id_golongan_tujuan`) REFERENCES `golongan` (`id_golongan`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- Notifikasi in-app (bukan WhatsApp/SMS - itu nunggu provider pihak
-- ketiga punya PA Rantau sendiri). Dipicu otomatis dari alur cuti:
-- pengajuan baru -> approver berikutnya, cuti disetujui/ditolak -> pemohon.
CREATE TABLE `notifikasi` (
  `id_notifikasi` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nip` varchar(225) NOT NULL COMMENT 'penerima, nip di tabel user',
  `pesan` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL DEFAULT '',
  `dibaca` tinyint(1) NOT NULL DEFAULT 0,
  `dibuat_pada` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_notifikasi`),
  KEY `nip` (`nip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- Pengaturan branding aplikasi - satu baris (id=1), diedit admin lewat
-- admin/pengaturan.php. Sebelumnya nama_aplikasi/instansi hardcode di
-- config/config.php, sekarang bisa diubah tanpa sentuh file.
CREATE TABLE `pengaturan` (
  `id_pengaturan` int(11) UNSIGNED NOT NULL,
  `nama_aplikasi` varchar(100) NOT NULL,
  `nama_lengkap` varchar(150) NOT NULL,
  `instansi` varchar(150) NOT NULL,
  `logo_path` varchar(255) DEFAULT NULL COMMENT 'logo aplikasi (brand mark topbar & login) - relatif ke assets/img/',
  `logo_instansi_path` varchar(255) DEFAULT NULL COMMENT 'logo instansi (PA Rantau) - tampil di halaman login gantiin badge teks, relatif ke assets/img/',
  `wa_aktif` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'notifikasi WhatsApp via Fonnte on/off - lihat includes/whatsapp.php',
  `wa_fonnte_token` varchar(100) NOT NULL DEFAULT '' COMMENT 'API token Fonnte (docs.fonnte.com), diisi admin/pengaturan.php',
  `diperbarui_pada` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_pengaturan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `pengaturan` (`id_pengaturan`, `nama_aplikasi`, `nama_lengkap`, `instansi`) VALUES
(1, 'LUCU', 'Aplikasi Untuk Cuti', 'Pengadilan Agama Rantau');

-- --------------------------------------------------------

-- Hari libur nasional, disinkron dari API publik (date.nager.at, dataset
-- Nager.Date, bukan hardcode di kode - kalender pemerintah berubah tiap
-- tahun). tahun_sinkron dipakai buat cek "tahun ini udah pernah
-- disinkron belum" tanpa fetch ulang ke API tiap buka kalender.
CREATE TABLE `hari_libur` (
  `id_libur` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `keterangan` varchar(150) NOT NULL,
  `tahun_sinkron` int(4) NOT NULL,
  `disinkron_pada` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_libur`),
  UNIQUE KEY `tanggal` (`tanggal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

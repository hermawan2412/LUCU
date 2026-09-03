<?php
// Dipanggil paling awal di setiap entry point. Urutan penting:
// session_start() harus jalan sebelum output apapun (perbaikan dari MACOA,
// yang panggil session_start() setelah echo).

declare(strict_types=1);

// PHP default ke UTC kalau gak di-set, sementara MySQL server jalan di
// timezone SYSTEM (WITA, ikut jam OS - PA Rantau di Kalimantan Selatan).
// Tanpa ini, semua hitungan "X jam/hari lalu" (notifikasi) dan cek
// tanggal (KGB/KNP jatuh tempo dkk) bisa geser sampai 8 jam.
date_default_timezone_set('Asia/Makassar');

error_reporting(E_ALL);
ini_set('display_errors', '0'); // jangan bocorkan error ke user
ini_set('log_errors', '1');

// error_log() di seluruh app (banyak dipakai buat catch(Throwable) silent-fail
// - lihat wa_kirim()/notifikasi_kirim()/dst) butuh tujuan file eksplisit.
// Tanpa ini, ikut default php.ini yang di server ini gak diset sama sekali
// DAN php-fpm pool `catch_workers_output` default off - artinya SEMUA
// error_log() app selama ini kebuang gitu aja (dicek 2026-09, gak pernah
// nyampe mana pun). storage/ diblok akses web lewat nginx (lihat config
// deploy), sama pola kayak config/includes/database/vendor/templates.
$logDir = __DIR__ . '/../storage';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
ini_set('error_log', $logDir . '/app.log');

// Secure flag otomatis nyala kalau request-nya HTTPS - jangan hardcode true,
// biar dev lokal (HTTP, tanpa TLS) tetep bisa login.
$httpsAktif = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

// Base URL absolut (buat link "Buka" di notifikasi WhatsApp - link relatif
// gak bisa diklik dari luar aplikasi). Diturunkan dari HTTP_HOST request
// yang lagi jalan, bukan hardcode - otomatis benar baik di dev (localhost)
// maupun produksi (restu.pa-rantau.go.id). Kosong kalau dipanggil dari CLI
// (gak ada request HTTP) - notifikasi_kirim() skip nambahin link kalau gini.
define('APP_URL', isset($_SERVER['HTTP_HOST'])
    ? ($httpsAktif ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']
    : '');

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => $httpsAktif,
]);

$config = require __DIR__ . '/config.php';

require_once __DIR__ . '/../vendor/autoload.php'; // phpoffice/phpword - generate .docx asli buat cetak_cuti.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/cuti.php';
require_once __DIR__ . '/../includes/kgb.php';
require_once __DIR__ . '/../includes/knp.php';
require_once __DIR__ . '/../includes/kalender.php';
require_once __DIR__ . '/../includes/libur.php';
require_once __DIR__ . '/../includes/cuti_docx.php';
require_once __DIR__ . '/../includes/export.php';
require_once __DIR__ . '/../includes/upload.php';
require_once __DIR__ . '/../includes/whatsapp.php';
require_once __DIR__ . '/../includes/notifikasi.php';
require_once __DIR__ . '/../includes/log.php';
require_once __DIR__ . '/../includes/layout.php';

$db = db_connect($config['db']);

// Nama aplikasi/instansi dulu hardcode di config.php, sekarang bisa diedit
// admin lewat admin/pengaturan.php (tabel `pengaturan`, baris tunggal).
// config.php dipakai sbg fallback kalau tabel/baris belum ada (mis. baru
// migrasi dari schema lama sebelum `pengaturan` ditambahkan).
try {
    $pengaturan = db_one($db, "SELECT * FROM pengaturan WHERE id_pengaturan = 1");
} catch (PDOException $e) {
    $pengaturan = null;
}
define('APP_NAME', $pengaturan['nama_aplikasi'] ?? $config['app']['name']);
define('APP_FULL_NAME', $pengaturan['nama_lengkap'] ?? $config['app']['full_name']);
define('APP_INSTANSI', $pengaturan['instansi'] ?? $config['app']['instansi']);
define('APP_LOGO_PATH', $pengaturan['logo_path'] ?? null);
define('APP_LOGO_INSTANSI_PATH', $pengaturan['logo_instansi_path'] ?? null);

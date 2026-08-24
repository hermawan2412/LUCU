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

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
]);

$config = require __DIR__ . '/config.php';
define('APP_NAME', $config['app']['name']);
define('APP_FULL_NAME', $config['app']['full_name']);
define('APP_INSTANSI', $config['app']['instansi']);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/cuti.php';
require_once __DIR__ . '/../includes/kgb.php';
require_once __DIR__ . '/../includes/knp.php';
require_once __DIR__ . '/../includes/kalender.php';
require_once __DIR__ . '/../includes/export.php';
require_once __DIR__ . '/../includes/notifikasi.php';
require_once __DIR__ . '/../includes/layout.php';

$db = db_connect($config['db']);

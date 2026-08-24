<?php
// Dipanggil paling awal di setiap entry point. Urutan penting:
// session_start() harus jalan sebelum output apapun (perbaikan dari MACOA,
// yang panggil session_start() setelah echo).

declare(strict_types=1);

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
require_once __DIR__ . '/../includes/layout.php';

$db = db_connect($config['db']);

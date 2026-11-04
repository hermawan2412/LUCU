<?php
// Daftar pegawai yang sedang cuti disetujui hari ini (WITA), untuk ditampilkan di portal kantor.
// Sengaja hanya nama dan tanggal selesai: jenis cuti (mis. sakit) dan alasan tidak ikut dikirim
// karena portal bisa dilihat semua orang di jaringan kantor.
//
// Token terpisah dari api.token: token itu juga membuka api/pegawai_daftar.php (NIP, tanda tangan),
// sedangkan portal berjalan di server lama yang tidak perlu akses sebesar itu.
//
// GET /api/cuti_hari_ini.php
// Header wajib: X-Api-Token: <api.token_portal dari config.php>
//
// Respons 200: {"ok":true,"tanggal":"YYYY-MM-DD","pegawai":[{"nama":"...","sampai":"YYYY-MM-DD"}]}
// Respons 401: {"ok":false,"error":"..."}

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/db.php';

$config = require __DIR__ . '/../config/config.php';
$tokenValid = (string) ($config['api']['token_portal'] ?? '');
$tokenKirim = (string) ($_SERVER['HTTP_X_API_TOKEN'] ?? '');

if ($tokenValid === '' || !hash_equals($tokenValid, $tokenKirim)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Token tidak valid.']);
    exit;
}

date_default_timezone_set('Asia/Makassar');
$hariIni = date('Y-m-d');

$baris = db_all(
    db_connect($config['db']),
    "SELECT p.nama_pegawai AS nama, MAX(c.sampai_dengan_iso) AS sampai
     FROM cuti_pegawai c JOIN pegawai p ON p.id_pegawai = c.id_pegawai
     WHERE c.status_cuti = 'Disetujui'
       AND c.dari_tanggal_iso <= ? AND c.sampai_dengan_iso >= ?
     GROUP BY p.id_pegawai, p.nama_pegawai
     ORDER BY p.nama_pegawai",
    [$hariIni, $hariIni]
);

echo json_encode(['ok' => true, 'tanggal' => $hariIni, 'pegawai' => $baris]);

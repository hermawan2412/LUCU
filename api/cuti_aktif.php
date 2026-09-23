<?php
// Endpoint read-only buat aplikasi lain (RAPAT) cek siapa yang sedang cuti disetujui pada
// rentang tanggal tertentu - dipakai sebagai peringatan lunak di form rapat, bukan pemeriksaan
// mengikat (RAPAT tetap boleh menyimpan rapat walau ada peserta cuti).
//
// Bukan lewat config/bootstrap.php: itu selalu session_start() dan load seluruh app (kalender,
// whatsapp, dll) - berlebihan buat satu query read-only server-ke-server, dan bikin cookie sesi
// ikut kebentuk buat klien yang bukan browser. Konek DB + baca config manual di sini saja.
//
// GET /api/cuti_aktif.php?nip=1234,5678&dari=2026-09-25&sampai=2026-09-25
// Header wajib: X-Api-Token: <token dari config.php>
//
// Respons 200: {"ok":true,"data":{"<nip>":{"jenis_cuti":"...","dari":"YYYY-MM-DD","sampai":"YYYY-MM-DD"}}}
// (nip yang tidak sedang cuti disetujui pada rentang itu tidak muncul di "data")
// Respons 401/400: {"ok":false,"error":"..."}

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';

$config = require __DIR__ . '/../config/config.php';
$tokenValid = (string) ($config['api']['token'] ?? '');

function balas(int $kode, array $body): never
{
    http_response_code($kode);
    echo json_encode($body);
    exit;
}

$tokenKirim = (string) ($_SERVER['HTTP_X_API_TOKEN'] ?? '');
if ($tokenValid === '' || !hash_equals($tokenValid, $tokenKirim)) {
    balas(401, ['ok' => false, 'error' => 'Token tidak valid.']);
}

$nipMentah = trim((string) ($_GET['nip'] ?? ''));
$dari = (string) ($_GET['dari'] ?? '');
$sampai = (string) ($_GET['sampai'] ?? $dari);
$nipList = array_values(array_filter(array_map('trim', explode(',', $nipMentah))));

if (!$nipList || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dari) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampai)) {
    balas(400, ['ok' => false, 'error' => 'Parameter nip, dari, sampai (YYYY-MM-DD) wajib diisi.']);
}
if (count($nipList) > 200) {
    balas(400, ['ok' => false, 'error' => 'Maksimal 200 nip per permintaan.']);
}

$db = db_connect($config['db']);
$placeholder = implode(',', array_fill(0, count($nipList), '?'));
$baris = db_all(
    $db,
    "SELECT p.nip, c.jenis_cuti, c.dari_tanggal_iso, c.sampai_dengan_iso
     FROM cuti_pegawai c JOIN pegawai p ON p.id_pegawai = c.id_pegawai
     WHERE p.nip IN ($placeholder) AND c.status_cuti = 'Disetujui'
       AND c.dari_tanggal_iso <= ? AND c.sampai_dengan_iso >= ?",
    [...$nipList, $sampai, $dari]
);

$data = [];
foreach ($baris as $b) {
    // Beberapa cuti beririsan buat nip yang sama (jarang, tapi mungkin) - ambil yang pertama saja,
    // cukup buat peringatan lunak, RAPAT tidak butuh daftar lengkap tiap nip.
    $data[$b['nip']] ??= [
        'jenis_cuti' => $b['jenis_cuti'],
        'dari' => $b['dari_tanggal_iso'],
        'sampai' => $b['sampai_dengan_iso'],
    ];
}

balas(200, ['ok' => true, 'data' => $data]);

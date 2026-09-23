<?php
// Endpoint read-only buat RAPAT sinkron data pegawai ke tabel `orang`-nya. Dipanggil manual
// (tombol "Sinkron RESTU" di RAPAT), bukan cron - RESTU tidak pernah mendorong data, RAPAT yang
// menarik saat diminta.
//
// Sama pola dengan api/cuti_aktif.php: bukan lewat config/bootstrap.php, token sama
// (config.php -> api.token).
//
// GET /api/pegawai_daftar.php
// Header wajib: X-Api-Token: <token dari config.php>
//
// Respons 200: {"ok":true,"data":[{"id_eksternal":"8","nip":"...","nama":"...","jabatan":"...",
//   "pangkat_golongan":"...","no_wa":"..."}]}
// Nama field respons SENGAJA beda dari nama kolom asli RESTU (nama_pegawai->nama,
// nama_jabatan->jabatan, nama_golongan->pangkat_golongan, no_telp->no_wa) supaya sisi RAPAT
// tidak perlu tahu skema internal RESTU - kontrak API-nya independen.
// pegawai TIDAK punya kolom status aktif/nonaktif (dicek 2026-09-23) - semua baris dianggap aktif.

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

$db = db_connect($config['db']);
$baris = db_all(
    $db,
    'SELECT p.id_pegawai, p.nip, p.nama_pegawai, j.nama_jabatan, g.nama_golongan, p.no_telp
     FROM pegawai p
     LEFT JOIN jabatan j ON j.id_jabatan = p.id_jabatan
     LEFT JOIN golongan g ON g.id_golongan = p.id_golongan
     ORDER BY p.nama_pegawai'
);

$data = array_map(static fn (array $p) => [
    'id_eksternal' => (string) $p['id_pegawai'],
    'nip' => $p['nip'] !== '' ? $p['nip'] : null,
    'nama' => $p['nama_pegawai'],
    'jabatan' => $p['nama_jabatan'],
    'pangkat_golongan' => $p['nama_golongan'],
    'no_wa' => $p['no_telp'] !== '' ? $p['no_telp'] : null,
], $baris);

balas(200, ['ok' => true, 'data' => $data]);

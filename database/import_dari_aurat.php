<?php
// Import satu-kali data pegawai dari AURA (192.168.100.7, tabel
// `pegawai`) ke RESTU. Dijalankan manual dari CLI:
//   php database/import_dari_aurat.php [--terapkan]
//
// Tanpa --terapkan: cuma preview (dry-run), gak nulis apapun ke RESTU.
// READ-ONLY ke AURA - script ini gak pernah INSERT/UPDATE/DELETE ke
// koneksi $dbAurat, cuma SELECT.
//
// Jabatan AURA itu teks bebas ("Kepala Subbagian Umum"), sementara
// RESTU pakai daftar jabatan terkontrol (buat rute approval cuti) -
// jadi dicocokkan by name (case-insensitive). Yang gak ketemu match
// PASTI, di-skip & dilaporkan - bukan ditebak, biar gak salah rute
// approval siapa yang harus nyetujuin cuti siapa.

declare(strict_types=1);

$config = require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

if (empty($config['db_aurat']['host'])) {
    fwrite(STDERR, "config['db_aurat'] belum diisi (host kosong). Isi dulu di config/config.php.\n");
    exit(1);
}

$terapkan = in_array('--terapkan', $argv, true);
echo $terapkan ? "MODE: TERAPKAN (nulis ke RESTU)\n\n" : "MODE: PREVIEW (dry-run, gak nulis apapun)\n\n";

$dbLucu = db_connect($config['db']);
$dbAurat = db_connect($config['db_aurat']);

$golonganLucu = db_all($dbLucu, "SELECT id_golongan, nama_golongan FROM golongan");
$golonganByNama = [];
foreach ($golonganLucu as $g) {
    $golonganByNama[strtoupper(trim($g['nama_golongan']))] = (int) $g['id_golongan'];
}

$jabatanLucu = db_all($dbLucu, "SELECT id_jabatan, nama_jabatan FROM jabatan");
$jabatanByNama = [];
foreach ($jabatanLucu as $j) {
    $jabatanByNama[strtoupper(trim($j['nama_jabatan']))] = (int) $j['id_jabatan'];
}

$pegawaiAurat = db_all($dbAurat, "SELECT nip, nama_lengkap, gelar_depan, gelar_belakang, pangkat, golongan_ruang, jabatan, unit_kerja
    FROM pegawai WHERE status_aktif = 1 ORDER BY nama_lengkap");

echo count($pegawaiAurat) . " pegawai aktif ditemukan di AURA.\n\n";

$masuk = 0;
$dilewati = [];

foreach ($pegawaiAurat as $p) {
    $nama = trim(($p['gelar_depan'] ? $p['gelar_depan'] . ' ' : '') . $p['nama_lengkap'] . ($p['gelar_belakang'] ? ', ' . $p['gelar_belakang'] : ''));
    $nip = trim($p['nip']);

    $idGolongan = $golonganByNama[strtoupper(trim((string) $p['golongan_ruang']))] ?? null;
    $idJabatan = $jabatanByNama[strtoupper(trim((string) $p['jabatan']))] ?? null;

    if ($idJabatan === null || $idGolongan === null) {
        $dilewati[] = [
            'nama' => $nama, 'nip' => $nip,
            'jabatan_aurat' => $p['jabatan'], 'golongan_aurat' => $p['golongan_ruang'],
            'sebab' => $idJabatan === null ? 'jabatan gak ketemu match di RESTU' : 'golongan gak ketemu match di RESTU',
        ];
        continue;
    }

    printf("  OK  %-40s NIP %s -> jabatan #%d, golongan #%d\n", $nama, $nip, $idJabatan, $idGolongan);

    if ($terapkan) {
        db_query($dbLucu, "INSERT INTO pegawai (nama_pegawai, nip, id_jabatan, id_golongan, unit_kerja, hak_cuti_tahunan)
            VALUES (?, ?, ?, ?, ?, 12)
            ON DUPLICATE KEY UPDATE nama_pegawai = VALUES(nama_pegawai), id_jabatan = VALUES(id_jabatan),
                id_golongan = VALUES(id_golongan), unit_kerja = VALUES(unit_kerja)",
            [$nama, $nip, $idJabatan, $idGolongan, $p['unit_kerja'] ?: $config['app']['instansi']]);
    }
    $masuk++;
}

echo "\n$masuk pegawai " . ($terapkan ? 'diimport/diperbarui' : 'siap diimport') . ".\n";

if (!empty($dilewati)) {
    echo "\n" . count($dilewati) . " DILEWATI (jabatan/golongan gak ketemu match otomatis - tambahkan manual lewat admin/data_pegawai.php):\n";
    foreach ($dilewati as $d) {
        printf("  - %-40s NIP %s | jabatan AURA: \"%s\" | golongan AURA: \"%s\" (%s)\n",
            $d['nama'], $d['nip'], $d['jabatan_aurat'], $d['golongan_aurat'], $d['sebab']);
    }
}

if (!$terapkan) {
    echo "\nIni cuma preview. Jalankan lagi dengan --terapkan buat benar-benar nulis ke RESTU:\n";
    echo "  php database/import_dari_aurat.php --terapkan\n";
}

echo "\nCatatan: jenis_asn (PNS/PPPK), TMT, dan hak cuti sakit/penting gak ada di AURA -\n";
echo "semua pegawai baru masuk default PNS, TMT kosong. Cek & lengkapi manual kalau perlu\n";
echo "(khususnya buat pegawai PPPK dan yang butuh cek syarat Cuti Besar/CLTN).\n";

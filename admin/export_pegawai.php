<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('Admin');

$list = db_all($db, "SELECT p.*, j.nama_jabatan, g.nama_golongan
    FROM pegawai p
    JOIN jabatan j ON j.id_jabatan = p.id_jabatan
    JOIN golongan g ON g.id_golongan = p.id_golongan
    ORDER BY p.nama_pegawai ASC");
foreach ($list as &$r) {
    $r = cuti_tahunan_rollover_jika_perlu($db, $r);
    $r['kuota_tersedia'] = cuti_tahunan_kuota_tersedia($r);
}
unset($r);

$rows = array_map(fn($r) => [
    $r['nama_pegawai'], $r['nip'], $r['nama_jabatan'], $r['nama_golongan'], $r['jenis_asn'],
    $r['unit_kerja'], $r['tmt_pegawai'] ? indonesia_tgl($r['tmt_pegawai']) : '',
    $r['hak_cuti_tahunan'], $r['cuti_tahunan_n1'], $r['cuti_tahunan_n2'], $r['kuota_tersedia'],
    $r['hak_cuti_sakit'], $r['hak_cuti_penting'], $r['no_telp'],
], $list);

csv_download(
    'data-pegawai-' . date('Y-m-d') . '.csv',
    ['Nama', 'NIP', 'Jabatan', 'Golongan', 'Jenis ASN', 'Unit Kerja', 'TMT', 'Cuti Tahunan (N)', 'Cuti Tahunan (N-1)', 'Cuti Tahunan (N-2)', 'Total Kuota Tersedia', 'Hak Cuti Sakit', 'Hak Cuti Penting', 'No. Telepon'],
    $rows
);

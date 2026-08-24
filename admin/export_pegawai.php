<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('Admin');

$list = db_all($db, "SELECT p.nama_pegawai, p.nip, j.nama_jabatan, g.nama_golongan, p.jenis_asn,
        p.unit_kerja, p.tmt_pegawai, p.hak_cuti_tahunan, p.hak_cuti_sakit, p.hak_cuti_penting, p.no_telp
    FROM pegawai p
    JOIN jabatan j ON j.id_jabatan = p.id_jabatan
    JOIN golongan g ON g.id_golongan = p.id_golongan
    ORDER BY p.nama_pegawai ASC");

$rows = array_map(fn($r) => [
    $r['nama_pegawai'], $r['nip'], $r['nama_jabatan'], $r['nama_golongan'], $r['jenis_asn'],
    $r['unit_kerja'], $r['tmt_pegawai'] ? indonesia_tgl($r['tmt_pegawai']) : '',
    $r['hak_cuti_tahunan'], $r['hak_cuti_sakit'], $r['hak_cuti_penting'], $r['no_telp'],
], $list);

csv_download(
    'data-pegawai-' . date('Y-m-d') . '.csv',
    ['Nama', 'NIP', 'Jabatan', 'Golongan', 'Jenis ASN', 'Unit Kerja', 'TMT', 'Hak Cuti Tahunan', 'Hak Cuti Sakit', 'Hak Cuti Penting', 'No. Telepon'],
    $rows
);

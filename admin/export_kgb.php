<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('Admin');

$list = kgb_daftar_terbaru_per_pegawai($db);

$rows = array_map(fn($r) => [
    $r['nama_pegawai'], $r['nip'], $r['nama_jabatan'], $r['nama_golongan'],
    $r['kgb_terakhir'] ? indonesia_tgl($r['kgb_terakhir']) : '-',
    $r['kgb_datang'] ? indonesia_tgl($r['kgb_datang']) : '-',
    kgb_status_label(kgb_status($r['kgb_datang'])),
    $r['keterangan'] ?? '',
], $list);

csv_download(
    'data-kgb-' . date('Y-m-d') . '.csv',
    ['Nama', 'NIP', 'Jabatan', 'Golongan', 'KGB Terakhir', 'KGB Akan Datang', 'Status', 'Keterangan'],
    $rows
);

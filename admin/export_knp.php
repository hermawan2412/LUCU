<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('Admin');

$list = knp_daftar_terbaru_per_pegawai($db);

$rows = array_map(fn($r) => [
    $r['nama_pegawai'], $r['nip'], $r['nama_jabatan'], $r['nama_golongan'],
    $r['knp_terakhir'] ? indonesia_tgl($r['knp_terakhir']) : '-',
    $r['knp_datang'] ? indonesia_tgl($r['knp_datang']) : '-',
    $r['nama_golongan_tujuan'] ?? '-',
    knp_status_label(knp_status($r['knp_datang'])),
    $r['pensiun'] ? indonesia_tgl($r['pensiun']) : '-',
    $r['catatan'] ?? '',
], $list);

csv_download(
    'data-knp-' . date('Y-m-d') . '.csv',
    ['Nama', 'NIP', 'Jabatan', 'Golongan Saat Ini', 'KNP Terakhir', 'KNP Akan Datang', 'Golongan Tujuan', 'Status', 'Proyeksi Pensiun', 'Catatan'],
    $rows
);

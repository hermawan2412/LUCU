<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('Admin');

$list = db_all($db, "SELECT p.nama_pegawai, p.nip, c.jenis_cuti, c.dari_tanggal, c.sampai_dengan,
        c.lama_cuti, c.ket_lama_cuti, c.status_cuti, c.ket_status_cuti, c.tgl_pengajuan
    FROM cuti_pegawai c JOIN pegawai p ON p.id_pegawai = c.id_pegawai
    ORDER BY c.id_cutipegawai DESC");

$rows = array_map(fn($r) => [
    $r['nama_pegawai'], $r['nip'], $r['jenis_cuti'], $r['dari_tanggal'], $r['sampai_dengan'],
    $r['lama_cuti'] . ' ' . $r['ket_lama_cuti'], $r['status_cuti'], $r['ket_status_cuti'], $r['tgl_pengajuan'],
], $list);

csv_download(
    'data-cuti-' . date('Y-m-d') . '.csv',
    ['Nama', 'NIP', 'Jenis Cuti', 'Dari Tanggal', 'Sampai Dengan', 'Lama', 'Status', 'Keterangan', 'Tanggal Pengajuan'],
    $rows
);

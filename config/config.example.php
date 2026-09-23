<?php
// Salin file ini jadi config.php, isi kredensial asli di sana.
// config.php di-gitignore - JANGAN commit kredensial ke repo.

return [
    'db' => [
        'host' => 'localhost',
        'name' => 'lucu_local',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name' => 'RESTU',
        'full_name' => 'Rekam Elektronik Sistem cuTi Untuk aparatur',
        'instansi' => 'Pengadilan Agama Rantau',
    ],

    // Opsional: sumber data pegawai buat import satu-kali dari AURA
    // (database/import_dari_aurat.php). Kosongin/hapus blok ini kalau
    // gak dipakai.
    'db_aurat' => [
        'host' => '',
        'name' => 'aura',
        'user' => '',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],

    // Token buat api/cuti_aktif.php (dipanggil RAPAT server-ke-server, header
    // X-Api-Token). Kosong = endpoint selalu menolak (401). Acak panjang,
    // mis. bin2hex(random_bytes(24)); sama nilainya harus diisi di RAPAT
    // (Pengaturan > Integrasi RESTU).
    'api' => [
        'token' => '',
    ],
];

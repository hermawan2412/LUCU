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
        'name' => 'LUCU',
        'full_name' => 'Aplikasi Untuk Cuti',
        'instansi' => 'Pengadilan Agama Rantau',
    ],
];

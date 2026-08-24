<?php
// Jalankan sekali via CLI: php database/seed.php
// Bikin akun dummy dengan password ter-hash bcrypt (bukan MD5 kayak MACOA).

declare(strict_types=1);

$config = require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$db = db_connect($config['db']);

$accounts = [
    ['nip' => '190000000000000001', 'password' => 'rahasia123', 'role' => 'Admin'],
    ['nip' => '190000000000000003', 'password' => 'rahasia123', 'role' => 'User'],
];

foreach ($accounts as $acc) {
    $exists = db_one($db, "SELECT id_user FROM user WHERE nip = ?", [$acc['nip']]);
    if ($exists) {
        echo "Skip {$acc['nip']}, sudah ada.\n";
        continue;
    }
    $hash = password_hash($acc['password'], PASSWORD_BCRYPT);
    db_query($db, "INSERT INTO user (nip, password, role) VALUES (?, ?, ?)", [$acc['nip'], $hash, $acc['role']]);
    echo "Dibuat: {$acc['nip']} / {$acc['password']} ({$acc['role']})\n";
}

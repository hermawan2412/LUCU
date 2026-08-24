<?php
require_once __DIR__ . '/config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

csrf_verify();

$nip = trim($_POST['nip'] ?? '');
$password = (string) ($_POST['password'] ?? '');

if ($nip === '' || $password === '') {
    flash_set('error', 'NIP dan kata sandi wajib diisi.');
    redirect('index.php');
}

$user = auth_attempt($db, $nip, $password);

if ($user === null) {
    flash_set('error', 'NIP atau kata sandi salah.');
    redirect('index.php');
}

auth_login($user);
redirect($user['role'] === 'Admin' ? 'admin/index.php' : 'user/index.php');

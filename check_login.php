<?php
require_once __DIR__ . '/config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

csrf_verify();

$username = trim($_POST['username'] ?? '');
$password = (string) ($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    flash_set('error', 'Username dan kata sandi wajib diisi.');
    redirect('index.php');
}

$user = auth_attempt($db, $username, $password);

if ($user === null) {
    log_aktivitas($db, 'login_gagal', "Percobaan login username \"$username\"", null, $username);
    flash_set('error', 'Username atau kata sandi salah.');
    redirect('index.php');
}

auth_login($user);
log_aktivitas($db, 'login', '', $user['nip'], $user['username']);
redirect($user['role'] === 'Admin' ? 'admin/index.php' : 'user/index.php');

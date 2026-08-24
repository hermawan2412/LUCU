<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('User');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title><?= e(APP_NAME) ?> | Dashboard Pegawai</title>
  <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body style="padding:40px;">
  <h1><?= e(APP_NAME) ?> — Dashboard Pegawai</h1>
  <p>Login sebagai NIP: <?= e($_SESSION['nip']) ?> (<?= e($_SESSION['role']) ?>)</p>
  <p><a href="../logout.php">Keluar</a></p>
  <p style="color:#888">Modul: pengajuan cuti, riwayat, sisa cuti, approval — menyusul.</p>
</body>
</html>

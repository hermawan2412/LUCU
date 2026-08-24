<?php
require_once __DIR__ . '/config/bootstrap.php';

if (auth_check()) {
    redirect($_SESSION['role'] === 'Admin' ? 'admin/index.php' : 'user/index.php');
}

$error = flash_get('error');
$statCuti = cuti_statistik_hari_ini($db);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(APP_NAME) ?> | Login</title>
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <div class="login-shell">
    <div class="login-brand">
      <span class="badge"><?= e(APP_INSTANSI) ?></span>
      <div class="brand-mark"><?= brand_mark_svg(34) ?><span class="wordmark"><?= e(APP_NAME) ?></span></div>
      <h1><?= e(APP_FULL_NAME) ?><br><em>layanan cuti, tanpa antre.</em></h1>
      <p>Pengajuan dan persetujuan cuti pegawai secara daring — dari pengajuan sampai disetujui, satu alur, satu sistem.</p>

      <div class="login-stat login-stat-<?= $statCuti['siaga'] ? 'danger' : 'success' ?>">
        <div class="login-stat-num"><?= $statCuti['persen'] ?>%</div>
        <div class="login-stat-label">
          Pegawai sedang cuti hari ini<br>
          <?= $statCuti['sedang_cuti'] ?> dari <?= $statCuti['total'] ?> pegawai
        </div>
      </div>
    </div>
    <div class="login-form-wrap">
      <div class="login-card">
        <h2>Masuk</h2>
        <p class="sub">Gunakan username dan kata sandi akun Anda.</p>

        <?php if ($error): ?>
          <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="check_login.php">
          <?= csrf_field() ?>
          <div class="field">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" autocomplete="username" required autofocus>
          </div>
          <div class="field">
            <label for="password">Kata Sandi</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
          </div>
          <button type="submit" class="btn-primary">Masuk</button>
        </form>
      </div>
    </div>
  </div>
  <p class="login-footer" style="position:fixed;bottom:12px;left:0;right:0;">
    <?= e(APP_NAME) ?> — <?= e(APP_INSTANSI) ?>
  </p>
</body>
</html>

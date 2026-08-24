<?php
require_once __DIR__ . '/config/bootstrap.php';

if (auth_check()) {
    redirect($_SESSION['role'] === 'Admin' ? 'admin/index.php' : 'user/index.php');
}

$error = flash_get('error');
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
      <span class="badge">● <?= e(APP_INSTANSI) ?></span>
      <h1><?= e(APP_NAME) ?></h1>
      <p><?= e(APP_FULL_NAME) ?> — layanan pengajuan dan persetujuan cuti pegawai secara daring.</p>
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

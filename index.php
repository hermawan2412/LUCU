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
  <!-- Link "Buka: ..." di notifikasi WA (includes/notifikasi.php) ngarah ke
       halaman yg butuh login, jadi crawler preview WA/link-unfurler
       ke-redirect ke halaman login INI (satu-satunya halaman yg keliatan
       tanpa sesi). Dulu SENGAJA gak declare og:image sama sekali biar
       preview teks doang - ternyata gak ngaruh, WhatsApp/Fonnte tetap
       fallback nebak & ambil <img> logo instansi PERTAMA di halaman ini
       apa adanya (dimensi FILE ASLI yg admin upload, bisa >1MB, bukan
       atribut width/height HTML-nya) - itu penyebab logo gede nongol di
       pesan WA. Fix: declare og:image eksplisit ke thumbnail kecil
       (og_image_url(), lihat includes/helpers.php), biar crawler-nya gak
       perlu nebak2 lagi. Null kalau logo instansi belum di-set admin. -->
  <meta property="og:title" content="<?= e(APP_NAME) ?>">
  <meta property="og:description" content="<?= e(APP_FULL_NAME) ?>">
  <?php if ($ogImage = og_image_url()): ?>
  <meta property="og:image" content="<?= e(APP_URL) ?>/<?= $ogImage ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <div class="login-shell">
    <div class="login-bg" aria-hidden="true"><span></span><span></span><span></span></div>
    <div class="login-topleft">
      <div class="login-logos">
        <?= logo_instansi_html(90) ?>
        <div class="login-logos-divider"></div>
        <div class="brand-mark"><?= brand_mark_svg(90) ?><span class="wordmark"><?= e(APP_NAME) ?></span></div>
      </div>
      <h1 class="login-title"><?= e(APP_FULL_NAME) ?><br><em><?= e(APP_TAGLINE) ?></em></h1>
    </div>

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

    <div class="login-stat login-stat-<?= $statCuti['siaga'] ? 'danger' : 'success' ?> login-stat-floating">
      <div class="login-stat-num"><?= $statCuti['persen'] ?>%</div>
      <div class="login-stat-label">
        Pegawai sedang cuti hari ini<br>
        <?= $statCuti['sedang_cuti'] ?> dari <?= $statCuti['total'] ?> pegawai
      </div>
    </div>

    <p class="login-footer"><?= e(APP_NAME) ?> — <?= e(APP_INSTANSI) ?></p>
  </div>
  <script src="assets/js/app.js"></script>
</body>
</html>

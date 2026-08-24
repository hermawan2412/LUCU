<?php
// Partial header/footer buat halaman setelah login. Semua href nav relatif
// terhadap folder tempat file pemanggil berada (user/ atau admin/), jadi
// tiap section punya nav sendiri.

declare(strict_types=1);

function layout_header(string $title, string $active = '', string $section = 'user'): void
{
    $navUser = [
        'dashboard' => ['label' => 'Dashboard', 'href' => 'index.php'],
        'ajukan' => ['label' => 'Ajukan Cuti', 'href' => 'pengajuan_cuti.php'],
        'riwayat' => ['label' => 'Riwayat Cuti', 'href' => 'daftar_cuti.php'],
        'approval' => ['label' => 'Approval', 'href' => 'approve_cuti.php'],
    ];
    $navAdmin = [
        'dashboard' => ['label' => 'Dashboard', 'href' => 'index.php'],
        'pegawai' => ['label' => 'Data Pegawai', 'href' => 'data_pegawai.php'],
        'jabatan' => ['label' => 'Data Jabatan', 'href' => 'data_jabatan.php'],
        'golongan' => ['label' => 'Data Golongan', 'href' => 'data_golongan.php'],
        'kgb' => ['label' => 'KGB', 'href' => 'data_kgb.php'],
        'knp' => ['label' => 'KNP', 'href' => 'data_knp.php'],
    ];
    $nav = $section === 'admin' ? $navAdmin : $navUser;
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(APP_NAME) ?> | <?= e($title) ?></title>
  <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body>
  <div class="topbar">
    <div class="brand-mark">
      <?= brand_mark_svg(24) ?>
      <span>
        <span class="wordmark"><?= e(APP_NAME) ?><?= $section === 'admin' ? ' · Admin' : '' ?></span>
        <span class="instansi"><?= e(APP_INSTANSI) ?></span>
      </span>
    </div>
    <nav>
      <?php foreach ($nav as $key => $item): ?>
        <a href="<?= e($item['href']) ?>" class="<?= $active === $key ? 'active' : '' ?>"><?= e($item['label']) ?></a>
      <?php endforeach; ?>
      <a href="../logout.php">Keluar</a>
    </nav>
    <div class="who"><?= e($_SESSION['username'] ?? '') ?></div>
  </div>
  <div class="page">
    <?php
}

function layout_footer(): void
{
    ?>
  </div>
</body>
</html>
    <?php
}

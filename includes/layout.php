<?php
// Partial header/footer buat halaman setelah login. $base harus diisi
// relatif dari file pemanggil ('.' buat root user/admin, '..' gak dipakai
// di sini karena tiap halaman ada di dalam user/ atau admin/).

declare(strict_types=1);

function layout_header(string $title, string $active = ''): void
{
    $nav = [
        'dashboard' => ['label' => 'Dashboard', 'href' => 'index.php'],
        'ajukan' => ['label' => 'Ajukan Cuti', 'href' => 'pengajuan_cuti.php'],
        'riwayat' => ['label' => 'Riwayat Cuti', 'href' => 'daftar_cuti.php'],
        'approval' => ['label' => 'Approval', 'href' => 'approve_cuti.php'],
    ];
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
    <div class="brand"><?= e(APP_NAME) ?> · <?= e(APP_INSTANSI) ?></div>
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

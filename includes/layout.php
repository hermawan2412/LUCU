<?php
// Partial header/footer buat halaman setelah login. Semua href nav relatif
// terhadap folder tempat file pemanggil berada (user/ atau admin/), jadi
// tiap section punya nav sendiri.

declare(strict_types=1);

function layout_bell_svg(): string
{
    return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path d="M6 10a6 6 0 1 1 12 0c0 3.2 1 5 1.6 5.8.3.4 0 1.2-.6 1.2H5c-.6 0-.9-.8-.6-1.2C5 15 6 13.2 6 10Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
        <path d="M9.5 19.5a2.5 2.5 0 0 0 5 0" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
    </svg>';
}

function layout_header(string $title, string $active = '', string $section = 'user'): void
{
    global $db;

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
        'pengaturan' => ['label' => 'Pengaturan', 'href' => 'pengaturan.php'],
    ];
    $nav = $section === 'admin' ? $navAdmin : $navUser;

    // Bell: user dapet notifikasi personal (persisted, dipicu alur cuti);
    // admin dapet ringkasan live (KGB/KNP jatuh tempo + cuti diajukan) -
    // gak perlu tabel notifikasi terpisah buat admin, datanya udah ada.
    $bellCount = 0;
    $bellHref = 'index.php';
    if ($section === 'user' && isset($_SESSION['nip'])) {
        $bellCount = notifikasi_belum_dibaca_count($db, $_SESSION['nip']);
        $bellHref = 'notifikasi.php';
    } elseif ($section === 'admin') {
        $bellCount = (int) db_one($db, "SELECT COUNT(*) AS n FROM cuti_pegawai WHERE status_cuti = 'Diajukan'")['n'];
    }
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
      <?= brand_mark_svg(24, '../') ?>
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
    <div style="display:flex; align-items:center; gap:10px;">
      <a href="<?= e($bellHref) ?>" class="notif-bell<?= $bellCount > 0 ? ' has-unread' : '' ?>" title="<?= $section === 'admin' ? 'Cuti sedang diajukan' : 'Notifikasi' ?>">
        <?= layout_bell_svg() ?>
        <?php if ($bellCount > 0): ?><span class="notif-count"><?= $bellCount > 9 ? '9+' : $bellCount ?></span><?php endif; ?>
      </a>
      <div class="who"><?= e($_SESSION['username'] ?? '') ?></div>
    </div>
  </div>
  <div class="page">
    <?php
}

function layout_footer(): void
{
    ?>
  </div>
  <script src="../assets/js/app.js"></script>
</body>
</html>
    <?php
}

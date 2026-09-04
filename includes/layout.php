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

/** Tombol toggle tema (Auto/Terang/Gelap) - state awal di-render "auto" krn PHP gak tau localStorage,
 * app.js langsung koreksi data-mode-nya di awal load sebelum keliatan (lihat theme-init inline script). */
function layout_theme_toggle_svg(): string
{
    return '<button type="button" class="theme-toggle" data-mode="auto" title="Tema" aria-label="Ganti tema">
        <svg class="icon-auto" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.6"/>
            <path d="M12 3.5a8.5 8.5 0 0 1 0 17Z" fill="currentColor"/>
        </svg>
        <svg class="icon-light" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <circle cx="12" cy="12" r="4.5" stroke="currentColor" stroke-width="1.6"/>
            <path d="M12 2.5v2.5M12 19v2.5M21.5 12H19M5 12H2.5M18.5 5.5l-1.8 1.8M7.3 16.7l-1.8 1.8M18.5 18.5l-1.8-1.8M7.3 7.3 5.5 5.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
        </svg>
        <svg class="icon-dark" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
        </svg>
    </button>';
}

function layout_header(string $title, string $active = '', string $section = 'user'): void
{
    global $db;

    $navUser = [
        'dashboard' => ['label' => 'Dashboard', 'href' => 'index.php'],
        'ajukan' => ['label' => 'Ajukan Cuti', 'href' => 'pengajuan_cuti.php'],
        'riwayat' => ['label' => 'Riwayat Cuti', 'href' => 'daftar_cuti.php'],
        'approval' => ['label' => 'Approval', 'href' => 'approve_cuti.php'],
        'profil' => ['label' => 'Profil Saya', 'href' => 'profil.php'],
    ];
    $navAdmin = [
        'dashboard' => ['label' => 'Dashboard', 'href' => 'index.php'],
        'pegawai' => ['label' => 'Data Pegawai', 'href' => 'data_pegawai.php'],
        'jabatan' => ['label' => 'Data Jabatan', 'href' => 'data_jabatan.php'],
        'plh' => ['label' => 'Data Plh/Plt', 'href' => 'data_plh.php'],
        'golongan' => ['label' => 'Data Golongan', 'href' => 'data_golongan.php'],
        'cuti' => ['label' => 'Data Cuti', 'href' => 'data_cuti.php'],
        'kgb' => ['label' => 'KGB', 'href' => 'data_kgb.php'],
        'knp' => ['label' => 'KNP', 'href' => 'data_knp.php'],
        'akun' => ['label' => 'Kelola Akun', 'href' => 'data_user.php'],
        'log' => ['label' => 'Log', 'href' => 'data_log.php'],
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
        $bellCount = (int) db_one($db, "SELECT COUNT(*) AS n FROM cuti_pegawai WHERE status_cuti IN ('Diajukan', 'Menunggu Nomor Surat')")['n'];
    }
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(APP_NAME) ?> | <?= e($title) ?></title>
  <script>(function(){try{var m=localStorage.getItem('restu-theme');if(m==='light'||m==='dark')document.documentElement.setAttribute('data-theme',m);}catch(e){}})();</script>
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
      <a href="changelog.php" class="version-badge" title="Lihat riwayat pembaruan">v<?= e(changelog_versi_terbaru()) ?></a>
    </div>
    <nav>
      <?php foreach ($nav as $key => $item): ?>
        <a href="<?= e($item['href']) ?>" class="<?= $active === $key ? 'active' : '' ?>"><?= e($item['label']) ?></a>
      <?php endforeach; ?>
      <a href="../logout.php">Keluar</a>
    </nav>
    <div style="display:flex; align-items:center; gap:10px;">
      <?= layout_theme_toggle_svg() ?>
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

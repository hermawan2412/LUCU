<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('Admin');

$totalPegawai = db_one($db, "SELECT COUNT(*) AS n FROM pegawai")['n'];
$totalJabatan = db_one($db, "SELECT COUNT(*) AS n FROM jabatan")['n'];
$totalGolongan = db_one($db, "SELECT COUNT(*) AS n FROM golongan")['n'];
$pengajuanAktif = db_one($db, "SELECT COUNT(*) AS n FROM cuti_pegawai WHERE status_cuti = 'Diajukan'")['n'];
$kgbList = kgb_daftar_terbaru_per_pegawai($db);
$kgbOverdue = count(array_filter($kgbList, fn($r) => kgb_status($r['kgb_datang']) === 'overdue'));

layout_header('Dashboard Admin', 'dashboard', 'admin');
?>
<h1>Dashboard Admin</h1>
<p class="lead">Selamat datang, <?= e($_SESSION['username']) ?>.</p>

<div class="stat-row">
  <div class="stat-tile"><div class="num"><?= (int) $totalPegawai ?></div><div class="label">Total Pegawai</div></div>
  <div class="stat-tile"><div class="num"><?= (int) $totalJabatan ?></div><div class="label">Jabatan</div></div>
  <div class="stat-tile"><div class="num"><?= (int) $totalGolongan ?></div><div class="label">Golongan</div></div>
  <div class="stat-tile"><div class="num"><?= (int) $pengajuanAktif ?></div><div class="label">Cuti Sedang Diajukan</div></div>
  <div class="stat-tile"><div class="num" style="color:<?= $kgbOverdue > 0 ? 'var(--danger)' : 'inherit' ?>"><?= $kgbOverdue ?></div><div class="label">KGB Jatuh Tempo</div></div>
</div>

<div class="card">
  <p><a href="data_pegawai.php" class="btn-secondary">Kelola Data Pegawai</a>
     <a href="data_jabatan.php" class="btn-secondary">Kelola Data Jabatan</a>
     <a href="data_golongan.php" class="btn-secondary">Kelola Data Golongan</a>
     <a href="data_kgb.php" class="btn-secondary">Kelola KGB<?= $kgbOverdue > 0 ? " ($kgbOverdue)" : '' ?></a></p>
</div>
<?php layout_footer(); ?>

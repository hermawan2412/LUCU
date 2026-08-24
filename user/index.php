<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('User');

$pegawai = cuti_get_pegawai_by_nip($db, $_SESSION['nip']);
$error = flash_get('error');
$pendingCount = $pegawai ? cuti_pending_count_for_approver($db, $pegawai['nip']) : 0;

layout_header('Dashboard', 'dashboard');
?>
<h1>Dashboard Pegawai</h1>
<p class="lead">Selamat datang, <?= e($_SESSION['username']) ?>.</p>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($pegawai): ?>
  <div class="stat-row">
    <div class="stat-tile">
      <div class="num"><?= (int) $pegawai['hak_cuti_tahunan'] ?></div>
      <div class="label">Sisa Cuti Tahunan</div>
    </div>
    <div class="stat-tile">
      <div class="num" style="font-size:1rem"><?= e($pegawai['nama_jabatan']) ?></div>
      <div class="label">Jabatan</div>
    </div>
    <div class="stat-tile">
      <div class="num" style="font-size:1rem"><?= e(cuti_masa_kerja($pegawai['tmt_pegawai'])) ?></div>
      <div class="label">Masa Kerja</div>
    </div>
    <div class="stat-tile">
      <div class="num"><?= $pendingCount ?></div>
      <div class="label">Menunggu Approval Anda</div>
    </div>
  </div>
  <div class="card">
    <p><a href="pengajuan_cuti.php" class="btn-secondary">+ Ajukan Cuti Baru</a>
       <a href="daftar_cuti.php" class="btn-secondary">Lihat Riwayat Cuti</a>
       <a href="approve_cuti.php" class="btn-secondary">Approval Cuti<?= $pendingCount > 0 ? " ($pendingCount)" : '' ?></a></p>
  </div>
<?php else: ?>
  <div class="card">
    <p>Akun Anda belum terhubung ke data pegawai. Hubungi administrator.</p>
  </div>
<?php endif; ?>
<?php layout_footer(); ?>

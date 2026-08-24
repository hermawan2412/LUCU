<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('User');

$pegawai = cuti_get_pegawai_by_nip($db, $_SESSION['nip']);
$error = flash_get('error');
$pendingCount = $pegawai ? cuti_pending_count_for_approver($db, $pegawai['nip']) : 0;

$bulanParam = $_GET['bulan'] ?? date('Y-m');
$bulanDate = DateTime::createFromFormat('Y-m-d', $bulanParam . '-01') ?: new DateTime('first day of this month');
$year = (int) $bulanDate->format('Y');
$month = (int) $bulanDate->format('n');
libur_pastikan_tersinkron($db, $year);
$grid = kalender_bulan_grid($year, $month);
$cutiBulan = $pegawai ? kalender_cuti_bulan($db, $year, $month, (int) $pegawai['id_pegawai']) : [];
$liburBulan = libur_bulan($db, $year, $month);
$todayStr = date('Y-m-d');
$bulanPrev = (clone $bulanDate)->modify('-1 month')->format('Y-m');
$bulanNext = (clone $bulanDate)->modify('+1 month')->format('Y-m');

layout_header('Dashboard', 'dashboard');
?>
<h1>Dashboard Pegawai</h1>
<p class="lead">Selamat datang, <?= e($_SESSION['username']) ?>.</p>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($pegawai): ?>
  <?php
    $kuotaStatus = kalender_kuota_status((int) $pegawai['hak_cuti_tahunan']);
    $kuotaTone = match ($kuotaStatus) { 'kritis' => 'tone-red', 'rendah' => 'tone-amber', default => 'tone-green' };
  ?>
  <div class="stat-row">
    <div class="stat-tile <?= $kuotaTone ?>">
      <div class="num"><?= (int) $pegawai['hak_cuti_tahunan'] ?></div>
      <div class="label">Sisa Cuti Tahunan &middot; <span class="badge <?= kalender_kuota_badge_class($kuotaStatus) ?>"><?= kalender_kuota_label($kuotaStatus) ?></span></div>
    </div>
    <div class="stat-tile tone-purple">
      <div class="num" style="font-size:1rem"><?= e($pegawai['nama_jabatan']) ?></div>
      <div class="label">Jabatan</div>
    </div>
    <div class="stat-tile tone-teal">
      <div class="num" style="font-size:1rem"><?= e(cuti_masa_kerja($pegawai['tmt_pegawai'])) ?></div>
      <div class="label">Masa Kerja</div>
    </div>
    <div class="stat-tile <?= $pendingCount > 0 ? 'tone-blue' : '' ?>">
      <div class="num"><?= $pendingCount ?></div>
      <div class="label">Menunggu Approval Anda</div>
    </div>
  </div>

  <div class="card">
    <div class="calendar-nav">
      <h2>Kalender Cuti Saya &middot; <?= e(kalender_nama_bulan($month)) ?> <?= $year ?></h2>
      <div class="calendar-nav-links">
        <a href="?bulan=<?= e($bulanPrev) ?>" class="btn-secondary">&larr;</a>
        <a href="?bulan=<?= date('Y-m') ?>" class="btn-secondary">Hari ini</a>
        <a href="?bulan=<?= e($bulanNext) ?>" class="btn-secondary">&rarr;</a>
      </div>
    </div>
    <div class="calendar-grid">
      <?php foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $wd): ?>
        <div class="calendar-weekday"><?= $wd ?></div>
      <?php endforeach; ?>

      <?php foreach ($grid as $week): foreach ($week as $tgl): ?>
        <?php if ($tgl === null): ?>
          <div class="calendar-cell empty"></div>
        <?php else: ?>
          <?php $cuti = $cutiBulan[$tgl][0] ?? null; $libur = $liburBulan[$tgl] ?? null; ?>
          <div class="calendar-cell<?= $tgl === $todayStr ? ' today' : '' ?><?= $cuti ? ' on-leave' : '' ?><?= $libur ? ' is-holiday' : '' ?>">
            <div class="calendar-day-num"><?= (int) substr($tgl, 8, 2) ?></div>
            <?php if ($libur): ?>
              <div class="calendar-holiday" title="<?= e($libur) ?>"><?= e($libur) ?></div>
            <?php endif; ?>
            <?php if ($cuti): ?>
              <div class="calendar-on-leave-mark"><?= e($cuti['jenis']) ?></div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endforeach; endforeach; ?>
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

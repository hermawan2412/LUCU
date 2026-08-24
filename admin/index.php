<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('Admin');

$totalPegawai = db_one($db, "SELECT COUNT(*) AS n FROM pegawai")['n'];
$totalJabatan = db_one($db, "SELECT COUNT(*) AS n FROM jabatan")['n'];
$totalGolongan = db_one($db, "SELECT COUNT(*) AS n FROM golongan")['n'];
$pengajuanAktif = db_one($db, "SELECT COUNT(*) AS n FROM cuti_pegawai WHERE status_cuti = 'Diajukan'")['n'];
$kgbList = kgb_daftar_terbaru_per_pegawai($db);
$kgbOverdue = count(array_filter($kgbList, fn($r) => kgb_status($r['kgb_datang']) === 'overdue'));
$knpList = knp_daftar_terbaru_per_pegawai($db);
$knpOverdue = count(array_filter($knpList, fn($r) => knp_status($r['knp_datang']) === 'overdue'));

// Kalender tim - bulan berjalan, bisa geser lewat ?bulan=YYYY-MM
$bulanParam = $_GET['bulan'] ?? date('Y-m');
$bulanDate = DateTime::createFromFormat('Y-m-d', $bulanParam . '-01') ?: new DateTime('first day of this month');
$year = (int) $bulanDate->format('Y');
$month = (int) $bulanDate->format('n');

$grid = kalender_bulan_grid($year, $month);
$cutiBulan = kalender_cuti_bulan($db, $year, $month);
$todayStr = date('Y-m-d');

$bulanPrev = (clone $bulanDate)->modify('-1 month')->format('Y-m');
$bulanNext = (clone $bulanDate)->modify('+1 month')->format('Y-m');

$pegawaiKuota = db_all($db, "SELECT p.id_pegawai, p.nama_pegawai, j.nama_jabatan, p.hak_cuti_tahunan
    FROM pegawai p JOIN jabatan j ON j.id_jabatan = p.id_jabatan
    ORDER BY p.hak_cuti_tahunan ASC, p.nama_pegawai ASC");

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
  <div class="stat-tile"><div class="num" style="color:<?= $knpOverdue > 0 ? 'var(--danger)' : 'inherit' ?>"><?= $knpOverdue ?></div><div class="label">KNP Jatuh Tempo</div></div>
</div>

<div class="card">
  <div class="calendar-nav">
    <h2>Kalender Cuti Tim &middot; <?= e(kalender_nama_bulan($month)) ?> <?= $year ?></h2>
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
        <?php $orang = $cutiBulan[$tgl] ?? []; ?>
        <div class="calendar-cell<?= $tgl === $todayStr ? ' today' : '' ?><?= !empty($orang) ? ' has-leave' : '' ?>">
          <div class="calendar-day-num"><?= (int) substr($tgl, 8, 2) ?></div>
          <?php foreach (array_slice($orang, 0, 2) as $o): ?>
            <div class="calendar-chip" title="<?= e($o['nama'] . ' - ' . $o['jenis']) ?>"><?= e($o['nama']) ?></div>
          <?php endforeach; ?>
          <?php if (count($orang) > 2): ?>
            <div class="calendar-more">+<?= count($orang) - 2 ?> lainnya</div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endforeach; endforeach; ?>
  </div>
</div>

<div class="card">
  <h2 style="margin:0 0 4px;">Sisa Jatah Cuti Tahunan</h2>
  <p class="lead" style="margin-bottom:16px;">Diurutkan yang paling sedikit dulu.</p>
  <div class="quota-list">
    <?php foreach ($pegawaiKuota as $p): ?>
      <?php $status = kalender_kuota_status((int) $p['hak_cuti_tahunan']); ?>
      <div class="quota-row">
        <div>
          <div class="nama"><?= e($p['nama_pegawai']) ?></div>
          <div class="jabatan"><?= e($p['nama_jabatan']) ?></div>
        </div>
        <div class="sisa">
          <?= (int) $p['hak_cuti_tahunan'] ?> hari
          <span class="badge <?= kalender_kuota_badge_class($status) ?>"><?= kalender_kuota_label($status) ?></span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="card">
  <p><a href="data_pegawai.php" class="btn-secondary">Kelola Data Pegawai</a>
     <a href="data_jabatan.php" class="btn-secondary">Kelola Data Jabatan</a>
     <a href="data_golongan.php" class="btn-secondary">Kelola Data Golongan</a>
     <a href="data_kgb.php" class="btn-secondary">Kelola KGB<?= $kgbOverdue > 0 ? " ($kgbOverdue)" : '' ?></a>
     <a href="data_knp.php" class="btn-secondary">Kelola KNP<?= $knpOverdue > 0 ? " ($knpOverdue)" : '' ?></a>
     <a href="export_cuti.php" class="btn-secondary">Export Data Cuti (CSV)</a></p>
</div>
<?php layout_footer(); ?>

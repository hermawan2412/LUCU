<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('Admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'sync_libur') {
    csrf_verify();
    $tahunSync = (int) ($_POST['tahun'] ?? date('Y'));
    $berhasil = libur_sinkron_tahun($db, $tahunSync);
    flash_set($berhasil ? 'success' : 'error', $berhasil
        ? "Data hari libur $tahunSync berhasil disinkron."
        : "Gagal menyinkron hari libur $tahunSync - API gak kejangkau, coba lagi nanti.");
    redirect('index.php' . (isset($_GET['bulan']) ? '?bulan=' . urlencode($_GET['bulan']) : ''));
}

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

libur_pastikan_tersinkron($db, $year);
$grid = kalender_bulan_grid($year, $month);
$cutiBulan = kalender_cuti_bulan($db, $year, $month);
$liburBulan = libur_bulan($db, $year, $month);
$todayStr = date('Y-m-d');

$bulanPrev = (clone $bulanDate)->modify('-1 month')->format('Y-m');
$bulanNext = (clone $bulanDate)->modify('+1 month')->format('Y-m');

// Diambil full row (bukan cuma hak_cuti_tahunan) krn cuti_tahunan_kuota_tersedia()
// butuh jenis_asn/tmt/N-1/N-2 buat ngitung akumulasi (SE 13/2019 / SK 212/2024).
// Rollover per-baris (bukan cron) - jumlah pegawai kecil (1 pengadilan), aman.
$pegawaiKuota = db_all($db, "SELECT p.*, j.nama_jabatan
    FROM pegawai p JOIN jabatan j ON j.id_jabatan = p.id_jabatan
    ORDER BY p.nama_pegawai ASC");
foreach ($pegawaiKuota as &$pk) {
    $pk = cuti_tahunan_rollover_jika_perlu($db, $pk);
    $pk['kuota_tersedia'] = cuti_tahunan_kuota_tersedia($pk);
}
unset($pk);
usort($pegawaiKuota, fn ($a, $b) => $a['kuota_tersedia'] <=> $b['kuota_tersedia']);

$flashSuccess = flash_get('success');
$flashError = flash_get('error');

layout_header('Dashboard Admin', 'dashboard', 'admin');
?>
<h1>Dashboard Admin</h1>
<p class="lead">Selamat datang, <?= e($_SESSION['username']) ?>.</p>

<?php if ($flashSuccess): ?><div class="alert alert-success"><?= e($flashSuccess) ?></div><?php endif; ?>
<?php if ($flashError): ?><div class="alert alert-danger"><?= e($flashError) ?></div><?php endif; ?>

<div class="stat-row">
  <a href="data_pegawai.php" class="stat-tile tone-blue"><div class="num"><?= (int) $totalPegawai ?></div><div class="label">Total Pegawai</div></a>
  <a href="data_jabatan.php" class="stat-tile tone-purple"><div class="num"><?= (int) $totalJabatan ?></div><div class="label">Jabatan</div></a>
  <a href="data_golongan.php" class="stat-tile tone-teal"><div class="num"><?= (int) $totalGolongan ?></div><div class="label">Golongan</div></a>
  <a href="data_cuti.php?status=Diajukan" class="stat-tile tone-amber"><div class="num"><?= (int) $pengajuanAktif ?></div><div class="label">Cuti Sedang Diajukan</div></a>
  <a href="data_kgb.php" class="stat-tile <?= $kgbOverdue > 0 ? 'tone-red' : 'tone-green' ?>"><div class="num"><?= $kgbOverdue ?></div><div class="label">KGB Jatuh Tempo</div></a>
  <a href="data_knp.php" class="stat-tile <?= $knpOverdue > 0 ? 'tone-red' : 'tone-green' ?>"><div class="num"><?= $knpOverdue ?></div><div class="label">KNP Jatuh Tempo</div></a>
</div>

<div class="card">
  <div class="calendar-nav">
    <h2>Kalender Cuti Tim &middot; <?= e(kalender_nama_bulan($month)) ?> <?= $year ?></h2>
    <div class="calendar-nav-links">
      <a href="?bulan=<?= e($bulanPrev) ?>" class="btn-secondary">&larr;</a>
      <a href="?bulan=<?= date('Y-m') ?>" class="btn-secondary">Hari ini</a>
      <a href="?bulan=<?= e($bulanNext) ?>" class="btn-secondary">&rarr;</a>
      <form method="POST" style="display:inline;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="sync_libur">
        <input type="hidden" name="tahun" value="<?= $year ?>">
        <button type="submit" class="btn-secondary" title="Sinkron ulang data hari libur nasional tahun <?= $year ?>">&#8635; Libur</button>
      </form>
    </div>
  </div>
  <p class="hint" style="margin-bottom:14px;">&#128308; = hari libur nasional/cuti bersama (sinkron otomatis dari kalender publik Google - nyakup libur keagamaan spt Idul Fitri, Idul Adha, Maulid Nabi)</p>
  <div class="calendar-grid">
    <?php foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $wd): ?>
      <div class="calendar-weekday"><?= $wd ?></div>
    <?php endforeach; ?>

    <?php foreach ($grid as $week): foreach ($week as $tgl): ?>
      <?php if ($tgl === null): ?>
        <div class="calendar-cell empty"></div>
      <?php else: ?>
        <?php $orang = $cutiBulan[$tgl] ?? []; $libur = $liburBulan[$tgl] ?? null; ?>
        <div class="calendar-cell<?= $tgl === $todayStr ? ' today' : '' ?><?= !empty($orang) ? ' has-leave' : '' ?><?= $libur ? ' is-holiday' : '' ?>">
          <div class="calendar-day-num"><?= (int) substr($tgl, 8, 2) ?></div>
          <?php if ($libur): ?>
            <div class="calendar-holiday" title="<?= e($libur) ?>"><?= e($libur) ?></div>
          <?php endif; ?>
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
      <?php $status = kalender_kuota_status($p['kuota_tersedia']); ?>
      <div class="quota-row">
        <div>
          <div class="nama"><?= e($p['nama_pegawai']) ?></div>
          <div class="jabatan"><?= e($p['nama_jabatan']) ?></div>
        </div>
        <div class="sisa">
          <?= $p['kuota_tersedia'] ?> hari
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

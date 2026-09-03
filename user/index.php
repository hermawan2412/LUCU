<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('User');

$pegawai = cuti_get_pegawai_by_nip($db, $_SESSION['nip']);
if ($pegawai) {
    $pegawai = cuti_tahunan_rollover_jika_perlu($db, $pegawai);
    $pegawai = cuti_sakit_reset_jika_perlu($db, $pegawai);
}
$error = flash_get('error');
$pendingCount = $pegawai ? cuti_pending_count_for_approver($db, $pegawai['nip']) : 0;
$riwayatTerbaru = $pegawai
    ? db_all($db, "SELECT * FROM cuti_pegawai WHERE id_pegawai = ? ORDER BY id_cutipegawai DESC LIMIT 5", [$pegawai['id_pegawai']])
    : [];
$rekapTahunIni = $pegawai
    ? cuti_rekap_tahun($db, (int) $pegawai['id_pegawai'], (int) date('Y'))
    : [];

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
    $kuotaTahunan = cuti_tahunan_kuota_tersedia($pegawai);
    $adaAkumulasi = (int) $pegawai['cuti_tahunan_n1'] > 0 || (int) $pegawai['cuti_tahunan_n2'] > 0;
    $kuotaStatus = kalender_kuota_status($kuotaTahunan);
    $kuotaTone = match ($kuotaStatus) { 'kritis' => 'tone-red', 'rendah' => 'tone-amber', default => 'tone-green' };
  ?>
  <div class="stat-row">
    <a href="daftar_cuti.php" class="stat-tile <?= $kuotaTone ?>">
      <div class="num"><?= $kuotaTahunan ?></div>
      <div class="label">Sisa Cuti Tahunan<?= $adaAkumulasi ? ' (+akumulasi)' : '' ?> &middot; <span class="badge <?= kalender_kuota_badge_class($kuotaStatus) ?>"><?= kalender_kuota_label($kuotaStatus) ?></span></div>
    </a>
    <div class="stat-tile tone-purple">
      <div class="num" style="font-size:1rem"><?= e($pegawai['nama_jabatan']) ?></div>
      <div class="label">Jabatan</div>
    </div>
    <div class="stat-tile tone-teal">
      <div class="num" style="font-size:1rem"><?= e(cuti_masa_kerja($pegawai['tmt_pegawai'])) ?></div>
      <div class="label">Masa Kerja</div>
    </div>
    <a href="approve_cuti.php" class="stat-tile <?= $pendingCount > 0 ? 'tone-blue' : '' ?>">
      <div class="num"><?= $pendingCount ?></div>
      <div class="label">Menunggu Approval Anda</div>
    </a>
  </div>

  <div class="card">
    <h2 style="margin:0 0 12px;">Rekap Cuti Saya</h2>

    <?php if ($adaAkumulasi): ?>
      <p style="margin:0 0 10px;">
        <strong>Cuti Tahunan:</strong>
        tahun ini <?= (int) $pegawai['hak_cuti_tahunan'] ?> hari
        <?php if ((int) $pegawai['cuti_tahunan_n1'] > 0): ?> + tahun lalu <?= (int) $pegawai['cuti_tahunan_n1'] ?> hari<?php endif; ?>
        <?php if ((int) $pegawai['cuti_tahunan_n2'] > 0): ?> + 2 tahun lalu <?= (int) $pegawai['cuti_tahunan_n2'] ?> hari<?php endif; ?>
        = <strong><?= $kuotaTahunan ?> hari</strong> bisa dipakai
        (akumulasi sesuai SE Sekma 13/2019 / SK Sekma 212/2024).
      </p>
    <?php endif; ?>

    <div class="stat-row" style="margin-bottom:14px;">
      <a href="daftar_cuti.php?jenis=Cuti+Sakit" class="stat-tile">
        <div class="num"><?= (int) $pegawai['hak_cuti_sakit'] ?></div>
        <div class="label">Hak Cuti Sakit (hari)</div>
      </a>
      <a href="daftar_cuti.php?jenis=Cuti+Karena+Alasan+Penting" class="stat-tile">
        <div class="num"><?= (int) $pegawai['hak_cuti_penting'] ?></div>
        <div class="label">Hak Cuti Alasan Penting (hari)</div>
      </a>
    </div>

    <h3 style="font-size:0.95rem;margin:0 0 8px;">Cuti Terpakai Tahun <?= date('Y') ?></h3>
    <?php if (empty($rekapTahunIni)): ?>
      <div class="empty-state">Belum ada cuti disetujui tahun ini.</div>
    <?php else: ?>
      <div class="table-scroll">
        <table class="data-table">
          <thead>
            <tr><th>Jenis Cuti</th><th>Jumlah Pengajuan</th><th>Total</th></tr>
          </thead>
          <tbody>
            <?php foreach ($rekapTahunIni as $r): ?>
              <tr>
                <td><?= e($r['jenis_cuti']) ?></td>
                <td><a href="daftar_cuti.php?jenis=<?= urlencode($r['jenis_cuti']) ?>"><?= (int) $r['jumlah_pengajuan'] ?></a></td>
                <td><?= (int) $r['total_lama'] ?> <?= e($r['ket_lama_cuti']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
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
          <div class="calendar-cell<?= $tgl === $todayStr ? ' today' : '' ?><?= $cuti ? ' on-leave' : '' ?><?= $libur ? ' is-holiday' : (kalender_is_weekend($tgl) ? ' is-weekend' : '') ?>">
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
    <div class="calendar-nav">
      <h2>Riwayat Cuti Terbaru</h2>
      <a href="daftar_cuti.php" class="btn-secondary">Lihat Semua</a>
    </div>
    <?php if (empty($riwayatTerbaru)): ?>
      <div class="empty-state">Belum ada pengajuan cuti. <a href="pengajuan_cuti.php">Ajukan sekarang</a>.</div>
    <?php else: ?>
      <div class="table-scroll">
        <table class="data-table">
          <thead>
            <tr>
              <th>Jenis</th>
              <th>Tanggal</th>
              <th>Diajukan</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($riwayatTerbaru as $row): ?>
              <tr>
                <td><?= e($row['jenis_cuti']) ?></td>
                <td><?= e($row['dari_tanggal']) ?> &ndash; <?= e($row['sampai_dengan']) ?></td>
                <td><?= e($row['tgl_pengajuan']) ?></td>
                <td><span class="badge <?= cuti_status_badge_class($row['status_cuti']) ?>"><?= e($row['status_cuti']) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
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

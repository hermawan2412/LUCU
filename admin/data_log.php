<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('Admin');

$aksiFilter = $_GET['aksi'] ?? '';
$cari = trim($_GET['cari'] ?? '');
$halaman = max(1, (int) ($_GET['halaman'] ?? 1));
$perHalaman = 50;
$offset = ($halaman - 1) * $perHalaman;

$total = log_aktivitas_count($db, $aksiFilter, $cari);
$totalHalaman = max(1, (int) ceil($total / $perHalaman));
$halaman = min($halaman, $totalHalaman);
$offset = ($halaman - 1) * $perHalaman;

$list = log_aktivitas_daftar($db, $aksiFilter, $cari, $perHalaman, $offset);
$daftarAksi = log_aktivitas_daftar_kode($db);

$qs = fn (array $override) => http_build_query(array_merge(['aksi' => $aksiFilter, 'cari' => $cari, 'halaman' => $halaman], $override));

layout_header('Log Aktivitas', 'log', 'admin');
?>
<h1>Log Aktivitas</h1>
<p class="lead">Riwayat login, perubahan data, dan approval cuti - <?= (int) $total ?> catatan. <a href="log_error.php" class="btn-secondary" style="padding:4px 14px;font-size:0.78rem;">Log Error Sistem</a></p>

<div class="card">
  <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
    <div class="field" style="min-width:200px;">
      <label for="aksi">Jenis Aksi</label>
      <select id="aksi" name="aksi" onchange="this.form.submit()">
        <option value="">-- Semua --</option>
        <?php foreach ($daftarAksi as $a): ?>
          <option value="<?= e($a) ?>" <?= $aksiFilter === $a ? 'selected' : '' ?>><?= e($a) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field" style="min-width:220px;">
      <label for="cari">Cari (username/keterangan)</label>
      <input id="cari" name="cari" type="text" value="<?= e($cari) ?>">
    </div>
    <button type="submit" class="btn-secondary" style="width:auto;padding:9px 18px;">Filter</button>
    <?php if ($aksiFilter !== '' || $cari !== ''): ?><a href="data_log.php" class="btn-secondary">Reset</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <div style="overflow-x:auto;">
    <table class="data-table">
      <thead><tr><th>Waktu</th><th>Username</th><th>Aksi</th><th>Keterangan</th><th>IP</th></tr></thead>
      <tbody>
        <?php foreach ($list as $row): ?>
          <tr>
            <td style="white-space:nowrap;"><?= e($row['dibuat_pada']) ?></td>
            <td><?= e($row['username']) ?></td>
            <td><span class="badge badge-neutral"><?= e($row['aksi']) ?></span></td>
            <td><?= e($row['keterangan']) ?: '-' ?></td>
            <td><?= e($row['ip_address']) ?: '-' ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($list)): ?>
          <tr><td colspan="5" class="empty-state">Belum ada catatan.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($totalHalaman > 1): ?>
    <div style="display:flex; gap:8px; justify-content:center; margin-top:16px;">
      <?php if ($halaman > 1): ?><a href="?<?= $qs(['halaman' => $halaman - 1]) ?>" class="btn-secondary">&larr; Sebelumnya</a><?php endif; ?>
      <span class="hint" style="align-self:center;">Halaman <?= $halaman ?> dari <?= $totalHalaman ?></span>
      <?php if ($halaman < $totalHalaman): ?><a href="?<?= $qs(['halaman' => $halaman + 1]) ?>" class="btn-secondary">Selanjutnya &rarr;</a><?php endif; ?>
    </div>
  <?php endif; ?>
</div>
<?php layout_footer(); ?>

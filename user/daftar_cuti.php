<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('User');

$pegawai = cuti_get_pegawai_by_nip($db, $_SESSION['nip']);
if ($pegawai === null) {
    flash_set('error', 'Akun Anda belum terhubung ke data pegawai. Hubungi admin.');
    redirect('index.php');
}

$riwayat = db_all($db, "SELECT * FROM cuti_pegawai WHERE id_pegawai = ? ORDER BY id_cutipegawai DESC", [$pegawai['id_pegawai']]);
$success = flash_get('success');

layout_header('Riwayat Cuti', 'riwayat');
?>
<h1>Riwayat Cuti</h1>
<p class="lead">Daftar pengajuan cuti Anda beserta status persetujuannya.</p>

<?php if ($success): ?>
  <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<div class="card">
  <?php if (empty($riwayat)): ?>
    <div class="empty-state">Belum ada pengajuan cuti. <a href="pengajuan_cuti.php">Ajukan sekarang</a>.</div>
  <?php else: ?>
    <div style="overflow-x:auto;">
      <table class="data-table">
        <thead>
          <tr>
            <th>Jenis</th>
            <th>Tanggal</th>
            <th>Lama</th>
            <th>Diajukan</th>
            <th>Status</th>
            <th>Keterangan</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($riwayat as $row): ?>
            <tr>
              <td><?= e($row['jenis_cuti']) ?></td>
              <td><?= e($row['dari_tanggal']) ?> &ndash; <?= e($row['sampai_dengan']) ?></td>
              <td><?= e($row['lama_cuti']) ?> <?= e($row['ket_lama_cuti']) ?></td>
              <td><?= e($row['tgl_pengajuan']) ?></td>
              <td><span class="badge <?= cuti_status_badge_class($row['status_cuti']) ?>"><?= e($row['status_cuti']) ?></span></td>
              <td><?= e($row['ket_status_cuti']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php layout_footer(); ?>

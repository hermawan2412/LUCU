<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('User');

$pegawai = cuti_get_pegawai_by_nip($db, $_SESSION['nip']);
if ($pegawai === null) {
    flash_set('error', 'Akun Anda belum terhubung ke data pegawai. Hubungi admin.');
    redirect('index.php');
}

$jenisFilter = $_GET['jenis'] ?? '';
$sql = "SELECT * FROM cuti_pegawai WHERE id_pegawai = ?";
$params = [$pegawai['id_pegawai']];
if (in_array($jenisFilter, cuti_leave_types($pegawai['jenis_asn']), true)) {
    $sql .= " AND jenis_cuti = ?";
    $params[] = $jenisFilter;
}
$sql .= " ORDER BY id_cutipegawai DESC";
$riwayat = db_all($db, $sql, $params);
$success = flash_get('success');
$error = flash_get('error');

layout_header('Riwayat Cuti', 'riwayat');
?>
<h1>Riwayat Cuti</h1>
<p class="lead">
  Daftar pengajuan cuti Anda beserta status persetujuannya.
  <?php if ($jenisFilter !== ''): ?> Difilter: <strong><?= e($jenisFilter) ?></strong> &middot; <a href="daftar_cuti.php">tampilkan semua</a>.<?php endif; ?>
</p>

<?php if ($success): ?>
  <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-danger"><?= e($error) ?></div>
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
            <th></th>
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
              <td>
                <a href="cetak_cuti.php?id=<?= (int) $row['id_cutipegawai'] ?>" class="btn-secondary" style="padding:5px 12px;font-size:0.78rem;">.docx</a>
                <a href="cetak_cuti.php?id=<?= (int) $row['id_cutipegawai'] ?>&format=pdf" class="btn-secondary" style="padding:5px 12px;font-size:0.78rem;">.pdf</a>
                <?php if (!empty($row['berkas'])): ?>
                  <a href="<?= e(berkas_cuti_url($row['berkas'], '../')) ?>" target="_blank" class="btn-secondary" style="padding:5px 12px;font-size:0.78rem;">Surat Dokter</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php layout_footer(); ?>

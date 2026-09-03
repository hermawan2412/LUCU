<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('Admin');

// Read-only buat admin - aksi approve/reject tetap cuma lewat approve_cuti.php
// (oleh approver yg beneran di rute jabatan.id_atasan), halaman ini cuma buat
// liat semua pengajuan lintas pegawai (gak ada di user/daftar_cuti.php krn itu
// di-scope ke id_pegawai sendiri).
$statusFilter = $_GET['status'] ?? '';
$statusValid = ['Diajukan', 'Disetujui', 'Tidak Disetujui', 'Ditangguhkan'];

$sql = "SELECT c.*, p.nama_pegawai, p.nip FROM cuti_pegawai c JOIN pegawai p ON p.id_pegawai = c.id_pegawai";
$params = [];
if (in_array($statusFilter, $statusValid, true)) {
    $sql .= " WHERE c.status_cuti = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY c.id_cutipegawai DESC";
$list = db_all($db, $sql, $params);

$tabs = ['' => 'Semua', 'Diajukan' => 'Diajukan', 'Disetujui' => 'Disetujui', 'Tidak Disetujui' => 'Tidak Disetujui'];

layout_header('Data Cuti', 'cuti', 'admin');
?>
<h1>Data Cuti</h1>
<p class="lead">Semua pengajuan cuti pegawai. Approve/reject tetap dilakukan oleh atasan yang bersangkutan lewat alur approval masing-masing, halaman ini cuma buat pemantauan. <a href="export_cuti.php" class="btn-secondary" style="padding:4px 14px;font-size:0.78rem;">Export CSV</a></p>

<div class="card" style="margin-bottom:20px;">
  <div style="display:flex; gap:8px; flex-wrap:wrap;">
    <?php foreach ($tabs as $value => $label): ?>
      <a href="?<?= $value !== '' ? 'status=' . urlencode($value) : '' ?>"
         class="btn-<?= $statusFilter === $value ? 'primary' : 'secondary' ?>"
         style="padding:8px 16px; font-size:0.85rem;"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="card">
  <?php if (empty($list)): ?>
    <div class="empty-state">Gak ada pengajuan cuti<?= $statusFilter !== '' ? ' dengan status "' . e($statusFilter) . '"' : '' ?>.</div>
  <?php else: ?>
    <div style="overflow-x:auto;">
      <table class="data-table">
        <thead>
          <tr>
            <th>Pegawai</th>
            <th>Jenis</th>
            <th>Tanggal</th>
            <th>Lama</th>
            <th>Diajukan</th>
            <th>Status</th>
            <th>Keterangan</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($list as $row): ?>
            <tr>
              <td><?= e($row['nama_pegawai']) ?><br><span class="hint"><?= e($row['nip']) ?></span></td>
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

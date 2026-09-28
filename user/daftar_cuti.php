<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('User', 'Pengelola');

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
    <div class="table-scroll">
      <!-- Pola padat sama dengan admin/data_cuti.php (tabel.tabel-cuti): tanpa gulir samping, kartu di layar sempit. -->
      <table class="data-table tabel-cuti">
        <thead>
          <tr>
            <th>Cuti</th>
            <th>Status</th>
            <th>Dokumen</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($riwayat as $row): ?>
            <tr id="cuti-<?= (int) $row['id_cutipegawai'] ?>">
              <td data-label="Cuti">
                <strong><?= e($row['jenis_cuti']) ?></strong>
                <span class="sub"><?= e($row['dari_tanggal']) ?> &ndash; <?= e($row['sampai_dengan']) ?> &middot; <?= e($row['lama_cuti']) ?> <?= e($row['ket_lama_cuti']) ?></span>
                <span class="sub">Diajukan <?= e($row['tgl_pengajuan']) ?>, <?= date('H:i', strtotime($row['waktu_pengajuan'])) ?></span>
              </td>
              <td data-label="Status">
                <span class="badge <?= cuti_status_badge_class($row['status_cuti']) ?>"><?= e($row['status_cuti']) ?></span>
                <?php if ($row['ket_status_cuti'] !== '' && $row['ket_status_cuti'] !== null): ?><span class="sub"><?= e($row['ket_status_cuti']) ?></span><?php endif; ?>
              </td>
              <td data-label="Dokumen" class="tindakan">
                <?php if ($row['status_cuti'] === 'Disetujui' || !empty($row['berkas'])): ?>
                  <span class="unduh">
                    <?php if ($row['status_cuti'] === 'Disetujui'): ?>
                      <a href="cetak_cuti.php?id=<?= (int) $row['id_cutipegawai'] ?>" class="btn-secondary">.docx</a>
                      <a href="cetak_cuti.php?id=<?= (int) $row['id_cutipegawai'] ?>&format=pdf" class="btn-secondary">.pdf</a>
                    <?php endif; ?>
                    <?php if (!empty($row['berkas'])): ?>
                      <a href="<?= e(berkas_cuti_url($row['berkas'], '../')) ?>" target="_blank" class="btn-secondary">Surat Dokter</a>
                    <?php endif; ?>
                  </span>
                <?php endif; ?>
                <?php if ($row['status_cuti'] !== 'Disetujui'): ?><span class="sub">Formulir tersedia setelah Disetujui</span><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php layout_footer(); ?>

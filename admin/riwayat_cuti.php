<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('Admin', 'Pengelola');

// Riwayat pengambilan cuti 1 pegawai, semua tahun (dibuka dari Data Pegawai).
$idPegawai = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$pegawai = $idPegawai
    ? db_one($db, "SELECT p.id_pegawai, p.nama_pegawai, p.nip, p.jenis_asn, j.nama_jabatan
        FROM pegawai p JOIN jabatan j ON j.id_jabatan = p.id_jabatan WHERE p.id_pegawai = ?", [$idPegawai])
    : null;
if (!$pegawai) {
    http_response_code(404);
}
$riwayat = $pegawai ? cuti_riwayat_pengambilan($db, (int) $pegawai['id_pegawai']) : [];

layout_header('Riwayat Cuti', 'pegawai', 'admin');
?>
<?php if (!$pegawai): ?>
  <h1>Riwayat Cuti</h1>
  <div class="card">
    <div class="empty-state">Pegawai tidak ditemukan. <a href="data_pegawai.php">Kembali ke Data Pegawai</a>.</div>
  </div>
<?php else: ?>
  <h1><?= e($pegawai['nama_pegawai']) ?></h1>
  <p class="lead">
    NIP <?= e($pegawai['nip']) ?> · <?= e($pegawai['jenis_asn']) ?> · <?= e($pegawai['nama_jabatan']) ?>.
    Semua cuti yang disetujui, semua jenis dan tahun.
    <a href="data_pegawai.php" class="btn-secondary" style="padding:4px 14px;font-size:0.78rem;">Kembali ke Data Pegawai</a>
  </p>
  <div class="card">
    <?php if (empty($riwayat)): ?>
      <div class="empty-state">Pegawai ini belum punya cuti yang disetujui.</div>
    <?php else: ?>
      <?php cuti_riwayat_tabel($riwayat); ?>
    <?php endif; ?>
  </div>
<?php endif; ?>
<?php layout_footer(); ?>

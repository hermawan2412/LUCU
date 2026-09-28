<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('User', 'Pengelola');

$pegawai = cuti_get_pegawai_by_nip($db, $_SESSION['nip']);
if ($pegawai === null) {
    flash_set('error', 'Akun Anda belum terhubung ke data pegawai. Hubungi admin.');
    redirect('index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $row = cuti_get_by_id($db, $id);

    if ($row === null) {
        $errors[] = 'Pengajuan tidak ditemukan.';
    } elseif ($action === 'approve') {
        $ttdManual = isset($_POST['ttd_manual']);
        if (cuti_approve($db, $row, $pegawai['nip'], $ttdManual)) {
            flash_set('success', 'Pengajuan cuti disetujui.');
            redirect('approve_cuti.php');
        }
        $errors[] = 'Anda tidak berhak menyetujui pengajuan ini (mungkin sudah diproses orang lain).';
    } elseif ($action === 'reject') {
        $alasan = trim($_POST['alasan'] ?? '');
        if ($alasan === '') {
            $errors[] = 'Alasan penolakan wajib diisi.';
        } elseif (cuti_reject($db, $row, $pegawai['nip'], $alasan)) {
            flash_set('success', 'Pengajuan cuti ditolak.');
            redirect('approve_cuti.php');
        } else {
            $errors[] = 'Anda tidak berhak menolak pengajuan ini (mungkin sudah diproses orang lain).';
        }
    }
}

// mode form-tolak: ?tolak=<id>
$rejectId = isset($_GET['tolak']) ? (int) $_GET['tolak'] : null;

$pending = cuti_pending_for_approver($db, $pegawai['nip']);
$success = flash_get('success');

layout_header('Approval Cuti', 'approval');
?>
<h1>Approval Cuti</h1>
<p class="lead">Pengajuan cuti yang menunggu persetujuan Anda sebagai <?= e($pegawai['nama_jabatan']) ?>.</p>
<?php if (!empty($pegawai['tanda_tangan_path'])): ?>
  <p class="hint" style="margin-top:-12px;margin-bottom:16px;">Tanda tangan digital Anda otomatis kepakai di formulir cetak. Centang "Tunda TTD" kalau untuk pengajuan tertentu mau tanda tangan basah manual setelah dicetak.</p>
<?php endif; ?>

<?php if ($success): ?>
  <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card">
  <?php if (empty($pending)): ?>
    <div class="empty-state">Tidak ada pengajuan yang menunggu approval Anda.</div>
  <?php else: ?>
    <div class="table-scroll">
      <!-- Pola padat sama dengan admin/data_cuti.php (tabel.tabel-cuti): tanpa gulir samping, kartu di layar sempit. -->
      <table class="data-table tabel-cuti">
        <thead>
          <tr>
            <th>Pemohon</th>
            <th>Cuti</th>
            <th>Alasan</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pending as $row): ?>
            <tr id="cuti-<?= (int) $row['id_cutipegawai'] ?>">
              <td data-label="Pemohon"><strong><?= e($row['nama_pegawai']) ?></strong></td>
              <td data-label="Cuti">
                <strong><?= e($row['jenis_cuti']) ?></strong>
                <span class="sub"><?= e($row['dari_tanggal']) ?> &ndash; <?= e($row['sampai_dengan']) ?> &middot; <?= e($row['lama_cuti']) ?> <?= e($row['ket_lama_cuti']) ?></span>
              </td>
              <td data-label="Alasan">
                <?= e($row['alasan_cuti']) ?>
                <?php if (!empty($row['berkas'])): ?>
                  <a class="sub" href="<?= e(berkas_cuti_url($row['berkas'], '../')) ?>" target="_blank">Lihat Surat Dokter</a>
                <?php endif; ?>
              </td>
              <td data-label="Aksi" class="tindakan">
                <?php if ($rejectId === (int) $row['id_cutipegawai']): ?>
                  <form method="POST" class="form-ringkas tolak">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $row['id_cutipegawai'] ?>">
                    <input type="hidden" name="action" value="reject">
                    <input type="text" name="alasan" placeholder="Alasan penolakan" aria-label="Alasan penolakan" required autofocus>
                    <button type="submit" class="btn-secondary">Kirim</button>
                    <a href="approve_cuti.php" class="btn-secondary">Batal</a>
                  </form>
                <?php else: ?>
                  <form method="POST" class="aksi-setuju">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $row['id_cutipegawai'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <?php if (!empty($pegawai['tanda_tangan_path'])): ?>
                      <label class="sub"><input type="checkbox" name="ttd_manual" value="1"> Tunda TTD (cetak dulu)</label>
                    <?php endif; ?>
                    <span class="unduh">
                      <button type="submit" class="btn-secondary">Setujui</button>
                      <a href="?tolak=<?= (int) $row['id_cutipegawai'] ?>#cuti-<?= (int) $row['id_cutipegawai'] ?>" class="btn-secondary">Tolak</a>
                    </span>
                  </form>
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

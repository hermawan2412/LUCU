<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('User');

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
        if (cuti_approve($db, $row, $pegawai['nip'])) {
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
    <div style="overflow-x:auto;">
      <table class="data-table">
        <thead>
          <tr>
            <th>Pemohon</th>
            <th>Jenis</th>
            <th>Tanggal</th>
            <th>Lama</th>
            <th>Alasan</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pending as $row): ?>
            <tr>
              <td><?= e($row['nama_pegawai']) ?></td>
              <td><?= e($row['jenis_cuti']) ?></td>
              <td><?= e($row['dari_tanggal']) ?> &ndash; <?= e($row['sampai_dengan']) ?></td>
              <td><?= e($row['lama_cuti']) ?> <?= e($row['ket_lama_cuti']) ?></td>
              <td><?= e($row['alasan_cuti']) ?></td>
              <td>
                <?php if ($rejectId === (int) $row['id_cutipegawai']): ?>
                  <form method="POST" style="display:flex; gap:6px; align-items:center;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $row['id_cutipegawai'] ?>">
                    <input type="hidden" name="action" value="reject">
                    <input type="text" name="alasan" placeholder="Alasan penolakan" required style="padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:0.85rem;">
                    <button type="submit" class="btn-secondary" style="padding:6px 12px;">Kirim</button>
                    <a href="approve_cuti.php" class="btn-secondary" style="padding:6px 12px;">Batal</a>
                  </form>
                <?php else: ?>
                  <form method="POST" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $row['id_cutipegawai'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="btn-secondary" style="padding:6px 12px;">Setujui</button>
                  </form>
                  <a href="?tolak=<?= (int) $row['id_cutipegawai'] ?>" class="btn-secondary" style="padding:6px 12px;">Tolak</a>
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

<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('Admin');

// Approve/reject tetap cuma lewat approve_cuti.php (oleh approver yg
// beneran di rute jabatan.id_atasan) - satu-satunya aksi admin di sini
// adalah kasih nomor_surat buat pengajuan yang masih 'Menunggu Nomor
// Surat', yang baru MEMULAI approval (lihat cuti_mulai_approval_setelah_nomor()
// di includes/cuti.php) - bukan approve/reject beneran.
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'beri_nomor') {
    csrf_verify();
    $id = (int) ($_POST['id_cutipegawai'] ?? 0);
    $nomor = trim($_POST['nomor_surat'] ?? '');
    $parafNip = trim($_POST['paraf_nip'] ?? '') ?: null;
    $row = cuti_get_by_id($db, $id);

    if ($nomor === '') {
        $errors[] = 'Nomor surat wajib diisi.';
    } elseif ($row === null || $row['status_cuti'] !== 'Menunggu Nomor Surat') {
        $errors[] = 'Pengajuan ini gak lagi di status "Menunggu Nomor Surat" (mungkin udah diproses).';
    } else {
        try {
            db_query($db, "UPDATE cuti_pegawai SET nomor_surat = ?, paraf_nip = ? WHERE id_cutipegawai = ?", [$nomor, $parafNip, $id]);
            $row['nomor_surat'] = $nomor;
            $row['paraf_nip'] = $parafNip;
            cuti_mulai_approval_setelah_nomor($db, $row);
            log_aktivitas($db, 'beri_nomor_surat', "Nomor surat \"$nomor\" utk pengajuan #$id");
            flash_set('success', "Nomor surat \"$nomor\" disimpan, approval mulai jalan.");
            redirect('data_cuti.php');
        } catch (Throwable $e) {
            error_log('Gagal beri nomor surat: ' . $e->getMessage());
            $errors[] = 'Terjadi kesalahan sistem, coba lagi.';
        }
    }
}

$statusFilter = $_GET['status'] ?? '';
$statusValid = ['Menunggu Nomor Surat', 'Diajukan', 'Disetujui', 'Tidak Disetujui', 'Ditangguhkan'];

$sql = "SELECT c.*, p.nama_pegawai, p.nip FROM cuti_pegawai c JOIN pegawai p ON p.id_pegawai = c.id_pegawai";
$params = [];
if (in_array($statusFilter, $statusValid, true)) {
    $sql .= " WHERE c.status_cuti = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY c.id_cutipegawai DESC";
$list = db_all($db, $sql, $params);
$semuaPegawai = db_all($db, "SELECT id_pegawai, nama_pegawai, nip FROM pegawai ORDER BY nama_pegawai ASC");
$success = flash_get('success');

$tabs = ['' => 'Semua', 'Menunggu Nomor Surat' => 'Menunggu Nomor Surat', 'Diajukan' => 'Diajukan', 'Disetujui' => 'Disetujui', 'Tidak Disetujui' => 'Tidak Disetujui'];

layout_header('Data Cuti', 'cuti', 'admin');
?>
<h1>Data Cuti</h1>
<p class="lead">Semua pengajuan cuti pegawai. Approve/reject tetap dilakukan oleh atasan yang bersangkutan lewat alur approval masing-masing - admin cuma kasih nomor surat (yang baru memulai approval-nya) dan paraf petugas. <a href="export_cuti.php" class="btn-secondary" style="padding:4px 14px;font-size:0.78rem;">Export CSV</a></p>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

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
    <div class="table-scroll">
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
            <th>Nomor Surat</th>
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
              <td>
                <?= e($row['ket_status_cuti']) ?>
                <?php if (!empty($row['berkas'])): ?>
                  <br><a href="<?= e(berkas_cuti_url($row['berkas'], '../')) ?>" target="_blank" style="font-size:0.78rem;">Surat Dokter</a>
                <?php endif; ?>
              </td>
              <td style="min-width:220px;">
                <?php if ($row['status_cuti'] === 'Menunggu Nomor Surat'): ?>
                  <form method="POST" style="display:flex; flex-direction:column; gap:6px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="beri_nomor">
                    <input type="hidden" name="id_cutipegawai" value="<?= (int) $row['id_cutipegawai'] ?>">
                    <input type="text" name="nomor_surat" placeholder="Nomor surat" required style="padding:6px 8px;border:1px solid var(--border-strong);border-radius:8px;font-size:0.8rem;">
                    <select name="paraf_nip" style="padding:6px 8px;border:1px solid var(--border-strong);border-radius:8px;font-size:0.8rem;">
                      <option value="">-- Paraf petugas (opsional) --</option>
                      <?php foreach ($semuaPegawai as $p): ?>
                        <option value="<?= e($p['nip']) ?>"><?= e($p['nama_pegawai']) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-secondary" style="padding:6px 12px;">Simpan & Mulai Approval</button>
                  </form>
                <?php else: ?>
                  <?= $row['nomor_surat'] ? e($row['nomor_surat']) : '-' ?>
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

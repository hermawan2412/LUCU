<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('Admin', 'Pengelola');

// Approve/reject tetap cuma lewat approve_cuti.php (oleh approver yg
// beneran di rute jabatan.id_atasan) - satu-satunya aksi admin di sini
// adalah kasih nomor_surat buat pengajuan yang masih 'Menunggu Nomor
// Surat', yang baru MEMULAI approval (lihat cuti_mulai_approval_setelah_nomor()
// di includes/cuti.php) - bukan approve/reject beneran.
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'catatan_sakit') {
    csrf_verify();
    $id = (int) ($_POST['id_cutipegawai'] ?? 0);
    $catatan = trim($_POST['catatan_sakit'] ?? '');
    $row = cuti_get_by_id($db, $id);

    if ($row === null || $row['jenis_cuti'] !== 'Cuti Sakit') {
        $errors[] = 'Pengajuan tidak ditemukan atau bukan Cuti Sakit.';
    } else {
        db_query($db, "UPDATE cuti_pegawai SET catatan_sakit = ? WHERE id_cutipegawai = ?", [$catatan ?: null, $id]);
        log_aktivitas($db, 'catatan_sakit', "Catatan Sakit diisi utk pengajuan #$id");
        flash_set('success', 'Catatan Sakit disimpan.');
        redirect('data_cuti.php');
    }
}

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
<p class="lead">Semua pengajuan cuti pegawai. Approve/reject tetap dilakukan oleh atasan yang bersangkutan lewat alur approval masing-masing - admin cuma kasih nomor surat (yang baru memulai approval-nya), paraf petugas, dan Catatan Sakit (kotak V.3 di dokumen cetak - riwayat cuti sakit yang diisi manual, bukan otomatis dari tanggal pengajuan). <a href="export_cuti.php" class="btn-secondary" style="padding:4px 14px;font-size:0.78rem;">Export CSV</a></p>

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
      <!-- 4 kolom padat (dulu 10 kolom + form ber-min-width, selalu lebih lebar dari layar). Di layar sempit tiap baris jadi kartu. -->
      <table class="data-table tabel-cuti">
        <thead>
          <tr>
            <th>Pegawai</th>
            <th>Cuti</th>
            <th>Status</th>
            <th>Tindakan</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($list as $row): ?>
            <tr id="cuti-<?= (int) $row['id_cutipegawai'] ?>">
              <td data-label="Pegawai">
                <strong><?= e($row['nama_pegawai']) ?></strong>
                <span class="sub"><?= e($row['nip']) ?></span>
                <span class="sub">Diajukan <?= e($row['tgl_pengajuan']) ?>, <?= date('H:i', strtotime($row['waktu_pengajuan'])) ?></span>
              </td>
              <td data-label="Cuti">
                <strong><?= e($row['jenis_cuti']) ?></strong>
                <span class="sub"><?= e($row['dari_tanggal']) ?> &ndash; <?= e($row['sampai_dengan']) ?> &middot; <?= e($row['lama_cuti']) ?> <?= e($row['ket_lama_cuti']) ?></span>
                <?php if (!empty($row['berkas'])): ?>
                  <a class="sub" href="<?= e(berkas_cuti_url($row['berkas'], '../')) ?>" target="_blank">Surat Dokter</a>
                <?php endif; ?>
              </td>
              <td data-label="Status">
                <span class="badge <?= cuti_status_badge_class($row['status_cuti']) ?>"><?= e($row['status_cuti']) ?></span>
                <?php if ($row['ket_status_cuti'] !== '' && $row['ket_status_cuti'] !== null): ?><span class="sub"><?= e($row['ket_status_cuti']) ?></span><?php endif; ?>
              </td>
              <td data-label="Tindakan" class="tindakan">
                <?php if ($row['status_cuti'] === 'Menunggu Nomor Surat'): ?>
                  <form method="POST" class="form-ringkas">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="beri_nomor">
                    <input type="hidden" name="id_cutipegawai" value="<?= (int) $row['id_cutipegawai'] ?>">
                    <input type="text" name="nomor_surat" placeholder="Nomor surat" aria-label="Nomor surat" required>
                    <select name="paraf_nip" aria-label="Paraf petugas">
                      <option value="">Paraf petugas (opsional)</option>
                      <?php foreach ($semuaPegawai as $p): ?>
                        <option value="<?= e($p['nip']) ?>"><?= e($p['nama_pegawai']) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-secondary">Simpan &amp; Mulai Approval</button>
                  </form>
                <?php elseif ($row['nomor_surat']): ?>
                  <span class="sub">No. surat <strong><?= e($row['nomor_surat']) ?></strong></span>
                <?php endif; ?>
                <?php if ($row['jenis_cuti'] === 'Cuti Sakit'): ?>
                  <form method="POST" class="form-ringkas satu-baris">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="catatan_sakit">
                    <input type="hidden" name="id_cutipegawai" value="<?= (int) $row['id_cutipegawai'] ?>">
                    <input type="text" name="catatan_sakit" placeholder="Catatan Sakit (kotak V.3)" aria-label="Catatan Sakit, kotak V.3" value="<?= e($row['catatan_sakit'] ?? '') ?>">
                    <button type="submit" class="btn-secondary">Simpan</button>
                  </form>
                <?php endif; ?>
                <?php if ($row['status_cuti'] === 'Disetujui'): ?>
                  <span class="unduh">
                    <a href="../user/cetak_cuti.php?id=<?= (int) $row['id_cutipegawai'] ?>" class="btn-secondary">.docx</a>
                    <a href="../user/cetak_cuti.php?id=<?= (int) $row['id_cutipegawai'] ?>&format=pdf" class="btn-secondary">.pdf</a>
                  </span>
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

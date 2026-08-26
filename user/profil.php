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
    $action = $_POST['action'] ?? '';

    if ($action === 'upload_ttd') {
        $hasil = upload_tanda_tangan($_FILES['tanda_tangan'] ?? [], (int) $pegawai['id_pegawai']);
        if (!$hasil['ok']) {
            $errors[] = $hasil['error'];
        } else {
            tanda_tangan_hapus($pegawai['tanda_tangan_path']); // hapus file lama kalau ekstensinya beda
            db_query($db, "UPDATE pegawai SET tanda_tangan_path = ? WHERE id_pegawai = ?", [$hasil['filename'], $pegawai['id_pegawai']]);
            flash_set('success', 'Tanda tangan berhasil disimpan.');
            redirect('profil.php');
        }
    } elseif ($action === 'hapus_ttd') {
        tanda_tangan_hapus($pegawai['tanda_tangan_path']);
        db_query($db, "UPDATE pegawai SET tanda_tangan_path = NULL WHERE id_pegawai = ?", [$pegawai['id_pegawai']]);
        flash_set('success', 'Tanda tangan dihapus.');
        redirect('profil.php');
    }
}

$success = flash_get('success');

layout_header('Profil Saya', 'profil');
?>
<h1>Profil Saya</h1>
<p class="lead">Data diri &amp; tanda tangan yang dipakai di formulir cetak cuti.</p>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<div class="card">
  <h2 style="font-size:1.1rem;margin:0 0 12px;">Data Diri</h2>
  <p><strong><?= e($pegawai['nama_pegawai']) ?></strong><br>
  NIP <?= e($pegawai['nip']) ?><br>
  <?= e($pegawai['nama_jabatan']) ?></p>
  <p class="hint">Data diri diisi/diubah oleh admin lewat Data Pegawai - kalau ada yang salah, hubungi admin.</p>
</div>

<div class="card">
  <h2 style="font-size:1.1rem;margin:0 0 12px;">Tanda Tangan</h2>
  <p class="lead">Dipakai otomatis di formulir cetak cuti (bagian tanda tangan pemohon, dan atasan/pejabat kalau Anda jadi approver).</p>

  <?php if ($pegawai['tanda_tangan_path']): ?>
    <div style="border:1px solid var(--border,#e5e5e5);border-radius:8px;padding:16px;margin-bottom:16px;display:inline-block;">
      <img src="<?= e(tanda_tangan_url($pegawai['tanda_tangan_path'], '../')) ?>" alt="Tanda tangan" style="max-width:240px;max-height:120px;display:block;">
    </div>
    <form method="POST" style="display:inline-block;margin-bottom:16px;margin-left:12px;vertical-align:top;" onsubmit="return confirm('Hapus tanda tangan tersimpan?');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="hapus_ttd">
      <button type="submit" class="btn-secondary">Hapus</button>
    </form>
  <?php else: ?>
    <div class="empty-state">Belum ada tanda tangan tersimpan.</div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="upload_ttd">
    <div class="field">
      <label for="tanda_tangan"><?= $pegawai['tanda_tangan_path'] ? 'Ganti' : 'Upload' ?> Tanda Tangan</label>
      <input id="tanda_tangan" name="tanda_tangan" type="file" accept="image/png,image/jpeg" required>
      <p class="hint">PNG atau JPG, maksimal 1MB. Sebaiknya latar putih/transparan, background bersih.</p>
    </div>
    <button type="submit" class="btn-primary" style="width:auto;padding:10px 20px;">Simpan</button>
  </form>
</div>
<?php layout_footer(); ?>

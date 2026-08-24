<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('Admin');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $namaAplikasi = trim($_POST['nama_aplikasi'] ?? '');
    $namaLengkap = trim($_POST['nama_lengkap'] ?? '');
    $instansi = trim($_POST['instansi'] ?? '');

    if ($namaAplikasi === '') $errors[] = 'Nama aplikasi wajib diisi.';
    if ($namaLengkap === '') $errors[] = 'Nama lengkap aplikasi wajib diisi.';
    if ($instansi === '') $errors[] = 'Nama instansi wajib diisi.';

    if (empty($errors)) {
        db_query($db, "UPDATE pengaturan SET nama_aplikasi=?, nama_lengkap=?, instansi=? WHERE id_pengaturan = 1",
            [$namaAplikasi, $namaLengkap, $instansi]);
        flash_set('success', 'Pengaturan disimpan.');
        redirect('pengaturan.php');
    }
}

$p = db_one($db, "SELECT * FROM pengaturan WHERE id_pengaturan = 1") ?? [
    'nama_aplikasi' => APP_NAME, 'nama_lengkap' => APP_FULL_NAME, 'instansi' => APP_INSTANSI,
];
$success = flash_get('success');

layout_header('Pengaturan', '', 'admin');
?>
<h1>Pengaturan Aplikasi</h1>
<p class="lead">Nama, judul, dan identitas instansi yang tampil di seluruh halaman.</p>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<div class="card">
  <h2 style="margin:0 0 16px;">Identitas &amp; Judul</h2>
  <form method="POST">
    <?= csrf_field() ?>
    <div class="field">
      <label for="nama_aplikasi">Nama Aplikasi (singkat)</label>
      <input id="nama_aplikasi" name="nama_aplikasi" type="text" required maxlength="100" value="<?= e($p['nama_aplikasi']) ?>">
      <p class="hint">Muncul di brand mark topbar &amp; tab browser, mis. "LUCU".</p>
    </div>
    <div class="field">
      <label for="nama_lengkap">Nama Lengkap Aplikasi</label>
      <input id="nama_lengkap" name="nama_lengkap" type="text" required maxlength="150" value="<?= e($p['nama_lengkap']) ?>">
      <p class="hint">Muncul di halaman login, mis. "Aplikasi Untuk Cuti".</p>
    </div>
    <div class="field">
      <label for="instansi">Nama Instansi</label>
      <input id="instansi" name="instansi" type="text" required maxlength="150" value="<?= e($p['instansi']) ?>">
      <p class="hint">Muncul di badge login, footer, dan topbar, mis. "Pengadilan Agama Rantau".</p>
    </div>
    <button type="submit" class="btn-primary" style="width:auto;padding:12px 24px;">Simpan</button>
  </form>
</div>

<div class="card">
  <h2 style="margin:0 0 8px;">Logo</h2>
  <p class="lead" style="margin-bottom:0;">Upload logo instansi &mdash; menyusul. Sementara ini brand mark pakai ikon perisai bawaan.</p>
</div>
<?php layout_footer(); ?>

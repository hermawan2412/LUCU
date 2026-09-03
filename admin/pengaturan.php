<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('Admin');

$errors = [];
$waTestResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? 'identitas';

    if ($action === 'identitas') {
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
    } elseif ($action === 'logo') {
        $hasil = upload_logo($_FILES['logo'] ?? []);
        if (!$hasil['ok']) {
            $errors[] = $hasil['error'];
        } else {
            $lama = db_one($db, "SELECT logo_path FROM pengaturan WHERE id_pengaturan = 1")['logo_path'] ?? null;
            if ($lama !== null && $lama !== $hasil['filename']) {
                logo_hapus($lama); // beda ekstensi dari upload sebelumnya - bersihin filenya
            }
            db_query($db, "UPDATE pengaturan SET logo_path=? WHERE id_pengaturan = 1", [$hasil['filename']]);
            flash_set('success', 'Logo berhasil diganti.');
            redirect('pengaturan.php');
        }
    } elseif ($action === 'logo_hapus') {
        $lama = db_one($db, "SELECT logo_path FROM pengaturan WHERE id_pengaturan = 1")['logo_path'] ?? null;
        logo_hapus($lama);
        db_query($db, "UPDATE pengaturan SET logo_path=NULL WHERE id_pengaturan = 1");
        flash_set('success', 'Logo dihapus, kembali ke ikon bawaan.');
        redirect('pengaturan.php');
    } elseif ($action === 'whatsapp') {
        $waAktif = isset($_POST['wa_aktif']) ? 1 : 0;
        $waToken = trim($_POST['wa_fonnte_token'] ?? '');

        if ($waAktif && $waToken === '') {
            $errors[] = 'Token Fonnte wajib diisi kalau notifikasi WhatsApp diaktifkan.';
        }

        if (empty($errors)) {
            db_query($db, "UPDATE pengaturan SET wa_aktif=?, wa_fonnte_token=? WHERE id_pengaturan = 1", [$waAktif, $waToken]);
            flash_set('success', 'Pengaturan WhatsApp disimpan.');
            redirect('pengaturan.php');
        }
    } elseif ($action === 'wa_test') {
        $noTes = trim($_POST['wa_test_nomor'] ?? '');
        $tokenTes = trim($_POST['wa_fonnte_token'] ?? '');
        if ($noTes === '') {
            $errors[] = 'Nomor tujuan tes wajib diisi.';
        } elseif ($tokenTes === '') {
            $errors[] = 'Token Fonnte wajib diisi dulu buat tes kirim (belum tersimpan juga gak apa, langsung dipakai buat tes ini).';
        } else {
            // Pakai token dari FORM (belum tentu udah disimpan) - bukan yg
            // di DB, biar admin bisa tes sebelum "Simpan"/aktifin.
            $ok = wa_kirim_dengan_token($tokenTes, $noTes, 'Tes notifikasi WhatsApp dari ' . APP_NAME . '. Kalau pesan ini sampai, token sudah benar.');
            $waTestResult = $ok
                ? ['ok' => true, 'pesan' => 'Terkirim ke Fonnte. Cek WhatsApp nomor tujuan (bisa nunggu beberapa detik).']
                : ['ok' => false, 'pesan' => 'Gagal kirim - cek token, atau lihat error_log server buat detail dari Fonnte.'];
        }
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
    <input type="hidden" name="action" value="identitas">
    <div class="field">
      <label for="nama_aplikasi">Nama Aplikasi (singkat)</label>
      <input id="nama_aplikasi" name="nama_aplikasi" type="text" required maxlength="100" value="<?= e($p['nama_aplikasi']) ?>">
      <p class="hint">Muncul di brand mark topbar &amp; tab browser, mis. "RESTU".</p>
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
  <h2 style="margin:0 0 16px;">Logo</h2>
  <p class="lead">Tampil sebagai brand mark di topbar &amp; halaman login, gantiin ikon perisai bawaan.</p>

  <div style="display:flex; align-items:center; gap:16px; margin-bottom:16px;">
    <div style="width:56px; height:56px; display:flex; align-items:center; justify-content:center; border:1px solid var(--border,#e5e5e5); border-radius:8px;">
      <?= brand_mark_svg(40, '../') ?>
    </div>
    <span class="hint" style="margin:0;">
      <?= APP_LOGO_PATH ? 'Logo saat ini sudah diupload.' : 'Belum ada logo — masih pakai ikon bawaan.' ?>
    </span>
  </div>

  <form method="POST" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="logo">
    <div class="field">
      <label for="logo">Berkas Logo (PNG atau JPG, maks 2MB)</label>
      <input id="logo" name="logo" type="file" accept="image/png,image/jpeg" required>
      <p class="hint">Idealnya persegi (mis. 256&times;256px), latar transparan (PNG) supaya rapi di topbar gelap maupun terang.</p>
    </div>
    <button type="submit" class="btn-primary" style="width:auto;padding:12px 24px;">Unggah Logo</button>
  </form>

  <?php if (APP_LOGO_PATH): ?>
    <form method="POST" style="margin-top:12px;" onsubmit="return confirm('Hapus logo dan kembali ke ikon bawaan?');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="logo_hapus">
      <button type="submit" class="btn-secondary" style="width:auto;padding:10px 20px;">Hapus Logo</button>
    </form>
  <?php endif; ?>
</div>

<div class="card">
  <h2 style="margin:0 0 4px;">Notifikasi WhatsApp</h2>
  <p class="lead" style="margin-bottom:16px;">
    Lewat <a href="https://fonnte.com/" target="_blank" rel="noopener">Fonnte</a> (paket Free: 1.000 pesan/bulan, gratis).
    Setiap notifikasi in-app (pengajuan baru, disetujui, ditolak) otomatis ikut dikirim WA ke nomor HP pegawai
    (kolom "No. Telepon" di <a href="data_pegawai.php">Data Pegawai</a>) kalau diaktifkan di sini.
  </p>

  <?php if ($waTestResult): ?>
    <div class="alert <?= $waTestResult['ok'] ? 'alert-success' : 'alert-danger' ?>"><?= e($waTestResult['pesan']) ?></div>
  <?php endif; ?>

  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="whatsapp">
    <div class="field">
      <label>
        <input type="checkbox" name="wa_aktif" value="1" <?= (int) ($p['wa_aktif'] ?? 0) === 1 ? 'checked' : '' ?> style="width:auto;">
        Aktifkan notifikasi WhatsApp
      </label>
    </div>
    <div class="field">
      <label for="wa_fonnte_token">Token Fonnte (API key)</label>
      <input id="wa_fonnte_token" name="wa_fonnte_token" type="text" maxlength="100" value="<?= e($p['wa_fonnte_token'] ?? '') ?>" placeholder="Dapatkan dari dashboard Fonnte setelah scan QR device">
      <p class="hint">Daftar &amp; hubungkan nomor WhatsApp kantor di <a href="https://md.fonnte.com/" target="_blank" rel="noopener">dashboard Fonnte</a>, salin token-nya ke sini.</p>
    </div>
    <button type="submit" class="btn-primary" style="width:auto;padding:12px 24px;">Simpan</button>

    <div class="field" style="margin-top:20px;border-top:1px solid var(--border,#e5e5e5);padding-top:16px;">
      <label for="wa_test_nomor">Tes Kirim ke Nomor</label>
      <input id="wa_test_nomor" name="wa_test_nomor" type="text" placeholder="08xxxxxxxxxx">
      <p class="hint">Isi token di atas dulu (gak perlu "Simpan" dulu), lalu tekan tombol tes - dipakai langsung dari form ini.</p>
    </div>
    <button type="submit" name="action" value="wa_test" formnovalidate class="btn-secondary" style="width:auto;padding:10px 20px;">Kirim Tes</button>
  </form>
</div>
<?php layout_footer(); ?>

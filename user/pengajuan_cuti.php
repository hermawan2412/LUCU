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

    $jenis = $_POST['jenis_cuti'] ?? '';
    $alasan = trim($_POST['alasan_cuti'] ?? '');
    $lama = (int) ($_POST['lama_cuti'] ?? 0);
    $ketLama = $_POST['ket_lamacuti'] ?? '';
    $tglPengajuan = $_POST['tgl_pengajuan'] ?? '';
    $dari = $_POST['dari_tanggal'] ?? '';
    $sampai = $_POST['sampai_dengan'] ?? '';
    $alamatCuti = trim($_POST['alamat_cuti'] ?? '');

    if (!in_array($jenis, cuti_leave_types(), true)) {
        $errors[] = 'Jenis cuti tidak valid.';
    }
    if ($alasan === '') {
        $errors[] = 'Alasan cuti wajib diisi.';
    }
    if ($lama < 1) {
        $errors[] = 'Lama cuti minimal 1.';
    }
    if (!in_array($ketLama, ['Hari', 'Bulan', 'Tahun'], true)) {
        $errors[] = 'Satuan lama cuti tidak valid.';
    }
    foreach (['tgl_pengajuan' => $tglPengajuan, 'dari_tanggal' => $dari, 'sampai_dengan' => $sampai] as $field => $val) {
        if ($val === '' || DateTime::createFromFormat('Y-m-d', $val) === false) {
            $errors[] = "Tanggal ($field) tidak valid.";
        }
    }
    if ($alamatCuti === '') {
        $errors[] = 'Alamat selama cuti wajib diisi.';
    }
    if (empty($errors) && $dari > $sampai) {
        $errors[] = '"Sampai dengan" tidak boleh sebelum "Dari tanggal".';
    }
    if (empty($errors) && $jenis === 'Cuti Tahunan' && $lama > (int) $pegawai['hak_cuti_tahunan']) {
        $errors[] = 'Sisa cuti tahunan tidak mencukupi (sisa: ' . $pegawai['hak_cuti_tahunan'] . ').';
    }

    if (empty($errors)) {
        $chain = cuti_approval_chain($db, (int) $pegawai['id_jabatan']);
        $slots = cuti_build_approval_slots($db, $chain);

        if ($slots === null) {
            $errors[] = 'Pejabat penyetuju belum terdaftar di data pegawai. Hubungi admin.';
        } else {
            [$status, $ketStatus] = cuti_status_awal($slots);
            $tglIndo = indonesia_tgl($tglPengajuan);
            $dariIndo = indonesia_tgl($dari);
            $sampaiIndo = indonesia_tgl($sampai);
            $masaKerja = cuti_masa_kerja($pegawai['tmt_pegawai']);

            $db->beginTransaction();
            try {
                db_query($db, "INSERT INTO cuti_pegawai
                    (id_pegawai, jenis_cuti, alasan_cuti, lama_cuti, ket_lama_cuti, dari_tanggal, sampai_dengan,
                     panmud_kasubag, panitera_sekretaris, ketua,
                     app_panmud_kasubag, app_panitera_sekretaris, app_ketua,
                     status_cuti, ket_status_cuti, sisa_cuti, tgl_pengajuan, masa_kerja, delegasi, alamat_cuti, berkas)
                    VALUES (?,?,?,?,?,?,?, ?,?,?, ?,?,?, ?,?,?,?,?,?,?,?)",
                    [
                        $pegawai['id_pegawai'], $jenis, $alasan, $lama, $ketLama, $dariIndo, $sampaiIndo,
                        $slots['panmud_kasubag']['nip'], $slots['panitera_sekretaris']['nip'], $slots['ketua']['nip'],
                        $slots['panmud_kasubag']['flag'], $slots['panitera_sekretaris']['flag'], $slots['ketua']['flag'],
                        $status, $ketStatus, $pegawai['hak_cuti_tahunan'], $tglIndo, $masaKerja, '', $alamatCuti, '',
                    ]);

                if ($status === 'Disetujui' && $jenis === 'Cuti Tahunan') {
                    db_query($db, "UPDATE pegawai SET hak_cuti_tahunan = hak_cuti_tahunan - ? WHERE id_pegawai = ?", [$lama, $pegawai['id_pegawai']]);
                }

                $db->commit();
                flash_set('success', 'Pengajuan cuti berhasil dikirim.');
                redirect('daftar_cuti.php');
            } catch (Throwable $e) {
                $db->rollBack();
                error_log('Gagal simpan pengajuan cuti: ' . $e->getMessage());
                $errors[] = 'Terjadi kesalahan sistem, coba lagi.';
            }
        }
    }
}

layout_header('Ajukan Cuti', 'ajukan');
?>
<h1>Ajukan Cuti</h1>
<p class="lead">Persetujuan akan otomatis dirutekan sesuai jabatan Anda: <?= e($pegawai['nama_jabatan']) ?>.</p>

<div class="stat-row">
  <div class="stat-tile">
    <div class="num"><?= (int) $pegawai['hak_cuti_tahunan'] ?></div>
    <div class="label">Sisa Cuti Tahunan (hari)</div>
  </div>
  <div class="stat-tile">
    <div class="num" style="font-size:1.1rem"><?= e(cuti_masa_kerja($pegawai['tmt_pegawai'])) ?></div>
    <div class="label">Masa Kerja</div>
  </div>
</div>

<div class="card">
  <?php foreach ($errors as $err): ?>
    <div class="alert alert-danger"><?= e($err) ?></div>
  <?php endforeach; ?>

  <form method="POST">
    <?= csrf_field() ?>
    <div class="field">
      <label for="jenis_cuti">Jenis Cuti</label>
      <select id="jenis_cuti" name="jenis_cuti" required>
        <option value="" disabled selected>-- Pilih jenis cuti --</option>
        <?php foreach (cuti_leave_types() as $type): ?>
          <option value="<?= e($type) ?>" <?= ($_POST['jenis_cuti'] ?? '') === $type ? 'selected' : '' ?>><?= e($type) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="alasan_cuti">Alasan Cuti</label>
      <input id="alasan_cuti" name="alasan_cuti" type="text" required value="<?= e($_POST['alasan_cuti'] ?? '') ?>">
    </div>
    <div class="field-row">
      <div class="field">
        <label for="lama_cuti">Lama Cuti</label>
        <input id="lama_cuti" name="lama_cuti" type="number" min="1" required value="<?= e($_POST['lama_cuti'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="ket_lamacuti">Satuan</label>
        <select id="ket_lamacuti" name="ket_lamacuti" required>
          <option value="" disabled selected>-- Pilih --</option>
          <?php foreach (['Hari', 'Bulan', 'Tahun'] as $unit): ?>
            <option value="<?= $unit ?>" <?= ($_POST['ket_lamacuti'] ?? '') === $unit ? 'selected' : '' ?>><?= $unit ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="field-row">
      <div class="field">
        <label for="tgl_pengajuan">Tanggal Pengajuan</label>
        <input id="tgl_pengajuan" name="tgl_pengajuan" type="date" required value="<?= e($_POST['tgl_pengajuan'] ?? date('Y-m-d')) ?>">
      </div>
      <div class="field"></div>
    </div>
    <div class="field-row">
      <div class="field">
        <label for="dari_tanggal">Dari Tanggal</label>
        <input id="dari_tanggal" name="dari_tanggal" type="date" required value="<?= e($_POST['dari_tanggal'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="sampai_dengan">Sampai Dengan</label>
        <input id="sampai_dengan" name="sampai_dengan" type="date" required value="<?= e($_POST['sampai_dengan'] ?? '') ?>">
      </div>
    </div>
    <div class="field">
      <label for="alamat_cuti">Alamat Selama Cuti</label>
      <input id="alamat_cuti" name="alamat_cuti" type="text" required value="<?= e($_POST['alamat_cuti'] ?? '') ?>">
    </div>
    <button type="submit" class="btn-primary" style="width:auto;padding:10px 24px;">Ajukan Cuti</button>
  </form>
</div>
<?php layout_footer(); ?>

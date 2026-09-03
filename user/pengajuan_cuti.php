<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('User');

$pegawai = cuti_get_pegawai_by_nip($db, $_SESSION['nip']);
if ($pegawai === null) {
    flash_set('error', 'Akun Anda belum terhubung ke data pegawai. Hubungi admin.');
    redirect('index.php');
}
$pegawai = cuti_tahunan_rollover_jika_perlu($db, $pegawai);
$pegawai = cuti_sakit_reset_jika_perlu($db, $pegawai);
$kuotaTahunan = cuti_tahunan_kuota_tersedia($pegawai);

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

    if (!in_array($jenis, cuti_leave_types($pegawai['jenis_asn']), true)) {
        $errors[] = 'Jenis cuti tidak valid untuk status ASN Anda.';
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
    if (empty($errors) && $jenis === 'Cuti Tahunan' && $lama > $kuotaTahunan) {
        $errors[] = 'Sisa cuti tahunan tidak mencukupi (sisa: ' . $kuotaTahunan . ' hari, termasuk akumulasi tahun sebelumnya).';
    }
    if (empty($errors)) {
        $errors = array_merge($errors, cuti_validasi_jenis($db, $jenis, $lama, $ketLama, $pegawai));
    }

    if (empty($errors)) {
        $chain = cuti_approval_chain($db, (int) $pegawai['id_jabatan']);
        $chain = cuti_cap_chain_for_jenis_asn($db, $chain, $pegawai['jenis_asn'], (int) $pegawai['id_jabatan']);

        if ($chain === null) {
            $errors[] = 'Pejabat pemberi izin cuti untuk PPPK belum dikonfigurasi. Hubungi admin.';
        } else {
            $slots = cuti_build_approval_slots($db, $chain);
        }

        if (empty($errors) && $slots === null) {
            $errors[] = 'Pejabat penyetuju belum terdaftar di data pegawai. Hubungi admin.';
        } elseif (empty($errors)) {
            // Status awal SELALU 'Menunggu Nomor Surat', BUKAN hasil
            // cuti_status_awal() langsung - approval (termasuk jalur
            // auto-Disetujui buat rantai kosong) baru mulai jalan setelah
            // admin.kepegawaian ngisi nomor_surat, lihat
            // cuti_mulai_approval_setelah_nomor() & admin/data_cuti.php.
            // panmud_kasubag/panitera_sekretaris/ketua + app_* flag tetap
            // disimpan sekarang (struktural, bukan progres approval).
            $status = 'Menunggu Nomor Surat';
            $ketStatus = 'Menunggu penomoran surat oleh Kepegawaian';
            $tglIndo = indonesia_tgl($tglPengajuan);
            $dariIndo = indonesia_tgl($dari);
            $sampaiIndo = indonesia_tgl($sampai);
            $masaKerja = cuti_masa_kerja($pegawai['tmt_pegawai']);

            $db->beginTransaction();
            try {
                db_query($db, "INSERT INTO cuti_pegawai
                    (id_pegawai, jenis_cuti, alasan_cuti, lama_cuti, ket_lama_cuti, dari_tanggal, sampai_dengan, dari_tanggal_iso, sampai_dengan_iso,
                     panmud_kasubag, panitera_sekretaris, ketua,
                     app_panmud_kasubag, app_panitera_sekretaris, app_ketua,
                     status_cuti, ket_status_cuti, sisa_cuti, tgl_pengajuan, masa_kerja, delegasi, alamat_cuti, berkas)
                    VALUES (?,?,?,?,?,?,?,?,?, ?,?,?, ?,?,?, ?,?,?,?,?,?,?,?)",
                    [
                        $pegawai['id_pegawai'], $jenis, $alasan, $lama, $ketLama, $dariIndo, $sampaiIndo, $dari, $sampai,
                        $slots['panmud_kasubag']['nip'], $slots['panitera_sekretaris']['nip'], $slots['ketua']['nip'],
                        $slots['panmud_kasubag']['flag'], $slots['panitera_sekretaris']['flag'], $slots['ketua']['flag'],
                        $status, $ketStatus, $kuotaTahunan, $tglIndo, $masaKerja, '', $alamatCuti, '',
                    ]);

                $db->commit();

                notifikasi_kirim($db, $pegawai['nip'],
                    "Pengajuan {$jenis} Anda terkirim, menunggu penomoran surat oleh Kepegawaian.",
                    'daftar_cuti.php');

                flash_set('success', 'Pengajuan cuti berhasil dikirim, menunggu penomoran surat oleh Kepegawaian.');
                redirect('daftar_cuti.php');
            } catch (Throwable $e) {
                $db->rollBack();
                error_log('Gagal simpan pengajuan cuti: ' . $e->getMessage());
                $errors[] = 'Terjadi kesalahan sistem, coba lagi.';
            }
        }
    }
}

$adaAkumulasi = (int) $pegawai['cuti_tahunan_n1'] > 0 || (int) $pegawai['cuti_tahunan_n2'] > 0;
layout_header('Ajukan Cuti', 'ajukan');
?>
<h1>Ajukan Cuti</h1>
<p class="lead">Persetujuan akan otomatis dirutekan sesuai jabatan Anda: <?= e($pegawai['nama_jabatan']) ?>.</p>
<div class="stat-row">
  <div class="stat-tile tone-green">
    <div class="num"><?= $kuotaTahunan ?></div>
    <div class="label">Sisa Cuti Tahunan (hari)<?= $adaAkumulasi ? ' &middot; termasuk akumulasi' : '' ?></div>
  </div>
  <div class="stat-tile tone-teal">
    <div class="num" style="font-size:1.1rem"><?= e(cuti_masa_kerja($pegawai['tmt_pegawai'])) ?></div>
    <div class="label">Masa Kerja</div>
  </div>
</div>
<?php if ($adaAkumulasi): ?>
  <p style="font-size:0.85rem;color:var(--text-muted,#666);margin-top:-8px;">
    Rincian: tahun ini <?= (int) $pegawai['hak_cuti_tahunan'] ?> hari
    <?php if ((int) $pegawai['cuti_tahunan_n1'] > 0): ?> + tahun lalu <?= (int) $pegawai['cuti_tahunan_n1'] ?> hari<?php endif; ?>
    <?php if ((int) $pegawai['cuti_tahunan_n2'] > 0): ?> + 2 tahun lalu <?= (int) $pegawai['cuti_tahunan_n2'] ?> hari<?php endif; ?>
    (sesuai SE Sekma 13/2019 / SK Sekma 212/2024).
  </p>
<?php endif; ?>

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
        <?php foreach (cuti_leave_types($pegawai['jenis_asn']) as $type): ?>
          <option value="<?= e($type) ?>" <?= ($_POST['jenis_cuti'] ?? '') === $type ? 'selected' : '' ?>><?= e($type) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($pegawai['jenis_asn'] === 'PPPK'): ?>
        <p class="hint">Status PPPK cuma dapat 3 jenis cuti (PP 49/2018 Psl 76): Tahunan, Sakit, Melahirkan. Cuti Sakit &gt;14 hari wajib lampirkan surat keterangan dokter (serahkan manual ke bagian kepegawaian).</p>
      <?php else: ?>
        <p class="hint">Cuti Besar &amp; Cuti di Luar Tanggungan Negara: minimal masa kerja 5 tahun terus-menerus. Cuti Sakit &gt;14 hari wajib lampirkan surat keterangan dokter (serahkan manual ke bagian kepegawaian).</p>
      <?php endif; ?>
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

<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('Admin', 'Pengelola');

// Input manual cuti yang UDAH KELAR sebelum RESTU resmi jalan (data historis,
// bukan pengajuan baru) - lewatin seluruh alur nomor surat/approval, langsung
// status Disetujui. SENGAJA gak motong saldo cuti tahunan/sakit/penting sama
// sekali (dikonfirmasi user) - asumsinya saldo pegawai SEKARANG udah manual
// di-set net oleh admin pas rollout, mempertimbangkan histori ini. Kalau
// asumsi itu berubah di kemudian hari, cuti_potong_saldo_tahunan() (includes/cuti.php)
// udah ada tinggal dipanggil di sini.
// CUTI_HISTORIS_KETERANGAN (penanda baris hasil fitur ini) sekarang di
// includes/cuti.php, bukan di sini - dipakai juga sama database/reset_uji_coba.php.

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $idPegawai = (int) ($_POST['id_pegawai'] ?? 0);
    $jenis = $_POST['jenis_cuti'] ?? '';
    $dari = $_POST['dari_tanggal'] ?? '';
    $sampai = $_POST['sampai_dengan'] ?? '';
    $ketLama = $_POST['ket_lamacuti'] ?? '';
    $alasan = trim($_POST['alasan_cuti'] ?? '');
    $alamatCuti = trim($_POST['alamat_cuti'] ?? '') ?: '-';
    $nomorSurat = trim($_POST['nomor_surat'] ?? '') ?: null;

    $pegawai = $idPegawai > 0 ? db_one($db, "SELECT * FROM pegawai WHERE id_pegawai = ?", [$idPegawai]) : null;

    if ($pegawai === null) {
        $errors[] = 'Pegawai tidak ditemukan.';
    } elseif (!in_array($jenis, cuti_leave_types($pegawai['jenis_asn']), true)) {
        $errors[] = "Jenis cuti \"$jenis\" tidak valid untuk status ASN pegawai ini ({$pegawai['jenis_asn']}).";
    }
    if (!in_array($ketLama, ['Hari', 'Bulan', 'Tahun'], true)) {
        $errors[] = 'Satuan lama cuti tidak valid.';
    }
    foreach (['dari_tanggal' => $dari, 'sampai_dengan' => $sampai] as $field => $val) {
        if ($val === '' || DateTime::createFromFormat('Y-m-d', $val) === false) {
            $errors[] = "Tanggal ($field) tidak valid.";
        }
    }
    if (empty($errors) && $dari > $sampai) {
        $errors[] = '"Sampai dengan" tidak boleh sebelum "Dari tanggal".';
    }
    if ($alasan === '') {
        $errors[] = 'Alasan/keterangan wajib diisi (mis. "Cuti sebelum RESTU berjalan").';
    }

    if (empty($errors)) {
        $lama = $ketLama === 'Hari' ? ((int) ((strtotime($sampai) - strtotime($dari)) / 86400) + 1) : (int) ($_POST['lama_cuti'] ?? 1);
        $pegawai = cuti_tahunan_rollover_jika_perlu($db, $pegawai);
        $sisaSnapshot = $jenis === 'Cuti Tahunan' ? cuti_tahunan_kuota_tersedia($pegawai) : 0;

        db_query($db, "INSERT INTO cuti_pegawai
            (id_pegawai, jenis_cuti, alasan_cuti, lama_cuti, ket_lama_cuti, dari_tanggal, sampai_dengan, dari_tanggal_iso, sampai_dengan_iso,
             app_panmud_kasubag, app_panitera_sekretaris, app_ketua,
             status_cuti, ket_status_cuti, sisa_cuti, tgl_pengajuan, masa_kerja, delegasi, alamat_cuti, berkas, nomor_surat)
            VALUES (?,?,?,?,?,?,?,?,?, 1,1,1, 'Disetujui', ?, ?, ?, ?, '', ?, '', ?)",
            [
                $pegawai['id_pegawai'], $jenis, $alasan, $lama, $ketLama,
                indonesia_tgl($dari), indonesia_tgl($sampai), $dari, $sampai,
                CUTI_HISTORIS_KETERANGAN,
                $sisaSnapshot, indonesia_tgl($dari), cuti_masa_kerja($pegawai['tmt_pegawai']),
                $alamatCuti, $nomorSurat,
            ]);
        $newId = (int) $db->lastInsertId();

        log_aktivitas($db, 'inject_cuti_historis', "Input cuti historis #$newId ($jenis, {$pegawai['nama_pegawai']}, $dari s/d $sampai)");
        flash_set('success', "Cuti historis {$pegawai['nama_pegawai']} ($jenis, " . indonesia_tgl($dari) . ' s/d ' . indonesia_tgl($sampai) . ') berhasil dicatat.');
        redirect('data_cuti_historis.php');
    }
}

$semuaPegawai = db_all($db, "SELECT id_pegawai, nama_pegawai, nip, jenis_asn FROM pegawai ORDER BY nama_pegawai ASC");
$riwayatHistoris = db_all($db, "SELECT c.*, p.nama_pegawai FROM cuti_pegawai c JOIN pegawai p ON p.id_pegawai = c.id_pegawai
    WHERE c.ket_status_cuti = ?
    ORDER BY c.id_cutipegawai DESC", [CUTI_HISTORIS_KETERANGAN]);
$success = flash_get('success');

layout_header('Cuti Historis', 'historis', 'admin');
?>
<h1>Cuti Historis</h1>
<p class="lead">Catat cuti pegawai yang sudah berjalan/selesai <strong>sebelum RESTU resmi dipakai</strong> - langsung berstatus Disetujui, gak lewat alur nomor surat/approval. <strong>Gak motong saldo cuti tahunan/sakit/penting</strong> - cuma catatan biar riwayat &amp; kalender lengkap.</p>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<div class="card">
  <h2 style="margin:0 0 16px;">Catat Cuti Historis</h2>
  <form method="POST">
    <?= csrf_field() ?>
    <div class="field">
      <label for="id_pegawai">Pegawai</label>
      <select id="id_pegawai" name="id_pegawai" required>
        <option value="" disabled selected>-- Pilih pegawai --</option>
        <?php foreach ($semuaPegawai as $p): ?>
          <option value="<?= (int) $p['id_pegawai'] ?>" <?= (int) ($_POST['id_pegawai'] ?? 0) === (int) $p['id_pegawai'] ? 'selected' : '' ?>>
            <?= e($p['nama_pegawai']) ?> &middot; <?= e($p['nip']) ?> &middot; <?= e($p['jenis_asn']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="jenis_cuti">Jenis Cuti</label>
      <select id="jenis_cuti" name="jenis_cuti" required>
        <option value="" disabled selected>-- Pilih jenis cuti --</option>
        <?php foreach (cuti_leave_types('PNS') as $type): ?>
          <option value="<?= e($type) ?>" <?= ($_POST['jenis_cuti'] ?? '') === $type ? 'selected' : '' ?>><?= e($type) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="hint">Daftar lengkap 7 jenis (PNS) - kalau pegawainya PPPK, cuma Tahunan/Sakit/Melahirkan yang valid, sisanya ditolak pas disimpan.</p>
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
    <div class="field-row">
      <div class="field">
        <label for="ket_lamacuti">Satuan Lama Cuti</label>
        <select id="ket_lamacuti" name="ket_lamacuti" required>
          <option value="Hari" selected>Hari (dihitung otomatis dari tanggal)</option>
          <option value="Bulan">Bulan</option>
          <option value="Tahun">Tahun</option>
        </select>
      </div>
      <div class="field">
        <label for="lama_cuti">Lama (khusus satuan Bulan/Tahun)</label>
        <input id="lama_cuti" name="lama_cuti" type="number" min="1" value="<?= e($_POST['lama_cuti'] ?? '') ?>">
      </div>
    </div>
    <div class="field">
      <label for="alasan_cuti">Alasan/Keterangan</label>
      <input id="alasan_cuti" name="alasan_cuti" type="text" required placeholder='mis. "Cuti melahirkan, data historis sebelum RESTU"' value="<?= e($_POST['alasan_cuti'] ?? '') ?>">
    </div>
    <div class="field-row">
      <div class="field">
        <label for="alamat_cuti">Alamat Selama Cuti (opsional)</label>
        <input id="alamat_cuti" name="alamat_cuti" type="text" value="<?= e($_POST['alamat_cuti'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="nomor_surat">Nomor Surat (opsional, kalau ada arsipnya)</label>
        <input id="nomor_surat" name="nomor_surat" type="text" value="<?= e($_POST['nomor_surat'] ?? '') ?>">
      </div>
    </div>
    <button type="submit" class="btn-primary" style="width:auto;padding:10px 24px;">Simpan sebagai Disetujui</button>
  </form>
</div>

<div class="card">
  <h2 style="margin:0 0 16px;">Riwayat Input Historis</h2>
  <?php if (empty($riwayatHistoris)): ?>
    <div class="empty-state">Belum ada cuti historis yang diinput.</div>
  <?php else: ?>
    <div class="table-scroll">
      <table class="data-table">
        <thead><tr><th>Pegawai</th><th>Jenis</th><th>Tanggal</th><th>Lama</th><th>Nomor Surat</th></tr></thead>
        <tbody>
          <?php foreach ($riwayatHistoris as $row): ?>
            <tr>
              <td><?= e($row['nama_pegawai']) ?></td>
              <td><?= e($row['jenis_cuti']) ?></td>
              <td><?= e($row['dari_tanggal']) ?> &ndash; <?= e($row['sampai_dengan']) ?></td>
              <td><?= e($row['lama_cuti']) ?> <?= e($row['ket_lama_cuti']) ?></td>
              <td><?= $row['nomor_surat'] ? e($row['nomor_surat']) : '-' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php layout_footer(); ?>

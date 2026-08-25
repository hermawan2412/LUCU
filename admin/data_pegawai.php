<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('Admin');

$errors = [];
$editing = null;

function pegawai_form_values(array $post, ?array $editing): array
{
    $keys = ['nama_pegawai', 'nip', 'id_jabatan', 'id_golongan', 'jenis_asn', 'unit_kerja', 'tmt_pegawai', 'hak_cuti_tahunan', 'cuti_tahunan_n1', 'cuti_tahunan_n2', 'hak_cuti_sakit', 'hak_cuti_penting', 'no_telp'];
    $out = [];
    foreach ($keys as $k) {
        $out[$k] = $post[$k] ?? ($editing[$k] ?? '');
    }
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['id_pegawai'] ?? 0);
        db_query($db, "DELETE FROM pegawai WHERE id_pegawai = ?", [$id]);
        flash_set('success', 'Data pegawai dihapus.');
        redirect('data_pegawai.php');
    }

    $nama = trim($_POST['nama_pegawai'] ?? '');
    $nip = trim($_POST['nip'] ?? '');
    $idJabatan = (int) ($_POST['id_jabatan'] ?? 0);
    $idGolongan = (int) ($_POST['id_golongan'] ?? 0);
    $jenisAsn = $_POST['jenis_asn'] ?? 'PNS';
    $unitKerja = trim($_POST['unit_kerja'] ?? '') ?: APP_INSTANSI;
    $tmt = $_POST['tmt_pegawai'] ?? '';
    $hakTahunan = (int) ($_POST['hak_cuti_tahunan'] ?? 0);
    $cutiN1 = (int) ($_POST['cuti_tahunan_n1'] ?? 0);
    $cutiN2 = (int) ($_POST['cuti_tahunan_n2'] ?? 0);
    $hakSakit = (int) ($_POST['hak_cuti_sakit'] ?? 0);
    $hakPenting = (int) ($_POST['hak_cuti_penting'] ?? 0);
    $noTelp = trim($_POST['no_telp'] ?? '');
    $id = isset($_POST['id_pegawai']) ? (int) $_POST['id_pegawai'] : null;

    if ($nama === '') $errors[] = 'Nama pegawai wajib diisi.';
    if ($nip === '' || !ctype_digit($nip)) $errors[] = 'NIP wajib diisi, hanya angka.';
    if ($idJabatan <= 0) $errors[] = 'Jabatan wajib dipilih.';
    if ($idGolongan <= 0) $errors[] = 'Golongan wajib dipilih.';
    if (!in_array($jenisAsn, ['PNS', 'PPPK'], true)) $errors[] = 'Jenis ASN tidak valid.';
    if ($tmt !== '' && DateTime::createFromFormat('Y-m-d', $tmt) === false) $errors[] = 'Tanggal TMT tidak valid.';
    if ($hakTahunan < 0 || $cutiN1 < 0 || $cutiN2 < 0 || $hakSakit < 0 || $hakPenting < 0) $errors[] = 'Hak cuti tidak boleh negatif.';

    if (empty($errors)) {
        $tmtValue = $tmt !== '' ? $tmt : null;
        $tahunIni = (int) date('Y');
        try {
            if ($action === 'create') {
                // cuti_tahunan_rollover_tahun diisi tahun ini biar rollover
                // otomatis gak langsung "makan" starter N-1/N-2 yg baru
                // diisi admin di tahun yg sama.
                db_query($db, "INSERT INTO pegawai (nama_pegawai, nip, id_jabatan, id_golongan, jenis_asn, unit_kerja, tmt_pegawai, hak_cuti_tahunan, cuti_tahunan_n1, cuti_tahunan_n2, cuti_tahunan_rollover_tahun, hak_cuti_sakit, hak_cuti_penting, no_telp)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    [$nama, $nip, $idJabatan, $idGolongan, $jenisAsn, $unitKerja, $tmtValue, $hakTahunan, $cutiN1, $cutiN2, $tahunIni, $hakSakit, $hakPenting, $noTelp]);
                flash_set('success', 'Pegawai ditambahkan.');
                redirect('data_pegawai.php');
            } elseif ($action === 'update') {
                db_query($db, "UPDATE pegawai SET nama_pegawai=?, nip=?, id_jabatan=?, id_golongan=?, jenis_asn=?, unit_kerja=?, tmt_pegawai=?, hak_cuti_tahunan=?, cuti_tahunan_n1=?, cuti_tahunan_n2=?, hak_cuti_sakit=?, hak_cuti_penting=?, no_telp=? WHERE id_pegawai=?",
                    [$nama, $nip, $idJabatan, $idGolongan, $jenisAsn, $unitKerja, $tmtValue, $hakTahunan, $cutiN1, $cutiN2, $hakSakit, $hakPenting, $noTelp, $id]);
                flash_set('success', 'Data pegawai diperbarui.');
                redirect('data_pegawai.php');
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errors[] = "NIP $nip sudah dipakai pegawai lain.";
            } else {
                error_log('Gagal simpan pegawai: ' . $e->getMessage());
                $errors[] = 'Terjadi kesalahan sistem, coba lagi.';
            }
        }
    }
}

if (isset($_GET['edit'])) {
    $editing = db_one($db, "SELECT * FROM pegawai WHERE id_pegawai = ?", [(int) $_GET['edit']]);
}

$form = pegawai_form_values($_POST, $editing);

$list = db_all($db, "SELECT p.*, j.nama_jabatan, g.nama_golongan
    FROM pegawai p
    JOIN jabatan j ON j.id_jabatan = p.id_jabatan
    JOIN golongan g ON g.id_golongan = p.id_golongan
    ORDER BY p.nama_pegawai ASC");
foreach ($list as &$row) {
    $row = cuti_tahunan_rollover_jika_perlu($db, $row);
    $row['kuota_tersedia'] = cuti_tahunan_kuota_tersedia($row);
}
unset($row);
$jabatanList = db_all($db, "SELECT id_jabatan, nama_jabatan FROM jabatan ORDER BY nama_jabatan ASC");
$golonganList = db_all($db, "SELECT id_golongan, nama_golongan, jenis_asn FROM golongan ORDER BY jenis_asn ASC, id_golongan ASC");
$golonganByJenis = ['PNS' => [], 'PPPK' => []];
foreach ($golonganList as $g) {
    $golonganByJenis[$g['jenis_asn']][] = $g;
}
$success = flash_get('success');

layout_header('Data Pegawai', 'pegawai', 'admin');
?>
<h1>Data Pegawai</h1>
<p class="lead">Kelola data pegawai, jabatan, dan hak cuti. <a href="export_pegawai.php" class="btn-secondary" style="padding:4px 14px;font-size:0.78rem;">Export CSV</a></p>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<div class="card">
  <h2 style="font-size:1.1rem;margin:0 0 12px;"><?= $editing ? 'Edit Pegawai' : 'Tambah Pegawai' ?></h2>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
    <?php if ($editing): ?><input type="hidden" name="id_pegawai" value="<?= (int) $editing['id_pegawai'] ?>"><?php endif; ?>

    <div class="field-row">
      <div class="field">
        <label for="nama_pegawai">Nama Lengkap</label>
        <input id="nama_pegawai" name="nama_pegawai" type="text" required value="<?= e($form['nama_pegawai']) ?>">
      </div>
      <div class="field">
        <label for="nip">NIP</label>
        <input id="nip" name="nip" type="text" inputmode="numeric" required value="<?= e($form['nip']) ?>">
      </div>
    </div>

    <div class="field-row">
      <div class="field">
        <label for="id_jabatan">Jabatan</label>
        <select id="id_jabatan" name="id_jabatan" required>
          <option value="" disabled <?= $form['id_jabatan'] === '' ? 'selected' : '' ?>>-- Pilih jabatan --</option>
          <?php foreach ($jabatanList as $j): ?>
            <option value="<?= (int) $j['id_jabatan'] ?>" <?= (int) $form['id_jabatan'] === (int) $j['id_jabatan'] ? 'selected' : '' ?>><?= e($j['nama_jabatan']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="id_golongan">Golongan</label>
        <select id="id_golongan" name="id_golongan" required>
          <option value="" disabled <?= $form['id_golongan'] === '' ? 'selected' : '' ?>>-- Pilih golongan --</option>
          <?php foreach ($golonganByJenis as $jenis => $golonganGrup): ?>
            <?php if (empty($golonganGrup)) continue; ?>
            <optgroup label="<?= e($jenis) ?>">
              <?php foreach ($golonganGrup as $g): ?>
                <option value="<?= (int) $g['id_golongan'] ?>" <?= (int) $form['id_golongan'] === (int) $g['id_golongan'] ? 'selected' : '' ?>><?= e($g['nama_golongan']) ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="field-row">
      <div class="field">
        <label for="unit_kerja">Unit Kerja</label>
        <input id="unit_kerja" name="unit_kerja" type="text" value="<?= e($form['unit_kerja'] ?: APP_INSTANSI) ?>">
      </div>
      <div class="field">
        <label for="tmt_pegawai">TMT (Terhitung Mulai Tanggal)</label>
        <input id="tmt_pegawai" name="tmt_pegawai" type="date" value="<?= e($form['tmt_pegawai'] ?? '') ?>">
      </div>
    </div>

    <div class="field-row">
      <div class="field">
        <label for="jenis_asn">Jenis ASN</label>
        <select id="jenis_asn" name="jenis_asn">
          <option value="PNS" <?= ($form['jenis_asn'] ?: 'PNS') === 'PNS' ? 'selected' : '' ?>>PNS</option>
          <option value="PPPK" <?= ($form['jenis_asn'] ?? '') === 'PPPK' ? 'selected' : '' ?>>PPPK</option>
        </select>
        <p class="hint">Menentukan pejabat pemberi izin cuti akhir: PNS &rarr; Ketua, PPPK &rarr; Sekretaris.</p>
      </div>
      <div class="field"></div>
    </div>

    <div class="field-row">
      <div class="field">
        <label for="hak_cuti_tahunan">Sisa Cuti Tahunan (hari) &middot; tahun berjalan</label>
        <input id="hak_cuti_tahunan" name="hak_cuti_tahunan" type="number" min="0" value="<?= e((string) ($form['hak_cuti_tahunan'] ?: 12)) ?>">
      </div>
      <div class="field">
        <label for="no_telp">No. Telepon</label>
        <input id="no_telp" name="no_telp" type="text" value="<?= e($form['no_telp']) ?>">
      </div>
    </div>

    <div class="field-row">
      <div class="field">
        <label for="cuti_tahunan_n1">Starter Sisa Cuti Tahunan Lalu (N-1)</label>
        <input id="cuti_tahunan_n1" name="cuti_tahunan_n1" type="number" min="0" max="12" value="<?= e((string) ($form['cuti_tahunan_n1'] ?: 0)) ?>">
        <p class="hint">Isi manual berapa hari sisa cuti tahunan tahun lalu (pas rollout aplikasi, karena belum ada histori). Otomatis di-roll tiap ganti tahun setelah ini.</p>
      </div>
      <div class="field">
        <label for="cuti_tahunan_n2">Starter Sisa Cuti 2 Tahun Lalu (N-2)</label>
        <input id="cuti_tahunan_n2" name="cuti_tahunan_n2" type="number" min="0" max="12" value="<?= e((string) ($form['cuti_tahunan_n2'] ?: 0)) ?>">
        <p class="hint">Sama, buat 2 tahun lalu. Akumulasi cuti tahunan ikut SE Sekma 13/2019 (PNS) / SK Sekma 212/2024 (PPPK): 18 hari kalau N-1 gak kepake sama sekali, 24 hari kalau N-1 &amp; N-2 gak kepake sama sekali.</p>
      </div>
    </div>

    <div class="field-row">
      <div class="field">
        <label for="hak_cuti_sakit">Hak Cuti Sakit (hari)</label>
        <input id="hak_cuti_sakit" name="hak_cuti_sakit" type="number" min="0" value="<?= e((string) ($form['hak_cuti_sakit'] ?: 0)) ?>">
      </div>
      <div class="field">
        <label for="hak_cuti_penting">Hak Cuti Alasan Penting (hari)</label>
        <input id="hak_cuti_penting" name="hak_cuti_penting" type="number" min="0" value="<?= e((string) ($form['hak_cuti_penting'] ?: 0)) ?>">
      </div>
    </div>

    <button type="submit" class="btn-primary" style="width:auto;padding:10px 20px;margin-top:8px;"><?= $editing ? 'Simpan' : 'Tambah' ?></button>
    <?php if ($editing): ?><a href="data_pegawai.php" class="btn-secondary">Batal</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <div style="overflow-x:auto;">
    <table class="data-table">
      <thead>
        <tr><th>Nama</th><th>NIP</th><th>Jabatan</th><th>Golongan</th><th>ASN</th><th>Sisa Cuti Tahunan</th><th style="width:160px;">Aksi</th></tr>
      </thead>
      <tbody>
        <?php foreach ($list as $row): ?>
          <tr>
            <td><?= e($row['nama_pegawai']) ?></td>
            <td><?= e($row['nip']) ?></td>
            <td><?= e($row['nama_jabatan']) ?></td>
            <td><?= e($row['nama_golongan']) ?></td>
            <td><span class="badge <?= $row['jenis_asn'] === 'PPPK' ? 'badge-warning' : 'badge-neutral' ?>"><?= e($row['jenis_asn']) ?></span></td>
            <td><?= $row['kuota_tersedia'] ?><?= ((int) $row['cuti_tahunan_n1'] > 0 || (int) $row['cuti_tahunan_n2'] > 0) ? ' <span class="hint" style="display:inline">(+akumulasi)</span>' : '' ?></td>
            <td>
              <a href="?edit=<?= (int) $row['id_pegawai'] ?>" class="btn-secondary" style="padding:5px 10px;">Edit</a>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus data pegawai <?= e(addslashes($row['nama_pegawai'])) ?>? Semua riwayat cuti/KGB/KNP-nya ikut terhapus.');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id_pegawai" value="<?= (int) $row['id_pegawai'] ?>">
                <button type="submit" class="btn-secondary" style="padding:5px 10px;">Hapus</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php layout_footer(); ?>

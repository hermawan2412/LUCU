<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('Admin');

$errors = [];
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['id_knppegawai'] ?? 0);
        db_query($db, "DELETE FROM knp_pegawai WHERE id_knppegawai = ?", [$id]);
        flash_set('success', 'Catatan KNP dihapus.');
        redirect('data_knp.php');
    }

    $idPegawai = (int) ($_POST['id_pegawai'] ?? 0);
    $knpTerakhir = $_POST['knp_terakhir'] ?? '';
    $knpDatang = $_POST['knp_datang'] ?? '';
    $idGolonganTujuan = (int) ($_POST['id_golongan_tujuan'] ?? 0);
    $catatan = trim($_POST['catatan'] ?? '');
    $pensiunRaw = $_POST['pensiun'] ?? '';
    $id = isset($_POST['id_knppegawai']) ? (int) $_POST['id_knppegawai'] : null;

    if ($idPegawai <= 0) $errors[] = 'Pegawai wajib dipilih.';
    if ($knpTerakhir === '' || DateTime::createFromFormat('Y-m-d', $knpTerakhir) === false) {
        $errors[] = 'Tanggal KNP terakhir tidak valid.';
    }
    if ($knpDatang === '' || DateTime::createFromFormat('Y-m-d', $knpDatang) === false) {
        $errors[] = 'Tanggal KNP yang akan datang tidak valid.';
    }
    if (empty($errors) && $knpDatang <= $knpTerakhir) {
        $errors[] = '"KNP yang akan datang" harus setelah "KNP terakhir".';
    }
    if ($idGolonganTujuan <= 0) $errors[] = 'Golongan tujuan wajib dipilih.';
    if ($pensiunRaw !== '' && DateTime::createFromFormat('Y-m-d', $pensiunRaw) === false) {
        $errors[] = 'Tanggal pensiun tidak valid.';
    }

    if (empty($errors)) {
        $pensiun = $pensiunRaw !== '' ? $pensiunRaw : null;
        if ($action === 'create') {
            db_query($db, "INSERT INTO knp_pegawai (id_pegawai, knp_terakhir, knp_datang, id_golongan_tujuan, catatan, pensiun) VALUES (?,?,?,?,?,?)",
                [$idPegawai, $knpTerakhir, $knpDatang, $idGolonganTujuan, $catatan, $pensiun]);
            flash_set('success', 'Catatan KNP ditambahkan.');
            redirect('data_knp.php');
        } elseif ($action === 'update') {
            db_query($db, "UPDATE knp_pegawai SET id_pegawai=?, knp_terakhir=?, knp_datang=?, id_golongan_tujuan=?, catatan=?, pensiun=? WHERE id_knppegawai=?",
                [$idPegawai, $knpTerakhir, $knpDatang, $idGolonganTujuan, $catatan, $pensiun, $id]);
            flash_set('success', 'Catatan KNP diperbarui.');
            redirect('data_knp.php');
        }
    }
}

if (isset($_GET['edit'])) {
    $editing = db_one($db, "SELECT * FROM knp_pegawai WHERE id_knppegawai = ?", [(int) $_GET['edit']]);
}

$list = knp_daftar_terbaru_per_pegawai($db);
$pegawaiList = db_all($db, "SELECT id_pegawai, nama_pegawai, nip FROM pegawai ORDER BY nama_pegawai ASC");
$golonganList = db_all($db, "SELECT id_golongan, nama_golongan FROM golongan ORDER BY id_golongan ASC");
$success = flash_get('success');

$jumlahOverdue = count(array_filter($list, fn($r) => knp_status($r['knp_datang']) === 'overdue'));
$jumlahSegera = count(array_filter($list, fn($r) => knp_status($r['knp_datang']) === 'segera'));
$jumlahPensiun = count(array_filter($list, fn($r) => knp_pensiun_mendekati($r['pensiun'])));

layout_header('Data KNP', 'knp', 'admin');
?>
<h1>Kenaikan Pangkat (KNP)</h1>
<p class="lead">Pencatatan kenaikan golongan pegawai. "KNP yang akan datang" otomatis terisi +4 tahun dari tanggal terakhir. <a href="export_knp.php" class="btn-secondary" style="padding:4px 14px;font-size:0.78rem;">Export CSV</a></p>

<div class="stat-row">
  <div class="stat-tile"><div class="num" style="color:var(--danger)"><?= $jumlahOverdue ?></div><div class="label">Jatuh Tempo</div></div>
  <div class="stat-tile"><div class="num" style="color:#8a6300"><?= $jumlahSegera ?></div><div class="label">Segera (&le;90 hari)</div></div>
  <div class="stat-tile"><div class="num" style="color:#8a6300"><?= $jumlahPensiun ?></div><div class="label">Mendekati Pensiun (&le;1th)</div></div>
  <div class="stat-tile"><div class="num"><?= count($list) ?></div><div class="label">Total Pegawai</div></div>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<div class="card">
  <h2 style="font-size:1.1rem;margin:0 0 12px;"><?= $editing ? 'Edit Catatan KNP' : 'Tambah Catatan KNP' ?></h2>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
    <?php if ($editing): ?><input type="hidden" name="id_knppegawai" value="<?= (int) $editing['id_knppegawai'] ?>"><?php endif; ?>

    <div class="field">
      <label for="id_pegawai">Pegawai</label>
      <select id="id_pegawai" name="id_pegawai" required>
        <option value="" disabled <?= !$editing ? 'selected' : '' ?>>-- Pilih pegawai --</option>
        <?php foreach ($pegawaiList as $p): ?>
          <option value="<?= (int) $p['id_pegawai'] ?>" <?= (int) ($editing['id_pegawai'] ?? 0) === (int) $p['id_pegawai'] ? 'selected' : '' ?>><?= e($p['nama_pegawai']) ?> &middot; <?= e($p['nip']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field-row">
      <div class="field">
        <label for="knp_terakhir">KNP Terakhir</label>
        <input id="knp_terakhir" name="knp_terakhir" type="date" required value="<?= e($editing['knp_terakhir'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="knp_datang">KNP Yang Akan Datang</label>
        <input id="knp_datang" name="knp_datang" type="date" required value="<?= e($editing['knp_datang'] ?? '') ?>">
        <p class="hint">Terisi otomatis (+4 tahun) begitu "KNP Terakhir" diisi.</p>
      </div>
    </div>

    <div class="field-row">
      <div class="field">
        <label for="id_golongan_tujuan">Golongan Tujuan</label>
        <select id="id_golongan_tujuan" name="id_golongan_tujuan" required>
          <option value="" disabled <?= !$editing ? 'selected' : '' ?>>-- Pilih golongan tujuan --</option>
          <?php foreach ($golonganList as $g): ?>
            <option value="<?= (int) $g['id_golongan'] ?>" <?= (int) ($editing['id_golongan_tujuan'] ?? 0) === (int) $g['id_golongan'] ? 'selected' : '' ?>><?= e($g['nama_golongan']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="pensiun">Proyeksi Pensiun (BUP)</label>
        <input id="pensiun" name="pensiun" type="date" value="<?= e($editing['pensiun'] ?? '') ?>">
      </div>
    </div>

    <div class="field">
      <label for="catatan">Catatan</label>
      <input id="catatan" name="catatan" type="text" value="<?= e($editing['catatan'] ?? '') ?>" placeholder="opsional">
    </div>

    <button type="submit" class="btn-primary" style="width:auto;padding:10px 20px;margin-top:8px;"><?= $editing ? 'Simpan' : 'Tambah' ?></button>
    <?php if ($editing): ?><a href="data_knp.php" class="btn-secondary">Batal</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr><th>Nama</th><th>NIP</th><th>Golongan Skrg</th><th>KNP Terakhir</th><th>KNP Akan Datang</th><th>Tujuan</th><th>Status</th><th>Pensiun</th><th style="width:160px;">Aksi</th></tr>
      </thead>
      <tbody>
        <?php foreach ($list as $row): ?>
          <?php $status = knp_status($row['knp_datang']); $mendekatiPensiun = knp_pensiun_mendekati($row['pensiun']); ?>
          <tr>
            <td><?= e($row['nama_pegawai']) ?></td>
            <td><?= e($row['nip']) ?></td>
            <td><?= e($row['nama_golongan']) ?></td>
            <td><?= $row['knp_terakhir'] ? e(indonesia_tgl($row['knp_terakhir'])) : '-' ?></td>
            <td><?= $row['knp_datang'] ? e(indonesia_tgl($row['knp_datang'])) : '-' ?></td>
            <td><?= $row['nama_golongan_tujuan'] ? e($row['nama_golongan_tujuan']) : '-' ?></td>
            <td><span class="badge <?= knp_status_badge_class($status) ?>"><?= knp_status_label($status) ?></span></td>
            <td>
              <?php if ($row['pensiun']): ?>
                <?= e(indonesia_tgl($row['pensiun'])) ?>
                <?php if ($mendekatiPensiun): ?><br><span class="badge badge-warning">Mendekati</span><?php endif; ?>
              <?php else: ?>-<?php endif; ?>
            </td>
            <td>
              <?php if ($row['id_knppegawai']): ?>
                <a href="?edit=<?= (int) $row['id_knppegawai'] ?>" class="btn-secondary" style="padding:5px 10px;">Edit</a>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus catatan KNP <?= e(addslashes($row['nama_pegawai'])) ?>?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id_knppegawai" value="<?= (int) $row['id_knppegawai'] ?>">
                  <button type="submit" class="btn-secondary" style="padding:5px 10px;">Hapus</button>
                </form>
              <?php else: ?>
                <span class="hint">belum dicatat</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
  document.getElementById('knp_terakhir').addEventListener('change', function () {
    var datangField = document.getElementById('knp_datang');
    if (datangField.value) return;
    var d = new Date(this.value);
    if (isNaN(d.getTime())) return;
    d.setFullYear(d.getFullYear() + 4);
    datangField.value = d.toISOString().slice(0, 10);
  });
</script>
<?php layout_footer(); ?>

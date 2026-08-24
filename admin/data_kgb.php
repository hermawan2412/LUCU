<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('Admin');

$errors = [];
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['id_kgbpegawai'] ?? 0);
        db_query($db, "DELETE FROM kgb_pegawai WHERE id_kgbpegawai = ?", [$id]);
        flash_set('success', 'Catatan KGB dihapus.');
        redirect('data_kgb.php');
    }

    $idPegawai = (int) ($_POST['id_pegawai'] ?? 0);
    $kgbTerakhir = $_POST['kgb_terakhir'] ?? '';
    $kgbDatang = $_POST['kgb_datang'] ?? '';
    $keterangan = trim($_POST['keterangan'] ?? '');
    $id = isset($_POST['id_kgbpegawai']) ? (int) $_POST['id_kgbpegawai'] : null;

    if ($idPegawai <= 0) $errors[] = 'Pegawai wajib dipilih.';
    if ($kgbTerakhir === '' || DateTime::createFromFormat('Y-m-d', $kgbTerakhir) === false) {
        $errors[] = 'Tanggal KGB terakhir tidak valid.';
    }
    if ($kgbDatang === '' || DateTime::createFromFormat('Y-m-d', $kgbDatang) === false) {
        $errors[] = 'Tanggal KGB yang akan datang tidak valid.';
    }
    if (empty($errors) && $kgbDatang <= $kgbTerakhir) {
        $errors[] = '"KGB yang akan datang" harus setelah "KGB terakhir".';
    }

    if (empty($errors)) {
        if ($action === 'create') {
            db_query($db, "INSERT INTO kgb_pegawai (id_pegawai, kgb_terakhir, kgb_datang, keterangan) VALUES (?,?,?,?)",
                [$idPegawai, $kgbTerakhir, $kgbDatang, $keterangan]);
            flash_set('success', 'Catatan KGB ditambahkan.');
            redirect('data_kgb.php');
        } elseif ($action === 'update') {
            db_query($db, "UPDATE kgb_pegawai SET id_pegawai=?, kgb_terakhir=?, kgb_datang=?, keterangan=? WHERE id_kgbpegawai=?",
                [$idPegawai, $kgbTerakhir, $kgbDatang, $keterangan, $id]);
            flash_set('success', 'Catatan KGB diperbarui.');
            redirect('data_kgb.php');
        }
    }
}

if (isset($_GET['edit'])) {
    $editing = db_one($db, "SELECT * FROM kgb_pegawai WHERE id_kgbpegawai = ?", [(int) $_GET['edit']]);
}

$list = kgb_daftar_terbaru_per_pegawai($db);
$pegawaiList = db_all($db, "SELECT id_pegawai, nama_pegawai, nip FROM pegawai ORDER BY nama_pegawai ASC");
$success = flash_get('success');

$jumlahOverdue = count(array_filter($list, fn($r) => kgb_status($r['kgb_datang']) === 'overdue'));
$jumlahSegera = count(array_filter($list, fn($r) => kgb_status($r['kgb_datang']) === 'segera'));

layout_header('Data KGB', 'kgb', 'admin');
?>
<h1>Kenaikan Gaji Berkala (KGB)</h1>
<p class="lead">Pencatatan KGB tiap pegawai. "KGB yang akan datang" otomatis terisi +2 tahun dari tanggal terakhir, bisa diubah manual kalau ada penundaan.</p>

<div class="stat-row">
  <div class="stat-tile"><div class="num" style="color:var(--danger)"><?= $jumlahOverdue ?></div><div class="label">Jatuh Tempo</div></div>
  <div class="stat-tile"><div class="num" style="color:#8a6300"><?= $jumlahSegera ?></div><div class="label">Segera (&le;60 hari)</div></div>
  <div class="stat-tile"><div class="num"><?= count($list) ?></div><div class="label">Total Pegawai</div></div>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<div class="card">
  <h2 style="font-size:1.1rem;margin:0 0 12px;"><?= $editing ? 'Edit Catatan KGB' : 'Tambah Catatan KGB' ?></h2>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
    <?php if ($editing): ?><input type="hidden" name="id_kgbpegawai" value="<?= (int) $editing['id_kgbpegawai'] ?>"><?php endif; ?>

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
        <label for="kgb_terakhir">KGB Terakhir</label>
        <input id="kgb_terakhir" name="kgb_terakhir" type="date" required value="<?= e($editing['kgb_terakhir'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="kgb_datang">KGB Yang Akan Datang</label>
        <input id="kgb_datang" name="kgb_datang" type="date" required value="<?= e($editing['kgb_datang'] ?? '') ?>">
        <p class="hint">Terisi otomatis (+2 tahun) begitu "KGB Terakhir" diisi. Ubah manual kalau ada penundaan.</p>
      </div>
    </div>

    <div class="field">
      <label for="keterangan">Keterangan</label>
      <input id="keterangan" name="keterangan" type="text" value="<?= e($editing['keterangan'] ?? '') ?>" placeholder="mis. Golongan III/b, masa kerja 4 tahun">
    </div>

    <button type="submit" class="btn-primary" style="width:auto;padding:10px 20px;margin-top:8px;"><?= $editing ? 'Simpan' : 'Tambah' ?></button>
    <?php if ($editing): ?><a href="data_kgb.php" class="btn-secondary">Batal</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <div style="overflow-x:auto;">
    <table class="data-table">
      <thead>
        <tr><th>Nama</th><th>NIP</th><th>Jabatan</th><th>Golongan</th><th>KGB Terakhir</th><th>KGB Akan Datang</th><th>Status</th><th style="width:160px;">Aksi</th></tr>
      </thead>
      <tbody>
        <?php foreach ($list as $row): ?>
          <?php $status = kgb_status($row['kgb_datang']); ?>
          <tr>
            <td><?= e($row['nama_pegawai']) ?></td>
            <td><?= e($row['nip']) ?></td>
            <td><?= e($row['nama_jabatan']) ?></td>
            <td><?= e($row['nama_golongan']) ?></td>
            <td><?= $row['kgb_terakhir'] ? e(indonesia_tgl($row['kgb_terakhir'])) : '-' ?></td>
            <td><?= $row['kgb_datang'] ? e(indonesia_tgl($row['kgb_datang'])) : '-' ?></td>
            <td><span class="badge <?= kgb_status_badge_class($status) ?>"><?= kgb_status_label($status) ?></span></td>
            <td>
              <?php if ($row['id_kgbpegawai']): ?>
                <a href="?edit=<?= (int) $row['id_kgbpegawai'] ?>" class="btn-secondary" style="padding:5px 10px;">Edit</a>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus catatan KGB <?= e(addslashes($row['nama_pegawai'])) ?>?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id_kgbpegawai" value="<?= (int) $row['id_kgbpegawai'] ?>">
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
  document.getElementById('kgb_terakhir').addEventListener('change', function () {
    var datangField = document.getElementById('kgb_datang');
    if (datangField.value) return; // jangan timpa kalau admin udah isi/edit manual
    var d = new Date(this.value);
    if (isNaN(d.getTime())) return;
    d.setFullYear(d.getFullYear() + 2);
    datangField.value = d.toISOString().slice(0, 10);
  });
</script>
<?php layout_footer(); ?>

<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('Admin');

$errors = [];
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['id_plh'] ?? 0);
        $detail = db_one($db, "SELECT j.nama_jabatan, p.nama_pegawai FROM plh_jabatan pl
            JOIN jabatan j ON j.id_jabatan = pl.id_jabatan
            JOIN pegawai p ON p.id_pegawai = pl.id_pegawai
            WHERE pl.id_plh = ?", [$id]);
        db_query($db, "DELETE FROM plh_jabatan WHERE id_plh = ?", [$id]);
        log_aktivitas($db, 'delete_plh', $detail ? "Hapus penugasan Plh/Plt {$detail['nama_pegawai']} utk {$detail['nama_jabatan']}" : '');
        flash_set('success', 'Penugasan Plh/Plt dihapus.');
        redirect('data_plh.php');
    } else {
        $idJabatan = (int) ($_POST['id_jabatan'] ?? 0);
        $idPegawai = (int) ($_POST['id_pegawai'] ?? 0);
        $jenis = $_POST['jenis'] ?? 'Plh';
        $mulai = $_POST['tanggal_mulai'] ?? '';
        $selesaiRaw = trim($_POST['tanggal_selesai'] ?? '');
        $selesai = $selesaiRaw === '' ? null : $selesaiRaw;
        $ket = trim($_POST['keterangan'] ?? '');
        $id = isset($_POST['id_plh']) ? (int) $_POST['id_plh'] : null;

        if ($idJabatan < 1) {
            $errors[] = 'Jabatan yang di-Plh/Plt-kan wajib dipilih.';
        }
        if ($idPegawai < 1) {
            $errors[] = 'Pegawai pelaksana wajib dipilih.';
        }
        if (!in_array($jenis, ['Plh', 'Plt'], true)) {
            $errors[] = 'Jenis penugasan tidak valid.';
        }
        if ($mulai === '' || DateTime::createFromFormat('Y-m-d', $mulai) === false) {
            $errors[] = 'Tanggal mulai tidak valid.';
        }
        if ($selesai !== null && DateTime::createFromFormat('Y-m-d', $selesai) === false) {
            $errors[] = 'Tanggal selesai tidak valid.';
        }
        if (empty($errors) && $selesai !== null && $mulai > $selesai) {
            $errors[] = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
        }

        if (empty($errors) && $action === 'create') {
            db_query($db, "INSERT INTO plh_jabatan (id_jabatan, id_pegawai, jenis, tanggal_mulai, tanggal_selesai, keterangan)
                VALUES (?,?,?,?,?,?)", [$idJabatan, $idPegawai, $jenis, $mulai, $selesai, $ket ?: null]);
            log_aktivitas($db, 'create_plh', "Tambah penugasan $jenis (jabatan #$idJabatan, pegawai #$idPegawai, mulai $mulai)");
            flash_set('success', 'Penugasan Plh/Plt ditambahkan.');
            redirect('data_plh.php');
        } elseif (empty($errors) && $action === 'update') {
            db_query($db, "UPDATE plh_jabatan SET id_jabatan=?, id_pegawai=?, jenis=?, tanggal_mulai=?, tanggal_selesai=?, keterangan=? WHERE id_plh=?",
                [$idJabatan, $idPegawai, $jenis, $mulai, $selesai, $ket ?: null, $id]);
            log_aktivitas($db, 'update_plh', "Ubah penugasan $jenis id #$id (jabatan #$idJabatan, pegawai #$idPegawai)");
            flash_set('success', 'Penugasan Plh/Plt diperbarui.');
            redirect('data_plh.php');
        }
    }
}

if (isset($_GET['edit'])) {
    $editing = db_one($db, "SELECT * FROM plh_jabatan WHERE id_plh = ?", [(int) $_GET['edit']]);
}

$list = db_all($db, "SELECT pl.*, j.nama_jabatan, p.nama_pegawai, p.nip
    FROM plh_jabatan pl
    JOIN jabatan j ON j.id_jabatan = pl.id_jabatan
    JOIN pegawai p ON p.id_pegawai = pl.id_pegawai
    ORDER BY pl.tanggal_mulai DESC");
$semuaJabatan = db_all($db, "SELECT id_jabatan, nama_jabatan FROM jabatan ORDER BY nama_jabatan ASC");
$semuaPegawai = db_all($db, "SELECT id_pegawai, nama_pegawai, nip FROM pegawai ORDER BY nama_pegawai ASC");
$success = flash_get('success');
$todayStr = date('Y-m-d');

layout_header('Data Plh/Plt', 'plh', 'admin');
?>
<h1>Data Plh/Plt</h1>
<p class="lead">Pegawai jabatan dobel / Pelaksana Harian (Plh) / Pelaksana Tugas (Plt) untuk jabatan lain. Selama periode aktif, approval cuti buat jabatan tersebut dirutekan ke pelaksana ini, bukan pemegang jabatan asli.</p>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<div class="card">
  <h2 style="font-size:1.1rem;margin:0 0 12px;"><?= $editing ? 'Edit Penugasan' : 'Tambah Penugasan' ?></h2>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
    <?php if ($editing): ?><input type="hidden" name="id_plh" value="<?= (int) $editing['id_plh'] ?>"><?php endif; ?>
    <div class="field-row">
      <div class="field">
        <label for="id_jabatan">Jabatan yang Di-Plh/Plt-kan</label>
        <select id="id_jabatan" name="id_jabatan" required>
          <option value="" disabled <?= !$editing ? 'selected' : '' ?>>-- Pilih jabatan --</option>
          <?php foreach ($semuaJabatan as $j): ?>
            <option value="<?= (int) $j['id_jabatan'] ?>" <?= (int) ($editing['id_jabatan'] ?? 0) === (int) $j['id_jabatan'] ? 'selected' : '' ?>><?= e($j['nama_jabatan']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="id_pegawai">Pegawai Pelaksana</label>
        <select id="id_pegawai" name="id_pegawai" required>
          <option value="" disabled <?= !$editing ? 'selected' : '' ?>>-- Pilih pegawai --</option>
          <?php foreach ($semuaPegawai as $p): ?>
            <option value="<?= (int) $p['id_pegawai'] ?>" <?= (int) ($editing['id_pegawai'] ?? 0) === (int) $p['id_pegawai'] ? 'selected' : '' ?>><?= e($p['nama_pegawai']) ?> (<?= e($p['nip']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="field-row">
      <div class="field">
        <label for="jenis">Jenis</label>
        <select id="jenis" name="jenis" required>
          <?php foreach (['Plh' => 'Plh - Pelaksana Harian', 'Plt' => 'Plt - Pelaksana Tugas'] as $val => $label): ?>
            <option value="<?= $val ?>" <?= ($editing['jenis'] ?? 'Plh') === $val ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"></div>
    </div>
    <div class="field-row">
      <div class="field">
        <label for="tanggal_mulai">Tanggal Mulai</label>
        <input id="tanggal_mulai" name="tanggal_mulai" type="date" required value="<?= e($editing['tanggal_mulai'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="tanggal_selesai">Tanggal Selesai (kosongkan kalau belum ditentukan)</label>
        <input id="tanggal_selesai" name="tanggal_selesai" type="date" value="<?= e($editing['tanggal_selesai'] ?? '') ?>">
      </div>
    </div>
    <div class="field">
      <label for="keterangan">Keterangan</label>
      <input id="keterangan" name="keterangan" type="text" placeholder="mis. nomor Surat Perintah Plh dari AURA" value="<?= e($editing['keterangan'] ?? '') ?>">
    </div>
    <button type="submit" class="btn-primary" style="width:auto;padding:10px 20px;margin-top:8px;"><?= $editing ? 'Simpan' : 'Tambah' ?></button>
    <?php if ($editing): ?><a href="data_plh.php" class="btn-secondary">Batal</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <table class="data-table">
    <thead><tr><th>Jabatan</th><th>Pelaksana</th><th>Jenis</th><th>Periode</th><th>Keterangan</th><th style="width:160px;">Aksi</th></tr></thead>
    <tbody>
      <?php foreach ($list as $row): ?>
        <?php
          $aktif = $row['tanggal_mulai'] <= $todayStr && ($row['tanggal_selesai'] === null || $row['tanggal_selesai'] >= $todayStr);
        ?>
        <tr>
          <td><?= e($row['nama_jabatan']) ?></td>
          <td><?= e($row['nama_pegawai']) ?></td>
          <td><span class="badge <?= $row['jenis'] === 'Plt' ? 'badge-warning' : 'badge-neutral' ?>"><?= e($row['jenis']) ?></span></td>
          <td>
            <?= e($row['tanggal_mulai']) ?> &ndash; <?= $row['tanggal_selesai'] ? e($row['tanggal_selesai']) : 'belum ditentukan' ?>
            <?php if ($aktif): ?><span class="badge badge-success">Aktif</span><?php endif; ?>
          </td>
          <td><?= $row['keterangan'] ? e($row['keterangan']) : '-' ?></td>
          <td>
            <a href="?edit=<?= (int) $row['id_plh'] ?>" class="btn-secondary" style="padding:5px 10px;">Edit</a>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus penugasan ini?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id_plh" value="<?= (int) $row['id_plh'] ?>">
              <button type="submit" class="btn-secondary" style="padding:5px 10px;">Hapus</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($list)): ?>
        <tr><td colspan="6" class="empty-state">Belum ada penugasan Plh/Plt.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php layout_footer(); ?>

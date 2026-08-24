<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('Admin');

$errors = [];
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $nama = trim($_POST['nama_golongan'] ?? '');

    if ($action === 'delete') {
        $id = (int) ($_POST['id_golongan'] ?? 0);
        $dipakai = db_one($db, "SELECT COUNT(*) AS n FROM pegawai WHERE id_golongan = ?", [$id])['n'];
        if ($dipakai > 0) {
            $errors[] = "Golongan masih dipakai $dipakai pegawai, tidak bisa dihapus.";
        } else {
            db_query($db, "DELETE FROM golongan WHERE id_golongan = ?", [$id]);
            flash_set('success', 'Golongan dihapus.');
            redirect('data_golongan.php');
        }
    } else {
        if ($nama === '') {
            $errors[] = 'Nama golongan wajib diisi.';
        }
        if (empty($errors) && $action === 'create') {
            db_query($db, "INSERT INTO golongan (nama_golongan) VALUES (?)", [$nama]);
            flash_set('success', 'Golongan ditambahkan.');
            redirect('data_golongan.php');
        } elseif (empty($errors) && $action === 'update') {
            $id = (int) ($_POST['id_golongan'] ?? 0);
            db_query($db, "UPDATE golongan SET nama_golongan = ? WHERE id_golongan = ?", [$nama, $id]);
            flash_set('success', 'Golongan diperbarui.');
            redirect('data_golongan.php');
        }
    }
}

if (isset($_GET['edit'])) {
    $editing = db_one($db, "SELECT * FROM golongan WHERE id_golongan = ?", [(int) $_GET['edit']]);
}

$list = db_all($db, "SELECT * FROM golongan ORDER BY id_golongan ASC");
$success = flash_get('success');

layout_header('Data Golongan', 'golongan', 'admin');
?>
<h1>Data Golongan</h1>
<p class="lead">Daftar golongan/pangkat pegawai.</p>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<div class="card">
  <h2 style="font-size:1.1rem;margin:0 0 12px;"><?= $editing ? 'Edit Golongan' : 'Tambah Golongan' ?></h2>
  <form method="POST" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
    <?php if ($editing): ?><input type="hidden" name="id_golongan" value="<?= (int) $editing['id_golongan'] ?>"><?php endif; ?>
    <div class="field" style="margin:0; flex:1; min-width:200px;">
      <label for="nama_golongan">Nama Golongan</label>
      <input id="nama_golongan" name="nama_golongan" type="text" required value="<?= e($editing['nama_golongan'] ?? '') ?>">
    </div>
    <button type="submit" class="btn-primary" style="width:auto;padding:10px 20px;"><?= $editing ? 'Simpan' : 'Tambah' ?></button>
    <?php if ($editing): ?><a href="data_golongan.php" class="btn-secondary">Batal</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <table class="data-table">
    <thead><tr><th>Nama Golongan</th><th style="width:160px;">Aksi</th></tr></thead>
    <tbody>
      <?php foreach ($list as $row): ?>
        <tr>
          <td><?= e($row['nama_golongan']) ?></td>
          <td>
            <a href="?edit=<?= (int) $row['id_golongan'] ?>" class="btn-secondary" style="padding:5px 10px;">Edit</a>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus golongan <?= e(addslashes($row['nama_golongan'])) ?>?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id_golongan" value="<?= (int) $row['id_golongan'] ?>">
              <button type="submit" class="btn-secondary" style="padding:5px 10px;">Hapus</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php layout_footer(); ?>

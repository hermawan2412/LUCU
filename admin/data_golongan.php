<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('Admin');

$errors = [];
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $nama = trim($_POST['nama_golongan'] ?? '');
    $jenisAsn = in_array($_POST['jenis_asn'] ?? '', ['PNS', 'PPPK'], true) ? $_POST['jenis_asn'] : 'PNS';

    if ($action === 'delete') {
        $id = (int) ($_POST['id_golongan'] ?? 0);
        $dipakaiPegawai = db_one($db, "SELECT COUNT(*) AS n FROM pegawai WHERE id_golongan = ?", [$id])['n'];
        $dipakaiKnp = db_one($db, "SELECT COUNT(*) AS n FROM knp_pegawai WHERE id_golongan_tujuan = ?", [$id])['n'];
        if ($dipakaiPegawai > 0) {
            $errors[] = "Golongan masih dipakai $dipakaiPegawai pegawai, tidak bisa dihapus.";
        } elseif ($dipakaiKnp > 0) {
            $errors[] = "Golongan masih jadi tujuan $dipakaiKnp catatan KNP, tidak bisa dihapus.";
        } else {
            try {
                db_query($db, "DELETE FROM golongan WHERE id_golongan = ?", [$id]);
                flash_set('success', 'Golongan dihapus.');
                redirect('data_golongan.php');
            } catch (PDOException $e) {
                error_log('Gagal hapus golongan: ' . $e->getMessage());
                $errors[] = 'Golongan masih direferensikan data lain, tidak bisa dihapus.';
            }
        }
    } else {
        if ($nama === '') {
            $errors[] = 'Nama golongan wajib diisi.';
        }
        if (empty($errors) && $action === 'create') {
            db_query($db, "INSERT INTO golongan (nama_golongan, jenis_asn) VALUES (?, ?)", [$nama, $jenisAsn]);
            flash_set('success', 'Golongan ditambahkan.');
            redirect('data_golongan.php');
        } elseif (empty($errors) && $action === 'update') {
            $id = (int) ($_POST['id_golongan'] ?? 0);
            db_query($db, "UPDATE golongan SET nama_golongan = ?, jenis_asn = ? WHERE id_golongan = ?", [$nama, $jenisAsn, $id]);
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
    <div class="field" style="margin:0; flex:1; min-width:160px;">
      <label for="nama_golongan">Nama Golongan</label>
      <input id="nama_golongan" name="nama_golongan" type="text" required value="<?= e($editing['nama_golongan'] ?? '') ?>">
    </div>
    <div class="field" style="margin:0; min-width:140px;">
      <label for="jenis_asn">Jenis ASN</label>
      <select id="jenis_asn" name="jenis_asn">
        <option value="PNS" <?= ($editing['jenis_asn'] ?? 'PNS') === 'PNS' ? 'selected' : '' ?>>PNS</option>
        <option value="PPPK" <?= ($editing['jenis_asn'] ?? '') === 'PPPK' ? 'selected' : '' ?>>PPPK</option>
      </select>
    </div>
    <button type="submit" class="btn-primary" style="width:auto;padding:10px 20px;"><?= $editing ? 'Simpan' : 'Tambah' ?></button>
    <?php if ($editing): ?><a href="data_golongan.php" class="btn-secondary">Batal</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <table class="data-table">
    <thead><tr><th>Nama Golongan</th><th>Jenis ASN</th><th style="width:160px;">Aksi</th></tr></thead>
    <tbody>
      <?php foreach ($list as $row): ?>
        <tr>
          <td><?= e($row['nama_golongan']) ?></td>
          <td><span class="badge <?= $row['jenis_asn'] === 'PPPK' ? 'badge-warning' : 'badge-neutral' ?>"><?= e($row['jenis_asn']) ?></span></td>
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

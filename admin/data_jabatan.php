<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('Admin');

/**
 * Cegah siklus: pastikan $proposedAtasan bukan diri sendiri dan bukan
 * turunan dari $idJabatan sendiri (kalau iya, rantai approval bakal
 * infinite loop / gak pernah nyampe Ketua).
 */
function jabatan_cek_siklus(PDO $db, ?int $idJabatan, ?int $proposedAtasan): bool
{
    if ($proposedAtasan === null) {
        return true;
    }
    if ($idJabatan !== null && $proposedAtasan === $idJabatan) {
        return false;
    }
    $current = $proposedAtasan;
    $guard = 0;
    while ($current !== null && $guard++ < 20) {
        if ($current === $idJabatan) {
            return false;
        }
        $row = db_one($db, "SELECT id_atasan FROM jabatan WHERE id_jabatan = ?", [$current]);
        $current = $row ? $row['id_atasan'] : null;
    }
    return true;
}

$errors = [];
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['id_jabatan'] ?? 0);
        $dipakaiPegawai = db_one($db, "SELECT COUNT(*) AS n FROM pegawai WHERE id_jabatan = ?", [$id])['n'];
        $dipakaiAtasan = db_one($db, "SELECT COUNT(*) AS n FROM jabatan WHERE id_atasan = ?", [$id])['n'];
        $isPejabatPppk = db_one($db, "SELECT is_pejabat_pppk FROM jabatan WHERE id_jabatan = ?", [$id]);

        if ($dipakaiPegawai > 0) {
            $errors[] = "Jabatan masih dipakai $dipakaiPegawai pegawai, tidak bisa dihapus.";
        } elseif ($dipakaiAtasan > 0) {
            $errors[] = "Jabatan ini masih jadi atasan $dipakaiAtasan jabatan lain, pindahkan dulu.";
        } elseif ($isPejabatPppk && (int) $isPejabatPppk['is_pejabat_pppk'] === 1) {
            $errors[] = 'Jabatan ini ditandai sebagai pemberi izin cuti akhir PPPK, pindahkan tandanya ke jabatan lain dulu sebelum dihapus.';
        } else {
            db_query($db, "DELETE FROM jabatan WHERE id_jabatan = ?", [$id]);
            flash_set('success', 'Jabatan dihapus.');
            redirect('data_jabatan.php');
        }
    } else {
        $nama = trim($_POST['nama_jabatan'] ?? '');
        $atasanRaw = $_POST['id_atasan'] ?? '';
        $idAtasan = $atasanRaw === '' ? null : (int) $atasanRaw;
        $isPejabatPppk = isset($_POST['is_pejabat_pppk']) ? 1 : 0;
        $id = isset($_POST['id_jabatan']) ? (int) $_POST['id_jabatan'] : null;

        if ($nama === '') {
            $errors[] = 'Nama jabatan wajib diisi.';
        }
        if (empty($errors) && !jabatan_cek_siklus($db, $id, $idAtasan)) {
            $errors[] = 'Atasan tidak valid: bikin rantai approval muter balik ke jabatan ini sendiri.';
        }

        if (empty($errors) && $action === 'create') {
            if ($isPejabatPppk) {
                db_query($db, "UPDATE jabatan SET is_pejabat_pppk = 0");
            }
            db_query($db, "INSERT INTO jabatan (nama_jabatan, id_atasan, is_pejabat_pppk) VALUES (?, ?, ?)", [$nama, $idAtasan, $isPejabatPppk]);
            flash_set('success', 'Jabatan ditambahkan.');
            redirect('data_jabatan.php');
        } elseif (empty($errors) && $action === 'update') {
            if ($isPejabatPppk) {
                db_query($db, "UPDATE jabatan SET is_pejabat_pppk = 0 WHERE id_jabatan != ?", [$id]);
            }
            db_query($db, "UPDATE jabatan SET nama_jabatan = ?, id_atasan = ?, is_pejabat_pppk = ? WHERE id_jabatan = ?", [$nama, $idAtasan, $isPejabatPppk, $id]);
            flash_set('success', 'Jabatan diperbarui.');
            redirect('data_jabatan.php');
        }
    }
}

if (isset($_GET['edit'])) {
    $editing = db_one($db, "SELECT * FROM jabatan WHERE id_jabatan = ?", [(int) $_GET['edit']]);
}

$list = db_all($db, "SELECT j.*, a.nama_jabatan AS nama_atasan
    FROM jabatan j LEFT JOIN jabatan a ON a.id_jabatan = j.id_atasan
    ORDER BY j.id_jabatan ASC");
$semuaJabatan = db_all($db, "SELECT id_jabatan, nama_jabatan FROM jabatan ORDER BY nama_jabatan ASC");
$success = flash_get('success');

layout_header('Data Jabatan', 'jabatan', 'admin');
?>
<h1>Data Jabatan</h1>
<p class="lead">Hierarki jabatan menentukan rute approval cuti otomatis (lihat kolom Atasan).</p>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<div class="card">
  <h2 style="font-size:1.1rem;margin:0 0 12px;"><?= $editing ? 'Edit Jabatan' : 'Tambah Jabatan' ?></h2>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
    <?php if ($editing): ?><input type="hidden" name="id_jabatan" value="<?= (int) $editing['id_jabatan'] ?>"><?php endif; ?>
    <div class="field-row">
      <div class="field">
        <label for="nama_jabatan">Nama Jabatan</label>
        <input id="nama_jabatan" name="nama_jabatan" type="text" required value="<?= e($editing['nama_jabatan'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="id_atasan">Atasan (approval cuti)</label>
        <select id="id_atasan" name="id_atasan">
          <option value="">-- Puncak, tanpa atasan (auto-approve) --</option>
          <?php foreach ($semuaJabatan as $j): ?>
            <?php if ($editing && (int) $j['id_jabatan'] === (int) $editing['id_jabatan']) continue; ?>
            <option value="<?= (int) $j['id_jabatan'] ?>" <?= (int) ($editing['id_atasan'] ?? 0) === (int) $j['id_jabatan'] ? 'selected' : '' ?>><?= e($j['nama_jabatan']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="field" style="margin-top:4px;">
      <label style="display:flex; align-items:center; gap:8px; font-weight:400;">
        <input type="checkbox" name="is_pejabat_pppk" value="1" style="width:auto;" <?= !empty($editing['is_pejabat_pppk']) ? 'checked' : '' ?>>
        Jadikan pemberi izin cuti akhir untuk pegawai PPPK
      </label>
      <p class="hint">Cuma boleh 1 jabatan yang ditandai; centang di sini otomatis lepas tanda dari jabatan lain.</p>
    </div>
    <button type="submit" class="btn-primary" style="width:auto;padding:10px 20px;margin-top:8px;"><?= $editing ? 'Simpan' : 'Tambah' ?></button>
    <?php if ($editing): ?><a href="data_jabatan.php" class="btn-secondary">Batal</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <table class="data-table">
    <thead><tr><th>Nama Jabatan</th><th>Atasan</th><th>Peran</th><th style="width:160px;">Aksi</th></tr></thead>
    <tbody>
      <?php foreach ($list as $row): ?>
        <tr>
          <td><?= e($row['nama_jabatan']) ?></td>
          <td><?= $row['nama_atasan'] ? e($row['nama_atasan']) : '<span class="badge badge-neutral">Puncak</span>' ?></td>
          <td><?= $row['is_pejabat_pppk'] ? '<span class="badge badge-warning">Pejabat PPPK</span>' : '' ?></td>
          <td>
            <a href="?edit=<?= (int) $row['id_jabatan'] ?>" class="btn-secondary" style="padding:5px 10px;">Edit</a>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus jabatan <?= e(addslashes($row['nama_jabatan'])) ?>?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id_jabatan" value="<?= (int) $row['id_jabatan'] ?>">
              <button type="submit" class="btn-secondary" style="padding:5px 10px;">Hapus</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php layout_footer(); ?>

<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('Admin');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $idPegawai = (int) ($_POST['id_pegawai'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $role = in_array($_POST['role'] ?? '', ['Admin', 'User'], true) ? $_POST['role'] : 'User';

        $pegawai = db_one($db, "SELECT * FROM pegawai WHERE id_pegawai = ?", [$idPegawai]);

        if ($pegawai === null) $errors[] = 'Pegawai tidak ditemukan.';
        if ($username === '' || !preg_match('/^[a-z0-9._-]{3,50}$/i', $username)) {
            $errors[] = 'Username 3-50 karakter, huruf/angka/titik/strip aja.';
        }
        if (strlen($password) < 6) $errors[] = 'Kata sandi minimal 6 karakter.';

        if (empty($errors)) {
            $sudahAda = db_one($db, "SELECT 1 FROM user WHERE username = ?", [$username]);
            if ($sudahAda) {
                $errors[] = "Username \"$username\" sudah dipakai.";
            } else {
                db_query($db, "INSERT INTO user (username, nip, password, role) VALUES (?, ?, ?, ?)",
                    [$username, $pegawai['nip'], password_hash($password, PASSWORD_BCRYPT), $role]);
                log_aktivitas($db, 'create_akun', "Buat akun \"$username\" ($role) buat {$pegawai['nama_pegawai']}");
                flash_set('success', "Akun \"$username\" dibuat buat {$pegawai['nama_pegawai']}.");
                redirect('data_user.php');
            }
        }
    } elseif ($action === 'reset_password') {
        $idUser = (int) ($_POST['id_user'] ?? 0);
        $password = (string) ($_POST['password'] ?? '');
        if (strlen($password) < 6) {
            $errors[] = 'Kata sandi minimal 6 karakter.';
        } else {
            $targetUsername = db_one($db, "SELECT username FROM user WHERE id_user = ?", [$idUser])['username'] ?? "#$idUser";
            db_query($db, "UPDATE user SET password = ? WHERE id_user = ?", [password_hash($password, PASSWORD_BCRYPT), $idUser]);
            log_aktivitas($db, 'reset_password', "Reset kata sandi akun \"$targetUsername\"");
            flash_set('success', 'Kata sandi direset.');
            redirect('data_user.php');
        }
    } elseif ($action === 'delete') {
        $idUser = (int) ($_POST['id_user'] ?? 0);
        if ((int) $idUser === (int) ($_SESSION['id_user'] ?? 0)) {
            $errors[] = 'Gak bisa hapus akun sendiri yang lagi dipakai login.';
        } else {
            $targetUsername = db_one($db, "SELECT username FROM user WHERE id_user = ?", [$idUser])['username'] ?? "#$idUser";
            db_query($db, "DELETE FROM user WHERE id_user = ?", [$idUser]);
            log_aktivitas($db, 'delete_akun', "Hapus akun \"$targetUsername\"");
            flash_set('success', 'Akun dihapus.');
            redirect('data_user.php');
        }
    }
}

$akunList = db_all($db, "SELECT u.id_user, u.username, u.role, u.nip, p.nama_pegawai, p.id_pegawai
    FROM user u LEFT JOIN pegawai p ON p.nip = u.nip
    ORDER BY p.nama_pegawai IS NULL, p.nama_pegawai ASC, u.username ASC");

$pegawaiTanpaAkun = db_all($db, "SELECT p.id_pegawai, p.nama_pegawai, p.nip
    FROM pegawai p
    WHERE NOT EXISTS (SELECT 1 FROM user u WHERE u.nip = p.nip)
    ORDER BY p.nama_pegawai ASC");

$success = flash_get('success');

layout_header('Kelola Akun', '', 'admin');
?>
<h1>Kelola Akun</h1>
<p class="lead">Bikin akun login buat pegawai yang belum bisa masuk, atau reset kata sandi akun yang ada.</p>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<div class="card">
  <h2 style="margin:0 0 4px;">Buat Akun Baru</h2>
  <p class="lead" style="margin-bottom:16px;"><?= count($pegawaiTanpaAkun) ?> pegawai belum punya akun login.</p>

  <?php if (empty($pegawaiTanpaAkun)): ?>
    <div class="empty-state">Semua pegawai sudah punya akun.</div>
  <?php else: ?>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <div class="field">
        <label for="id_pegawai">Pegawai</label>
        <select id="id_pegawai" name="id_pegawai" required>
          <option value="" disabled selected>-- Pilih pegawai --</option>
          <?php foreach ($pegawaiTanpaAkun as $p): ?>
            <option value="<?= (int) $p['id_pegawai'] ?>"><?= e($p['nama_pegawai']) ?> &middot; <?= e($p['nip']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field-row">
        <div class="field">
          <label for="username">Username</label>
          <input id="username" name="username" type="text" required placeholder="mis. nama.depan">
        </div>
        <div class="field">
          <label for="password">Kata Sandi Awal</label>
          <input id="password" name="password" type="text" required minlength="6" placeholder="min. 6 karakter">
          <p class="hint">Pegawai pakai ini buat login pertama kali. Belum ada fitur ganti sandi mandiri.</p>
        </div>
      </div>
      <div class="field">
        <label for="role">Role</label>
        <select id="role" name="role">
          <option value="User" selected>User (pegawai biasa)</option>
          <option value="Admin">Admin</option>
        </select>
      </div>
      <button type="submit" class="btn-primary" style="width:auto;padding:12px 24px;">Buat Akun</button>
    </form>
  <?php endif; ?>
</div>

<div class="card">
  <h2 style="margin:0 0 16px;">Akun Aktif</h2>
  <div class="table-scroll">
    <table class="data-table">
      <thead><tr><th>Username</th><th>Nama Pegawai</th><th>Role</th><th style="width:220px;">Aksi</th></tr></thead>
      <tbody>
        <?php foreach ($akunList as $a): ?>
          <tr>
            <td><?= e($a['username']) ?></td>
            <td><?= $a['nama_pegawai'] ? e($a['nama_pegawai']) : '<span class="hint">tidak terhubung ke data pegawai</span>' ?></td>
            <td><span class="badge <?= $a['role'] === 'Admin' ? 'badge-warning' : 'badge-neutral' ?>"><?= e($a['role']) ?></span></td>
            <td>
              <details style="display:inline-block;">
                <summary class="btn-secondary" style="padding:5px 10px; cursor:pointer; display:inline-block;">Reset Sandi</summary>
                <form method="POST" style="margin-top:8px; display:flex; gap:6px;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="reset_password">
                  <input type="hidden" name="id_user" value="<?= (int) $a['id_user'] ?>">
                  <input type="text" name="password" placeholder="sandi baru" required minlength="6" style="padding:6px 8px;border:1px solid var(--border-strong);border-radius:8px;font-size:0.85rem;">
                  <button type="submit" class="btn-secondary" style="padding:6px 12px;">Set</button>
                </form>
              </details>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus akun <?= e(addslashes($a['username'])) ?>?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id_user" value="<?= (int) $a['id_user'] ?>">
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

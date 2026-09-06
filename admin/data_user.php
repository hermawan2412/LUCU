<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('Admin', 'Pengelola');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $idPegawai = (int) ($_POST['id_pegawai'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        // Jangan percaya role dari POST mentah - Pengelola gak boleh bikin
        // akun Admin (juga gak buat akun sendiri), termasuk kalau field
        // dropdown-nya di-manipulasi manual di request. Lihat auth_assignable_roles().
        $role = in_array($_POST['role'] ?? '', auth_assignable_roles(), true) ? $_POST['role'] : 'User';

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
        $target = db_one($db, "SELECT username, role FROM user WHERE id_user = ?", [$idUser]);
        // Reset password akun Admin = ambil alih akun itu (login pake sandi
        // baru) - jalan pintas buat celah yang sama kayak "gak bisa bikin
        // akun Admin", jadi diblok cara yang sama: cuma Admin yang boleh.
        if ($target !== null && $target['role'] === 'Admin' && $_SESSION['role'] !== 'Admin') {
            $errors[] = 'Cuma Admin yang boleh reset kata sandi akun Admin.';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Kata sandi minimal 6 karakter.';
        } else {
            db_query($db, "UPDATE user SET password = ? WHERE id_user = ?", [password_hash($password, PASSWORD_BCRYPT), $idUser]);
            log_aktivitas($db, 'reset_password', "Reset kata sandi akun \"" . ($target['username'] ?? "#$idUser") . "\"");
            flash_set('success', 'Kata sandi direset.');
            redirect('data_user.php');
        }
    } elseif ($action === 'ubah_role') {
        $idUser = (int) ($_POST['id_user'] ?? 0);
        $roleBaru = $_POST['role'] ?? '';
        $target = db_one($db, "SELECT username, role FROM user WHERE id_user = ?", [$idUser]);

        if ((int) $idUser === (int) ($_SESSION['id_user'] ?? 0)) {
            $errors[] = 'Gak bisa ubah role akun sendiri yang lagi dipakai login (risiko kekunci - satu-satunya Admin misalnya).';
        } elseif ($target === null) {
            $errors[] = 'Akun tidak ditemukan.';
        } elseif ($target['role'] === 'Admin' && $_SESSION['role'] !== 'Admin') {
            $errors[] = 'Cuma Admin yang boleh ubah role akun Admin.';
        } elseif (!in_array($roleBaru, auth_assignable_roles(), true)) {
            $errors[] = 'Role tidak valid.';
        } else {
            db_query($db, "UPDATE user SET role = ? WHERE id_user = ?", [$roleBaru, $idUser]);
            log_aktivitas($db, 'ubah_role', "Ubah role akun \"{$target['username']}\" dari \"{$target['role']}\" jadi \"$roleBaru\"");
            flash_set('success', "Role akun \"{$target['username']}\" diubah jadi $roleBaru.");
            redirect('data_user.php');
        }
    } elseif ($action === 'delete') {
        $idUser = (int) ($_POST['id_user'] ?? 0);
        $target = db_one($db, "SELECT username, role FROM user WHERE id_user = ?", [$idUser]);
        if ((int) $idUser === (int) ($_SESSION['id_user'] ?? 0)) {
            $errors[] = 'Gak bisa hapus akun sendiri yang lagi dipakai login.';
        } elseif ($target !== null && $target['role'] === 'Admin' && $_SESSION['role'] !== 'Admin') {
            $errors[] = 'Cuma Admin yang boleh hapus akun Admin.';
        } else {
            db_query($db, "DELETE FROM user WHERE id_user = ?", [$idUser]);
            log_aktivitas($db, 'delete_akun', "Hapus akun \"" . ($target['username'] ?? "#$idUser") . "\"");
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
<p class="lead">Bikin akun login buat pegawai yang belum bisa masuk, ubah role, atau reset kata sandi akun yang ada.</p>

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
          <?php $roleLabel = ['User' => 'User (pegawai biasa)', 'Pengelola' => 'Pengelola (staf kepegawaian)', 'Admin' => 'Admin']; ?>
          <?php foreach (auth_assignable_roles() as $r): ?>
            <option value="<?= $r ?>" <?= $r === 'User' ? 'selected' : '' ?>><?= e($roleLabel[$r]) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if ($_SESSION['role'] !== 'Admin'): ?>
          <p class="hint">Akun Admin cuma bisa dibuat oleh Admin.</p>
        <?php endif; ?>
      </div>
      <button type="submit" class="btn-primary" style="width:auto;padding:12px 24px;">Buat Akun</button>
    </form>
  <?php endif; ?>
</div>

<div class="card">
  <h2 style="margin:0 0 16px;">Akun Aktif</h2>
  <div class="table-scroll">
    <table class="data-table">
      <thead><tr><th>Username</th><th>Nama Pegawai</th><th>Role</th><th style="width:320px;">Aksi</th></tr></thead>
      <tbody>
        <?php foreach ($akunList as $a): ?>
          <tr>
            <td><?= e($a['username']) ?></td>
            <td><?= $a['nama_pegawai'] ? e($a['nama_pegawai']) : '<span class="hint">tidak terhubung ke data pegawai</span>' ?></td>
            <?php $roleBadge = ['Admin' => 'badge-warning', 'Pengelola' => 'badge-neutral', 'User' => 'badge-success']; ?>
            <td><span class="badge <?= $roleBadge[$a['role']] ?? 'badge-neutral' ?>"><?= e($a['role']) ?></span></td>
            <td>
              <?php $bisaUbahRole = (int) $a['id_user'] !== (int) ($_SESSION['id_user'] ?? 0) && !($a['role'] === 'Admin' && $_SESSION['role'] !== 'Admin'); ?>
              <?php if ($bisaUbahRole): ?>
                <details style="display:inline-block;">
                  <summary class="btn-secondary" style="padding:5px 10px; cursor:pointer; display:inline-block;">Ubah Role</summary>
                  <form method="POST" style="margin-top:8px; display:flex; gap:6px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="ubah_role">
                    <input type="hidden" name="id_user" value="<?= (int) $a['id_user'] ?>">
                    <select name="role" style="padding:6px 8px;border:1px solid var(--border-strong);border-radius:8px;font-size:0.85rem;">
                      <?php foreach (auth_assignable_roles() as $r): ?>
                        <option value="<?= $r ?>" <?= $r === $a['role'] ? 'selected' : '' ?>><?= e($roleLabel[$r]) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-secondary" style="padding:6px 12px;">Ubah</button>
                  </form>
                </details>
              <?php endif; ?>
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

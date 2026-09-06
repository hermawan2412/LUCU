<?php
// Auth: password_hash/password_verify (ganti MD5 tanpa salt di MACOA).

declare(strict_types=1);

const AUTH_MAX_ATTEMPTS = 5;
const AUTH_LOCKOUT_MINUTES = 15;

/**
 * null = username/password salah ATAU akun lagi dikunci.
 * Pesan ke user sengaja sama (lihat check_login.php) biar gak bocorin
 * status akun ke penyerang.
 */
function auth_attempt(PDO $db, string $username, string $password): ?array
{
    $user = db_one($db, "SELECT * FROM user WHERE username = ? LIMIT 1", [$username]);
    if ($user === null) {
        return null;
    }

    if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
        return null; // masih dikunci, gak usah cek password lagi
    }

    if (!password_verify($password, $user['password'])) {
        auth_register_failed_attempt($db, $user);
        return null;
    }

    if ((int) $user['failed_attempts'] > 0 || $user['locked_until'] !== null) {
        db_query($db, "UPDATE user SET failed_attempts = 0, locked_until = NULL WHERE id_user = ?", [$user['id_user']]);
    }

    return $user;
}

function auth_register_failed_attempt(PDO $db, array $user): void
{
    $attempts = (int) $user['failed_attempts'] + 1;
    $lockedUntil = $attempts >= AUTH_MAX_ATTEMPTS
        ? date('Y-m-d H:i:s', time() + AUTH_LOCKOUT_MINUTES * 60)
        : null;

    db_query(
        $db,
        "UPDATE user SET failed_attempts = ?, locked_until = ? WHERE id_user = ?",
        [$attempts, $lockedUntil, $user['id_user']]
    );
}

function auth_login(array $user): void
{
    session_regenerate_id(true); // cegah session fixation
    $_SESSION['username'] = $user['username'];
    $_SESSION['nip'] = $user['nip'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['id_user'] = $user['id_user'];
}

function auth_logout(): void
{
    $_SESSION = [];
    session_destroy();
}

function auth_check(): bool
{
    return isset($_SESSION['username'], $_SESSION['role']);
}

function auth_require(string ...$roles): void
{
    if (!auth_check() || !in_array($_SESSION['role'], $roles, true)) {
        header('Location: /index.php');
        exit;
    }
}

/**
 * Role yang boleh DIPILIH/DIBUAT lewat admin/data_user.php, tergantung
 * siapa yang lagi bikin akunnya - bukan cuma UI dropdown, dipakai juga
 * buat validasi server-side (jangan percaya nilai role dari POST mentah).
 * Cuma Admin yang boleh bikin akun Admin baru - Pengelola gak bisa naikin
 * akun manapun (termasuk punya sendiri) jadi Admin.
 */
function auth_assignable_roles(): array
{
    return $_SESSION['role'] === 'Admin' ? ['Admin', 'Pengelola', 'User'] : ['Pengelola', 'User'];
}

/**
 * Role yang punya akses area admin/*.php (Admin & Pengelola) - dipakai di
 * tempat yang butuh cek "bukan auth_require() 1 halaman" tapi keputusan
 * routing/kepemilikan lintas role, mis. index.php abis login & cetak_cuti.php
 * (staf kepegawaian jg boleh cetak dokumen siapa aja, bukan cuma Admin).
 */
function auth_is_staff(): bool
{
    return in_array($_SESSION['role'] ?? null, ['Admin', 'Pengelola'], true);
}

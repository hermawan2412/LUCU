<?php
// Auth: password_hash/password_verify (ganti MD5 tanpa salt di MACOA).

declare(strict_types=1);

function auth_attempt(PDO $db, string $username, string $password): ?array
{
    $user = db_one($db, "SELECT * FROM user WHERE username = ? LIMIT 1", [$username]);
    if ($user === null) {
        return null;
    }
    if (!password_verify($password, $user['password'])) {
        return null;
    }
    return $user;
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

function auth_require(string $role): void
{
    if (!auth_check() || $_SESSION['role'] !== $role) {
        header('Location: /index.php');
        exit;
    }
}

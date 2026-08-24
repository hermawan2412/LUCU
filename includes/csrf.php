<?php
// CSRF token per session. MACOA tidak punya ini sama sekali.

declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

function csrf_verify(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
        http_response_code(403);
        die('Sesi tidak valid, muat ulang halaman dan coba lagi.');
    }
}

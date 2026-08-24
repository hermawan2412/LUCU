<?php
// Helper umum. e() dipakai buat escape semua output ke HTML (perbaikan dari
// MACOA yang echo langsung tanpa htmlspecialchars di banyak tempat -> celah XSS).

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function indonesia_tgl(string $tanggal): string
{
    $namaBulan = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
        '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
        '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
    ];
    $tgl = substr($tanggal, 8, 2);
    $bln = substr($tanggal, 5, 2);
    $thn = substr($tanggal, 0, 4);
    return "$tgl {$namaBulan[$bln]} $thn";
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function flash_set(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

function flash_get(string $key): ?string
{
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

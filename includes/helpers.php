<?php
// Helper umum. e() dipakai buat escape semua output ke HTML (perbaikan dari
// MACOA yang echo langsung tanpa htmlspecialchars di banyak tempat -> celah XSS).

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Brand mark: pakai logo asli (diupload admin lewat Pengaturan) kalau
 * ada, else fallback SVG perisai + centang (kesan resmi/disetujui,
 * cocok buat aplikasi approval cuti). $assetPrefix: '' dari root
 * (index.php), '../' dari dalam folder admin/ atau user/.
 */
function brand_mark_svg(int $size = 26, string $assetPrefix = ''): string
{
    if (defined('APP_LOGO_PATH') && APP_LOGO_PATH) {
        $fsPath = __DIR__ . '/../assets/img/' . basename(APP_LOGO_PATH);
        // Nama file logo tetap ("logo.png") walau gantiin file lama - tanpa
        // cache-buster browser bisa nampilin logo LAMA dari cache abis
        // admin ganti logo baru. mtime file sbg query string, gratis & akurat.
        $versi = is_file($fsPath) ? '?v=' . filemtime($fsPath) : '';
        $src = e($assetPrefix . 'assets/img/' . APP_LOGO_PATH) . $versi;
        return "<img src=\"$src\" width=\"$size\" height=\"$size\" alt=\"\" style=\"object-fit:contain;\">";
    }

    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path d="M12 2.5L19.5 5.5V11C19.5 15.5 16.5 19 12 21C7.5 19 4.5 15.5 4.5 11V5.5L12 2.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
        <path d="M8.5 11.5L10.8 13.8L15.5 9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>';
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

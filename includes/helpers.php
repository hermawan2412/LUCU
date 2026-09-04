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

/**
 * Logo instansi buat halaman login - gantiin badge teks nama instansi kalau
 * admin udah upload (admin/pengaturan.php). $size disamain dgn brand_mark_svg()
 * app biar dua logo keliatan sama besar (permintaan eksplisit user).
 * Fallback: badge teks + titik oranye yg SUDAH ada dari awal (bukan fitur baru,
 * cuma dipindah ke sini biar 1 titik keputusan "ada logo atau enggak").
 */
function logo_instansi_html(int $size = 64, string $assetPrefix = ''): string
{
    if (defined('APP_LOGO_INSTANSI_PATH') && APP_LOGO_INSTANSI_PATH) {
        $fsPath = __DIR__ . '/../assets/img/' . basename(APP_LOGO_INSTANSI_PATH);
        $versi = is_file($fsPath) ? '?v=' . filemtime($fsPath) : '';
        $src = e($assetPrefix . 'assets/img/' . APP_LOGO_INSTANSI_PATH) . $versi;
        return "<img src=\"$src\" width=\"$size\" height=\"$size\" alt=\"\" style=\"object-fit:contain;\">";
    }

    return '<span class="badge">' . e(APP_INSTANSI) . '</span>';
}

/**
 * Thumbnail kecil (maks $maxBox x $maxBox, di-resize proporsional) dari file
 * logo admin-upload manapun - dipakai og_image_url() (og:image) dan
 * favicon_url() (tab browser). JANGAN pernah pakai file logo asli langsung
 * di konteks itu, logo upload admin bisa >1MB resolusi tinggi apa adanya.
 * Di-cache ke disk (nama file dari mtime sumber, otomatis regenerate kalau
 * admin ganti logo) biar gak resize ulang tiap request. Return null kalau
 * sumbernya belum di-set atau filenya rusak/gak kebaca GD.
 */
function resize_thumb_cache(?string $sumberPath, string $prefixCache, int $maxBox, string $assetPrefix = ''): ?string
{
    if (!$sumberPath) {
        return null;
    }
    $srcPath = __DIR__ . '/../assets/img/' . basename($sumberPath);
    if (!is_file($srcPath)) {
        return null;
    }

    $cacheName = $prefixCache . '_' . md5($srcPath) . '_' . filemtime($srcPath) . '.png';
    $cachePath = __DIR__ . '/../assets/img/' . $cacheName;
    if (!is_file($cachePath)) {
        $src = @imagecreatefromstring((string) file_get_contents($srcPath));
        if ($src === false) {
            return null;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $skala = min(1.0, $maxBox / max($w, $h)); // cuma diperkecil, gak pernah diperbesar
        $dw = max(1, (int) round($w * $skala));
        $dh = max(1, (int) round($h * $skala));
        $dst = imagecreatetruecolor($dw, $dh);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dw, $dh, $w, $h);
        imagepng($dst, $cachePath);
        // imagedestroy() gak dipanggil - no-op sejak PHP 8.0, GC yg beresin.
    }

    return e($assetPrefix . 'assets/img/' . $cacheName);
}

function og_image_url(string $assetPrefix = ''): ?string
{
    $sumber = defined('APP_LOGO_INSTANSI_PATH') ? APP_LOGO_INSTANSI_PATH : null;
    return resize_thumb_cache($sumber, 'og', 300, $assetPrefix);
}

/** Favicon tab browser - pakai logo APLIKASI (bukan logo instansi), mark-only. */
function favicon_url(string $assetPrefix = ''): ?string
{
    $sumber = defined('APP_LOGO_PATH') ? APP_LOGO_PATH : null;
    return resize_thumb_cache($sumber, 'favicon', 64, $assetPrefix);
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

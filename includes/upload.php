<?php
// Upload gambar tanda tangan pegawai (dipakai user/profil.php buat upload
// sendiri, admin/data_pegawai.php buat admin upload-in). 1 fungsi dipakai
// dari 2 tempat - bukan cuma bisa salah satu.

declare(strict_types=1);

const UPLOAD_TTD_MAX_BYTES = 1_000_000; // 1MB, cukup buat gambar tanda tangan
const UPLOAD_TTD_MIME_EXT = ['image/png' => 'png', 'image/jpeg' => 'jpg'];

/**
 * $file: 1 entri $_FILES (mis. $_FILES['tanda_tangan']).
 * Return ['ok'=>bool, 'error'=>?string, 'filename'=>?string].
 * $filename kalau sukses itu nama file di uploads/ttd/ (bukan path penuh) -
 * caller yang nyimpen ke kolom pegawai.tanda_tangan_path.
 */
function upload_tanda_tangan(array $file, int $idPegawai): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['ok' => false, 'error' => 'Upload tidak valid.'];
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'Belum pilih file.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload gagal (kode error ' . $file['error'] . ').'];
    }
    if ($file['size'] > UPLOAD_TTD_MAX_BYTES) {
        return ['ok' => false, 'error' => 'Ukuran file maksimal 1MB.'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'Upload tidak valid.'];
    }

    // Cek MIME beneran dari isi file (finfo), bukan cuma percaya nama
    // file/ekstensi/Content-Type yang dikirim browser - itu bisa dipalsuin.
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!isset(UPLOAD_TTD_MIME_EXT[$mime])) {
        return ['ok' => false, 'error' => 'Format file harus PNG atau JPG.'];
    }
    $ext = UPLOAD_TTD_MIME_EXT[$mime];

    $dir = __DIR__ . '/../uploads/ttd';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return ['ok' => false, 'error' => 'Folder upload gak bisa dibuat di server.'];
    }

    // Hapus file lama punya pegawai ini (mungkin beda ekstensi dari yang baru).
    foreach (UPLOAD_TTD_MIME_EXT as $oldExt) {
        $old = "$dir/$idPegawai.$oldExt";
        if (is_file($old)) {
            unlink($old);
        }
    }

    $filename = "$idPegawai.$ext";
    if (!move_uploaded_file($file['tmp_name'], "$dir/$filename")) {
        return ['ok' => false, 'error' => 'Gagal menyimpan file di server.'];
    }

    return ['ok' => true, 'filename' => $filename];
}

function tanda_tangan_hapus(?string $filename): void
{
    if ($filename === null || $filename === '') {
        return;
    }
    $path = __DIR__ . '/../uploads/ttd/' . basename($filename);
    if (is_file($path)) {
        unlink($path);
    }
}

/** URL relatif buat ditampilin di <img>. $assetPrefix: '' dari root, '../' dari admin/ atau user/. */
function tanda_tangan_url(?string $filename, string $assetPrefix = ''): ?string
{
    if ($filename === null || $filename === '') {
        return null;
    }
    return $assetPrefix . 'uploads/ttd/' . rawurlencode($filename);
}

/** Path filesystem penuh - dipakai cuti_docx.php buat embed gambar ke docx. */
function tanda_tangan_fs_path(?string $filename): ?string
{
    if ($filename === null || $filename === '') {
        return null;
    }
    $path = __DIR__ . '/../uploads/ttd/' . basename($filename);
    return is_file($path) ? $path : null;
}

// ============================================================
// Logo - admin/pengaturan.php. Dua slot terpisah pakai fungsi yg sama:
// "logo" (brand mark aplikasi, topbar & login) dan "logo_instansi" (logo
// PA Rantau, gantiin badge teks di halaman login). Masing-masing SATU
// logo global (bukan per-pegawai kayak tanda tangan), disimpan di
// assets/img/ dgn nama tetap "{baseName}.{ext}" - upload baru selalu
// menimpa/mengganti ekstensi yang lama. Pola validasi (MIME asli via
// finfo, bukan percaya ekstensi/Content-Type) sama persis dgn
// upload_tanda_tangan().
// ============================================================

const UPLOAD_LOGO_MAX_BYTES = 2_000_000; // 2MB
const UPLOAD_LOGO_MIME_EXT = ['image/png' => 'png', 'image/jpeg' => 'jpg'];

/**
 * $baseName: "logo" (default, logo aplikasi) atau "logo_instansi".
 * Return ['ok'=>bool, 'error'=>?string, 'filename'=>?string]. $filename = nama file di assets/img/ (bukan path penuh).
 */
function upload_logo(array $file, string $baseName = 'logo'): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['ok' => false, 'error' => 'Upload tidak valid.'];
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'Belum pilih file.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload gagal (kode error ' . $file['error'] . ').'];
    }
    if ($file['size'] > UPLOAD_LOGO_MAX_BYTES) {
        return ['ok' => false, 'error' => 'Ukuran file maksimal 2MB.'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'Upload tidak valid.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!isset(UPLOAD_LOGO_MIME_EXT[$mime])) {
        return ['ok' => false, 'error' => 'Format file harus PNG atau JPG.'];
    }
    $ext = UPLOAD_LOGO_MIME_EXT[$mime];

    $dir = __DIR__ . '/../assets/img';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return ['ok' => false, 'error' => 'Folder upload gak bisa dibuat di server.'];
    }

    // Hapus logo lama (mungkin beda ekstensi dari yang baru).
    foreach (UPLOAD_LOGO_MIME_EXT as $oldExt) {
        $old = "$dir/$baseName.$oldExt";
        if (is_file($old)) {
            unlink($old);
        }
    }

    $filename = "$baseName.$ext";
    if (!move_uploaded_file($file['tmp_name'], "$dir/$filename")) {
        return ['ok' => false, 'error' => 'Gagal menyimpan file di server.'];
    }

    return ['ok' => true, 'filename' => $filename];
}

function logo_hapus(?string $filename): void
{
    if ($filename === null || $filename === '') {
        return;
    }
    $path = __DIR__ . '/../assets/img/' . basename($filename);
    if (is_file($path)) {
        unlink($path);
    }
}

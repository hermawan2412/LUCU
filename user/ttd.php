<?php
require_once __DIR__ . '/../config/bootstrap.php';

// Gambar tanda tangan pegawai - cuma buat pratinjau di Profil (punya
// sendiri) dan Data Pegawai (Admin/Pengelola). Dokumen cetak gak lewat sini
// (embed langsung dari filesystem, lihat tanda_tangan_fs_path()).
if (!auth_check()) {
    http_response_code(403);
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
$row = db_one($db, "SELECT nip, tanda_tangan_path FROM pegawai WHERE id_pegawai = ?", [$id]);
$boleh = $row !== null && (auth_is_staff() || $row['nip'] === ($_SESSION['nip'] ?? null));
$path = $boleh ? tanda_tangan_fs_path($row['tanda_tangan_path']) : null;
$mime = $path !== null ? (new finfo(FILEINFO_MIME_TYPE))->file($path) : false;

if ($path === null || !isset(UPLOAD_TTD_MIME_EXT[$mime])) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header('Content-Length: ' . filesize($path));
readfile($path);

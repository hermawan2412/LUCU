<?php
require_once __DIR__ . '/../config/bootstrap.php';

// Surat dokter (lampiran Cuti Sakit) - disajikan lewat sini, bukan link
// langsung ke uploads/, biar cuma yang berhak yang bisa buka (lihat
// cuti_boleh_lihat()). Ditampilkan inline (pratinjau di browser).
if (!auth_check()) {
    redirect('../index.php');
}

$id = (int) ($_GET['id'] ?? 0);
$cuti = db_one($db, "SELECT c.*, p.nip AS nip_pemilik FROM cuti_pegawai c
    JOIN pegawai p ON p.id_pegawai = c.id_pegawai WHERE c.id_cutipegawai = ?", [$id]);

$path = null;
if ($cuti !== null && !empty($cuti['berkas']) && cuti_boleh_lihat($cuti, $cuti['nip_pemilik'])) {
    $path = __DIR__ . '/../uploads/berkas_cuti/' . basename($cuti['berkas']);
}
$ext = $path !== null ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : '';
$mime = array_search($ext, UPLOAD_BERKAS_MIME_EXT, true);

if ($path === null || $mime === false || !is_file($path)) {
    http_response_code(404);
    exit('Berkas tidak ditemukan atau Anda tidak berhak membukanya.');
}

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="Surat_Dokter_' . $id . '.' . $ext . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header('Content-Length: ' . filesize($path));
readfile($path);

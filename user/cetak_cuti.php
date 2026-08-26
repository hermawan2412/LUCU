<?php
require_once __DIR__ . '/../config/bootstrap.php';

// Diakses User (cuma punya sendiri) atau Admin (siapa aja). Bukan
// auth_require() biasa karena butuh 2 role + cek kepemilikan.
if (!auth_check()) {
    redirect('../index.php');
}

$id = (int) ($_GET['id'] ?? 0);
$cuti = db_one($db, "SELECT c.*, p.nama_pegawai, p.nip, p.unit_kerja, p.tmt_pegawai, p.jenis_asn, j.nama_jabatan, g.nama_golongan, p.id_pegawai, p.no_telp, p.tanda_tangan_path
    FROM cuti_pegawai c
    JOIN pegawai p ON p.id_pegawai = c.id_pegawai
    JOIN jabatan j ON j.id_jabatan = p.id_jabatan
    JOIN golongan g ON g.id_golongan = p.id_golongan
    WHERE c.id_cutipegawai = ?", [$id]);

if ($cuti === null) {
    flash_set('error', 'Data cuti tidak ditemukan.');
    redirect($_SESSION['role'] === 'Admin' ? '../admin/index.php' : 'daftar_cuti.php');
}

if ($_SESSION['role'] !== 'Admin' && $cuti['nip'] !== ($_SESSION['nip'] ?? null)) {
    flash_set('error', 'Anda tidak berhak mencetak dokumen ini.');
    redirect('daftar_cuti.php');
}

/**
 * Formulir cuti - keluaran .docx asli (phpoffice/phpword, lihat
 * includes/cuti_docx.php), bukan HTML print-to-PDF lagi. Struktur & istilah
 * ngikutin 2 dokumen resmi PA Rantau:
 * - PNS: templates/cuti.docx AURAT (LAMPIRAN II SE Sekretaris MA RI No 13/2019)
 * - PPPK: FORMAT CUTI PPPK.dotx (root LUCU) (LAMPIRAN II Kep. Sekretaris MA RI
 *   No 212/SEK/SK.KP5.3/II/2024)
 * Kertas F4 (21,59 x 33,02cm), kop & tanggal rata kanan, VII/VIII sejajar
 * kiri-kanan biar muat 1 halaman - lihat includes/cuti_docx.php buat detail
 * layout. Field yang di dokumen asli diisi manual petugas kepegawaian
 * (nomor surat, catatan cuti detail, paraf petugas) dibiarin kosong di sini
 * juga - LUCU gak punya alur pencatatan surat-menyurat.
 */
function pejabat_by_nip(PDO $db, ?string $nip): array
{
    if ($nip === null) {
        return ['nama_pegawai' => '-', 'nip' => '-', 'nama_jabatan' => '-', 'tanda_tangan_path' => null];
    }
    $row = db_one($db, "SELECT p.nama_pegawai, p.nip, j.nama_jabatan, p.tanda_tangan_path
        FROM pegawai p JOIN jabatan j ON j.id_jabatan = p.id_jabatan WHERE p.nip = ?", [$nip]);
    return $row ?? ['nama_pegawai' => '-', 'nip' => $nip, 'nama_jabatan' => '-', 'tanda_tangan_path' => null];
}

// "Atasan Langsung" (bagian VII) = level approval pertama yang beneran ke-assign
// (panmud_kasubag kalau ada, else panitera_sekretaris, else - kalau rantainya cuma
// 1 tingkat - sama aja dengan pejabat berwenang). "Pejabat Berwenang" (bagian VIII)
// = slot 'ketua', yang di LUCU selalu otoritas akhir (Ketua utk PNS, Sekretaris utk
// PPPK lewat cuti_cap_chain_for_jenis_asn()), bukan berarti jabatannya harfiah Ketua.
$atasanLangsungNip = $cuti['panmud_kasubag'] ?? $cuti['panitera_sekretaris'] ?? $cuti['ketua'];
$atasanLangsungLevel = $cuti['panmud_kasubag'] !== null ? 'panmud_kasubag'
    : ($cuti['panitera_sekretaris'] !== null ? 'panitera_sekretaris' : 'ketua');
$atasanLangsung = pejabat_by_nip($db, $atasanLangsungNip);
$pejabatBerwenang = pejabat_by_nip($db, $cuti['ketua']);

// Level yang barusan nolak (kalau status Tidak Disetujui) - app_flag level itu gak
// pernah disentuh cuti_reject(), jadi cuti_current_pending_level() apa adanya masih
// nunjuk persis level yang nolak.
$levelPenolak = $cuti['status_cuti'] === 'Tidak Disetujui' ? cuti_current_pending_level($cuti) : null;

$isPppk = $cuti['jenis_asn'] === 'PPPK';

// ?format=pdf buat versi PDF (docx di-convert lewat LibreOffice headless -
// lihat cuti_docx_ke_pdf()), default/lainnya tetap .docx asli.
if (($_GET['format'] ?? '') === 'pdf') {
    cuti_pdf_stream($cuti, $atasanLangsung, $pejabatBerwenang, $levelPenolak, $atasanLangsungLevel, $isPppk);
} else {
    cuti_docx_stream($cuti, $atasanLangsung, $pejabatBerwenang, $levelPenolak, $atasanLangsungLevel, $isPppk);
}

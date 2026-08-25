<?php
// Generator .docx buat formulir cetak cuti. Ganti pendekatan (2026-08-25):
// bukan lagi bikin dokumen dari nol lewat API PhpWord (addTable/addCell dst)
// - itu selalu meleset dikit dari aslinya (spasi, VII/VIII kesejajarin
// padahal aslinya numpuk ke bawah, kop jadi header malah ilang). Sekarang
// pakai PhpWord\TemplateProcessor: isi langsung dokumen ASLI yang user edit
// sendiri (templates/cuti_pns.docx & templates/cuti_pppk.docx, hasil salin
// dari FORMAT CUTI PNS.docx / FORMAT CUTI PPPK.docx di root LUCU), lewat
// macro ${NAMA_PEGAWAI} dkk yg sudah disisipkan ke setiap sel "…." yang
// datanya ada di app. Struktur, spasi, kop/header, border, semuanya persis
// bawaan Word punya user - PHP cuma isi teksnya, gak nyusun ulang layout.
//
// Field yg di dokumen asli diisi manual petugas kepegawaian (nomor surat,
// isian V. CATATAN CUTI - paraf/tahun/sisa/keterangan) sengaja TIDAK disentuh,
// tetap "…." kosong buat ditulis tangan - LUCU gak punya alur surat-menyurat.
//
// Kalau nanti template docx-nya diedit ulang: JANGAN timpa langsung file di
// templates/. Re-generate lewat proses yang sama (baca xml, sisipkan macro
// ${...} ke tiap placeholder "….") - lihat riwayat sesi 2026-08-25 di memory
// buat urutan macro per section per jenis (PNS 42 placeholder, PPPK 32),
// atau tanya ulang ke user dokumen mana yg berubah.

declare(strict_types=1);

use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\TemplateProcessor;

function cuti_docx_centang(bool $ya): string
{
    return $ya ? "\u{2612}" : "\u{2610}"; // ☒ / ☐
}

/**
 * $cuti: row gabungan cuti_pegawai + pegawai + jabatan + golongan.
 * $atasanLangsung / $pejabatBerwenang: ['nama_pegawai','nip','nama_jabatan'].
 * $levelPenolak: nama level yg nolak (kalau status Tidak Disetujui) atau null.
 * $atasanLangsungLevel: 'panmud_kasubag' | 'panitera_sekretaris' | 'ketua'.
 */
function cuti_docx_stream(
    array $cuti,
    array $atasanLangsung,
    array $pejabatBerwenang,
    ?string $levelPenolak,
    string $atasanLangsungLevel,
    bool $isPppk
): void {
    Settings::setOutputEscapingEnabled(true); // isi bisa aman ngandung & / < dkk

    $templatePath = __DIR__ . '/../templates/' . ($isPppk ? 'cuti_pppk.docx' : 'cuti_pns.docx');
    $tp = new TemplateProcessor($templatePath);

    $tp->setValue('TANGGAL_SURAT', indonesia_tgl(date('Y-m-d')));
    // Cuma di baris "Yth. ..." ini yang perlu Title Case - nama_jabatan asalnya
    // ALL CAPS di DB, dipakai apa adanya (uppercase) di tempat lain kayak
    // tanda tangan VII/VIII.
    $tp->setValue('JABATAN_BERWENANG', mb_convert_case($pejabatBerwenang['nama_jabatan'], MB_CASE_TITLE, 'UTF-8'));

    $tp->setValue('NAMA_PEGAWAI', $cuti['nama_pegawai']);
    $tp->setValue('NIP_PEGAWAI', $cuti['nip']);
    $tp->setValue('JABATAN_PEGAWAI', $cuti['nama_jabatan']);
    $tp->setValue('MASA_KERJA', $cuti['masa_kerja']);

    if ($isPppk) {
        foreach (cuti_leave_types('PPPK') as $i => $jt) {
            $tp->setValue('CK' . ($i + 1), cuti_docx_centang($cuti['jenis_cuti'] === $jt));
        }
    } else {
        // Label & urutan literal persis "FORMAT CUTI PNS.docx" (dobel "Cuti
        // Melahirkan" di slot 3&4, gak ada "Cuti Besar" - dikonfirmasi user,
        // jangan dibetulin).
        $jenisPnsII = [
            'Cuti Tahunan', 'Cuti Sakit', 'Cuti Melahirkan', 'Cuti Melahirkan',
            'Cuti Karena Alasan Penting', 'Cuti diluar Tanggungan Negara',
        ];
        foreach ($jenisPnsII as $i => $jt) {
            $tp->setValue('CK' . ($i + 1), cuti_docx_centang($cuti['jenis_cuti'] === $jt));
        }
    }

    $tp->setValue('ALASAN_CUTI', $cuti['alasan_cuti']);
    $tp->setValue('LAMA_CUTI', $cuti['lama_cuti'] . ' ' . $cuti['ket_lama_cuti']);
    $tp->setValue('TGL_MULAI', $cuti['dari_tanggal']);
    $tp->setValue('TGL_SELESAI', $cuti['sampai_dengan']);

    $tp->setValue('ALAMAT_CUTI', $cuti['alamat_cuti']);
    $tp->setValue('NO_TELP', $cuti['no_telp'] ?: '-');

    $tp->setValue('NAMA_ATASAN', $atasanLangsung['nama_pegawai']);
    $tp->setValue('NIP_ATASAN', $atasanLangsung['nip']);
    $tp->setValue('NAMA_BERWENANG', $pejabatBerwenang['nama_pegawai']);
    $tp->setValue('NIP_BERWENANG', $pejabatBerwenang['nip']);

    $disetujuiVII = (bool) $cuti[$atasanLangsungLevel];
    $ditolakVII = $levelPenolak === $atasanLangsungLevel;
    $tp->setValue('CK7_DISETUJUI', cuti_docx_centang($disetujuiVII));
    $tp->setValue('CK7_PERUBAHAN', cuti_docx_centang(false));
    $tp->setValue('CK7_DITANGGUHKAN', cuti_docx_centang(false));
    $tp->setValue('CK7_TIDAK', cuti_docx_centang($ditolakVII));

    $disetujuiVIII = (bool) $cuti['app_ketua'];
    $ditolakVIII = $levelPenolak === 'ketua';
    $tp->setValue('CK8_DISETUJUI', cuti_docx_centang($disetujuiVIII));
    $tp->setValue('CK8_PERUBAHAN', cuti_docx_centang(false));
    $tp->setValue('CK8_DITANGGUHKAN', cuti_docx_centang(false));
    $tp->setValue('CK8_TIDAK', cuti_docx_centang($ditolakVIII));

    $namaFile = 'Formulir_Cuti_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $cuti['nama_pegawai']) . '.docx';

    $tmpPath = $tp->save(); // TemplateProcessor nulis ke file temp sendiri

    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment;filename="' . $namaFile . '"');
    header('Cache-Control: max-age=0');
    header('Content-Length: ' . filesize($tmpPath));
    readfile($tmpPath);
    unlink($tmpPath);
    exit;
}

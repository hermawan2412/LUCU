<?php
// Generator .docx buat formulir cetak cuti. Ganti pendekatan (2026-08-25):
// bukan lagi bikin dokumen dari nol lewat API PhpWord (addTable/addCell dst)
// - itu selalu meleset dikit dari aslinya (spasi, VII/VIII kesejajarin
// padahal aslinya numpuk ke bawah, kop jadi header malah ilang). Sekarang
// pakai PhpWord\TemplateProcessor: isi langsung dokumen ASLI yang user edit
// sendiri (templates/cuti_pns.docx & templates/cuti_pppk.docx, hasil salin
// dari FORMAT CUTI PNS.docx / FORMAT CUTI PPPK.docx di root RESTU), lewat
// macro ${NAMA_PEGAWAI} dkk yg sudah disisipkan ke setiap sel "…." yang
// datanya ada di app. Struktur, spasi, kop/header, border, semuanya persis
// bawaan Word punya user - PHP cuma isi teksnya, gak nyusun ulang layout.
//
// Field yg di dokumen asli diisi manual petugas kepegawaian (nomor surat,
// isian V. CATATAN CUTI - paraf/tahun/sisa/keterangan) sengaja TIDAK disentuh,
// tetap "…." kosong buat ditulis tangan - RESTU gak punya alur surat-menyurat.
//
// Kalau nanti template docx-nya diedit ulang: JANGAN timpa langsung file di
// templates/. Re-generate lewat proses yang sama (baca xml, sisipkan macro
// ${...} ke tiap placeholder "….") - lihat riwayat sesi 2026-08-25 di memory
// buat urutan macro per section per jenis (PNS 42 placeholder, PPPK 32),
// atau tanya ulang ke user dokumen mana yg berubah.
//
// 2026-08-26: tambah 3 macro GAMBAR (bukan teks) - ${TTD_PEGAWAI}/
// ${TTD_ATASAN}/${TTD_BERWENANG}, disisipkan ke paragraf kosong tepat di
// atas NAMA_PEGAWAI/NAMA_ATASAN/NAMA_BERWENANG (spasi yg emang udah
// disediain templatenya buat tanda tangan basah). Diisi lewat
// setImageValue() kalau pegawainya punya tanda_tangan_path (upload lewat
// user/profil.php atau admin/data_pegawai.php - lihat includes/upload.php),
// else macronya di-setValue('') biar gak nyisa teks "${TTD_...}" mentah.
//
// Juga nambah opsi keluaran .pdf (cuti_pdf_stream()) - convert docx yg
// sama lewat LibreOffice headless (`soffice --convert-to pdf`), BUKAN
// PhpWord\Writer\PDF bawaan (dicoba, hasil tabel/border-nya berantakan).
// Konsekuensinya: server WAJIB punya `soffice` di PATH buat opsi PDF -
// kalau enggak, cuti_pdf_stream() lempar RuntimeException yg ke-catch di
// situ sendiri (flash error + redirect, gak nge-crash), tapi tetep dicek
// dulu pas setup server baru.

declare(strict_types=1);

use PhpOffice\PhpWord\Settings;

function cuti_docx_centang(bool $ya): string
{
    return $ya ? "\u{2611}" : "\u{2610}"; // ☑ / ☐
}

/**
 * Isi macro ${TTD_xxx} pakai gambar tanda tangan kalau pegawainya udah
 * upload (lihat includes/upload.php), else kosongin macronya (titik-titik
 * placeholder di template ilang, jadi baris kosong buat tanda tangan basah
 * manual - bukan nyisain teks "${TTD_...}" mentah di hasil cetak).
 */
/**
 * Wrap "In Front of Text" + rata tengah horizontal (bukan inline lagi) -
 * lihat includes/RestuTemplateProcessor.php buat alasannya. $tp harus
 * instance RestuTemplateProcessor (bukan TemplateProcessor polos) supaya
 * method floating-nya ada.
 *
 * $floating=false: fallback ke inline biasa (PhpWord setImageValue() bawaan)
 * - dipakai KHUSUS buat PARAF_PETUGAS (lihat catatan di pemanggilnya
 * kenapa floating gak bisa diandalkan di situ).
 */
function cuti_docx_isi_ttd(RestuTemplateProcessor $tp, string $macro, ?string $tandaTanganPath, int $widthPx = 100, int $heightPx = 50, bool $floating = true): void
{
    $fsPath = tanda_tangan_fs_path($tandaTanganPath);
    if ($fsPath === null) {
        $tp->setValue($macro, '');
        return;
    }
    if ($floating) {
        $tp->setImageValueFloatingCentered($macro, $fsPath, $widthPx, $heightPx);
    } else {
        $tp->setImageValue($macro, ['path' => $fsPath, 'width' => $widthPx, 'height' => $heightPx, 'ratio' => false]);
    }
}

/**
 * $cuti: row gabungan cuti_pegawai + pegawai + jabatan + golongan (perlu
 * kolom tanda_tangan_path juga - lihat query di user/cetak_cuti.php).
 * $atasanLangsung / $pejabatBerwenang: ['nama_pegawai','nip','nama_jabatan','tanda_tangan_path'].
 * $levelPenolak: nama level yg nolak (kalau status Tidak Disetujui) atau null.
 * $atasanLangsungLevel: 'panmud_kasubag' | 'panitera_sekretaris' | 'ketua'.
 *
 * Return path file .docx sementara (caller yang stream + unlink) - dipakai
 * bareng sama cuti_docx_stream() (langsung stream docx-nya) dan
 * cuti_pdf_stream() (convert dulu ke PDF sebelum di-stream).
 */
function cuti_docx_generate(
    array $cuti,
    array $atasanLangsung,
    array $pejabatBerwenang,
    ?string $levelPenolak,
    string $atasanLangsungLevel,
    bool $isPppk,
    array $paraf = ['nama_pegawai' => '-', 'tanda_tangan_path' => null]
): string {
    Settings::setOutputEscapingEnabled(true); // isi bisa aman ngandung & / < dkk

    $templatePath = __DIR__ . '/../templates/' . ($isPppk ? 'cuti_pppk.docx' : 'cuti_pns.docx');
    $tp = new RestuTemplateProcessor($templatePath);

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

    // Nomor surat diisi admin.kepegawaian SETELAH pengajuan dibuat (lihat
    // admin/data_cuti.php) - kosong kalau dicetak sebelum itu (status masih
    // 'Menunggu Nomor Surat').
    $tp->setValue('NOMOR_SURAT', $cuti['nomor_surat'] ?: '-');

    // V. Catatan Cuti - auto-isi dari data cuti, TAPI cuma buat baris/kotak
    // yang beneran relevan sama jenis_cuti pengajuan INI (dokumen ini
    // sekali-cetak buat satu pengajuan, bukan kartu manual multi-tahun) -
    // sisanya diblankin (bukan dibiarin "….", karena gak relevan di dokumen
    // spesifik ini). Paraf petugas selalu diisi kalau admin milih (gak
    // tergantung jenis_cuti).
    $rentang = $cuti['dari_tanggal'] . ' s.d. ' . $cuti['sampai_dengan'];
    if ($isPppk) {
        // N/N-1/N-2 selalu ditampilkan (saldo TERKINI, bukan snapshot) -
        // konsisten sama desain lama "selalu full", Keterangan cuma keisi
        // di baris N kalau pengajuan ini jenisnya Cuti Tahunan.
        $tp->setValue('CATATAN_N2_SISA', (string) ((int) $cuti['cuti_tahunan_n2']));
        $tp->setValue('CATATAN_N2_KET', '');
        $tp->setValue('CATATAN_N1_SISA', (string) ((int) $cuti['cuti_tahunan_n1']));
        $tp->setValue('CATATAN_N1_KET', '');
        $tp->setValue('CATATAN_N_SISA', (string) ((int) $cuti['hak_cuti_tahunan']));
        $tp->setValue('CATATAN_N_KET', $cuti['jenis_cuti'] === 'Cuti Tahunan' ? $rentang : '');
        $tp->setValue('CATATAN_SAKIT', $cuti['jenis_cuti'] === 'Cuti Sakit' ? $rentang : '');
        $tp->setValue('CATATAN_MELAHIRKAN', $cuti['jenis_cuti'] === 'Cuti Melahirkan' ? $rentang : '');
    } else {
        $adaTahunan = $cuti['jenis_cuti'] === 'Cuti Tahunan';
        $tp->setValue('CATATAN_TAHUN', $adaTahunan ? substr($cuti['dari_tanggal_iso'], 0, 4) : '');
        $tp->setValue('CATATAN_SISA', $adaTahunan ? (string) ((int) $cuti['sisa_cuti']) : '');
        $tp->setValue('CATATAN_KET', $adaTahunan ? $rentang : '');
        $tp->setValue('CATATAN_BESAR', $cuti['jenis_cuti'] === 'Cuti Besar' ? $rentang : '');
        $tp->setValue('CATATAN_SAKIT', $cuti['jenis_cuti'] === 'Cuti Sakit' ? $rentang : '');
        $tp->setValue('CATATAN_MELAHIRKAN', $cuti['jenis_cuti'] === 'Cuti Melahirkan' ? $rentang : '');
        $tp->setValue('CATATAN_PENTING', $cuti['jenis_cuti'] === 'Cuti Karena Alasan Penting' ? $rentang : '');
        $tp->setValue('CATATAN_TANGGUNGAN', $cuti['jenis_cuti'] === 'Cuti diluar Tanggungan Negara' ? $rentang : '');
    }
    // PARAF_PETUGAS sengaja INLINE, bukan floating - kolom "PARAF PETUGAS
    // CUTI" di kotak V jauh lebih sempit & vertically-merged (vMerge) dari
    // box VII/VIII. Dicoba floating (posisi absolut dihitung manual dari
    // tblGrid) - LibreOffice (pipeline .pdf) render-nya meleset jauh
    // (ke-lempar ke kolom lain, turun 1 baris) - bug rendering DOCX/LO yg
    // dikenal luas soal anchor di sel tabel ber-vMerge, bukan salah hitung
    // koordinat (sudah dicoba 3 pendekatan beda, semua meleset sama). Sel
    // ini juga udah ada teks di dalamnya ("….") jadi gak ada teks lain buat
    // "di-depanin" - inline vs floating gak ada beda visual di kasus ini.
    // Paragraf macro-nya sendiri udah center-aligned (jc=center, dicek pas
    // suntik macro) jadi tetep ke-tengah di kolomnya biarpun inline.
    cuti_docx_isi_ttd($tp, 'PARAF_PETUGAS', $paraf['tanda_tangan_path'] ?? null, 70, 35, false);

    $tp->setValue('NAMA_ATASAN', $atasanLangsung['nama_pegawai']);
    $tp->setValue('NIP_ATASAN', $atasanLangsung['nip']);
    $tp->setValue('NAMA_BERWENANG', $pejabatBerwenang['nama_pegawai']);
    $tp->setValue('NIP_BERWENANG', $pejabatBerwenang['nip']);

    // $cuti[$atasanLangsungLevel] itu kolom NIP (SELALU truthy begitu slot
    // ke-assign, gak peduli udah approve apa belum) - app_{level} adalah
    // flag approval BENERAN. Dipakai buat checkbox VII/VIII (bug lama,
    // sempet ke-☑ padahal belum approve apa-apa) SEKALIGUS buat gambar TTD
    // di bawah - bug BARU ketemu 2026-09-04: TTD_ATASAN/TTD_BERWENANG
    // dulu selalu digambar kalau pejabatnya PUNYA tanda_tangan_path,
    // gak peduli levelnya udah approve apa belum - tanda tangan pejabat
    // yang BELUM approve bisa nongol di dokumen. Sekarang cuma digambar
    // kalau levelnya beneran udah app_*=1.
    $disetujuiVII = (bool) $cuti["app_{$atasanLangsungLevel}"];
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

    // 80x32 itu KOTAK MAKS, bukan ukuran paksa - setImageValueFloatingCentered()
    // jaga rasio asli gambar (gak di-stretch), dan sejak wrap-nya "Top and
    // Bottom" (bukan "In Front of Text" lagi) paragraf di bawahnya otomatis
    // kedorong turun ngasih ruang ke gambar - gak akan numpuk teks lagi
    // walau gambarnya lebih tinggi dari 32px.
    cuti_docx_isi_ttd($tp, 'TTD_PEGAWAI', $cuti['tanda_tangan_path'] ?? null, 80, 32);
    cuti_docx_isi_ttd($tp, 'TTD_ATASAN', $disetujuiVII ? ($atasanLangsung['tanda_tangan_path'] ?? null) : null, 80, 32);
    cuti_docx_isi_ttd($tp, 'TTD_BERWENANG', $disetujuiVIII ? ($pejabatBerwenang['tanda_tangan_path'] ?? null) : null, 80, 32);

    return $tp->save(); // TemplateProcessor nulis ke file temp sendiri
}

function cuti_docx_nama_file(array $cuti, string $ext): string
{
    return 'Formulir_Cuti_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $cuti['nama_pegawai']) . '.' . $ext;
}

function cuti_docx_stream(
    array $cuti,
    array $atasanLangsung,
    array $pejabatBerwenang,
    ?string $levelPenolak,
    string $atasanLangsungLevel,
    bool $isPppk,
    array $paraf = ['nama_pegawai' => '-', 'tanda_tangan_path' => null]
): void {
    $tmpPath = cuti_docx_generate($cuti, $atasanLangsung, $pejabatBerwenang, $levelPenolak, $atasanLangsungLevel, $isPppk, $paraf);
    $namaFile = cuti_docx_nama_file($cuti, 'docx');

    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment;filename="' . $namaFile . '"');
    header('Cache-Control: max-age=0');
    header('Content-Length: ' . filesize($tmpPath));
    readfile($tmpPath);
    unlink($tmpPath);
    exit;
}

/**
 * Convert .docx ke .pdf lewat LibreOffice headless (`soffice --convert-to
 * pdf`) - ini konverter paling akurat buat layout tabel/border kompleks
 * kayak formulir ini (dicoba PhpWord\Writer\PDF bawaan dulu, hasilnya
 * berantakan buat tabel bergaris - LibreOffice jauh lebih presisi krn
 * emang real office suite renderer, bukan librari PDF generik).
 *
 * WAJIB LibreOffice ke-install di server (`soffice` ada di PATH) - kalau
 * gak ada, lempar RuntimeException biar keliatan jelas di error_log,
 * bukan diam-diam gagal.
 */
function cuti_docx_ke_pdf(string $docxPath): string
{
    $outDir = sys_get_temp_dir();
    $cmd = 'soffice --headless --nologo --nofirststartwizard --convert-to pdf --outdir '
        . escapeshellarg($outDir) . ' ' . escapeshellarg($docxPath) . ' 2>&1';
    exec($cmd, $output, $exitCode);

    $expectedPdf = $outDir . '/' . pathinfo($docxPath, PATHINFO_FILENAME) . '.pdf';
    if ($exitCode !== 0 || !is_file($expectedPdf)) {
        error_log('Gagal convert docx ke PDF (LibreOffice): ' . implode(' | ', $output));
        throw new RuntimeException('Konversi ke PDF gagal - LibreOffice belum terpasang di server atau error lain, lihat error_log.');
    }
    return $expectedPdf;
}

function cuti_pdf_stream(
    array $cuti,
    array $atasanLangsung,
    array $pejabatBerwenang,
    ?string $levelPenolak,
    string $atasanLangsungLevel,
    bool $isPppk,
    array $paraf = ['nama_pegawai' => '-', 'tanda_tangan_path' => null]
): void {
    $docxPath = cuti_docx_generate($cuti, $atasanLangsung, $pejabatBerwenang, $levelPenolak, $atasanLangsungLevel, $isPppk, $paraf);

    try {
        $pdfPath = cuti_docx_ke_pdf($docxPath);
    } catch (RuntimeException $e) {
        unlink($docxPath);
        flash_set('error', $e->getMessage());
        redirect('daftar_cuti.php');
    }

    $namaFile = cuti_docx_nama_file($cuti, 'pdf');

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment;filename="' . $namaFile . '"');
    header('Cache-Control: max-age=0');
    header('Content-Length: ' . filesize($pdfPath));
    readfile($pdfPath);
    unlink($docxPath);
    unlink($pdfPath);
    exit;
}

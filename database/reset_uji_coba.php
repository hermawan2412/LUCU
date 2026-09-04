<?php
// SEMENTARA - reset harian data uji coba RESTU selama masa uji coba (belum
// jalan reguler). Diminta user 2026-09-04, dijalanin via cron VPS
// (/etc/cron.d/restu-reset-uji-coba, jam 00:00 tiap hari) sampai akhir
// September 2026 - HAPUS FILE INI + cron entry-nya kalau udah lewat/gak
// kepake lagi, jangan dibiarin nempel permanen.
//
// Auto no-op sendiri setelah 30 Sept 2026 (guard di bawah) - aman kalau
// kelupaan dihapus, tapi tetap sebaiknya dibersihkan manual.
//
// Kerjanya: tiap baris cuti_pegawai yang statusnya 'Disetujui', balikin
// PERSIS kredit yang kepotong (hak_cuti_tahunan buat Cuti Tahunan,
// hak_cuti_sakit buat Cuti Sakit satuan Hari) ke pegawainya - additive,
// bukan reset ke angka tetap (12/14), krn beberapa pegawai punya baseline
// asli beda dari default, mis. pegawai baru dgn jatah tahun pertama
// prorata - lihat cek manual 2026-09-04, 4 dari 35 pegawai punya
// hak_cuti_tahunan != 12 dan cuma 1 yang emang dari deduksi test (sisanya
// data asli, JANGAN ketimpa). Abis di-refund, baris cuti_pegawai-nya
// DIHAPUS (semua status, bukan cuma Disetujui) + berkas surat dokter yg
// nempel ikut kehapus.
//
// TIDAK pakai CronCreate (tool Claude) - itu session-only & auto-expire
// 7 hari, gak nyampe akhir bulan dan mati kalau sesi ini selesai. Ini
// crontab VPS beneran, independen dari sesi Claude mana pun.

declare(strict_types=1);
chdir(__DIR__);
require_once __DIR__ . '/../config/bootstrap.php';

if (date('Y-m-d') > '2026-09-30') {
    echo "[" . date('c') . "] Sudah lewat 30 Sept 2026 - no-op. Hapus file ini + cron entry-nya.\n";
    exit(0);
}

$rows = db_all($db, "SELECT * FROM cuti_pegawai");
$refund = 0;
$hapusBerkas = 0;

foreach ($rows as $row) {
    if ($row['status_cuti'] === 'Disetujui') {
        if (cuti_apakah_potong_saldo_tahunan($row['jenis_cuti'])) {
            db_query($db, "UPDATE pegawai SET hak_cuti_tahunan = hak_cuti_tahunan + ? WHERE id_pegawai = ?",
                [(int) $row['lama_cuti'], $row['id_pegawai']]);
            $refund++;
        } elseif ($row['jenis_cuti'] === 'Cuti Sakit' && $row['ket_lama_cuti'] === 'Hari') {
            db_query($db, "UPDATE pegawai SET hak_cuti_sakit = hak_cuti_sakit + ? WHERE id_pegawai = ?",
                [(int) $row['lama_cuti'], $row['id_pegawai']]);
            $refund++;
        }
    }
    if (!empty($row['berkas'])) {
        $fsPath = __DIR__ . '/../uploads/berkas_cuti/' . basename($row['berkas']);
        if (is_file($fsPath)) {
            unlink($fsPath);
            $hapusBerkas++;
        }
    }
}

db_query($db, "DELETE FROM cuti_pegawai");

echo "[" . date('c') . "] Reset uji coba selesai: " . count($rows) . " baris cuti_pegawai dihapus, "
    . "$refund kredit di-refund, $hapusBerkas berkas surat dokter dibersihkan.\n";

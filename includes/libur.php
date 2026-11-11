<?php
// Hari libur nasional - disinkron dari kalender publik Google
// ("Indonesian Holidays"), sumber yg paling lengkap buat Indonesia:
// nyakup Idul Fitri/Idul Adha/Maulid Nabi/Isra Mikraj/Nyepi/Waisak dan
// Cuti Bersama (tanggal-tanggal yg ditentukan hisab + SKB 3 Menteri,
// gak bisa dihitung pakai rumus tetap kayak libur Masehi).
//
// Sempat coba date.nager.at dulu - ternyata cakupan Indonesianya cuma
// libur "tetap" (Masehi/Pancasila/Kemerdekaan), semua libur keagamaan
// yg tanggalnya berubah tiap tahun (termasuk Maulid Nabi) gak ada.
// Dua alternatif API komunitas lain (dayoffapi.vercel.app,
// libur.deno.dev) pas dicoba sekarang mati/di-nonaktifkan - risiko
// nyata gantung ke layanan gratis kecil yg gak terjamin umur panjangnya.
// Feed Google Calendar dipilih krn infrastrukturnya jauh lebih stabil.
//
// Cache di DB per tahun biar gak nembak feed tiap buka kalender.

declare(strict_types=1);

const LIBUR_ICS_URL = 'https://calendar.google.com/calendar/ical/id.indonesian%23holiday%40group.v.calendar.google.com/public/basic.ics';

// Disinkron ulang tiap 30 hari: SKB cuti bersama & tanggal libur keagamaan
// bisa direvisi pemerintah setelah sinkron pertama (dulu sekali sinkron =
// selamanya, entri "(belum pasti)" ikut nyangkut).
const LIBUR_UMUR_MAKS_HARI = 30;

function libur_tahun_sudah_sinkron(PDO $db, int $tahun): bool
{
    return db_one($db, "SELECT 1 FROM hari_libur WHERE tahun_sinkron = ?
        AND disinkron_pada > NOW() - INTERVAL " . LIBUR_UMUR_MAKS_HARI . " DAY LIMIT 1", [$tahun]) !== null;
}

/**
 * Ambil feed .ics & simpan baris tahun yg diminta ke DB. Return true
 * kalau berhasil (gagal senyap - kalender tetap jalan tanpa data libur
 * kalau feed gak kejangkau, ini pelengkap bukan fitur inti).
 */
function libur_sinkron_tahun(PDO $db, int $tahun): bool
{
    $ctx = stream_context_create(['http' => ['timeout' => 8]]);
    $ics = @file_get_contents(LIBUR_ICS_URL, false, $ctx);
    if ($ics === false) {
        // Sama seperti feed kosong: coba lagi besok, bukan di setiap request.
        db_query($db, "UPDATE hari_libur SET disinkron_pada = NOW() - INTERVAL " . (LIBUR_UMUR_MAKS_HARI - 1) . " DAY WHERE tahun_sinkron = ?", [$tahun]);
        return false;
    }

    // Unfold baris terlipat (RFC5545: baris lanjutan diawali spasi/tab)
    $ics = preg_replace("/\r?\n[ \t]/", '', $ics);

    if (!preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $ics, $blocks)) {
        return false;
    }

    $libur = [];
    foreach ($blocks[1] as $block) {
        if (!preg_match('/DTSTART;VALUE=DATE:(\d{4})(\d{2})(\d{2})/', $block, $tgl)) {
            continue;
        }
        if ((int) $tgl[1] !== $tahun) {
            continue;
        }
        if (!preg_match('/SUMMARY:(.+)/', $block, $nama)) {
            continue;
        }
        // Feed ini juga memuat "Perayaan" (Observance) - Malam Tahun Baru,
        // 1 Ramadan, dsb. - yang BUKAN hari libur. Libur resmi berdeskripsi
        // "Hari libur nasional" (Cuti Bersama juga). Tanpa saringan ini
        // 31 Desember terbaca libur padahal hari kerja.
        if (preg_match('/DESCRIPTION:(Perayaan|Observance)/i', $block)) {
            continue;
        }

        $tanggal = "{$tgl[1]}-{$tgl[2]}-{$tgl[3]}";
        $keterangan = trim(str_replace(['\\,', '\\;'], [',', ';'], $nama[1]));

        $libur[$tanggal] = $keterangan;
    }

    if (!$libur) {
        // Feed kosong/aneh: data lama dipertahankan dan baru dicoba lagi
        // besok, biar gak nembak feed (timeout 8 detik) di setiap buka halaman.
        db_query($db, "UPDATE hari_libur SET disinkron_pada = NOW() - INTERVAL " . (LIBUR_UMUR_MAKS_HARI - 1) . " DAY WHERE tahun_sinkron = ?", [$tahun]);
        return false;
    }

    // Ganti isi 1 tahun utuh, bukan upsert: tanggal yang dicabut dari feed
    // (atau dulu salah masuk) ikut hilang.
    $db->beginTransaction();
    db_query($db, "DELETE FROM hari_libur WHERE tanggal BETWEEN ? AND ?", ["$tahun-01-01", "$tahun-12-31"]);
    foreach ($libur as $tanggal => $keterangan) {
        db_query($db, "INSERT INTO hari_libur (tanggal, keterangan, tahun_sinkron) VALUES (?, ?, ?)", [$tanggal, $keterangan, $tahun]);
    }
    $db->commit();

    return true;
}

/** Pastiin tahun ini ada datanya - sync sekali kalau belum pernah. */
function libur_pastikan_tersinkron(PDO $db, int $tahun): void
{
    if (!libur_tahun_sudah_sinkron($db, $tahun)) {
        libur_sinkron_tahun($db, $tahun);
    }
}

/** Peta tanggal ('Y-m-d') -> nama libur, buat 1 bulan. */
function libur_bulan(PDO $db, int $tahun, int $bulan): array
{
    $awal = sprintf('%04d-%02d-01', $tahun, $bulan);
    $akhir = date('Y-m-t', strtotime($awal));
    $rows = db_all($db, "SELECT tanggal, keterangan FROM hari_libur WHERE tanggal BETWEEN ? AND ?", [$awal, $akhir]);

    $map = [];
    foreach ($rows as $r) {
        $map[$r['tanggal']] = $r['keterangan'];
    }
    return $map;
}

/** Cek 1 tanggal ('Y-m-d') - return nama libur kalau ada, null kalau bukan hari libur. */
function libur_cek_tanggal(PDO $db, string $tanggalIso): ?string
{
    $tahun = (int) substr($tanggalIso, 0, 4);
    libur_pastikan_tersinkron($db, $tahun);
    $row = db_one($db, "SELECT keterangan FROM hari_libur WHERE tanggal = ?", [$tanggalIso]);
    return $row['keterangan'] ?? null;
}

/**
 * Hari kerja = bukan Sabtu/Minggu DAN bukan hari libur nasional/cuti
 * bersama. Dipakai buat batesin pengajuan cuti cuma bisa dilakukan pas
 * hari kerja (lihat user/pengajuan_cuti.php) - bukan buat ngitung lama
 * cuti (itu tetep hari kalender apa adanya, gak dikurangin weekend/libur).
 */
function hari_kerja_cek(PDO $db, string $tanggalIso): bool
{
    if (kalender_is_weekend($tanggalIso)) {
        return false;
    }
    return libur_cek_tanggal($db, $tanggalIso) === null;
}

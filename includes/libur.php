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

function libur_tahun_sudah_sinkron(PDO $db, int $tahun): bool
{
    return db_one($db, "SELECT 1 FROM hari_libur WHERE tahun_sinkron = ? LIMIT 1", [$tahun]) !== null;
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
        return false;
    }

    // Unfold baris terlipat (RFC5545: baris lanjutan diawali spasi/tab)
    $ics = preg_replace("/\r?\n[ \t]/", '', $ics);

    if (!preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $ics, $blocks)) {
        return false;
    }

    $adaData = false;
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

        $tanggal = "{$tgl[1]}-{$tgl[2]}-{$tgl[3]}";
        $keterangan = trim(str_replace(['\\,', '\\;'], [',', ';'], $nama[1]));

        db_query($db, "INSERT INTO hari_libur (tanggal, keterangan, tahun_sinkron) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE keterangan = VALUES(keterangan), tahun_sinkron = VALUES(tahun_sinkron)",
            [$tanggal, $keterangan, $tahun]);
        $adaData = true;
    }

    return $adaData;
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

<?php
// Hari libur nasional - disinkron dari date.nager.at (dataset publik,
// dipakai luas, tanpa API key). Cache di DB per tahun biar gak nembak API
// tiap buka kalender; sync ulang cuma kalau tahun itu belum pernah/gagal.

declare(strict_types=1);

function libur_tahun_sudah_sinkron(PDO $db, int $tahun): bool
{
    return db_one($db, "SELECT 1 FROM hari_libur WHERE tahun_sinkron = ? LIMIT 1", [$tahun]) !== null;
}

/**
 * Ambil dari API & simpan ke DB. Return true kalau berhasil, false kalau
 * API gak kejangkau/gagal (gagal senyap - kalender tetap jalan tanpa
 * data libur, bukan error fatal, ini fitur pelengkap bukan inti).
 */
function libur_sinkron_tahun(PDO $db, int $tahun): bool
{
    $ctx = stream_context_create(['http' => ['timeout' => 5]]);
    $json = @file_get_contents("https://date.nager.at/api/v3/PublicHolidays/{$tahun}/ID", false, $ctx);
    if ($json === false) {
        return false;
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        return false;
    }

    foreach ($data as $row) {
        if (!isset($row['date'], $row['localName'])) {
            continue;
        }
        db_query($db, "INSERT INTO hari_libur (tanggal, keterangan, tahun_sinkron) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE keterangan = VALUES(keterangan), tahun_sinkron = VALUES(tahun_sinkron)",
            [$row['date'], $row['localName'], $tahun]);
    }

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

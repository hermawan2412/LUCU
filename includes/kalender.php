<?php
// Kalender bulan berjalan buat dashboard: siapa lagi cuti tanggal berapa,
// plus indikator sisa jatah cuti tahunan. Minggu mulai Senin (konvensi
// Indonesia).

declare(strict_types=1);

/**
 * Grid kalender 1 bulan, array of weeks, tiap week array 7 elemen
 * ('Y-m-d' string atau null buat padding sebelum/sesudah bulan).
 */
function kalender_bulan_grid(int $year, int $month): array
{
    $first = new DateTime(sprintf('%04d-%02d-01', $year, $month));
    $daysInMonth = (int) $first->format('t');
    $startWeekday = (int) $first->format('N'); // 1=Senin .. 7=Minggu

    $cells = [];
    for ($i = 1; $i < $startWeekday; $i++) {
        $cells[] = null;
    }
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $cells[] = sprintf('%04d-%02d-%02d', $year, $month, $d);
    }
    while (count($cells) % 7 !== 0) {
        $cells[] = null;
    }

    return array_chunk($cells, 7);
}

/** Sabtu/Minggu - dipakai bareng hari_libur buat nandain "bukan hari kerja". */
function kalender_is_weekend(string $tanggalIso): bool
{
    return (int) date('N', strtotime($tanggalIso)) >= 6;
}

/**
 * Peta tanggal -> daftar pegawai yang cuti (Disetujui) di tanggal itu,
 * dalam rentang 1 bulan. $idPegawai buat filter 1 orang aja (dashboard
 * user); null = semua pegawai (dashboard admin).
 */
function kalender_cuti_bulan(PDO $db, int $year, int $month, ?int $idPegawai = null): array
{
    $start = sprintf('%04d-%02d-01', $year, $month);
    $end = date('Y-m-t', strtotime($start));

    $sql = "SELECT c.dari_tanggal_iso, c.sampai_dengan_iso, c.jenis_cuti, p.id_pegawai, p.nama_pegawai
            FROM cuti_pegawai c JOIN pegawai p ON p.id_pegawai = c.id_pegawai
            WHERE c.status_cuti = 'Disetujui' AND c.dari_tanggal_iso <= ? AND c.sampai_dengan_iso >= ?";
    $params = [$end, $start];
    if ($idPegawai !== null) {
        $sql .= " AND c.id_pegawai = ?";
        $params[] = $idPegawai;
    }

    $map = [];
    foreach (db_all($db, $sql, $params) as $row) {
        $cursor = new DateTime(max($row['dari_tanggal_iso'], $start));
        $stop = new DateTime(min($row['sampai_dengan_iso'], $end));
        while ($cursor <= $stop) {
            $map[$cursor->format('Y-m-d')][] = [
                'nama' => $row['nama_pegawai'],
                'jenis' => $row['jenis_cuti'],
            ];
            $cursor->modify('+1 day');
        }
    }
    return $map;
}

const KALENDER_NAMA_BULAN = [
    1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

function kalender_nama_bulan(int $month): string
{
    return KALENDER_NAMA_BULAN[$month];
}

/** 'kritis' | 'rendah' | 'aman' - ambang sisa cuti tahunan (dari 12 hari default) */
function kalender_kuota_status(int $sisa): string
{
    if ($sisa <= 2) return 'kritis';
    if ($sisa <= 5) return 'rendah';
    return 'aman';
}

function kalender_kuota_label(string $status): string
{
    return match ($status) {
        'kritis' => 'Kritis',
        'rendah' => 'Rendah',
        default => 'Aman',
    };
}

function kalender_kuota_badge_class(string $status): string
{
    return match ($status) {
        'kritis' => 'badge-danger',
        'rendah' => 'badge-warning',
        default => 'badge-success',
    };
}

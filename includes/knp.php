<?php
// KNP (Kenaikan Pangkat) - naik golongan PNS, siklus reguler 4 tahun
// (PP 11/2017 jo. PP 17/2020). Beda dari KGB (naik gaji, golongan tetap):
// ini naik golongannya. Pola modul sama kayak KGB (log + reminder jatuh
// tempo), ditambah proyeksi tanggal pensiun (BUP).

declare(strict_types=1);

const KNP_SIKLUS_TAHUN = 4;
const KNP_AMBANG_SEGERA_HARI = 90; // KP butuh proses lebih lama dari KGB, kasih ancang2 lebih awal
const KNP_AMBANG_PENSIUN_TAHUN = 1;

function knp_hitung_tanggal_datang(string $knpTerakhir): string
{
    $tgl = new DateTime($knpTerakhir);
    $tgl->modify('+' . KNP_SIKLUS_TAHUN . ' years');
    return $tgl->format('Y-m-d');
}

/**
 * 'overdue' | 'segera' | 'aktif' | 'kosong'. Pola sama kayak kgb_status().
 */
function knp_status(?string $knpDatang): string
{
    if ($knpDatang === null) {
        return 'kosong';
    }
    $selisihHari = (int) (new DateTime('today'))->diff(new DateTime($knpDatang))->format('%r%a');
    if ($selisihHari < 0) {
        return 'overdue';
    }
    if ($selisihHari <= KNP_AMBANG_SEGERA_HARI) {
        return 'segera';
    }
    return 'aktif';
}

function knp_status_label(string $status): string
{
    return match ($status) {
        'overdue' => 'Jatuh Tempo',
        'segera' => 'Segera',
        'aktif' => 'Aktif',
        default => 'Belum Ada Data',
    };
}

function knp_status_badge_class(string $status): string
{
    return match ($status) {
        'overdue' => 'badge-danger',
        'segera' => 'badge-warning',
        'aktif' => 'badge-success',
        default => 'badge-neutral',
    };
}

function knp_pensiun_mendekati(?string $pensiun): bool
{
    if ($pensiun === null) {
        return false;
    }
    $selisihHari = (int) (new DateTime('today'))->diff(new DateTime($pensiun))->format('%r%a');
    return $selisihHari >= 0 && $selisihHari <= (KNP_AMBANG_PENSIUN_TAHUN * 365);
}

/**
 * Catatan KNP terbaru per pegawai, sama pola query-nya kayak
 * kgb_daftar_terbaru_per_pegawai().
 */
function knp_daftar_terbaru_per_pegawai(PDO $db): array
{
    return db_all($db, "
        SELECT p.id_pegawai, p.nama_pegawai, p.nip, j.nama_jabatan, g.nama_golongan,
               k.id_knppegawai, k.knp_terakhir, k.knp_datang, k.catatan, k.pensiun,
               gt.nama_golongan AS nama_golongan_tujuan
        FROM pegawai p
        JOIN jabatan j ON j.id_jabatan = p.id_jabatan
        JOIN golongan g ON g.id_golongan = p.id_golongan
        LEFT JOIN knp_pegawai k ON k.id_pegawai = p.id_pegawai
            AND k.knp_terakhir = (SELECT MAX(k2.knp_terakhir) FROM knp_pegawai k2 WHERE k2.id_pegawai = p.id_pegawai)
        LEFT JOIN golongan gt ON gt.id_golongan = k.id_golongan_tujuan
        ORDER BY (k.knp_datang IS NULL) ASC, k.knp_datang ASC, p.nama_pegawai ASC
    ");
}

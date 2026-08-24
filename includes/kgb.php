<?php
// KGB (Kenaikan Gaji Berkala) - kenaikan gaji periodik tiap 2 tahun buat
// PNS dalam golongan yg sama (PP 7/1977 jo. perubahannya). Beda dari cuti:
// ini bukan alur pengajuan-approval, cuma log pencatatan admin + reminder
// jatuh tempo.

declare(strict_types=1);

const KGB_SIKLUS_TAHUN = 2;
const KGB_AMBANG_SEGERA_HARI = 60; // dianggap "segera" kalau jatuh tempo <= 60 hari lagi

function kgb_hitung_tanggal_datang(string $kgbTerakhir): string
{
    $tgl = new DateTime($kgbTerakhir);
    $tgl->modify('+' . KGB_SIKLUS_TAHUN . ' years');
    return $tgl->format('Y-m-d');
}

/**
 * 'overdue' | 'segera' | 'aktif' | 'kosong' (belum pernah ada catatan KGB)
 */
function kgb_status(?string $kgbDatang): string
{
    if ($kgbDatang === null) {
        return 'kosong';
    }
    $sekarang = new DateTime('today');
    $datang = new DateTime($kgbDatang);
    $selisihHari = (int) $sekarang->diff($datang)->format('%r%a');

    if ($selisihHari < 0) {
        return 'overdue';
    }
    if ($selisihHari <= KGB_AMBANG_SEGERA_HARI) {
        return 'segera';
    }
    return 'aktif';
}

function kgb_status_label(string $status): string
{
    return match ($status) {
        'overdue' => 'Jatuh Tempo',
        'segera' => 'Segera',
        'aktif' => 'Aktif',
        default => 'Belum Ada Data',
    };
}

function kgb_status_badge_class(string $status): string
{
    return match ($status) {
        'overdue' => 'badge-danger',
        'segera' => 'badge-warning',
        'aktif' => 'badge-success',
        default => 'badge-neutral',
    };
}

/**
 * Catatan KGB terbaru per pegawai (LEFT JOIN biar pegawai yg belum pernah
 * dicatat sama sekali tetap muncul), diurutkan yg paling mendesak duluan.
 */
function kgb_daftar_terbaru_per_pegawai(PDO $db): array
{
    return db_all($db, "
        SELECT p.id_pegawai, p.nama_pegawai, p.nip, j.nama_jabatan, g.nama_golongan,
               k.id_kgbpegawai, k.kgb_terakhir, k.kgb_datang, k.keterangan
        FROM pegawai p
        JOIN jabatan j ON j.id_jabatan = p.id_jabatan
        JOIN golongan g ON g.id_golongan = p.id_golongan
        LEFT JOIN kgb_pegawai k ON k.id_pegawai = p.id_pegawai
            AND k.kgb_terakhir = (SELECT MAX(k2.kgb_terakhir) FROM kgb_pegawai k2 WHERE k2.id_pegawai = p.id_pegawai)
        ORDER BY (k.kgb_datang IS NULL) ASC, k.kgb_datang ASC, p.nama_pegawai ASC
    ");
}

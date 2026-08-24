<?php
// Logic pengajuan cuti & rute approval.
//
// Beda dari MACOA: MACOA nentuin atasan approval lewat NIP hardcode per
// jabatan di kode (punya PTA Sulawesi Barat/PTA Makassar, gak cocok buat
// instansi lain, dan beberapa jabatan gak ke-handle sama sekali -> dropdown
// kosong/error). Di sini rute approval ditentukan dari `jabatan.id_atasan`
// (data, bukan kode) - jalan buat instansi manapun tanpa ubah kode, dan
// user gak bisa pilih sendiri siapa yg approve (dulu bisa, celah manipulasi).

declare(strict_types=1);

function cuti_leave_types(): array
{
    return [
        'Cuti Tahunan',
        'Cuti Besar',
        'Cuti Sakit',
        'Cuti Melahirkan',
        'Cuti Karena Alasan Penting',
        'Cuti diluar Tanggungan Negara',
    ];
}

function cuti_get_pegawai_by_nip(PDO $db, string $nip): ?array
{
    return db_one($db, "SELECT p.*, j.nama_jabatan FROM pegawai p JOIN jabatan j ON j.id_jabatan = p.id_jabatan WHERE p.nip = ? LIMIT 1", [$nip]);
}

function cuti_masa_kerja(?string $tmt): string
{
    if (!$tmt) {
        return '-';
    }
    $mulai = new DateTime($tmt);
    $sekarang = new DateTime();
    $selisih = $mulai->diff($sekarang);
    return "{$selisih->y} Tahun {$selisih->m} Bulan";
}

/**
 * Jalan naik dari id_jabatan pemohon lewat id_atasan sampai NULL (puncak).
 * Return array id_jabatan approver berurutan (index 0 = approver pertama).
 * Array kosong = pemohon sendiri di puncak (auto-approve).
 */
function cuti_approval_chain(PDO $db, int $idJabatanPemohon): array
{
    $chain = [];
    $current = $idJabatanPemohon;
    $guard = 0; // cegah infinite loop kalau data id_atasan siklik
    while ($guard++ < 10) {
        $row = db_one($db, "SELECT id_atasan FROM jabatan WHERE id_jabatan = ?", [$current]);
        if ($row === null || $row['id_atasan'] === null) {
            break;
        }
        $chain[] = (int) $row['id_atasan'];
        $current = (int) $row['id_atasan'];
    }
    return $chain;
}

function cuti_resolve_pegawai_by_jabatan(PDO $db, int $idJabatan): ?array
{
    return db_one($db, "SELECT * FROM pegawai WHERE id_jabatan = ? LIMIT 1", [$idJabatan]);
}

/**
 * Susun 3 slot approval (panmud_kasubag, panitera_sekretaris, ketua) dari
 * rantai approver. Slot yg gak kepakai (rantai lebih pendek dari 3) ditandai
 * sudah terpenuhi (flag=1, nip=null) - sama polanya kayak MACOA.
 *
 * Return null kalau ada approver di rantai yg jabatannya kosong (gak ada
 * pegawai) - submission harus ditolak, bukan diloloskan diam-diam.
 */
function cuti_build_approval_slots(PDO $db, array $chain): ?array
{
    $labels = ['panmud_kasubag', 'panitera_sekretaris', 'ketua'];
    $depth = count($chain);

    if ($depth > 3) {
        // Data id_atasan salah konfigurasi (rantai lebih dari 3 tingkat).
        return null;
    }

    $slots = [
        'panmud_kasubag' => ['nip' => null, 'flag' => 1],
        'panitera_sekretaris' => ['nip' => null, 'flag' => 1],
        'ketua' => ['nip' => null, 'flag' => 1],
    ];

    $offset = 3 - $depth; // slot kosong di depan (auto-satisfied)
    foreach ($chain as $i => $idJabatanApprover) {
        $approverPegawai = cuti_resolve_pegawai_by_jabatan($db, $idJabatanApprover);
        if ($approverPegawai === null) {
            return null; // jabatan approver kosong, gak ada yg bisa approve
        }
        $label = $labels[$offset + $i];
        $slots[$label] = ['nip' => $approverPegawai['nip'], 'flag' => 0];
    }

    return $slots;
}

function cuti_status_awal(array $slots): array
{
    $semuaTerpenuhi = $slots['panmud_kasubag']['flag'] === 1
        && $slots['panitera_sekretaris']['flag'] === 1
        && $slots['ketua']['flag'] === 1;

    if ($semuaTerpenuhi) {
        return ['Disetujui', 'Pengajuan Cuti Disetujui'];
    }

    if ($slots['panmud_kasubag']['flag'] === 0) {
        return ['Diajukan', 'Menunggu Approval Atasan Langsung'];
    }
    if ($slots['panitera_sekretaris']['flag'] === 0) {
        return ['Diajukan', 'Menunggu Approval Panitera/Sekretaris'];
    }
    return ['Diajukan', 'Menunggu Approval Ketua'];
}

function cuti_get_by_id(PDO $db, int $id): ?array
{
    return db_one($db, "SELECT * FROM cuti_pegawai WHERE id_cutipegawai = ?", [$id]);
}

/**
 * Urutan level approval tetap: panmud_kasubag -> panitera_sekretaris -> ketua.
 * Return nama level yang lagi nunggu approve, atau null kalau semua sudah
 * terpenuhi (flag=1 semua).
 */
function cuti_current_pending_level(array $row): ?string
{
    foreach (['panmud_kasubag', 'panitera_sekretaris', 'ketua'] as $level) {
        if ((int) $row["app_{$level}"] === 0) {
            return $level;
        }
    }
    return null;
}

/**
 * Pengajuan yang lagi nunggu approval dari $nip di level yang sedang
 * berjalan (bukan level yg sudah lewat/belum sampai giliran).
 */
function cuti_pending_for_approver(PDO $db, string $nip): array
{
    return db_all($db, "SELECT c.*, p.nama_pegawai
        FROM cuti_pegawai c JOIN pegawai p ON p.id_pegawai = c.id_pegawai
        WHERE c.status_cuti = 'Diajukan' AND (
            (c.app_panmud_kasubag = 0 AND c.panmud_kasubag = ?)
            OR (c.app_panmud_kasubag = 1 AND c.app_panitera_sekretaris = 0 AND c.panitera_sekretaris = ?)
            OR (c.app_panmud_kasubag = 1 AND c.app_panitera_sekretaris = 1 AND c.app_ketua = 0 AND c.ketua = ?)
        )
        ORDER BY c.id_cutipegawai ASC", [$nip, $nip, $nip]);
}

function cuti_pending_count_for_approver(PDO $db, string $nip): int
{
    return count(cuti_pending_for_approver($db, $nip));
}

/**
 * Approve level yang sedang pending. $approverNip harus cocok sama NIP
 * yang ke-assign di level itu (dicek di sini, bukan cuma dipercaya dari form).
 * Return true kalau berhasil, false kalau approverNip gak berhak atas row ini.
 */
function cuti_approve(PDO $db, array $row, string $approverNip): bool
{
    $level = cuti_current_pending_level($row);
    if ($level === null || $row[$level] !== $approverNip) {
        return false;
    }

    $db->beginTransaction();
    try {
        db_query($db, "UPDATE cuti_pegawai SET app_{$level} = 1 WHERE id_cutipegawai = ?", [$row['id_cutipegawai']]);

        $updated = cuti_get_by_id($db, (int) $row['id_cutipegawai']);
        $nextLevel = cuti_current_pending_level($updated);

        if ($nextLevel === null) {
            db_query($db, "UPDATE cuti_pegawai SET status_cuti = 'Disetujui', ket_status_cuti = 'Pengajuan Cuti Disetujui' WHERE id_cutipegawai = ?", [$row['id_cutipegawai']]);
            if ($row['jenis_cuti'] === 'Cuti Tahunan') {
                db_query($db, "UPDATE pegawai SET hak_cuti_tahunan = hak_cuti_tahunan - ? WHERE id_pegawai = ?", [$row['lama_cuti'], $row['id_pegawai']]);
            }
        } else {
            $ket = match ($nextLevel) {
                'panitera_sekretaris' => 'Menunggu Approval Panitera/Sekretaris',
                'ketua' => 'Menunggu Approval Ketua',
                default => 'Menunggu Approval Atasan Langsung',
            };
            db_query($db, "UPDATE cuti_pegawai SET ket_status_cuti = ? WHERE id_cutipegawai = ?", [$ket, $row['id_cutipegawai']]);
        }

        $db->commit();
        return true;
    } catch (Throwable $e) {
        $db->rollBack();
        error_log('Gagal approve cuti: ' . $e->getMessage());
        return false;
    }
}

function cuti_reject(PDO $db, array $row, string $approverNip, string $alasan): bool
{
    $level = cuti_current_pending_level($row);
    if ($level === null || $row[$level] !== $approverNip) {
        return false;
    }

    db_query($db, "UPDATE cuti_pegawai SET status_cuti = 'Tidak Disetujui', ket_status_cuti = ? WHERE id_cutipegawai = ?", [$alasan, $row['id_cutipegawai']]);
    return true;
}

function cuti_status_badge_class(string $status): string
{
    return match ($status) {
        'Disetujui' => 'badge-success',
        'Ditangguhkan' => 'badge-warning',
        'Tidak Disetujui' => 'badge-danger',
        default => 'badge-neutral',
    };
}

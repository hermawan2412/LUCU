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

function cuti_status_badge_class(string $status): string
{
    return match ($status) {
        'Disetujui' => 'badge-success',
        'Ditangguhkan' => 'badge-warning',
        'Tidak Disetujui' => 'badge-danger',
        default => 'badge-neutral',
    };
}

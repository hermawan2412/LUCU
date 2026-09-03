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

// PNS: 7 jenis cuti per Pasal 3 PP No. 11 Tahun 2017 jo. Perka BKN No. 24
// Tahun 2017 (beserta perubahannya, Perka BKN No. 7 Tahun 2021). "Cuti
// Bersama" sebelumnya kelewat - bukan cuti yg diajukan pegawai (ditetapkan
// pemerintah tiap tahun), tapi tetap perlu dicatat sistem: gak boleh
// ngurangin jatah Cuti Tahunan (lihat cuti_apakah_potong_saldo_tahunan()).
//
// PPPK: cuma 3 jenis per PP No. 49 Tahun 2018 Pasal 76 - status
// kepegawaiannya kontrak, jadi gak dapet Cuti Besar/CLTN (konsep pegawai
// tetap) atau Cuti Karena Alasan Penting.
function cuti_leave_types(string $jenisAsn = 'PNS'): array
{
    if ($jenisAsn === 'PPPK') {
        return [
            'Cuti Tahunan',
            'Cuti Sakit',
            'Cuti Melahirkan',
        ];
    }
    return [
        'Cuti Tahunan',
        'Cuti Besar',
        'Cuti Sakit',
        'Cuti Melahirkan',
        'Cuti Karena Alasan Penting',
        // 'Cuti Bersama', // dinonaktifkan sementara atas permintaan user (2026-09-03) - tinggal
        // uncomment buat aktifin lagi. Gak nyentuh checkbox formulir cetak PNS (itu daftar
        // terpisah, hardcode di cuti_docx.php/template docx-nya, gak baca array ini).
        'Cuti diluar Tanggungan Negara',
    ];
}

// Cuma Cuti Tahunan yg motong saldo hak_cuti_tahunan. Cuti Bersama secara
// aturan gak boleh ngurangin jatah cuti tahunan pegawai.
function cuti_apakah_potong_saldo_tahunan(string $jenis): bool
{
    return $jenis === 'Cuti Tahunan';
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

function cuti_masa_kerja_tahun(?string $tmt): int
{
    if (!$tmt) {
        return 0;
    }
    return (new DateTime($tmt))->diff(new DateTime())->y;
}

// ===== Akumulasi cuti tahunan (dasar: SE Sekma 13/2019 poin F.1.d/e utk PNS,
// SK Sekma 212/2024 poin D.1.c/d utk PPPK) =====
//
// Saldo cuti tahunan disimpan 3 bucket per tahun, persis konsep N/N-1/N-2 di
// formulir cetak: hak_cuti_tahunan (N, tahun berjalan), cuti_tahunan_n1,
// cuti_tahunan_n2. "Starter" N-1/N-2 diisi manual admin lewat
// admin/data_pegawai.php pas rollout aplikasi (belum ada histori sebelum
// itu); selanjutnya di-roll otomatis tiap ganti tahun lewat
// cuti_tahunan_rollover_jika_perlu().
//
// INI VERSI "ATURAN INTI" (v1, dikonfirmasi user 2026-08-25) - sudah cover:
// - dasar 12 hari/tahun
// - cap 18 hari kalau N-1 sama sekali gak kepake (SE 13/2019 F.1.d / SK
//   212/2024 D.1.c)
// - cap 24 hari kalau N-1 & N-2 sama sekali gak kepake 2 tahun berturut-turut
//   (SE 13/2019 F.1.e / SK 212/2024 D.1.d)
// - syarat masa kontrak PPPK: >2 tahun buat cap 18, >3 tahun buat cap 24
//   (gak ada padanannya di aturan PNS)
// BELUM cover (sengaja ditunda, v2 kalau dibutuhkan):
// - cap 6 hari kerja per tahun yang dibawa kalau cuma dipakai SEBAGIAN
//   (SE 13/2019 F.1.f/g)
// - sisa yang udah lewat >2 tahun hangus eksplisit (F.1.h) - secara natural
//   udah "kebuang" karena rollover cuma nyimpen 3 bucket (N/N-1/N-2), jadi
//   efeknya mirip tanpa presisi rumusnya
// - penangguhan cuti tahunan oleh pejabat berwenang (F.1.i) - beda alur
//   approval sendiri, gak diimplementasi

/**
 * Geser N->N-1->N-2 tiap kali tahun kalender berganti sejak rollover
 * terakhir buat pegawai ini, isi N dengan jatah baru (12 hari). Dipanggil
 * lazy di titik-titik yang butuh saldo akurat (dashboard, form pengajuan
 * cuti) - bukan cron, biar gak ketinggalan kalau app gak jalan pas 1 Jan.
 * Return array pegawai yang sudah dimutakhirkan (field N/N-1/N-2/tahun
 * rollover-nya), field lain apa adanya.
 */
function cuti_tahunan_rollover_jika_perlu(PDO $db, array $pegawai): array
{
    $tahunSekarang = (int) date('Y');
    $tahunTerakhir = (int) ($pegawai['cuti_tahunan_rollover_tahun'] ?? $tahunSekarang);
    if ($tahunTerakhir >= $tahunSekarang) {
        return $pegawai;
    }

    $n2 = (int) $pegawai['cuti_tahunan_n2'];
    $n1 = (int) $pegawai['cuti_tahunan_n1'];
    $n = (int) $pegawai['hak_cuti_tahunan'];
    // Loop per tahun yg kelewat (bukan cuma sekali), jaga-jaga kalau aplikasi
    // gak dibuka sama sekali selama >1 pergantian tahun.
    for ($tahun = $tahunTerakhir; $tahun < $tahunSekarang; $tahun++) {
        $n2 = $n1;
        $n1 = $n;
        $n = 12;
    }

    db_query($db, "UPDATE pegawai SET hak_cuti_tahunan = ?, cuti_tahunan_n1 = ?, cuti_tahunan_n2 = ?, cuti_tahunan_rollover_tahun = ? WHERE id_pegawai = ?",
        [$n, $n1, $n2, $tahunSekarang, $pegawai['id_pegawai']]);

    $pegawai['hak_cuti_tahunan'] = $n;
    $pegawai['cuti_tahunan_n1'] = $n1;
    $pegawai['cuti_tahunan_n2'] = $n2;
    $pegawai['cuti_tahunan_rollover_tahun'] = $tahunSekarang;
    return $pegawai;
}

/**
 * Total hari cuti tahunan yang BISA dipakai tahun ini (N + carry dari
 * N-1/N-2, sudah kena cap). Panggil cuti_tahunan_rollover_jika_perlu() dulu
 * sebelum ini biar bucket-nya udah tahun berjalan.
 */
function cuti_tahunan_kuota_tersedia(array $pegawai): int
{
    $n = (int) $pegawai['hak_cuti_tahunan'];
    $n1 = (int) $pegawai['cuti_tahunan_n1'];
    $n2 = (int) $pegawai['cuti_tahunan_n2'];
    $totalMentah = $n + $n1 + $n2;

    $n1PenuhGakKepake = $n1 >= 12;
    $n2PenuhGakKepake = $n2 >= 12;

    if (($pegawai['jenis_asn'] ?? 'PNS') === 'PPPK') {
        // SK Sekma 212/2024 D.1.c/d: cap 18 cuma buat masa kontrak >2 tahun,
        // cap 24 cuma buat >3 tahun. SK gak nyebut apa yg terjadi kalau
        // syarat belum kepenuhi - asumsi konservatif kita: gak dapet carry
        // sama sekali (cuma jatah tahun berjalan), sampai syaratnya kepenuhi.
        //
        // Sengaja BUKAN cuti_masa_kerja_tahun() - itu diff()->y kepotong ke
        // tahun penuh (2 thn 11 bln kebaca "2"), jadi orang yg udah lewat
        // 2 thn tapi belum genap 3 thn kalender bakal salah ke-tolak. Bikin
        // tolok ukur "udah lewat X tahun sejak TMT" langsung dari tanggal.
        $tmt = !empty($pegawai['tmt_pegawai']) ? new DateTime($pegawai['tmt_pegawai']) : null;
        $lewat2Tahun = $tmt !== null && $tmt <= (new DateTime())->modify('-2 years');
        $lewat3Tahun = $tmt !== null && $tmt <= (new DateTime())->modify('-3 years');
        if (!$lewat2Tahun) {
            return $n;
        }
        if ($n1PenuhGakKepake && $n2PenuhGakKepake && $lewat3Tahun) {
            return min($totalMentah, 24);
        }
        if ($n1PenuhGakKepake) {
            return min($totalMentah, 18);
        }
        return $totalMentah;
    }

    // PNS/Hakim
    if ($n1PenuhGakKepake && $n2PenuhGakKepake) {
        return min($totalMentah, 24);
    }
    if ($n1PenuhGakKepake) {
        return min($totalMentah, 18);
    }
    return $totalMentah;
}

/**
 * Potong saldo cuti tahunan $lama hari, dari bucket TERTUA dulu (N-2 -> N-1
 * -> N) biar yang mau kadaluarsa duluan yg kepake duluan. Dipanggil di
 * kedua titik yg motong saldo (submit langsung-disetujui & cuti_approve())
 * - jangan lagi UPDATE hak_cuti_tahunan langsung di situ. Ambil ulang saldo
 * pegawai + rollover sendiri di sini (bukan terima dari caller) - beberapa
 * caller cuma pegang row cuti_pegawai, bukan row pegawai.
 */
function cuti_potong_saldo_tahunan(PDO $db, int $idPegawai, int $lama): void
{
    $pegawai = db_one($db, "SELECT * FROM pegawai WHERE id_pegawai = ?", [$idPegawai]);
    if ($pegawai === null) {
        return;
    }
    $pegawai = cuti_tahunan_rollover_jika_perlu($db, $pegawai);

    $n2 = (int) $pegawai['cuti_tahunan_n2'];
    $n1 = (int) $pegawai['cuti_tahunan_n1'];
    $n = (int) $pegawai['hak_cuti_tahunan'];

    $ambilN2 = min($n2, $lama);
    $lama -= $ambilN2;
    $ambilN1 = min($n1, $lama);
    $lama -= $ambilN1;
    $ambilN = min($n, $lama);
    // Sisa $lama setelah ini (harusnya 0 kalau validasi di form udah bener)
    // gak dipaksa jadi negatif - biar gak nyembunyikan bug validasi.

    db_query($db, "UPDATE pegawai SET cuti_tahunan_n2 = cuti_tahunan_n2 - ?, cuti_tahunan_n1 = cuti_tahunan_n1 - ?, hak_cuti_tahunan = hak_cuti_tahunan - ? WHERE id_pegawai = ?",
        [$ambilN2, $ambilN1, $ambilN, $idPegawai]);
}

/**
 * Rekap cuti (Disetujui) 1 pegawai dalam 1 tahun kalender, dikelompokkan per
 * jenis_cuti + satuan (Hari/Bulan/Tahun) - gak digabung lintas satuan biar
 * gak salah jumlah (mis. "3 Hari" + "2 Bulan" gak bisa dijumlah langsung).
 * Dipakai buat kartu "Rekap Cuti Saya" di dashboard.
 */
function cuti_rekap_tahun(PDO $db, int $idPegawai, int $tahun): array
{
    return db_all($db, "SELECT jenis_cuti, ket_lama_cuti, COUNT(*) AS jumlah_pengajuan, SUM(lama_cuti) AS total_lama
        FROM cuti_pegawai
        WHERE id_pegawai = ? AND status_cuti = 'Disetujui' AND YEAR(dari_tanggal_iso) = ?
        GROUP BY jenis_cuti, ket_lama_cuti
        ORDER BY jenis_cuti ASC, ket_lama_cuti ASC", [$idPegawai, $tahun]);
}

function cuti_pernah_cuti_besar_5_tahun(PDO $db, int $idPegawai): bool
{
    $row = db_one($db, "SELECT id_cutipegawai FROM cuti_pegawai
        WHERE id_pegawai = ? AND jenis_cuti = 'Cuti Besar' AND status_cuti = 'Disetujui'
          AND dari_tanggal_iso >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)
        LIMIT 1", [$idPegawai]);
    return $row !== null;
}

/**
 * Aturan syarat & batas durasi per jenis cuti, Perka BKN 24/2017 jo.
 * perubahannya. Cuma jenis yg ada syarat masa-kerja/batas durasi ketat yg
 * dicek di sini; Cuti Melahirkan, Cuti Karena Alasan Penting, dan Cuti
 * Bersama gak dikasih batas kaku (tergantung kondisi/kebijakan pejabat).
 */
function cuti_validasi_jenis(PDO $db, string $jenis, int $lama, string $unit, array $pegawai): array
{
    $errors = [];
    $masaKerjaTahun = cuti_masa_kerja_tahun($pegawai['tmt_pegawai']);

    if ($jenis === 'Cuti Besar') {
        if ($masaKerjaTahun < 5) {
            $errors[] = "Cuti Besar cuma buat PNS dgn masa kerja minimal 5 tahun terus-menerus (masa kerja Anda: $masaKerjaTahun tahun).";
        }
        if ($unit === 'Tahun' || ($unit === 'Bulan' && $lama > 3) || ($unit === 'Hari' && $lama > 92)) {
            $errors[] = 'Cuti Besar diberikan maksimal 3 bulan.';
        }
        if (cuti_pernah_cuti_besar_5_tahun($db, (int) $pegawai['id_pegawai'])) {
            $errors[] = 'Sudah pernah mengambil Cuti Besar dalam 5 tahun terakhir, belum bisa mengambil lagi.';
        }
    } elseif ($jenis === 'Cuti diluar Tanggungan Negara') {
        if ($masaKerjaTahun < 5) {
            $errors[] = "Cuti di Luar Tanggungan Negara cuma buat PNS dgn masa kerja minimal 5 tahun terus-menerus (masa kerja Anda: $masaKerjaTahun tahun).";
        }
        if ($unit === 'Hari' || ($unit === 'Bulan' && $lama > 36) || ($unit === 'Tahun' && $lama > 3)) {
            $errors[] = 'Cuti di Luar Tanggungan Negara maksimal 3 tahun (dapat diperpanjang 1 tahun oleh pejabat berwenang).';
        }
    } elseif ($jenis === 'Cuti Sakit') {
        if (($unit === 'Bulan' && $lama > 18) || ($unit === 'Tahun' && $lama >= 2)) {
            $errors[] = 'Cuti Sakit diberikan maksimal 1,5 tahun (18 bulan) termasuk perpanjangan.';
        }
    }

    return $errors;
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

/**
 * Ganti otoritas akhir rantai approval sesuai jenis ASN pemohon.
 * PNS: rantai gak berubah (otoritas akhir tetap Ketua/puncak alami).
 * PPPK: otoritas akhir diganti jadi jabatan yang ditandai
 * `is_pejabat_pppk` (Sekretaris) - langkah2 sebelumnya (kasubag/panmud dst)
 * tetap jalan seperti biasa.
 *
 * Return null kalau pejabat PPPK belum dikonfigurasi (caller harus tolak
 * submission, bukan lolos diam-diam).
 */
function cuti_cap_chain_for_jenis_asn(PDO $db, array $chain, string $jenisAsn, int $idJabatanPemohon): ?array
{
    if ($jenisAsn !== 'PPPK') {
        return $chain;
    }

    $row = db_one($db, "SELECT id_jabatan FROM jabatan WHERE is_pejabat_pppk = 1 LIMIT 1");
    if ($row === null) {
        return null;
    }
    $idPejabatPppk = (int) $row['id_jabatan'];

    if ($idJabatanPemohon === $idPejabatPppk) {
        return []; // pemohon sendiri pejabat berwenang PPPK -> auto-approve
    }

    // rantai alami selalu diakhiri jabatan puncak (id_atasan NULL, "Ketua");
    // buang itu, ganti otoritas akhirnya jadi pejabat PPPK.
    if (!empty($chain)) {
        array_pop($chain);
    }
    if (empty($chain) || end($chain) !== $idPejabatPppk) {
        $chain[] = $idPejabatPppk;
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

function cuti_nama_jabatan_by_nip(PDO $db, ?string $nip): string
{
    if ($nip === null) {
        return 'Atasan';
    }
    $row = db_one($db, "SELECT j.nama_jabatan FROM pegawai p JOIN jabatan j ON j.id_jabatan = p.id_jabatan WHERE p.nip = ?", [$nip]);
    return $row['nama_jabatan'] ?? 'Atasan';
}

/**
 * Label status pending diambil dari jabatan approver yang beneran ke-assign
 * di slot itu (bukan teks hardcode "Ketua") - buat PPPK, slot terakhir
 * isinya Sekretaris, bukan Ketua, jadi labelnya harus ikut berubah.
 */
function cuti_status_awal(PDO $db, array $slots): array
{
    $semuaTerpenuhi = $slots['panmud_kasubag']['flag'] === 1
        && $slots['panitera_sekretaris']['flag'] === 1
        && $slots['ketua']['flag'] === 1;

    if ($semuaTerpenuhi) {
        return ['Disetujui', 'Pengajuan Cuti Disetujui'];
    }

    foreach (['panmud_kasubag', 'panitera_sekretaris', 'ketua'] as $level) {
        if ($slots[$level]['flag'] === 0) {
            $jabatan = cuti_nama_jabatan_by_nip($db, $slots[$level]['nip']);
            return ['Diajukan', "Menunggu Approval $jabatan"];
        }
    }
    return ['Diajukan', 'Menunggu Approval'];
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

        $pemohon = db_one($db, "SELECT nip, nama_pegawai FROM pegawai WHERE id_pegawai = ?", [$row['id_pegawai']]);
        $pemohonNip = $pemohon['nip'];

        if ($nextLevel === null) {
            db_query($db, "UPDATE cuti_pegawai SET status_cuti = 'Disetujui', ket_status_cuti = 'Pengajuan Cuti Disetujui' WHERE id_cutipegawai = ?", [$row['id_cutipegawai']]);
            if (cuti_apakah_potong_saldo_tahunan($row['jenis_cuti'])) {
                cuti_potong_saldo_tahunan($db, (int) $row['id_pegawai'], (int) $row['lama_cuti']);
            }
            notifikasi_kirim($db, $pemohonNip, "Pengajuan {$row['jenis_cuti']} Anda telah disetujui.", 'daftar_cuti.php');
        } else {
            $jabatan = cuti_nama_jabatan_by_nip($db, $updated[$nextLevel]);
            $ket = "Menunggu Approval $jabatan";
            db_query($db, "UPDATE cuti_pegawai SET ket_status_cuti = ? WHERE id_cutipegawai = ?", [$ket, $row['id_cutipegawai']]);
            notifikasi_kirim($db, $updated[$nextLevel], "Pengajuan {$row['jenis_cuti']} dari {$pemohon['nama_pegawai']} menunggu approval Anda.", 'approve_cuti.php');
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

    $pemohonNip = db_one($db, "SELECT nip FROM pegawai WHERE id_pegawai = ?", [$row['id_pegawai']])['nip'];
    notifikasi_kirim($db, $pemohonNip, "Pengajuan {$row['jenis_cuti']} Anda ditolak: $alasan", 'daftar_cuti.php');

    return true;
}

/**
 * Statistik pegawai yang lagi cuti HARI INI (status Disetujui, tanggal
 * sekarang jatuh di antara dari_tanggal_iso & sampai_dengan_iso). Dipakai
 * di kotak info halaman login - gak butuh login buat lihat ini, cuma
 * angka agregat, gak ada data pribadi.
 */
function cuti_statistik_hari_ini(PDO $db): array
{
    $total = (int) db_one($db, "SELECT COUNT(*) AS n FROM pegawai")['n'];
    $sedangCuti = (int) db_one($db, "SELECT COUNT(DISTINCT id_pegawai) AS n FROM cuti_pegawai
        WHERE status_cuti = 'Disetujui' AND dari_tanggal_iso <= CURDATE() AND sampai_dengan_iso >= CURDATE()")['n'];

    $persen = $total > 0 ? round(($sedangCuti / $total) * 100) : 0;

    return [
        'sedang_cuti' => $sedangCuti,
        'total' => $total,
        'persen' => $persen,
        'siaga' => $persen >= 30, // >=30% pegawai gak masuk - patut diwaspadai
    ];
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

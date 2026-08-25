<?php
// Satu-kali lanjutan import_dari_aurat.php buat 25 pegawai yang jabatan
// AURAT-nya teks compound (gak string-match langsung ke jabatan
// terkontrol LUCU). Mapping NIP -> id_jabatan LUCU sudah dicek manual
// satu-satu terhadap teks jabatan+unit_kerja AURAT (lihat riwayat sesi).
// Read-only ke AURAT, sama seperti import_dari_aurat.php.
//
//   php database/import_manual_pending.php [--terapkan]

declare(strict_types=1);

$config = require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

if (empty($config['db_aurat']['host'])) {
    fwrite(STDERR, "config['db_aurat'] belum diisi.\n");
    exit(1);
}

$terapkan = in_array('--terapkan', $argv, true);
echo $terapkan ? "MODE: TERAPKAN\n\n" : "MODE: PREVIEW\n\n";

$dbLucu = db_connect($config['db']);
$dbAurat = db_connect($config['db_aurat']);

// NIP -> id_jabatan LUCU (dicocokkan manual dari jabatan+unit_kerja AURAT)
$mapping = [
    '197807182006041015' => 17, // Abdul Muluk -> KASUBAG PERENCANAAN, TI DAN PELAPORAN
    '200010182024051002' => 20, // Achmad Adjie Al Muas -> ANALIS PERKARA PERADILAN (Hukum)
    '198007042025212022' => 31, // Aulia Hayani -> PENATA LAYANAN OPERASIONAL (Kepegawaian)
    '197407042000121002' => 14, // Bambang Julianto -> JURUSITA
    '199805022022032016' => 3,  // Diah Melindasari -> HAKIM
    '199609152022032005' => 22, // Dita Kharisma -> PENGELOLA PENANGANAN PERKARA (Gugatan)
    '199703172025061005' => 29, // Fachrul Ahmad Lubis -> DOKUMENTALIS HUKUM (Permohonan)
    '198812232020121005' => 35, // Fajar Sugih Rizqillah -> PRANATA KOMPUTER (baru, di bawah Sekretaris)
    '198109202006041009' => 6,  // Fauzan Rahman ("Seketaris") -> SEKRETARIS
    '199705182022032009' => 3,  // Fika Aufani Kumala -> HAKIM
    '199503252022032009' => 3,  // Galuh Retno Setyo Wardani -> HAKIM
    '197605102025211027' => 34, // Hanafi -> OPERATOR LAYANAN OPERASIONAL (Umum & Keuangan)
    '198812242025211036' => 32, // Hermawan Cahyo Husodo -> PENATA LAYANAN OPERASIONAL (Umum & Keuangan)
    '199504072020121006' => 27, // Junaidi Fajar -> OPERATOR - TEKNISI SARANA DAN PRASARANA
    '199812232025062019' => 28, // Maya Purwaningtiyas -> DOKUMENTALIS HUKUM (Hukum)
    '199912292025062019' => 25, // Melinia Friska Desi Afrida -> TEKNISI SARANA DAN PRASARANA
    '199907122022031001' => 3,  // Muhammad Andre Sheva Panjalu Shahensyah -> HAKIM
    '198503162025211036' => 33, // Nasrudin -> OPERATOR LAYANAN OPERASIONAL (Kepegawaian)
    '199809202025211019' => 24, // Norjimansyah -> OPERATOR - PENATA LAYANAN OPERASIONAL (PTIP)
    '199802242022032014' => 3,  // Rizki Adelia -> HAKIM
    '200205242025062012' => 20, // Salwa Husna Sekai Suryawi -> ANALIS PERKARA PERADILAN (Hukum)
    '199903112025062009' => 30, // Vika Sandi Nurlana -> DOKUMENTALIS HUKUM (Gugatan)
    '198505072011011006' => 18, // Wageyono Indra -> KASUBAG UMUM DAN KEUANGAN
    '200011082025061012' => 26, // Wahyu Khoirurrohman Sugiarto -> ANALIS PERKARA PERADILAN (Gugatan)
    '199902112022032006' => 3,  // Zidna Mazidah -> HAKIM
];

$golonganLucu = db_all($dbLucu, "SELECT id_golongan, nama_golongan FROM golongan");
$golonganByNama = [];
foreach ($golonganLucu as $g) {
    $golonganByNama[strtoupper(trim($g['nama_golongan']))] = (int) $g['id_golongan'];
}

$masuk = 0;
$gagal = [];

foreach ($mapping as $nip => $idJabatan) {
    $p = db_one($dbAurat, "SELECT nip, nama_lengkap, gelar_depan, gelar_belakang, golongan_ruang, unit_kerja
        FROM pegawai WHERE nip = ? AND status_aktif = 1", [$nip]);

    if (!$p) {
        $gagal[] = "$nip: gak ketemu / gak aktif lagi di AURAT";
        continue;
    }

    $idGolongan = $golonganByNama[strtoupper(trim((string) $p['golongan_ruang']))] ?? null;
    if ($idGolongan === null) {
        $gagal[] = "$nip ({$p['nama_lengkap']}): golongan \"{$p['golongan_ruang']}\" gak ketemu match";
        continue;
    }

    $nama = trim(($p['gelar_depan'] ? $p['gelar_depan'] . ' ' : '') . $p['nama_lengkap'] . ($p['gelar_belakang'] ? ', ' . $p['gelar_belakang'] : ''));

    printf("  OK  %-40s NIP %s -> jabatan #%d, golongan #%d\n", $nama, $nip, $idJabatan, $idGolongan);

    if ($terapkan) {
        db_query($dbLucu, "INSERT INTO pegawai (nama_pegawai, nip, id_jabatan, id_golongan, unit_kerja, hak_cuti_tahunan)
            VALUES (?, ?, ?, ?, ?, 12)
            ON DUPLICATE KEY UPDATE nama_pegawai = VALUES(nama_pegawai), id_jabatan = VALUES(id_jabatan),
                id_golongan = VALUES(id_golongan), unit_kerja = VALUES(unit_kerja)",
            [$nama, $nip, $idJabatan, $idGolongan, $p['unit_kerja'] ?: $config['app']['instansi']]);
    }
    $masuk++;
}

echo "\n$masuk pegawai " . ($terapkan ? 'diimport/diperbarui' : 'siap diimport') . ".\n";

if ($gagal) {
    echo "\n" . count($gagal) . " GAGAL:\n";
    foreach ($gagal as $g) {
        echo "  - $g\n";
    }
}

if (!$terapkan) {
    echo "\nPreview doang. Jalankan --terapkan buat nulis ke LUCU.\n";
}

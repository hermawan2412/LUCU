<?php
// Riwayat pembaruan fitur - ditampilkan di changelog.php (link dari badge
// versi di topbar, lihat includes/layout.php). Diisi manual tiap ada rilis
// fitur baru - bukan auto-generate dari git log, biar bahasanya bisa
// dirapihin buat pembaca awam (bukan salinan pesan commit teknis).
//
// Skema versi: tanggal rilis (YYYY.MM.DD) - sederhana, gampang diurutkan,
// gak perlu mikirin semver major/minor buat app internal kayak gini.
//
// URUTAN: paling baru di ATAS array (ditampilkan apa adanya, gak di-sort
// ulang di kode) - pas nambah rilis baru, tambahin di paling atas.

declare(strict_types=1);

const CHANGELOG_RILIS = [
    [
        'versi' => '2026.09.04',
        'tanggal' => '4 September 2026',
        'judul' => 'Halaman login dirapihkan',
        'items' => [
            'Logo instansi & logo aplikasi ditampilkan berdampingan (dulu bertumpuk).',
            'Tagline di halaman login sekarang bisa diedit lewat Pengaturan.',
            'Menu, logo, toggle tema, dan info akun di topbar dibuat center-alignment.',
        ],
    ],
    [
        'versi' => '2026.09.03',
        'tanggal' => '3 September 2026',
        'judul' => 'Approval berjenjang, Plh/Plt, notifikasi WhatsApp, dan perapihan besar',
        'items' => [
            'Approval cuti berjenjang: atasan langsung dulu, baru pejabat berwenang - masing-masing bisa pilih tanda tangan digital atau tunda ke tanda tangan basah.',
            'Kredit Cuti Sakit 14 hari/tahun (hangus di akhir tahun) - lewat kuota tetap disetujui, dikasih catatan potongan TUKIN.',
            'Dukungan jabatan dobel/Plh/Plt - approval cuti otomatis ikut ke pelaksana harian/tugas kalau sedang aktif.',
            'Kolom "Atasan Langsung" di Data Pegawai, biar rute approval kelihatan tanpa cek satu-satu.',
            'Notifikasi WhatsApp sekarang nyertain link langsung ke halaman/dokumen terkait, dan dikirim ke pengaju + kedua atasan sekaligus tiap ada approval/tolak.',
            'Log Aktivitas (siapa ngapain kapan) dan Log Error sistem - khusus admin.',
            'Nomor surat cuti sekarang diisi admin.kepegawaian dulu, baru proses approval jalan.',
            'Kotak "V. Catatan Cuti" di formulir cetak sekarang otomatis terisi dari data cuti, bukan manual lagi.',
            'Pengajuan cuti cuma bisa dilakukan pada hari kerja - Sabtu/Minggu/hari libur nasional ditandai merah di kalender.',
            'Cuti Sakit lebih dari 1 hari wajib lampirkan surat keterangan dokter.',
            'Perbaikan tampilan: kontras menu aktif di dark mode, tabel data punya indikator bisa digeser di HP, kalender gak numpuk lagi di layar sempit.',
        ],
    ],
    [
        'versi' => '2026.09.02',
        'tanggal' => '2 September 2026',
        'judul' => 'Ganti nama jadi RESTU',
        'items' => [
            'Nama aplikasi berubah dari LUCU menjadi RESTU (Rekam Elektronik Sistem cuTi Untuk aparatur).',
        ],
    ],
    [
        'versi' => '2026.08.28',
        'tanggal' => '28 Agustus 2026',
        'judul' => 'Keamanan & tampilan login',
        'items' => [
            'Rate-limit percobaan login, cookie sesi lebih aman, akses folder konfigurasi ditutup dari web.',
            'Halaman login dapat tampilan baru dengan efek kaca & latar dinamis.',
        ],
    ],
    [
        'versi' => '2026.08.26',
        'tanggal' => '26 Agustus 2026',
        'judul' => 'Tanda tangan digital',
        'items' => [
            'Pegawai bisa upload gambar tanda tangan sendiri, otomatis terpasang di formulir cetak.',
            'Formulir cuti bisa diunduh sebagai .pdf, selain .docx.',
        ],
    ],
    [
        'versi' => '2026.08.25',
        'tanggal' => '25 Agustus 2026',
        'judul' => 'Akumulasi cuti tahunan & notifikasi WhatsApp',
        'items' => [
            'Sisa cuti tahunan yang gak kepakai otomatis terbawa ke tahun berikutnya (sesuai SE Sekma 13/2019 / SK Sekma 212/2024).',
            'Notifikasi WhatsApp via Fonnte tiap ada pengajuan/approval/tolak cuti.',
            'Formulir cetak cuti pakai template .docx resmi asli, bukan bikinan ulang.',
        ],
    ],
    [
        'versi' => '2026.08.24',
        'tanggal' => '24 Agustus 2026',
        'judul' => 'Rilis awal',
        'items' => [
            'Login aman (kata sandi terenkripsi, terlindung dari serangan umum).',
            'Pengajuan & approval cuti otomatis dirutekan sesuai jabatan pegawai - beda aturan buat PNS dan PPPK.',
            'Data Pegawai, Jabatan, Golongan, KGB (Kenaikan Gaji Berkala), dan KNP (Kenaikan Pangkat).',
            'Kalender tim + indikator sisa jatah cuti tiap pegawai di dashboard.',
            'Export data ke CSV, cetak formulir cuti.',
            'Sinkronisasi otomatis hari libur nasional & cuti bersama.',
            'Notifikasi dalam aplikasi, halaman Pengaturan, dan Kelola Akun.',
        ],
    ],
];

function changelog_versi_terbaru(): string
{
    return CHANGELOG_RILIS[0]['versi'] ?? '-';
}

/**
 * Markup halaman changelog - dipanggil dari admin/changelog.php DAN
 * user/changelog.php (2 file tipis, bukan 1 halaman shared di root) karena
 * layout_header() nge-hardcode path aset/nav relatif ke folder admin/ atau
 * user/ - halaman di root bakal salah path kalau motong dari sana.
 */
function changelog_render_html(): void
{
    ?>
    <h1>Riwayat Pembaruan</h1>
    <p class="lead">Versi berjalan: <strong>v<?= e(changelog_versi_terbaru()) ?></strong> &middot; daftar fitur baru per rilis.</p>

    <?php foreach (CHANGELOG_RILIS as $rilis): ?>
      <div class="card">
        <div style="display:flex; align-items:baseline; gap:10px; flex-wrap:wrap; margin-bottom:4px;">
          <span class="badge badge-neutral">v<?= e($rilis['versi']) ?></span>
          <h2 style="margin:0; font-size:1.05rem;"><?= e($rilis['judul']) ?></h2>
        </div>
        <p class="hint" style="margin:0 0 12px;"><?= e($rilis['tanggal']) ?></p>
        <ul style="margin:0; padding-left:20px; line-height:1.7;">
          <?php foreach ($rilis['items'] as $item): ?>
            <li><?= e($item) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
    <?php
}

<?php
// Notifikasi in-app (bell) + WhatsApp (Fonnte, opsional - lihat
// includes/whatsapp.php). 1 titik panggil buat semua alur cuti
// (pengajuan/approve/reject), jadi WA otomatis ke-cover di mana pun
// notifikasi_kirim() sudah dipanggil - gak perlu ubah call site lain.

declare(strict_types=1);

function notifikasi_kirim(PDO $db, string $nip, string $pesan, string $url = ''): void
{
    db_query($db, "INSERT INTO notifikasi (nip, pesan, url) VALUES (?, ?, ?)", [$nip, $pesan, $url]);

    // Best-effort, gak boleh gagalin alur cuti kalau WA error - lihat
    // catatan silent-fail di wa_kirim().
    try {
        $pegawai = db_one($db, "SELECT no_telp FROM pegawai WHERE nip = ?", [$nip]);
        if ($pegawai !== null) {
            $pesanWa = $pesan;
            // $url disimpan relatif ke folder user/ (dipakai user/notifikasi.php
            // buat href di halaman - lihat call site di includes/cuti.php &
            // user/pengajuan_cuti.php). Buat WA, harus link ABSOLUT biar bisa
            // langsung diklik dari luar aplikasi (APP_URL kosong kalau
            // dipanggil dari CLI - skip link, tetep kirim teksnya).
            if ($url !== '' && APP_URL !== '') {
                $pesanWa .= "\n\nBuka: " . APP_URL . '/user/' . $url;
            }
            wa_kirim($db, $pegawai['no_telp'], $pesanWa);
        }
    } catch (Throwable $e) {
        error_log('Gagal kirim notifikasi WA: ' . $e->getMessage());
    }
}

function notifikasi_belum_dibaca_count(PDO $db, string $nip): int
{
    return (int) db_one($db, "SELECT COUNT(*) AS n FROM notifikasi WHERE nip = ? AND dibaca = 0", [$nip])['n'];
}

function notifikasi_daftar(PDO $db, string $nip, int $limit = 10): array
{
    $limit = max(1, min(50, $limit));
    // LIMIT gak bisa lewat placeholder pas ATTR_EMULATE_PREPARES=false;
    // aman di-inline krn $limit di-cast int & di-clamp di atas, bukan input user.
    return db_all($db, "SELECT * FROM notifikasi WHERE nip = ? ORDER BY id_notifikasi DESC LIMIT $limit", [$nip]);
}

function notifikasi_tandai_semua_dibaca(PDO $db, string $nip): void
{
    db_query($db, "UPDATE notifikasi SET dibaca = 1 WHERE nip = ? AND dibaca = 0", [$nip]);
}

function notifikasi_waktu_relatif(string $timestamp): string
{
    $diff = (new DateTime())->diff(new DateTime($timestamp));
    if ($diff->days > 0) {
        return $diff->days === 1 ? 'Kemarin' : "{$diff->days} hari lalu";
    }
    if ($diff->h > 0) {
        return "{$diff->h} jam lalu";
    }
    if ($diff->i > 0) {
        return "{$diff->i} menit lalu";
    }
    return 'Baru saja';
}

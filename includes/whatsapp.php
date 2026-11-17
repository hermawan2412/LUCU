<?php
// Notifikasi WhatsApp via Fonnte (docs.fonnte.com) - paket Free (1000
// pesan/bulan, gratis selamanya). Best-effort: gagal kirim WA GAK BOLEH
// nge-block alur cuti (approve/reject/notif in-app tetap jalan walau WA-nya
// gagal/nonaktif/timeout) - makanya semua di sini nangkep exception sendiri,
// gak pernah throw ke pemanggil.
//
// Diaktifkan/token diisi admin lewat admin/pengaturan.php (tabel
// `pengaturan`, kolom wa_aktif/wa_fonnte_token) - bukan config.php, biar
// bisa diubah tanpa deploy ulang.

declare(strict_types=1);

const WA_FONNTE_ENDPOINT = 'https://api.fonnte.com/send';

/**
 * Rapikan nomor telepon ke format yang diterima Fonnte (62xxxxxxxxxx).
 * Terima input longgar: "0812...", "+62812...", "812...", ada spasi/strip.
 * Return null kalau kosong/kependekan (kemungkinan nomor gak valid) -
 * caller harus skip kirim kalau null, bukan maksa kirim ke nomor sampah.
 */
function wa_format_nomor(?string $no): ?string
{
    if ($no === null) {
        return null;
    }
    $digit = preg_replace('/\D+/', '', $no);
    if ($digit === '') {
        return null;
    }
    if (str_starts_with($digit, '0')) {
        $digit = '62' . substr($digit, 1);
    } elseif (!str_starts_with($digit, '62')) {
        $digit = '62' . $digit;
    }
    // Nomor Indonesia wajar: 62 + 9-13 digit lokal.
    if (strlen($digit) < 10 || strlen($digit) > 15) {
        return null;
    }
    return $digit;
}

/**
 * Ambil pengaturan WA (aktif + token) sekali per request lewat static cache
 * - notifikasi_kirim() bisa dipanggil berkali-kali dalam 1 request (mis.
 * loop approval), gak perlu query pengaturan tiap kali.
 */
function wa_pengaturan(PDO $db): array
{
    static $cache = null;
    if ($cache === null) {
        $row = db_one($db, "SELECT wa_aktif, wa_fonnte_token, wa_batas_harian, wa_kuota_bulanan FROM pengaturan WHERE id_pengaturan = 1");
        $cache = [
            'aktif' => $row !== null && (int) $row['wa_aktif'] === 1,
            'token' => $row['wa_fonnte_token'] ?? '',
            'batas_harian' => max(1, (int) ($row['wa_batas_harian'] ?? 100)),
            'kuota_bulanan' => max(1, (int) ($row['wa_kuota_bulanan'] ?? 1000)),
        ];
    }
    return $cache;
}

/**
 * Kirim 1 pesan WhatsApp lewat alur normal aplikasi (approve/reject/dst).
 * Return true kalau Fonnte terima requestnya (bukan jaminan pesan udah
 * nyampe HP tujuan - itu di luar kendali kita). Silent-fail: nonaktif/token
 * kosong/nomor invalid/curl error/HTTP error semua cuma di-log, gak pernah
 * exception.
 */
function wa_kirim(PDO $db, ?string $noTelp, string $pesan): bool
{
    $cfg = wa_pengaturan($db);
    if (!$cfg['aktif'] || trim($cfg['token']) === '') {
        return false;
    }
    // Batas harian: bug/loop atau pengajuan beruntun gak boleh ngabisin kuota
    // Fonnte sebulan. Lewat batas = ditahan (notif in-app tetap jalan).
    if (wa_hitung($db, 'hari') >= $cfg['batas_harian']) {
        error_log('WA Fonnte: ditahan, batas harian ' . $cfg['batas_harian'] . ' pesan tercapai');
        wa_catat($db, false, 'ditahan: batas harian tercapai');
        return false;
    }
    $alasan = null;
    $ok = wa_kirim_dengan_token($cfg['token'], $noTelp, $pesan, $alasan);
    if ($ok || $alasan !== 'nomor tidak valid') {
        wa_catat($db, $ok, $ok ? null : $alasan);
    }
    return $ok;
}

/** Jumlah WA yang berhasil dikirim hari ini ('hari') atau bulan ini ('bulan'). */
function wa_hitung(PDO $db, string $rentang): int
{
    $mulai = $rentang === 'bulan' ? date('Y-m-01 00:00:00') : date('Y-m-d 00:00:00');
    $row = db_one($db, "SELECT COUNT(*) AS n FROM wa_log WHERE berhasil = 1 AND dikirim_at >= ?", [$mulai]);
    return (int) ($row['n'] ?? 0);
}

/** Catat 1 percobaan kirim (tanpa nomor & isi pesan) buat penghitung kuota di admin/pengaturan.php. */
function wa_catat(PDO $db, bool $berhasil, ?string $alasan): void
{
    try {
        db_query($db, "INSERT INTO wa_log (dikirim_at, berhasil, alasan) VALUES (?, ?, ?)",
            [date('Y-m-d H:i:s'), $berhasil ? 1 : 0, $alasan === null ? null : mb_substr($alasan, 0, 150)]);
    } catch (Throwable $e) {
        error_log('WA log gagal dicatat: ' . $e->getMessage());
    }
}

/**
 * Kirim langsung pakai token tertentu, TANPA cek pengaturan.wa_aktif -
 * dipakai cuma buat tombol "Kirim Tes" di admin/pengaturan.php, biar admin
 * bisa tes token sebelum nyimpen/ngaktifin (form belum ke-submit, jadi
 * token yg mau ditest belum ada di DB).
 */
function wa_kirim_dengan_token(string $token, ?string $noTelp, string $pesan, ?string &$alasan = null): bool
{
    if (trim($token) === '') {
        $alasan = 'token kosong';
        return false;
    }

    $target = wa_format_nomor($noTelp);
    if ($target === null) {
        $alasan = 'nomor tidak valid';
        return false;
    }

    $ch = curl_init(WA_FONNTE_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10, // jangan sampe nge-hang halaman gara-gara Fonnte lemot
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => ['Authorization: ' . $token],
        CURLOPT_POSTFIELDS => ['target' => $target, 'message' => $pesan],
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        error_log("WA Fonnte: curl gagal ke $target - $curlError");
        $alasan = 'koneksi gagal';
        return false;
    }
    if ($httpCode >= 400) {
        error_log("WA Fonnte: HTTP $httpCode ke $target - " . substr($response, 0, 300));
        $alasan = "Fonnte HTTP $httpCode";
        return false;
    }

    $data = json_decode($response, true);
    if (!is_array($data) || ($data['status'] ?? false) !== true) {
        $reason = is_array($data) ? ($data['reason'] ?? 'unknown') : 'response bukan JSON valid';
        error_log("WA Fonnte: gagal kirim ke $target - $reason");
        $alasan = 'Fonnte: ' . (string) $reason;
        return false;
    }

    return true;
}

<?php
// Log aktivitas/audit - siapa ngapain kapan. Cuma buat dibaca admin
// (admin/data_log.php), gak pernah nge-block alur apa pun kalau gagal
// nyimpen (best-effort, sama pola kayak wa_kirim()).

declare(strict_types=1);

/**
 * $nip null kalau aktornya gak punya NIP terhubung (mis. login gagal
 * dengan username yang gak ada), $username diambil dari sesi kalau ada,
 * fallback ke param eksplisit (dipakai check_login.php sebelum sesi
 * kebentuk).
 */
function log_aktivitas(PDO $db, string $aksi, string $keterangan = '', ?string $nip = null, ?string $username = null): void
{
    try {
        $nip ??= $_SESSION['nip'] ?? null;
        $username ??= $_SESSION['username'] ?? '-';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        db_query($db, "INSERT INTO log_aktivitas (nip, username, aksi, keterangan, ip_address) VALUES (?,?,?,?,?)",
            [$nip, $username, $aksi, $keterangan, $ip]);
    } catch (Throwable $e) {
        error_log('Gagal simpan log_aktivitas: ' . $e->getMessage());
    }
}

/**
 * Daftar log terbaru dulu, opsional filter aksi/kata kunci. $limit/$offset
 * di-cast int & di-clamp - aman diinline ke SQL (LIMIT/OFFSET gak bisa
 * lewat placeholder pas ATTR_EMULATE_PREPARES=false).
 */
function log_aktivitas_daftar(PDO $db, string $aksiFilter = '', string $cari = '', int $limit = 50, int $offset = 0): array
{
    $limit = max(1, min(200, $limit));
    $offset = max(0, $offset);
    $where = [];
    $params = [];
    if ($aksiFilter !== '') {
        $where[] = 'aksi = ?';
        $params[] = $aksiFilter;
    }
    if ($cari !== '') {
        $where[] = '(username LIKE ? OR keterangan LIKE ?)';
        $params[] = "%$cari%";
        $params[] = "%$cari%";
    }
    $sql = "SELECT * FROM log_aktivitas";
    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    $sql .= " ORDER BY id_log DESC LIMIT $limit OFFSET $offset";
    return db_all($db, $sql, $params);
}

function log_aktivitas_count(PDO $db, string $aksiFilter = '', string $cari = ''): int
{
    $where = [];
    $params = [];
    if ($aksiFilter !== '') {
        $where[] = 'aksi = ?';
        $params[] = $aksiFilter;
    }
    if ($cari !== '') {
        $where[] = '(username LIKE ? OR keterangan LIKE ?)';
        $params[] = "%$cari%";
        $params[] = "%$cari%";
    }
    $sql = "SELECT COUNT(*) AS n FROM log_aktivitas";
    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    return (int) db_one($db, $sql, $params)['n'];
}

/**
 * Daftar kode aksi yang pernah tercatat - buat dropdown filter, gak
 * hardcode (biar otomatis nambah kalau ada aksi baru ditambahkan nanti).
 */
function log_aktivitas_daftar_kode(PDO $db): array
{
    return array_column(db_all($db, "SELECT DISTINCT aksi FROM log_aktivitas ORDER BY aksi ASC"), 'aksi');
}

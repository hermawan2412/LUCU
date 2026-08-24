<?php
// Koneksi DB pakai PDO + prepared statement. Ini pengganti pola lama
// mysqli_query($koneksi, "...$_POST...") di MACOA yang rawan SQL injection.

declare(strict_types=1);

function db_connect(array $cfg): PDO
{
    $dsn = "mysql:host={$cfg['host']};dbname={$cfg['name']};charset={$cfg['charset']}";
    try {
        return new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        error_log('DB connect failed: ' . $e->getMessage());
        http_response_code(500);
        die('Koneksi database gagal. Hubungi administrator.');
    }
}

/**
 * Helper query prepared statement singkat.
 * Contoh: db_query($db, "SELECT * FROM user WHERE nip = ?", [$nip])
 */
function db_query(PDO $db, string $sql, array $params = []): PDOStatement
{
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_one(PDO $db, string $sql, array $params = []): ?array
{
    $row = db_query($db, $sql, $params)->fetch();
    return $row === false ? null : $row;
}

function db_all(PDO $db, string $sql, array $params = []): array
{
    return db_query($db, $sql, $params)->fetchAll();
}

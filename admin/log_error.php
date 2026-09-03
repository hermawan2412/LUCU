<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('Admin');

// Baca storage/app.log (lihat config/bootstrap.php - ini tujuan error_log()
// di seluruh app, di-set eksplisit karena default php.ini di server ini
// gak nyimpen error_log() ke mana pun). Read-only, gak ada fitur hapus di
// sini - kalau perlu bersihin, lakuin langsung di server.
$logPath = __DIR__ . '/../storage/app.log';
$maxBaris = 300;
$baris = [];

if (is_file($logPath)) {
    // File log bisa gede - baca dari belakang biar gak nge-load semuanya
    // ke memori kalau udah bertahun-tahun jalan. SplFileObject::seek() ke
    // akhir dulu buat tau total baris.
    $fp = new SplFileObject($logPath, 'r');
    $fp->seek(PHP_INT_MAX);
    $totalBaris = $fp->key(); // 0-indexed, baris kosong terakhir ikut kehitung
    $mulai = max(0, $totalBaris - $maxBaris);
    $fp->seek($mulai);
    while (!$fp->eof()) {
        $line = $fp->current();
        if ($line !== false && trim($line) !== '') {
            $baris[] = $line;
        }
        $fp->next();
    }
    $baris = array_reverse($baris); // terbaru dulu
}

layout_header('Log Error Sistem', 'log', 'admin');
?>
<h1>Log Error Sistem</h1>
<p class="lead"><?= $maxBaris ?> baris terakhir dari <code>storage/app.log</code> - error PHP/upaya sistem yang gagal diam-diam (WA gagal kirim, gagal simpan data, dll). <a href="data_log.php" class="btn-secondary" style="padding:4px 14px;font-size:0.78rem;">Log Aktivitas</a> <a href="log_error.php" class="btn-secondary" style="padding:4px 14px;font-size:0.78rem;">Muat Ulang</a></p>

<div class="card">
  <?php if (!is_file($logPath)): ?>
    <div class="empty-state">Belum ada file log (belum pernah ada error tercatat sejak fitur ini aktif).</div>
  <?php elseif (empty($baris)): ?>
    <div class="empty-state">File log ada tapi masih kosong.</div>
  <?php else: ?>
    <pre style="white-space:pre-wrap;word-break:break-word;font-size:0.8rem;line-height:1.5;max-height:70vh;overflow-y:auto;background:var(--surface,#f7f6f4);border:1px solid var(--border,#e5e5e5);border-radius:10px;padding:14px;"><?php foreach ($baris as $l): ?><?= e(rtrim($l, "\n")) ?>
<?php endforeach; ?></pre>
  <?php endif; ?>
</div>
<?php layout_footer(); ?>

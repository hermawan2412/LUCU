<?php
require_once __DIR__ . '/../config/bootstrap.php';
auth_require('User');

$daftar = notifikasi_daftar($db, $_SESSION['nip'], 20);
notifikasi_tandai_semua_dibaca($db, $_SESSION['nip']); // dibuka = dianggap dibaca, kayak inbox pada umumnya

layout_header('Notifikasi', '', 'user');
?>
<h1>Notifikasi</h1>
<p class="lead">Pemberitahuan seputar pengajuan dan persetujuan cuti Anda.</p>

<div class="card">
  <?php if (empty($daftar)): ?>
    <div class="empty-state">Belum ada notifikasi.</div>
  <?php else: ?>
    <div class="notif-list">
      <?php foreach ($daftar as $n): ?>
        <a href="<?= e($n['url'] ?: '#') ?>" class="notif-item<?= (int) $n['dibaca'] === 0 ? ' unread' : '' ?>">
          <div class="notif-dot"></div>
          <div>
            <div class="notif-pesan"><?= e($n['pesan']) ?></div>
            <div class="notif-waktu"><?= e(notifikasi_waktu_relatif($n['dibuat_pada'])) ?></div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php layout_footer(); ?>

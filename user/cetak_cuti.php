<?php
require_once __DIR__ . '/../config/bootstrap.php';

// Diakses User (cuma punya sendiri) atau Admin (siapa aja). Bukan
// auth_require() biasa karena butuh 2 role + cek kepemilikan.
if (!auth_check()) {
    redirect('../index.php');
}

$id = (int) ($_GET['id'] ?? 0);
$cuti = db_one($db, "SELECT c.*, p.nama_pegawai, p.nip, p.unit_kerja, p.tmt_pegawai, j.nama_jabatan, g.nama_golongan, p.id_pegawai
    FROM cuti_pegawai c
    JOIN pegawai p ON p.id_pegawai = c.id_pegawai
    JOIN jabatan j ON j.id_jabatan = p.id_jabatan
    JOIN golongan g ON g.id_golongan = p.id_golongan
    WHERE c.id_cutipegawai = ?", [$id]);

if ($cuti === null) {
    flash_set('error', 'Data cuti tidak ditemukan.');
    redirect($_SESSION['role'] === 'Admin' ? '../admin/index.php' : 'daftar_cuti.php');
}

if ($_SESSION['role'] !== 'Admin' && $cuti['nip'] !== ($_SESSION['nip'] ?? null)) {
    flash_set('error', 'Anda tidak berhak mencetak dokumen ini.');
    redirect('daftar_cuti.php');
}

function nama_pejabat_by_nip(PDO $db, ?string $nip): string
{
    if ($nip === null) {
        return '-';
    }
    $row = db_one($db, "SELECT nama_pegawai FROM pegawai WHERE nip = ?", [$nip]);
    return $row['nama_pegawai'] ?? '-';
}

$namaPanmud = nama_pejabat_by_nip($db, $cuti['panmud_kasubag']);
$namaPaniteraSek = nama_pejabat_by_nip($db, $cuti['panitera_sekretaris']);
$namaKetua = nama_pejabat_by_nip($db, $cuti['ketua']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Formulir Cuti - <?= e($cuti['nama_pegawai']) ?></title>
  <link rel="stylesheet" href="../assets/css/app.css">
  <style>
    body { background: #fff; font-size: 12.5px; }
    .doc {
      max-width: 760px;
      margin: 24px auto;
      padding: 32px;
    }
    .doc-actions { max-width: 760px; margin: 20px auto 0; text-align: right; }
    .doc h1 { font-size: 1.15rem; text-align: center; margin: 0 0 2px; text-transform: uppercase; }
    .doc .subtitle { text-align: center; color: var(--slate); font-size: 0.82rem; margin-bottom: 24px; }
    .doc table { width: 100%; border-collapse: collapse; margin-bottom: 18px; font-size: 12.5px; }
    .doc table td, .doc table th { border: 1px solid #999; padding: 6px 10px; vertical-align: top; }
    .doc .section-title { font-weight: 700; margin: 20px 0 6px; text-transform: uppercase; font-size: 0.82rem; letter-spacing: 0.03em; }
    .doc .label-col { width: 220px; background: #f7f7f5; font-weight: 500; }
    .doc .jenis-list span { display: block; }
    .doc .jenis-list .selected { font-weight: 700; }
    .doc .jenis-list .selected::before { content: "\2611  "; }
    .doc .jenis-list .not-selected::before { content: "\2610  "; color: #999; }
    .signatures { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 18px; margin-top: 28px; text-align: center; }
    .signatures .sig-box { font-size: 11.5px; }
    .signatures .sig-name { margin-top: 60px; font-weight: 700; text-decoration: underline; }
    .signatures .sig-status { margin-top: 4px; }
    @media print {
      .no-print { display: none !important; }
      body { background: #fff; }
      .doc { margin: 0; max-width: none; padding: 0; }
    }
  </style>
</head>
<body>
  <div class="doc-actions no-print">
    <button onclick="window.print()" class="btn-secondary">Cetak / Simpan sebagai PDF</button>
  </div>

  <div class="doc">
    <h1><?= e(APP_INSTANSI) ?></h1>
    <p class="subtitle">Formulir Permintaan dan Pemberian Cuti Pegawai</p>

    <div class="section-title">I. Data Pegawai</div>
    <table>
      <tr><td class="label-col">Nama Lengkap</td><td><?= e($cuti['nama_pegawai']) ?></td></tr>
      <tr><td class="label-col">NIP</td><td><?= e($cuti['nip']) ?></td></tr>
      <tr><td class="label-col">Jabatan</td><td><?= e($cuti['nama_jabatan']) ?></td></tr>
      <tr><td class="label-col">Golongan</td><td><?= e($cuti['nama_golongan']) ?></td></tr>
      <tr><td class="label-col">Unit Kerja</td><td><?= e($cuti['unit_kerja']) ?></td></tr>
      <tr><td class="label-col">Masa Kerja</td><td><?= e($cuti['masa_kerja']) ?></td></tr>
    </table>

    <div class="section-title">II. Jenis Cuti yang Diambil</div>
    <table>
      <tr><td class="jenis-list">
        <?php foreach (cuti_leave_types() as $type): ?>
          <span class="<?= $type === $cuti['jenis_cuti'] ? 'selected' : 'not-selected' ?>"><?= e($type) ?></span>
        <?php endforeach; ?>
      </td></tr>
    </table>

    <div class="section-title">III. Alasan Cuti</div>
    <table><tr><td><?= e($cuti['alasan_cuti']) ?></td></tr></table>

    <div class="section-title">IV. Lamanya Cuti</div>
    <table>
      <tr><td class="label-col">Dari Tanggal</td><td><?= e($cuti['dari_tanggal']) ?></td></tr>
      <tr><td class="label-col">Sampai Dengan</td><td><?= e($cuti['sampai_dengan']) ?></td></tr>
      <tr><td class="label-col">Lama</td><td><?= e($cuti['lama_cuti']) ?> <?= e($cuti['ket_lama_cuti']) ?></td></tr>
      <tr><td class="label-col">Alamat Selama Cuti</td><td><?= e($cuti['alamat_cuti']) ?></td></tr>
    </table>

    <div class="section-title">V. Status Permohonan</div>
    <table>
      <tr><td class="label-col">Tanggal Pengajuan</td><td><?= e($cuti['tgl_pengajuan']) ?></td></tr>
      <tr><td class="label-col">Status</td><td><strong><?= e($cuti['status_cuti']) ?></strong> &mdash; <?= e($cuti['ket_status_cuti']) ?></td></tr>
      <tr><td class="label-col">Sisa Cuti Tahunan</td><td><?= e((string) $cuti['sisa_cuti']) ?> hari</td></tr>
    </table>

    <div class="section-title">VI. Persetujuan</div>
    <div class="signatures">
      <div class="sig-box">
        Atasan Langsung
        <div class="sig-name"><?= e($namaPanmud) ?></div>
        <div class="sig-status"><?= $cuti['app_panmud_kasubag'] ? 'Disetujui' : 'Menunggu' ?></div>
      </div>
      <div class="sig-box">
        Panitera / Sekretaris
        <div class="sig-name"><?= e($namaPaniteraSek) ?></div>
        <div class="sig-status"><?= $cuti['app_panitera_sekretaris'] ? 'Disetujui' : 'Menunggu' ?></div>
      </div>
      <div class="sig-box">
        Pejabat Pemberi Izin
        <div class="sig-name"><?= e($namaKetua) ?></div>
        <div class="sig-status"><?= $cuti['app_ketua'] ? 'Disetujui' : 'Menunggu' ?></div>
      </div>
    </div>
  </div>
</body>
</html>

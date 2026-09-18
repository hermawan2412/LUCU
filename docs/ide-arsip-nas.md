# Ide: Auto-arsip dokumen output ke NAS

Status: **belum diimplementasi** — dicatat 2026-09-18 buat dieksekusi nanti (RESTU atau project sejenis lain).

## Masalah

Dokumen final (surat cuti .docx/.pdf) yang di-generate RESTU gak pernah disimpan permanen di
mana pun. Alurnya sekarang (`includes/cuti_docx.php`): generate ke temp file
(`sys_get_temp_dir()`) -> `readfile()` stream ke browser user -> temp file abis itu ilang.
Gak ada arsip yang bisa dicari/dibuka ulang di luar aplikasi.

## Ide

Auto-arsip tiap dokumen final yang di-generate ke folder di NAS (Synology, DSM) milik user.
NAS bisa diakses dari internet (ada DDNS/port forward), jadi VPS RESTU (`aurat-vps`,
103.129.149.242) bisa konek langsung.

## Desain yang diusulkan

Dua bagian, sengaja dipisah biar gak saling blokir:

1. **App-side (synchronous, wajib reliable):** abis `$tp->save()` sukses di
   `cuti_docx.php`, copy 1 salinan ke folder arsip lokal permanen di VPS, misal
   `/var/www/restu/storage/arsip/<tahun>/<nip>-<nomor_surat>.docx`. Ini tetap jalan walau
   NAS lagi down - gak nge-block proses download user.
2. **VPS -> NAS (async, via cron):** cron job `rsync -avz` over SSH tiap N menit, dari
   `storage/arsip/` ke folder tujuan di NAS. Kalau NAS unreachable, cuma skip - retry
   otomatis di run berikutnya. Gak taruh logic push-network di request path aplikasi.

Kenapa cron+rsync, bukan push langsung dari PHP tiap generate: lebih tahan gangguan
jaringan, gak nambah latency ke user pas download surat, dan satu mekanisme sync bisa
dipake ulang buat folder lain/project lain tanpa nulis ulang kode app.

## Yang dibutuhin sebelum eksekusi

- SSH service nyala di Synology (Control Panel -> Terminal & SNMP)
- User NAS khusus buat sync ini (bukan admin), + folder tujuan (misal
  `/volume1/Arsip-Cuti-RESTU`)
- Hostname/DDNS NAS + port SSH custom
- Keypair baru khusus VPS -> NAS (jangan reuse `id_ed25519_aurat_vps`), public key
  ditaruh di `authorized_keys` user NAS tsb
- Naming convention file arsip yang jelas (tahun/NIP/nomor surat) biar gampang dicari manual

## Berlaku juga buat project lain

Pola app-side-permanent-copy + cron-rsync-ke-NAS ini generik - bisa dipake ulang di
project manapun yang generate dokumen/laporan dan perlu arsip di luar server aplikasi
(server aplikasi sering disposable/VPS murah, NAS lebih awet buat penyimpanan jangka
panjang).

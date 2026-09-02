# RESTU (Rekam Elektronik Sistem cuTi Untuk aparatur)
Pengadilan Agama Rantau — manajemen cuti pegawai online.

Fork/rebuild dari MACOA (PTA Sulawesi Barat), sistem & output dipertahankan sama,
tampilan dibuat ulang, celah keamanan yang ditemukan di analisis awal diperbaiki:

- Prepared statement (PDO) di semua query — ganti concat string mysqli
- Password di-hash bcrypt (`password_hash`/`password_verify`) — ganti MD5 polos
- Kredensial DB di `config/config.php` (gitignored) — ganti hardcode di source
- CSRF token per form
- `session_start()` di baris pertama tiap entry point, cookie httponly+samesite
- Semua output di-escape (`e()` / htmlspecialchars) — cegah XSS
- Tidak ada file test/copy/error_log ikut deploy

## Setup lokal
1. `cp config/config.example.php config/config.php`, isi kredensial DB lokal
2. `mysql <db_lokal> < database/lucu.sql`
3. `php database/seed.php` — bikin akun dummy (lihat output untuk NIP/password)
4. `php -S localhost:8899`

## Status
Alur login (index -> check_login -> dashboard admin/user) sudah jalan & diuji.
Modul cuti, approval, KGB, KNP, export/PDF: belum diporting dari MACOA.

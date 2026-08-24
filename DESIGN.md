# LUCU — Design System

Panduan desain visual LUCU (Aplikasi Untuk Cuti), Pengadilan Agama Rantau.
Semua token & komponen didefinisikan di `assets/css/app.css`; dokumen ini
menjelaskan *kenapa* pilihannya begitu, biar konsisten kalau ada modul baru
atau ada yang mau nyesuaikan brand di kemudian hari.

## Prinsip

**Formal institusional, dipoles.** Ini aplikasi pemerintah/peradilan —
bukan startup consumer app. Warna hijau-emas (identik dunia peradilan
Indonesia) dipertahankan, tapi tipografi, spacing, dan detail interaksi
digarap serius biar terasa modern tanpa kehilangan kesan berwibawa.

**Mandiri, gak gantung CDN.** Font di-self-host (`assets/fonts/`), bukan
`<link>` ke Google Fonts langsung. Aplikasi ini kemungkinan jalan di
jaringan instansi yang gak selalu punya akses internet stabil — jangan
sampai halaman gagal render cuma gara-gara CDN font lambat/kepotong.

**Konsisten lewat token, bukan nilai hardcode.** Semua warna, radius,
shadow didefinisikan sebagai CSS custom property di `:root`. Halaman baru
tinggal pakai class yang udah ada (`.card`, `.badge-*`, `.btn-primary`,
dst) — jangan nulis warna/ukuran baru langsung di file PHP.

## Warna

| Token | Nilai | Dipakai buat |
|---|---|---|
| `--court-green` | `#0b5533` | Aksen utama, tombol primer, brand mark |
| `--court-green-dark` | `#073b23` | Hover state, gradient login |
| `--court-green-tint` | `#e5f0e9` | Background badge aktif, focus ring |
| `--court-gold` | `#b8912a` | Aksen sekunder — dipakai **seperlunya** (garis bawah nav aktif, aksen hero), bukan warna dominan |
| `--court-gold-tint` | `#f6efdc` | Teks/badge di atas latar hijau gelap |
| `--ink` / `--ink-muted` / `--ink-faint` | `#17231c` / `#57685d` / `#8b998f` | Teks utama/sekunder/tersier — neutral condong hijau, bukan abu-abu murni |
| `--surface` / `--surface-2` / `--bg` | putih / `#f5f7f4` / `#eef1ec` | Kartu / panel dalam kartu / latar halaman |
| `--border` / `--border-strong` | `#dde4dc` / `#c3cec3` | Garis tipis / garis yang perlu lebih tegas (mis. header tabel) |

**Semantik terpisah dari brand:** `--danger`, `--success`, `--warning`
(dengan versi `-bg` masing-masing) dipakai buat status (badge cuti
disetujui/ditolak, KGB jatuh tempo, dst) — jangan pinjam warna brand buat
ini, biar gak ambigu.

## Tipografi

Tiga peran, dipasang lewat `@font-face` self-hosted di baris paling atas
`app.css`:

1. **Newsreader** (serif, variable font 400–600 + italic 400) — semua
   `h1`/`h2`/`h3`, judul halaman, hero login. Kasih kesan institusional
   tanpa berat.
2. **Public Sans** (sans, variable 400–700) — body text, label, tombol,
   nav. Dipilih karena ini font resmi USWDS (US Web Design System) buat
   layanan sipil/pemerintah — legible, netral, gak "AI generic" (bukan
   Inter/Space Grotesk).
3. **IBM Plex Mono** (400 & 500) — data presisi: username di topbar,
   badge instansi di login, footer. Dipakai buat hal yang terasa kayak
   "kode/ID", bukan buat body text panjang.

Kalau nambah berat/style baru, unduh dari Google Fonts subset **latin
only** (`unicode-range: U+0000-00FF...`), simpan `.woff2` di
`assets/fonts/`, daftarkan `@font-face` baru — jangan pasang `<link>` ke
`fonts.googleapis.com`.

## Bentuk & Kedalaman

- Radius: `--radius` (12px, kartu/tombol besar), `--radius-sm` (8px,
  input/tombol kecil/badge kecil)
- Shadow: `--shadow-sm` (kartu, topbar) dan `--shadow-md` (kartu yang
  perlu menonjol, mis. kartu login) — selalu halus, warnanya condong
  hijau gelap transparan (`rgba(11,30,20,...)`), bukan abu-abu netral,
  biar shadow-nya "milik" palet ini bukan generik.

## Brand mark

SVG perisai + centang (`brand_mark_svg()` di `includes/helpers.php`),
inline dan `currentColor` — dipasang di topbar (24px) dan hero login
(34px). Motifnya sengaja: perisai = institusi/resmi, centang = disetujui
— nyambung ke fungsi utama app (alur approval cuti). Ini pengganti logo
gambar; kalau instansi punya lambang resmi (logo Pengadilan Agama Rantau)
yang mau dipasang, ganti fungsi ini jadi `<img>` ke asetnya, jangan hapus
pola pemanggilannya di layout/login.

## Komponen kunci

Semua ada di `assets/css/app.css`, dipetakan lewat class:

- **Kartu** — `.card` (kotak putih, border tipis, shadow halus, radius
  besar). Dipakai buat bungkus form dan tabel di semua halaman admin.
- **Stat tile** — `.stat-row` + `.stat-tile`: grid `auto-fill` dengan
  track max (168–220px), **bukan flexbox**. Ini sengaja — flexbox wrap
  bikin item terakhir yang sendirian di baris baru stretch penuh lebar
  kalau jumlahnya ganjil (kejadian nyata pas dashboard admin ada 5 stat
  tile). Kalau nambah stat tile baru di halaman manapun, jangan balikin
  ke flexbox.
- **Badge status** — `.badge-success` / `.badge-warning` / `.badge-danger`
  / `.badge-neutral`. Dipetakan dari fungsi status per modul
  (`cuti_status_badge_class()`, `kgb_status_badge_class()`,
  `knp_status_badge_class()`), bukan hardcode class di PHP.
- **Tabel data** — `.data-table`: header uppercase+letter-spacing, border
  bawah tegas, row hover halus (`--surface-2`). Untuk kolom angka/badge,
  ada aturan `font-variant-numeric: tabular-nums` otomatis.
- **Tombol** — `.btn-primary` (aksi utama, hijau solid) dan
  `.btn-secondary` (aksi sekunder, outline). Konsisten dipakai di semua
  form create/edit/delete.
- **Topbar** — `sticky`, shadow halus, nav aktif ditandai underline emas
  + background tint hijau (bukan cuma warna teks beda — biar kelihatan
  dari jauh/sekilas).

## Kalau mau ubah brand di masa depan

1. Ganti nilai token warna di `:root` (`app.css`) — semua komponen ikut
   berubah otomatis, gak perlu edit file lain.
2. Ganti font: unduh subset latin baru, taruh di `assets/fonts/`, update
   3 `@font-face` block + variable `--font-display`/`--font-body`/
   `--font-mono`.
3. Ganti brand mark: edit `brand_mark_svg()` di `includes/helpers.php`.

Belum digarap sengaja: **dark mode** (di luar scope permintaan awal —
aplikasi ini gak butuh, beda dari Artifact yang wajib dukung tema
viewer) dan **layout sidebar** (topbar dipilih secara eksplisit,
lihat riwayat keputusan di sesi Claude Code kalau mau tau alasannya).

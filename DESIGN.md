# RESTU Design Direction

Dial: ENERGY 2 / RHYTHM 1 / MOTION 2

## Who it is for

RESTU is the leave-request (cuti) app for Pengadilan Agama Rantau. Its users are court employees (PNS and PPPK), their supervisors, the Ketua, and the admin staff. They open it to submit leave, approve a request, check remaining leave, or print an official form. Most visits are short and task-driven, on an office PC or a phone.

The design should feel like a well-kept office ledger seen through glass: the work stays calm and readable, while the weekday photo behind it gives each day its own place in Indonesia. It is not a marketing site.

## Identity

- **Canvas:** warm paper cream (`--canvas #f3f0ee`), with white surfaces for cards and forms. Reason: it reads like a paper form, which is what a leave request ultimately becomes (it is printed and signed).
- **Ink:** warm near-black (`--ink #141413`) for text and primary buttons. Reason: it looks formal without the harshness of pure black.
- **One accent:** signal orange (`--signal #cf4500`). Use it only for the active navigation item, focus rings, and the one thing on a screen that needs action (for example "menunggu persetujuan Anda"). Never use it for decoration.
- **Status colours:** use `--success`, `--warning`, and `--danger` (plus their `-bg`) only. A status colour means a state (Disetujui, Menunggu, Ditolak/Habis), never a category.
- **Typeface:** Sofia Sans (self-hosted woff2), one family only. Reason: it is humanist and readable for long Indonesian names and NIP numbers, and it renders well at small sizes in tables. Use weight 450 for body text and 500 for headings.
- **Shape:** cards use 28px radius (`--radius`), inputs 16px, chips and buttons the pill radius. Tables and printed-form previews stay square-edged, because they represent documents.
- **Identity motif:** the paper card on cream canvas, plus the hand-drawn check mark (the "done" animation and the large ticks on the form preview). A finished approval is the moment of the app.

## Layout

- The top bar holds the brand, the role, the navigation (grouped dropdowns), the theme toggle, and the notification bell. There is no footer beyond the page.
- Each page has one focal point: a form, a table, or the calendar. The dashboard uses stat tiles for counts, and each tile links to its list.
- Pages are uniform on purpose (RHYTHM 1). Staff learn one pattern and reuse it.
- Tables scroll horizontally inside `.table-scroll` on mobile. The page itself never overflows.

## Motion (MOTION 2)

- Allowed: hover and press feedback, the view-transition menu motion, the done animation after submit or approve, and the calendar lens.
- Everything sits inside `prefers-reduced-motion: no-preference`. Nothing loops longer than 5 seconds.

## Liquid Glass and the weekday backdrop (owner decision, 2026-11-24)

- Each working day has its own photo theme: Senin mountains, Selasa beach, Rabu the view from an airplane window, Kamis rice terraces, and Jumat Toraja tongkonan houses. Every theme has two photos (a/b). `latar_hari()` in `includes/helpers.php` picks one per date, so the photo stays the same on every page for the whole day. Sabtu and Minggu use the Jumat theme.
- Photos are free Unsplash images, served as WebP from `assets/img/latar/` (1920px, plus a 960px version for screens up to 960px wide).
- Every panel is glass: the topbar, cards, stat tiles, dropdown menus, dialogs, the calendar, and the login card. The owner chose full glass, which overrides antislop R-10's cap of 1-2 glass elements. This is a deliberate exception.
- Glass recipe: blur 28px with saturation 180%, a diagonal sheen, a bright top edge, inner depth, and two shadows that make each panel float above the photo. The topbar floats highest. In Chromium, the topbar also refracts the photo through an SVG displacement filter.
- Readability is non-negotiable. The photo sits under a scrim (white 38% in light mode, black 50% in dark mode). Panels people read for a long time (cards, tables, dialogs) use 86% opacity, and the rest use 74%. `--slate` is darkened in light mode. Page titles that sit directly on the photo get a soft text halo.
- `prefers-reduced-transparency` and forced colours turn the panels back into solid surfaces.

## Themes

- Light and dark both ship. Only neutral and semantic tokens flip. The accent, font, and radius stay the same, so the identity holds in both themes.

## Copy

- Use plain Indonesian office language: "Ajukan Cuti", "Setujui", "Tolak", "Unduh Formulir". The verb names the action.
- No em dashes, no marketing words, no invented numbers. Every number shown comes from the database.

## Do not

- Do not copy another brand's design system.
- Do not add colours for categories. Do not add a second typeface.
- Do not add decoration that does not mark a state or an action.
- Do not add a photo without the owner's approval. Add a new backdrop photo as an a/b pair inside an existing day theme.

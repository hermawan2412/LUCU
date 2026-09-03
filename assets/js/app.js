// Count-up halus buat angka statistik (stat tile, kotak info login).
// Cuma jalan kalau user gak minta reduced motion.
(function () {
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    return;
  }

  var targets = document.querySelectorAll('.stat-tile .num, .login-stat-num');
  targets.forEach(function (el) {
    var text = el.textContent.trim();
    var match = text.match(/^(\d+)(.*)$/); // angka di depan (biasa ada suffix kayak '%', atau lanjutan teks kayak ' Tahun 2 Bulan')
    if (!match) return;
    var end = parseInt(match[1], 10);
    var suffix = match[2];
    if (isNaN(end) || end === 0) return;

    var duration = 650;
    var start = null;
    el.textContent = '0' + suffix;

    function step(ts) {
      if (!start) start = ts;
      var progress = Math.min((ts - start) / duration, 1);
      var eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
      el.textContent = Math.round(eased * end) + suffix;
      if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  });
})();

// Toggle tema: Auto -> Terang -> Gelap -> Auto. 'Auto' berarti data-theme
// dilepas dari <html>, biar CSS @media (prefers-color-scheme) yang nentuin
// (ikut OS). Pilihan manual disimpan localStorage, DAN dicek lagi lewat
// inline <script> di <head> (lihat includes/layout.php) SEBELUM app.js ini
// sempat kemuat - itu yang nyegah kedipan tema salah pas halaman dibuka.
(function () {
  var KEY = 'restu-theme';
  var btn = document.querySelector('.theme-toggle');
  if (!btn) return;

  var LABEL = { auto: 'Tema: Otomatis (ikut sistem)', light: 'Tema: Terang', dark: 'Tema: Gelap' };
  var URUTAN = ['auto', 'light', 'dark'];

  function modeSekarang() {
    var v = null;
    try { v = localStorage.getItem(KEY); } catch (e) {}
    return (v === 'light' || v === 'dark') ? v : 'auto';
  }

  function terapkan(mode) {
    if (mode === 'light' || mode === 'dark') {
      document.documentElement.setAttribute('data-theme', mode);
    } else {
      document.documentElement.removeAttribute('data-theme');
    }
    btn.setAttribute('data-mode', mode);
    btn.title = LABEL[mode];
  }

  terapkan(modeSekarang()); // koreksi tombol (default render PHP selalu "auto")

  btn.addEventListener('click', function () {
    var mode = URUTAN[(URUTAN.indexOf(modeSekarang()) + 1) % URUTAN.length];
    try { localStorage.setItem(KEY, mode); } catch (e) {}
    terapkan(mode);
  });
})();

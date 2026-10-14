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

// Dropdown nav ("Data Master" dkk) - klik toggle, bukan :hover (biar jalan
// jg di mobile/tap). Klik di luar atau pilih salah satu link nutup lagi.
(function () {
  var dropdowns = document.querySelectorAll('.nav-dropdown');
  if (!dropdowns.length) return;

  dropdowns.forEach(function (dd) {
    var toggle = dd.querySelector('.nav-dropdown-toggle');
    toggle.addEventListener('click', function (e) {
      e.stopPropagation();
      var sudahKebuka = dd.classList.contains('open');
      dropdowns.forEach(function (other) { other.classList.remove('open'); });
      if (!sudahKebuka) dd.classList.add('open');
    });
  });

  document.addEventListener('click', function () {
    dropdowns.forEach(function (dd) { dd.classList.remove('open'); });
  });
})();

// Bunyi notifikasi: bunyi cuma sekali pas jumlah notif belum-dibaca NAIK
// dibanding kunjungan terakhir (localStorage), bukan tiap buka halaman
// selagi masih ada yg belum dibaca - getar iconnya sendiri udah dihandle
// CSS (.notif-bell.has-unread, lihat app.css bell-ring).
(function () {
  var KEY = 'restu-notif-count-seen';
  var bell = document.querySelector('.notif-bell');
  if (!bell) return;

  var count = parseInt(bell.getAttribute('data-count') || '0', 10);
  var seen = 0;
  try { seen = parseInt(localStorage.getItem(KEY) || '0', 10); } catch (e) {}

  if (count > seen) {
    try {
      var ctx = new (window.AudioContext || window.webkitAudioContext)();
      var osc = ctx.createOscillator();
      var gain = ctx.createGain();
      osc.frequency.value = 880;
      gain.gain.setValueAtTime(0.15, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);
      osc.connect(gain).connect(ctx.destination);
      osc.start();
      osc.stop(ctx.currentTime + 0.35);
    } catch (e) {}
  }

  try { localStorage.setItem(KEY, String(count)); } catch (e) {}
})();

// Pratinjau formulir cuti: link [data-pratinjau] dibuka di <dialog> berisi
// iframe PDF. Layar sentuh (HP) dibiarkan buka tab baru - viewer PDF bawaan
// HP umumnya gak bisa render PDF di dalam iframe.
(function () {
  if (!window.HTMLDialogElement || window.matchMedia('(pointer: coarse)').matches) return;
  var dlg = null, frame;
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[data-pratinjau]');
    if (!a || e.ctrlKey || e.metaKey || e.shiftKey) return;
    e.preventDefault();
    if (!dlg) {
      dlg = document.createElement('dialog');
      dlg.className = 'pratinjau';
      dlg.innerHTML = '<div class="pratinjau-bar"><strong></strong>'
        + '<span class="sub">Dokumen resmi hanya setelah Disetujui</span>'
        + '<a class="btn-secondary" target="_blank">Buka di tab baru</a>'
        + '<button type="button" class="btn-secondary">Tutup</button></div>'
        + '<p class="pratinjau-muat">Menyiapkan dokumen&hellip;</p><iframe title="Pratinjau formulir cuti"></iframe>';
      frame = dlg.querySelector('iframe');
      dlg.querySelector('button').addEventListener('click', function () { dlg.close(); });
      dlg.addEventListener('click', function (ev) { if (ev.target === dlg) dlg.close(); });
      dlg.addEventListener('close', function () { frame.removeAttribute('src'); });
      frame.addEventListener('load', function () { dlg.classList.add('siap'); });
      document.body.appendChild(dlg);
    }
    var judul = a.getAttribute('data-pratinjau');
    dlg.querySelector('.pratinjau-bar strong').textContent = judul || 'Pratinjau formulir';
    dlg.querySelector('.pratinjau-bar .sub').hidden = !!judul; // catatan "resmi setelah Disetujui" cuma buat formulir
    frame.title = judul || 'Pratinjau formulir cuti';
    dlg.classList.remove('siap');
    dlg.querySelector('.pratinjau-bar a').href = a.href;
    frame.src = a.href;
    dlg.showModal();
  });
})();

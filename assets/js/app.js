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

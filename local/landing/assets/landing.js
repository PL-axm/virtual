(function () {
  'use strict';

  // Menu movil.
  var drawer = document.getElementById('menu-movil');
  var openBtn = document.querySelector('[data-menu-open]');
  function setMenu(open) {
    if (!drawer || !openBtn) { return; }
    drawer.hidden = !open;
    openBtn.setAttribute('aria-expanded', String(open));
    document.body.style.overflow = open ? 'hidden' : '';
    if (open) {
      var first = drawer.querySelector('button, a');
      if (first) { first.focus(); }
    } else {
      openBtn.focus();
    }
  }
  if (openBtn) {
    openBtn.addEventListener('click', function () { setMenu(true); });
  }
  if (drawer) {
    drawer.addEventListener('click', function (e) {
      if (e.target === drawer || e.target.closest('[data-menu-close]')) { setMenu(false); }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !drawer.hidden) { setMenu(false); }
    });
  }

  // Filtro de cursos.
  var filterBtns = document.querySelectorAll('.filters button');
  var courses = document.querySelectorAll('#lista-cursos .course');
  Array.prototype.forEach.call(filterBtns, function (btn) {
    btn.addEventListener('click', function () {
      var f = btn.getAttribute('data-filter');
      Array.prototype.forEach.call(filterBtns, function (b) {
        var active = b === btn;
        b.classList.toggle('is-active', active);
        b.setAttribute('aria-pressed', String(active));
      });
      Array.prototype.forEach.call(courses, function (c) {
        c.hidden = !(f === 'all' || c.getAttribute('data-category') === f);
      });
    });
  });

  // Simulador de impacto.
  var range = document.getElementById('sim-range');
  if (range) {
    var count = document.getElementById('sim-count');
    var savings = document.getElementById('sim-savings');
    var hours = document.getElementById('sim-hours');
    var cta = document.getElementById('sim-cta');
    var update = function () {
      var n = parseInt(range.value, 10);
      count.textContent = n + ' personas';
      savings.textContent = Math.min(85, Math.round(55 + n * 0.2)) + '%';
      hours.textContent = '~' + (n * 4) + ' hrs';
      cta.href = 'https://wa.me/573147237457?text=' + encodeURIComponent(
        'Hola, usé el simulador en virtual.enelmapa.co. Quisiera cotizar la capacitación para ' + n + ' colaboradores.');
    };
    range.addEventListener('input', update);
    update();
  }

  // Pestaña activa de la barra inferior segun la seccion visible.
  var tabs = document.querySelectorAll('.tabs a');
  if ('IntersectionObserver' in window && tabs.length) {
    var byId = {};
    Array.prototype.forEach.call(tabs, function (t) { byId[t.getAttribute('href').slice(1)] = t; });
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting && byId[en.target.id]) {
          Array.prototype.forEach.call(tabs, function (t) { t.classList.remove('is-active'); });
          byId[en.target.id].classList.add('is-active');
        }
      });
    }, {rootMargin: '-45% 0px -50% 0px'});
    Object.keys(byId).forEach(function (id) {
      var el = document.getElementById(id);
      if (el) { io.observe(el); }
    });
  }
})();

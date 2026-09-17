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

  // Modal con el temario de cada curso.
  var WA = 'https://wa.me/573147237457?text=';
  var CURSOS = {
    comase: {
      titulo: 'Comunicación Asertiva',
      categoria: 'Habilidades blandas',
      imagen: 'comunicacion.jpg',
      disponible: true,
      meta: ['3 semanas', 'Lecciones cortas', 'Certificado digital'],
      intro: 'Desarrolla habilidades para comunicarte con claridad, empatía y efectividad en el trabajo. ' +
        'Cada módulo termina con algo que se puede aplicar al día siguiente.',
      nota: 'El certificado se emite al aprobar la evaluación final con 70 % o más, e incluye un código para verificarlo en línea.',
      cta: 'Inscribir a mi equipo',
      mensaje: 'Hola, vi el temario de Comunicación Asertiva y quiero inscribir a mi equipo.'
    },
    finanzas: {
      titulo: 'Finanzas Personales',
      categoria: 'Finanzas',
      imagen: 'finanzas.jpg',
      disponible: false,
      meta: ['4 semanas', 'Contenido previsto'],
      intro: 'Manejar el dinero, crear presupuestos, ahorrar e invertir de forma inteligente. ' +
        'Este curso está en preparación: así está planeado su contenido.',
      nota: 'Déjanos tus datos por WhatsApp y te avisamos apenas abra, con la tarifa para tu empresa.',
      cta: 'Quiero que me avisen',
      mensaje: 'Hola, me interesa el curso de Finanzas Personales. ¿Me avisan cuándo abre?'
    },
    liderazgo: {
      titulo: 'Liderazgo para Equipos',
      categoria: 'Liderazgo',
      imagen: 'liderazgo.jpg',
      disponible: false,
      meta: ['4 semanas', 'Contenido previsto'],
      intro: 'Herramientas para liderar equipos, tomar decisiones bajo presión y motivar al grupo de trabajo. ' +
        'Este curso está en preparación: así está planeado su contenido.',
      nota: 'Déjanos tus datos por WhatsApp y te avisamos apenas abra, con la tarifa para tu empresa.',
      cta: 'Quiero que me avisen',
      mensaje: 'Hola, me interesa el curso de Liderazgo para Equipos. ¿Me avisan cuándo abre?'
    },
    servicio: {
      titulo: 'Servicio al Cliente',
      categoria: 'Atención al cliente',
      imagen: 'servicio.jpg',
      disponible: false,
      meta: ['2 semanas', 'Contenido previsto'],
      intro: 'Técnicas para ofrecer una atención excepcional, resolver fricciones y fidelizar clientes. ' +
        'Este curso está en preparación: así está planeado su contenido.',
      nota: 'Déjanos tus datos por WhatsApp y te avisamos apenas abra, con la tarifa para tu empresa.',
      cta: 'Quiero que me avisen',
      mensaje: 'Hola, me interesa el curso de Servicio al Cliente. ¿Me avisan cuándo abre?'
    },
    excel: {
      titulo: 'Excel para el Trabajo',
      categoria: 'Herramientas digitales',
      imagen: 'excel.jpg',
      disponible: false,
      meta: ['3 semanas', 'Contenido previsto'],
      intro: 'Fórmulas clave, tablas dinámicas y reportes para el día a día. ' +
        'Este curso está en preparación: así está planeado su contenido.',
      nota: 'Déjanos tus datos por WhatsApp y te avisamos apenas abra, con la tarifa para tu empresa.',
      cta: 'Quiero que me avisen',
      mensaje: 'Hola, me interesa el curso de Excel para el Trabajo. ¿Me avisan cuándo abre?'
    },
    sst: {
      titulo: 'Seguridad y Salud en el Trabajo',
      categoria: 'Normatividad legal',
      imagen: 'sst.jpg',
      disponible: false,
      meta: ['3 semanas', 'Contenido previsto'],
      intro: 'Normativa colombiana y buenas prácticas para mantener un entorno laboral seguro. ' +
        'Este curso está en preparación: así está planeado su contenido.',
      nota: 'Déjanos tus datos por WhatsApp y te avisamos apenas abra, con la tarifa para tu empresa.',
      cta: 'Quiero que me avisen',
      mensaje: 'Hola, me interesa el curso de Seguridad y Salud en el Trabajo. ¿Me avisan cuándo abre?'
    }
  };

  var modal = document.getElementById('modal-curso');
  if (modal) {
    var base = (document.querySelector('.course__img img') || {}).src || '';
    base = base.replace(/[^/]+$/, '');

    var abrir = function (clave) {
      var c = CURSOS[clave];
      var tpl = document.querySelector('[data-temario="' + clave + '"]');
      if (!c || !tpl) { return; }

      var img = document.getElementById('modal-imagen');
      img.src = base + c.imagen;
      img.alt = 'Curso ' + c.titulo;
      document.getElementById('modal-categoria').textContent = c.categoria;
      document.getElementById('modal-titulo').textContent = c.titulo;
      document.getElementById('modal-intro').textContent = c.intro;
      document.getElementById('modal-nota').textContent = c.nota;

      var meta = document.getElementById('modal-meta');
      meta.innerHTML = '';
      var estado = document.createElement('span');
      estado.className = 'chip ' + (c.disponible ? 'chip--ok' : '');
      estado.textContent = c.disponible ? 'Disponible' : 'Próximamente';
      meta.appendChild(estado);
      c.meta.forEach(function (m) {
        var t = document.createElement('span');
        t.className = 'tag';
        t.textContent = m;
        meta.appendChild(t);
      });

      var cont = document.getElementById('modal-contenido');
      cont.innerHTML = '';
      cont.appendChild(tpl.content.cloneNode(true));

      var cta = document.getElementById('modal-cta');
      cta.textContent = c.cta;
      cta.href = WA + encodeURIComponent(c.mensaje);

      if (typeof modal.showModal === 'function') {
        modal.showModal();
      } else {
        modal.setAttribute('open', '');
      }
      document.body.style.overflow = 'hidden';
      modal.scrollTop = 0;
    };

    var cerrar = function () {
      if (typeof modal.close === 'function') { modal.close(); } else { modal.removeAttribute('open'); }
    };

    document.addEventListener('click', function (e) {
      var disparador = e.target.closest('[data-curso]');
      if (disparador) {
        e.preventDefault();
        abrir(disparador.getAttribute('data-curso'));
        return;
      }
      if (e.target.closest('[data-modal-close]') || e.target === modal) { cerrar(); }
    });

    modal.addEventListener('close', function () { document.body.style.overflow = ''; });
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

/*
 * Sancta — comportamento do site (sem dependências).
 * Carregado com nonce da CSP; nenhum manipulador inline. Tudo é aprimoramento progressivo:
 * sem JavaScript, os links e formulários continuam funcionando.
 */
(function () {
  'use strict';

  var root = document.documentElement;

  function store(key, value) {
    try {
      if (value === undefined) return window.localStorage.getItem(key);
      window.localStorage.setItem(key, value);
    } catch (e) { /* armazenamento indisponível (modo privado): segue sem lembrar */ }
    return null;
  }

  /* ---------- Tema: automático → claro → escuro ---------- */
  var themeBtn = document.getElementById('theme-toggle');
  if (themeBtn) {
    var labels = { system: 'automático', light: 'claro', dark: 'escuro' };
    var icons = {
      system: '<path d="M3 5h18v11H3z"/><path d="M8 20h8M12 16v4"/>',
      light: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
      dark: '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>'
    };
    var current = function () { return root.getAttribute('data-theme') || 'system'; };
    var paint = function () {
      var t = current();
      themeBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + icons[t] + '</svg>';
      themeBtn.setAttribute('aria-label', 'Tema: ' + labels[t] + '. Alterar tema');
      themeBtn.title = 'Tema: ' + labels[t];
    };
    themeBtn.addEventListener('click', function () {
      var order = ['system', 'light', 'dark'];
      var next = order[(order.indexOf(current()) + 1) % order.length];
      if (next === 'system') root.removeAttribute('data-theme'); else root.setAttribute('data-theme', next);
      document.cookie = 'sancta_theme=' + next + '; path=/; max-age=31536000; samesite=lax' + (location.protocol === 'https:' ? '; secure' : '');
      paint();
      document.dispatchEvent(new CustomEvent('sancta:theme'));
    });
    paint();
  }

  /* ---------- Menu móvel ---------- */
  var header = document.querySelector('.site-header');
  var menuBtn = document.getElementById('menu-toggle');
  var nav = document.getElementById('nav-links');
  if (header && menuBtn && nav) {
    var setMenu = function (open, focusButton) {
      header.classList.toggle('menu-open', open);
      menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      menuBtn.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
      if (!open && focusButton) menuBtn.focus();
    };
    menuBtn.addEventListener('click', function () { setMenu(!header.classList.contains('menu-open')); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && header.classList.contains('menu-open')) setMenu(false, true);
    });
    nav.addEventListener('click', function (e) { if (e.target.closest('a')) setMenu(false); });
    window.matchMedia('(min-width: 980px)').addEventListener('change', function (e) { if (e.matches) setMenu(false); });

    var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 8); };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ---------- Aviso (toast) ---------- */
  var toast = document.getElementById('flash');
  if (toast) {
    var close = toast.querySelector('[data-dismiss]');
    var hide = function () { toast.remove(); };
    if (close) close.addEventListener('click', hide);
    setTimeout(hide, 7000);
  }

  /* ---------- Copiar (Pix, código de presente) ---------- */
  document.querySelectorAll('[data-copy]').forEach(function (btn) {
    var label = btn.querySelector('[data-copy-label]') || btn;
    var original = label.textContent;
    btn.addEventListener('click', function () {
      var source = document.getElementById(btn.getAttribute('data-copy'));
      if (!source) return;
      var text = source.textContent.trim();
      var done = function () {
        label.textContent = 'Copiado';
        btn.classList.add('is-copied');
        setTimeout(function () { label.textContent = original; btn.classList.remove('is-copied'); }, 2500);
      };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done, function () { select(source); });
      } else {
        select(source);
      }
    });
  });
  function select(el) {
    var range = document.createRange();
    range.selectNodeContents(el);
    var sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);
  }

  /* ---------- Leitor da Bíblia: tamanho do texto e fonte "Clássica" ---------- */
  var reader = document.querySelector('[data-reader]');
  if (reader) {
    var size = parseInt(store('sancta_reader_size') || '2', 10);
    var font = store('sancta_reader_font') === 'classic' ? 'classic' : 'default';
    var smaller = reader.querySelector('[data-reader-smaller]');
    var bigger = reader.querySelector('[data-reader-bigger]');
    var classic = reader.querySelector('[data-reader-classic]');
    var apply = function () {
      size = Math.min(4, Math.max(1, size || 2));
      reader.setAttribute('data-size', String(size));
      reader.setAttribute('data-font', font);
      if (smaller) smaller.disabled = size <= 1;
      if (bigger) bigger.disabled = size >= 4;
      if (classic) classic.setAttribute('aria-pressed', font === 'classic' ? 'true' : 'false');
    };
    if (smaller) smaller.addEventListener('click', function () { size--; store('sancta_reader_size', String(size)); apply(); });
    if (bigger) bigger.addEventListener('click', function () { size++; store('sancta_reader_size', String(size)); apply(); });
    if (classic) classic.addEventListener('click', function () { font = font === 'classic' ? 'default' : 'classic'; store('sancta_reader_font', font); apply(); });
    reader.querySelectorAll('[hidden][data-reader-tools]').forEach(function (el) { el.hidden = false; });
    apply();
  }
})();

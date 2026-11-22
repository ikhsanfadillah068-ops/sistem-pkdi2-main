(function () {
  'use strict';

  var root = document.documentElement;
  var body = document.body;
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  function iconHref(name) {
    var use = document.querySelector('use[href*="icons.svg"]');
    var base = use ? use.getAttribute('href').split('#')[0] : '/icons.svg';
    return base + '#i-' + name;
  }
  var store = {
    get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} },
    del: function (k) { try { localStorage.removeItem(k); } catch (e) {} }
  };

  /* Tema dan mode sidebar tersimpan di browser tanpa memengaruhi backend */
  var DEFAULTS = { theme: 'system', sbmode: 'full' };
  function pref(k) { return store.get(k) || DEFAULTS[k]; }
  function effTheme() {
    return root.dataset.theme || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  }
  function apply(k, v) {
    if (k === 'theme') { if (v === 'system') delete root.dataset.theme; else root.dataset.theme = v; }
    if (k === 'sbmode') { if (v === 'mini') root.dataset.sbmode = 'mini'; else delete root.dataset.sbmode; }
  }
  function syncPrefs() {
    $$('[data-pref]').forEach(function (b) {
      b.setAttribute('aria-pressed', String(pref(b.dataset.pref) === b.dataset.value));
    });
    var icon = iconHref(effTheme() === 'dark' ? 'sun' : 'moon');
    $$('.js-theme-icon').forEach(function (u) { u.setAttribute('href', icon); });
  }
  function setPref(k, v) { store.set(k, v); apply(k, v); syncPrefs(); }
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', syncPrefs);

  function setSidebar(open) { body.dataset.sidebar = open ? 'open' : 'closed'; }

  var TONES = {
    success: ['check-circle', 'text-success'],
    danger: ['alert-circle', 'text-danger'],
    warning: ['alert-triangle', 'text-warning'],
    info: ['info', 'text-info'],
    neutral: ['bell', 'text-muted-foreground']
  };
  function motionTime(el) {
    var style = window.getComputedStyle(el);
    function ms(value) { return value.endsWith('ms') ? parseFloat(value) : parseFloat(value) * 1000; }
    var durations = style.animationDuration.split(',').map(function (v) { return ms(v.trim()) || 0; });
    var delays = style.animationDelay.split(',').map(function (v) { return ms(v.trim()) || 0; });
    return Math.max.apply(null, durations.map(function (duration, i) { return duration + (delays[i] || 0); }));
  }
  function afterMotion(el, callback) {
    var duration = motionTime(el);
    if (duration <= 0) { callback(); return function () {}; }
    var done = false, timer;
    function cleanup() { clearTimeout(timer); el.removeEventListener('animationend', onEnd); }
    function finish() { if (done) return; done = true; cleanup(); callback(); }
    function onEnd(event) { if (event.target === el && !event.pseudoElement) finish(); }
    el.addEventListener('animationend', onEnd);
    timer = setTimeout(finish, duration + 60);
    return function () { if (done) return; done = true; cleanup(); };
  }
  function toast(o) {
    var c = $('#toasts'); if (!c) return;
    var t = TONES[o.tone] || TONES.neutral;
    var el = document.createElement('div');
    el.className = 'toast-notice pointer-events-auto flex items-start gap-3 rounded-lg border bg-card p-4 text-sm shadow-lg';
    el.innerHTML =
      '<svg class="mt-0.5 h-4 w-4 shrink-0 ' + t[1] + '" aria-hidden="true"><use href="' + iconHref(t[0]) + '"/></svg>' +
      '<div class="min-w-0 flex-1"><p class="font-medium"></p>' +
      (o.description ? '<p class="mt-0.5 text-muted-foreground"></p>' : '') + '</div>' +
      '<button type="button" class="-m-1 rounded p-1 text-muted-foreground hover:text-foreground" aria-label="Tutup notifikasi"><svg class="h-4 w-4"><use href="' + iconHref('x') + '"/></svg></button>';
    el.querySelector('p.font-medium').textContent = o.title || '';
    if (o.description) el.querySelector('p.text-muted-foreground').textContent = o.description;
    var timer;
    var close = function () {
      if (el.dataset.closing) return;
      clearTimeout(timer);
      el.dataset.closing = 'true';
      afterMotion(el, function () { el.remove(); });
    };
    el.querySelector('button').addEventListener('click', close);
    c.appendChild(el);
    timer = setTimeout(close, o.duration || 4500);
  }

  function closeMenus(except) {
    $$('[data-dropdown] .menu').forEach(function (m) {
      if (m === except || m.hidden || m.classList.contains('hidden') || m.dataset.closing) return;
      m.dataset.closing = 'true';
      m.__cancelClose = afterMotion(m, function () {
        m.hidden = true;
        delete m.dataset.closing;
        m.__cancelClose = null;
      });
    });
    $$('[data-dropdown-toggle]').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
  }
  function openMenu(tg) {
    var w = tg.closest('[data-dropdown]'), m = $('.menu', w);
    closeMenus(m);
    if (m.__cancelClose) m.__cancelClose();
    m.__cancelClose = null;
    delete m.dataset.closing;
    m.classList.remove('hidden');
    m.hidden = false;
    var r = tg.getBoundingClientRect(), mw = m.offsetWidth, mh = m.offsetHeight;
    var left = m.classList.contains('menu-right') ? r.right - mw : r.left;
    left = Math.max(8, Math.min(left, window.innerWidth - mw - 8));
    var topPos = r.bottom + 4;
    if (topPos + mh > window.innerHeight - 8 && r.top - 4 - mh > 8) topPos = r.top - 4 - mh;
    m.style.left = left + 'px'; m.style.top = topPos + 'px';
    tg.setAttribute('aria-expanded', 'true');
  }

  function openDialog(dialog) {
    if (dialog.__cancelClose) dialog.__cancelClose();
    dialog.__cancelClose = null;
    delete dialog.dataset.closing;
    if (!dialog.open) dialog.showModal();
  }
  function closeDialog(dialog, onClosed) {
    if (!dialog || !dialog.open) { if (onClosed) onClosed(); return; }
    if (dialog.dataset.closing) return;
    dialog.dataset.closing = 'true';
    dialog.__cancelClose = afterMotion(dialog, function () {
      dialog.close();
      delete dialog.dataset.closing;
      dialog.__cancelClose = null;
      if (onClosed) onClosed();
    });
  }
  document.addEventListener('cancel', function (event) {
    if (!event.target.matches('dialog.modal, dialog.drawer')) return;
    event.preventDefault();
    closeDialog(event.target);
  }, true);

  document.addEventListener('click', function (e) {
    var t = e.target;
    var el;

    if ((el = t.closest('#sidebarToggle'))) {
      if (window.innerWidth >= 1024) {
        var nextMode = root.dataset.sbmode === 'mini' ? 'full' : 'mini';
        setPref('sbmode', nextMode);
        setSidebar(true);
        el.setAttribute('aria-expanded', String(nextMode !== 'mini'));
      } else {
        var nextOpen = body.dataset.sidebar !== 'open';
        setSidebar(nextOpen);
        el.setAttribute('aria-expanded', String(nextOpen));
      }
      return;
    }
    if (t.id === 'overlay') { setSidebar(false); return; }
    if (t.closest('#sidebar a.nav-link[href]') && window.innerWidth < 1024) setSidebar(false);

    if ((el = t.closest('[data-theme-toggle]'))) { setPref('theme', effTheme() === 'dark' ? 'light' : 'dark'); return; }
    if ((el = t.closest('[data-pref]'))) { setPref(el.dataset.pref, el.dataset.value); return; }

    if ((el = t.closest('[data-toggle-password]'))) {
      var input = $(el.dataset.togglePassword);
      if (input) {
        var nextType = input.type === 'password' ? 'text' : 'password';
        input.type = nextType;
        el.setAttribute('aria-pressed', String(nextType === 'text'));
        var eyeOpen = el.querySelector('[data-eye-open]');
        var eyeClosed = el.querySelector('[data-eye-closed]');
        if (eyeOpen && eyeClosed) {
          eyeOpen.classList.toggle('hidden', nextType === 'text');
          eyeClosed.classList.toggle('hidden', nextType !== 'text');
          el.setAttribute('aria-label', nextType === 'text' ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
        } else {
          el.textContent = nextType === 'text' ? 'Sembunyikan' : 'Tampilkan';
        }
      }
      return;
    }

    if ((el = t.closest('[data-collapse]'))) {
      var target = $(el.dataset.collapse);
      if (target) { target.hidden = !target.hidden; el.setAttribute('aria-expanded', String(!target.hidden)); }
      return;
    }

    if ((el = t.closest('[data-dropdown-toggle]'))) {
      var menu = $('.menu', el.closest('[data-dropdown]'));
      if (menu.hidden || menu.classList.contains('hidden') || menu.dataset.closing) openMenu(el); else closeMenus();
      return;
    }
    if (t.closest('.menu-item') && !t.closest('[data-keep-open]')) closeMenus();
    else if (!t.closest('.menu')) closeMenus();

    if ((el = t.closest('[role="tab"]'))) { selectTab(el); return; }

    if ((el = t.closest('[data-modal-open]'))) {
      var d = $(el.dataset.modalOpen);
      if (d && d.showModal) openDialog(d);
      return;
    }
    if ((el = t.closest('[data-modal-close]'))) {
      var dd = el.closest('dialog');
      closeDialog(dd, el.hasAttribute('data-toast') ? function () {
        toast({ title: el.dataset.toastTitle, description: el.dataset.toastDesc, tone: el.dataset.toast });
      } : null);
      return;
    }
    if (t.tagName === 'DIALOG') { closeDialog(t); return; }

    if ((el = t.closest('[data-toast]'))) {
      toast({ title: el.dataset.toastTitle, description: el.dataset.toastDesc, tone: el.dataset.toast });
      return;
    }
    if ((el = t.closest('[data-copy]'))) {
      try { navigator.clipboard.writeText(el.dataset.copy); } catch (er) {}
      toast({ title: 'Disalin ke papan klip', description: el.dataset.copy, tone: 'success' });
      return;
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeMenus(); if (window.innerWidth < 1024 && !$('dialog[open]')) setSidebar(false); }
    var tab = e.target.closest && e.target.closest('[role="tab"]');
    if (tab && (e.key === 'ArrowRight' || e.key === 'ArrowLeft')) {
      var tabs = $$('[role="tab"]', tab.closest('[role="tablist"]'));
      var next = tabs[(tabs.indexOf(tab) + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
      next.focus(); selectTab(next);
    }
  });
  window.addEventListener('scroll', closeMenus, true);
  window.addEventListener('resize', function () {
    closeMenus();
    if (window.innerWidth >= 1024) setSidebar(true);
  });

  function selectTab(tab) {
    var list = tab.closest('[role="tablist"]');
    $$('[role="tab"]', list).forEach(function (x) {
      var on = x === tab;
      x.setAttribute('aria-selected', String(on));
      x.tabIndex = on ? 0 : -1;
      var p = x.getAttribute('aria-controls') && document.getElementById(x.getAttribute('aria-controls'));
      if (p) p.hidden = !on;
    });
    if (window.Charts) Charts.init(document);
  }

  function initTable(t) {
    if (t.__init) return; t.__init = true;
    var tbody = $('tbody', t), rows = $$('tr', tbody);
    var st = { q: '', f: '', col: -1, dir: 1, page: 1, size: +t.dataset.pageSize || 8 };
    var qi = $('[data-table-search]', t), fi = $('[data-table-filter]', t);
    var pager = $('[data-table-pager]', t), info = $('[data-table-info]', t), empty = $('[data-table-empty]', t);
    var ths = $$('th[data-sort]', t);
    rows.forEach(function (r) {
      r.__txt = $$('td', r).map(function (c) { return c.textContent.trim().toLowerCase(); }).join(' ');
    });
    function cell(r, i) { var c = r.cells[i]; return c ? (c.dataset.value != null ? c.dataset.value : c.textContent.trim()) : ''; }

    function drawPager(pages) {
      pager.innerHTML = '';
      function btn(label, p, o) {
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'page-btn'; b.innerHTML = label;
        if (o && o.aria) b.setAttribute('aria-label', o.aria);
        if (o && o.disabled) b.disabled = true;
        if (o && o.current) b.setAttribute('aria-current', 'page');
        b.addEventListener('click', function () { st.page = p; draw(); });
        pager.appendChild(b);
      }
      btn('‹', st.page - 1, { disabled: st.page <= 1, aria: 'Halaman sebelumnya' });
      var list = [];
      for (var p = 1; p <= pages; p++) {
        if (p === 1 || p === pages || Math.abs(p - st.page) <= 1) list.push(p);
        else if (list[list.length - 1] !== '…') list.push('…');
      }
      list.forEach(function (p) {
        if (p === '…') { var s = document.createElement('span'); s.className = 'px-1 text-muted-foreground'; s.textContent = '…'; pager.appendChild(s); }
        else btn(String(p), p, { current: p === st.page });
      });
      btn('›', st.page + 1, { disabled: st.page >= pages, aria: 'Halaman berikutnya' });
    }

    function draw() {
      var list = rows.filter(function (r) {
        if (st.f && r.dataset.status !== st.f) return false;
        return !st.q || r.__txt.indexOf(st.q) > -1;
      });
      if (st.col > -1) {
        var th = ths.filter(function (h) { return h.cellIndex === st.col; })[0];
        var num = th && th.dataset.sort === 'num';
        list.sort(function (a, b) {
          var x = cell(a, st.col), y = cell(b, st.col);
          return (num ? parseFloat(x) - parseFloat(y) : x.localeCompare(y, 'id')) * st.dir;
        });
      }
      var pages = Math.max(1, Math.ceil(list.length / st.size));
      st.page = Math.min(st.page, pages);
      var s = (st.page - 1) * st.size, e = s + st.size;
      rows.forEach(function (r) { r.hidden = true; });
      list.forEach(function (r, i) { r.hidden = !(i >= s && i < e); tbody.appendChild(r); });
      if (empty) {
        empty.hidden = list.length > 0;
        var h3 = $('h3', empty), p = $('p', empty);
        if (rows.length === 0 && !st.q && !st.f) {
          if (h3) h3.textContent = 'Belum ada data';
          if (p) p.textContent = 'Data akan muncul di sini setelah transaksi atau pengajuan dibuat.';
        } else if (list.length === 0) {
          if (h3) h3.textContent = 'Tidak ada data yang cocok';
          if (p) p.textContent = 'Coba kata kunci lain atau atur ulang filter yang aktif.';
        }
      }
      if (info) info.textContent = list.length ? 'Menampilkan ' + (s + 1) + ' sampai ' + Math.min(e, list.length) + ' dari ' + list.length + ' data' : (rows.length === 0 && !st.q && !st.f ? 'Belum ada data' : 'Tidak ada data cocok');
      if (pager) drawPager(pages);
      ths.forEach(function (h) { h.setAttribute('aria-sort', h.cellIndex === st.col ? (st.dir > 0 ? 'ascending' : 'descending') : 'none'); });
    }

    if (qi) qi.addEventListener('input', function () { st.q = qi.value.trim().toLowerCase(); st.page = 1; draw(); });
    if (fi) fi.addEventListener('change', function () { st.f = fi.value; st.page = 1; draw(); });
    var reset = $('[data-demo-reset-table]', t);
    if (reset) reset.addEventListener('click', function () {
      if (qi) qi.value = '';
      if (fi) fi.value = '';
      st.q = ''; st.f = ''; st.page = 1; draw();
      if (qi) qi.focus();
    });
    ths.forEach(function (h) {
      h.tabIndex = 0;
      var go = function () {
        if (st.col === h.cellIndex) st.dir *= -1; else { st.col = h.cellIndex; st.dir = 1; }
        st.page = 1; draw();
      };
      h.addEventListener('click', go);
      h.addEventListener('keydown', function (e) { if (e.key === 'Enter') go(); });
    });
    draw();
  }

  function isValidEmail(val) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(val);
  }

  function getFieldLabel(field, form) {
    var wrapper = field.closest('[data-field]');
    if (wrapper && wrapper.dataset.fieldLabel) {
      return wrapper.dataset.fieldLabel.trim();
    }
    if (field.id && form) {
      var lbl = form.querySelector('label[for="' + field.id + '"]');
      if (lbl) {
        return lbl.textContent.replace(/\*/g, '').replace(/\s*\(Opsional\)\s*/gi, '').trim() || 'Kolom ini';
      }
    }
    return 'Kolom ini';
  }

  function getControlTargets(field) {
    var targets = [field];
    if (field._flatpickr && field._flatpickr.altInput) {
      targets.push(field._flatpickr.altInput);
    }
    if (field._pkdiChoices) {
      var cWrap = field.closest('.choices');
      if (cWrap) targets.push(cWrap);
    }
    return targets;
  }

  function setFieldError(field, message, form) {
    var targets = getControlTargets(field);
    targets.forEach(function (t) {
      if (t.classList.contains('choices')) {
        t.classList.add('is-invalid');
      } else {
        t.classList.add('input-invalid');
      }
      t.setAttribute('aria-invalid', 'true');
    });

    var wrapper = field.closest('[data-field]');
    if (wrapper) {
      var serverErr = $('[data-server-error]', wrapper);
      if (serverErr) serverErr.remove();

      var helpEl = $('.field-help', wrapper);
      if (helpEl) helpEl.hidden = true;

      var errEl = $('[data-client-error]', wrapper);
      if (!errEl) {
        errEl = document.createElement('p');
        errEl.className = 'field-error';
        errEl.setAttribute('data-client-error', 'true');
        errEl.setAttribute('role', 'alert');
        if (helpEl) wrapper.insertBefore(errEl, helpEl);
        else wrapper.appendChild(errEl);
      }
      errEl.textContent = message;
      return;
    }

    if (form) {
      var sibling = field.parentElement && field.parentElement.querySelector('[data-client-error]');
      if (!sibling) {
        sibling = document.createElement('p');
        sibling.className = 'field-error mt-1';
        sibling.setAttribute('data-client-error', 'true');
        sibling.setAttribute('role', 'alert');
        field.insertAdjacentElement('afterend', sibling);
      }
      sibling.textContent = message;
    }
  }

  function clearFieldError(field) {
    var targets = getControlTargets(field);
    targets.forEach(function (t) {
      t.classList.remove('input-invalid', 'is-invalid');
      t.removeAttribute('aria-invalid');
    });

    var wrapper = field.closest('[data-field]');
    if (wrapper) {
      var clientErr = $('[data-client-error]', wrapper);
      if (clientErr) clientErr.remove();
      var serverErr = $('[data-server-error]', wrapper);
      if (serverErr) serverErr.remove();
      var helpEl = $('.field-help', wrapper);
      if (helpEl) helpEl.hidden = false;
      return;
    }

    var sibling = field.parentElement && field.parentElement.querySelector('[data-client-error]');
    if (sibling) sibling.remove();
  }

  function validateSingleField(field, form) {
    if (field.disabled || field.type === 'submit' || field.type === 'button' || field.type === 'reset') {
      return null;
    }
    if (field.type === 'hidden' && !field._flatpickr) {
      return null;
    }
    var raw = field.value || '';
    var val = field.type === 'password' ? raw : raw.trim();
    var label = getFieldLabel(field, form);

    if (field.hasAttribute('required')) {
      if (field.type === 'checkbox' || field.type === 'radio') {
        if (!field.checked) return label + ' wajib dipilih.';
      } else if (val === '') {
        return field.dataset.requiredMessage || (label + ' wajib diisi.');
      }
    }

    if (val !== '') {
      if (field.type === 'email' && !isValidEmail(val)) {
        return 'Masukkan alamat email yang valid.';
      }
      var minAttr = field.getAttribute('minlength');
      if (minAttr) {
        var minLen = parseInt(minAttr, 10);
        if (!Number.isNaN(minLen) && val.length < minLen) {
          if (field.type === 'password') return 'Password minimal ' + minLen + ' karakter.';
          return label + ' minimal ' + minLen + ' karakter.';
        }
      }
      if (field.type === 'number') {
        var num = Number(val);
        if (Number.isNaN(num)) return label + ' harus berupa angka yang valid.';
        if (field.hasAttribute('min') && num < Number(field.getAttribute('min'))) {
          return label + ' minimal ' + field.getAttribute('min') + '.';
        }
        if (field.hasAttribute('max') && num > Number(field.getAttribute('max'))) {
          return label + ' melebihi batas maksimal.';
        }
      }
      if (field.dataset.match) {
        var targetField = form.querySelector('[name="' + field.dataset.match + '"]');
        if (targetField && targetField.value !== field.value) {
          return field.dataset.matchMessage || 'Konfirmasi kata sandi tidak cocok.';
        }
      }
    }

    return null;
  }

  function validateLoginForm(form) {
    var emailField = form.querySelector('[name="email"]');
    var passField = form.querySelector('[name="password"]');

    if (!emailField || !passField) return true;

    clearFieldError(emailField);
    clearFieldError(passField);

    var emailVal = (emailField.value || '').trim();
    var passVal = passField.value || '';

    if (emailVal === '' && passVal === '') {
      setFieldError(emailField, 'Email wajib diisi.', form);
      setFieldError(passField, 'Password wajib diisi.', form);
      emailField.focus();
      return false;
    }

    var emailErr = null;
    if (emailVal === '') emailErr = 'Email wajib diisi.';
    else if (!isValidEmail(emailVal)) emailErr = 'Masukkan alamat email yang valid.';

    var passErr = null;
    if (passVal === '') passErr = 'Password wajib diisi.';
    else if (passVal.length < 8) passErr = 'Password minimal 8 karakter.';

    if (emailErr) setFieldError(emailField, emailErr, form);
    if (passErr) setFieldError(passField, passErr, form);

    if (emailErr || passErr) {
      (emailErr ? emailField : passField).focus();
      return false;
    }

    return true;
  }

  function validateGenericForm(form) {
    var fields = $$('input, textarea, select', form);
    var firstInvalid = null;
    var invalidCount = 0;

    fields.forEach(function (field) {
      clearFieldError(field);
      var msg = validateSingleField(field, form);
      if (msg) {
        setFieldError(field, msg, form);
        invalidCount++;
        if (!firstInvalid) {
          firstInvalid = (field._flatpickr && field._flatpickr.altInput) ? field._flatpickr.altInput : field;
        }
      }
    });

    if (invalidCount > 0) {
      if (firstInvalid && typeof firstInvalid.focus === 'function') firstInvalid.focus();
      return false;
    }

    return true;
  }

  function initFormValidation(scope) {
    $$('form[data-validate-form]', scope || document).forEach(function (form) {
      if (form.__validateInit) return;
      form.__validateInit = true;
      form.setAttribute('novalidate', 'novalidate');

      form.addEventListener('submit', function (e) {
        form.dataset.wasSubmitted = 'true';
        var valid = form.dataset.validateForm === 'login'
          ? validateLoginForm(form)
          : validateGenericForm(form);

        if (!valid) {
          e.preventDefault();
          e.stopImmediatePropagation();
        }
      });

      $$('input, textarea, select', form).forEach(function (field) {
        var onEdit = function () {
          field.dataset.touched = 'true';
          var currentErr = validateSingleField(field, form);
          if (!currentErr) {
            clearFieldError(field);
          } else if (field.getAttribute('aria-invalid') === 'true' && (field.value || '').trim() !== '') {
            // Hapus catatan "wajib diisi" segera saat pengguna mulai mengetik; format diperiksa ulang pada blur/submit
            if (currentErr.indexOf('wajib diisi') === -1) {
              setFieldError(field, currentErr, form);
            } else {
              clearFieldError(field);
            }
          }
        };

        field.addEventListener('input', onEdit);
        field.addEventListener('change', onEdit);
        field.addEventListener('blur', function () {
          if (form.dataset.wasSubmitted !== 'true' && field.dataset.touched !== 'true') return;
          if (form.dataset.validateForm === 'login') {
            var val = field.type === 'password' ? (field.value || '') : (field.value || '').trim();
            if (field.name === 'email') {
              if (val === '') setFieldError(field, 'Email wajib diisi.', form);
              else if (!isValidEmail(val)) setFieldError(field, 'Masukkan alamat email yang valid.', form);
              else clearFieldError(field);
            } else if (field.name === 'password') {
              if (val === '') setFieldError(field, 'Password wajib diisi.', form);
              else if (val.length < 8) setFieldError(field, 'Password minimal 8 karakter.', form);
              else clearFieldError(field);
            }
            return;
          }
          var err = validateSingleField(field, form);
          if (err) setFieldError(field, err, form);
          else clearFieldError(field);
        });
      });
    });
  }

  function initDoubleSubmitPrevention(scope) {
    $$('form[data-prevent-double-submit]', scope || document).forEach(function (form) {
      if (form.__preventDoubleInit) return;
      form.__preventDoubleInit = true;
      form.addEventListener('submit', function (e) {
        if (e.defaultPrevented) {
          return;
        }
        if (form.dataset.submitting === 'true') {
          e.preventDefault();
          return;
        }
        if (typeof form.checkValidity === 'function' && !form.hasAttribute('novalidate') && !form.checkValidity()) {
          return;
        }
        var submitter = e.submitter || document.activeElement;
        if (submitter && submitter.name && submitter.form === form && submitter.type === 'submit') {
          var hidden = $('input[type="hidden"][data-submitter-clone]', form);
          if (!hidden) {
            hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.setAttribute('data-submitter-clone', 'true');
            form.appendChild(hidden);
          }
          hidden.name = submitter.name;
          hidden.value = submitter.value;
        }
        form.dataset.submitting = 'true';
        $$('button[type="submit"]', form).forEach(function (btn) {
          btn.disabled = true;
          btn.setAttribute('aria-busy', 'true');
        });
      });
    });
  }

  function initInvoiceCalculator(scope) {
    $$('form[data-invoice-calc]', scope || document).forEach(function (form) {
      if (form.__invoiceCalcInit) return;
      form.__invoiceCalcInit = true;
      var qtyEl = $('input[name="quantity"]', form);
      var priceEl = $('input[name="unit_price"]', form);
      var adjEl = $('input[name="adjustment_amount"]', form);
      var taxEl = $('input[name="tax_amount"]', form);
      var tariffEl = $('select[name="tariff_id"]', form);
      var totalEl = $('[data-invoice-total]', form);
      var priceMap = {};
      try {
        priceMap = JSON.parse(form.dataset.tariffPrices || '{}');
      } catch (e) {}

      function recalc() {
        var qty = Math.max(0, parseFloat(qtyEl && qtyEl.value) || 0);
        var price = Math.max(0, parseFloat(priceEl && priceEl.value) || 0);
        var adj = Math.max(0, parseFloat(adjEl && adjEl.value) || 0);
        var tax = Math.max(0, parseFloat(taxEl && taxEl.value) || 0);
        var total = Math.max(0, Math.round((qty * price) - adj + tax));
        if (totalEl) {
          totalEl.textContent = 'Rp ' + total.toLocaleString('id-ID');
        }
      }

      if (tariffEl) {
        tariffEl.addEventListener('change', function () {
          var code = tariffEl.value;
          if (code && priceMap[code] && priceEl) {
            priceEl.value = String(priceMap[code]);
          }
          recalc();
        });
      }

      [qtyEl, priceEl, adjEl, taxEl].forEach(function (el) {
        if (!el) return;
        el.addEventListener('input', recalc);
        el.addEventListener('change', recalc);
      });

      recalc();
    });
  }

  function initStatusHeartbeat() {
    var url = body.dataset.statusHeartbeat;
    if (!url || body.__heartbeatInit) return;
    body.__heartbeatInit = true;
    var intervalMs = parseInt(body.dataset.heartbeatInterval || '15000', 10) || 15000;
    var lastSeenUpdatedAt = null;
    var labelEl = $('[data-heartbeat-label]');

    function pollStatus() {
      if (document.visibilityState === 'hidden') return;
      fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
          if (!data) return;
          if (labelEl && data.server_time_wib) {
            labelEl.textContent = 'Aktif (15 detik · Cek ' + data.server_time_wib + ')';
          }
          if (lastSeenUpdatedAt && data.latest_updated_at && data.latest_updated_at !== lastSeenUpdatedAt) {
            toast({
              title: 'Status pesanan diperbarui di server',
              description: 'Terdapat perubahan status terbaru (' + data.server_time_wib + '). Muat ulang halaman untuk melihat detail terbaru.',
              tone: 'info'
            });
          }
          if (data.latest_updated_at) {
            lastSeenUpdatedAt = data.latest_updated_at;
          }
        })
        .catch(function () {});
    }

    setTimeout(pollStatus, 2500);
    setInterval(pollStatus, intervalMs);
  }

  function initTransactionTypeForm(scope) {
    $$('[data-transaction-form]', scope).forEach(function (form) {
      if (form.dataset.txFormReady) return;
      form.dataset.txFormReady = '1';

      var radios = $$('[data-transaction-radio]', form);
      var recondBlocks = $$('[data-reconditioning-only]', form);
      var recondSelect = $('#f-reconditioning_service_id', form);
      var productSelect = $('[data-product-select]', form);
      var unitInput = $('[data-unit-input]', form);

      function syncVisibility() {
        var checked = form.querySelector('[data-transaction-radio]:checked');
        var isRecond = !checked || (checked.value !== 'WASTE_TRANSPORT' && checked.value !== 'DRUM_SALE');
        recondBlocks.forEach(function (block) {
          block.hidden = !isRecond;
        });
        if (recondSelect) {
          recondSelect.disabled = !isRecond;
          recondSelect.required = isRecond;
          if (!isRecond) recondSelect.value = '';
        }
      }

      radios.forEach(function (radio) {
        radio.addEventListener('change', syncVisibility);
      });

      if (productSelect && unitInput) {
        productSelect.addEventListener('change', function () {
          var selected = productSelect.options[productSelect.selectedIndex];
          var defaultUnit = selected ? selected.getAttribute('data-default-unit') : '';
          if (defaultUnit) unitInput.value = defaultUnit;
        });
      }

      syncVisibility();
    });
  }

  function initWarehouseSortingBalance(scope) {
    $$('[data-sorting-form]', scope).forEach(function (form) {
      if (form.dataset.sortingReady) return;
      form.dataset.sortingReady = '1';

      var expected = parseInt(form.getAttribute('data-expected-total') || '0', 10);
      var unit = form.getAttribute('data-sorting-unit') || 'Drum';
      var passedEl = $('[name="qty_passed"]', form);
      var specialEl = $('[name="qty_special_repair"]', form);
      var rejectedEl = $('[name="qty_rejected"]', form);
      var reasonEl = $('[name="rejected_reason"]', form);
      var reasonWrap = $('[data-rejected-reason-wrap]', form);
      var badge = $('[data-sorting-balance]', form);

      function updateBalance() {
        var p = parseInt((passedEl && passedEl.value) || '0', 10) || 0;
        var s = parseInt((specialEl && specialEl.value) || '0', 10) || 0;
        var r = parseInt((rejectedEl && rejectedEl.value) || '0', 10) || 0;
        var sum = p + s + r;

        if (badge) {
          var diff = sum - expected;
          if (expected > 0 && diff === 0) {
            badge.textContent = 'Seimbang: ' + sum + ' / ' + expected + ' ' + unit;
            badge.className = 'badge badge-success tabular-nums';
          } else {
            var dir = diff > 0 ? '+' + diff : String(diff);
            badge.textContent = 'Belum seimbang: ' + sum + ' / ' + expected + ' ' + unit + ' (' + dir + ')';
            badge.className = 'badge badge-warning tabular-nums';
          }
        }

        if (reasonEl) {
          reasonEl.required = r > 0;
        }
        if (reasonWrap) {
          reasonWrap.classList.toggle('opacity-60', r === 0);
        }
      }

      [passedEl, specialEl, rejectedEl].forEach(function (el) {
        if (el) el.addEventListener('input', updateBalance);
      });

      updateBalance();
    });
  }

  function initCombinedBillingForm(scope) {
    $$('[data-combined-billing-form]', scope).forEach(function (form) {
      if (form.dataset.combinedReady) return;
      form.dataset.combinedReady = '1';

      var checks = $$('[data-billable-check]', form);
      var countEl = $('[data-combined-count]', form);
      var subEl = $('[data-combined-subtotal]', form);
      var totalEl = $('[data-combined-total]', form);
      var warnEl = $('[data-combined-client-warning]', form);
      var adjEl = $('[name="adjustment_amount"]', form);
      var taxEl = $('[name="tax_amount"]', form);
      var submits = $$('[data-combined-submit]', form);

      function fmtRp(val) {
        return 'Rp ' + Math.round(val).toLocaleString('id-ID');
      }

      function recalc() {
        var selected = checks.filter(function (c) { return c.checked; });
        var clientIds = {};
        var subtotal = 0;

        selected.forEach(function (c) {
          var cid = c.getAttribute('data-client-id') || '';
          if (cid) clientIds[cid] = true;
          var row = c.closest('tr');
          var qty = parseInt(c.getAttribute('data-qty') || '0', 10) || 0;
          var priceInput = row ? $('[data-billable-price]', row) : null;
          var unitPrice = priceInput ? (parseFloat(priceInput.value) || 0) : 0;
          var line = qty * unitPrice;
          subtotal += line;
          var lineEl = row ? $('[data-billable-line-total]', row) : null;
          if (lineEl) lineEl.textContent = fmtRp(line);
        });

        checks.forEach(function (c) {
          if (c.checked) return;
          var row = c.closest('tr');
          var qty = parseInt(c.getAttribute('data-qty') || '0', 10) || 0;
          var priceInput = row ? $('[data-billable-price]', row) : null;
          var unitPrice = priceInput ? (parseFloat(priceInput.value) || 0) : 0;
          var lineEl = row ? $('[data-billable-line-total]', row) : null;
          if (lineEl) lineEl.textContent = fmtRp(qty * unitPrice);
        });

        var uniqueClients = Object.keys(clientIds);
        var mixedClient = uniqueClients.length > 1;
        var adj = adjEl ? (parseFloat(adjEl.value) || 0) : 0;
        var tax = taxEl ? (parseFloat(taxEl.value) || 0) : 0;
        var grandTotal = Math.max(0, subtotal - adj + tax);

        if (countEl) countEl.textContent = selected.length + ' transaksi dipilih';
        if (subEl) subEl.textContent = fmtRp(subtotal);
        if (totalEl) totalEl.textContent = fmtRp(grandTotal);
        if (warnEl) warnEl.hidden = !mixedClient;

        submits.forEach(function (btn) {
          btn.disabled = selected.length === 0 || mixedClient;
        });
      }

      checks.forEach(function (c) {
        c.addEventListener('change', recalc);
      });

      $$('[data-billable-tariff]', form).forEach(function (sel) {
        sel.addEventListener('change', function () {
          var opt = sel.options[sel.selectedIndex];
          var price = opt ? opt.getAttribute('data-price') : '';
          var row = sel.closest('tr');
          var priceInput = row ? $('[data-billable-price]', row) : null;
          if (priceInput && price) {
            priceInput.value = price;
          }
          recalc();
        });
      });

      $$('[data-billable-price]', form).forEach(function (inp) {
        inp.addEventListener('input', recalc);
      });

      [adjEl, taxEl].forEach(function (el) {
        if (el) el.addEventListener('input', recalc);
      });

      recalc();
    });
  }

  function initNewsTicker(scope) {
    $$('[data-news-ticker]', scope).forEach(function (ticker) {
      if (ticker.dataset.tickerReady) return;
      ticker.dataset.tickerReady = '1';
      var track = $('[data-news-ticker-track]', ticker);
      var group = $('[data-news-ticker-group]', ticker);
      if (track && group && track.children.length < 2) {
        var clone = group.cloneNode(true);
        clone.setAttribute('aria-hidden', 'true');
        track.appendChild(clone);
      }
      ticker.addEventListener('mouseenter', function () { ticker.classList.add('is-paused'); });
      ticker.addEventListener('mouseleave', function () { ticker.classList.remove('is-paused'); });
    });
  }

  function initPage(scope) {
    scope = scope || document;
    if (window.EnhancedControls) EnhancedControls.init(scope);
    $$('[data-table]', scope).forEach(initTable);
    if (window.Charts) Charts.init(scope);
    initFormValidation(scope);
    initDoubleSubmitPrevention(scope);
    initInvoiceCalculator(scope);
    initTransactionTypeForm(scope);
    initWarehouseSortingBalance(scope);
    initCombinedBillingForm(scope);
    initNewsTicker(scope);
    initStatusHeartbeat();
    syncPrefs();
  }

  window.App = { toast: toast, setPref: setPref, initPage: initPage, closeDialog: closeDialog };
  syncPrefs();
  initPage(document);
})();

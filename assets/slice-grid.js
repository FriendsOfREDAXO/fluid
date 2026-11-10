/*
 * Backend, Slice-Liste (FriendsOfRedaxo\Fluid\Backend\SliceGrid): Wird ein Block in einer Layout-Spalte bearbeitet oder
 * über „+ Block in Spalte n“ neu angelegt, öffnet das Formular des Cores in einem Dialog über dem Raster – das Raster
 * bleibt nebeneinander stehen. Esc und ✕ wirken wie „Abbrechen“. Ohne Skript bzw. <dialog>: Spalten untereinander.
 */
(() => {
  /*
   * Menü „⋯“ an jedem fluid-Block (Kopf des Cores): Verdoppeln, Kopieren, Ausschneiden, Einfügen darunter – Zwischenablage
   * wie auf der Website (localStorage „fluid-clipboard“, auch seitenübergreifend). Ausschneiden löscht das Original erst
   * nach dem Einfügen (Backend\BlockOpsApi). Spalten-Blöcke: schwebende Leiste mit Titel-Reiter, „Bearbeiten“ und „⋯“ –
   * Pfeile zum Verschieben bleiben sichtbar, Status und Löschen des Cores stehen im Menü (verschoben, Rückfrage über data-confirm wie gehabt).
   */
  const CLIP = 'fluid-clipboard';
  const clip = () => { try { return JSON.parse(localStorage.getItem(CLIP) || 'null'); } catch { return null; } };
  const ops = () => document.querySelector('[data-fluid-ops]')?.dataset.fluidOps || '';
  const toast = (text, bad = false) => {
    let t = document.querySelector('.fluid-be-toast');
    if (!t) { t = document.createElement('div'); t.className = 'fluid-be-toast'; t.setAttribute('role', 'status'); document.body.append(t); }
    t.textContent = text;
    t.classList.toggle('is-bad', bad);
    t.hidden = false;
    clearTimeout(t.timer);
    t.timer = setTimeout(() => { t.hidden = true; }, 3500);
  };
  const item = (label, op) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.setAttribute('role', 'menuitem');
    b.dataset.fluidOp = op;
    b.innerHTML = '<span class="fluid-be-menu__label"></span>';
    b.firstChild.textContent = label;
    return b;
  };
  const refreshPaste = () => {
    const c = clip();
    document.querySelectorAll('[data-fluid-op="paste"]').forEach((b) => {
      b.disabled = !c;
      b.firstChild.textContent = c ? `${c.cut ? 'Verschieben hierher' : 'Einfügen darunter'}: „${c.label}“` : 'Einfügen darunter';
    });
  };
  const doOp = async (op, slice) => {
    const url = ops();
    if (!url) return;
    if (op === 'copy' || op === 'cut') {
      try {
        const r = await fetch(url + '&fluid_op=copy&slice_id=' + slice, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const d = r.ok ? await r.json() : null;
        if (!d?.clip) throw new Error();
        const p = new URL(url, location.href).searchParams;
        const entry = { clip: d.clip, label: d.label };
        if (op === 'cut') entry.cut = { article: +p.get('article_id'), language: +p.get('language'), slice: +slice };
        localStorage.setItem(CLIP, JSON.stringify(entry));
        refreshPaste();
        toast(op === 'cut' ? `„${d.label}“ ausgeschnitten – an der neuen Stelle über ⋯ → Verschieben hierher.` : `„${d.label}“ kopiert – über ⋯ → Einfügen darunter, auch auf einer anderen Seite oder auf der Website.`);
      } catch { toast('Kopieren hat nicht geklappt.', true); }
      return;
    }
    if (op === 'paste') {
      const c = clip();
      if (!c) return;
      const f = document.createElement('form');
      f.method = 'post';
      f.action = url + '&fluid_op=paste&slice_id=' + slice + '#slice' + slice;
      const field = (n, v) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = n; i.value = v; f.append(i); };
      field('clip', JSON.stringify(c.clip));
      if (c.cut) {
        field('cut', JSON.stringify(c.cut));
        delete c.cut;   // nach dem Verschieben bleibt der Inhalt als Kopie in der Zwischenablage
        localStorage.setItem(CLIP, JSON.stringify(c));
      }
      document.body.append(f);
      f.submit();
    }
  };

  const bars = () => {
    let n = 0;
    document.querySelectorAll('.rex-slice-output > .rex-section > .rex-section-header').forEach((h) => {
      if (h.dataset.fluidBar) return;
      const dup = h.querySelector('[data-fluid-op="duplicate"]');
      if (!dup) return;   // kein fluid-Block
      h.dataset.fluidBar = '1';
      const slice = dup.dataset.slice;
      const inCol = !!h.closest('.fluid-be-list');
      const opts = h.querySelector('.rex-section-options');
      const all = [...opts.querySelectorAll('a[href], button')];
      const edit = all.find((a) => /[?&]function=edit/.test(a.getAttribute('href') || ''));
      const id = 'fluid-be-m' + (++n) + '-' + slice;
      const more = document.createElement('button');
      more.type = 'button';
      more.className = 'fluid-be-more';
      more.setAttribute('aria-haspopup', 'menu');
      more.setAttribute('aria-expanded', 'false');
      more.setAttribute('aria-controls', id);
      more.title = 'Weitere Aktionen';
      more.innerHTML = '<span aria-hidden="true">⋯</span><span class="rex-u-visually-hidden">Weitere Aktionen</span>';
      const list = document.createElement('div');
      list.className = 'fluid-be-menu';
      list.id = id;
      list.setAttribute('role', 'menu');
      list.hidden = true;
      const asItem = (a) => {
        a.setAttribute('role', 'menuitem');
        const hidden = a.querySelector('.rex-u-visually-hidden');
        if (hidden) {
          hidden.classList.replace('rex-u-visually-hidden', 'fluid-be-menu__label');
          hidden.textContent = hidden.textContent.trim().replace(/^Modul .*? (Nach (oben|unten) verschieben)$/, '$1');
        }
        a.removeAttribute('title');
        return a;
      };
      // Zwischenablage-Gruppe
      dup.closest('.rex-button-group')?.remove();
      list.append(asItem(dup), item('Kopieren', 'copy'), item('Ausschneiden', 'cut'), item('Einfügen darunter', 'paste'));
      if (inCol && edit) {
        // Spalten-Block: übrige Knöpfe des Cores ins Menü
        // Verschieben bleibt sichtbar (wird oft gebraucht), Status und Löschen ins Menü
        const arrows = all.filter((a) => a.querySelector('.rex-icon-up, .rex-icon-down'));
        const rest = all.filter((a) => a !== edit && a !== dup && !arrows.includes(a));
        if (rest.length) {
          const sep = document.createElement('hr');
          sep.className = 'fluid-be-menu__sep';
          list.append(sep, ...rest.map(asItem));
        }
        const wrap = document.createElement('div');
        wrap.className = 'fluid-be-acts';
        edit.classList.add('fluid-be-edit');
        const move = document.createElement('div');
        move.className = 'fluid-be-move';
        move.append(...arrows);
        wrap.append(edit, move, more, list);
        opts.replaceWith(wrap);
        h.classList.add('fluid-be-bar');
      } else {
        const wrap = document.createElement('div');
        wrap.className = 'fluid-be-acts fluid-be-acts--inline';
        wrap.append(more, list);
        opts.append(wrap);
      }
      const close = () => { list.hidden = true; more.setAttribute('aria-expanded', 'false'); };
      more.addEventListener('click', (e) => {
        e.stopPropagation();
        const open = list.hidden;
        document.querySelectorAll('.fluid-be-menu:not([hidden])').forEach((m) => { m.hidden = true; });
        refreshPaste();
        list.hidden = !open;
        more.setAttribute('aria-expanded', String(open));
        if (open) list.querySelector('[role=menuitem]:not([disabled])')?.focus();
      });
      list.addEventListener('click', (e) => {
        const b = e.target.closest('button[data-fluid-op]');
        if (!b) return;
        close();
        doOp(b.dataset.fluidOp, slice);
      });
      list.addEventListener('keydown', (e) => {
        const items = [...list.querySelectorAll('[role=menuitem]:not([disabled])')];
        const i = items.indexOf(document.activeElement);
        if (e.key === 'Escape') { e.preventDefault(); close(); more.focus(); }
        else if (e.key === 'ArrowDown') { e.preventDefault(); items[(i + 1) % items.length]?.focus(); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); items[(i - 1 + items.length) % items.length]?.focus(); }
      });
      h.closest('.rex-slice-output').addEventListener('focusout', (e) => { if (!h.contains(e.relatedTarget)) close(); });
    });
    document.addEventListener('click', (e) => {
      if (e.target.closest?.('.fluid-be-acts')) return;
      document.querySelectorAll('.fluid-be-menu:not([hidden])').forEach((m) => { m.hidden = true; m.previousElementSibling?.setAttribute('aria-expanded', 'false'); });
    });
    addEventListener('storage', (e) => { if (e.key === CLIP) refreshPaste(); });   // in einem anderen Tab kopiert
    refreshPaste();
  };

  const run = () => {
    if (!window.HTMLDialogElement) return;
    const form = document.getElementById('REX_FORM');
    const li = form?.closest('li.rex-slice');
    if (!li) return;
    const item = li.closest('.fluid-be-item.is-editing');
    const params = new URL(location.href).searchParams;
    const col = item ? item.closest('.fluid-be-gcol')?.querySelector('.blocks-be-gcol__label')?.textContent.trim() : (params.get('fluid_col') && params.get('function') === 'add' ? 'Spalte ' + params.get('fluid_col') : '');
    if (!item && !col) return;   // gewöhnlicher Block: Formular bleibt an seiner Stelle

    const title = li.querySelector('.rex-section-title')?.textContent.trim() || 'Block';
    // „Abbrechen“ des Cores: Link zurück zur Sprungmarke des Slices – nie ein Knopf mit API-Aufruf (Status, Löschen …)
    const cancel = [...form.querySelectorAll('a[href]')].find((a) => {
      const u = new URL(a.href, location.href);
      return /^#slice\d+$/.test(u.hash) && !u.searchParams.has('function') && !u.searchParams.has('rex-api-call') && !u.searchParams.has('status');
    }) || null;
    const back = () => {
      if (cancel) { location.href = cancel.href; return; }
      const u = new URL(location.href);
      ['function', 'module', 'fluid_col'].forEach((k) => u.searchParams.delete(k));
      location.href = u.href;
    };

    const dlg = document.createElement('dialog');
    dlg.className = 'fluid-be-dlg';
    dlg.setAttribute('aria-labelledby', 'fluid-be-dlg-t');
    dlg.innerHTML = '<div class="fluid-be-dlg__bar"><span class="fluid-be-dlg__col"></span><strong id="fluid-be-dlg-t"></strong>'
      + '<button type="button" class="fluid-be-dlg__x" aria-label="Schließen (wie Abbrechen)">✕</button></div><ul class="rex-slices fluid-be-list"></ul>';
    dlg.querySelector('.fluid-be-dlg__col').textContent = col;
    dlg.querySelector('#fluid-be-dlg-t').textContent = (item ? '' : 'Neu: ') + title;
    dlg.querySelector('.fluid-be-dlg__x').addEventListener('click', back);
    dlg.addEventListener('cancel', (e) => { e.preventDefault(); back(); });

    if (item) {
      // Platzhalter in der Spalte, Raster wieder nebeneinander
      const ph = document.createElement('p');
      ph.className = 'fluid-be-placeholder';
      ph.textContent = `„${title}“ wird bearbeitet …`;
      li.closest("ul").before(ph);
      item.closest('.fluid-be-grid')?.removeAttribute('data-editing');
    }
    dlg.querySelector('ul').append(li);
    document.body.append(dlg);
    dlg.showModal();
  };

  /*
   * Vorschauen in der Höhe begrenzt (form.css): überlaufende markieren (Ausblenden unten), per Tastatur scrollbar machen
   * (tabindex, Name) und „Ganze Vorschau“ zum Aufklappen anbieten. Neu prüfen, wenn sich die Größe ändert (Diagramme,
   * Bilder laden nach).
   */
  const clips = () => {
    const check = (body) => {
      const over = body.scrollHeight > body.clientHeight + 4 && !body.classList.contains('is-open');
      body.classList.toggle('is-clipped', over);
      let btn = body.nextElementSibling?.classList.contains('fluid-be-expand') ? body.nextElementSibling : null;
      if (over || body.classList.contains('is-open')) {
        if (!body.hasAttribute('tabindex')) {
          body.tabIndex = 0;
          body.setAttribute('role', 'region');
          body.setAttribute('aria-label', 'Vorschau: ' + (body.parentElement.querySelector('.rex-section-title')?.textContent.trim() || 'Block'));
        }
        if (!btn) {
          btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'fluid-be-expand';
          btn.addEventListener('click', () => {
            const open = body.classList.toggle('is-open');
            btn.setAttribute('aria-expanded', String(open));
            btn.textContent = open ? '▴ Vorschau begrenzen' : '▾ Ganze Vorschau';
            check(body);
          });
          body.after(btn);
        }
        btn.setAttribute('aria-expanded', String(body.classList.contains('is-open')));
        btn.textContent = body.classList.contains('is-open') ? '▴ Vorschau begrenzen' : '▾ Ganze Vorschau';
      } else if (btn) {
        btn.remove();
        body.removeAttribute('tabindex');
        body.removeAttribute('role');
        body.removeAttribute('aria-label');
      }
    };
    const ro = 'ResizeObserver' in window ? new ResizeObserver((list) => list.forEach((e) => check(e.target.closest('.rex-section-body') || e.target))) : null;
    document.querySelectorAll('.rex-slice-output > .rex-section > .rex-section-body').forEach((body) => {
      if (body.querySelector('.fluid-be-grid')) return;
      check(body);
      if (ro) { ro.observe(body); if (body.firstElementChild) ro.observe(body.firstElementChild); }
    });
  };

  const init = () => { bars(); clips(); run(); };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();

/*
 * Backend, Slice-Liste (FriendsOfRedaxo\Fluid\Backend\SliceGrid): Wird ein Block in einer Layout-Spalte bearbeitet oder
 * über „+ Block in Spalte n“ neu angelegt, öffnet das Formular des Cores in einem Dialog über dem Raster – das Raster
 * bleibt nebeneinander stehen. Esc und ✕ wirken wie „Abbrechen“. Ohne Skript bzw. <dialog>: Spalten untereinander.
 */
(() => {
  /*
   * Schwebende Leiste der Spalten-Blöcke wie die Blockleiste von fluid: Titel als Reiter links, rechts „Bearbeiten“ und
   * „⋯“ mit den übrigen Knöpfen des Cores (Status, Verschieben, Löschen) – Platz für weitere Aktionen (Kopieren, Einfügen).
   * Die Knöpfe bleiben die des Cores (nur verschoben; Rückfrage beim Löschen über data-confirm wie gehabt).
   */
  const bars = () => {
    let n = 0;
    document.querySelectorAll('.fluid-be-list > .rex-slice-output > .rex-section > .rex-section-header').forEach((h) => {
      if (h.dataset.fluidBar) return;
      h.dataset.fluidBar = '1';
      const opts = h.querySelector('.rex-section-options');
      const all = opts ? [...opts.querySelectorAll('a[href], button')] : [];
      const edit = all.find((a) => /[?&]function=edit/.test(a.getAttribute('href') || ''));
      const rest = all.filter((a) => a !== edit);
      if (!edit || !rest.length) return;
      const id = 'fluid-be-m' + (++n) + '-' + (h.closest('[id]')?.id || '');
      const wrap = document.createElement('div');
      wrap.className = 'fluid-be-acts';
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
      rest.forEach((a) => {
        a.setAttribute('role', 'menuitem');
        // im Menü sichtbar: die versteckte Beschriftung des Cores (sonst stünde sie doppelt im DOM)
        const hidden = a.querySelector('.rex-u-visually-hidden');
        if (hidden) {
          hidden.classList.replace('rex-u-visually-hidden', 'fluid-be-menu__label');
          hidden.textContent = hidden.textContent.trim().replace(/^Modul .*? (Nach (oben|unten) verschieben)$/, '$1');
        }
        a.removeAttribute('title');
        list.append(a);
      });
      edit.classList.add('fluid-be-edit');
      wrap.append(edit, more, list);
      opts.replaceWith(wrap);
      const close = () => { list.hidden = true; more.setAttribute('aria-expanded', 'false'); };
      more.addEventListener('click', () => {
        const open = list.hidden;
        document.querySelectorAll('.fluid-be-menu:not([hidden])').forEach((m) => { m.hidden = true; });
        list.hidden = !open;
        more.setAttribute('aria-expanded', String(open));
        if (open) list.querySelector('a, button')?.focus();
      });
      list.addEventListener('keydown', (e) => {
        const items = [...list.querySelectorAll('[role=menuitem]')];
        const i = items.indexOf(document.activeElement);
        if (e.key === 'Escape') { e.preventDefault(); close(); more.focus(); }
        else if (e.key === 'ArrowDown') { e.preventDefault(); items[(i + 1) % items.length]?.focus(); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); items[(i - 1 + items.length) % items.length]?.focus(); }
      });
      h.closest('.rex-slice-output').addEventListener('focusout', (e) => { if (!h.contains(e.relatedTarget)) close(); });
      h.classList.add('fluid-be-bar');
    });
    document.addEventListener('click', (e) => { if (!e.target.closest?.('.fluid-be-acts')) document.querySelectorAll('.fluid-be-menu:not([hidden])').forEach((m) => { m.hidden = true; m.previousElementSibling?.setAttribute('aria-expanded', 'false'); }); });
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
  const init = () => { bars(); run(); };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();

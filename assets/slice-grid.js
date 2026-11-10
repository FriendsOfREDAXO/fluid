/*
 * Backend, Slice-Liste (FriendsOfRedaxo\Fluid\Backend\SliceGrid): Wird ein Block in einer Layout-Spalte bearbeitet oder
 * über „+ Block in Spalte n“ neu angelegt, öffnet das Formular des Cores in einem Dialog über dem Raster – das Raster
 * bleibt nebeneinander stehen. Esc und ✕ wirken wie „Abbrechen“. Ohne Skript bzw. <dialog>: Spalten untereinander.
 */
(() => {
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
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
})();

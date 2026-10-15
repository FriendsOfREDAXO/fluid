/*
 * friendsofredaxo/fluid – Bearbeiten direkt auf der Website.
 *
 * Nur für angemeldete Backend-Benutzer geladen (Toolbar::inject). Oberfläche im Shadow DOM (#blocks-ui), damit das CSS
 * der Website sie nicht beeinflusst. Blöcke kennzeichnet der Server im Bearbeiten-Modus mit
 * [data-block][data-type][data-label] (Abschnitte bzw. Blöcke in Layout-Spalten mit data-col), Textfelder mit [data-edit="pfad"].
 */
(async () => {
  'use strict';

  // AddOns (cfg.scripts, z. B. fluid_demo) können den Start verzögern, etwa bis die Seite ausgetauscht ist
  await Promise.all(window.fluidEditorWait || []);
  const cfgEl = document.getElementById('blocks-editor-config');
  if (!cfgEl) return;
  const cfg = JSON.parse(cfgEl.textContent);
  // Aktionsrechte des Cores (Rollen → Optionen); ohne Angabe erlaubt
  const may = (k) => cfg.can?.[k] !== false;
  const unamp = (u) => String(u).replace(/&amp;/g, '&');
  const main = document.querySelector('main') || document.body;

  // ------------------------------------------------------------------ Server

  // Schreibende Aktionen: vorher „cms:before-save“ (z. B. Präsenz: jemand anderes hat inzwischen gespeichert → Rückfrage)
  const WRITES = new Set(['save', 'add', 'move', 'reorder', 'col_move', 'paste', 'duplicate', 'status', 'delete', 'publish', 'discard', 'page_save', 'lang_copy', 'version_restore']);
  async function beforeSave(action) {
    const waits = [];
    document.dispatchEvent(new CustomEvent('cms:before-save', { detail: { action, waitUntil: (p) => waits.push(p) } }));
    if ((await Promise.all(waits)).some((ok) => ok === false)) throw new Error('Abgebrochen.');
  }

  async function api(action, data = {}) {
    if (WRITES.has(action)) await beforeSave(action);
    const fd = data instanceof FormData ? data : new FormData();
    if (!(data instanceof FormData)) {
      for (const [k, v] of Object.entries(data)) {
        if (v && typeof v === 'object') for (const [k2, v2] of Object.entries(v)) fd.append(`${k}[${k2}]`, v2);
        else fd.append(k, v);
      }
    }
    fd.append('_csrf_token', cfg.csrf);
    // AddOns können Felder ergänzen (z. B. fluid_demo: gemerkte Änderungen) und erfahren vom Erfolg (fluid:api-done)
    document.dispatchEvent(new CustomEvent('fluid:api-request', { detail: { action, fd } }));
    let res;
    try {
      res = await fetch(unamp(cfg.api).replace('__action__', action), { method: 'POST', body: fd, credentials: 'same-origin' });
    } catch {
      throw new Error('Keine Verbindung zum Server.');
    }
    const json = await res.json().catch(() => ({ ok: false, error: `Ungültige Antwort (${res.status}).` }));
    if (!json.ok) throw new Error(json.error || 'Unbekannter Fehler.');
    document.dispatchEvent(new CustomEvent('fluid:api-done', { detail: { action, fd, json } }));
    // Server verlangt neu laden (z. B. erster Block eines Datensatzes)
    if (json.refresh) { dirty?.clear(); location.reload(); return new Promise(() => {}); }
    if (json.state) setState(json.state);
    return json;
  }

  // ------------------------------------------------------------------ Oberfläche (Shadow DOM)

  const host = document.createElement('div');
  host.id = 'blocks-ui';
  const root = host.attachShadow({ mode: 'open' });
  const h = (html) => {
    const t = document.createElement('template');
    t.innerHTML = html.trim();
    return t.content.firstElementChild;
  };
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  root.append(h(`<link rel="stylesheet" href="${esc(unamp(cfg.css))}">`));
  root.append(h(`<link rel="stylesheet" href="${esc(unamp(cfg.rteCss))}">`));
  const bar = h(`
    <header class="bar" role="toolbar" aria-label="Bearbeiten">
      ${cfg.guest ? '<span class="brand">R</span>' : `<a class="brand" href="${esc(unamp(cfg.backendUrl))}" title="Im Backend öffnen">R</a>`}
      <div class="title">
        <span class="kind">Seite</span>
        <strong class="name">${esc(cfg.article.name)}</strong>
        <button type="button" class="chip" data-chip aria-live="polite"></button>
        ${cfg.presence ? `<span class="cms-presence" role="status"${cfg.presence.here} hidden></span>` : ''}
        ${cfg.notice ? `<span class="gnote" role="note"><span class="gnote__tag">${esc(cfg.notice.tag || '')}</span><span class="gnote__text">${esc(cfg.notice.text || '')}</span>${(cfg.notice.buttons || []).map((b) => `<button type="button" class="gnote__btn" data-notice-event="${esc(b.event)}"${b.hidden ? ' hidden' : ''}>${esc(b.label)}</button>`).join('')}</span>` : ''}
      </div>
      <nav class="modes" aria-label="Modus">
        <a href="${esc(unamp(cfg.viewUrl))}" data-mode="view"${cfg.editing ? '' : ' aria-current="page"'}>Ansehen</a>
        <a href="${esc(unamp(cfg.editUrl))}" data-mode="edit"${cfg.editing ? ' aria-current="page"' : ''}>Bearbeiten</a>
      </nav>
      ${cfg.languages ? `
      <nav class="modes modes--lang" aria-label="Sprache">
        ${cfg.languages.map((l) => `<a href="${esc(unamp(l.href))}" hreflang="${esc(l.code)}"${l.current ? ' aria-current="page"' : ''} title="${esc(l.name)}${l.online ? '' : ' (offline)'}${l.empty ? ' – noch ohne Inhalt' : ''}" class="${l.online ? '' : 'is-off'}${l.empty ? ' is-empty' : ''}">${esc(l.code.toUpperCase())}</a>`).join('')}
      </nav>` : ''}
      ${!cfg.editing && cfg.state.workVersion && !cfg.guest ? `
      <nav class="modes modes--version" aria-label="Fassung ansehen">
        <a href="${esc(unamp(cfg.viewUrl))}"${cfg.viewingDraft ? '' : ' aria-current="page"'} title="Was Besucher sehen">Live</a>
        <a href="${esc(unamp(cfg.draftUrl))}"${cfg.viewingDraft ? ' aria-current="page"' : ''} title="Arbeitsversion – noch nicht veröffentlicht">Entwurf</a>
      </nav>` : ''}
      <div class="actions">
        ${cfg.editing ? `
        <button type="button" class="btn" data-save aria-disabled="true">Speichern</button>
        <button type="button" class="btn btn--primary" data-publish><span class="lg">Veröffentlichen</span><span class="sm" aria-hidden="true">Live ↑</span></button>` : ''}
        ${cfg.design ? '<button type="button" class="btn" data-design-open aria-expanded="false">Design</button>' : ''}
        <div class="more">
          <button type="button" class="btn btn--icon" data-more aria-haspopup="menu" aria-expanded="false" title="Weitere">⋯</button>
          <div class="menu" role="menu" hidden>
            ${cfg.design ? '<button type="button" role="menuitem" class="menu-sm" data-design-menu>Design …</button>' : ''}
            ${cfg.guest ? '' : '<button type="button" role="menuitem" data-tree-open>Seiten …</button>'}
            ${cfg.inbox ? `<button type="button" role="menuitem" data-inbox-open>Anfragen …${cfg.inbox.new ? ` <span class="badge">${cfg.inbox.new}</span>` : ''}</button>` : ''}
            ${cfg.guest ? '' : '<button type="button" role="menuitem" data-history-open>Verlauf & Entwürfe …</button>'}
            <button type="button" role="menuitem" data-compact>Kompaktansicht</button>
            ${may(cfg.article.menu ? 'editCategory' : 'editArticle') ? '<button type="button" role="menuitem" data-page-settings>Seiteneinstellungen …</button>' : ''}
            ${cfg.site ? '<button type="button" role="menuitem" data-site-open>Website …</button>' : ''}
            ${may('addArticle') || may('addCategory') ? '<button type="button" role="menuitem" data-page-add>Neue Seite …</button>' : ''}
            ${may('addArticle') ? '<button type="button" role="menuitem" data-template-save>Als Vorlage speichern …</button>' : ''}
            ${cfg.editing && cfg.state.workVersion && !cfg.guest ? '<button type="button" role="menuitem" data-discard>Entwurf verwerfen …</button>' : ''}
            ${cfg.guest ? '' : `<a role="menuitem" href="${esc(unamp(cfg.backendUrl))}">Im Backend öffnen</a>`}
            <a role="menuitem" href="${esc(unamp(cfg.viewUrl))}" target="_blank" rel="noopener">Live-Ansicht (neuer Tab)</a>
          </div>
        </div>
      </div>
    </header>`);
  const blockbar = h(`
    <div class="bb" hidden>
      <span class="bb__label" data-bb-label draggable="true" title="Ziehen zum Verschieben"></span>
      <button type="button" data-bb-edit class="bb__main">Bearbeiten</button>
      <button type="button" data-bb-up title="Nach oben" aria-label="Nach oben">↑</button>
      <button type="button" data-bb-down title="Nach unten" aria-label="Nach unten">↓</button>
      <button type="button" data-bb-status title="Online/Offline"></button>
      <button type="button" data-bb-more title="Weitere Aktionen" aria-label="Weitere Aktionen" aria-haspopup="menu" aria-expanded="false">⋯</button>
      <button type="button" data-bb-delete title="Löschen" aria-label="Löschen" class="bb__danger">✕</button>
      <div class="bb__menu" role="menu" hidden>
        <button type="button" role="menuitem" data-bb-op="duplicate">Verdoppeln</button>
        <button type="button" role="menuitem" data-bb-op="copy">Kopieren</button>
        <button type="button" role="menuitem" data-bb-op="paste">Einfügen darunter</button>
        <button type="button" role="menuitem" data-bb-op="left" data-col-only>← In die linke Spalte</button>
        <button type="button" role="menuitem" data-bb-op="right" data-col-only>→ In die rechte Spalte</button>
        <button type="button" role="menuitem" data-bb-op="collapse">Einklappen</button>
      </div>
    </div>`);
  const addbtn = h('<button type="button" class="addbtn" hidden title="Block einfügen" aria-label="Block einfügen">+</button>');
  const panel = h(`
    <aside class="panel" hidden aria-labelledby="blocks-panel-title">
      <header class="panel__head"><h2 id="blocks-panel-title" data-panel-title>Block</h2><button type="button" class="btn btn--icon" data-panel-close aria-label="Schließen">✕</button></header>
      <form class="panel__body" data-panel-form novalidate></form>
      <footer class="panel__foot"><button type="button" class="btn" data-panel-cancel>Abbrechen</button><button type="button" class="btn btn--primary" data-panel-apply>Übernehmen</button></footer>
    </aside>`);
  const picker = h(`
    <div class="picker" hidden role="dialog" aria-modal="true" aria-label="Block einfügen">
      <div class="picker__box">
        <header class="picker__head"><input type="search" placeholder="Block suchen …" data-picker-q aria-label="Block suchen"><button type="button" class="btn btn--icon" data-picker-close aria-label="Schließen">✕</button></header>
        <div class="picker__list" role="listbox" data-picker-list></div>
      </div>
    </div>`);
  const dialog = h(`
    <div class="dlg" hidden role="alertdialog" aria-modal="true" aria-labelledby="blocks-dlg-t">
      <div class="dlg__box"><h2 id="blocks-dlg-t" data-dlg-title></h2><p data-dlg-body></p>
      <div class="dlg__actions"><button type="button" class="btn dlg__alt" data-dlg-alt hidden></button><button type="button" class="btn" data-dlg-cancel>Abbrechen</button><button type="button" class="btn btn--primary" data-dlg-ok>OK</button></div></div>
    </div>`);
  const toast = h('<div class="toast" role="status" aria-live="polite" hidden></div>');
  const dpanel = h(`
    <aside class="panel dpanel" hidden aria-labelledby="fluid-design-title">
      <header class="panel__head"><h2 id="fluid-design-title">Design</h2><button type="button" class="btn btn--icon" data-design-close aria-label="Schließen">✕</button></header>
      <div class="panel__body" data-design-body></div>
      <footer class="panel__foot">
        <button type="button" class="btn" data-design-reset title="Standard des Kits">Standard</button>
        <span class="dpanel__space"></span>
        <button type="button" class="btn" data-design-cancel>Abbrechen</button>
        <button type="button" class="btn btn--primary" data-design-save>Speichern</button>
      </footer>
    </aside>`);
  const ppanel = h(`
    <aside class="panel ppanel" hidden aria-labelledby="fluid-page-title">
      <header class="panel__head"><h2 id="fluid-page-title">Seiteneinstellungen</h2><button type="button" class="btn btn--icon" data-page-close aria-label="Schließen">✕</button></header>
      <form class="panel__body" data-page-form novalidate></form>
      <footer class="panel__foot"><span class="dpanel__space"></span><button type="button" class="btn" data-page-cancel>Abbrechen</button><button type="button" class="btn btn--primary" data-page-save>Speichern</button></footer>
    </aside>`);
  const spanel = h(`
    <aside class="panel spanel" hidden aria-labelledby="fluid-site-title">
      <header class="panel__head"><h2 id="fluid-site-title">Website</h2><button type="button" class="btn btn--icon" data-site-close aria-label="Schließen">✕</button></header>
      <nav class="spanel__tabs" role="tablist" aria-label="Bereiche" data-site-tabs></nav>
      <div class="panel__body" data-site-body></div>
    </aside>`);
  const tpanel = h(`
    <aside class="panel tpanel" hidden aria-labelledby="fluid-tree-title">
      <header class="panel__head"><h2 id="fluid-tree-title">Seiten</h2><button type="button" class="btn btn--icon" data-tree-close aria-label="Schließen">✕</button></header>
      <div class="panel__body"><p class="tree__intro"${may('moveArticle') || may('moveCategory') ? '' : ' hidden'}>Ziehen am Griff ⠿ sortiert und verschiebt (auf eine Seite fallen lassen = darunter einordnen, nach links ziehen = eine Ebene höher), ohne Ziehen: ↑ ↓ sortieren, ← eine Ebene höher, → unter die Menüseite darüber. <b>Menüpunkte</b> stehen je Ebene vor Seiten ohne Menüeintrag.</p><ul class="tree" role="tree" data-tree${cfg.presence ? cfg.presence.list : ''}></ul></div>
      <footer class="panel__foot"><button type="button" class="btn" data-tree-new="0">+ Neue Seite (oberste Ebene)</button></footer>
    </aside>`);
  const tdlg = h(`
    <div class="dlg" hidden role="dialog" aria-modal="true" aria-labelledby="fluid-tpl-t">
      <form class="dlg__box newpage" data-tpl-form novalidate>
        <h2 id="fluid-tpl-t">Als Vorlage speichern</h2>
        <p class="newpage__hint">Die Blöcke dieser Seite werden zur Vorlage. Neue Seiten können sie als Ausgangspunkt nutzen. Vorlagen bearbeiten Sie wie normale Seiten (Seiten → „Seitenvorlagen“ ist für Besucher unsichtbar).</p>
        <label class="newpage__field"><span>Name der Vorlage</span><input type="text" name="name" required maxlength="191" autocomplete="off"></label>
        <div class="dlg__actions"><button type="button" class="btn" data-tpl-cancel>Abbrechen</button><button type="submit" class="btn btn--primary">Speichern</button></div>
      </form>
    </div>`);
  const hpanel = h(`
    <aside class="panel hpanel" hidden aria-labelledby="fluid-hist-title">
      <header class="panel__head"><h2 id="fluid-hist-title">Verlauf</h2><button type="button" class="btn btn--icon" data-history-close aria-label="Schließen">✕</button></header>
      <nav class="spanel__tabs" role="tablist" aria-label="Bereiche">
        <button type="button" role="tab" data-history-tab="versions" aria-selected="true">Versionen dieser Seite</button>
        <button type="button" role="tab" data-history-tab="drafts" aria-selected="false">Entwürfe</button>
        <button type="button" role="tab" data-history-tab="activity" aria-selected="false">Aktivität</button>
      </nav>
      <div class="panel__body" data-history-body></div>
    </aside>`);
  const ipanel = h(`
    <aside class="panel ipanel" hidden aria-labelledby="fluid-inbox-title">
      <header class="panel__head"><h2 id="fluid-inbox-title">Anfragen</h2><button type="button" class="btn btn--icon" data-inbox-close aria-label="Schließen">✕</button></header>
      <nav class="spanel__tabs" role="tablist" aria-label="Filter">
        <button type="button" role="tab" data-inbox-filter="new" aria-selected="true">Neu</button>
        <button type="button" role="tab" data-inbox-filter="done" aria-selected="false">Erledigt</button>
        <button type="button" role="tab" data-inbox-filter="all" aria-selected="false">Alle</button>
      </nav>
      <div class="panel__body" data-inbox-body></div>
    </aside>`);
  const newdlg = h(`
    <div class="dlg" hidden role="dialog" aria-modal="true" aria-labelledby="fluid-new-t">
      <form class="dlg__box newpage" data-new-form novalidate>
        <h2 id="fluid-new-t">Neue Seite</h2>
        <label class="newpage__field"><span>Titel</span><input type="text" name="name" required maxlength="191" autocomplete="off"></label>
        <fieldset class="newpage__field"><legend>Art</legend>
          <label${may('addCategory') ? '' : ' hidden'}><input type="radio" name="kind" value="menu"${may('addCategory') ? ' checked' : ' disabled'}> Menüpunkt <small>erscheint in der Navigation</small></label>
          <label${may('addArticle') ? '' : ' hidden'}><input type="radio" name="kind" value="page"${may('addCategory') ? '' : ' checked'}${may('addArticle') ? '' : ' disabled'}> Seite ohne Menüeintrag <small>z. B. Impressum, Aktion – nur per Link erreichbar</small></label>
        </fieldset>
        <label class="newpage__field"><span>Inhalt</span><select name="from" data-new-from><option value="">Leere Seite</option><option value="copy">Kopie dieser Seite</option></select></label>
        <fieldset class="newpage__field" data-new-where><legend>Ort</legend>
          <label><input type="radio" name="where" value="here" checked> ${cfg.article.category ? `Unter „${esc(cfg.article.category)}“` : 'Hier'}</label>
          <label><input type="radio" name="where" value="root"> Oberste Ebene</label>
        </fieldset>
        <p class="newpage__hint">Die Seite ist zunächst offline – „Online“ schalten Sie in den Seiteneinstellungen.</p>
        <div class="dlg__actions"><button type="button" class="btn" data-new-cancel>Abbrechen</button><button type="submit" class="btn btn--primary">Anlegen &amp; bearbeiten</button></div>
      </form>
    </div>`);
  // Ebene für Menüs und Dialoge der Formatierungsleiste (Studio-Editor, assets/rte.js)
  const layer = h('<div class="layer"></div>');
  root.append(bar, blockbar, addbtn, panel, dpanel, ppanel, spanel, tpanel, hpanel, ipanel, picker, dialog, newdlg, tdlg, toast, layer);
  document.body.append(host);
  document.documentElement.classList.add('blocks-has-bar');
  // ohne Recht: Knöpfe ausblenden (die API prüft ohnehin)
  const noperm = (sel) => root.querySelectorAll(sel).forEach((el) => el.setAttribute('data-noperm', ''));
  if (!may('publish')) noperm('[data-publish]');
  if (!may('moveSlice')) {
    noperm('[data-bb-up], [data-bb-down], [data-bb-op="left"], [data-bb-op="right"]');
    root.querySelector('[data-bb-label]')?.setAttribute('draggable', 'false');
  }
  if (!may('publishSlice')) noperm('[data-bb-status]');
  if (cfg.editing) document.documentElement.classList.add('blocks-editing');

  const $ = (sel, ctx = root) => ctx.querySelector(sel);

  function say(text, error = false) {
    toast.textContent = text;
    toast.classList.toggle('is-error', error);
    toast.hidden = false;
    clearTimeout(say.t);
    say.t = setTimeout(() => (toast.hidden = true), error ? 6000 : 2500);
  }

  /** Rückfrage: true (OK), false (Abbrechen, Esc) oder 'alt' (dritte Möglichkeit, z. B. „Verwerfen“) */
  function ask({ title, body = '', ok = 'OK', cancel = 'Abbrechen', danger = false, alt = '' }) {
    return new Promise((resolve) => {
      $('[data-dlg-title]').textContent = title;
      $('[data-dlg-body]').textContent = body;
      const okBtn = $('[data-dlg-ok]');
      okBtn.textContent = ok;
      okBtn.classList.toggle('btn--danger', danger);
      $('[data-dlg-cancel]').textContent = cancel;
      const altBtn = $('[data-dlg-alt]');
      altBtn.textContent = alt;
      altBtn.hidden = !alt;
      const back = root.activeElement || document.activeElement;
      dialog.hidden = false;
      $('[data-dlg-cancel]').focus();
      const done = (v) => {
        dialog.hidden = true;
        dialog.removeEventListener('click', onClick);
        dialog.removeEventListener('keydown', onKey);
        back?.focus?.();
        resolve(v);
      };
      const onClick = (e) => {
        if (e.target.closest('[data-dlg-ok]')) done(true);
        else if (e.target.closest('[data-dlg-alt]')) done('alt');
        else if (e.target.closest('[data-dlg-cancel]') || e.target === dialog) done(false);
      };
      const onKey = (e) => e.key === 'Escape' && (e.stopPropagation(), done(false));
      dialog.addEventListener('click', onClick);
      dialog.addEventListener('keydown', onKey);
    });
  }

  // Rückfrage für andere Module (z. B. Präsenz: assets/editor/presence.js)
  window.FluidEditor = { ...(window.FluidEditor || {}), ask, discard: () => dirty.clear() };

  // ------------------------------------------------------------------ Status

  let state = cfg.state;
  let busy = 0;
  const dirty = new Map(); // Block-ID → { pfad: wert } (Text direkt auf der Seite)

  function setState(s) {
    state = s;
    render();
  }

  function render() {
    const chip = $('[data-chip]');
    let label = 'Veröffentlicht';
    let cls = 'ok';
    if (busy) [label, cls] = ['Speichert …', 'busy'];
    else if (dirty.size) [label, cls] = ['Ungespeichert', 'warn'];
    else if (state.draft) [label, cls] = [may('publish') ? 'Entwurf – nicht veröffentlicht' : 'Entwurf – wartet auf Freigabe', 'draft'];
    if (cfg.viewingDraft) [label, cls] = [state.draft ? 'Ansicht: Entwurf (nicht veröffentlicht)' : 'Ansicht: Entwurf = live', 'draft'];
    if (!cfg.article.online) label += ' · Seite offline';
    chip.textContent = label;
    chip.dataset.state = cls;
    const save = $('[data-save]');
    if (save) save.setAttribute('aria-disabled', String(!dirty.size || busy > 0));
    const pub = $('[data-publish]');
    if (pub) pub.setAttribute('aria-disabled', String(!(state.draft || dirty.size) || busy > 0));
  }

  $('[data-chip]').addEventListener('click', () => {
    say(state.workVersion
      ? 'Änderungen landen zuerst in der Arbeitsversion. „Veröffentlichen“ übernimmt sie auf die Website.'
      : 'Die Arbeitsversion ist aus – gespeicherte Änderungen sind sofort live.');
  });

  // Menü ⋯ – auf schmalen Bildschirmen steckt „Design“ im Menü
  $('[data-design-menu]')?.addEventListener('click', () => { $('.menu').hidden = true; $('[data-design-open]')?.click(); });
  const moreBtn = $('[data-more]');
  const menu = $('.menu');
  moreBtn.addEventListener('click', () => {
    menu.hidden = !menu.hidden;
    moreBtn.setAttribute('aria-expanded', String(!menu.hidden));
  });
  document.addEventListener('click', (e) => {
    if (!e.composedPath().includes(moreBtn.parentElement)) {
      menu.hidden = true;
      moreBtn.setAttribute('aria-expanded', 'false');
    }
  });

  // Bearbeiten verlassen: erst ungespeicherte Änderungen klären, dann einen unveröffentlichten Entwurf
  // (sonst zeigt „Ansehen“ die Live-Fassung und die Änderungen scheinen verloren)
  $('.modes').addEventListener('click', async (e) => {
    const a = e.target.closest('a[data-mode="view"]');
    if (!a || !cfg.editing || (!dirty.size && !state.draft)) return;
    e.preventDefault();
    if (dirty.size) {
      const r = await ask({ title: 'Ungespeicherte Änderungen', body: 'Vor dem Verlassen speichern? „Verwerfen“ nimmt nur die ungespeicherten Änderungen zurück – ein gespeicherter Entwurf bleibt.', ok: 'Speichern', cancel: 'Weiter bearbeiten', alt: 'Verwerfen' });
      if (r === 'alt') dirty.clear();
      else if (r !== true || !(await saveAll())) return;
    }
    if (!state.draft) {
      location.href = a.href;
      return;
    }
    if (!may('publish')) {
      const r = await ask({ title: 'Entwurf gespeichert', body: 'Ihre Änderungen sind als Entwurf gespeichert. Veröffentlichen kann jemand mit Freigaberecht – Besucher sehen bis dahin die bisherige Fassung.', ok: 'Entwurf ansehen', cancel: 'Weiter bearbeiten', alt: 'Live ansehen' });
      if (r === true) location.href = unamp(cfg.draftUrl);
      else if (r === 'alt') location.href = a.href;
      return;
    }
    const r = await ask({ title: 'Noch nicht veröffentlicht', body: 'Ihre Änderungen sind als Entwurf gespeichert – Besucher sehen sie erst nach dem Veröffentlichen.', ok: 'Veröffentlichen', cancel: 'Weiter bearbeiten', alt: 'Entwurf ansehen' });
    if (r === 'alt') {
      location.href = unamp(cfg.draftUrl);
    } else if (r === true) {
      try {
        busy++;
        render();
        await api('publish');
        location.href = a.href;
      } catch (err) {
        say(err.message, true);
      } finally {
        busy--;
        render();
      }
    }
  });

  render();
  if (cfg.design) initDesign();
  initPage();
  initTree();
  initHistory();
  if (cfg.inbox) initInbox();
  if (cfg.site) initSite();
  if (!cfg.editing) return;


  // ================================================================== Style-Editor (Design)
  // Werte → Server (Design::css/classes) → live auf der Seite: <style id="fluid-design-preview">, Klassen am <html>,
  // Schriften und Options-Stylesheets. „Speichern“ gilt für die ganze Website.
  function initDesign() {
    const D = cfg.design;
    let saved = { ...D.values };
    let values = { ...D.values };
    let timer = 0;
    let seq = 0;
    const body = $('[data-design-body]');
    const openBtn = $('[data-design-open]');
    const sw = (c) => `<span class="sw" style="background:${esc(c)}"></span>`;

    const control = (t) => {
      const v = values[t.name];
      const id = 'fd-' + t.name;
      const help = t.help ? `<p class="help-block">${esc(t.help)}</p>` : '';
      let input = '';
      if (t.type === 'color') {
        input = `<span class="dcolor"><input type="color" id="${id}" data-token="${esc(t.name)}" value="${esc(v)}" aria-label="${esc(t.label)} (hell)"><code>${esc(v)}</code>`
          + (t.dark ? `<input type="color" data-token="${esc(t.name)}@dark" value="${esc(values[t.name + '@dark'] || t.dark)}" title="Im dunklen Farbschema" aria-label="${esc(t.label)} (dunkel)"><span class="dcolor__dark">dunkel</span>` : '') + '</span>';
      } else if (t.type === 'range') {
        input = `<span class="drange"><input type="range" id="${id}" data-token="${esc(t.name)}" min="${t.min ?? 0}" max="${t.max ?? 100}" step="${t.step ?? 1}" value="${esc(v)}"><output>${esc(v)}${esc(t.unit || '')}</output></span>`;
      } else if (t.type === 'choice' || t.type === 'font') {
        const opts = t.type === 'font' ? Object.fromEntries(Object.entries(D.fonts).map(([k, f]) => [k, f.label])) : t.options;
        input = `<select class="form-control" id="${id}" data-token="${esc(t.name)}">${Object.entries(opts || {}).map(([k, l]) => `<option value="${esc(k)}"${String(v) === k ? ' selected' : ''}>${esc(l)}</option>`).join('')}</select>`;
      } else if (t.type === 'bool') {
        return `<div class="dfield"><label class="blocks-form__check"><input type="checkbox" data-token="${esc(t.name)}"${v ? ' checked' : ''}> ${esc(t.label)}</label>${help}</div>`;
      }
      return `<div class="dfield"><label class="control-label" for="${id}">${esc(t.label)}</label>${input}${help}</div>`;
    };

    const draw = () => {
      const presets = Object.entries(D.presets).map(([k, p]) => `<button type="button" class="dpreset" data-preset="${esc(k)}" title="${esc(p.description)}">
          <span class="dpreset__sw">${sw(p.values.accent || '#000')}${sw(p.values.highlight || '#fff')}${sw(p.values.ink || '#000')}${sw(p.values.background || '#fff')}</span>
          <span class="dpreset__label">${esc(p.label)}</span></button>`).join('');
      body.innerHTML = `<details class="dgroup" open><summary>Vorlagen</summary><div class="dpresets">${presets}</div></details>`
        + D.groups.map((g, i) => `<details class="dgroup"${i === 0 ? ' open' : ''}><summary>${esc(g.label)}</summary>${g.tokens.map(control).join('')}</details>`).join('')
        + '<div class="dchecks" data-design-checks aria-live="polite"></div>';
    };

    const applyPreview = (res) => {
      const html = document.documentElement;
      // Klassen des Designs austauschen (übrige Klassen der Seite bleiben)
      (html.dataset.fluidClasses || '').split(/\s+/).filter(Boolean).forEach((c) => html.classList.remove(c));
      res.classes.split(/\s+/).filter(Boolean).forEach((c) => html.classList.add(c));
      html.dataset.fluidClasses = res.classes;
      const bg = values.background || '#ffffff';
      const n = (i) => parseInt(bg.slice(i, i + 2), 16) / 255;
      html.classList.toggle('is-darkbase', 0.2126 * n(1) + 0.7152 * n(3) + 0.0722 * n(5) < 0.18);
      html.classList.toggle('head-serif', ['fraunces', 'newsreader', 'instrument-serif', 'roboto-slab'].includes(values.font_head));
      // Variablen
      let st = document.getElementById('fluid-design-preview');
      if (!st) { st = document.createElement('style'); st.id = 'fluid-design-preview'; document.head.append(st); }
      st.textContent = res.css;
      // Schriften und Options-Stylesheets
      res.fonts.forEach((href) => {
        if (!document.querySelector(`link[data-fluid-font][href="${CSS.escape(href)}"]`)) {
          const l = document.createElement('link'); l.rel = 'stylesheet'; l.href = href; l.dataset.fluidFont = ''; document.head.append(l);
        }
      });
      const want = new Set(res.opt);
      document.querySelectorAll('link[data-fluid-opt]').forEach((l) => { if (!want.has(l.getAttribute('href'))) l.remove(); else want.delete(l.getAttribute('href')); });
      want.forEach((href) => { const l = document.createElement('link'); l.rel = 'stylesheet'; l.href = href; l.dataset.fluidOpt = ''; document.head.append(l); });
      // Kopf- und Fußvariante: Klasse am Element (Markup ist für alle Varianten gleich)
      document.querySelectorAll('[data-header]').forEach((el) => { el.className = el.className.replace(/\bhdr--[a-z]+/, 'hdr--' + values.header); });
      document.querySelectorAll('footer.ftr').forEach((el) => el.classList.replace([...el.classList].find((c) => c.startsWith('ftr--')) || 'ftr--columns', 'ftr--' + values.footer));
      const checks = $('[data-design-checks]');
      if (checks) checks.innerHTML = res.checks.length
        ? '<p><strong>Kontrast prüfen:</strong></p><ul>' + res.checks.map((c) => `<li>${esc(D.groups.flatMap((g) => g.tokens).find((t) => t.name === c.token)?.label || c.token)} (${c.mode === 'dark' ? 'dunkel' : 'hell'}): ${String(c.ratio).replace('.', ',')} : 1 – mindestens ${String(c.min).replace('.', ',')} : 1</li>`).join('') + '</ul>'
        : '';
    };

    const preview = () => {
      clearTimeout(timer);
      timer = setTimeout(async () => {
        const my = ++seq;
        try {
          const res = await api('design', { values: JSON.stringify(values) });
          if (my === seq) applyPreview(res);
        } catch (err) { say(err.message, true); }
      }, 120);
    };

    body.addEventListener('input', (e) => {
      const el = e.target.closest('[data-token]');
      if (!el) return;
      const v = el.type === 'checkbox' ? el.checked : el.type === 'range' ? Number(el.value) : el.value;
      values[el.dataset.token] = v;
      if (el.type === 'color') el.parentElement.querySelector('code').textContent = el.value;
      if (el.type === 'range') el.nextElementSibling.textContent = el.value + (D.groups.flatMap((g) => g.tokens).find((t) => t.name === el.dataset.token)?.unit || '');
      preview();
    });
    body.addEventListener('change', (e) => e.target.matches('select[data-token],input[type=checkbox][data-token]') && body.dispatchEvent(new Event('input')));
    body.addEventListener('click', (e) => {
      const p = e.target.closest('[data-preset]');
      if (!p) return;
      values = { ...values, ...D.presets[p.dataset.preset].values };
      const open = [...body.querySelectorAll('details')].map((d) => d.open);
      draw();
      body.querySelectorAll('details').forEach((d, i) => (d.open = open[i] ?? d.open));
      body.querySelector(`[data-preset="${CSS.escape(p.dataset.preset)}"]`)?.classList.add('is-active');
      preview();
      say(`Vorlage „${D.presets[p.dataset.preset].label}“ – Vorschau. Speichern übernimmt sie für die Website.`);
    });

    const close = () => { dpanel.hidden = true; openBtn?.setAttribute('aria-expanded', 'false'); document.documentElement.classList.remove('blocks-panel-open'); };
    openBtn?.addEventListener('click', () => {
      if (!dpanel.hidden) return close();
      if (!panel.hidden) return say('Bitte zuerst die Seitenleiste des Blocks schließen.', true);
      draw();
      dpanel.hidden = false;
      openBtn.setAttribute('aria-expanded', 'true');
      document.documentElement.classList.add('blocks-panel-open');
    });
    $('[data-design-close]').addEventListener('click', () => { values = { ...saved }; preview(); close(); });
    $('[data-design-cancel]').addEventListener('click', () => { values = { ...saved }; preview(); close(); });
    $('[data-design-reset]').addEventListener('click', () => { values = { ...D.defaults }; draw(); preview(); });
    $('[data-design-save]').addEventListener('click', async () => {
      try {
        const res = await api('design_save', { values: JSON.stringify(values) });
        saved = { ...values };
        applyPreview(res);
        say(res.message);
        close();
      } catch (err) { say(err.message, true); }
    });
  }

  // ------------------------------------------------------------------ Seiteneinstellungen, neue Seite

  function initPage() {
    const pform = $('[data-page-form]');
    let pageSaved = '';
    const snap = () => new URLSearchParams(new FormData(pform)).toString();
    const close = () => { ppanel.hidden = true; document.documentElement.classList.remove('blocks-panel-open'); };
    const count = () => pform.querySelectorAll('[data-count]').forEach((t) => {
      let o = t.parentElement.querySelector('.count');
      if (!o) { o = document.createElement('span'); o.className = 'count'; t.after(o); }
      const n = t.value.length, max = +t.dataset.count;
      o.textContent = `${n} / ${max} Zeichen`;
      o.classList.toggle('is-over', n > max);
    });
    pform.addEventListener('input', (e) => {
      count();
      const sl = e.target.closest?.('[data-slug]');
      if (sl) {
        const v = sl.value.toLowerCase().replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss').replace(/[^a-z0-9-]+/g, '-').replace(/-{2,}/g, '-');
        if (v !== sl.value) sl.value = v;
      }
    });
    $('[data-page-settings]')?.addEventListener('click', async () => {
      menu.hidden = true;
      if (!panel.hidden || !dpanel.hidden) return say('Bitte zuerst die andere Seitenleiste schließen.', true);
      try {
        const res = await api('page');
        pform.innerHTML = res.html;
        window.BlocksForm?.init(pform, () => {});
        count();
        pageSaved = snap();
        ppanel.hidden = false;
        document.documentElement.classList.add('blocks-panel-open');
        pform.querySelector('input[name="name"]')?.focus();
      } catch (err) { say(err.message, true); }
    });
    const cancel = async () => {
      if (snap() !== pageSaved && !(await ask({ title: 'Änderungen verwerfen?', ok: 'Verwerfen', danger: true }))) return;
      close();
    };
    $('[data-page-close]').addEventListener('click', cancel);
    $('[data-page-cancel]').addEventListener('click', cancel);
    const saveBtn = $('[data-page-save]');
    saveBtn.addEventListener('click', async () => {
      if (!pform.reportValidity()) return;
      saveBtn.disabled = true;
      try {
        const res = await api('page_save', new FormData(pform));
        say(res.message);
        pageSaved = snap();
        if (dirty?.size) { close(); $('.name').textContent = pform.name.value; return; }
        const next = res.url ? unamp(res.url) + (cfg.editing ? (res.url.includes('?') ? '&' : '?') + 'fluid_edit=1' : '') : '';
        if (next && next !== location.pathname + location.search) location.href = next;
        else location.reload();
      } catch (err) { say(err.message, true); } finally { saveBtn.disabled = false; }
    });

    const nform = $('[data-new-form]');
    // Vorlagen in die Auswahl „Inhalt“ laden (bei jedem Öffnen aktuell)
    newdlg.addEventListener('fluid:open', async () => {
      const sel = nform.querySelector('[data-new-from]');
      sel.querySelectorAll('[data-tpl]').forEach((o) => o.remove());
      try {
        const res = await api('templates', {});
        for (const t of res.templates || []) sel.insertAdjacentHTML('beforeend', `<option value="${t.id}" data-tpl>Vorlage: ${esc(t.name)}</option>`);
      } catch { /* ohne Vorlagen */ }
    });
    $('[data-template-save]')?.addEventListener('click', () => {
      menu.hidden = true;
      const f = $('[data-tpl-form]');
      f.reset();
      f.name.value = cfg.article.name;
      tdlg.hidden = false;
      f.name.select();
    });
    $('[data-tpl-cancel]').addEventListener('click', () => { tdlg.hidden = true; });
    $('[data-tpl-form]').addEventListener('submit', async (e) => {
      e.preventDefault();
      const f = e.currentTarget;
      if (!f.reportValidity()) return;
      if (dirty.size && !(await ask({ title: 'Erst speichern?', body: 'Ungespeicherte Änderungen kommen nicht in die Vorlage. Trotzdem fortfahren?', ok: 'Fortfahren' }))) return;
      try { const res = await api('template_save', new FormData(f)); tdlg.hidden = true; say(res.message); } catch (err) { say(err.message, true); }
    });
    // Datensatz ohne Blöcke: „+ Ersten Block einfügen“
    main.addEventListener('click', (e) => {
      const rb = e.target.closest('[data-record-add]');
      if (!rb) return;
      e.preventDefault();
      openPicker(rb);
    });
    // Block „Beiträge“: „+ Neuer Beitrag“ – Seite ohne Menüeintrag im Blog
    main.addEventListener('click', (e) => {
      const nb = e.target.closest('[data-fluid-new-post]');
      if (!nb) return;
      e.preventDefault();
      if (!may('addArticle')) return say('Dafür fehlt Ihnen das Recht „Artikel anlegen“.', true);
      nform.reset();
      nform.dataset.parent = nb.dataset.fluidNewPost;
      const page = nform.querySelector('input[name=kind][value=page]');
      if (page) page.checked = true;
      newdlg.querySelector('#fluid-new-t').textContent = 'Neuer Beitrag';
      newdlg.querySelector('[data-new-where]')?.setAttribute('hidden', '');
      newdlg.querySelector('input[name=kind]')?.closest('fieldset')?.setAttribute('hidden', '');
      newdlg.hidden = false;
      newdlg.dispatchEvent(new Event('fluid:open'));
      nform.name.focus();
    });
    $('[data-page-add]')?.addEventListener('click', () => {
      menu.hidden = true;
      nform.reset();
      newdlg.querySelector('#fluid-new-t').textContent = 'Neue Seite';
      newdlg.querySelector('input[name=kind]')?.closest('fieldset')?.removeAttribute('hidden');
      delete nform.dataset.parent;
      newdlg.querySelector('[data-new-where]')?.removeAttribute('hidden');
      newdlg.hidden = false;
      newdlg.dispatchEvent(new Event('fluid:open'));
      nform.name.focus();
    });
    $('[data-new-cancel]').addEventListener('click', () => { newdlg.hidden = true; });
    newdlg.addEventListener('keydown', (e) => { if (e.key === 'Escape') newdlg.hidden = true; });
    nform.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!nform.reportValidity()) return;
      if (dirty?.size && !(await ask({ title: 'Ungespeicherte Änderungen', body: 'Die Änderungen auf dieser Seite gehen verloren. Trotzdem fortfahren?', ok: 'Fortfahren', danger: true }))) return;
      try {
        const fd = new FormData(nform);
        if (nform.dataset.parent !== undefined && nform.dataset.parent !== '') fd.append('parent', nform.dataset.parent);
        const res = await api('page_add', fd);
        dirty?.clear();
        location.href = unamp(res.url);
      } catch (err) { say(err.message, true); }
    });
  }



  // ------------------------------------------------------------------ Seitenbaum (sortieren, verschieben, Menü, Status)

  function initTree() {
    const list = $('[data-tree]');
    const nform = $('[data-new-form]');
    let tree = [];
    let dragId = 0;
    const find = (nodes, id, parent = null) => {
      for (const n of nodes) {
        if (n.id === id) return { node: n, parent, siblings: nodes };
        const r = find(n.children || [], id, n);
        if (r) return r;
      }
      return null;
    };
    // nächste Menüseite oberhalb in derselben Ebene (Ziel für →)
    const mv = (n) => may(n.menu ? 'moveCategory' : 'moveArticle');
    // Blogs: Beiträge eingeklappt (aufgeklappt, wenn die aktuelle Seite ein Beitrag ist)
    const open = new Set();
    const openCurrent = (nodes) => nodes.forEach((n) => {
      if (n.blog && (n.children || []).some((c) => c.id === cfg.article.id)) open.add(n.id);
      openCurrent(n.children || []);
    });
    const menuBefore = (all, i) => all.slice(0, i).findLast((x) => x.menu) || null;
    const row = (n, depth, prev = null) => `
      <li role="treeitem" aria-expanded="${n.children?.length ? 'true' : 'false'}" data-id="${n.id}" data-menu="${n.menu ? 1 : 0}" class="tree__item${n.online ? '' : ' is-offline'}${n.id === cfg.article.id ? ' is-current' : ''}">
        <div class="tree__row" style="--d:${depth}">
          ${mv(n) ? '<span class="tree__grip" draggable="true" title="Ziehen zum Sortieren/Verschieben" aria-hidden="true">⠿</span>' : '<span class="tree__grip" aria-hidden="true"></span>'}
          <a class="tree__name" href="${esc(unamp(n.url))}${cfg.editing ? (n.url.includes('?') ? '&' : '?') + 'fluid_edit=1' : ''}">${n.home ? '⌂ ' : ''}${esc(n.name)}</a><span data-presence-slot></span>
          <span class="tree__badge">${n.home ? 'Startseite' : n.menu ? 'Menü' : 'Seite'}${n.online ? '' : ' · offline'}</span>
          <span class="tree__tools">
            ${n.home || !mv(n) ? '' : `<button type="button" class="tree__btn" data-tree-act="up" title="Nach oben" aria-label="${esc(n.name)} nach oben">↑</button>
            <button type="button" class="tree__btn" data-tree-act="down" title="Nach unten" aria-label="${esc(n.name)} nach unten">↓</button>`}
            ${depth && mv(n) ? `<button type="button" class="tree__btn" data-tree-act="out" title="Eine Ebene höher" aria-label="${esc(n.name)} eine Ebene höher">←</button>` : ''}
            ${prev?.menu && !n.home && mv(n) ? `<button type="button" class="tree__btn" data-tree-act="in" title="Unter „${esc(prev.name)}“ einordnen" aria-label="${esc(n.name)} unter ${esc(prev.name)} einordnen">→</button>` : ''}
            ${!may(n.menu ? 'publishCategory' : 'publishArticle') ? '' : `<button type="button" class="tree__btn" data-tree-act="tree_status" title="${n.online ? 'Offline schalten' : 'Online schalten'}" aria-label="${esc(n.name)}: ${n.online ? 'offline schalten' : 'online schalten'}">${n.online ? '●' : '○'}</button>`}
            ${n.home || !may('article2category') ? '' : `<button type="button" class="tree__btn" data-tree-act="tree_menu" title="${n.menu ? 'Aus dem Menü nehmen' : 'Ins Menü aufnehmen'}" aria-label="${esc(n.name)}: ${n.menu ? 'aus dem Menü nehmen' : 'ins Menü aufnehmen'}">☰</button>`}
            ${n.menu && (may('addArticle') || may('addCategory')) ? `<button type="button" class="tree__btn" data-tree-new="${n.id}" title="Unterseite anlegen" aria-label="Unterseite von ${esc(n.name)} anlegen">+</button>` : ''}
            ${n.home || !may(n.menu ? 'deleteCategory' : 'deleteArticle') ? '' : `<button type="button" class="tree__btn tree__btn--danger" data-tree-act="tree_delete" title="Löschen" aria-label="${esc(n.name)} löschen">✕</button>`}
          </span>
        </div>
        ${n.children?.length && n.blog ? `<button type="button" class="tree__more" data-tree-toggle style="--d:${depth + 1}" aria-expanded="${open.has(n.id)}">${n.children.length} ${n.children.length === 1 ? 'Beitrag' : 'Beiträge'} ${open.has(n.id) ? '▾' : '▸'}</button>` : ''}
        ${n.children?.length ? `<ul role="group"${n.blog && !open.has(n.id) ? ' hidden' : ''}>${n.children.map((c, i, all) => row(c, depth + 1, menuBefore(all, i))).join('')}</ul>` : ''}
      </li>`;
    // Startseite immer oben (REDAXO sortiert je Ebene erst Menüpunkte, dann Seiten – die Startseite gehört davor)
    const draw = () => {
      const top = [...tree.filter((n) => n.home), ...tree.filter((n) => !n.home)];
      list.innerHTML = top.map((n, i, all) => row(n, 0, n.home ? null : menuBefore(all, i))).join('');
      document.dispatchEvent(new CustomEvent('cms:presence-scan'));   // Präsenz: Kürzel an den Seiten
    };
    const run = async (action, data) => {
      try {
        const res = await api(action, data);
        tree = res.tree;
        openCurrent(tree);
        draw();
        if (res.message) say(res.message);
        return true;
      } catch (err) { say(err.message, true); return false; }
    };
    const close = () => { tpanel.hidden = true; document.documentElement.classList.remove('blocks-panel-open'); };
    // Position innerhalb der eigenen Gruppe (Menüpunkte bzw. Seiten) ermitteln
    const indexIn = (siblings, moving, beforeId) => {
      const group = siblings.filter((x) => x.menu === moving.menu && x.id !== moving.id);
      if (beforeId === null) return group.length;
      const i = group.findIndex((x) => x.id === beforeId);
      return i < 0 ? group.length : i;
    };
    $('[data-tree-open]')?.addEventListener('click', async () => {
      menu.hidden = true;
      if (!panel.hidden || !dpanel.hidden || !ppanel.hidden || !spanel.hidden) return say('Bitte zuerst die andere Seitenleiste schließen.', true);
      tpanel.hidden = false;
      document.documentElement.classList.add('blocks-panel-open');
      await run('tree', {});
    });
    $('[data-tree-close]').addEventListener('click', close);
    tpanel.addEventListener('click', async (e) => {
      const tg = e.target.closest('[data-tree-toggle]');
      if (tg) {
        const id = +tg.closest('[data-id]').dataset.id;
        open.has(id) ? open.delete(id) : open.add(id);
        draw();
        return;
      }
      const nb = e.target.closest('[data-tree-new]');
      if (nb) {
        // Dialog „Neue Seite“ mit vorgegebener Stelle
        close();
        nform.reset();
        nform.dataset.parent = nb.dataset.treeNew;
        // im Blog: neuer Beitrag (Seite ohne Menüeintrag)
        const isBlog = find(tree, +nb.dataset.treeNew)?.node.blog;
        newdlg.querySelector('#fluid-new-t').textContent = isBlog ? 'Neuer Beitrag' : 'Neue Seite';
        const kindSet = newdlg.querySelector('input[name=kind]')?.closest('fieldset');
        if (isBlog) { nform.querySelector('input[name=kind][value=page]').checked = true; kindSet?.setAttribute('hidden', ''); } else kindSet?.removeAttribute('hidden');
        newdlg.querySelector('[data-new-where]')?.setAttribute('hidden', '');
        newdlg.hidden = false;
        newdlg.dispatchEvent(new Event('fluid:open'));
        nform.name.focus();
        return;
      }
      const b = e.target.closest('[data-tree-act]');
      if (!b) return;
      const id = +b.closest('[data-id]').dataset.id;
      const loc = find(tree, id);
      if (!loc) return;
      const act = b.dataset.treeAct;
      // ← eine Ebene höher: direkt hinter die Elternseite; → unter die Menüseite darüber (ans Ende) – geht auch ohne Ziehen (Handy)
      if (act === 'out' && loc.parent) {
        const up = find(tree, loc.parent.id);
        const after = up.siblings.slice(up.siblings.indexOf(up.node) + 1).find((x) => x.menu === loc.node.menu);
        await run('tree_move', { id, parent: up.parent?.id || 0, index: indexIn(up.siblings, loc.node, after ? after.id : null) });
        return;
      }
      if (act === 'in') {
        const prev = menuBefore(loc.siblings, loc.siblings.indexOf(loc.node));
        if (!prev?.menu) return;
        await run('tree_move', { id, parent: prev.id, index: indexIn(prev.children || [], loc.node, null) });
        return;
      }
      if (act === 'up' || act === 'down') {
        const group = loc.siblings.filter((x) => x.menu === loc.node.menu);
        const i = group.indexOf(loc.node) + (act === 'up' ? -1 : 1);
        // erste Unterseite ↑ → eine Ebene höher, vor die Elternseite
        if (i < 0 && loc.parent && loc.node.menu) {
          const up = find(tree, loc.parent.id);
          await run('tree_move', { id, parent: up.parent?.id || 0, index: indexIn(up.siblings, loc.node, up.node.id) });
          return;
        }
        if (i < 0 || i >= group.length) return;
        await run('tree_move', { id, parent: loc.parent?.id || 0, index: i });
        list.querySelector(`[data-id="${id}"] [data-tree-act="${act}"]`)?.focus();
        return;
      }
      if (act === 'tree_delete' && !(await ask({ title: `„${loc.node.name}“ löschen?`, body: 'Die Seite und ihre Inhalte werden gelöscht. Seiten mit Unterseiten lassen sich erst löschen, wenn diese verschoben oder gelöscht sind.', ok: 'Löschen', danger: true }))) return;
      await run(act, { id });
      if (act === 'tree_delete' && id === cfg.article.id) location.href = '/';
    });
    // Ziehen
    list.addEventListener('dragstart', (e) => {
      const it = e.target.closest('[data-id]');
      if (!it) return;
      dragId = +it.dataset.id;
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', String(dragId));
      it.classList.add('is-dragging');
    });
    list.addEventListener('dragend', () => { dragId = 0; list.querySelectorAll('.is-dragging').forEach((x) => x.classList.remove('is-dragging')); clearMarks(); });
    const zone = (e, rowEl, target) => {
      const r = rowEl.getBoundingClientRect();
      const y = (e.clientY - r.top) / r.height;
      return y < 0.3 ? 'before' : y > 0.7 ? 'after' : (target.menu ? 'inside' : (y < 0.5 ? 'before' : 'after'));
    };
    // Ziel des Ziehens: hinein, davor, dahinter – unter der letzten Unterseite (oder auf der eigenen Zeile) bestimmt die
    // Mausposition nach links die Ebene (nach links ziehen = eine Ebene höher)
    const plan = (e, rowEl) => {
      const target = find(tree, +rowEl.closest('[data-id]').dataset.id);
      const moving = find(tree, dragId);
      if (!target || !moving || target.node.home) return null;
      const depth = +rowEl.style.getPropertyValue('--d') || 0;
      const own = target.node.id === dragId;
      let z = own ? 'after' : zone(e, rowEl, target.node);
      if (z === 'inside') {
        if (contains(moving.node, target.node.id)) return null;
        return { mark: 'inside', level: depth, parent: target.node.id, index: indexIn(target.node.children || [], moving.node, null) };
      }
      let anchor = target;
      let level = depth;
      const last = target.siblings[target.siblings.length - 1] === target.node && !(target.node.children || []).length;
      if (z === 'after' && (last || own) && depth > 0) {
        const x = e.clientX - list.getBoundingClientRect().left - 6;
        level = Math.max(0, Math.min(depth, Math.round(x / 18) - 1));
        for (let d = depth; d > level; d--) anchor = find(tree, anchor.parent.id);
      }
      if (own && anchor === target) return null;
      if (anchor.node.id !== dragId && contains(moving.node, anchor.node.id)) return null;
      const sib = anchor.siblings;
      const next = sib.slice(sib.indexOf(anchor.node) + (z === 'after' ? 1 : 0)).find((x) => x.menu === moving.node.menu && x.id !== dragId);
      return { mark: z, level, parent: anchor.parent?.id || 0, index: indexIn(sib, moving.node, next ? next.id : null) };
    };
    const contains = (node, id) => (node.children || []).some((c) => c.id === id || contains(c, id));
    const clearMarks = () => list.querySelectorAll('[data-drop]').forEach((x) => { x.removeAttribute('data-drop'); x.style.removeProperty('--dl'); });
    list.addEventListener('dragover', (e) => {
      const rowEl = e.target.closest('.tree__row');
      if (!dragId || !rowEl) return;
      const p = plan(e, rowEl);
      clearMarks();
      if (!p) return;
      e.preventDefault();
      rowEl.setAttribute('data-drop', p.mark);
      rowEl.style.setProperty('--dl', p.level);
    });
    list.addEventListener('drop', async (e) => {
      const rowEl = e.target.closest('.tree__row');
      if (!dragId || !rowEl) return;
      e.preventDefault();
      const p = plan(e, rowEl);
      clearMarks();
      if (p) await run('tree_move', { id: dragId, parent: p.parent, index: p.index });
    });
  }


  // ------------------------------------------------------------------ Verlauf: Versionen, Entwürfe, Aktivität

  function initHistory() {
    const body = $('[data-history-body]');
    let tab = 'versions';
    const when = (d) => { const t = new Date(String(d).replace(' ', 'T')); return isNaN(t) ? d : t.toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }); };
    const editHref = (u) => unamp(u) + (u.includes('?') ? '&' : '?') + 'fluid_edit=1';
    const draw = {
      versions: (res) => (res.versions?.length
        ? `<p class="site-intro">Stände beim Speichern und vor dem Veröffentlichen. „Wiederherstellen“ holt einen Stand in den <b>Entwurf</b> – live geht er erst mit „Veröffentlichen“. Der aktuelle Entwurf wird vorher gesichert.</p>
            <p><button type="button" class="btn btn--primary" data-versions-view>Zeitleiste mit Vorschau öffnen</button></p><ul class="site-list" role="list">`
          + res.versions.map((v) => `<li class="site-list__item"><span class="site-list__main"><b>${esc(when(v.date))}</b><small>${esc(v.label)} · ${v.revision ? 'Entwurf' : 'Live'} · ${v.blocks} Blöcke${v.user ? ' · ' + esc(v.user) : ''}</small></span>
              <button type="button" class="btn" data-versions-view="${v.revision}-${esc(String(v.date).replace(/\D/g, ''))}">Ansehen</button>
              <button type="button" class="btn" data-restore="${esc(v.date)}" data-rev="${v.revision}">Wiederherstellen</button></li>`).join('') + '</ul>'
        : '<p class="site-intro">Noch keine gespeicherten Stände. Sie entstehen beim Speichern und Veröffentlichen.</p>'),
      drafts: (res) => (res.drafts?.length
        ? '<p class="site-intro">Seiten mit Änderungen, die noch nicht veröffentlicht sind.</p><ul class="site-list" role="list">'
          + res.drafts.map((d) => `<li class="site-list__item"><span class="site-list__main"><a href="${esc(editHref(d.url))}"><b>${esc(d.name)}</b></a><small>zuletzt ${esc(when(d.changed))}${d.user ? ' · ' + esc(d.user) : ''}</small></span><a class="btn" href="${esc(editHref(d.url))}">Öffnen</a></li>`).join('') + '</ul>'
        : '<p class="site-intro">Keine offenen Entwürfe – alles ist veröffentlicht.</p>'),
      activity: (res) => (cfg.presence ? `<div data-presence-board-box hidden><h3 class="site-h" style="margin-top:0">Gerade aktiv</h3><ul class="site-list cms-presence-board"${cfg.presence.board}></ul><h3 class="site-h">Zuletzt</h3></div>` : '') + (res.activity?.length
        ? '<ul class="site-list" role="list">' + res.activity.map((a) => `<li class="site-list__item"><span class="site-list__main"><span><b>${esc(a.user || 'Jemand')}</b> hat ${a.page ? `<a href="${esc(editHref(a.url))}">${esc(a.page)}</a> ` : ''}${esc(a.action)}</span><small>${esc(when(a.when))}${a.detail ? ' · ' + esc(a.detail) : ''}</small></span></li>`).join('') + '</ul>'
        : '<p class="site-intro">Noch keine Aktivität.</p>'),
    };
    const load = async (t) => {
      tab = t;
      hpanel.querySelectorAll('[data-history-tab]').forEach((b) => b.setAttribute('aria-selected', String(b.dataset.historyTab === t)));
      body.innerHTML = '<p class="site-intro">Lädt …</p>';
      try { body.innerHTML = draw[t](await api(t, {})); document.dispatchEvent(new CustomEvent('cms:presence-scan')); } catch (err) { body.innerHTML = ''; say(err.message, true); }
    };
    $('[data-history-open]')?.addEventListener('click', () => {
      menu.hidden = true;
      if (!panel.hidden || !dpanel.hidden || !ppanel.hidden || !spanel.hidden || !tpanel.hidden) return say('Bitte zuerst die andere Seitenleiste schließen.', true);
      hpanel.hidden = false;
      document.documentElement.classList.add('blocks-panel-open');
      load(tab);
    });
    $('[data-history-close]').addEventListener('click', () => { hpanel.hidden = true; document.documentElement.classList.remove('blocks-panel-open'); });
    hpanel.addEventListener('click', async (e) => {
      const t = e.target.closest('[data-history-tab]');
      if (t) return load(t.dataset.historyTab);
      const vv = e.target.closest('[data-versions-view]');
      if (vv) return openVersions(vv.dataset.versionsView);
      const r = e.target.closest('[data-restore]');
      if (!r) return;
      if (dirty.size && !(await ask({ title: 'Ungespeicherte Änderungen', body: 'Sie gehen beim Wiederherstellen verloren. Fortfahren?', ok: 'Fortfahren', danger: true }))) return;
      if (!(await ask({ title: 'Diesen Stand wiederherstellen?', body: 'Er ersetzt den aktuellen Entwurf (der vorher gesichert wird). Live ändert sich erst mit „Veröffentlichen“.', ok: 'Wiederherstellen' }))) return;
      try {
        const res = await api('version_restore', { date: r.dataset.restore, revision: r.dataset.rev });
        dirty.clear();
        say(res.message);
        location.reload();
      } catch (err) { say(err.message, true); }
    });
  }


  // Versionen-Ansicht wie in KLXM Studio (assets/editor/versions.js): Zeitleiste, Vorschau, Gegenüberstellen, Hervorheben
  async function openVersions(select = '') {
    try {
      const mod = await import(cfg.versions.js);
      await mod.open({
        data: () => api('version_view'),
        css: cfg.versions.css,
        select,
        restore: async (key) => {
          // eigener Dialog läge hinter der Versionen-Ansicht – hier die Rückfrage des Browsers
          if (dirty.size && !confirm('Ungespeicherte Änderungen gehen beim Wiederherstellen verloren. Fortfahren?')) throw new Error('Abgebrochen.');
          const res = await api('version_restore', { key });
          dirty.clear();
          say(res.message);
          location.reload();
        },
      });
    } catch (err) { say(err.message, true); }
  }


  // ------------------------------------------------------------------ Anfragen-Postfach (YForm-Formulare der Website)

  function initInbox() {
    const body = $('[data-inbox-body]');
    let filter = 'new';
    let items = [];
    const when = (d) => { const t = new Date(String(d).replace(' ', 'T')); return d && !isNaN(t) ? t.toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : ''; };
    const badge = (n) => {
      const b = $('[data-inbox-open]');
      b.querySelector('.badge')?.remove();
      if (n) b.insertAdjacentHTML('beforeend', ` <span class="badge">${n}</span>`);
      $('[data-more]').classList.toggle('has-badge', n > 0);
    };
    const draw = () => {
      body.innerHTML = items.length ? '<ul class="site-list" role="list">' + items.map((it, i) => `
        <li class="inbox__item${it.state === 'new' ? ' is-new' : ''}">
          <details>
            <summary><span class="site-list__main"><b>${esc(it.title)}</b><small>${esc(it.tableLabel)}${it.when ? ' · ' + esc(when(it.when)) : ''}${it.state === 'new' ? ' · neu' : ''}</small></span></summary>
            <dl class="inbox__dl">${it.values.map((v) => `<div><dt>${esc(v.label)}</dt><dd>${esc(v.value).replace(/\n/g, '<br>')}</dd></div>`).join('')}</dl>
            <div class="site-actions">
              ${it.email ? `<a class="btn" href="mailto:${esc(it.email)}">Antworten</a>` : ''}
              ${it.edit ? `<a class="btn" href="${esc(unamp(it.edit))}" target="_blank" rel="noopener">In YForm öffnen</a>` : ''}
              ${it.canDelete ? `<button type="button" class="btn" data-inbox-delete="${i}">Löschen</button>` : ''}
              <button type="button" class="btn btn--primary" data-inbox-state="${i}">${it.state === 'new' ? 'Erledigt' : 'Wieder neu'}</button>
            </div>
          </details>
        </li>`).join('') + '</ul>'
        : `<p class="site-intro">${filter === 'new' ? 'Keine neuen Anfragen.' : 'Keine Anfragen.'}</p>`;
    };
    const load = async (action = 'inbox', data = {}) => {
      try {
        const res = await api(action, { filter, ...data });
        items = res.items || [];
        badge(res.new || 0);
        draw();
        if (res.message) say(res.message);
      } catch (err) { say(err.message, true); }
    };
    $('[data-inbox-open]').addEventListener('click', () => {
      menu.hidden = true;
      if ([panel, dpanel, ppanel, spanel, tpanel, hpanel].some((x) => !x.hidden)) return say('Bitte zuerst die andere Seitenleiste schließen.', true);
      ipanel.hidden = false;
      document.documentElement.classList.add('blocks-panel-open');
      load();
    });
    $('[data-inbox-close]').addEventListener('click', () => { ipanel.hidden = true; document.documentElement.classList.remove('blocks-panel-open'); });
    ipanel.addEventListener('click', async (e) => {
      const f = e.target.closest('[data-inbox-filter]');
      if (f) {
        filter = f.dataset.inboxFilter;
        ipanel.querySelectorAll('[data-inbox-filter]').forEach((b) => b.setAttribute('aria-selected', String(b === f)));
        return load();
      }
      const st = e.target.closest('[data-inbox-state]');
      if (st) {
        const it = items[+st.dataset.inboxState];
        return load('inbox_state', { table: it.table, id: it.id, state: it.state === 'new' ? 'done' : 'new' });
      }
      const del = e.target.closest('[data-inbox-delete]');
      if (del) {
        const it = items[+del.dataset.inboxDelete];
        if (!(await ask({ title: 'Anfrage löschen?', body: `„${it.title}“ wird endgültig aus „${it.tableLabel}“ gelöscht.`, ok: 'Löschen', danger: true }))) return;
        return load('inbox_delete', { table: it.table, id: it.id });
      }
    });
    if (cfg.inbox.new) $('[data-more]').classList.add('has-badge');
  }

  // ------------------------------------------------------------------ Website-Einstellungen (Admins)

  function initSite() {
    const body = $('[data-site-body]');
    const tabsEl = $('[data-site-tabs]');
    let tab = 'base';
    let reload = false;
    const drawTabs = (tabs) => {
      tabsEl.innerHTML = Object.entries(tabs).map(([k, l]) => `<button type="button" role="tab" data-site-tab="${esc(k)}" aria-selected="${k === tab}">${esc(l)}</button>`).join('');
    };
    const show = (res) => {
      tab = res.tab || tab;
      if (res.tabs) drawTabs(res.tabs);
      body.innerHTML = res.html;
      window.BlocksForm?.init(body, () => {});
      landingOnly();
      body.scrollTop = 0;
    };
    let lang = 0;   // Sprache der übersetzbaren Texte (0 = Startsprache)
    const load = async (t) => {
      tab = t;
      try { show(await api('site', { tab: t, lang })); } catch (err) { say(err.message, true); }
    };
    // Domains: Felder der Landing-Domain nur bei dieser Rolle
    const landingOnly = () => body.querySelectorAll('form').forEach((f) => {
      const role = f.querySelector('[data-domain-role]')?.value;
      f.querySelectorAll('[data-landing-only]').forEach((x) => { x.hidden = role !== 'landing'; });
    });
    const close = () => {
      spanel.hidden = true;
      document.documentElement.classList.remove('blocks-panel-open');
      if (reload) location.reload();
    };
    $('[data-site-open]').addEventListener('click', () => {
      menu.hidden = true;
      if (!panel.hidden || !dpanel.hidden || !ppanel.hidden) return say('Bitte zuerst die andere Seitenleiste schließen.', true);
      spanel.hidden = false;
      document.documentElement.classList.add('blocks-panel-open');
      drawTabs(cfg.site.tabs);
      load(tab);
    });
    $('[data-site-close]').addEventListener('click', close);
    tabsEl.addEventListener('click', (e) => {
      const b = e.target.closest('[data-site-tab]');
      if (b) { tabsEl.querySelectorAll('[data-site-tab]').forEach((x) => x.setAttribute('aria-selected', String(x === b))); load(b.dataset.siteTab); }
    });
    body.addEventListener('change', (e) => { if (e.target.matches('[data-domain-role]')) landingOnly(); });
    body.addEventListener('submit', async (e) => {
      const f = e.target.closest('[data-site-form]');
      if (!f) return;
      e.preventDefault();
      if (!f.reportValidity()) return;
      const fd = new FormData(f);
      fd.append('tab', f.dataset.tab || tab);
      const btn = f.querySelector('[type=submit]');
      if (btn) btn.disabled = true;
      try {
        const res = await api(f.dataset.siteForm, fd);
        if (res.reload) reload = true;
        show(res);
        say(res.message || 'Gespeichert.');
      } catch (err) { say(err.message, true); } finally { if (btn) btn.disabled = false; }
    });
    body.addEventListener('click', async (e) => {
      const lb = e.target.closest('[data-site-lang]');
      if (lb) {
        lang = +lb.dataset.siteLang;
        load(tab);
        return;
      }
      const act = e.target.closest('[data-site-act]');
      if (act) {
        if (act.dataset.confirm && !(await ask({ title: act.dataset.confirm, ok: 'Ja', danger: true }))) return;
        try { const res = await api(act.dataset.siteAct, { id: act.dataset.id, tab }); show(res); say(res.message); } catch (err) { say(err.message, true); }
        return;
      }
      const pre = e.target.closest('[data-prefill-source]');
      if (pre) {
        const src = body.querySelector('[data-redirect-source]');
        if (src) { src.value = pre.dataset.prefillSource; src.focus(); src.scrollIntoView({ block: 'center' }); }
        return;
      }
      const geo = e.target.closest('[data-geocode]');
      if (geo) {
        const f = geo.closest('form');
        const val = (n) => f.querySelector(`[name$="[${n}]"]`)?.value?.trim() || '';
        const q = [val('street'), [val('zip'), val('city')].filter(Boolean).join(' '), val('country')].filter(Boolean).join(', ') || val('address');
        if (!q) return say('Bitte zuerst die Adresse eintragen.', true);
        geo.disabled = true;
        try {
          const res = await api('geocode', { q });
          const hit = res.results?.[0];
          if (!hit) return say('Keinen Ort zu dieser Adresse gefunden.', true);
          geo.closest('.blocks-geo').querySelector('[data-geo]').value = `${hit.lat}, ${hit.lng}`;
          say(`Gefunden: ${hit.label}`);
        } catch (err) { say(err.message, true); } finally { geo.disabled = false; }
      }
    });
  }

  // ================================================================== Bearbeiten-Modus

  const blocks = () => [...main.querySelectorAll('[data-block]')];
  // Geschwister für „nach oben/unten“: Abschnitte untereinander bzw. Blöcke derselben Layout-Spalte
  const siblings = (el) => [...el.parentElement.children].filter((x) => x.matches('[data-block]'));
  const byId = (id) => main.querySelector(`[data-block="${id}"]`);
  let current = null;   // Block unter der Maus bzw. ausgewählt
  let selected = null;  // ausgewählter Block (Klick)
  let panelBlock = null;

  // ------------------------------------------------------------------ Text direkt auf der Seite

  function initEditables(scope) {
    scope.querySelectorAll('[data-edit]').forEach((el) => {
      if (el.closest('[data-blocks-ignore]')) return;
      const rich = el.dataset.editMode === 'rich';
      el.contentEditable = rich ? 'true' : 'plaintext-only';
      if (el.contentEditable !== (rich ? 'true' : 'plaintext-only')) el.contentEditable = 'true';
      el.spellcheck = true;
      el.dataset.blocksEditable = '';
      if (el.tagName === 'A' || el.closest('a')) el.closest('a')?.addEventListener('click', (e) => e.preventDefault());
    });
  }

  main.addEventListener('input', (e) => {
    const el = e.target.closest?.('[data-edit]');
    const sec = el?.closest('[data-block]');
    if (!el || !sec) return;
    const mode = el.dataset.editMode || 'plain';
    const value = mode === 'rich' ? el.innerHTML : mode === 'lines' ? el.innerText.replace(/\n{3,}/g, '\n\n') : el.innerText.replace(/\s*\n\s*/g, ' ');
    const patch = dirty.get(sec.dataset.block) || {};
    patch[el.dataset.edit] = value;
    dirty.set(sec.dataset.block, patch);
    render();
  });

  main.addEventListener('keydown', (e) => {
    const el = e.target.closest?.('[data-edit]');
    if (el && e.key === 'Enter' && !el.dataset.editMode) e.preventDefault();
    // Einzeilige Felder: Ende/Pos1 an Ende/Anfang des ganzen Feldes – nicht der sichtbaren Zeile (große Überschriften
    // brechen um, mit Silbentrennung sogar mitten im Wort); mit Umschalt wird markiert
    if (el && !el.dataset.editMode && (e.key === 'End' || e.key === 'Home') && !e.altKey && !e.ctrlKey && !e.metaKey) {
      e.preventDefault();
      const sel = getSelection();
      const r = document.createRange();
      r.selectNodeContents(el);
      r.collapse(e.key === 'Home');
      if (e.shiftKey && sel.rangeCount) sel.extend(r.startContainer, r.startOffset);
      else { sel.removeAllRanges(); sel.addRange(r); }
    }
  });

  main.addEventListener('paste', (e) => {
    const el = e.target.closest?.('[data-edit]');
    if (!el) return;
    e.preventDefault();
    const text = e.clipboardData.getData('text/plain');
    document.execCommand('insertText', false, el.dataset.editMode ? text : text.replace(/\s*\n\s*/g, ' '));
  });

  // Links und Formulare im Inhalt im Bearbeiten-Modus nicht auslösen
  main.addEventListener('click', (e) => {
    const sec = e.target.closest('[data-block]');
    if (e.target.closest('a[href]') && sec) e.preventDefault();
    if (sec) select(sec);
  }, true);
  main.addEventListener('submit', (e) => e.preventDefault(), true);

  async function saveAll() {
    if (!dirty.size) return true;
    busy++;
    render();
    try {
      for (const [id, patch] of [...dirty]) {
        const sec = main.querySelector(`section[data-block="${id}"]`);
        const res = await api('save', { slice: id, patch });
        dirty.delete(id);
        if (sec && !sec.contains(document.activeElement)) apply(res, sec);
      }
      say('Gespeichert.');
      return true;
    } catch (err) {
      say(err.message, true);
      return false;
    } finally {
      busy--;
      render();
    }
  }

  $('[data-save]').addEventListener('click', () => saveAll());
  document.addEventListener('keydown', (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') {
      e.preventDefault();
      if (!panel.hidden) applyPanel();
      else saveAll();
    }
    if (e.key === 'Escape') {
      if (!picker.hidden) closePicker();
      else if (!panel.hidden) cancelPanel();
    }
  });
  window.addEventListener('beforeunload', (e) => {
    if (dirty.size) e.preventDefault();
  });

  $('[data-publish]').addEventListener('click', async () => {
    if ($('[data-publish]').getAttribute('aria-disabled') === 'true') return;
    if (!(await saveAll())) return;
    busy++;
    render();
    try {
      const res = await api('publish');
      say(res.message || 'Veröffentlicht.');
    } catch (err) {
      say(err.message, true);
    } finally {
      busy--;
      render();
    }
  });

  root.querySelector('[data-discard]')?.addEventListener('click', async () => {
    if (!(await ask({ title: 'Entwurf verwerfen?', body: 'Alle Änderungen seit der letzten Veröffentlichung gehen verloren.', ok: 'Verwerfen', danger: true }))) return;
    dirty.clear();
    await api('discard').catch((err) => say(err.message, true));
    location.reload();
  });

  // Hinweis-Leiste von AddOns (cfg.notice): Knöpfe lösen ihr Ereignis am document aus; Meldungen über „fluid:notice“
  root.querySelectorAll('[data-notice-event]').forEach((b) => b.addEventListener('click', () => document.dispatchEvent(new CustomEvent(b.dataset.noticeEvent, { detail: { button: b } }))));
  document.addEventListener('fluid:notice', (e) => say(String(e.detail || ''), true));

  // ------------------------------------------------------------------ Blockleiste und „+“

  function replace(sec, html) {
    const el = h(html);
    const ids = [sec, ...sec.querySelectorAll('[data-block]')].map((x) => x.dataset.block);
    sec.replaceWith(el);
    // Skripte des neu ausgegebenen Blocks ausführen (innerHTML führt sie nicht aus) und Kit-Skripte benachrichtigen
    el.querySelectorAll('script').forEach((old) => {
      const n = document.createElement('script');
      for (const a of old.attributes) n.setAttribute(a.name, a.value);
      n.textContent = old.textContent;
      old.replaceWith(n);
    });
    document.dispatchEvent(new CustomEvent('fluid:rendered', { detail: { block: el } }));
    initEditables(el);
    initColumns(el);
    initHandles(el.closest('section[data-block]') || el);
    // Verweise auf ersetzte Blöcke (auch Spalten-Blöcke eines neu ausgegebenen Layouts) neu auflösen
    const again = (old) => (old && ids.includes(old.dataset.block) ? byId(old.dataset.block) : old);
    current = again(current);
    if (selected && ids.includes(selected.dataset.block)) { const s2 = byId(selected.dataset.block); selected = null; select(s2); }
    panelBlock = again(panelBlock);
    panelBlock?.setAttribute('data-blocks-panel', '');
    place();
    return el;
  }

  /** Antwort des Servers einsetzen: `target` = Block, der ersetzt wird (bei Spaltenwechsel das ganze Layout) */
  function apply(res, sec) {
    const wasChild = sec.hasAttribute('data-col');
    const target = byId(res.target) || sec;
    const el = replace(target, res.html);
    if (sec.isConnected && !el.contains(sec) && sec !== el) sec.remove();   // in eine Spalte gewechselt: alte Stelle weg
    if (wasChild && el.tagName === 'SECTION' && el.dataset.block === sec.dataset.block) location.reload();   // Layout verlassen
    return el;
  }

  function select(sec) {
    if (selected === sec) return;
    selected?.removeAttribute('data-blocks-selected');
    selected = sec;
    sec?.setAttribute('data-blocks-selected', '');
    current = sec;
    place();
  }

  main.addEventListener('mouseover', (e) => {
    if (!panel.hidden) return;
    const sec = e.target.closest('[data-block]');
    if (sec && sec !== current) {
      current = sec;
      place();
    }
  });

  function place() {
    const sec = panel.hidden ? current : panelBlock;
    if (!sec || !sec.isConnected) {
      blockbar.hidden = addbtn.hidden = true;
      return;
    }
    const r = sec.getBoundingClientRect();
    const barH = bar.offsetHeight;
    blockbar.hidden = r.bottom < barH + 40 || r.top > innerHeight - 40;
    blockbar.style.top = `${Math.max(r.top + 8, barH + 8)}px`;
    blockbar.style.left = `${Math.max(8, r.left + 8)}px`;
    $('[data-bb-label]').textContent = sec.dataset.label || sec.dataset.type;
    const offline = sec.hasAttribute('data-offline');
    const st = $('[data-bb-status]');
    // Datensatz-Blöcke: kein Online-Schalter, keine Spalten
    st.toggleAttribute('data-noperm', sec.hasAttribute('data-record') || !may('publishSlice'));
    st.textContent = offline ? 'Offline' : 'Online';
    st.classList.toggle('is-off', offline);
    const sib = siblings(sec);
    const i = sib.indexOf(sec);
    $('[data-bb-up]').disabled = i <= 0;
    $('[data-bb-down]').disabled = i === sib.length - 1;
    // „+“ an der Unterkante des Blocks – bei hohen Blöcken am unteren Fensterrand, solange der Block sichtbar ist
    addbtn.hidden = !panel.hidden || r.bottom < barH || r.top > innerHeight;
    addbtn.style.top = `${Math.min(r.bottom - 18, innerHeight - 52)}px`;
    addbtn.style.left = `${r.left + r.width / 2 - 18}px`;
  }
  window.addEventListener('scroll', place, { passive: true });
  window.addEventListener('resize', place);

  const target = () => (panel.hidden ? current : panelBlock);

  $('[data-bb-edit]').addEventListener('click', () => target() && openPanel(target()));

  async function move(dir) {
    const sec = target();
    if (!sec) return;
    try {
      await saveAll();
      await api('move', { slice: sec.dataset.block, dir });
      const sib = siblings(sec);
      const other = sib[sib.indexOf(sec) + (dir === 'up' ? -1 : 1)];
      if (other) {
        dir === 'up' ? other.before(sec) : other.after(sec);
        sec.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      }
      place();
    } catch (err) {
      say(err.message, true);
    }
  }
  $('[data-bb-up]').addEventListener('click', () => move('up'));
  $('[data-bb-down]').addEventListener('click', () => move('down'));

  $('[data-bb-status]').addEventListener('click', async () => {
    const sec = target();
    if (!sec) return;
    try {
      const res = await api('status', { slice: sec.dataset.block });
      apply(res, sec);
      say(res.online ? 'Block ist online.' : 'Block ist offline – auf der Website nicht sichtbar.');
    } catch (err) {
      say(err.message, true);
    }
  });

  $('[data-bb-delete]').addEventListener('click', async () => {
    const sec = target();
    if (!sec) return;
    if (!(await ask({ title: `„${sec.dataset.label}“ löschen?`, body: sec.hasAttribute('data-record') ? 'Der Block wird aus dem Eintrag entfernt – sofort gespeichert.' : 'Der Block wird aus dem Entwurf entfernt.', ok: 'Löschen', danger: true }))) return;
    try {
      const res = await api('delete', { slice: sec.dataset.block });
      if (res.reload) { location.reload(); return; }
      dirty.delete(sec.dataset.block);
      if (panelBlock === sec) closePanel();
      sec.remove();
      current = selected = null;
      place();
      emptyHint();
    } catch (err) {
      say(err.message, true);
    }
  });

  // ------------------------------------------------------------------ Block einfügen

  let insertAfter = null;
  let insertCol = 0;
  let insertInto = null;   // .lay-col, in die eingefügt wird

  function openPicker(after, col = 0, into = null) {
    insertAfter = after;
    insertCol = col;
    insertInto = into;
    const list = $('[data-picker-list]');
    const groups = {};
    // Datensatz-Blöcke (Feld „Fluid-Blöcke“): nur die im Feld erlaubten Blöcke
    const allowed = after?.closest?.('[data-record-blocks]')?.dataset.allowed?.split(',');
    cfg.modules.filter((m) => (!col || m.nestable) && (!allowed || allowed.includes(m.key))).forEach((m) => (groups[m.group] ||= []).push(m));
    list.innerHTML = Object.entries(groups).map(([g, ms]) => `<p class="picker__group">${esc(g)}</p>`
      + ms.map((m) => `<button type="button" role="option" class="picker__item" data-key="${esc(m.key)}" data-q="${esc((m.label + ' ' + m.help).toLowerCase())}"><strong>${esc(m.label)}</strong>${m.help ? `<span>${esc(m.help)}</span>` : ''}</button>`).join('')).join('');
    picker.hidden = false;
    const q = $('[data-picker-q]');
    q.value = '';
    q.focus();
  }
  function closePicker() {
    picker.hidden = true;
  }
  // „+“ unter einem Block: Spalten-Block → in dieselbe Spalte dahinter, sonst als Abschnitt dahinter
  addbtn.addEventListener('click', () => {
    const col = Number(current?.dataset.col || 0);
    openPicker(current, col, col ? current.parentElement : null);
  });
  $('[data-picker-close]').addEventListener('click', closePicker);
  picker.addEventListener('click', (e) => e.target === picker && closePicker());
  $('[data-picker-q]').addEventListener('input', (e) => {
    const q = e.target.value.trim().toLowerCase();
    root.querySelectorAll('.picker__item').forEach((b) => (b.hidden = q !== '' && !b.dataset.q.includes(q)));
  });
  $('[data-picker-list]').addEventListener('click', async (e) => {
    const item = e.target.closest('.picker__item');
    if (!item) return;
    closePicker();
    try {
      await saveAll();
      const res = await api('add', { module: item.dataset.key, after: insertAfter?.dataset.block || insertAfter?.dataset.recordAdd || 0, col: insertCol });
      const el = h(res.html);
      if (insertInto && insertAfter?.parentElement === insertInto) insertAfter.after(el);
      else if (insertInto) {
        const btn = insertInto.querySelector(':scope > [data-blocks-coladd]');
        if (btn) btn.before(el); else insertInto.append(el);
      }
      else if (insertAfter?.isConnected) (insertAfter.closest('section[data-block]') || insertAfter).after(el);
      else main.prepend(el);
      insertInto?.classList.remove('lay-col--empty');
      initEditables(el);
      emptyHint();
      el.scrollIntoView({ block: 'center', behavior: 'smooth' });
      select(el);
      openPanel(el);
    } catch (err) {
      say(err.message, true);
    }
  });

  function emptyHint() {
    let hint = main.querySelector('[data-blocks-empty]');
    if (blocks().length) {
      hint?.remove();
      return;
    }
    if (!hint) {
      hint = document.createElement('div');
      hint.dataset.blocksEmpty = '';
      // in einer weiteren Sprache: Inhalt einer gefüllten Sprache übernehmen und dann übersetzen
      const sources = may('copyContent') ? (cfg.languages || []).filter((l) => !l.current && !l.empty) : [];
      hint.innerHTML = '<p>Diese Seite hat noch keine Blöcke.</p><button type="button" data-first>+ Ersten Block einfügen</button>'
        + sources.map((l) => `<button type="button" data-lang-copy="${l.id}">Inhalt aus ${esc(l.name)} übernehmen</button>`).join('');
      hint.querySelector('[data-first]').addEventListener('click', () => openPicker(null));
      hint.querySelectorAll('[data-lang-copy]').forEach((b) => b.addEventListener('click', async () => {
        b.disabled = true;
        try {
          const res = await api('lang_copy', { from: b.dataset.langCopy });
          say(res.message);
          location.reload();
        } catch (err) { b.disabled = false; say(err.message, true); }
      }));
      // Beitragsseite: unter den Beitragskopf
      const ph = main.querySelector(':scope > .post-head');
      if (ph) ph.after(hint); else main.prepend(hint);
    }
  }

  // „+ Block in diese Spalte“ in jeder Spalte eines Layouts (Light DOM, Stil in editor.css)
  function initColumns(scope) {
    const cols = scope.matches?.('[data-lay-col]') ? [scope] : [...scope.querySelectorAll('[data-lay-col]')];
    cols.forEach((col) => {
      if (col.querySelector(':scope > [data-blocks-coladd]')) return;
      const b = document.createElement('button');
      b.type = 'button';
      b.dataset.blocksColadd = '';
      b.textContent = '+ Block in diese Spalte';
      b.addEventListener('click', (e) => {
        e.stopPropagation();
        const layout = col.closest('[data-layout]');
        openPicker(layout, Number(col.dataset.layCol), col);
      });
      col.append(b);
    });
  }

  // ------------------------------------------------------------------ Seitenleiste: alle Felder

  let original = '';
  let previewTimer = 0;
  let previewSeq = 0;
  const form = $('[data-panel-form]');

  async function openPanel(sec) {
    if (dirty.has(sec.dataset.block) && !(await saveAll())) return;
    if (!panel.hidden && panelBlock !== sec) await cancelPanel();
    try {
      const res = await api('form', { slice: sec.dataset.block });
      panelBlock = sec;
      original = sec.outerHTML;
      $('[data-panel-title]').textContent = res.label;
      form.innerHTML = res.html;
      window.BlocksForm?.init(form, schedulePreview);
      Rich?.initFields(form);
      panel.hidden = false;
      document.documentElement.classList.add('blocks-panel-open');
      sec.setAttribute('data-blocks-panel', '');
      sec.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      place();
      form.querySelector('input:not([type=hidden]),textarea,select')?.focus({ preventScroll: true });
    } catch (err) {
      say(err.message, true);
    }
  }

  function closePanel() {
    panel.hidden = true;
    form.innerHTML = '';
    panelBlock?.removeAttribute('data-blocks-panel');
    panelBlock = null;
    document.documentElement.classList.remove('blocks-panel-open');
    place();
  }

  async function cancelPanel() {
    clearTimeout(previewTimer);
    previewSeq++;
    if (panelBlock && original) replace(panelBlock, original);
    closePanel();
  }

  async function applyPanel() {
    if (!panelBlock) return;
    clearTimeout(previewTimer);
    previewSeq++;
    const fd = new FormData(form);
    fd.append('slice', panelBlock.dataset.block);
    busy++;
    render();
    try {
      const res = await api('save', fd);
      const sec = panelBlock;
      closePanel();
      apply(res, sec);
      say('Gespeichert.');
    } catch (err) {
      say(err.message, true);
    } finally {
      busy--;
      render();
    }
  }

  $('[data-panel-close]').addEventListener('click', cancelPanel);
  $('[data-panel-cancel]').addEventListener('click', cancelPanel);
  $('[data-panel-apply]').addEventListener('click', applyPanel);

  function schedulePreview() {
    clearTimeout(previewTimer);
    previewTimer = setTimeout(async () => {
      if (!panelBlock) return;
      const seq = ++previewSeq;
      const fd = new FormData(form);
      fd.append('slice', panelBlock.dataset.block);
      try {
        const res = await api('preview', fd);
        if (seq === previewSeq && panelBlock) replace(byId(res.target) || panelBlock, res.html);
      } catch (err) {
        say(err.message, true);
      }
    }, 350);
  }

  form.addEventListener('input', () => schedulePreview());
  form.addEventListener('change', () => schedulePreview());
  form.addEventListener('click', (e) => e.target.closest('[data-blocks-add]') && setTimeout(() => Rich?.initFields(form)));

  // ------------------------------------------------------------------ Bilder bearbeiten (Addon image_editor, optional)

  const ieBtn = h('<div class="iebtn" hidden><button type="button" data-ie="edit">✎ Bearbeiten</button><button type="button" data-ie="adjust">◐ Anpassen</button><button type="button" data-ie="crop" hidden>⬚ Ausschnitt</button></div>');
  root.append(ieBtn);
  let ieImg = null;
  // Skript-Lader in form.js: CSS und Dialog gehören in diese Ebene
  document.addEventListener('blocks:image-editor', (e) => {
    e.preventDefault();
    root.append(h(`<link rel="stylesheet" href="${esc(unamp(e.detail.css))}">`));
    window.ImageEditor.setLayer(layer);
  });
  if (cfg.imageEditor) {
    // Bild unter dem Zeiger – auch wenn eine Ebene darüber liegt (ganze Karte als Link mit ::after, Verlauf, Beschriftung);
    // dann trifft mouseover nie das <img> (wie KLXM Studio _media.js imgAt)
    const imgAt = (e) => e.target.closest?.('img[data-ie-file]')
      || document.elementsFromPoint(e.clientX, e.clientY).find((x) => x.matches?.('img[data-ie-file]')) || null;
    let ieRaf = 0;
    main.addEventListener('mousemove', (e) => {
      if (ieRaf) return;
      ieRaf = requestAnimationFrame(() => { ieRaf = 0; showIe(imgAt(e)); });
    }, { passive: true });
    const showIe = (img) => {
      if (!img || img === ieImg) return;
      ieImg = img;
      // „Ausschnitt“ nur, wenn das Bild in einem festen Format steht (data-ie-ratio)
      ieBtn.querySelector('[data-ie="crop"]').hidden = !img.dataset.ieRatio;
      ieBtn.hidden = false;
      const r = img.getBoundingClientRect();
      ieBtn.style.top = `${Math.max(bar.offsetHeight + 8, r.top + 10)}px`;
      ieBtn.style.left = `${Math.max(8, r.right - ieBtn.offsetWidth - 10)}px`;
    };
    window.addEventListener('scroll', () => { ieBtn.hidden = true; ieImg = null; }, { passive: true });
    ieBtn.addEventListener('click', async (e) => {
      const which = e.target.closest('[data-ie]')?.dataset.ie;
      const img = ieImg;
      const sec = img?.closest('[data-block]');
      if (!which || !img || !sec) return;
      ieBtn.hidden = true;
      try {
        const IE = await window.BlocksPick.imageEditor(cfg.imageEditor);
        const opts = { file: img.dataset.ieFile, api: cfg.imageEditor.api };
        const item = which === 'adjust' ? await IE.adjust(opts)
          : which === 'crop' ? await IE.crop({ ...opts, ratio: img.dataset.ieRatio })
          : await IE.open({ ...opts, tool: 'crop' });
        if (!item) return;
        const res = await api('render', { slice: sec.dataset.block });
        apply(res, sec);
        say('Bild gespeichert – das Original bleibt im Medienpool erhalten.');
      } catch (err) {
        say(err.message, true);
      }
      ieImg = null;
    });
  }

  // ------------------------------------------------------------------ Formatierung (Studio-Editor, assets/rte.js)

  const Rich = window.BlocksRich;
  Rich?.setLayer(layer);
  const inline = Rich?.inlineBar({ minTop: () => bar.offsetHeight });
  main.addEventListener('focusin', (e) => {
    const el = e.target.closest?.('[data-edit][data-edit-mode="rich"]');
    if (el && inline) inline.show(el, 'rich');
  });
  main.addEventListener('focusout', (e) => {
    if (e.target.closest?.('[data-edit][data-edit-mode="rich"]')) inline?.hideSoon();
  });
  ['keyup', 'mouseup'].forEach((ev) => main.addEventListener(ev, () => inline?.update()));


  // ------------------------------------------------------------------ Blöcke: Menü ⋯, Ziehen, Zwischenablage, Einklappen

  const CLIP = 'fluid-clipboard';
  const bbMenu = $('.bb__menu');
  const bbMore = $('[data-bb-more]');
  const clip = () => { try { return JSON.parse(localStorage.getItem(CLIP) || 'null'); } catch { return null; } };
  bbMore.addEventListener('click', (e) => {
    e.stopPropagation();
    const sec = target();
    bbMenu.hidden = !bbMenu.hidden;
    bbMore.setAttribute('aria-expanded', String(!bbMenu.hidden));
    const inCol = !!sec?.closest('[data-lay-col]');
    bbMenu.querySelectorAll('[data-col-only]').forEach((b) => { b.hidden = !inCol; });
    const c = clip();
    const paste = bbMenu.querySelector('[data-bb-op="paste"]');
    paste.disabled = !c || inCol;
    paste.textContent = c ? `Einfügen darunter: „${c.label}“` : 'Einfügen darunter';
    bbMenu.querySelector('[data-bb-op="duplicate"]').disabled = inCol;
    bbMenu.querySelector('[data-bb-op="collapse"]').textContent = sec?.classList.contains('fluid-collapsed') ? 'Aufklappen' : 'Einklappen';
  });
  document.addEventListener('click', () => { bbMenu.hidden = true; bbMore.setAttribute('aria-expanded', 'false'); });
  // Eingeklappte Blöcke je Seite merken (nur in diesem Browser)
  const FOLD = 'fluid-folded-' + cfg.article.id;
  const folded = () => { try { return new Set(JSON.parse(localStorage.getItem(FOLD) || '[]')); } catch { return new Set(); } };
  const setFold = (sec, on) => {
    sec.classList.toggle('fluid-collapsed', on);
    const f = folded();
    on ? f.add(sec.dataset.block) : f.delete(sec.dataset.block);
    try { localStorage.setItem(FOLD, JSON.stringify([...f])); } catch { /* privat */ }
  };
  const applyFolds = () => { const f = folded(); main.querySelectorAll('section[data-block]').forEach((s) => s.classList.toggle('fluid-collapsed', f.has(s.dataset.block))); };
  applyFolds();
  main.addEventListener('click', (e) => {
    const sec = e.target.closest?.('section.fluid-collapsed');
    if (sec && !e.target.closest('.fluid-handle')) { e.preventDefault(); setFold(sec, false); place(); }
  });
  $('[data-compact]').addEventListener('click', () => {
    menu.hidden = true;
    const secs = [...main.querySelectorAll(':scope section[data-block]')].filter((s) => !s.parentElement.closest('section[data-block]'));
    const on = secs.some((s) => !s.classList.contains('fluid-collapsed'));
    secs.forEach((s) => setFold(s, on));
    say(on ? 'Kompaktansicht: Blöcke zugeklappt – Klick auf einen Block klappt ihn auf.' : 'Alle Blöcke aufgeklappt.');
    place();
  });
  bbMenu.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-bb-op]');
    const sec = target();
    if (!b || !sec) return;
    const op = b.dataset.bbOp;
    const top = sec.closest('section[data-block]:not([data-lay-col] *)') || sec;
    try {
      if (op === 'collapse') { setFold(top, !top.classList.contains('fluid-collapsed')); place(); return; }
      await saveAll();
      if (op === 'copy') {
        const res = await api('copy', { slice: sec.dataset.block });
        localStorage.setItem(CLIP, JSON.stringify({ clip: res.clip, label: res.label }));
        say(`„${res.label}“ kopiert – auf dieser oder einer anderen Seite über ⋯ → Einfügen.`);
        return;
      }
      if (op === 'left' || op === 'right') {
        const res = await api('col_move', { slice: sec.dataset.block, dir: op });
        const lay = sec.closest('section[data-block]');
        apply(await api('render', { slice: lay.dataset.block }), lay);
        return;
      }
      const res = op === 'duplicate'
        ? await api('duplicate', { slice: top.dataset.block, after: top.dataset.block })
        : await api('paste', { after: top.dataset.block, clip: JSON.stringify(clip()?.clip || null) });
      const el = h(res.html);
      top.after(el);
      initEditables(el);
      initColumns(el);
      initHandles(el);
      document.dispatchEvent(new CustomEvent('fluid:rendered', { detail: { block: el } }));
      el.scrollIntoView({ block: 'center', behavior: 'smooth' });
      select(el);
      say(op === 'duplicate' ? 'Block verdoppelt.' : 'Block eingefügt.');
    } catch (err) { say(err.message, true); }
  });

  // Ziehen am Namen in der Blockleiste: Abschnitte umsortieren (Layout samt Spalten)
  const dropLine = document.createElement('div');
  dropLine.className = 'fluid-dropline';
  dropLine.hidden = true;
  document.body.append(dropLine);
  let dragSec = null;
  let dropBefore = null;
  const label = $('[data-bb-label]');
  label.addEventListener('dragstart', (e) => {
    const sec = target();
    if (!sec || sec.closest('[data-lay-col]')) { e.preventDefault(); say('Blöcke in Spalten verschieben Sie mit ↑ ↓ bzw. ⋯ → linke/rechte Spalte.', true); return; }
    dragSec = sec;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', sec.dataset.block);
    sec.classList.add('fluid-dragging');
  });
  label.addEventListener('dragend', () => { dragSec?.classList.remove('fluid-dragging'); dragSec = null; dropLine.hidden = true; });
  document.addEventListener('dragover', (e) => {
    if (!dragSec) return;
    e.preventDefault();
    // Datensatz-Blöcke nur innerhalb ihrer Liste, Seiten-Blöcke nicht in eine Datensatz-Liste
    const secs = dragSec.hasAttribute('data-record')
      ? siblings(dragSec)
      : [...main.querySelectorAll('section[data-block]')].filter((s) => !s.closest('[data-lay-col]') && !s.hasAttribute('data-record'));
    dropBefore = secs.find((s) => { const r = s.getBoundingClientRect(); return e.clientY < r.top + r.height / 2; }) || null;
    const ref = dropBefore || secs[secs.length - 1];
    const r = ref.getBoundingClientRect();
    dropLine.hidden = false;
    dropLine.style.top = `${(dropBefore ? r.top : r.bottom) - 2}px`;
  });
  document.addEventListener('drop', async (e) => {
    if (!dragSec) return;
    e.preventDefault();
    const sec = dragSec;
    dropLine.hidden = true;
    if (dropBefore === sec || dropBefore === sec.nextElementSibling) return;
    try {
      await saveAll();
      await api('reorder', { slice: sec.dataset.block, before: dropBefore?.dataset.block || 0 });
      dropBefore ? dropBefore.before(sec) : (sec.hasAttribute('data-record') ? siblings(sec).at(-1) : main.querySelector('section[data-block]:last-of-type'))?.after(sec);
      sec.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      say('Block verschoben.');
      place();
    } catch (err) { say(err.message, true); }
  });

  // ------------------------------------------------------------------ YForm-Einträge (Datenliste)

  // „Bearbeiten“ / „Neuer Eintrag“: YForm-Maske im Popup (wie Medienpool); nach dem Schließen den Block neu ausgeben
  main.addEventListener('click', (e) => {
    const a = e.target.closest?.('a.fdl-edit');
    if (!a) return;
    e.preventDefault();
    const sec = a.closest('[data-block]');
    const w = window.open(a.href, 'fluid_yform', 'width=1100,height=820,resizable=yes,scrollbars=yes');
    if (!w) { location.href = a.href; return; }
    w.focus();
    const timer = setInterval(async () => {
      if (!w.closed) return;
      clearInterval(timer);
      if (!sec?.isConnected) return;
      try {
        apply(await api('render', { slice: sec.dataset.block }), sec);
        say('Liste aktualisiert.');
      } catch (err) { say(err.message, true); }
    }, 600);
  });

  // ------------------------------------------------------------------ Greifer: Abstände und Spaltenbreiten (wie KLXM Studio)

  /*
   * Abstand oben/unten (violett ↕) am Rand jedes Abschnitts: Ziehen um je ~56 px wechselt Kein → Klein → Normal → Groß
   * (Vorschau sofort über pt-/pb-Klassen). Spaltenbreiten (grün ↔) zwischen zwei Layout-Spalten: rastet auf Zwölftel ein,
   * Nachbarspalte gleicht aus; Doppelklick bzw. Entf = Breiten des Rasters. Pfeiltasten je ein Schritt. Änderungen landen
   * wie Text in den ungespeicherten Änderungen (dirty) – „Speichern“ übernimmt sie.
   */
  const HANDLE_CSS = `
    :host{all:initial}
    .h{--k:#7C3AED;all:unset;box-sizing:border-box;position:absolute;inset:0;display:grid;place-items:center;touch-action:none}
    .h--y{cursor:row-resize}.h--x{cursor:col-resize}.h--cols{--k:#0F9F74}.h--width{--k:#D97706}.h--split{--k:#DB2777}
    .g{display:grid;place-items:center;border-radius:999px;background:var(--k);color:#fff;box-shadow:0 0 0 2px #fff,0 4px 12px rgb(0 0 0/.25);opacity:.7;transition:opacity .15s,scale .15s}
    .g svg{width:12px;height:12px;fill:none;stroke:currentColor;stroke-width:2.4;stroke-linecap:round;stroke-linejoin:round}
    .h--y .g{width:44px;height:18px}.h--x .g{width:18px;height:44px}
    .h:is(:hover,:focus-visible,.is-drag) .g{opacity:1;scale:1.12}
    .h--x::before{content:"";position:absolute;top:0;bottom:0;left:50%;width:2px;margin-left:-1px;background:var(--k);opacity:0;transition:opacity .15s}
    .h--x:is(:hover,:focus-visible,.is-drag)::before{opacity:.55}
    .h--y::before{content:"";position:absolute;left:-28px;right:-600px;top:50%;height:2px;margin-top:-1px;background:var(--k);opacity:0;transition:opacity .15s;pointer-events:none}
    .h--y:is(:hover,:focus-visible,.is-drag)::before{opacity:.45}
    .h:focus-visible{outline:2px solid var(--k);outline-offset:3px;border-radius:8px}
    .t{position:absolute;left:0;top:-32px;padding:4px 9px;border-radius:999px;background:var(--k);color:#fff;font:600 12px/1.2 system-ui,-apple-system,sans-serif;white-space:nowrap;pointer-events:none;opacity:0;transition:opacity .15s;box-shadow:0 4px 12px rgb(0 0 0/.2)}
    .h--x .t{top:50%;left:50%;translate:-50% calc(-50% - 44px)}
    .h:is(:hover,:focus-visible,.is-drag) .t{opacity:1}
    @media (prefers-reduced-motion:reduce){.g,.t{transition:none}}`;
  const ICON_Y = '<svg viewBox="0 0 24 24"><path d="M12 3v18M8 7l4-4 4 4M8 17l4 4 4-4"/></svg>';
  const ICON_X = '<svg viewBox="0 0 24 24"><path d="M3 12h18M7 8l-4 4 4 4M17 8l4 4-4 4"/></svg>';
  const SPACE = { none: 'Kein', small: 'Klein', normal: 'Normal', large: 'Groß' };

  function handleHost(parent, css, html) {
    const host = document.createElement('div');
    host.className = 'fluid-handle';
    host.contentEditable = 'false';
    host.style.cssText = css;
    const sr = host.attachShadow({ mode: 'open' });
    sr.innerHTML = `<style>${HANDLE_CSS}</style>${html}`;
    parent.append(host);
    return sr;
  }

  function patchBlock(sec, key, value) {
    const patch = dirty.get(sec.dataset.block) || {};
    patch[key] = value;
    dirty.set(sec.dataset.block, patch);
    render();
  }

  function initHandles(scope) {
    const secs = scope.matches?.('section[data-block]') ? [scope] : [...scope.querySelectorAll('section[data-block]')];
    secs.forEach((sec) => {
      sec.querySelectorAll(':scope > .fluid-handle, [data-lay-col] > .fluid-handle').forEach((x) => x.remove());
      requestAnimationFrame(() => { spaceHandles(sec); colHandles(sec); fieldHandles(sec); });
    });
  }

  function spaceHandles(sec) {
    if (!sec.isConnected) return;
    if (getComputedStyle(sec).position === 'static') sec.style.position = 'relative';
    for (const side of ['top', 'bottom']) {
      const pre = side === 'top' ? 'pt-' : 'pb-', key = side === 'top' ? '@spaceTop' : '@spaceBottom';
      const prop = side === 'top' ? 'paddingTop' : 'paddingBottom';
      const values = ['none', 'small', 'normal', 'large'];
      // Wirkt der Abstand hier? (Kopfbereiche haben eigene Abstände) – Klassen kurz ausprobieren, sonst kein Griff
      const keep = sec.className, seen = new Set();
      for (const v of values) { values.forEach((x) => sec.classList.remove(pre + x)); if (v !== 'normal') sec.classList.add(pre + v); seen.add(getComputedStyle(sec)[prop]); }
      sec.className = keep;
      if (seen.size < 2) continue;
      const cur = () => {
        const p = dirty.get(sec.dataset.block)?.[key];
        return p || values.find((v) => v !== 'normal' && sec.classList.contains(pre + v)) || 'normal';
      };
      const label = side === 'top' ? 'Abstand oben' : 'Abstand unten';
      const sr = handleHost(sec, `position:absolute;left:${side === 'top' ? 116 : 28}px;${side}:-12px;width:72px;height:24px;z-index:30`,
        `<button type="button" class="h h--y" role="slider" aria-orientation="vertical"><span class="g" aria-hidden="true">${ICON_Y}</span><span class="t"></span></button>`);
      const h = sr.querySelector('.h'), tip = sr.querySelector('.t');
      const show = (v) => {
        tip.textContent = `${label}: ${SPACE[v]} · ↕ ziehen`;
        h.setAttribute('aria-label', `${label} ziehen – jetzt ${SPACE[v]}. Pfeiltasten: kleiner/größer.`);
        h.setAttribute('aria-valuenow', String(values.indexOf(v)));
        h.setAttribute('aria-valuemin', '0');
        h.setAttribute('aria-valuemax', '3');
        h.setAttribute('aria-valuetext', SPACE[v]);
      };
      const preview = (v) => { values.forEach((x) => sec.classList.remove(pre + x)); if (v !== 'normal') sec.classList.add(pre + v); show(v); };
      const commit = (v) => { patchBlock(sec, key, v); say(`${label}: ${SPACE[v]} – noch nicht gespeichert.`); };
      show(cur());
      let drag = null;
      h.addEventListener('pointerdown', (e) => {
        e.preventDefault(); e.stopPropagation();
        h.setPointerCapture(e.pointerId); h.classList.add('is-drag');
        drag = { y: e.clientY, i0: values.indexOf(cur()), i: values.indexOf(cur()) };
      });
      h.addEventListener('pointermove', (e) => {
        if (!drag) return;
        const i = Math.max(0, Math.min(3, drag.i0 + Math.round((e.clientY - drag.y) / 56)));
        if (i !== drag.i) { drag.i = i; preview(values[i]); }
      });
      const end = (e) => {
        if (!drag) return;
        const { i, i0 } = drag; drag = null; h.classList.remove('is-drag');
        if (h.hasPointerCapture?.(e.pointerId)) h.releasePointerCapture(e.pointerId);
        if (i !== i0) commit(values[i]);
      };
      h.addEventListener('pointerup', end);
      h.addEventListener('pointercancel', end);
      h.addEventListener('lostpointercapture', end);   // Erfassung verloren (Tab-Wechsel, Systemgeste): Zug trotzdem abschließen
      h.addEventListener('click', (e) => e.stopPropagation());
      h.addEventListener('keydown', (e) => {
        const step = e.key === 'ArrowDown' ? 1 : e.key === 'ArrowUp' ? -1 : 0;
        if (!step) return;
        e.preventDefault(); e.stopPropagation();
        const i = Math.max(0, Math.min(3, values.indexOf(cur()) + step));
        if (values[i] === cur()) return;
        preview(values[i]); commit(values[i]);
      });
    }
  }

  function colHandles(sec) {
    const grid = sec.querySelector('.lay-grid[data-lay-w]');
    if (!grid) return;
    const cols = [...grid.children].filter((c) => c.matches('[data-lay-col]'));
    let units = grid.dataset.layW.split(',').map(Number);
    const pw = dirty.get(sec.dataset.block)?.widths;
    if (pw) units = pw.split(',').map(Number);
    if (cols.length < 2 || units.length !== cols.length) return;
    if (cols[1].getBoundingClientRect().top > cols[0].getBoundingClientRect().top + 8) return;   // untereinander (schmal)
    const U = 12, MIN = 2;
    const frac = (n) => ({ 2: '⅙', 3: '¼', 4: '⅓', 6: '½', 8: '⅔', 9: '¾', 10: '⅚' })[n] || n + '/12';
    const label = (w) => w.map(frac).join(' · ');
    const apply = (w) => cols.forEach((c, i) => c.style.setProperty('--lay-w', w[i]));
    const base = grid.dataset.layPreset || grid.dataset.layW;   // Breiten des Rasters (Doppelklick/Entf)
    const commit = (w) => {
      units = w ? [...w] : base.split(',').map(Number);
      apply(units);
      patchBlock(sec, 'widths', w ? w.join(',') : '');
      say(w ? `Spaltenbreiten: ${label(w)} – noch nicht gespeichert.` : 'Spaltenbreiten zurück zum Raster – noch nicht gespeichert.');
      cols.forEach((c) => c.querySelector(':scope > .fluid-handle')?.remove());
      requestAnimationFrame(() => colHandles(sec));
    };
    if (pw) apply(units);
    const gap = parseFloat(getComputedStyle(grid).columnGap) || 0;
    for (let i = 0; i < cols.length - 1; i++) {
      const col = cols[i];
      if (getComputedStyle(col).position === 'static') col.style.position = 'relative';
      const sr = handleHost(col, `position:absolute;top:0;bottom:0;right:${-(gap / 2) - 11}px;width:22px;z-index:30`,
        `<button type="button" class="h h--x h--cols" role="separator" aria-orientation="vertical"><span class="g" aria-hidden="true">${ICON_X}</span><span class="t"></span></button>`);
      const h = sr.querySelector('.h'), tip = sr.querySelector('.t');
      const aria = (w) => {
        tip.textContent = `Spalten: ${label(w)} · ↔ ziehen`;
        h.setAttribute('aria-label', `Breite von Spalte ${i + 1} und ${i + 2} ziehen – jetzt ${label(w)}. Pfeiltasten: schmaler/breiter, Entf: zurück zum Raster.`);
        h.setAttribute('aria-valuenow', String(w[i]));
        h.setAttribute('aria-valuemin', String(MIN));
        h.setAttribute('aria-valuemax', String(w[i] + w[i + 1] - MIN));
      };
      aria(units);
      h.title = 'Ziehen: Spaltenbreite ändern · Doppelklick: zurück zum Raster';
      let drag = null;
      h.addEventListener('pointerdown', (e) => {
        e.preventDefault(); e.stopPropagation();
        h.setPointerCapture(e.pointerId); h.classList.add('is-drag');
        const total = grid.getBoundingClientRect().width - gap * (cols.length - 1);
        drag = { w: [...units], left: col.getBoundingClientRect().left, unit: total / U, pair: units[i] + units[i + 1] };
      });
      h.addEventListener('pointermove', (e) => {
        if (!drag) return;
        const n = Math.max(MIN, Math.min(drag.pair - MIN, Math.round((e.clientX - drag.left) / drag.unit)));
        if (n === drag.w[i]) return;
        drag.w[i] = n; drag.w[i + 1] = drag.pair - n;
        apply(drag.w); aria(drag.w);
      });
      const end = (e) => {
        if (!drag) return;
        const w = drag.w; drag = null; h.classList.remove('is-drag');
        if (h.hasPointerCapture?.(e.pointerId)) h.releasePointerCapture(e.pointerId);
        if (w.join() !== units.join()) commit(w);
      };
      h.addEventListener('pointerup', end);
      h.addEventListener('pointercancel', end);
      h.addEventListener('lostpointercapture', end);   // Erfassung verloren (Tab-Wechsel, Systemgeste): Zug trotzdem abschließen
      h.addEventListener('dblclick', (e) => { e.stopPropagation(); commit(null); });
      h.addEventListener('click', (e) => e.stopPropagation());
      h.addEventListener('keydown', (e) => {
        e.stopPropagation();
        if (e.key === 'Delete' || e.key === 'Backspace') { e.preventDefault(); commit(null); return; }
        const step = e.key === 'ArrowLeft' ? -1 : e.key === 'ArrowRight' ? 1 : 0;
        if (!step) return;
        e.preventDefault();
        const w = [...units], pair = w[i] + w[i + 1], n = Math.max(MIN, Math.min(pair - MIN, w[i] + step));
        if (n === w[i]) return;
        w[i] = n; w[i + 1] = pair - n;
        commit(w);
      });
    }
  }

  /*
   * Felder, die der Block als Griff anbietet (Kit 'drags'): Textbreite (bernstein), Aufteilung Bild/Text (pink).
   * Griff an der Kante des Zielelements; weg vom Element ziehen = größer, je ~56 px ein Wert, Vorschau über die Klasse.
   */
  function fieldHandles(sec) {
    if (!sec.isConnected) return;
    const type = sec.dataset.type || '';
    const mod = (cfg.modules || []).find((m) => m.key === type || m.key.endsWith('.' + type));
    for (const g of mod?.drags || []) {
      const target = sec.querySelector(g.target);
      const values = Object.keys(g.options || {});
      if (!target || values.length < 2) continue;
      const apply = g.apply ? target.closest(g.apply) || target : target;
      const cls = (v) => g.class.replace('{v}', v);
      const cur = () => dirty.get(sec.dataset.block)?.[g.field] || values.find((v) => apply.classList.contains(cls(v))) || g.default;
      if (getComputedStyle(sec).position === 'static') sec.style.position = 'relative';
      const sr = handleHost(sec, 'position:absolute;width:24px;z-index:30',
        `<button type="button" class="h h--x h--${g.kind}" role="slider" aria-orientation="horizontal"><span class="g" aria-hidden="true">${ICON_X}</span><span class="t"></span></button>`);
      const host = sr.host, h = sr.querySelector('.h'), tip = sr.querySelector('.t');
      let dir = 1, drag = null;
      // Griff sitzt an der Kante des Ziels (nie im bearbeitbaren Text): Element rechts → Griff links, nach links ziehen = größer
      const place = () => {
        const t = target.getBoundingClientRect(), hr = sec.getBoundingClientRect(), pr = (target.parentElement || target).getBoundingClientRect();
        if (!t.width) return;
        const leftEdge = (t.left + t.right) / 2 > (pr.left + pr.right) / 2 + 12;
        if (!drag) dir = leftEdge ? -1 : 1;
        host.style.top = `${t.top - hr.top}px`;
        host.style.height = `${Math.max(48, t.height)}px`;
        host.style.left = `${(leftEdge ? t.left : t.right) - hr.left - 12}px`;
      };
      const show = (v) => {
        tip.textContent = `${g.label}: ${g.options[v]} · ↔ ziehen`;
        h.setAttribute('aria-label', `${g.label} ziehen – jetzt ${g.options[v]}. Pfeiltasten: kleiner/größer.`);
        h.setAttribute('aria-valuenow', String(values.indexOf(v)));
        h.setAttribute('aria-valuemin', '0');
        h.setAttribute('aria-valuemax', String(values.length - 1));
        h.setAttribute('aria-valuetext', g.options[v]);
      };
      const preview = (v) => { values.forEach((x) => apply.classList.remove(cls(x))); apply.classList.add(cls(v)); show(v); place(); };
      const commit = (v) => { patchBlock(sec, g.field, v); say(`${g.label}: ${g.options[v]} – noch nicht gespeichert.`); };
      show(cur());
      requestAnimationFrame(place);
      sec.addEventListener('pointerenter', () => { if (!drag) place(); });
      h.addEventListener('pointerdown', (e) => {
        e.preventDefault(); e.stopPropagation();
        h.setPointerCapture(e.pointerId); h.classList.add('is-drag');
        const i = Math.max(0, values.indexOf(cur()));
        drag = { x: e.clientX, i0: i, i };
      });
      h.addEventListener('pointermove', (e) => {
        if (!drag) return;
        const i = Math.max(0, Math.min(values.length - 1, drag.i0 + Math.round(((e.clientX - drag.x) * dir) / 56)));
        if (i !== drag.i) { drag.i = i; preview(values[i]); }
      });
      const end = (e) => {
        if (!drag) return;
        const { i, i0 } = drag; drag = null; h.classList.remove('is-drag');
        if (h.hasPointerCapture?.(e.pointerId)) h.releasePointerCapture(e.pointerId);
        if (i !== i0) commit(values[i]);
        place();
      };
      h.addEventListener('pointerup', end);
      h.addEventListener('pointercancel', end);
      h.addEventListener('lostpointercapture', end);   // Erfassung verloren (Tab-Wechsel, Systemgeste): Zug trotzdem abschließen
      h.addEventListener('click', (e) => e.stopPropagation());
      h.addEventListener('keydown', (e) => {
        const step = e.key === (dir > 0 ? 'ArrowRight' : 'ArrowLeft') ? 1 : e.key === (dir > 0 ? 'ArrowLeft' : 'ArrowRight') ? -1 : 0;
        if (!step) return;
        e.preventDefault(); e.stopPropagation();
        const i = Math.max(0, Math.min(values.length - 1, values.indexOf(cur()) + step));
        if (values[i] === cur()) return;
        preview(values[i]); commit(values[i]);
      });
    }
  }
  let handleTimer = 0;
  // Neu aufbauen bei geänderter Fenstergröße – nie während gezogen wird (sonst geht das Loslassen verloren; auf dem Handy
  // feuert resize schon beim Ein-/Ausblenden der Adressleiste)
  const dragging = () => [...main.querySelectorAll('.fluid-handle')].some((x) => x.shadowRoot?.querySelector('.is-drag'));
  const rebuild = () => { clearTimeout(handleTimer); handleTimer = setTimeout(() => (dragging() ? rebuild() : initHandles(main)), 200); };
  addEventListener('resize', rebuild, { passive: true });

  // ------------------------------------------------------------------ Start

  // Redaktionskommentare [# … #] markieren – CSS Custom Highlight API, ohne das DOM zu ändern (nichts landet im Text)
  function highlightNotes() {
    if (!window.CSS?.highlights || typeof Highlight === 'undefined') return;
    const ranges = [];
    const walker = document.createTreeWalker(main, NodeFilter.SHOW_TEXT);
    for (let n = walker.nextNode(); n; n = walker.nextNode()) {
      const t = n.nodeValue;
      if (!t.includes('[#')) continue;
      for (const m of t.matchAll(/\[#[\s\S]*?#\]/g)) {
        const r = new Range();
        r.setStart(n, m.index);
        r.setEnd(n, m.index + m[0].length);
        ranges.push(r);
      }
    }
    CSS.highlights.set('fluid-note', new Highlight(...ranges));
  }
  let noteTimer = 0;
  main.addEventListener('input', () => { clearTimeout(noteTimer); noteTimer = setTimeout(highlightNotes, 300); });
  document.addEventListener('fluid:rendered', () => setTimeout(highlightNotes, 50));
  highlightNotes();

  initEditables(main);
  initColumns(main);
  initHandles(main);
  emptyHint();
  render();
})();

/*
 * friendsofredaxo/fluid – Bearbeiten direkt auf der Website.
 *
 * Nur für angemeldete Backend-Benutzer geladen (Toolbar::inject). Oberfläche im Shadow DOM (#blocks-ui), damit das CSS
 * der Website sie nicht beeinflusst. Blöcke kennzeichnet der Server im Bearbeiten-Modus mit
 * [data-block][data-type][data-label] (Abschnitte bzw. Blöcke in Layout-Spalten mit data-col), Textfelder mit [data-edit="pfad"].
 */
(() => {
  'use strict';

  const cfgEl = document.getElementById('blocks-editor-config');
  if (!cfgEl) return;
  const cfg = JSON.parse(cfgEl.textContent);
  const unamp = (u) => String(u).replace(/&amp;/g, '&');
  const main = document.querySelector('main') || document.body;

  // ------------------------------------------------------------------ Server

  async function api(action, data = {}) {
    const fd = data instanceof FormData ? data : new FormData();
    if (!(data instanceof FormData)) {
      for (const [k, v] of Object.entries(data)) {
        if (v && typeof v === 'object') for (const [k2, v2] of Object.entries(v)) fd.append(`${k}[${k2}]`, v2);
        else fd.append(k, v);
      }
    }
    fd.append('_csrf_token', cfg.csrf);
    let res;
    try {
      res = await fetch(unamp(cfg.api).replace('__action__', action), { method: 'POST', body: fd, credentials: 'same-origin' });
    } catch {
      throw new Error('Keine Verbindung zum Server.');
    }
    const json = await res.json().catch(() => ({ ok: false, error: `Ungültige Antwort (${res.status}).` }));
    if (!json.ok) throw new Error(json.error || 'Unbekannter Fehler.');
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
      <a class="brand" href="${esc(unamp(cfg.backendUrl))}" title="Im Backend öffnen">R</a>
      <div class="title">
        <span class="kind">Seite</span>
        <strong class="name">${esc(cfg.article.name)}</strong>
        <button type="button" class="chip" data-chip aria-live="polite"></button>
      </div>
      <nav class="modes" aria-label="Modus">
        <a href="${esc(unamp(cfg.viewUrl))}" data-mode="view"${cfg.editing ? '' : ' aria-current="page"'}>Ansehen</a>
        <a href="${esc(unamp(cfg.editUrl))}" data-mode="edit"${cfg.editing ? ' aria-current="page"' : ''}>Bearbeiten</a>
      </nav>
      <div class="actions">
        ${cfg.editing ? `
        <button type="button" class="btn" data-save aria-disabled="true">Speichern</button>
        <button type="button" class="btn btn--primary" data-publish>Veröffentlichen</button>` : ''}
        ${cfg.design ? '<button type="button" class="btn" data-design-open aria-expanded="false">Design</button>' : ''}
        <div class="more">
          <button type="button" class="btn btn--icon" data-more aria-haspopup="menu" aria-expanded="false" title="Weitere">⋯</button>
          <div class="menu" role="menu" hidden>
            ${cfg.editing && cfg.state.workVersion ? '<button type="button" role="menuitem" data-discard>Entwurf verwerfen …</button>' : ''}
            <a role="menuitem" href="${esc(unamp(cfg.backendUrl))}">Im Backend öffnen</a>
            <a role="menuitem" href="${esc(unamp(cfg.viewUrl))}" target="_blank" rel="noopener">Live-Ansicht (neuer Tab)</a>
          </div>
        </div>
      </div>
    </header>`);
  const blockbar = h(`
    <div class="bb" hidden>
      <span class="bb__label" data-bb-label></span>
      <button type="button" data-bb-edit class="bb__main">Bearbeiten</button>
      <button type="button" data-bb-up title="Nach oben" aria-label="Nach oben">↑</button>
      <button type="button" data-bb-down title="Nach unten" aria-label="Nach unten">↓</button>
      <button type="button" data-bb-status title="Online/Offline"></button>
      <button type="button" data-bb-delete title="Löschen" aria-label="Löschen" class="bb__danger">✕</button>
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
  // Ebene für Menüs und Dialoge der Formatierungsleiste (Studio-Editor, assets/rte.js)
  const layer = h('<div class="layer"></div>');
  root.append(bar, blockbar, addbtn, panel, dpanel, picker, dialog, toast, layer);
  document.body.append(host);
  document.documentElement.classList.add('blocks-has-bar');
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
    else if (state.draft) [label, cls] = ['Entwurf – nicht veröffentlicht', 'draft'];
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

  // Menü ⋯
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

  // Modus wechseln mit ungespeicherten Änderungen
  $('.modes').addEventListener('click', async (e) => {
    const a = e.target.closest('a[data-mode]');
    if (!a || !cfg.editing || !dirty.size) return;
    e.preventDefault();
    const r = await ask({ title: 'Ungespeicherte Änderungen', body: 'Vor dem Verlassen speichern? „Verwerfen“ nimmt nur die ungespeicherten Änderungen zurück – ein gespeicherter Entwurf bleibt.', ok: 'Speichern & beenden', cancel: 'Weiter bearbeiten', alt: 'Verwerfen', danger: false });
    if (r === 'alt') {
      dirty.clear();   // kein Nachfragen des Browsers beim Verlassen
      location.href = a.href;
    } else if (r === true && (await saveAll())) {
      location.href = a.href;
    }
  });

  render();
  if (cfg.design) initDesign();
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

  // ------------------------------------------------------------------ Blockleiste und „+“

  function replace(sec, html) {
    const el = h(html);
    const ids = [sec, ...sec.querySelectorAll('[data-block]')].map((x) => x.dataset.block);
    sec.replaceWith(el);
    initEditables(el);
    initColumns(el);
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
    if (!(await ask({ title: `„${sec.dataset.label}“ löschen?`, body: 'Der Block wird aus dem Entwurf entfernt.', ok: 'Löschen', danger: true }))) return;
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
    cfg.modules.filter((m) => !col || m.nestable).forEach((m) => (groups[m.group] ||= []).push(m));
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
      const res = await api('add', { module: item.dataset.key, after: insertAfter?.dataset.block || 0, col: insertCol });
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
      hint.innerHTML = '<p>Diese Seite hat noch keine Blöcke.</p><button type="button">+ Ersten Block einfügen</button>';
      hint.querySelector('button').addEventListener('click', () => openPicker(null));
      main.prepend(hint);
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

  const ieBtn = h('<button type="button" class="iebtn" hidden>✎ Bild bearbeiten</button>');
  root.append(ieBtn);
  let ieImg = null;
  // Skript-Lader in form.js: CSS und Dialog gehören in diese Ebene
  document.addEventListener('blocks:image-editor', (e) => {
    e.preventDefault();
    root.append(h(`<link rel="stylesheet" href="${esc(unamp(e.detail.css))}">`));
    window.ImageEditor.setLayer(layer);
  });
  if (cfg.imageEditor) {
    main.addEventListener('mouseover', (e) => {
      const img = e.target.closest?.('img[data-ie-file]');
      if (!img || img === ieImg) return;
      ieImg = img;
      const r = img.getBoundingClientRect();
      ieBtn.style.top = `${Math.max(bar.offsetHeight + 8, r.top + 10)}px`;
      ieBtn.style.left = `${r.right - 160}px`;
      ieBtn.hidden = false;
    });
    window.addEventListener('scroll', () => { ieBtn.hidden = true; ieImg = null; }, { passive: true });
    ieBtn.addEventListener('click', async () => {
      const img = ieImg;
      const sec = img?.closest('[data-block]');
      if (!img || !sec) return;
      ieBtn.hidden = true;
      try {
        const IE = await window.BlocksPick.imageEditor(cfg.imageEditor);
        const item = await IE.open({ file: img.dataset.ieFile, api: cfg.imageEditor.api, tool: 'crop' });
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

  // ------------------------------------------------------------------ Start

  initEditables(main);
  initColumns(main);
  emptyHint();
  render();
})();

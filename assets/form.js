/*
 * friendsofredaxo/fluid – Verhalten der Block-Formulare (FieldForm): Felder je Variante, Wiederholgruppen.
 * Gemeinsam für das Backend (Slice bearbeiten) und den Editor auf der Website (window.BlocksForm.init).
 */
(() => {
  'use strict';

  const toEl = (html) => {
    const t = document.createElement('template');
    t.innerHTML = html.trim();
    return t.content.firstElementChild;
  };

  function variantFields(form) {
    const sel = form.querySelector('[data-blocks-variant]');
    const v = sel ? sel.value : '';
    form.querySelectorAll('[data-variants]').forEach((f) => {
      f.hidden = v !== '' && !f.dataset.variants.split(' ').includes(v);
    });
  }

  // Wiederholgruppen: hinzufügen, entfernen, verschieben (Reihenfolge im Formular = Reihenfolge der Einträge)
  function initRepeater(rep, changed) {
    if (rep.dataset.blocksReady) return;
    rep.dataset.blocksReady = '1';
    const list = rep.querySelector('[data-blocks-list]');
    const tpl = rep.querySelector('[data-blocks-template]');
    const max = Number(rep.dataset.max || 0);
    const addBtn = rep.querySelector('[data-blocks-add]');
    let n = list.children.length;
    const sync = () => {
      if (addBtn) addBtn.disabled = max > 0 && list.children.length >= max;
    };
    addBtn?.addEventListener('click', () => {
      // neue Einträge: freie Zahl als Schlüssel; der Server nummeriert neu
      const item = toEl(tpl.innerHTML.replaceAll('__i__', String(1000 + n++)));
      list.append(item);
      item.open = true;
      item.querySelectorAll('[data-blocks-repeater]').forEach((r) => initRepeater(r, changed));
      initPickers(item, changed);
      item.querySelector('input:not([type=hidden]),textarea,select')?.focus();
      sync();
      changed();
    });
    list.addEventListener('click', (e) => {
      const item = e.target.closest('[data-blocks-item]');
      if (!item || item.parentElement !== list) return;
      if (e.target.closest('[data-blocks-remove]')) item.remove();
      else if (e.target.closest('[data-blocks-up]')) item.previousElementSibling?.before(item);
      else if (e.target.closest('[data-blocks-down]')) item.nextElementSibling?.after(item);
      else return;
      e.preventDefault();
      sync();
      changed();
    });
    list.addEventListener('input', (e) => {
      const item = e.target.closest('[data-blocks-item]');
      const title = item?.querySelector(':scope > summary [data-blocks-item-title]');
      if (title && e.target.name?.endsWith(`[${title.dataset.field}]`) && e.target.value) title.textContent = e.target.value;
    });
    sync();
  }

  // ------------------------------------------------------------------ Medienpool und Linkmap (REDAXO-Popups)
  // Die Popups lösen am öffnenden Fenster „rex:selectMedia“ (Datei, Titel) bzw. „rex:selectLink“ (redaxo://ID, Name) aus.
  // Auf der Website gibt es kein jQuery: dafür genügt ein kleiner Ersatz mit on/trigger/Event.
  // Die Popups lösen das Event an IHREM Fenster aus (opener.jQuery(window).trigger …) und binden die Handler bei jedem Laden
  // neu aus opener.rex.popupEvents[Fenstername] (rex_retain_popup_event_handlers in standard.js) – dort hängt unser Handler.
  if (!window.jQuery) {
    const handlers = {};
    const jq = () => ({
      on(name, fn) { name.split(' ').forEach((n) => { (handlers[n] ||= []).includes(fn) || handlers[n].push(fn); }); return this; },
      trigger(ev, args = []) { (handlers[ev.type] || []).forEach((fn) => fn(ev, ...args)); return this; },
    });
    jq.Event = (type) => ({ type, prevented: false, preventDefault() { this.prevented = true; }, isDefaultPrevented() { return this.prevented; } });
    jq._data = () => ({});
    window.jQuery = jq;
  }
  window.rex = window.rex || {};

  const POPUP = 'blocks_pick';
  let pending = null;   // { resolve, win }
  const onSelect = (ev, value, name) => {
    if (!pending) return;
    ev.preventDefault();
    const p = pending;
    pending = null;
    try { p.win?.close(); } catch {}
    p.resolve({ value: String(value), name: name || '' });
  };

  /** Popup öffnen; Ergebnis: { value, name } oder null (Fenster ohne Auswahl geschlossen) */
  function pick(url) {
    if (pending) pending.resolve(null);
    // erst beim Öffnen setzen: das Backend legt window.rex ggf. später neu an
    window.rex = window.rex || {};
    window.rex.popupEvents = window.rex.popupEvents || {};
    window.rex.popupEvents[POPUP] = { 'rex:selectMedia': [onSelect], 'rex:selectLink': [onSelect] };
    return new Promise((resolve) => {
      const win = window.open(String(url).replace(/&amp;/g, '&'), POPUP, 'width=1100,height=760,resizable=yes,scrollbars=yes');
      pending = { resolve, win };
      const timer = setInterval(() => {
        if (!win || win.closed) {
          clearInterval(timer);
          if (pending?.win === win) { pending = null; resolve(null); }
        }
      }, 500);
    });
  }

  // ------------------------------------------------------------------ Bildeditor (Addon image_editor, optional)
  // Skript erst bei Bedarf laden. Der Editor auf der Website hängt Dialog und CSS in seine Shadow-DOM-Ebene
  // (Ereignis „blocks:image-editor“); ohne Abnehmer (Backend) kommt das CSS ins Dokument.
  let ieLoading = null;
  async function imageEditor(cfg) {
    if (!window.ImageEditor) {
      ieLoading ||= new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = String(cfg.js).replace(/&amp;/g, '&');
        s.onload = resolve;
        s.onerror = () => reject(new Error('Bildeditor konnte nicht geladen werden.'));
        document.head.append(s);
      });
      await ieLoading;
      const ev = new CustomEvent('blocks:image-editor', { detail: cfg, cancelable: true });
      if (document.dispatchEvent(ev)) {
        const l = document.createElement('link');
        l.rel = 'stylesheet';
        l.href = String(cfg.css).replace(/&amp;/g, '&');
        document.head.append(l);
      }
    }
    return window.ImageEditor;
  }

  function initPickers(scope, changed) {
    scope.querySelectorAll('[data-blocks-pick]').forEach((box) => {
      if (box.dataset.blocksReady) return;
      box.dataset.blocksReady = '1';
      const input = box.querySelector('input');
      const thumb = box.querySelector('[data-blocks-pick-thumb]');
      const label = box.querySelector('[data-blocks-pick-name]');
      const set = (value, name = '') => {
        input.value = value;
        if (thumb) {
          const img = /\.(jpe?g|png|gif|webp|avif|svg)$/i.test(value);
          thumb.hidden = !value || !img;
          thumb.innerHTML = value && img ? `<img src="${box.dataset.preview.replace(/&amp;/g, '&').replace('__FILE__', encodeURIComponent(value))}" alt="">` : '';
        }
        if (label) label.textContent = /^redaxo:\/\//.test(value) ? name : '';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        changed();
      };
      box.querySelector('[data-blocks-pick-open]')?.addEventListener('click', async () => {
        const res = await pick(box.dataset.url);
        if (res) set(res.value, res.name);
      });
      box.querySelector('[data-blocks-pick-clear]')?.addEventListener('click', () => set(''));
      box.querySelector('[data-blocks-pick-edit]')?.addEventListener('click', async () => {
        if (!input.value) return;
        try {
          const IE = await imageEditor({ api: box.dataset.ieApi, js: box.dataset.ieJs, css: box.dataset.ieCss });
          const item = await IE.open({ file: input.value, api: box.dataset.ieApi, tool: 'crop' });
          if (item && thumb) {
            thumb.hidden = false;
            thumb.innerHTML = `<img src="${item.thumb.replace(/&amp;/g, '&')}" alt="">`;
          }
          if (item) { input.dispatchEvent(new Event('change', { bubbles: true })); changed(); }
        } catch (err) {
          input.setCustomValidity(err.message);
          input.reportValidity();
          setTimeout(() => input.setCustomValidity(''), 4000);
        }
      });
      input.addEventListener('input', () => { if (label && !/^redaxo:\/\//.test(input.value)) label.textContent = ''; });
    });
  }

  function init(scope, changed = () => {}) {
    initPickers(scope, changed);
    scope.querySelectorAll('[data-blocks-form]').forEach((form) => {
      variantFields(form);
      if (!form.dataset.blocksReady) {
        form.dataset.blocksReady = '1';
        form.addEventListener('change', (e) => e.target.matches('[data-blocks-variant]') && variantFields(form));
      }
      form.querySelectorAll('[data-blocks-repeater]').forEach((r) => initRepeater(r, changed));
    });
  }

  window.BlocksForm = { init };
  window.BlocksPick = { pick, imageEditor };

  // Backend: REDAXO lädt Seiten per PJAX nach und meldet das mit „rex:ready“
  if (window.jQuery) {
    window.jQuery(document).on('rex:ready', (e, container) => init(container?.[0] || document));
  }
  if (document.readyState !== 'loading') init(document);
  else document.addEventListener('DOMContentLoaded', () => init(document));
})();

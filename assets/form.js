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
      // Backend: aufklappbare Einträge (Collapse des Cores) brauchen je Eintrag eigene IDs (auch verschachtelte)
      item.querySelectorAll('.collapse[id]').forEach((el) => {
        const id = `${el.id}-${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`;
        item.querySelectorAll(`[data-target="#${CSS.escape(el.id)}"]`).forEach((t) => { t.dataset.target = '#' + id; t.setAttribute('aria-controls', id); });
        el.id = id;
      });
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
    list.addEventListener('change', (e) => { if (e.target.matches('select')) e.target.dispatchEvent(new Event('input', { bubbles: true })); });
    list.addEventListener('input', (e) => {
      const item = e.target.closest('[data-blocks-item]');
      // Titel im Kopf: <summary> (Website) bzw. Kopf des Eintrags (Backend) – nicht die verschachtelter Einträge
      const title = item?.querySelector(':scope > summary [data-blocks-item-title], :scope > .fluid-rep-item__head [data-blocks-item-title]');
      // Auswahlfeld als Titel: Text der Option („Montag“ statt „1“)
      if (title && e.target.name?.endsWith(`[${title.dataset.field}]`) && e.target.value) title.textContent = e.target.selectedOptions?.[0]?.text || e.target.value;
    });
    sync();
  }

  // ------------------------------------------------------------------ Medien- und Seitenauswahl (ohne Popup-Fenster)
  // Backend: Medienpool und Linkmap des Cores als Overlay (rex.pick). Website: FrontendPicker (assets/picker, für jedes
  // AddOn nutzbar) mit derselben Antwort [{ value, label }].

  /**
   * Auswahl öffnen; Ergebnis: { value, name } oder null (ohne Auswahl geschlossen)
   * @param {'media'|'link'} kind
   * @param {{types?: string[], category?: number, language?: number, value?: string}} opts
   */
  async function pick(kind, opts = {}) {
    // Gastzugang ohne Backend (z. B. AddOn fluid_demo setzt window.fluidPickerOff): Hinweis statt Auswahl
    if (window.fluidPickerOff) {
      document.dispatchEvent(new CustomEvent('fluid:notice', { detail: String(window.fluidPickerOff) }));
      return null;
    }
    const open = typeof window.rex?.pick === 'function' ? window.rex.pick : window.FrontendPicker?.pick;
    if (!open) return null;
    const [item] = kind === 'media'
      ? await open('media', { types: opts.types?.length ? opts.types : undefined })
      : await open('article', {
        category: opts.category || undefined,
        language: opts.language || undefined,
        value: /^redaxo:\/\/\d+/.test(opts.value || '') ? opts.value : undefined,
      });
    if (!item) return null;
    return kind === 'media' ? { value: item.value, name: item.label } : { value: 'redaxo://' + item.value, name: item.label };
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
      // Bearbeiten/Anpassen/Entfernen nur mit gewählter Datei
      // hidden für die Website; im Backend setzt .rex-button display, dort blendet die Hilfsklasse des Cores aus
      const isVideo = () => /\.(mp4|m4v|mov|webm|mkv|ogv|avi)$/i.test(input.value);
      const tools = () => box.querySelectorAll('[data-blocks-pick-edit],[data-blocks-pick-adjust],[data-blocks-pick-clear],[data-blocks-pick-video]').forEach((b) => {
        const off = !input.value || (b.hasAttribute('data-blocks-pick-video') && !isVideo());
        b.hidden = off;
        b.classList.toggle('rex-u-hidden', off);
      });
      tools();
      input.addEventListener('input', tools);
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
        const res = await pick(box.dataset.blocksPick === 'media' ? 'media' : 'link', {
          types: (box.dataset.types || '').split(',').filter(Boolean),
          category: Number(box.dataset.category) || 0,
          language: Number(box.dataset.language) || 0,
          value: input.value,
        });
        if (res) set(res.value, res.name);
      });
      box.querySelector('[data-blocks-pick-clear]')?.addEventListener('click', () => set(''));
      // AddOn video_editor: Video im Medienpool öffnen (neuer Tab, die Eingaben hier bleiben erhalten)
      box.querySelector('[data-blocks-pick-video]')?.addEventListener('click', () => {
        if (!isVideo() || !box.dataset.veEdit) return;
        window.open(box.dataset.veEdit.replace(/&amp;/g, '&').replace('__FILE__', encodeURIComponent(input.value)), '_blank', 'noopener');
      });
      box.querySelectorAll('[data-blocks-pick-edit],[data-blocks-pick-adjust]').forEach((btn) => btn.addEventListener('click', async () => {
        if (!input.value) return;
        try {
          const IE = await imageEditor({ api: box.dataset.ieApi, js: box.dataset.ieJs, css: box.dataset.ieCss });
          const opts = { file: input.value, api: box.dataset.ieApi };
          const item = btn.hasAttribute('data-blocks-pick-adjust') ? await IE.adjust(opts) : await IE.open({ ...opts, tool: 'crop' });
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
      }));
      input.addEventListener('input', () => { if (label && !/^redaxo:\/\//.test(input.value)) label.textContent = ''; });
    });
  }

  // YForm: Feldauswahl auf die gewählte Tabelle einschränken (Optionsgruppen anderer Tabellen ausblenden)
  function yformFields(form) {
    const table = form.querySelector('[data-yform-table]')?.value || '';
    form.querySelectorAll('[data-yform-field]').forEach((sel) => {
      sel.querySelectorAll('optgroup').forEach((g) => {
        const on = g.dataset.table === table;
        g.hidden = !on;
        g.disabled = !on;
      });
      if (sel.value && !sel.value.startsWith(table + '.')) sel.value = '';
    });
  }

  function init(scope, changed = () => {}) {
    initPickers(scope, changed);
    scope.querySelectorAll('[data-blocks-form]').forEach((form) => {
      variantFields(form);
      yformFields(form);
      if (!form.dataset.blocksReady) {
        form.dataset.blocksReady = '1';
        form.addEventListener('change', (e) => {
          if (e.target.matches('[data-blocks-variant]')) variantFields(form);
          if (e.target.matches('[data-yform-table]')) yformFields(form);
        });
      }
      form.querySelectorAll('[data-blocks-repeater]').forEach((r) => initRepeater(r, changed));
    });
  }

  window.BlocksForm = { init };
  window.BlocksPick = { pick, imageEditor };

  // Backend: neu eingefügtes Markup (Overlays, Wiederholgruppen …) meldet der Core mit „rex:ready“ am neuen Element
  document.addEventListener('rex:ready', (e) => init(e.target instanceof Element ? e.target : document));
  if (document.readyState !== 'loading') init(document);
  else document.addEventListener('DOMContentLoaded', () => init(document));
})();

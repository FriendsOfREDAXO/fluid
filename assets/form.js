/*
 * friendsofredaxo/blocks – Verhalten der Block-Formulare (FieldForm): Felder je Variante, Wiederholgruppen.
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

  function init(scope, changed = () => {}) {
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

  // Backend: REDAXO lädt Seiten per PJAX nach und meldet das mit „rex:ready“
  if (window.jQuery) {
    window.jQuery(document).on('rex:ready', (e, container) => init(container?.[0] || document));
  }
  if (document.readyState !== 'loading') init(document);
  else document.addEventListener('DOMContentLoaded', () => init(document));
})();

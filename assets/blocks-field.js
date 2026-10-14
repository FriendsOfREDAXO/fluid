/*
 * YForm-Feld „Fluid-Blöcke“ (Data\FluidBlocks) in der Maske: Liste der Blöcke mit Hinzufügen, Bearbeiten (Formular des
 * Blocks wie auf der Website, über rex-api-call=fluid_blocks), Verschieben und Entfernen. Der Wert steht als JSON im
 * versteckten Feld und wird mit dem YForm-Formular gespeichert.
 */
(() => {
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const post = async (url, fd) => {
    const res = await fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' });
    const json = await res.json().catch(() => ({ ok: false, error: 'Ungültige Antwort (' + res.status + ').' }));
    if (!json.ok) throw new Error(json.error || 'Fehler');
    return json;
  };
  const init = (root) => {
    if (root.dataset.fbfReady) return;
    root.dataset.fbfReady = '1';
    const cfg = JSON.parse(root.dataset.fluidBlocks);
    const input = root.querySelector('input[type=hidden]');
    const list = root.querySelector('[data-fbf-list]');
    const type = root.querySelector('[data-fbf-type]');
    let blocks = JSON.parse(input.value || '[]');
    let labels = cfg.labels || [];
    let open = null;   // { index, li } – geöffneter Editor

    // Auswahl der Blocktypen nach Gruppen
    const groups = {};
    Object.entries(cfg.catalog).forEach(([k, v]) => { (groups[v.group || 'Blöcke'] ??= []).push([k, v.label]); });
    type.innerHTML = Object.entries(groups).map(([g, items]) => `<optgroup label="${esc(g)}">${items.map(([k, l]) => `<option value="${esc(k)}">${esc(l)}</option>`).join('')}</optgroup>`).join('');

    const sync = () => { input.value = JSON.stringify(blocks); input.dispatchEvent(new Event('change', { bubbles: true })); };
    const draw = () => {
      list.innerHTML = blocks.map((b, i) => `
        <li class="fbf-item" data-i="${i}">
          <div class="fbf-row">
            <span class="fbf-label">${esc(labels[i] || cfg.catalog[b.module]?.label || b.module)}</span>
            <span class="fbf-tools">
              <button type="button" class="btn btn-xs btn-default" data-act="up" ${i === 0 ? 'disabled' : ''} title="Nach oben" aria-label="Nach oben">↑</button>
              <button type="button" class="btn btn-xs btn-default" data-act="down" ${i === blocks.length - 1 ? 'disabled' : ''} title="Nach unten" aria-label="Nach unten">↓</button>
              <button type="button" class="btn btn-xs btn-primary" data-act="edit">Bearbeiten</button>
              <button type="button" class="btn btn-xs btn-delete" data-act="del" title="Entfernen" aria-label="Entfernen">✕</button>
            </span>
          </div>
        </li>`).join('') || '<li class="fbf-empty">Noch keine Blöcke.</li>';
    };
    const closeEditor = () => { open?.box.remove(); open = null; };
    const edit = async (i, isNew = false) => {
      if (open && !(await confirmClose())) return;
      const b = blocks[i];
      const fd = new FormData();
      fd.append('action', 'form');
      fd.append('module', b.module);
      fd.append('data', JSON.stringify(b.data || {}));
      fd.append('tunes', JSON.stringify(b.tunes || {}));
      try {
        const res = await post(cfg.api, fd);
        const li = list.querySelector(`[data-i="${i}"]`);
        const box = document.createElement('div');
        box.className = 'fbf-editor';
        box.innerHTML = `<div class="fbf-editor__head">${esc(res.label)}</div><div class="fbf-editor__body">${res.html}</div>
          <div class="fbf-editor__foot"><button type="button" class="btn btn-default" data-fbf-cancel>Abbrechen</button> <button type="button" class="btn btn-save" data-fbf-apply>Übernehmen</button></div>`;
        li.append(box);
        open = { index: i, box, isNew };
        window.BlocksForm?.init(box, () => {});
        window.BlocksRich?.initFields(box);
        window.jQuery?.(box).trigger('rex:ready', [window.jQuery(box)]);
        box.querySelector('input, select, textarea, [contenteditable]')?.focus();
      } catch (err) {
        alert(err.message);
        if (isNew) { blocks.splice(i, 1); labels.splice(i, 1); draw(); sync(); }
      }
    };
    const apply = async () => {
      if (!open) return;
      const fd = new FormData();
      fd.append('action', 'parse');
      fd.append('module', blocks[open.index].module);
      open.box.querySelectorAll('[name^="fb["]').forEach((el) => {
        if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) return;
        if (el.disabled) return;
        fd.append(el.name, el.value);
      });
      try {
        const res = await post(cfg.api, fd);
        blocks[open.index] = res.item;
        labels[open.index] = res.label;
        closeEditor();
        draw();
        sync();
      } catch (err) { alert(err.message); }
    };
    const confirmClose = async () => {
      if (!open) return true;
      if (!confirm('Der geöffnete Block ist noch nicht übernommen. Änderungen verwerfen?')) return false;
      if (open.isNew) { blocks.splice(open.index, 1); labels.splice(open.index, 1); }
      closeEditor();
      draw();
      sync();
      return true;
    };

    root.addEventListener('click', async (e) => {
      const btn = e.target.closest('[data-act], [data-fbf-add], [data-fbf-apply], [data-fbf-cancel]');
      if (!btn || !root.contains(btn)) return;
      e.preventDefault();
      if (btn.matches('[data-fbf-apply]')) return apply();
      if (btn.matches('[data-fbf-cancel]')) {
        if (open?.isNew) { blocks.splice(open.index, 1); labels.splice(open.index, 1); }
        closeEditor(); draw(); sync();
        return;
      }
      if (btn.matches('[data-fbf-add]')) {
        if (open && !(await confirmClose())) return;
        blocks.push({ module: type.value, data: {}, tunes: {} });
        labels.push(cfg.catalog[type.value]?.label || type.value);
        draw();
        return edit(blocks.length - 1, true);
      }
      const i = +btn.closest('[data-i]').dataset.i;
      const act = btn.dataset.act;
      if (act === 'edit') return edit(i);
      if (open && !(await confirmClose())) return;
      if (act === 'del') {
        if (!confirm('Block „' + (labels[i] || '') + '“ entfernen?')) return;
        blocks.splice(i, 1); labels.splice(i, 1);
      } else {
        const j = act === 'up' ? i - 1 : i + 1;
        [blocks[i], blocks[j]] = [blocks[j], blocks[i]];
        [labels[i], labels[j]] = [labels[j], labels[i]];
      }
      draw();
      sync();
    });
    // Speichern der YForm-Maske mit offenem Block: erst übernehmen
    root.closest('form')?.addEventListener('submit', (e) => {
      if (!open) return;
      e.preventDefault();
      alert('Bitte den geöffneten Block erst übernehmen oder abbrechen.');
      open.box.scrollIntoView({ block: 'center' });
    });
    // Felder des geöffneten Blocks gehören nicht zum YForm-Formular
    draw();
  };
  const scan = (scope = document) => scope.querySelectorAll('[data-fluid-blocks]').forEach(init);
  if (document.readyState !== 'loading') scan(); else document.addEventListener('DOMContentLoaded', () => scan());
  window.jQuery?.(document).on('rex:ready', (e, c) => scan(c?.[0] || document));
})();

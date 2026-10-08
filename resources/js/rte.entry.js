/*
 * friendsofredaxo/blocks – Formatierungsleiste (aus KLXM Studio) für Backend und Website: window.BlocksRich.
 *   initFields(scope)  textarea[data-blocks-rich] → Feld mit Leiste (.rte > .rte-bar + .rte-area), Wert im Textfeld
 *   inlineBar()        schwebende Leiste für [data-edit-mode="rich"] direkt auf der Website
 *   setLayer(el)       Ebene für Menüs/Dialoge (Shadow DOM des Editors)
 */
import { Rich } from './_rte.js';
import { setLayer, layerBox } from './_shadow.js';

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

function initFields(scope = document) {
  scope.querySelectorAll('textarea[data-blocks-rich]').forEach((ta) => {
    if (ta.dataset.blocksRte) return;
    ta.dataset.blocksRte = '1';
    const wrap = document.createElement('div');
    wrap.className = 'rte';
    wrap.innerHTML = '<div class="rte-bar" role="toolbar" aria-label="Formatierung"></div><div class="rte-area" contenteditable="true" role="textbox" aria-multiline="true"></div>';
    const bar = wrap.querySelector('.rte-bar');
    const area = wrap.querySelector('.rte-area');
    if (ta.id) area.setAttribute('aria-labelledby', ta.id + '-label');
    const v = ta.value.trim();
    area.innerHTML = v === '' ? '' : /<(p|h[2-4]|ul|ol|blockquote)\b/i.test(v) ? v : '<p>' + esc(v).replace(/\n{2,}/g, '</p><p>').replace(/\n/g, '<br>') + '</p>';
    ta.hidden = true;
    ta.after(wrap);
    bar.innerHTML = Rich.barHtml('rich');
    const sync = () => {
      ta.value = area.innerHTML.trim() === '<br>' ? '' : area.innerHTML;
      ta.dispatchEvent(new Event('input', { bubbles: true }));
    };
    area.addEventListener('input', sync);
    Rich.bindKeys(area, 'rich');
    Rich.mount(bar, () => area);
    Rich.state(area, bar);
    area.addEventListener('keyup', () => Rich.state(area, bar));
    area.addEventListener('mouseup', () => Rich.state(area, bar));
  });
}

/** Schwebende Leiste für Text mit Formatierung direkt auf der Website (Leiste in der Ebene des Editors) */
function inlineBar({ minTop = () => 0 } = {}) {
  const el = document.createElement('div');
  el.className = 'cms-inline-bar rte-bar';
  el.setAttribute('role', 'toolbar');
  el.setAttribute('aria-label', 'Formatierung');
  el.hidden = true;
  layerBox().append(el);
  let target = null;
  let mode = '';
  let timer = 0;
  const place = () => {
    if (!target || el.hidden) return;
    const r = target.getBoundingClientRect();
    const h = el.offsetHeight;
    const w = el.offsetWidth;
    let top = r.top - h - 10;
    if (top < minTop() + 10) top = Math.min(r.bottom + 10, innerHeight - h - 10);
    el.style.top = Math.max(minTop() + 10, top) + 'px';
    el.style.left = Math.max(8, Math.min(r.left, innerWidth - w - 8)) + 'px';
  };
  Rich.mount(el, () => target, () => place());
  addEventListener('scroll', place, { passive: true });
  addEventListener('resize', place);
  const api = {
    show(n, m = 'rich') {
      clearTimeout(timer);
      if (mode !== m) { el.innerHTML = Rich.barHtml(m); mode = m; }
      if (!n._blocksKeys) { Rich.bindKeys(n, m); n._blocksKeys = true; }
      target = n;
      el.hidden = false;
      place();
      Rich.state(n, el);
    },
    update() { if (target && !el.hidden) Rich.state(target, el); },
    hideSoon() {
      clearTimeout(timer);
      timer = setTimeout(() => {
        const deep = (r) => (r.activeElement?.shadowRoot ? deep(r.activeElement.shadowRoot) : r.activeElement);
        if (document.querySelector('dialog[open]') || layerBox().querySelector?.('dialog[open]')) return;
        if (target && target.contains(document.activeElement)) return;
        if (el.contains(deep(document))) return;
        api.hide();
      }, 200);
    },
    hide() { el.hidden = true; target = null; },
    el,
  };
  el.addEventListener('focusout', () => api.hideSoon());
  return api;
}

window.BlocksRich = { Rich, initFields, inlineBar, setLayer };

if (document.readyState !== 'loading') initFields(document);
else document.addEventListener('DOMContentLoaded', () => initFields(document));
if (window.jQuery) window.jQuery(document).on('rex:ready', (e, c) => initFields(c?.[0] || document));

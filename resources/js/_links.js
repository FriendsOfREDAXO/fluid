/*
 * Linkauswahl für REDAXO (Ersatz für Studio _links.js): Artikel der Struktur, Adresse, E-Mail, Telefon, Anker.
 * Artikel werden als href (aktuelle Adresse) + data-link="redaxo://ID" gespeichert – die Ausgabe aktualisiert die Adresse,
 * wenn der Artikel umzieht. Liste der Artikel: <script type="application/json" id="blocks-links"> (Links::json()).
 */
import { t } from './_i18n.js';
import { layerBox } from './_shadow.js';

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

export function normalizeTel(v) {
  const raw = String(v || '').trim().replace(/^tel:/i, '');
  let n = raw.replace(/\(0\)/g, '').replace(/[^\d+]/g, '');
  if (n.startsWith('00')) n = '+' + n.slice(2);
  else if (n.startsWith('0')) n = '+49' + n.slice(1);
  else if (n && !n.startsWith('+')) n = '+49' + n;
  if (n.replace(/\D/g, '').length < 5) return { error: t('Bitte eine gültige Telefonnummer eingeben.') };
  return { href: 'tel:' + n, label: raw };
}

let articles = null;
function list() {
  if (articles === null) {
    try { articles = JSON.parse(document.getElementById('blocks-links')?.textContent || '[]'); } catch { articles = []; }
  }
  return articles;
}

/** Wert aus dem Eingabefeld → Link (Adresse, E-Mail, Telefon, Anker, redaxo://ID) */
function resolve(v) {
  v = v.trim();
  if (!v) return null;
  const art = /^(?:redaxo:\/\/)?(\d+)(#[\w-]+)?$/.exec(v);
  if (art) {
    const a = list().find((x) => String(x.id) === art[1]);
    return { href: (a ? a.url : '#') + (art[2] || ''), ref: 'redaxo://' + art[1] + (art[2] || ''), label: a ? a.name : v };
  }
  if (/^[\w.+-]+@[\w-]+(\.[\w-]+)+$/.test(v)) return { href: 'mailto:' + v, label: v };
  if (/^(mailto:|tel:|https?:\/\/|#|\/)/i.test(v)) return { href: v, label: v.replace(/^(mailto:|tel:)/i, '') };
  if (/^[+0(][\d\s/().-]{5,}$/.test(v)) return normalizeTel(v).href ? normalizeTel(v) : null;
  if (/^[\w-]+(\.[\w-]+)+([/?#].*)?$/.test(v)) return { href: 'https://' + v, label: v };
  return null;
}

let dlg = null;
function build() {
  if (dlg) return dlg;
  dlg = document.createElement('dialog');
  dlg.className = 'adm-dialog lp';
  dlg.innerHTML = `
    <form method="dialog" class="lp__form">
      <div class="adm-dialog__head"><h2 data-lp-title>${esc(t('Link einfügen'))}</h2></div>
      <div class="f"><label for="blocks-lp-q">${esc(t('Seite suchen oder Adresse eingeben'))}</label>
        <input type="text" id="blocks-lp-q" autocomplete="off" placeholder="${esc(t('Seitenname, https://…, E-Mail, Telefon, #anker'))}"></div>
      <div class="adm-row lp__map"><button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-lp-linkmap>${esc(t('Linkmap öffnen …'))}</button></div>
      <div class="lp__list" role="listbox" data-lp-list></div>
      <label class="f-check"><input type="checkbox" data-lp-tab> ${esc(t('In neuem Tab öffnen'))}</label>
      <p class="f-help" data-lp-error hidden></p>
      <div class="adm-row">
        <button type="button" class="adm-btn adm-btn--ghost" data-lp-remove hidden>${esc(t('Link entfernen'))}</button>
        <button type="button" class="adm-btn adm-btn--ghost" data-lp-cancel>${esc(t('Abbrechen'))}</button>
        <button type="submit" class="adm-btn adm-btn--primary">${esc(t('Übernehmen'))}</button>
      </div>
    </form>`;
  return dlg;
}

export function openLinkPicker(opts = {}) {
  const d = build();
  layerBox().append(d);
  const q = d.querySelector('#blocks-lp-q');
  const box = d.querySelector('[data-lp-list]');
  const err = d.querySelector('[data-lp-error]');
  const cur = opts.current || null;
  d.querySelector('[data-lp-title]').textContent = cur ? t('Link bearbeiten') : t('Link einfügen');
  d.querySelector('[data-lp-remove]').hidden = !cur;
  d.querySelector('[data-lp-tab]').checked = !!cur?.newTab;
  q.value = cur ? (cur.ref || cur.href || '') : '';
  err.hidden = true;
  let picked = null;
  const render = () => {
    const s = q.value.trim().toLowerCase();
    const items = list().filter((a) => !s || a.name.toLowerCase().includes(s) || a.path.toLowerCase().includes(s)).slice(0, 40);
    box.innerHTML = items.map((a) => `<button type="button" role="option" class="lp__item" data-id="${a.id}"><span class="lp__txt"><span class="lp__label">${esc(a.name)}</span><span class="lp__meta">${esc(a.path)}</span></span>${a.online ? '' : `<span class="lp__badge lp__badge--draft">${esc(t('offline'))}</span>`}</button>`).join('');
  };
  render();
  return new Promise((resolve) => {
    const done = (v) => {
      d.close();
      d.remove();
      q.removeEventListener('input', onInput);
      d.removeEventListener('click', onClick);
      d.removeEventListener('submit', onSubmit);
      d.removeEventListener('cancel', onCancel);
      resolve(v);
    };
    const tab = () => d.querySelector('[data-lp-tab]').checked;
    const onInput = () => { picked = null; render(); };
    const onClick = (e) => {
      const it = e.target.closest('.lp__item');
      if (it) {
        const a = list().find((x) => String(x.id) === it.dataset.id);
        picked = a;
        done({ href: a.url, ref: 'redaxo://' + a.id, label: a.name, newTab: tab() });
      } else if (e.target.closest('[data-lp-cancel]')) done(null);
      else if (e.target.closest('[data-lp-remove]')) done({ remove: true });
      else if (e.target.closest('[data-lp-linkmap]')) {
        // REDAXO-Linkmap im Popup (assets/form.js: BlocksPick) – Ergebnis redaxo://ID
        const url = document.getElementById('blocks-links')?.dataset.linkmap;
        if (!url || !window.BlocksPick) return;
        window.BlocksPick.pick(url).then((res) => {
          if (!res) return;
          const id = String(res.value).replace('redaxo://', '');
          const a = list().find((x) => String(x.id) === id);
          done({ href: a ? a.url : '#', ref: 'redaxo://' + id, label: a ? a.name : res.name, newTab: tab() });
        });
      }
    };
    const onSubmit = (e) => {
      e.preventDefault();
      const r = resolve(q.value);
      if (!r || r.error) { err.textContent = r?.error || t('Bitte eine Seite wählen oder eine Adresse eingeben.'); err.hidden = false; return; }
      done({ ...r, newTab: tab() });
    };
    const onCancel = (e) => { e.preventDefault(); done(null); };
    q.addEventListener('input', onInput);
    d.addEventListener('click', onClick);
    d.addEventListener('submit', onSubmit);
    d.addEventListener('cancel', onCancel);
    d.showModal();
    q.focus();
    q.select();
  });
}

/*
 * FrontendPicker – Medien- und Seitenauswahl für die Website, das Gegenstück zu rex.pick() im Backend.
 * Für jedes AddOn nutzbar (PHP: FriendsOfRedaxo\Fluid\Picker\FrontendPicker::head()), ohne Abhängigkeit zu fluid.
 *
 *   const [media] = await FrontendPicker.pick('media', { types: ['jpg', 'png'] });   // [{ value: 'datei.jpg', label: 'Titel' }]
 *   const [page] = await FrontendPicker.pick('article', { category: 5, value: 12 });  // [{ value: '12', label: 'Name [12]' }]
 *
 * Optionen: types (Endungen), category (Startkategorie), language, value (aktuelle Auswahl), onPick(items).
 * Die Dialoge liegen in einem eigenen Shadow DOM, damit das CSS der Website sie nicht verändert.
 */
(() => {
  if (window.FrontendPicker) return;

  const unamp = (s) => String(s).replace(/&amp;/g, '&');
  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const store = {
    get(key) { try { return sessionStorage.getItem(key); } catch { return null; } },
    set(key, value) { try { sessionStorage.setItem(key, String(value)); } catch {} },
  };

  let config = null;
  function cfg() {
    if (!config) {
      try { config = JSON.parse(document.getElementById('frontend-picker-config')?.textContent || 'null'); } catch { config = null; }
    }
    if (!config) throw new Error('FrontendPicker: Konfiguration fehlt (FrontendPicker::head()).');
    return config;
  }

  async function api(action, data = {}) {
    const fd = data instanceof FormData ? data : new FormData();
    if (!(data instanceof FormData)) for (const [k, v] of Object.entries(data)) fd.append(k, v);
    fd.append('action', action);
    let res;
    try {
      res = await fetch(unamp(cfg().api), { method: 'POST', body: fd, credentials: 'same-origin' });
    } catch {
      throw new Error('Keine Verbindung zum Server.');
    }
    const json = await res.json().catch(() => ({ ok: false, error: `Ungültige Antwort (${res.status}).` }));
    // Meldungen des Medienpools enthalten HTML (<code>, <br>)
    if (!json.ok) throw new Error(new DOMParser().parseFromString(json.error || 'Unbekannter Fehler.', 'text/html').body.textContent);
    return json;
  }

  // eigene Ebene: Shadow DOM mit eigenem CSS, über allem
  let layer = null;
  function root() {
    if (layer) return layer;
    const host = document.createElement('div');
    host.setAttribute('data-frontend-picker', '');
    document.body.append(host);
    layer = host.attachShadow({ mode: 'open' });
    const css = document.createElement('link');
    css.rel = 'stylesheet';
    css.href = unamp(cfg().css);
    layer.append(css);
    return layer;
  }

  function dialog(title, body) {
    const d = document.createElement('dialog');
    d.className = 'fp';
    d.setAttribute('aria-label', title);
    d.innerHTML = `<div class="fp__head"><h2>${esc(title)}</h2>`
      + `<button type="button" class="fp__btn" data-close aria-label="Schließen" title="Schließen">✕</button></div>${body}`;
    root().append(d);
    d.showModal();
    return d;
  }

  /** Ergebnis eines Dialogs: Auswahl als Liste wie rex.pick(), [] beim Schließen; der Dialog verschwindet danach */
  function settle(d, onPick, wire) {
    return new Promise((resolve) => {
      let done = false;
      const finish = (items) => {
        if (done) return;
        done = true;
        d.close();
        d.remove();
        if (items.length) onPick?.(items);
        resolve(items);
      };
      d.addEventListener('close', () => finish([]));
      d.querySelector('[data-close]').addEventListener('click', () => finish([]));
      wire((item) => finish([item]));
    });
  }

  const trailHtml = (rootLabel, chain, at) => `<button type="button" data-cat="0"${at ? '' : ' aria-current="location"'}>${esc(rootLabel)}</button>`
    + chain.map((c) => ` <span aria-hidden="true">›</span> <button type="button" data-cat="${c.id}"${c.id === at ? ' aria-current="location"' : ''}>${esc(c.name)}</button>`).join('');
  const catButton = (c) => `<li><button type="button" class="fp__cat" data-cat="${c.id}"><span class="fp__folder" aria-hidden="true">▸</span> ${esc(c.name)}</button></li>`;

  /** Medien wie der Medienpool: Pfad, links Kategorien (öffnen), rechts Medien (wählen); Suche, Hochladen */
  function media({ types = [], category, onPick } = {}) {
    const d = dialog('Medien auswählen', `
      <div class="fp__bar">
        <input class="fp__input" type="search" data-q placeholder="Medien suchen …" aria-label="Medien suchen">
        <label class="fp__btn fp__upload" hidden data-up-label>Hochladen<input type="file" data-up${types.length ? ` accept="${esc(types.map((t) => '.' + t).join(','))}"` : ''}></label>
      </div>
      <div class="fp__body">
        <nav class="fp__trail" aria-label="Pfad" data-trail></nav>
        ${types.length ? `<p class="fp__note">Nur Dateien vom Typ ${esc(types.join(', '))}</p>` : ''}
        <p class="fp__msg" role="status" aria-live="polite" data-msg></p>
        <div class="fp__cols fp__cols--media">
          <section data-cats-col><h3>Kategorien</h3><ul class="fp__list" data-cats></ul></section>
          <section><h3 data-media-h>Medien</h3><ul class="fp__grid" data-list></ul>
            <p class="fp__more"><button type="button" class="fp__btn" data-more hidden>Mehr laden</button></p></section>
        </div>
      </div>`);
    const $ = (sel) => d.querySelector(sel);
    const state = { category: Number(category ?? store.get('frontend-picker-media-cat')) || 0, q: '', page: 0 };
    let cats = [];
    const msg = (text) => { $('[data-msg]').textContent = text; };

    const card = (it) => `<li><button type="button" class="fp__item" data-file="${esc(it.file)}" data-title="${esc(it.title || it.file)}" title="${esc(it.title || it.file)}">`
      + (it.image && it.thumb ? `<img src="${esc(unamp(it.thumb))}" alt="" loading="lazy">` : `<span class="fp__ext">${esc(it.ext)}</span>`)
      + `<span class="fp__name">${esc(it.title || it.file)}</span><small class="fp__file">${esc(it.file)} · ${esc(it.size)}</small></button></li>`;

    const nav = (res) => {
      const byId = new Map(cats.map((c) => [c.id, c]));
      const chain = [];
      for (let c = byId.get(state.category); c; c = byId.get(c.parent)) chain.unshift(c);
      $('[data-trail]').innerHTML = trailHtml('Medienpool', chain, state.category);
      const sub = cats.filter((c) => c.parent === state.category);
      $('[data-cats]').innerHTML = sub.length ? sub.map(catButton).join('') : '<li class="fp__empty">Keine Unterkategorien.</li>';
      $('[data-cats-col]').hidden = !!state.q;
      $('[data-media-h]').textContent = state.q ? 'Suchergebnis' : (state.category ? 'Medien' : 'Medien ohne Kategorie');
      $('[data-up-label]').hidden = !res.upload;
    };

    let loading = 0;
    async function load(append = false) {
      const run = ++loading;
      msg('Lädt …');
      try {
        const res = await api('media', { category: state.category, q: state.q, page: state.page, types: types.join(',') });
        if (run !== loading) return;
        cats = res.categories;
        state.category = res.category;
        nav(res);
        const list = $('[data-list]');
        if (!append) list.innerHTML = '';
        list.insertAdjacentHTML('beforeend', res.items.map(card).join(''));
        $('[data-more]').hidden = !res.more;
        msg(list.children.length ? '' : (state.q ? 'Keine Treffer.' : (res.upload ? 'Keine Dateien in dieser Kategorie.' : 'Keine Rechte für diese Kategorie – bitte eine Unterkategorie wählen.')));
      } catch (err) {
        if (run === loading) msg(err.message);
      }
    }

    return settle(d, onPick, (pick) => {
      d.addEventListener('click', (e) => {
        const c = e.target.closest('[data-cat]');
        if (c) {
          state.category = Number(c.dataset.cat);
          state.page = 0;
          store.set('frontend-picker-media-cat', state.category);
          load();
          return;
        }
        const b = e.target.closest('[data-file]');
        if (b) pick({ value: b.dataset.file, label: b.dataset.title });
      });
      // e.target gilt im Shadow DOM nur während des Events – die Felder selbst merken
      const q = $('[data-q]');
      let timer;
      q.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => { state.q = q.value.trim(); state.page = 0; load(); }, 250);
      });
      $('[data-more]').addEventListener('click', () => { state.page += 1; load(true); });
      const up = $('[data-up]');
      up.addEventListener('change', async () => {
        const file = up.files?.[0];
        up.value = '';
        if (!file) return;
        const fd = new FormData();
        fd.append('file', file);
        fd.append('category', String(state.category));
        fd.append('types', types.join(','));
        msg(`Lädt ${file.name} hoch …`);
        try {
          const res = await api('media_upload', fd);
          pick({ value: res.item.file, label: res.item.title || res.item.file });
        } catch (err) {
          msg(err.message);
        }
      });
      load();
      q.focus();
    });
  }

  /** Seiten wie die Linkmap von REDAXO: Pfad, links Kategorien (öffnen), rechts Artikel (wählen); Suche über alle */
  const articleCache = new Map();
  function article({ category, language, value, onPick } = {}) {
    const lang = Number(language) || cfg().language;
    const d = dialog('Seite auswählen', `
      <div class="fp__bar"><input class="fp__input" type="search" data-q placeholder="Seite suchen …" aria-label="Seite suchen"></div>
      <div class="fp__body" data-list><p class="fp__msg" role="status">Lädt …</p></div>`);
    const list = d.querySelector('[data-list]');
    const q = d.querySelector('[data-q]');
    const current = Number(String(value ?? '').replace(/^redaxo:\/\//, '')) || 0;
    let pages = [];
    let cats = new Map();
    let at = Number(category) || 0;

    const off = (p) => (p.online ? '' : ' <small>(offline)</small>');
    const item = (p, sub = '') => `<li><button type="button" class="fp__pick${p.id === current ? ' is-current' : ''}" data-id="${p.id}" data-name="${esc(p.name)}"${p.id === current ? ' aria-current="true"' : ''}>`
      + `<span class="fp__name">${esc(p.name)} <small class="fp__id">[${p.id}]</small>${off(p)}</span>${sub}</button></li>`;
    const render = () => {
      const words = q.value.toLowerCase().split(/\s+/).filter(Boolean);
      if (words.length) {
        const hits = pages.filter((p) => words.every((w) => (p.name + ' ' + p.path).toLowerCase().includes(w)));
        list.innerHTML = hits.length
          ? '<ul class="fp__list fp__list--box">' + hits.map((p) => item(p, `<small class="fp__path">${esc(p.path)}</small>`)).join('') + '</ul>'
          : '<p class="fp__empty">Keine Seite gefunden.</p>';
        return;
      }
      const chain = [];
      for (let c = cats.get(at); c; c = cats.get(c.parent)) chain.unshift(c);
      const sub = pages.filter((p) => p.cat && p.parent === at);
      const arts = [...(at && cats.get(at) ? [cats.get(at)] : []), ...pages.filter((p) => !p.cat && p.parent === at)];
      list.innerHTML = `<nav class="fp__trail" aria-label="Pfad">${trailHtml('Hauptebene', chain, at)}</nav><div class="fp__cols">`
        + '<section><h3>Kategorien</h3>' + (sub.length ? '<ul class="fp__list">' + sub.map((c) => catButton({ ...c, name: c.name })).join('') + '</ul>' : '<p class="fp__empty">Keine Unterkategorien.</p>') + '</section>'
        + '<section><h3>Artikel</h3>' + (arts.length ? '<ul class="fp__list">' + arts.map((p) => item(p)).join('') + '</ul>' : '<p class="fp__empty">Keine Artikel.</p>') + '</section></div>';
    };

    return settle(d, onPick, async (pick) => {
      list.addEventListener('click', (e) => {
        const c = e.target.closest('[data-cat]');
        if (c) {
          at = Number(c.dataset.cat);
          render();
          (list.querySelector('.fp__cat, .fp__pick') || q).focus();
          return;
        }
        const b = e.target.closest('[data-id]');
        if (b) pick({ value: b.dataset.id, label: `${b.dataset.name} [${b.dataset.id}]` });
      });
      q.addEventListener('input', render);
      q.focus();
      try {
        if (!articleCache.has(lang)) articleCache.set(lang, api('articles', { language: lang }).then((r) => r.articles));
        pages = await articleCache.get(lang);
      } catch (err) {
        articleCache.delete(lang);
        list.innerHTML = `<p class="fp__msg" role="alert">${esc(err.message)}</p>`;
        return;
      }
      cats = new Map(pages.filter((p) => p.cat).map((p) => [p.id, p]));
      // Start in der Kategorie der gewählten Seite (Startartikel: in seiner eigenen Kategorie)
      const cur = pages.find((p) => p.id === current);
      if (cur) at = cur.cat ? cur.id : cur.parent;
      if (at && !cats.has(at)) at = 0;
      render();
      list.querySelector('.is-current')?.focus();
    });
  }

  const pickers = { media, article };

  window.FrontendPicker = {
    pickers,
    /**
     * Auswahl öffnen; Ergebnis wie rex.pick(): Liste der gewählten Einträge ({ value, label }), leer ohne Auswahl
     * @param {'media'|'article'} type
     */
    pick(type, options = {}) {
      if (!pickers[type]) return Promise.reject(new Error(`Unbekannter Picker „${type}“.`));
      return pickers[type](options);
    },
  };
})();

/*
 * Design „Seitenwechsel: Morphen“ (css/opt-transition-morph.css; aus KLXM Studio, Kits foto/galerie). Im <head> geladen (nicht defer):
 * pagereveal kommt vor dem ersten Bild der neuen Seite. Alte Seite: angeklicktes Bild → „f-morph“, angeklickter kurzer Text →
 * „f-title“; ohne Klick (Zurück, Vor) großes Bild und Überschrift. Neue Seite: Bild, das zur vorigen Seite führt, bzw. großes Bild
 * und erste sichtbare Überschrift. Ohne „Animationen“ (html.has-motion) oder mit „Bewegung reduzieren“: kein Übergang.
 */
(() => {
  if (!('onpagereveal' in window)) return;
  const d = document, html = d.documentElement, key = 'f-vt-from';
  const on = () => html.classList.contains('has-motion') && !matchMedia('(prefers-reduced-motion: reduce)').matches;
  const vis = (el) => { const r = el?.getBoundingClientRect(); return !!r && r.width > 8 && r.bottom > 0 && r.top < innerHeight; };
  const set = (el, n) => { if (el) el.style.viewTransitionName = n; };
  const clear = () => d.querySelectorAll('[style*="view-transition-name"]').forEach((el) => { el.style.viewTransitionName = ''; });
  const imgOf = (a) => a?.querySelector('img') || a?.closest('li,article,figure,.card,.tile')?.querySelector('img');
  const bigImg = () => [...d.querySelectorAll('main img')].find((i) => vis(i) && i.getBoundingClientRect().width > innerWidth * 0.3) || null;
  const title = () => [...d.querySelectorAll('main h1, main h2')].find(vis) || null;
  let clicked = false;

  // alte Seite
  d.addEventListener('click', (e) => {
    const a = e.target.closest?.('a[href]');
    if (!on() || !a || e.defaultPrevented || e.button || e.metaKey || e.ctrlKey || e.shiftKey || a.target === '_blank' || a.origin !== location.origin
      || a.hasAttribute('download') || a.closest('[data-cms-lightbox]') || (a.hash && a.pathname === location.pathname)) return;
    clear();
    const img = imgOf(a);
    if (vis(img)) set(img, 'f-morph');
    else if (a.textContent.trim().length < 60) set(a, 'f-title');
    clicked = true;
  });
  addEventListener('pageswap', (e) => {
    if (!e.viewTransition || !on()) return;
    if (!clicked) { clear(); set(bigImg(), 'f-morph'); set(title(), 'f-title'); }
    clicked = false;
  });
  addEventListener('pagehide', () => { try { sessionStorage.setItem(key, location.href); } catch { /* privat */ } });
  addEventListener('pageshow', (e) => { if (e.persisted) clear(); });

  // neue Seite
  addEventListener('pagereveal', (e) => {
    const t = e.viewTransition;
    if (!t) return;
    t.ready.catch(() => {});   // abgebrochener Übergang (schneller Klick) ist kein Fehler
    if (!on()) { t.skipTransition(); return; }
    let from = window.navigation?.activation?.from?.url;
    try { from ||= sessionStorage.getItem(key); } catch { /* privat */ }
    let img = from ? imgOf([...d.querySelectorAll('a[href]')].find((x) => x.href === from && vis(imgOf(x)))) : null;
    img ||= bigImg();
    const h = title();
    set(img, 'f-morph');
    set(h, 'f-title');
    t.finished.finally(() => { set(img, ''); set(h, ''); });
  });
})();

#!/usr/bin/env node
/**
 * Demo-Video „Hey REDAXO 6 – wie cool ist das denn?“: Seiten pflegen, formatieren, Blöcke einfügen, Design ändern,
 * veröffentlichen – mit fluid. Ohne Ton, Untertitel im Bild (deutsch) + VTT (de, en).
 *
 *   node tools/video/redaxo6-demo.mjs            aufnehmen und schneiden → tools/video/out/
 *   Umgebung: SITE (Standard http://127.0.0.1:8095), LOGIN (Anmelde-Adresse der lokalen Testinstallation, Standard
 *   {SITE}/__devlogin?next=…), PLAYWRIGHT (Pfad zum Paket playwright), FFMPEG
 *
 * NUR gegen eine lokale Testinstallation: Die Aufnahme ändert Inhalte und Design wirklich. Danach zurücksetzen mit
 *   php project/bin/console fluid:demo --set=redaxo6 --replace --reset-design
 *
 * Aufnahme: Chromium-Bildschirmübertragung (CDP Page.startScreencast) in 1920 × 1080 (Fenster 1280 × 720, Pixeldichte 1,5),
 * Einzelbilder mit Zeitstempel → ffmpeg (concat, 30 fps, H.264). Mauszeiger, Untertitel und Titelkarten liegen als
 * Ebene in der Seite (ffmpeg ohne drawtext/subtitles genügt).
 */
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';

const DIR = path.dirname(fileURLToPath(import.meta.url));
const OUT = path.join(DIR, 'out');
const WORK = path.join(DIR, '.work');
const SITE = process.env.SITE || 'http://127.0.0.1:8095';
const FFMPEG = process.env.FFMPEG || (fs.existsSync('/opt/homebrew/bin/ffmpeg') ? '/opt/homebrew/bin/ffmpeg' : 'ffmpeg');
const require = createRequire(import.meta.url);
const { chromium } = require(process.env.PLAYWRIGHT || 'playwright');
const NAME = 'redaxo6-fluid-demo';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

fs.rmSync(WORK, { recursive: true, force: true });
fs.mkdirSync(path.join(WORK, 'frames'), { recursive: true });
fs.mkdirSync(OUT, { recursive: true });

// ------------------------------------------------------------------ Ebene in der Seite: Zeiger, Untertitel, Titelkarten

const OVERLAY = () => {
  if (window.__demo) return;
  window.__demo = true;
  const css = `
    #demo-cursor{position:fixed;z-index:2147483647;left:0;top:0;width:22px;height:22px;margin:-3px 0 0 -3px;pointer-events:none;transition:transform .06s linear}
    #demo-cursor svg{width:22px;height:22px;filter:drop-shadow(0 2px 3px rgba(0,0,0,.35))}
    #demo-cursor.is-down::after{content:"";position:absolute;left:-14px;top:-14px;width:44px;height:44px;border-radius:50%;background:rgba(75,91,214,.28);animation:demo-ripple .5s ease-out forwards}
    @keyframes demo-ripple{from{transform:scale(.4);opacity:1}to{transform:scale(1.4);opacity:0}}
    #demo-cap{position:fixed;z-index:2147483646;left:50%;bottom:28px;transform:translateX(-50%);max-width:min(880px,calc(100vw - 48px));padding:12px 20px;border-radius:14px;
      background:rgba(14,16,34,.88);color:#fff;font:600 21px/1.35 Inter,system-ui,sans-serif;text-align:center;letter-spacing:-.01em;box-shadow:0 12px 40px rgba(0,0,0,.35);
      transition:opacity .25s;pointer-events:none}
    #demo-cap[hidden]{display:block;opacity:0}
    #demo-card{position:fixed;inset:0;z-index:2147483647;display:grid;place-items:center;text-align:center;padding:48px;
      background:radial-gradient(1200px 600px at 30% 20%,#5b6cf0 0%,#2b2f8f 45%,#0e1022 100%);color:#fff;font-family:Inter,system-ui,sans-serif;transition:opacity .5s}
    #demo-card h1{margin:0 0 18px;font-size:64px;line-height:1.05;letter-spacing:-.03em;font-weight:800}
    #demo-card p{margin:0;font-size:26px;opacity:.88}
    #demo-card small{display:block;margin-top:36px;font-size:16px;letter-spacing:.12em;text-transform:uppercase;opacity:.7}
    #demo-card[hidden]{display:grid;opacity:0;pointer-events:none}`;
  const add = () => {
    const st = document.createElement('style');
    st.textContent = css;
    document.head.append(st);
    const c = document.createElement('div');
    c.id = 'demo-cursor';
    c.innerHTML = '<svg viewBox="0 0 24 24"><path d="M4 2l16 9-7 1.6L9.8 20z" fill="#fff" stroke="#111" stroke-width="1.6" stroke-linejoin="round"/></svg>';
    document.body.append(c);
    const pos = JSON.parse(sessionStorage.getItem('demo-pos') || '[640,360]');
    c.style.transform = `translate(${pos[0]}px,${pos[1]}px)`;
    addEventListener('mousemove', (e) => { c.style.transform = `translate(${e.clientX}px,${e.clientY}px)`; sessionStorage.setItem('demo-pos', JSON.stringify([e.clientX, e.clientY])); }, true);
    addEventListener('mousedown', () => { c.classList.remove('is-down'); void c.offsetWidth; c.classList.add('is-down'); }, true);
    const cap = document.createElement('div');
    cap.id = 'demo-cap';
    cap.hidden = true;
    document.body.append(cap);
    const t = sessionStorage.getItem('demo-cap');
    if (t) { cap.textContent = t; cap.hidden = false; }
  };
  if (document.body) add(); else addEventListener('DOMContentLoaded', add);
};

// ------------------------------------------------------------------ Drehbuch-Helfer

const cues = [];       // { start, end, de, en } in Sekunden (Bezug: erstes Bild)
let t0 = 0;            // Zeit des ersten Bildes (Sekunden, epoch)
let open = null;
const now = () => Date.now() / 1000;

async function caption(page, de, en) {
  const t = now() - t0;
  if (open) { open.end = t; cues.push(open); }
  open = de ? { start: t, end: 0, de, en } : null;
  await page.evaluate(([text]) => {
    sessionStorage.setItem('demo-cap', text || '');
    const c = document.getElementById('demo-cap');
    if (!c) return;
    if (text) { c.textContent = text; c.hidden = false; } else c.hidden = true;
  }, [de || '']);
}

async function card(page, title, sub, small = '', ms = 3500) {
  await page.evaluate(([t, s, sm]) => {
    let c = document.getElementById('demo-card');
    if (!c) { c = document.createElement('div'); c.id = 'demo-card'; document.body.append(c); }
    c.innerHTML = `<div><h1>${t}</h1><p>${s}</p>${sm ? `<small>${sm}</small>` : ''}</div>`;
    c.hidden = false;
  }, [title, sub, small]);
  await sleep(ms);
  await page.evaluate(() => { const c = document.getElementById('demo-card'); if (c) c.hidden = true; });
  await sleep(500);
}

/** Maus sichtbar zum Element bewegen und klicken */
async function moveTo(page, locator, { click = true, offset = null } = {}) {
  await locator.scrollIntoViewIfNeeded();
  const box = await locator.boundingBox();
  if (!box) throw new Error('Element nicht sichtbar: ' + locator);
  const x = box.x + (offset ? offset[0] : box.width / 2);
  const y = box.y + (offset ? offset[1] : Math.min(box.height / 2, 24));
  await page.mouse.move(x, y, { steps: 18 });
  await sleep(250);
  if (click) await page.mouse.click(x, y);
  await sleep(300);
}

async function type(page, text, delay = 55) {
  await page.keyboard.type(text, { delay });
}

const ui = (page) => page.locator('#blocks-ui');
const goto = async (page, url) => { await page.goto(url); await page.waitForLoadState('networkidle'); await sleep(400); };
const nav = (page, label) => page.locator('.hnav__link', { hasText: label }).first();

// ------------------------------------------------------------------ Aufnahme

const browser = await chromium.launch({ headless: !process.argv.includes('--headed') });
const context = await browser.newContext({ viewport: { width: 1280, height: 720 }, deviceScaleFactor: 1.5, locale: 'de-DE' });
await context.addInitScript(OVERLAY);
const page = await context.newPage();

// Anmelden (lokal) – vor der Aufnahme
await goto(page, process.env.LOGIN || `${SITE}/__devlogin?next=${encodeURIComponent('/index.php')}`);
await page.waitForURL(/index\.php/, { timeout: 15000 });
await goto(page, `${SITE}/index.php`);

const frames = [];
let active = null;   // Fenster, dessen Bilder gerade ins Video kommen (Hauptseite bzw. Popup von Medienpool/Linkmap)
async function cast(p) {
  const session = await context.newCDPSession(p);
  session.on('Page.screencastFrame', async (f) => {
    try { await session.send('Page.screencastFrameAck', { sessionId: f.sessionId }); } catch {}
    if (active !== p) return;
    const file = path.join(WORK, 'frames', String(frames.length).padStart(6, '0') + '.jpg');
    fs.writeFileSync(file, Buffer.from(f.data, 'base64'));
    frames.push({ file, ts: f.metadata.timestamp });
    if (!t0) t0 = f.metadata.timestamp;
  });
  await session.send('Page.startScreencast', { format: 'jpeg', quality: 92, maxWidth: 1920, maxHeight: 1080, everyNthFrame: 1 });
  return session;
}
const cdp = await cast(page);
active = page;
// Popups (Medienpool, Linkmap): solange sie offen sind, zeigt das Video sie
context.on('page', async (popup) => {
  await popup.setViewportSize({ width: 1280, height: 720 }).catch(() => {});
  await cast(popup).catch(() => {});
  active = popup;
  popup.on('close', () => { active = page; });
});
// Bild halten, bis die Aufnahme läuft
while (!t0) await sleep(50);

try {
  // 1. Titel
  await card(page, 'Hey REDAXO 6 –<br>wie cool ist das denn?', 'Seiten pflegen direkt auf der Website – mit fluid', 'Prototyp · Friends Of REDAXO', 4200);

  // 2. Die Website
  await caption(page, 'Das ist eine Website mit REDAXO 6 und dem Kit „fluid“.', 'This is a website built with REDAXO 6 and the “fluid” kit.');
  await page.mouse.move(900, 400, { steps: 20 });
  await sleep(1200);
  for (let i = 0; i < 6; i++) { await page.mouse.wheel(0, 260); await sleep(220); }
  await sleep(700);
  for (let i = 0; i < 6; i++) { await page.mouse.wheel(0, -260); await sleep(120); }
  await sleep(500);

  // 3. Bearbeiten-Modus
  await caption(page, 'Angemeldet? Ein Klick auf „Bearbeiten“ – und die Seite wird zum Editor.', 'Logged in? One click on “Edit” turns the page into the editor.');
  await moveTo(page, ui(page).locator('[data-mode="edit"]'), { click: false });
  await sleep(500);
  await Promise.all([page.waitForURL(/fluid_edit=1/), ui(page).locator('[data-mode="edit"]').click()]);
  await page.waitForLoadState('networkidle');
  await sleep(900);

  // 4. Text direkt ändern
  await caption(page, 'Texte ändern, wo sie stehen: einfach hineinklicken und tippen.', 'Change text right where it is: click and type.');
  const lead = page.locator('section[data-type="hero"] [data-edit="text"]').first();
  await moveTo(page, lead, { offset: [30, 14] });
  await page.keyboard.press('Home');
  await page.keyboard.down('Shift');
  for (let i = 0; i < 34; i++) await page.keyboard.press('ArrowRight');   // „REDAXO 6 räumt unter der Haube auf“
  await page.keyboard.up('Shift');
  await sleep(400);
  await type(page, 'REDAXO 6 räumt gründlich auf');
  await sleep(900);

  // 5. Formatieren (Rich Text in einer Layout-Spalte)
  await caption(page, 'Formatieren mit der Leiste: fett, Marker, Farben, Listen, Links.', 'Format with the toolbar: bold, highlight, colours, lists, links.');
  const rich = page.locator('[data-edit-mode="rich"]').first();
  await rich.scrollIntoViewIfNeeded();
  await sleep(400);
  const word = rich.locator('strong').first();
  const wb = await word.boundingBox();
  await page.mouse.move(wb.x + 2, wb.y + wb.height / 2, { steps: 16 });
  await page.mouse.dblclick(wb.x + wb.width / 2, wb.y + wb.height / 2);
  await sleep(500);
  const fmt = ui(page).locator('.cms-inline-bar');
  await moveTo(page, fmt.locator('[data-cmd="mark"]').first());
  await sleep(700);
  await moveTo(page, fmt.locator('[data-menu="color"]').first());
  await sleep(600);
  await moveTo(page, fmt.locator('[data-cmd="color:accent"]').first());
  await sleep(1000);

  // 6. Speichern
  await caption(page, 'Speichern landet in der Arbeitsversion – noch nicht live.', 'Saving goes to the working version – not live yet.');
  await moveTo(page, ui(page).locator('[data-save]'));
  await sleep(1400);

  // 7. Seitenleiste mit allen Feldern
  await caption(page, 'Alle Felder in der Seitenleiste – die Seite zeigt beim Tippen sofort das Ergebnis.', 'All fields in the side panel – the page previews as you type.');
  const stats = page.locator('section[data-type="stats"]').first();
  await stats.scrollIntoViewIfNeeded();
  await moveTo(page, stats, { click: false, offset: [400, 120] });
  await moveTo(page, ui(page).locator('[data-bb-edit]'));
  await sleep(700);
  await moveTo(page, ui(page).locator('select[name="f[1][variant]"]'), { click: false });
  await ui(page).locator('select[name="f[1][variant]"]').selectOption('cards');
  await sleep(1300);
  const title = ui(page).locator('input[name="f[1][title]"]');
  await moveTo(page, title);
  await page.keyboard.press('ControlOrMeta+A');
  await type(page, 'REDAXO 6 in Zahlen', 70);
  await sleep(1200);
  await moveTo(page, ui(page).locator('[data-panel-apply]'));
  await sleep(900);

  // 8. Block einfügen: Text + Bild – Bild aus dem Medienpool, Link über die Linkmap
  await caption(page, 'Neue Blöcke einfügen – jeder Block ist ein echter REDAXO-Slice.', 'Insert new blocks – every block is a real REDAXO slice.');
  await stats.scrollIntoViewIfNeeded();
  await moveTo(page, stats, { click: false, offset: [500, 80] });
  await ui(page).locator('.addbtn').waitFor({ state: 'visible' });
  await moveTo(page, ui(page).locator('.addbtn'));
  await ui(page).locator('[data-picker-q]').waitFor({ state: 'visible' });
  await type(page, 'Bild', 110);
  await sleep(500);
  await moveTo(page, ui(page).locator('.picker__item[data-key="fluid.media_text"]'));
  await page.waitForTimeout(1200);
  await moveTo(page, ui(page).locator('input[name="f[1][title]"]'));
  await type(page, 'Bilder kommen aus dem Medienpool', 45);
  await sleep(500);

  await caption(page, 'Bilder kommen aus dem echten REDAXO-Medienpool …', 'Images come from the real REDAXO media pool …');
  const popup1 = context.waitForEvent('page');
  await moveTo(page, ui(page).locator('[data-blocks-pick="media"] [data-blocks-pick-open]').first());
  const mp = await popup1;
  await mp.waitForLoadState('networkidle');
  await sleep(900);
  const sel = mp.locator('a.btn-select').first();
  const sb = await sel.boundingBox();
  await mp.mouse.move(sb.x + sb.width / 2, sb.y + sb.height / 2, { steps: 18 });
  await sleep(500);
  await sel.click();
  await page.waitForTimeout(1500);

  await caption(page, '… und Links über die Linkmap – auch wenn die Seite später umzieht.', '… and links via the linkmap – even if the page moves later.');
  await moveTo(page, ui(page).locator('input[name="f[1][button_label]"]'));
  await type(page, 'Mehr zur Technik', 60);
  const popup2 = context.waitForEvent('page');
  await moveTo(page, ui(page).locator('[data-blocks-pick="link"] [data-blocks-pick-open]').first());
  const lm = await popup2;
  await lm.waitForLoadState('networkidle');
  await sleep(800);
  const cat = lm.locator('a', { hasText: 'Technik' }).first();
  let cb = await cat.boundingBox();
  await lm.mouse.move(cb.x + cb.width / 2, cb.y + cb.height / 2, { steps: 16 });
  await sleep(400);
  await cat.click();
  await lm.waitForLoadState('networkidle');
  await sleep(800);
  const art = lm.locator('.rex-linkmap-list-item-article a', { hasText: 'Technik' }).first();
  cb = await art.boundingBox();
  await lm.mouse.move(cb.x + cb.width / 2, cb.y + cb.height / 2, { steps: 16 });
  await sleep(400);
  await art.click();
  await page.waitForTimeout(1500);
  await moveTo(page, ui(page).locator('[data-panel-apply]'));
  await sleep(1500);

  // 9. Design
  await caption(page, 'Das Design: Vorlagen, Farben, Schriften – live auf der Seite.', 'Design: presets, colours, fonts – live on the page.');
  await page.evaluate(() => scrollTo({ top: 0, behavior: 'smooth' }));
  await sleep(700);
  await moveTo(page, ui(page).locator('[data-design-open]'));
  await sleep(800);
  for (const p of ['kanzlei', 'restaurant', 'startup', 'aurora']) {
    await moveTo(page, ui(page).locator(`[data-preset="${p}"]`));
    await sleep(1700);
  }
  await caption(page, 'Jede Einstellung einzeln – mit Prüfung des Kontrasts.', 'Every setting on its own – with a contrast check.');
  const farben = ui(page).locator('.dgroup > summary', { hasText: 'Form & Raum' });
  await moveTo(page, farben);
  await sleep(500);
  const buttons = ui(page).locator('select[data-token="buttons"]');
  await moveTo(page, buttons, { click: false });
  await buttons.selectOption('pill');
  await sleep(1100);
  const radius = ui(page).locator('input[data-token="radius"]');
  await radius.scrollIntoViewIfNeeded();
  const rb = await radius.boundingBox();
  await page.mouse.move(rb.x + rb.width * 0.3, rb.y + rb.height / 2, { steps: 12 });
  await page.mouse.down();
  await page.mouse.move(rb.x + rb.width * 0.85, rb.y + rb.height / 2, { steps: 24 });
  await page.mouse.up();
  await sleep(1200);
  await moveTo(page, ui(page).locator('[data-design-save]'));
  await sleep(1300);

  // 10. Veröffentlichen
  await caption(page, '„Veröffentlichen“ – und alles ist live.', '“Publish” – and it’s all live.');
  await moveTo(page, ui(page).locator('[data-publish]'));
  await sleep(1400);
  await moveTo(page, ui(page).locator('[data-mode="view"]'), { click: false });
  await Promise.all([page.waitForURL((u) => !String(u).includes('fluid_edit')), ui(page).locator('[data-mode="view"]').click()]);
  await page.waitForLoadState('networkidle');
  await sleep(600);
  for (let i = 0; i < 8; i++) { await page.mouse.wheel(0, 240); await sleep(200); }
  await sleep(800);

  // 11. Backend
  await caption(page, 'Im Backend: dieselben Blöcke als ganz normale Slices – mit Medienpool und Linkmap.', 'In the backend: the same blocks as regular slices – with media pool and linkmap.');
  const homeId = await page.evaluate(() => JSON.parse(document.getElementById('blocks-editor-config').textContent).article.id);
  await goto(page, `${SITE}/redaxo/index.php?page=content/edit&article_id=${homeId}&language=1&mode=edit`);
  await sleep(900);
  for (let i = 0; i < 5; i++) { await page.mouse.wheel(0, 240); await sleep(260); }
  await sleep(1000);

  await caption(page, '');
  await card(page, 'fluid für REDAXO 6', 'Blöcke mit Feldschema · echte Slices · Bearbeiten auf der Website · Style-Editor', 'Prototyp · Friends Of REDAXO · MIT', 4500);
} finally {
  await cdp.send('Page.stopScreencast').catch(() => {});
  active = null;
  if (open) { open.end = now() - t0; cues.push(open); }
  await browser.close();
}

// ------------------------------------------------------------------ Schnitt

const end = now();
const list = frames.map((f, i) => {
  const next = frames[i + 1]?.ts ?? Math.min(end, f.ts + 1);
  return `file '${f.file}'\nduration ${Math.max(0.001, next - f.ts).toFixed(3)}`;
}).join('\n') + `\nfile '${frames.at(-1).file}'\n`;
fs.writeFileSync(path.join(WORK, 'frames.txt'), list);

const mp4 = path.join(OUT, NAME + '.mp4');
execFileSync(FFMPEG, ['-y', '-loglevel', 'error', '-f', 'concat', '-safe', '0', '-i', path.join(WORK, 'frames.txt'),
  '-vf', 'scale=1920:1080:flags=lanczos,format=yuv420p', '-r', '30', '-fps_mode', 'cfr',
  '-c:v', 'libx264', '-preset', 'slow', '-crf', '18', '-movflags', '+faststart', '-an', mp4], { stdio: 'inherit' });
execFileSync(FFMPEG, ['-y', '-loglevel', 'error', '-ss', '6', '-i', mp4, '-frames:v', '1', '-q:v', '3', path.join(OUT, NAME + '.jpg')]);

const ts = (s) => { const ms = Math.max(0, Math.round(s * 1000)); const h = Math.floor(ms / 3600000); const m = Math.floor(ms / 60000) % 60; const sec = Math.floor(ms / 1000) % 60; return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}.${String(ms % 1000).padStart(3, '0')}`; };
for (const lang of ['de', 'en']) {
  const vtt = 'WEBVTT\n\n' + cues.filter((c) => c.end > c.start).map((c, i) => `${i + 1}\n${ts(c.start)} --> ${ts(c.end)}\n${c[lang]}\n`).join('\n');
  fs.writeFileSync(path.join(OUT, `${NAME}.${lang}.vtt`), vtt);
}
const dur = frames.length ? (end - t0) : 0;
console.log(`${frames.length} Bilder, ${dur.toFixed(1)} s → ${mp4}`);

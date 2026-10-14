#!/usr/bin/env node
/**
 * Trailer „fluid für REDAXO 6“ (höchstens 30 s, ohne Ton): kurze Einstellungen, beschleunigt geschnitten, Titelkarten mit
 * bewegter Schrift, Etiketten im Bild; Untertitel als VTT (de, en, sl).
 *
 *   node tools/video/trailer.mjs [--headed]      → tools/video/out/fluid-trailer.{mp4,jpg,de.vtt,en.vtt,sl.vtt}
 *   Umgebung: SITE (Standard http://127.0.0.1:8095), PLAYWRIGHT (Pfad zum Paket playwright), FFMPEG
 *
 * NUR gegen eine lokale Testinstallation mit den Beispielseiten (fluid:demo --set=redaxo6, YForm-Beispiele).
 * Die Aufnahme speichert nichts: Tippen und Design-Vorschau werden nicht übernommen, das Formular nicht abgeschickt.
 *
 * Aufnahme wie redaxo6-demo.mjs (CDP Page.startScreencast, 1920 × 1080). Schnitt: Jede Einstellung („shot“) hat eine
 * Geschwindigkeit; nur Bilder innerhalb der Einstellungen kommen ins Video, ihre Dauer wird durch die Geschwindigkeit
 * geteilt (ffmpeg concat mit Dauer je Bild).
 */
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';

const DIR = path.dirname(fileURLToPath(import.meta.url));
const OUT = path.join(DIR, 'out');
const WORK = path.join(DIR, '.work-trailer');
const SITE = process.env.SITE || 'http://127.0.0.1:8095';
const FFMPEG = process.env.FFMPEG || (fs.existsSync('/opt/homebrew/bin/ffmpeg') ? '/opt/homebrew/bin/ffmpeg' : 'ffmpeg');
const require = createRequire(import.meta.url);
const { chromium } = require(process.env.PLAYWRIGHT || 'playwright');
const NAME = 'fluid-trailer';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const now = () => Date.now() / 1000;

fs.rmSync(WORK, { recursive: true, force: true });
fs.mkdirSync(path.join(WORK, 'frames'), { recursive: true });
fs.mkdirSync(OUT, { recursive: true });

// ------------------------------------------------------------------ Ebene in der Seite: Zeiger, Etikett, Titelkarten

const OVERLAY = () => {
  if (window.__tr) return;
  window.__tr = true;
  const css = `
    #tr-cursor{position:fixed;z-index:2147483647;left:0;top:0;width:22px;height:22px;margin:-3px 0 0 -3px;pointer-events:none;transition:transform .05s linear}
    #tr-cursor svg{width:22px;height:22px;filter:drop-shadow(0 2px 3px rgba(0,0,0,.35))}
    #tr-cursor.is-down::after{content:"";position:absolute;left:-14px;top:-14px;width:44px;height:44px;border-radius:50%;background:rgba(44,106,168,.35);animation:tr-rip .45s ease-out forwards}
    @keyframes tr-rip{from{transform:scale(.4);opacity:1}to{transform:scale(1.5);opacity:0}}
    #tr-label{position:fixed;z-index:2147483646;left:36px;bottom:36px;padding:14px 22px 14px 18px;border-radius:6px;background:#1d2835;color:#fff;
      font:700 30px/1.15 "Source Sans 3",Inter,system-ui,sans-serif;letter-spacing:-.01em;box-shadow:0 18px 50px rgba(0,0,0,.35);pointer-events:none;
      border-left:8px solid #8cbdee;transform-origin:left center}
    #tr-label.in{animation:tr-in .45s cubic-bezier(.2,.9,.2,1) both}
    #tr-label[hidden]{display:none}
    @keyframes tr-in{from{opacity:0;transform:translateX(-40px) scale(.96)}to{opacity:1;transform:none}}
    #tr-card{position:fixed;inset:0;z-index:2147483647;display:grid;place-items:center;text-align:center;padding:48px;overflow:hidden;
      background:radial-gradient(1100px 700px at 25% 15%,#3d82c4 0%,#2c6aa8 35%,#1d2835 100%);color:#fff;font-family:"Source Sans 3",Inter,system-ui,sans-serif}
    #tr-card[hidden]{display:none}
    #tr-card h1{margin:0;font-size:150px;line-height:.95;font-weight:800;letter-spacing:-.04em}
    #tr-card h2{margin:0;font-size:84px;line-height:1.02;font-weight:800;letter-spacing:-.03em}
    #tr-card p{margin:22px 0 0;font-size:34px;font-weight:600;opacity:.92}
    #tr-card small{display:block;margin-top:40px;font-size:20px;letter-spacing:.16em;text-transform:uppercase;opacity:.75}
    #tr-card .w{display:inline-block;opacity:0;animation:tr-word .55s cubic-bezier(.2,.9,.2,1) forwards}
    @keyframes tr-word{from{opacity:0;transform:translateY(40px);filter:blur(8px)}to{opacity:1;transform:none;filter:none}}
    #tr-card .bar{width:0;height:8px;margin:28px auto 0;background:#8cbdee;border-radius:4px;animation:tr-bar .9s .35s cubic-bezier(.2,.9,.2,1) forwards}
    @keyframes tr-bar{to{width:220px}}`;
  const add = () => {
    const st = document.createElement('style');
    st.textContent = css;
    document.head.append(st);
    const c = document.createElement('div');
    c.id = 'tr-cursor';
    c.innerHTML = '<svg viewBox="0 0 24 24"><path d="M4 2l16 9-7 1.6L9.8 20z" fill="#fff" stroke="#111" stroke-width="1.6" stroke-linejoin="round"/></svg>';
    document.body.append(c);
    const pos = JSON.parse(sessionStorage.getItem('tr-pos') || '[640,360]');
    c.style.transform = `translate(${pos[0]}px,${pos[1]}px)`;
    addEventListener('mousemove', (e) => { c.style.transform = `translate(${e.clientX}px,${e.clientY}px)`; sessionStorage.setItem('tr-pos', JSON.stringify([e.clientX, e.clientY])); }, true);
    addEventListener('mousedown', () => { c.classList.remove('is-down'); void c.offsetWidth; c.classList.add('is-down'); }, true);
    const l = document.createElement('div');
    l.id = 'tr-label';
    l.hidden = true;
    document.body.append(l);
    const t = sessionStorage.getItem('tr-label');
    if (t) { l.textContent = t; l.hidden = false; }
  };
  if (document.body) add(); else addEventListener('DOMContentLoaded', add);
};

// ------------------------------------------------------------------ Drehbuch

const shots = [];   // { from, to, speed, label? }
const cues = [];    // { from, to, de, en, sl } in Aufnahmezeit
const ui = (page) => page.locator('#blocks-ui');

async function label(page, text) {
  await page.evaluate((t) => {
    sessionStorage.setItem('tr-label', t || '');
    const l = document.getElementById('tr-label');
    if (!l) return;
    l.hidden = !t;
    l.textContent = t || '';
    l.classList.remove('in'); void l.offsetWidth; l.classList.add('in');
  }, text);
}

/** Einstellung aufnehmen: Etikett zeigen, Handlung ausführen, im Schnitt mit `speed` abspielen */
async function shot(page, speed, text, fn) {
  if (text) await label(page, text.de);
  const from = now();
  await fn();
  const to = now();
  shots.push({ from, to, speed });
  if (text) cues.push({ from, to, speed, ...text });
}

/** Titelkarte mit bewegter Schrift (Echtzeit) */
async function card(page, html, ms) {
  await label(page, '');
  await page.evaluate((h) => {
    let c = document.getElementById('tr-card');
    if (!c) { c = document.createElement('div'); c.id = 'tr-card'; document.body.append(c); }
    c.innerHTML = `<div>${h}</div>`;
    c.querySelectorAll('.w').forEach((w, i) => { w.style.animationDelay = `${0.08 + i * 0.11}s`; });
    c.hidden = false;
  }, html);
  const from = now();
  await sleep(ms);
  shots.push({ from, to: now(), speed: 1 });
  await page.evaluate(() => { const c = document.getElementById('tr-card'); if (c) c.hidden = true; });
}
const words = (s) => s.split(' ').map((w) => `<span class="w">${w}</span>`).join(' ');

async function moveTo(page, locator, { click = true, offset = null, steps = 14 } = {}) {
  await locator.scrollIntoViewIfNeeded();
  const box = await locator.boundingBox();
  if (!box) throw new Error('Element nicht sichtbar: ' + locator);
  const x = box.x + (offset ? offset[0] : box.width / 2);
  const y = box.y + (offset ? offset[1] : Math.min(box.height / 2, 24));
  await page.mouse.move(x, y, { steps });
  await sleep(150);
  if (click) await page.mouse.click(x, y);
  await sleep(200);
}
const goto = async (page, url) => { await page.goto(url); await page.waitForLoadState('networkidle'); await sleep(350); };

// ------------------------------------------------------------------ Aufnahme

const browser = await chromium.launch({ headless: !process.argv.includes('--headed') });
const context = await browser.newContext({ viewport: { width: 1280, height: 720 }, deviceScaleFactor: 1.5, locale: 'de-DE' });
await context.addInitScript(OVERLAY);
const page = await context.newPage();
page.on('dialog', (d) => d.accept().catch(() => {}));   // „Ungespeicherte Änderungen?“ – nichts wird gespeichert

await goto(page, `${SITE}/__devlogin?next=${encodeURIComponent('/index.php')}`);
await page.waitForURL(/index\.php/, { timeout: 15000 });

const frames = [];
let t0 = 0;
const session = await context.newCDPSession(page);
session.on('Page.screencastFrame', async (f) => {
  try { await session.send('Page.screencastFrameAck', { sessionId: f.sessionId }); } catch {}
  const file = path.join(WORK, 'frames', String(frames.length).padStart(6, '0') + '.jpg');
  fs.writeFileSync(file, Buffer.from(f.data, 'base64'));
  frames.push({ file, ts: f.metadata.timestamp });
  if (!t0) t0 = f.metadata.timestamp;
});
await session.send('Page.startScreencast', { format: 'jpeg', quality: 92, maxWidth: 1920, maxHeight: 1080, everyNthFrame: 1 });
while (!t0) await sleep(50);

try {
  // Auftakt
  await goto(page, `${SITE}/?fluid_edit=1`);
  await card(page, `<h1>${words('fluid')}</h1><div class="bar"></div><p>${words('Bearbeiten direkt auf der Website – für REDAXO 6')}</p>`, 2300);

  // 1. Text direkt auf der Seite
  await shot(page, 2, { de: 'Direkt auf der Seite bearbeiten', en: 'Edit right on the page', sl: 'Urejanje neposredno na strani' }, async () => {
    const t = page.locator('section[data-type="hero"] [data-edit="text"]').first();
    await moveTo(page, t, { offset: [40, 16] });
    await page.keyboard.press('End');
    await page.keyboard.type(' Einfach klicken und tippen.', { delay: 45 });
    await sleep(500);
  });

  // 2. Design-Vorlagen
  await shot(page, 2.6, { de: 'Design-Vorlagen – live umschalten', en: 'Design presets – switch live', sl: 'Predloge oblikovanja – preklop v živo' }, async () => {
    await moveTo(page, ui(page).locator('[data-design-open]'));
    await sleep(600);
    for (const p of ['handwerk', 'kanzlei', 'aurora', 'redaxo']) {
      await moveTo(page, ui(page).locator(`[data-preset="${p}"]`), { steps: 8 });
      await sleep(1100);
    }
  });

  // 3. Seitenbaum und Blog
  await goto(page, `${SITE}/neuigkeiten?fluid_edit=1`);
  await shot(page, 2, { de: 'Seitenbaum, Blog und News', en: 'Page tree, blog and news', sl: 'Drevo strani, blog in novice' }, async () => {
    for (let i = 0; i < 4; i++) { await page.mouse.wheel(0, 220); await sleep(160); }
    await page.evaluate(() => scrollTo({ top: 0 }));
    await moveTo(page, ui(page).locator('[data-more]'));
    await moveTo(page, ui(page).locator('[data-tree-open]'));
    await sleep(500);
    await moveTo(page, ui(page).locator('[data-tree-toggle]').first());
    await sleep(900);
  });

  // 4. Mehrsprachig
  await goto(page, `${SITE}/`);
  await shot(page, 1.6, { de: 'Mehrsprachig', en: 'Multilingual', sl: 'Večjezično' }, async () => {
    const en = page.locator('.hdr .langswitch a[hreflang="en"]').first();
    await moveTo(page, en, { click: false });
    await Promise.all([page.waitForURL(/\/en\//), en.click()]);
    await page.waitForLoadState('networkidle');
    await sleep(1000);
  });

  // 4b. Suche
  await goto(page, `${SITE}/suche`);
  await shot(page, 1.8, { de: 'Suche – auch mit Tippfehlern', en: 'Search – typos included', sl: 'Iskanje – tudi s tipkarskimi napakami' }, async () => {
    const q = page.locator('input[name="q"]').first();
    await moveTo(page, q);
    await page.keyboard.type('redxao bloecke', { delay: 60 });
    await Promise.all([page.waitForLoadState('networkidle'), page.keyboard.press('Enter')]);
    await sleep(400);
    for (let i = 0; i < 2; i++) { await page.mouse.wheel(0, 200); await sleep(160); }
    await sleep(500);
  });

  // 5. Formular-Baukasten
  await goto(page, `${SITE}/anmeldung`);
  await shot(page, 2.6, { de: 'Formular-Baukasten – mehrstufig', en: 'Form builder – multi-step', sl: 'Gradnik obrazcev – v več korakih' }, async () => {
    const f = 'form.dff';
    await moveTo(page, page.locator(`${f} [name$="[name]"]`));
    await page.keyboard.type('Erika Musterfrau', { delay: 30 });
    await moveTo(page, page.locator(`${f} [name$="[e_mail]"]`));
    await page.keyboard.type('erika@example.org', { delay: 25 });
    await moveTo(page, page.locator('.dff-stepnav .btn--primary'));
    await sleep(400);
    await moveTo(page, page.locator(`${f} input[type=radio][name$="[workshop]"]`).nth(1));
    await moveTo(page, page.locator(`${f} input[type=checkbox][name$="[interessen][]"]`).nth(1));
    await moveTo(page, page.locator('.dff-stepnav .btn--primary'));
    await sleep(700);
  });

  // 6. YForm-Daten mit eigenen Adressen
  await goto(page, `${SITE}/projekte`);
  await shot(page, 2, { de: 'YForm-Daten mit eigenen Adressen', en: 'YForm data with their own URLs', sl: 'Podatki YForm z lastnimi naslovi' }, async () => {
    for (let i = 0; i < 3; i++) { await page.mouse.wheel(0, 200); await sleep(150); }
    const a = page.locator('a.dl-link').first();
    await moveTo(page, a, { click: false });
    await Promise.all([page.waitForURL(/projekte\/.+-\d+/), a.click()]);
    await page.waitForLoadState('networkidle');
    for (let i = 0; i < 5; i++) { await page.mouse.wheel(0, 260); await sleep(170); }
    await sleep(400);
  });

  // 7. Blöcke im Datensatz – auf der Website bearbeiten
  await goto(page, page.url().replace(/\?.*$/, '') + '?fluid_edit=1');
  await shot(page, 2, { de: 'Blöcke auch in YForm-Datensätzen', en: 'Blocks inside YForm records, too', sl: 'Bloki tudi v zapisih YForm' }, async () => {
    const h = page.locator('section[data-record][data-type="richtext"] [data-edit="title"]').first();
    await h.scrollIntoViewIfNeeded();
    await page.evaluate(() => scrollBy(0, -140));
    await moveTo(page, h, { offset: [60, 30] });
    await page.keyboard.press('End');
    await page.keyboard.type(' – direkt hier', { delay: 45 });
    await sleep(300);
    // Blockleiste zeigen, aber nichts öffnen: Datensatz-Blöcke speichern sofort, die Aufnahme soll nichts ändern
    const s = page.locator('section[data-record][data-type="quote"]').first();
    await moveTo(page, s, { click: false, offset: [500, 120] });
    await moveTo(page, ui(page).locator('[data-bb-up]'), { click: false });
    await sleep(900);
  });

  // 8. YForm-Maske im Backend: dasselbe Feld „Fluid-Blöcke“
  await goto(page, `${SITE}/redaxo/index.php?page=yform/data&table=rex_yf_project&func=edit&id=1`);
  await shot(page, 2.2, { de: '… und in der YForm-Maske', en: '… and in the YForm form', sl: '… in v obrazcu YForm' }, async () => {
    const f = page.locator('[data-fluid-blocks]');
    await f.scrollIntoViewIfNeeded();
    await page.evaluate(() => scrollBy(0, -160));
    await moveTo(page, f.locator('[data-act="edit"]').nth(1));
    await page.waitForSelector('.fbf-editor');
    await sleep(900);
  });

  // Zwischenkarte: was noch drin ist
  await card(page, `<h2>${words('Rechte · Versionen · SEO')}</h2><h2>${words('Suche · Karten · Videos')}</h2>`, 2100);

  // Abspann
  await card(page, `<h1>${words('fluid')}</h1><p>${words('für REDAXO 6 · Friends Of REDAXO · MIT')}</p><small>Prototyp</small>`, 2800);
} finally {
  await session.send('Page.stopScreencast').catch(() => {});
  await browser.close();
}

// ------------------------------------------------------------------ Schnitt

let list = '';
let out = 0;           // Zeit im fertigen Video
const timeline = [];   // Einstellung → Zeitraum im Video
for (const s of shots) {
  const inShot = frames.filter((f) => f.ts >= s.from && f.ts < s.to);
  const before = [...frames].reverse().find((f) => f.ts < s.from);
  const seq = before ? [{ ...before, ts: s.from }, ...inShot] : inShot;
  const start = out;
  seq.forEach((f, i) => {
    const next = seq[i + 1]?.ts ?? s.to;
    const d = Math.max(0.001, (next - f.ts) / s.speed);
    list += `file '${f.file}'\nduration ${d.toFixed(4)}\n`;
    out += d;
  });
  timeline.push({ ...s, start, end: out });
}
list += `file '${frames.at(-1).file}'\n`;
fs.writeFileSync(path.join(WORK, 'frames.txt'), list);

const mp4 = path.join(OUT, NAME + '.mp4');
execFileSync(FFMPEG, ['-y', '-loglevel', 'error', '-f', 'concat', '-safe', '0', '-i', path.join(WORK, 'frames.txt'),
  '-vf', 'scale=1920:1080:flags=lanczos,format=yuv420p', '-r', '30', '-fps_mode', 'cfr',
  '-c:v', 'libx264', '-preset', 'slow', '-crf', '18', '-movflags', '+faststart', '-an', mp4], { stdio: 'inherit' });
execFileSync(FFMPEG, ['-y', '-loglevel', 'error', '-ss', '1.2', '-i', mp4, '-frames:v', '1', '-q:v', '3', path.join(OUT, NAME + '.jpg')]);

// Untertitel: Etiketten der Einstellungen in Videozeit
const ts = (x) => { const ms = Math.max(0, Math.round(x * 1000)); return `${String(Math.floor(ms / 3600000)).padStart(2, '0')}:${String(Math.floor(ms / 60000) % 60).padStart(2, '0')}:${String(Math.floor(ms / 1000) % 60).padStart(2, '0')}.${String(ms % 1000).padStart(3, '0')}`; };
const labelled = timeline.filter((s) => cues.some((c) => c.from === s.from));
for (const lang of ['de', 'en', 'sl']) {
  const vtt = 'WEBVTT\n\n' + labelled.map((s, i) => `${i + 1}\n${ts(s.start)} --> ${ts(s.end)}\n${cues.find((c) => c.from === s.from)[lang]}\n`).join('\n');
  fs.writeFileSync(path.join(OUT, `${NAME}.${lang}.vtt`), vtt);
}
console.log(`${frames.length} Bilder, ${out.toFixed(1)} s → ${mp4}`);

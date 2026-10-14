# Changelog

## 1.0.0 – in Arbeit

Erste Fassung für REDAXO 6 (`redaxo/core` 6.x, YForm 6).

### Für Redakteure

- Bearbeiten auf der Website: Texte direkt auf der Seite, Seitenleiste je Block, Blöcke einfügen, verschieben (Ziehen
  oder Pfeile), verdoppeln, kopieren und einfügen, online/offline; Greifer für Spalten, Abstände, Textbreite und Aufteilung.
- Arbeitsversion: Entwurf speichern, veröffentlichen, verwerfen; Versionen wiederherstellen; Entwürfe-Übersicht und Aktivität.
  Beim Verlassen mit unveröffentlichtem Entwurf fragt fluid nach.
- Bilder in festen Formaten (16:9, 4:3 …) mit dem AddOn image_editor:
  - Ausschnitt je Format, wie in KLXM Studio: Knopf „Ausschnitt“ am Bild.
  - Ausgabe genau im Format (eigener Ausschnitt oder um den Fokuspunkt).
- Bildknöpfe erscheinen auch bei Karten, deren Link über dem Bild liegt.
- MCP-Server für KI-Werkzeuge (`/fluid-mcp`, Backend → fluid → MCP, standardmäßig aus):
  - Zugangsschlüssel je Benutzer mit „nur lesen“ oder „lesen und ändern“, optional veröffentlichen.
  - Datentabellen werden einzeln freigegeben.
  - Optional öffentlicher Lesezugang; welche Inhalte, legt der Admin fest.
  - Änderungen landen im Entwurf, mit Versionen und Aktivität.
- Versionen wie in KLXM Studio:
  - Zeitleiste nach Tagen mit Uhr und Kalenderblatt; je Stand, was sich an den Blöcken geändert hat.
  - Vorschau des Stands als echte Seite (Desktop/Mobil), „Änderungen hervorheben“.
  - „Gegenüberstellen“ mit der Live-Fassung, gemeinsames Scrollen.
  - „Wiederherstellen“ in den Entwurf; Tastatur ↑/↓/Pos1/Ende/Esc.
- Wer arbeitet gerade wo (aus KLXM Studio):
  - Die Leiste zeigt, wer die Seite gerade bearbeitet, sonst wer zuletzt gespeichert hat.
  - Hat jemand anderes seit dem Öffnen gespeichert: ⚠ in der Leiste und Rückfrage vor dem Speichern. Es gibt keine
    Sperre, der vorige Stand bleibt als Version.
  - Kürzel im Seitenbaum, „Gerade aktiv“ unter Verlauf → Aktivität.
- Datenliste, Beiträge und Suche: Blättern, Schlagworte und Treffer beim Tippen ohne Seitenwechsel. Adresse,
  Zurück-Taste und Screenreader-Ansage gehen mit, ohne JavaScript bleibt alles wie bisher.
- Beiträge „Aufmacher + Karten“: Der Link des Aufmachers verdeckte die Schlagwort-Filter darüber.
- Suche zeigt nur Öffentliches:
  - Empfänger, Betreff und Bestätigungstexte von Formularen kommen nicht mehr in den Index, ebenso Platzhalter und
    Filterwerte.
  - Blöcke in einem Offline-Layout fehlen.
  - Der Index wird auch nach Änderungen im Backend und ohne Arbeitsversion neu aufgebaut.
  - Jeder Treffer wird vor der Ausgabe geprüft (Seite online, Sprache online, nicht „nicht indexieren“).
- Seiten: Seitenbaum (Ziehen, ↑ ↓ ← →, auch am Handy), neue Seiten leer, als Kopie oder aus einer Vorlage, Seiteneinstellungen
  mit Adresse und SEO-Angaben.
- Website: Stammdaten, Kontakt und Öffnungszeiten, Kopf, Fuß und Hinweisleiste, Rechtliches, Sprachen, Domains,
  Weiterleitungen; Website-Texte je Sprache.
- Mehrsprachigkeit: Sprachwahl in der Leiste, „Inhalt aus … übernehmen“, Sprachwahl für Besucher, Kit-Texte übersetzt (EN).
- Videos mit automatischem Vorschaubild (ffmpeg), Untertiteln und Kapiteln (.vtt/.srt) und Transkript; YouTube/Vimeo
  mit Zwei-Klick-Lösung.
- Blog / News: Menüseite als Blog, Beiträge mit Datum, Autor, Schlagworten; Block „Beiträge“ (Aufmacher, Karten, Liste,
  Blättern, Schlagworte), Beitragskopf und -navigation, RSS-Feed, „+ Neuer Beitrag“, Beiträge im Seitenbaum eingeklappt.
- Blöcke von YForm-Einträgen direkt auf der Website bearbeiten (Detailansicht der Datenliste, sofort gespeichert); Demo „Projekte“
  (Fallstudien aus Blöcken, `ProjectTable`).
- YForm-Feld „Fluid-Blöcke“ (`FluidBlocks`): Inhalt aus Blöcken in jeder YForm-Tabelle, Bearbeiten in der Maske, Ausgabe in der
  Detailansicht der Datenliste bzw. mit `BlockData::render()`; Vorlage `NewsTable` mit Blöcken.
- Formular-Baukasten auf der Website: Felder, Schritte (mehrstufig), speichern und/oder per E-Mail, Bestätigung an den Absender;
  YForm-FormDefinition aus dem Block, Eingänge in der YForm-Tabelle „Formular-Eingänge“ und im Postfach.
- Datenliste: Blättern, schöne Adressen für Einträge (/seite/titel-ID) mit eigenem Titel und Canonical, Sitemap; Vorlage `NewsTable`.
- Formulare, Datenlisten und Anfragen-Postfach mit YForm 6; Karten ohne Einwilligung (eigener Proxy); Suche mit Loupe.
- Erste Schritte: leere Installation → Startseite oder Beispielseiten mit einem Klick.

### Installation

- `install-dev.sh`: REDAXO 6, YForm 6, fluid (und image_editor) mit einem Befehl – mit kompletter Demo oder leer;
  prüft PHP, Erweiterungen und Datenbank, lädt Composer 2.9+ bei Bedarf, richtet Admin, `.env.local` und Webserver ein.
- `fluid:demo --media --design=redaxo`; README für Einsteiger, Entwickler-Referenz in `docs/entwickler.md`.

### Für Entwickler

- Blöcke als Modulklassen (`FluidBlock`) mit Feldschema und View; Kit fluid mit Template, Blöcken und Design-Editor.
- Eigenes Routing ohne yrewrite: gespeicherte Adressen, automatische 301-Weiterleitungen, Multidomain mit Landing-Domains,
  sitemap.xml, robots.txt, Canonical, hreflang, schema.org-@graph.
- Rechte des Cores (Struktur, Module, Optionen der Rolle) auf der Website.
- Blog-API (`Blog::posts()`, `Post`), Feldtyp `blog`, EP `FLUID_BLOG_FEED`; Demo mit Blog.
- Extension Points: `FLUID_BLOCK_DEFINITION`, `FLUID_BLOCK_VIEW`, `FLUID_SETTINGS_SCHEMA`, `FLUID_SEARCH_DOCUMENT`,
  `FLUID_STRUCTURED_DATA`, `FLUID_ROBOTS`, `FLUID_EDITOR_CONFIG`, `FLUID_SITE_TRANSLATIONS`.
- Media-Manager-Typ `fluid_poster`; Feldoption `types` für Dateifelder; Konsole `fluid:demo`, `fluid:search-index`.
- Backend: AddOns → fluid (Status, Wartung, Hilfe).
- `Live` (aus KLXM Studio `Core\Live`): Kanäle mit Versionen, signierte Abos, SSE-Stream `/fluid-live` mit Rückfall auf
  Nachfragen. „Neu laden auf Aktion“ mit `'reload' => true`, `Live::region($b)`, `data-live-nav`/`data-live-input`.
- Präsenz: `Presence` (Speicher, unverändert aus Studio), `PresenceApi` (`/fluid-presence`), `assets/editor/presence.js`.
  Ereignisse `cms:before-save` (vor schreibenden Aktionen des Editors) und `cms:presence-scan`. Konsole `fluid:selftest`.

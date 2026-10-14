# Changelog

## 1.0.0 – in Arbeit

Erste Fassung für REDAXO 6 (`redaxo/core` 6.x, YForm 6).

### Für Redakteure

- Bearbeiten auf der Website: Texte direkt auf der Seite, Seitenleiste je Block, Blöcke einfügen, verschieben (Ziehen
  oder Pfeile), verdoppeln, kopieren und einfügen, online/offline; Greifer für Spalten, Abstände, Textbreite und Aufteilung.
- Arbeitsversion: Entwurf speichern, veröffentlichen, verwerfen; Versionen wiederherstellen; Entwürfe-Übersicht und Aktivität.
  Beim Verlassen mit unveröffentlichtem Entwurf fragt fluid nach.
- Versionen wie in KLXM Studio:
  - Zeitleiste nach Tagen mit Uhr und Kalenderblatt; je Stand, was sich an den Blöcken geändert hat.
  - Vorschau des Stands als echte Seite (Desktop/Mobil), „Änderungen hervorheben“.
  - „Gegenüberstellen“ mit der Live-Fassung, gemeinsames Scrollen.
  - „Wiederherstellen“ in den Entwurf; Tastatur ↑/↓/Pos1/Ende/Esc.
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

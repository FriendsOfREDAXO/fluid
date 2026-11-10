# Changelog

## 1.0.0 – in Arbeit

Erste Fassung für REDAXO 6 (`redaxo/core` 6.x, YForm 6).

### Diagramme

- Neuer Block „Diagramm“ (`fluid.chart`): Balken, waagerechte Balken, Linie, Fläche, Torte, Ring – Werte von Hand
  (Reihen als `Collection`) oder aus einer Datenquelle (`#[AsChartSource]`), Tabelle der Werte für Screenreader und
  ohne JavaScript, Darstellungen „standard“ und „split“.
- `Chart::render()` für Templates, Module und AddOns; Engine austauschbar (`#[AsChartEngine]`, `ChartConfig`),
  Standard Apache ECharts, selbst gehostet, lädt erst in Sichtweite (aus KLXM Studio – `Core\Charts`).
- Demo: Seite „Diagramme“ im Bereich fluid, Quelle „Blöcke je Art“ des Kits zählt live aus der Datenbank.

### Versionen: Live-Schaltung = Stand, Entwurf = Zwischenstände

- Jede Live-Schaltung legt den Stand an, der live geht („Veröffentlicht“, Archiv). Vorher ein Stand der alten
  Live-Version nur, wenn sie noch nicht im Archiv liegt; unverändert erneut veröffentlicht → kein neuer Stand.
- Zwischenstände der Arbeitsversion (Speichern, vor Verwerfen/Wiederherstellen) werden bei der Live-Schaltung geleert –
  sie waren nie online. Seitenleiste und Zeitleiste zeigen Entwurf und Archiv getrennt.
- Stände in derselben Sekunde liefen unter einem Zeitpunkt zusammen (Wiederherstellen hätte Blöcke doppelt eingefügt) –
  fluid weicht jetzt auf die nächste freie Sekunde aus.

### Bearbeitungssperre

- Optional (Website → Einstellungen → Redaktion, Standard aus): Eine Seite bearbeitet immer nur eine Person – wer sie
  zuerst im Bearbeiten-Modus hat. Alle anderen sehen sie nur an (Hinweis unter der Leiste, „Jetzt bearbeiten“, sobald
  sie frei ist); Speichern über Editor, Seitenleiste und MCP lehnt fluid mit 423 ab. Administratoren übernehmen, die
  bisherige Person erfährt es sofort. Abgeleitet aus der Präsenz, ohne Tabelle und ohne hängende Sperren (aus KLXM
  Studio, `EditLock`). Selbsttest in `fluid:selftest`.
- Präsenz: Die Live-Aktualisierung von Seitenbaum und „Gerade aktiv“ lief ins Leere (Funktion `lists()` durch eine
  Konstante verdeckt) – behoben. Der Bearbeiten-Modus wurde am Parameter `?edit` statt `?fluid_edit` erkannt.

### Blöcke wie Module des Cores

- **Ein Block ist ein Modul des Cores mit anderer Basisklasse:** Felder als Attribute des Cores, Ausgabe in `output()`,
  der Core befüllt die Eigenschaften (typisiert: `?Media`, `DateTimeImmutable`, Objekte einer `Collection`). Vom Modul
  zum Block: `FluidBlock` statt `Module`, Schlüssel `fluid.…`, `#[AsBlock]` – Felder und `output()` bleiben.
  `$this->edit('feld')` für das Bearbeiten auf der Seite. Ohne eigenes `output()` die View des Kits (`$b`, `$d`, `$m`).
- **Felder:** die des Cores, oder die gleichnamigen von fluid (`FriendsOfRedaxo\Fluid\Field\…`) als Unterklassen mit
  zusätzlichen, optionalen Parametern: `width`, `variants`, `placeholder`, `search`, bei `Collection` `itemLabel` und
  `titleField`. Felder nur von fluid: `RichText`, `Link`, `Icon`, `Geo`, `MediaCategory`, `Blog`, `YFormTable`,
  `YFormForm`, `YFormField` – auch in gewöhnlichen Modulen nutzbar. Neu in der Seitenleiste: `Email`,
  `DateTimePicker`, `ArticlePicker`, Zahl mit `min`/`max`/`step`, Auswahl mit Platzhalter und Gruppen.
- **`Repeater` → `Collection` des Cores** (Eintrag = Klasse mit eigenen Feldern); `Media`/`File` → `MediaPicker`
  (`preview: true` = Bildfeld). Die 17 Wiederholgruppen der Blöcke haben Eintragsklassen in `Kit\Fluid\Module\Item`.
- **Speicherung wie beim Core:** je Feld unter seinem Schlüssel in `data`, dazu `variant` und `tunes` (vorher
  `{values, tunes}`). `console fluid:upgrade:slices` stellt vorhandene Slices samt Historie um; gelesen wird das alte
  Format weiterhin. Verweise (Medien, Seiten) kommen jetzt von den Feldern des Cores bzw. `Link`/`RichText`.
- Formular im Backend benennt die Eingaben wie der Core nach Eigenschaft (`slice[moreLink]`) – vorher hätte ein
  Speichern im Backend Felder mit zusammengesetzten Namen verloren.
- **Gewöhnliche Module** zeigt die Seitenleiste mit derselben Abbildung – jetzt auch mit `Collection` und den Feldern
  von fluid; gespeichert und geprüft vom Formular des Cores.
- Entfernt: `view(Block $b, array $d)` und `{Klasse}.view.php` (→ `output()`), `Field\Field`, `Field\Repeater`,
  `Field\Media`, `Field\File`, `Form\BlockValues` (→ internes `Form\Stored`), Felder anhängen über das Kit
  (Felder gehören als Eigenschaften in die Klasse).

### Adressen (Rewriter)

- **Für alle AddOns:** Einträge eigener Quellen (News, Termine, Produkte …) bekommen eine Adresse unter ihrer Seite –
  aus einer Tabelle nur mit Attributen (`#[AsRouteSource]`, `#[FromTable]`, `#[Slug]`, `#[Title]`, `#[Description]`,
  `#[Image]`, `#[Modified]`, Klasse `TableSource`) oder als eigene `RouteSource`. Gespeichert, eindeutig gegen Seiten,
  301 nach einer Änderung, in der Sitemap mit hreflang und Bildern, mit Canonical, hreflang und Open Graph im Kopf.
  `Router::urlFor()`, `Routes::rebuildSource()`, Konsole `routing:rebuild`.
- **Adressformat** im Projekt: ohne Endung, „/“, „.html“, gemischt (Menüpunkte „/“, Seiten „.html“) oder eigene Endung
  (`Router::$config = new RoutingConfig(format: UrlFormat::mixed())`). Jede andere Schreibweise leitet per 301 um – eine
  kanonische Adresse je Seite, an einer Stelle geprüft (Groß-/Kleinschreibung, Schrägstrich, Endung, Sprachpräfix).
- **Seitenkopf** für Seiten und Einträge gleich (`Routing\Seo\Head`): neu `og:url`, `og:type`, `og:locale` (+ alternate),
  `x-default`, `article:*_time`, `twitter:card` (nur dieses – den Rest liest X aus Open Graph), Bildmaße aus dem
  Medienpool. Eigene Tags über `HeadConfig::$tags`, `Head::add()` oder `PageMetaResolved` (gleicher Schlüssel ersetzt).
- Felder eines Eintrags (Titel, Beschreibung, Bild, Adresse) in den **Seiteneinstellungen der Detailseite** bearbeitbar;
  YForm-Tabellen über die Record-API.
- Detailseiten der Datenlisten: Titel, Beschreibung, Bild, Canonical und hreflang kommen jetzt vom Resolver (unabhängig
  von der Reihenfolge beim Rendern), Brotkrumen und JSON-LD mit der Adresse des Eintrags. Mit Endung `.html` entstand
  vorher `/daten.html/titel-5`.
- Cache: Adressen der Seiten und jeder Quelle als PHP-Datei (OPcache) statt einer Abfrage aller Adressen je Anfrage.
- REDAXO-6-Stil: Auflösen bei `PACKAGES_INCLUDED` (Quellen und Endpunkte von AddOns, die nach fluid starten, gelten
  jetzt auch), Erweiterungen per `#[AsExtension]`, eigene Adressen per `#[AsEndpoint]`, Extension Points als Klassen
  (`ArticleMeta`, `PageMetaResolved`, `SitemapEntries`, `RobotsTxt`, `RoutesRebuilt` statt `FLUID_SEO_HEAD`,
  `FLUID_SITEMAP`, `FLUID_ROBOTS`, `FLUID_ROBOTS_RULES`, `FLUID_ROUTES_REBUILT`). `Router::endpoint()` ohne `afterPackages`,
  Resolver geben `RouteMatch` statt einer Seiten-ID zurück.
- `RoutingConfig(enabled: false)` schaltet den Rewriter ab (z. B. für yrewrite). `src/Routing/README.md`: Herauslösen als
  eigenes AddOn und Abbildung auf yrewrite + URL-AddOn.
- Demo: Seite „Adressen“ im Kapitel fluid.

### Für Redakteure

- Datensätze aus YForm auf der Website bearbeiten:
  - „Eintrag bearbeiten“ und „+ Neuer Eintrag“ öffnen die Maske von YForm im Overlay statt in einem Popup-Fenster.
    „Speichern“ schließt es und gibt den Block neu aus, „Übernehmen“ bleibt in der Maske.
  - In der Detailansicht lassen sich Titel und Felder direkt auf der Seite ändern: Text, Internetadresse, E-Mail, Datum,
    Uhrzeit, Zahl, Auswahl, Ja/Nein, Bild (Medienpool) und Seitenlink (Linkmap). Gespeichert wird sofort, mit den Prüfungen
    von YForm; Esc nimmt eine Änderung zurück. Ein geänderter Titel ändert die Adresse der Detailseite.
  - Auf einer Detailseite bleiben „Bearbeiten“, „Ansehen“ und „Arbeitsversion“ in der Leiste beim Eintrag (vorher Sprung
    zur Liste); Blöcke werden mit dem Eintrag neu ausgegeben.
  - Der Knopf „Eintrag bearbeiten“ sitzt an Karten unten – die Bildwerkzeuge verdeckten ihn.
  - Weiterleitungen wegen eines geänderten Titels speichert der Browser nicht mehr (ein zurückbenannter Eintrag führte
    sonst in eine Schleife).
- Seitenbaum: „Nicht im Menü zeigen“ blendet einen Menüpunkt nur aus dem Menü aus – die Seite bleibt online, an ihrer
  Stelle, mit ihren Unterseiten und in der Sitemap (wie in KLXM Studio). Gespeichert im Metafeld „Nicht in der
  Navigation zeigen“ von navigation_array (auch im Backend unter Kategorie bearbeiten). Vorher wurde der Menüpunkt zur
  Seite ohne Menüeintrag umgewandelt und sprang dabei in der Reihenfolge; mit Unterseiten ging es gar nicht.
  Im Baum: ☰ schräg durchgestrichen und „nicht im Menü“ in Warnfarbe.
- Seitenbaum: Status farbig – online grün ●, offline grau ○, gesperrt rot ● (weiterer Status eines AddOns, mit dessen
  Bezeichnung). Nur online steht in Menü und Sitemap.
- Menüs der Seite (Kopf, Menü-Blatt, Fuß) passen sich sofort an, wenn im Seitenbaum etwas online/offline geschaltet,
  aus dem Menü genommen, verschoben oder gelöscht wird – ohne Neuladen.
- Gewöhnliche REDAXO-Module (ohne fluid-Felder) lassen sich auf der Website pflegen: Im Bearbeiten-Modus zeigt ihre
  Ausgabe „… bearbeiten“, die Seitenleiste zeigt ihre Felder im Formular von fluid (Text, Textbereich, E-Mail, URL,
  Zahl, Ja/Nein, Auswahl, Datum, Uhrzeit, Bild, Seitenlink – mit Medienpool und Linkmap der Website), „Übernehmen“
  prüft wie der Core (Pflichtfelder, Länge, gültige Werte, `validate()` des Moduls), speichert und erneuert die Ausgabe
  auf der Seite. Module mit eigenem `input()` oder anderen Feldtypen zeigen das Formular des Cores in der Seitenleiste. Das Template „fluid“ erlaubt dafür neben den Blöcken des Kits auch gewöhnliche
  Module (vorher lehnte der Core das Speichern solcher Slices ab – auch im Backend).
- YForm-Einträge mit Sprache (Feld `FluidLanguage`): Datenliste, Detailansicht und Sitemap zeigen nur Einträge der
  Seitensprache und solche ohne Sprache.
- Formulare aus YForm übernehmen die Breiten des Layouts (Designer oder `layout()`) auch auf der Website.
- Formular-Baukasten: Felder Uhrzeit, Datum und Uhrzeit, Kommazahl.
- Block „Code“ für Codebeispiele (eine oder mehrere Dateien, mit Kopier-Knopf); `<code>` im Rich Text.
- Menü ⋯ → „Abmelden“: meldet aus REDAXO ab, die Seite bleibt als Besucher offen.
- Menü-Blatt (Off-Canvas) mit Unterseiten zum Aufklappen, wie in KLXM Studio (vorher nur die erste Ebene).
- Kopf: Der aktive Eintrag im Untermenü ist auf farbigem Kopf lesbar; die Kopfzeile misst nach dem Laden der Webschriften
  neu (Menü und Menü-Knopf wechselten sonst zufällig).
- Die Knöpfe der Hinweis-Leiste wirken auch in der Ansicht (z. B. „Zurücksetzen“ und „Demo beenden“ von `fluid_demo`).
- Block-Formulare im Backend im Stil von REDAXO 6: Felder, Auswahllisten, Checkboxen und Knöpfe des Cores,
  Wiederholgruppen wie die Sammelfelder des Cores (Kopfzeile mit Titel und Knopfgruppe, aufklappbar), Farben passend
  zum hellen und dunklen Theme.
- Medien und Seiten auswählen ohne Popup-Fenster: im Backend Medienpool und Linkmap des Cores als Overlay, auf der
  Website eine eigene Auswahl im gleichen Aufbau – Medien mit Kategorien, Suche und Hochladen, Seiten als Linkmap
  (Kategorien links, Artikel rechts). Es gelten die Rechte von Medienpool und Struktur.
- Teilen in sozialen Netzwerken: Das Vorschaubild hat jetzt das Format der Netzwerke (1200 × 630, um den Fokuspunkt,
  JPEG – LinkedIn zeigt mit WebP nichts), mit Alt-Text. Neue Option (Standard aus): Linkvorschau beim Teilen erlauben,
  auch wenn der robots.txt-Text Suchmaschinen aussperrt – etwa vor dem Start einer Live-Website.
- Sitemap: Bilder je Seite, Änderungszeit mit Uhrzeit, im Browser als Tabelle lesbar (`/sitemap.xml`).
- Schnellerer Seitenaufbau: Das erste Bild im ersten Block lädt sofort und mit Vorrang.
- Blockauswahl wie in KLXM Studio: oben „Häufig verwendet“ (die meistgenutzten Blöcke der Website), darunter alle
  nach Bereich in fester Reihenfolge; beim Suchen verschwinden leere Bereiche.
- Seitenleiste: Schalter „Auf der Website sichtbar“ ganz oben, mit deutlichem Hinweis, wenn der Block ausgeblendet ist –
  schaltet sofort, ungespeicherte Eingaben im Formular bleiben.
- Redaktionsnotizen `[# … #]`: Klick im Bearbeiten-Modus → „Als Text übernehmen“ oder „Notiz löschen“.
- Umschalt+Enter im Rich-Text: Zeilenumbruch statt neuem Absatz (auch in Safari).
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
- Datenliste, Beiträge und Suche: Blättern, Schlagworte und Treffer beim Tippen (nach einer kurzen Pause, ab 3 Zeichen) ohne Seitenwechsel. Adresse,
  Zurück-Taste und Screenreader-Ansage gehen mit, ohne JavaScript bleibt alles wie bisher.
- Beiträge „Aufmacher + Karten“: Der Link des Aufmachers verdeckte die Schlagwort-Filter darüber.
- Kopf: Die Suchseite erscheint nicht mehr zusätzlich als Menüpunkt, wenn die Lupe im Kopf zu ihr führt.
- Schrift Exo (selbst gehostet, variabel, OFL) für Überschriften; die Design-Vorlage „REDAXO · Blau“ nutzt sie wie redaxo.org.
- Design: Symbole „Dünner Rahmen, zweifarbig“.
- Sprachwahl im Kopf: ab drei Sprachen ein Menü (aktuelle Sprache, Liste mit Namen; Esc und Klick daneben schließen).
  Im Handy-Menü und im Fuß bricht die Liste bei vielen Sprachen um, statt über den Rand zu laufen.
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

- Demo „REDAXO 6“ (`fluid:demo --set=redaxo6`): Bereich „REDAXO 6“ (Was sich ändert, Technik, Backend, Projekt anlegen,
  Module und Templates, Native Module, YForm, Navigation) und Bereich „fluid“ (Bearbeiten, Bausteine, fluid-Projekt, Eigenes Kit), mit
  Codebeispielen und Screenshots; englische Fassung aller Seiten und Datensätze mit `--lang=en`
  (Texte in `demo-redaxo6.en.php`). Auf der Startseite der Trailer, wenn die Datei im Medienpool liegt.
- `fluid:demo`:
  - `--page=…` hängt einzelne Seiten an und verschiebt vorhandene an ihre Stelle, `--update` befüllt sie neu.
  - `--media-dir=…` übernimmt weitere Dateien in den Medienpool (z. B. einen Trailer, der nicht zum Kit gehört);
    `--media` und `--media-dir` allein scheitern auf einer Website mit Seiten nicht mehr.
  - Gibt es eine Menüseite der Demo nur noch als Seite ohne Menüeintrag, macht `--page=…` sie wieder zum Menüpunkt
    (Inhalte und Adresse bleiben), statt eine zweite anzulegen.
  - Mit `--page=start` wird die vorhandene Startseite neu befüllt; vorher entstand ein zweiter Startartikel, der
    Start- und 404-Seite wurde.
  - Blöcke gewöhnlicher Module (`'native' => 'modul', 'values' => […]`), nur wenn das Modul im Projekt liegt –
    Beispiele in `examples/module` (Seite „Native Module“).
  - Website-Einstellungen, die auf gelöschte Seiten zeigen (z. B. die Suchseite), werden beim Import neu gesetzt.
- Abhängigkeit `friendsofredaxo/navigation_array:6.x-dev` (per VCS, in der Anleitung und in `install-dev.sh`).
- `install-dev.sh`: REDAXO 6, YForm 6, fluid (und image_editor) mit einem Befehl – mit kompletter Demo oder leer;
  prüft PHP, Erweiterungen und Datenbank, lädt Composer 2.9+ bei Bedarf, richtet Admin, `.env.local` und Webserver ein.
- `fluid:demo --media --design=redaxo`; README für Einsteiger, Entwickler-Referenz in `docs/entwickler.md`.
- Angepasst an REDAXO-Core `cf598c1` (Oktober 2026):
  - Statt `uninstall()` meldet fluid seine Tabellen über `getOwnedSchema()`. Nach `composer remove` entfernt
    `console migrate:cleanup` sie samt Einstellungen und Cache.
  - `install-dev.sh` nutzt `console setup` ohne die entfallenen Optionen `--agree-license` und `--db-setup`.

### Für Entwickler

- **Felder der Blöcke als Attribute** – wie bei den Modulen des Cores. An der Klasse `#[AsBlock(icon:, group:, variants:,
  nestable: …)]`, an den Eigenschaften die Felder aus `FriendsOfRedaxo\Fluid\Field`: `Text`, `Textarea`, `Choice`,
  `Checkbox`, `Number`, `DatePicker`, `TimePicker`, `Url` (Namen und Parameter wie im Core: `label`, `note`, `required`,
  `key`, `maxLength` …) sowie `RichText`, `Link`, `Media`, `File`, `Repeater`, `Icon`, `Geo`, `MediaCategory`, `Blog`,
  `YFormTable`, `YFormForm`, `YFormField`; dazu `width: 'half'` und `variants:`. Schlüssel = Eigenschaft in snake_case,
  Standardwert = Vorgabewert der Eigenschaft. Gespeichert wird unverändert unter `values` – vorhandene Inhalte bleiben.
  - Die 24 Blöcke des Kits fluid stehen so in `kits/fluid/src/Module/` (vorher als Arrays in `kits/fluid/kit.php`);
    die Definitionen sind unverändert (maschinell umgestellt und verglichen).
  - **Ausgabe in der Klasse:** `protected static function view(Block $b, array $d): string` – wie `output()` bei
    gewöhnlichen Modulen. Sonst `{Klasse}.view.php` daneben, dann die View des Kits.
  - **Entfällt:** `define()` und die Felder in `kit.php`. `'blocks'` in `kit.php` ergänzt jetzt die Definition der
    Blockklasse (Varianten und `variant_help` kommen hinzu, `fields` werden angehängt, anderes ersetzt) – statt sie
    komplett zu ersetzen. `Kit::hasBlock()` und `Maps::blockFields()` entfallen.
  - Beispiel `examples/block/Hinweis.php`: das Beispiel-Modul „Hinweis“ als Block in einer Datei; die Seite „Native
    Module“ der Demo zeigt die Umstellung Schritt für Schritt.
  - Demo: Auf der Seite „Native Module“ stand unter „Derselbe Hinweis als fluid-Block“ der Code des Teasers (doppelter
    Schlüssel im Demo-Inhalt).

- Menü über [navigation_array](https://github.com/FriendsOfREDAXO/navigation_array) (Branch 6.x) statt einer eigenen
  Klasse; fluid meldet über den Extension Point `NavigationStart` den Seitenbaum einer Landing-Domain als Start.
- Blöcke speichern über `ContentHandler::editSlice()` des Cores (#6800) – im Editor und im MCP-Server.
- `fluid:upgrade:slices` macht `ctype_id` optional, wenn `migrate` `content_section_id` schon angelegt hat (sonst
  scheiterte jeder neue Block, bis `--drop-old` lief).
- `Data\RecordInline` und die Editor-Aktion `record_field`: ein Feld eines Datensatzes über `fromInput()` und die
  Record-API von YForm speichern. Die Maske im Overlay ist eine Seite des Cores im Popup-Modus (Parameter `fluid_frame`
  bzw. `Sec-Fetch-Dest: iframe`, `assets/frame.css`).
- Formular-Theme des Kits: Spaltenklassen `dff-c dff-c--{1–12}` für die Breiten des YForm-Layouts.
- `FluidBlocks` nutzt `filterable` (die dynamische Eigenschaft `searchable` gibt es in YForm 6 nicht, in PHP 8.5
  deprecated); Beispieltabellen mit filterbaren Feldern, damit YForm eine Suche zeigt.
- Seite „MCP“ im Backend mit Komponenten des Cores (ohne Bootstrap; Widerrufen fragt wieder nach – `data-confirm`
  statt `onsubmit`, das die CSP blockierte).
- Abmelden über die Editor-Aktion `logout` (`BackendLogin`): das CSRF-Token für `rex_logout` aus dem Frontend gilt im
  Backend nicht.
- Entwickler-Referenz: Ein Kit als Composer-Paket muss ein AddOn sein (`redaxo-addon` mit AddOn-Klasse).
- Beispiele `examples/yform`: `TerminTable` und `AnfrageForm` mit `layout()` im Code.
- FrontendPicker (`Picker\FrontendPicker`, `assets/picker`): Medien- und Seitenauswahl für die Website, für jedes AddOn
  nutzbar – `FrontendPicker::head()` einbinden, im Browser `FrontendPicker.pick('media' | 'article', {…})` mit derselben
  Antwort wie `rex.pick()` im Backend. Eigenständig angelegt, damit daraus später ein eigenes AddOn werden kann.
- Blöcke als Modulklassen (`FluidBlock`) mit Feldschema und View; Kit fluid mit Template, Blöcken und Design-Editor.
- Eigene Kits (Projekt oder Composer-Paket) mit `#[AsKit]`:
  - Jedes Kit erbt vom Kit fluid (oder einem anderen) und ersetzt nur, was es mitbringt: `kit.php`-Einträge, Views,
    `template.view.php`, Assets, `functions.php`. Alle Blöcke sind damit immer vorhanden.
  - Auswahl unter Website → Kit (ab zwei Kits), per EP `FLUID_KIT` auch je Domain. Die Block-Module gehören zu keinem
    Kit, Inhalte bleiben beim Wechsel.
  - Design-Werte je Kit; ein abgeleitetes Kit ohne eigene übernimmt die des Eltern-Kits.
  - Assets nach `assets/fluid-kits/{kit}/` (bei `migrate` und bei Bedarf je Datei).
  - `bin/console fluid:kit:create name --view=hero,cards --template` legt ein Kit im Projekt an.
- E-Mail (Baustein `src/Mail/`):
  - Statusseite → „E-Mail“: Versand, Absender, Umleitung, Protokoll, Archiv, Testmail, letzte Fehler, nicht gesendete
    Mails mit „Erneut senden“ (Bcc-Kopfzeile wird entfernt).
  - Formular-Mails als HTML im Stil der Website (Logo, Akzentfarbe, Anschrift) mit sauberem Textteil.
  - Cronjob `fluid_mail_archive_purge` und Einstellung „Gesendete Mails aufbewahren (Tage)“ (Standard 90).
- Formulare: Ein Mailfehler verwirft die Eingabe nicht mehr. Ist der Eintrag gespeichert, wird der Fehler protokolliert
  (Systemprotokoll, Aktivität „Formular-Mail nicht gesendet“), die Bestätigung wird trotzdem versucht, und Besucher sehen
  die Erfolgsmeldung – mit Hinweis, falls die Bestätigung nicht rausging. Vorher: „Fehler – bitte noch einmal“ trotz
  gespeichertem Eintrag (doppelte Einträge beim erneuten Absenden).
- Angepasst an REDAXO-Core bis `af70a14` (Module je Slice, Felder als JSON in `data`):
  - Blöcke speichern ihre Felder unter `values`, die Abschnitts-Optionen unter `tunes` in der Spalte `data` des Slices
    (statt `value1`/`value2`); im Backend über das Formular des Cores (Feldtyp `Form\BlockValues`).
  - Was den Blocktyp betrifft, ist statisch: `define()`, `kit()`, `fields()`, `definition()`, neu `label()`;
    `BlockModule::of($slice)` liefert das Modul eines Slices, `BlockModule::classFor($key)` und `all()` die Klassen.
    **Eigene Blöcke im Projekt:** `protected static function define()` bzw. `public static function kit()`.
  - Bestehende Datenbanken: `php bin/console fluid:upgrade:slices` (vor oder nach `migrate`) übernimmt die Blöcke und
    benennt `ctype_id` in `content_section_id` um; `--drop-old` entfernt danach die alten Spalten.
  - Löschschutz über die Verweistabelle des Cores (`rex_article_slice_reference`): Der Feldtyp der Blöcke meldet Seiten
    (`redaxo://ID`, auch im Rich-Text) und Medien aus dem Medienpool; „Verwendet in“ im Medienpool und in der Struktur.
    `fluid:upgrade:slices` baut die Verweise mit auf (sonst `bin/console content:rebuild-references`).
  - Extension Points als Klassen: `ArticleContentRendering`, `ArticleSlicesQuery`, `SliceOutput`, `SliceDeleting`,
    `ArticleUrl`, `ContentPageBeforeSlices`; Handler mit einheitlichem `$data` (Kategorien, Sprachen, Medien als `File`).
  - Neue Blöcke stehen sicher an ihrer Stelle, auch wenn mehrere in derselben Sekunde entstehen (vorher konnte ein
    Spalten-Block hinter einem fremden Block landen). Die Zwischenablage nutzt `values`/`tunes`; ältere Einträge
    lassen sich weiter einfügen.
- Angepasst an REDAXO-Core bis `fb11e8a`: Handler-Methoden umbenannt (`ArticleHandler::add()`, `changeStatus()`,
  `convertToCategory()`, `CategoryHandler::convertToArticle()` …) und deren Rückgabewerte genutzt (neue IDs ohne
  `MAX(id)`), Struktur-Extension-Points als Klassen (`ArticleAdded`, `CategoryMoved` …); Demo legt Seiten mit dem
  Template „fluid“ an (nicht mit dem Schlüssel des Kits).
- Angepasst an REDAXO-Core bis `c9b0bf1`: `Redaxo\Core\Console\AbstractCommand`, `Redaxo\Core\MediaManager\AsMediaType`,
  `Redaxo\Core\Util\Markdown\Markdown`; Handler des Cores werfen statt `false` zu liefern (Seitenbaum: Menü und Verschieben),
  ihre `UserMessageException` erscheint im Editor und per MCP als Meldung statt als interner Fehler.
- Bausteine statt fester Verdrahtung (Vorbereitung für eigene AddOns, wie beim Bildeditor):
  - Routing kennt keinen anderen Teil von fluid mehr. Bausteine melden sich an: `Router::endpoint()`, `resolver()`,
    `onPage()`, `gate()`, `keep()`, `Sitemap::exclude()/children()`; Antworten über `Router::respond()`/`redirectTo()`.
  - MCP, Live, Präsenz, Kartenproxy, Untertitel, „Erste Schritte“, Detailseiten der Datenlisten und Blog-Feed melden
    sich selbst an (`boot()` je Baustein).
  - SEO im Seitenkopf aus dem Routing gelöst: `Seo\Head`, `Seo\StructuredData`; Blog (RSS, BlogPosting) und Karten (Geo)
    ergänzen über `FLUID_SEO_HEAD` bzw. `FLUID_STRUCTURED_DATA`.
  - Neue Extension Points: `FLUID_SITEMAP`, `FLUID_ROBOTS_RULES`, `FLUID_ROUTES_REBUILT`, `FLUID_SEO_HEAD`.
  - Entwickler-Referenz: Abschnitt „Bausteine“ mit Regeln, Stand je Baustein und Ablauf zum Herauslösen.
- sitemap.xml: `lastmod` mit dem echten Änderungsdatum (vorher stand bei jeder Seite das heutige Datum), hreflang
  `x-default` (Startsprache), Sitemap-Index ab 45 000 Adressen (`/sitemap-1.xml` …).
- Kit „NativeCSS“ (`kits/native/`) auf Basis von [NativeCSS](https://github.com/skerbis/nativecss) (MIT):
  - Seitenrahmen mit Topbar, Navigation als Off-Canvas (`dialog`, Untermenüs mit `details`) und Footer im Raster.
  - Eigene Views für Einstieg (split, centered, compact, type, fullbleed), Karten, Text + Bild, Handlungsaufruf (band, box)
    und FAQ; alle übrigen Blöcke und Varianten von fluid.
  - Cascade Layers: NativeCSS-Basis, darüber das Grund-CSS von fluid, darüber Komponenten und Brücke – Design-Leiste und
    Vorlagen gelten für beides. Eigene Vorlagen „NativeCSS“ und „NativeCSS · Graphit“ (WCAG AA hell und dunkel).
- Design: abgeleitete Kits geben ihre Design-Werte immer aus (auch Standardwerte), weil das Grund-CSS die von fluid enthält.
- `fluid:demo --design=… --kit=…`; mit vorhandenen Seiten setzt `--design` nur die Vorlage.
- Eigenes Routing ohne yrewrite: gespeicherte Adressen, automatische 301-Weiterleitungen, Multidomain mit Landing-Domains,
  sitemap.xml, robots.txt, Canonical, hreflang, schema.org-@graph.
- Rechte des Cores (Struktur, Module, Optionen der Rolle) auf der Website.
- Blog-API (`Blog::posts()`, `Post`), Feldtyp `blog`, EP `FLUID_BLOG_FEED`; Demo mit Blog.
- Extension Points: `FLUID_BLOCK_DEFINITION`, `FLUID_BLOCK_VIEW`, `FLUID_SETTINGS_SCHEMA`, `FLUID_SEARCH_DOCUMENT`,
  `FLUID_STRUCTURED_DATA`, `FLUID_ROBOTS`, `FLUID_EDITOR_CONFIG`, `FLUID_SITE_TRANSLATIONS`.
- Media-Manager-Typ `fluid_poster`; Feldoption `types` für Dateifelder; Konsole `fluid:demo`, `fluid:search-index`.
- Backend: AddOns → fluid (Status, Wartung, Hilfe).
- Gastzugang für AddOns (z. B. `fluid_demo`):
  - `FLUID_GUEST_USER` meldet Besucher ohne Anmeldung mit den Rechten eines Benutzers an, nie als Admin.
  - `FLUID_API_ACTION` übernimmt Aktionen des Editors. Gäste bedient fluid nie selbst, ohne AddOn gibt es 403.
  - `Editing::isGuest()`, `Editing::guestRevision()`, `Api::dispatch()`.
  - Editor: `cfg.scripts`, `cfg.notice`, Ereignisse `fluid:api-request` und `fluid:api-done`, `window.fluidEditorWait`,
    `window.fluidPickerOff`.
  - Für Gäste aus: Präsenz, Versionen-Vorschau, Bildeditor, Medienpool, Postfach, Design, Website-Einstellungen,
    Seitenbaum, Verlauf, Entwürfe in der Beitragsliste.
- `Live` (aus KLXM Studio `Core\Live`): Kanäle mit Versionen, signierte Abos, SSE-Stream `/fluid-live` mit Rückfall auf
  Nachfragen. „Neu laden auf Aktion“ mit `'reload' => true`, `Live::region($b)`, `data-live-nav`/`data-live-input`.
- Präsenz: `Presence` (Speicher, unverändert aus Studio), `PresenceApi` (`/fluid-presence`), `assets/editor/presence.js`.
  Ereignisse `cms:before-save` (vor schreibenden Aktionen des Editors) und `cms:presence-scan`. Konsole `fluid:selftest`.

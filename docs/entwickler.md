# fluid – Entwickler-Referenz

Technische Doku zu fluid für REDAXO 6. Einstieg und Installation: [README](../README.md).

## Inhalt

- [Ein eigener Block](#ein-eigener-block) · [Eigene Kits](#eigene-kits)
- [Extension Points](#extension-points)
- [YForm: Formulare und Datenlisten](#yform-formulare-und-datenlisten)
- [Blöcke in YForm-Tabellen](#blöcke-in-yform-tabellen-feld-fluid-blöcke)
- [Adressen, Domains, SEO](#adressen-domains-seo)
- [Sprachen](#sprachen) · [Videos](#videos) · [Blog](#blog) · [Karten](#karten)
- [Versionen](#versionen) · [MCP-Server](#mcp-server)
- [Suche](#suche) · [Neu laden auf Aktion](#neu-laden-auf-aktion) · [Live-Aktualisierung](#live-aktualisierung) · [Präsenz der Redaktion](#präsenz-der-redaktion)
- [Konsole](#konsole) · [Rechte](#rechte) · [Gastzugang](#gastzugang-für-addons) · [Aufbau des AddOns](#aufbau-des-addons) · [Mitentwickeln](#mitentwickeln)


## Ein eigener Block

Ein Block ist eine Modulklasse mit Feldschema und eine View. Im Projekt, ohne das Kit zu ändern:

```php
// src/Block/Teaser.php
namespace Project\Block;

use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.teaser', 'Teaser')]
final class Teaser extends FluidBlock
{
    protected function define(): array
    {
        return [
            'label' => 'Teaser', 'icon' => 'star', 'group' => 'Inhalt',
            'nestable' => true,   // darf in eine Layout-Spalte
            'fields' => [
                ['name' => 'title', 'label' => 'Überschrift', 'type' => 'text', 'required' => true, 'max' => 80],
                ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 3],
                ['name' => 'link', 'label' => 'Link', 'type' => 'link'],
            ],
        ];
    }
}
```

```php
<?php // src/Block/Teaser.view.php – $b = Block, $d = Daten ?>
<div class="wrap">
  <h2 class="h3"<?= $b->edit('title') ?>><?= e($d['title']) ?></h2>
  <p<?= $b->edit('text', 'lines') ?>><?= nl2br(e($d['text']), false) ?></p>
</div>
```

- `$b->edit('feld')` macht ein Feld direkt auf der Seite bearbeitbar (`'lines'` mehrzeilig, `'rich'` mit Formatierung).
- Daten liegen als JSON in `value1`, Abschnitts-Optionen (Hintergrund, Abstände, Spalte) in `value2` – echte Slices
  mit Rechten, Arbeitsversion und Historie des Cores.
- Vorlage: `examples/block/`.

**Feldtypen:** `text`, `textarea`, `richtext`, `select` (`options`), `bool`, `number`, `url`, `time`, `media`, `file`,
`link` (Linkmap), `mediacategory`, `repeater` (`fields`, `item_label`, `title_field`), `geo` (Koordinaten mit Adresssuche),
`yform_form`, `yform_table`, `yform_field`. Optionen je Feld: `label`, `help`, `required`, `default`, `max`, `rows`,
`placeholder`, `width` (`half`), `variants` (nur bei bestimmten Darstellungen), `types` (bei `file`: erlaubte Endungen, z. B. `['vtt', 'srt']`).

**Block-Optionen:** `variants` (Darstellungen), `background`, `nestable`, `requires` (`'yform'`), `jsonld`
(strukturierte Daten, z. B. `['type' => 'faq', 'items' => 'items', 'question' => 'q', 'answer' => 'a']`),
`drags` (Greifer auf der Seite: `['field' => 'measure', 'target' => '.prose', 'class' => 'prose--m-{v}', 'label' => 'Textbreite']`).

## Eigene Kits

Ein Kit bestimmt die Gestaltung der ganzen Website: Seitenrahmen, CSS, Ausgabe der Blöcke, Design-Optionen. fluid bringt
das Kit `fluid` mit. Weitere Kits liegen im Projekt oder kommen als Composer-Paket; welches gilt, wählen Admins unter
**Website → Kit** (der Reiter erscheint, sobald es mehr als ein Kit gibt).

**Inhalte bleiben beim Wechsel.** Die Block-Module (`fluid.hero`, `fluid.cards` …) gehören zu keinem Kit, sie zeigen
sich im gewählten. Jedes Kit erbt von einem Eltern-Kit (Standard `fluid`) und bringt nur mit, was es ändert – alle
Blöcke sind also immer da, und Updates von fluid kommen beim abgeleiteten Kit mit an.

### Anlegen

```bash
php bin/console fluid:kit:create nature --label="Nature" --view=hero,cards --template
```

Legt `src/Kit/Nature/` im Projekt an:

| Datei | |
|---|---|
| `NatureKit.php` | Anmeldung: `#[AsKit('nature', 'Nature', parent: 'fluid')] final class NatureKit extends Kit {}` |
| `kit.php` | Definition – nur, was vom Eltern-Kit abweicht (`$parent` steht bereit) |
| `assets/css/kit.css` | eigenes CSS, geladen nach Grund- und Block-CSS des Eltern-Kits |
| `views/hero.php` … | mit `--view` kopierte Views zum Anpassen |
| `template.view.php` | mit `--template` kopierter Seitenrahmen |

### Was ein Kit ersetzen kann

Jede Datei im Kit-Ordner ersetzt die gleichnamige des Eltern-Kits, alles andere gilt von dort:

- `views/{typ}.php` – Ausgabe eines Blocktyps (`$b` = Block, `$d` = Daten, wie beim eigenen Block)
- `template.view.php` – Seitenrahmen (Kopf, Menü, Fuß); Vorlage ist `kits/fluid/src/FluidTemplate.view.php`
- `assets/…` – einzelne Dateien, z. B. `assets/css/b-cards.css` statt der des Eltern-Kits
- `functions.php` – zusätzliche Hilfsfunktionen (die des Eltern-Kits sind schon geladen)
- `kit.php` – Definition, über die des Eltern-Kits gelegt:

```php
<?php // src/Kit/Nature/kit.php
return [
    'label' => 'Nature',
    'css' => ['css/kit.css'],                                   // Stylesheets für jede Seite (angehängt)
    'backgrounds' => ['sand' => 'Sand'],                          // ergänzt die Hintergründe
    'assets' => ['css/b-gallery.css' => ['gallery']],             // CSS je Blocktyp (ergänzt)
    'blocks' => [                                                // je Typ ersetzt oder neu
        'hero' => ['variants' => ['leaf' => 'Mit Blatt'] + $parent['blocks']['hero']['variants']] + $parent['blocks']['hero'],
    ],
    'design' => $parent['design'],                              // Style-Editor; eigene Vorlagen unter ['presets']
];
```

`blocks`, `backgrounds` und `assets` werden je Schlüssel ergänzt, `css` wird angehängt, alle anderen Einträge ersetzt.

**Design-Werte** speichert jedes Kit für sich. Solange ein abgeleitetes Kit keine eigenen hat, übernimmt es die seines
Eltern-Kits – nach dem Wechsel sieht die Website also zunächst aus wie vorher.

**Assets** veröffentlicht fluid nach `assets/fluid-kits/{kit}/`: bei `console migrate` komplett, im Betrieb je Datei,
sobald sie fehlt oder sich geändert hat. Relative Pfade im CSS zeigen auf `assets/` des eigenen Kits.

### Als Composer-Paket

Ein Kit für mehrere Projekte ist ein eigenes Paket mit der Kit-Klasse im Autoload (PSR-4), z. B.
`friendsofredaxo/fluid-kit-nature`. Nach `composer require` und `console migrate` steht es in der Auswahl. Ein Kit
kann auch von einem anderen Kit als `fluid` erben (`parent: 'nature'`).

### Kit je Domain

Standard ist ein Kit für die ganze Instanz. Per Extension Point `FLUID_KIT` lässt es sich je Anfrage wählen:

```php
Extension::register('FLUID_KIT', static fn (ExtensionPoint $ep) => 'shop.example.org' === Request::server('HTTP_HOST', 'string', '') ? 'nature' : $ep->subject);
```

## Extension Points

| Name | Subject | Params | Zweck |
|---|---|---|---|
| `FLUID_BLOCK_DEFINITION` | Definition (array) | `type`, `module`, `kit` | Felder ergänzen, Beschriftungen ändern |
| `FLUID_BLOCK_VIEW` | Pfad der View | `type`, `variant`, `block` | Ausgabe eines Blocks überschreiben |
| `FLUID_SETTINGS_SCHEMA` | Reiter der Website-Einstellungen | – | eigene Einstellungen (lesen mit `setting('name')`) |
| `FLUID_SEARCH_DOCUMENT` | Dokument (array) oder null | `article` | Suchindex ergänzen, Seiten ausschließen |
| `FLUID_STRUCTURED_DATA` | JSON-LD (`@graph`) | `article` | schema.org ergänzen |
| `FLUID_ROBOTS` | Text der robots.txt | – | Regeln ergänzen |
| `FLUID_SITE_TRANSLATIONS` | Übersetzungen (array) | `code` | feste Texte des Kits für eine Sprache ergänzen/ändern |
| `FLUID_EDITOR_CONFIG` | Einstellungen des Editors | `article`, `user` | Blöcke/Menüpunkte für Rollen ausblenden |
| `FLUID_KIT` | Schlüssel des Kits | – | Kit je Anfrage wählen, z. B. je Domain |

Dazu nutzt fluid die Extension Points des Cores, z. B. `URL_REWRITE` für die Adressen.

```php
// Eigene Website-Einstellung
Extension::register('FLUID_SETTINGS_SCHEMA', static function (ExtensionPoint $ep) {
    $schema = $ep->subject;
    $schema['base']['fields'][] = ['name' => 'claim_en', 'label' => 'Claim (englisch)', 'type' => 'text'];
    return $schema;
});
```

## YForm: Formulare und Datenlisten

fluid arbeitet mit den Mitteln von YForm 6 – Tabellen und Formulare sind Klassen im Projekt (`#[AsTable]`, `#[AsForm]`):

- **Formular-Baukasten** (Block „Formular“, ohne gewählte Klasse): Die Redaktion stellt Felder auf der Website zusammen
  (Textzeile, E-Mail, Telefon, Auswahl, Häkchen, Datum, Hinweistext …), teilt das Formular mit „— Neuer Schritt —“ in
  Schritte und wählt, was nach dem Absenden passiert: speichern, per E-Mail senden oder beides, Bestätigung an den
  Absender, Text oder Weiterleitung. Technisch eine YForm-`FormDefinition` (`Data\BuiltForm`), deren Felder aus dem Block
  kommen – Prüfung, CSRF, Honigtopf und Aktionen (`SaveRecord`, `SendMail`, `Redirect`) laufen über YForm, ebenso der EP
  `YFORM_FORM_SUBMITTED`. Schritte = Reiter des YForm-Layouts (`js/formsteps.js`, ohne JavaScript alle untereinander).
  Gespeichert wird in die YForm-Tabelle **„Formular-Eingänge“** (`rex_fluid_form_entry`, Klasse in fluid, Postfach
  „Anfragen“) oder in eine eigene YForm-Tabelle (Felder mit gleichem Feldnamen).
- **Block „Formular“** mit Klasse und Formular im **Block „Kontakt“**: Die Redaktion wählt ein Formular. Prüfung, CSRF, Honigtopf
  und Aktionen (`SaveRecord`, `SendMail`, `ShowMessage` …) kommen aus der Formular-Klasse; fluid gibt es im Stil des Kits aus.
- **Block „Datenliste“**: eine Tabelle als Karten, Liste oder Tabelle mit Detailansicht (`?eintrag=ID`), Filter und
  Sortierung; „Bearbeiten“ öffnet die Maske von YForm.
- **Postfach „Anfragen“**: Einträge der Tabellen, in die die Formulare der Website speichern; Status neu/erledigt.
- **Kurzmeldungen, Presse, Termine**: Datenliste mit Sortierung (z. B. Datum absteigend), Blättern („Höchstens“ = je Seite,
  über YForms `paginate()`) und Detailansicht mit eigener Adresse `/seite/titel-ID` – Titel, Beschreibung, Bild und
  Canonical des Eintrags, Eintrag in der Sitemap, 301 von alten `?eintrag=`-Links, 404 für fehlende Einträge.
  Wann Blog, wann Datenliste? Blog = Beiträge mit Blöcken, Entwurf und Versionen; Datenliste = gleichförmige Einträge
  mit festen Feldern, gepflegt in der YForm-Maske.

## Blöcke in YForm-Tabellen: Feld „Fluid-Blöcke“

Jede YForm-Tabelle kann Inhalt aus den Blöcken von fluid haben – ein Feld wie jedes andere in der Tabellenklasse:

```php
use FriendsOfRedaxo\Fluid\Data\FluidBlocks;

yield FluidBlocks::new('inhalt')->label('Inhalt');
yield FluidBlocks::new('inhalt')->label('Inhalt')->blocks('richtext', 'media_text', 'video');   // nur diese Blöcke
```

- Bearbeiten in der YForm-Maske: Blöcke hinzufügen, bearbeiten (dieselben Felder wie auf der Website – Medienpool,
  Linkmap, Text-Editor), verschieben, entfernen. Gespeichert als JSON (`longtext`).
- Ausgabe: Die Detailansicht der Datenliste zeigt das Feld automatisch als Blöcke. In eigenen Templates:
  `echo \FriendsOfRedaxo\Fluid\BlockData::render($record->get('inhalt'));` – Klartext für Listen und Suche:
  `BlockData::text(…)`.
- **Auf der Website bearbeiten:** Im Bearbeiten-Modus sind die Blöcke eines Eintrags in der Detailansicht der Datenliste
  bearbeitbar wie Seiten-Blöcke – Texte direkt auf der Seite, Seitenleiste, Greifer, hinzufügen (nur die im Feld
  erlaubten Blöcke), verschieben (Pfeile, Ziehen), verdoppeln, kopieren/einfügen (auch zwischen Seite und Eintrag),
  löschen. Gespeichert wird sofort in den Datensatz (YForm-Record-API mit Prüfung und Hooks) – Datensätze kennen keinen
  Entwurf. Recht: YForm „Tabelle bearbeiten“ und Modulrecht des Blocks. Technisch: Block-IDs `r~tabelle~id~feld~uid`
  (`Data\RecordBlocks`), jeder Block hat eine feste Kennung `uid`.
- Layout-Spalten gibt es im Feld nicht (sie brauchen Slices); Rechte: Modulrecht des Blocks wie auf den Seiten.

Für eigene Ausgaben: `PageSettings::override(['title' => …, 'description' => …, 'image' => …, 'canonical' => …])`
setzt den Seitenkopf aus einem Block heraus (z. B. eigene Detailansichten).

Vorlagen: `examples/yform/` (`TerminTable`, `NewsTable`, `ProjectTable`, `AnfrageTable`, `AnfrageForm`) – nach `src/` kopieren, `console migrate`.

## Adressen, Domains, SEO

- Adresse = Slugs der Elternseiten + eigener Slug (Tabelle `rex_fluid_route`); der Slug bleibt beim Umbenennen gleich.
- Domains (Website → Domains): **Hauptadresse**, **weitere Domains** (301 auf die Hauptadresse), **Landing-Domains**
  (eigene Domain für eine Seite, optional mit Unterseiten, Canonical „eigene Domain“ oder „Spiegel“).
- `sitemap.xml` und `robots.txt` je Domain, Canonical, hreflang, schema.org-@graph (Organisation, WebSite, WebPage,
  Brotkrumen, Blöcke).
- Weitere Sprachen bekommen ein Präfix (`/en/…`); Sprachwahl im Kopf und im mobilen Menü zeigt nur Sprachen, in denen
  die Seite online ist (`Router::alternates($article)`).

## Sprachen

Website-Texte (Claim, Hinweisleiste, Fußtext …) pflegt man in „Website“ je Sprache; leer = Text der Startsprache.
Eigene Einstellungen werden mit `'translate' => true` im Schema (EP `FLUID_SETTINGS_SCHEMA`) übersetzbar.

Sprachen sind die des Cores (`LanguageHandler`) – angelegt unter Website → Sprachen oder im Backend. Feste Texte des Kits
(„Menü“, „Öffnungszeiten“ …) schreibt das Kit mit `lt('Text')`; Übersetzungen liegen in `kits/fluid/lang/site/{code}.php`
(`['Menü' => 'Menu', …]`). Eigene Sprachen oder Änderungen ohne das Kit anzufassen:

```php
Extension::register('FLUID_SITE_TRANSLATIONS', static function (ExtensionPoint $ep) {
    return 'fr' === $ep->getParam('code') ? $ep->subject + ['Menü' => 'Menu', 'Suche' => 'Recherche'] : $ep->subject;
});
```

## Videos

- **Vorschaubild:** Media-Manager-Typ `fluid_poster` – bei einem Video ein typisches Standbild per ffmpeg (bei ~10 % der
  Laufzeit), bei einem Bild nur verkleinert; Ausgabe AVIF/WebP, gespeichert im Cache des Media Managers. Ohne ffmpeg
  gibt es nur gewählte Vorschaubilder. Pfad bei Bedarf über `FFMPEG_BIN` / `FFPROBE_BIN` in `.env`.
  `MediaManager::getUrl('fluid_poster', 'film.mp4')` funktioniert auch in eigenen Templates.
- **Untertitel und Kapitel:** `.vtt` oder `.srt` im Medienpool, im Block je Spur Sprache und Art. fluid liefert sie geprüft
  als WebVTT unter `/fluid-track/{datei}` aus (SRT umgewandelt; nur erlaubte WebVTT-Tags). Option „gleich einschalten“
  wählt die Spur in der Sprache der Seite; „Transkript“ zeigt den Text darunter (`<details>`).
- Eigene Ausgabe: `fluid_video_embed($url, $datei, $poster, $format, $titel, $spuren, $einschalten, $transkript)`
  bzw. `FriendsOfRedaxo\Fluid\Media\Tracks` (`parse()`, `toVtt()`, `transcript()`).

## Blog

Beiträge sind normale Artikel in einer Kategorie mit „Blog / News“ (Seiteneinstellungen der Menüseite) – Editor, Entwurf,
Versionen, Rechte, Adressen, Sprachen und Suche gelten wie überall. Dazu Datum, Autor, Schlagworte (`rex_fluid_page`);
Teaserbild = Bild für soziale Netzwerke, Kurztext = Beschreibung. Sortierung nach Datum, neueste zuerst.

- Beitragsseiten bekommen im Template Kopf (Titel, Datum, Autor, Schlagworte, Bild) und „Älterer/Neuerer Beitrag“
  (`fluid_post_header()`, `fluid_post_footer()` im Kit), schema.org `BlogPosting`, die Übersicht `Blog`.
- RSS 2.0 unter `/{blog}/feed.xml` (EP `FLUID_BLOG_FEED`), `<link rel="alternate">` im Kopf.
- Eigene Ausgaben: `Blog::posts($blogId, ['limit' => 3, 'tag' => 'team'])` liefert `Post`-Objekte
  (`article`, `date`, `author`, `tags`, `image`, `excerpt`, `url`); `Blog::blogOf($article)`, `Blog::tags($blogId)`.
- Feldtyp `blog` (Auswahl der Blogs) für eigene Blöcke.

## Karten

MapLibre GL und OpenFreeMap über den eigenen Proxy `/proxy/ofm/…` (Cache im Cache-Ordner des AddOns). Besucher verbinden
sich nicht mit Dritten. Detailkacheln gibt es nur rund um Orte, die auf der Website als Karte vorkommen.

## Versionen

Stände entstehen beim Speichern (zusammengefasst, solange dieselbe Person innerhalb von 2 Minuten weiterarbeitet),
vor dem Veröffentlichen, vor dem Verwerfen und vor dem Wiederherstellen. Sie liegen in der Historie-Tabelle des Cores
(`rex_article_slice_history`), die letzten 40 je Seite und Sprache (`Versions::KEEP`).

- Ansicht: `assets/editor/versions.js` + `versions.css` (aus KLXM Studio übernommen), Daten von `?fluid_api=version_view`.
- Vorschau eines Stands: `?fluid_version=now|live|<revision>-<YmdHis>` rendert die Seite aus der Historie. Dafür
  tauscht `ART_SLICES_QUERY` die Tabelle gegen die Zeilen des Stands. Die Vorschau gibt es nur mit Recht auf die Seite,
  ohne Leiste und nie aus dem Cache. `&fluid_mark=1` setzt `data-vdiff="new|changed"` am äußersten Element geänderter
  Blöcke (`SLICE_SHOW`).
- Vergleich je Block: erst über die Slice-ID, dann über gleichen Inhalt, dann über Blocktyp in Reihenfolge.
  Veröffentlichen und Wiederherstellen legen Slices mit neuen IDs an.
- Ältere Stände mit demselben Inhalt wie ein neuerer erscheinen nicht.

## MCP-Server

`src/Mcp/` – Model Context Protocol über „Streamable HTTP“, zustandslos, JSON-Antworten (Aufbau wie in KLXM Studio).

- Endpunkt `POST /fluid-mcp`. Ohne schöne Adressen geht `/index.php?fluid_mcp=1`. Der Router reicht die Anfrage an
  `PACKAGES_INCLUDED` weiter, damit YForm geladen ist.
- Anmeldung mit `Authorization: Bearer fluid_…` (alternativ `X-Api-Key`).
  - In `rex_fluid_mcp_token` steht nur der SHA-256-Hash.
  - Jeder Schlüssel handelt als REDAXO-Benutzer: `Core::setUser()` und `Editing::actAs()`. Dadurch gelten
    Struktur- und Modulrechte, und Aktivität und Versionen nennen den Benutzer.
- `Access`: Schlüssel (`read`/`write`, Veröffentlichen nur mit `version[live_version]`) oder öffentlich.
  - Öffentlich gibt es nur, was der Admin in `fluid` → Config `mcp` freigibt: Seiten, Suche, Blog, Tabellen.
  - Öffentlich heißt: nur live, ohne Offline-Blöcke, ohne Optionen.
- `Tools`: Die Liste hängt vom Zugang ab. Werkzeuge ohne Recht erscheinen gar nicht. Fehler der Werkzeuge kommen als
  `isError` zurück, Ergebnisse als `structuredContent` und als Text.
- `Content`: Schreiben geht immer in den Entwurf und nutzt dieselben Wege wie der Editor (`BlockOps`, `Versions`,
  `Activity`, `Api::publish`).
  - Es werden nur Felder übernommen, die der Blocktyp kennt.
  - Rich-Text läuft durch `RichText::html`, andere Texte ohne Tags.
- Grenzen: 300 Anfragen je 5 Minuten und Schlüssel, 60 öffentlich je IP (Dateien im Cache des AddOns).
  - Der Origin-Header wird geprüft (Schutz vor DNS-Rebinding).
  - `GET` antwortet mit 405, es gibt keinen SSE-Stream.

Testen ohne KI-Werkzeug:

```bash
curl -s https://example.org/fluid-mcp -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"site_overview","arguments":{}}}'
```

## Suche

`src/Search/Search.php` – Loupe (SQLite) je Sprache, aus den Blockdaten der veröffentlichten Fassung. In den Index kommt
nur, was Besucher sehen können:

- Seiten, die samt Eltern online sind, in einer Sprache, die online ist. Ohne „nicht indexieren“, ohne Seitenvorlagen,
  ohne die Fehlerseite.
- Nur die Live-Fassung, nie Entwürfe der Arbeitsversion.
- Nur Blöcke, die online sind. Spalten eines Layouts zählen nur, wenn auch das Layout online ist.
- Nur Felder vom Typ Text, Textarea und Rich-Text. Redaktionskommentare `[# … #]` werden entfernt.
- Einstellungen, die nicht auf der Seite stehen, fehlen: Empfänger und Betreff von Formularen, Bestätigungstexte,
  Platzhalter, Filterwerte, Hinweise für leere Listen (`Search::NO_FIELDS`).
- Eigene Blöcke: `'search' => false` am Feld (z. B. interne Kennungen) oder am ganzen Block im Kit.

Der Index wird neu aufgebaut, wenn sich etwas ändert: Veröffentlichen, Struktur (online/offline, verschieben, löschen),
Inhalte im Backend (`ART_CONTENT_UPDATED`), jede Aktion mit Eintrag in der Aktivität (Editor, MCP). Außerdem prüft
`Search::search()` jeden Treffer vor der Ausgabe noch einmal. Ist eine Seite inzwischen nicht mehr öffentlich, fällt
der Treffer weg und der Index wird sofort neu aufgebaut. Das gilt auch für das MCP-Werkzeug `search`.

## Neu laden auf Aktion

Filtern, Blättern und Suchen ohne Seitenwechsel – aus KLXM Studio (`Core\Live`, MIT), gleiche Attribute und Skripte.
Die Blöcke Datenliste (Blättern), Beiträge (Schlagworte, Blättern) und Suche (Treffer beim Tippen) nutzen es.
Ohne JavaScript bleiben es normale Links und Formulare.

Eigener Block:

```php
// kit.php: 'reload' => true beim Block
<div class="wrap …"<?= \FriendsOfRedaxo\Fluid\Live::region($b) ?>>
  <a href="?seite=2#<?= $b->domId() ?>" data-live-nav>Weiter</a>
  <form method="get" data-live-nav><input name="q" id="…" data-live-input data-live-delay="600" data-live-min="3"></form>
  <p role="status" data-live-count>12 Treffer</p>
</div>
<?= \FriendsOfRedaxo\Fluid\Live::assets() ?>
```

- Eingabefelder: `data-live-delay` = Pause nach dem letzten Tastendruck (Standard 350 ms), `data-live-min` = erst ab so
  vielen Zeichen. Ein geleertes Feld lädt sofort neu (zurücksetzen). Die Suche nutzt 600 ms und 3 Zeichen.
- `Live::region($b)` setzt `data-live-region` (= `domId`) und `data-live-src` (Seitenadresse mit `?fluid_block={slice}`).
  Im Bearbeiten-Modus und bei Blöcken aus Daten bleibt es leer.
- `assets/live.js` holt den Block mit den Parametern des Links bzw. Formulars und tauscht `[data-live-region]` aus.
  - Links und Absenden: `pushState`, Eingabe: `replaceState`, Zurück/Vor lädt passend nach.
  - Laufende Anfragen werden abgebrochen, danach kommt das Ereignis `cms:content-replaced`.
  - Der Fokus bleibt im Feld (mit Cursor) bzw. geht an den Block. Die Ansage für Screenreader nimmt den Text aus
    `[data-live-count]`.
  - Bei Fehlern lädt es die Seite normal.
- Der Server (`Live::block()`, über `FE_OUTPUT`) rendert nur Blöcke mit `'reload'` oder `'live'` der veröffentlichten
  Seite. Die übrigen Parameter liest der Block wie beim normalen Aufruf und prüft sie selbst. `fluid_block` ist reserviert.

## Live-Aktualisierung

Versionen statt Inhalte über Server-Sent Events: `GET /fluid-live?t={abo}`, Ereignis `v` = `{abo: version}`.

- Kanäle sind Namen, deren Version steigt: `Live::touch('ext:shop:lager')`. fluid selbst setzt `page:{id}` beim
  Veröffentlichen und `presence:default` bei Kommen und Gehen in der Präsenz.
- Abos sind signiert (HMAC mit einem Schlüssel in der Konfiguration des AddOns): `Live::token([...])`,
  `Live::attrs([...], $src)` fürs Element. `live.js` hält eine Verbindung je Seite. Neue Version → Element mit
  `data-live-src` neu laden (`[data-live-list]`, `[data-live-id]`) bzw. Ereignis `cms:live` für eigenes JavaScript.
- Betrieb: höchstens 16 offene Streams zu je 20 Sekunden. Darüber und auf dem Entwicklungsserver (`php -S`) antwortet
  der Endpunkt sofort, und der Browser fragt alle 15 Sekunden nach.

## Präsenz der Redaktion

Wer arbeitet gerade wo – aus KLXM Studio (`Core\Presence`, `presence.mjs`), das Modul ist unverändert übernommen.

- In der Leiste: „Anna bearbeitet gerade“ (Initialen), sonst „Zuletzt gespeichert: Ben, vor 5 Min.“ (aus der Aktivität).
- Konflikt: Hat jemand anderes seit dem Öffnen gespeichert, zeigt die Leiste ⚠. Vor dem nächsten Speichern fragt fluid
  nach. Es gibt keine Sperre, „Trotzdem speichern“ ist erlaubt, der vorige Stand bleibt als Version.
- Im Seitenbaum ein Kürzel an Seiten, die gerade jemand bearbeitet. Unter „Verlauf & Entwürfe“ → Aktivität die Liste
  „Gerade aktiv“.
- Nur für angemeldete Backend-Benutzer mit Recht auf die Seite. Gespeichert werden nur Name, Ort und Zeitpunkt der letzten
  Meldung (Cache des AddOns, 45 Sekunden), es gibt kein Protokoll. Abschalten: Config `presence` des AddOns auf `false`.

Teile:

| Teil | Aufgabe |
|---|---|
| `src/Presence.php` | Speicher: `beat($who, $meta)`, `leave($who)`, `list($filter)`; Schlüssel `site/type/id/user`, kennt keine Artikel |
| `src/PresenceApi.php` | Anbindung: Endpunkt `/fluid-presence`, Rechte, „zuletzt gespeichert“, Attribute für die Leiste |
| `assets/editor/presence.js` | Oberfläche, nur über data-Attribute und Ereignisse gekoppelt |
| `Live` | Kanal `presence:default` – Kommen und Gehen sofort statt nach 15 Sekunden |

Schema (wie in Studio):

```
POST /fluid-presence  {type: "article", id: "{sprache}:{artikel}", label?, url?, leave?, peek?, _csrf?}
                      → {on, me, here: [Person], last: Stand|null, live: Abo}
GET  /fluid-presence  → {on, me, items: [{type, id, label, url, …Person}], live: Abo}
Person = {user, name, initials, avatar, since, at, self}     Stand = {user, name, at, self}
```

- `[data-presence-here]` mit `data-presence-endpoint/-type/-id/-label/-url/-csrf/-live/-texts/-editing` – meldet sich
  alle 15 Sekunden (im Ansehen-Modus nur `peek`), beim Verlassen per `sendBeacon` ab.
- `[data-presence-list]` mit `data-presence-type` und `-prefix`; Einträge `[data-id]` bekommen ein Kürzel in `[data-presence-slot]`.
- `[data-presence-board]` – Liste „Gerade aktiv“, `[data-presence-board-box]` wird ohne Einträge ausgeblendet.
- Später eingefügte Listen (Seitenleisten): Ereignis `cms:presence-scan` am `document`.
- Konflikt: Der Editor löst vor schreibenden Aktionen `cms:before-save` aus (`detail.waitUntil(promise)`; `false` bricht ab).
  Die Rückfrage läuft über `window.FluidEditor.ask`. Ereignis `cms:presence` am `document`: `{here, last, conflict}`.

Einbau in andere Systeme: `Presence.php` und `presence.js` kopieren, einen Endpunkt nach dem Schema oben schreiben
(Rechte prüfen, `beat`/`leave`/`list`, „zuletzt gespeichert“ aus der eigenen Historie) und die Attribute ausgeben.
Ohne Live-Kanal funktioniert es auch, dann mit 15 Sekunden Verzögerung.

## Konsole

| Befehl | |
|---|---|
| `fluid:demo --set=redaxo6` | Demo-Website anlegen (nur in einer leeren Installation; YForm-Beispieldaten gleich mit) |
| `… --media` | Demo-Bilder des Kits in den Medienpool übernehmen |
| `… --design=redaxo` | Design-Vorlage setzen (`redaxo` zusätzlich mit REDAXO-Logo aus dem Core); andere: `kanzlei`, `handwerk`, `praxis`, `aurora` … |
| `… --replace` | vorher **alle** Seiten löschen – nur Testinstallationen |
| `… --page=slug` | nur eine Demo-Seite anhängen, z. B. `--page=projekte` |
| `… --yform` | nur die Beispielzeilen der YForm-Tabellen schreiben |
| `fluid:kit:create name` | eigenes Kit im Projekt anlegen (`--label`, `--parent`, `--view=hero,cards`, `--template`) |
| `fluid:search-index` | Suchindex neu aufbauen (sonst automatisch nach dem Veröffentlichen) |
| `fluid:selftest` | Live-Aktualisierung und Präsenz prüfen |

## Rechte

fluid nutzt die Rechte des Cores – eine Rolle wirkt auf der Website genau wie im Backend:

- **Kategorien** (Strukturrecht) und **Module** (welche Blöcke) bestimmen, was jemand bearbeiten und einfügen darf.
- **Optionen** der Rolle schalten Aktionen frei: `version[live_version]` Veröffentlichen (ohne: „Entwurf – wartet auf
  Freigabe“), `moveSlice[]` Blöcke verschieben, `publishSlice[]` Blöcke online/offline, `addArticle[]`/`addCategory[]`
  neue Seiten bzw. Menüpunkte, `editArticle[]`/`editCategory[]` Seiteneinstellungen, `moveArticle[]`/`moveCategory[]`,
  `publishArticle[]`/`publishCategory[]`, `deleteArticle[]`/`deleteCategory[]`, `article2category[]` (Menüpunkt ja/nein),
  `copyContent[]` (Inhalt aus anderer Sprache). Die Leiste zeigt nur, was erlaubt ist; die API prüft jede Aktion.
- Design und Website-Einstellungen: Admins. YForm-Tabellen: Rechte von YForm (Tabellen ansehen/bearbeiten).

## Gastzugang (für AddOns)

fluid selbst kennt nur angemeldete Backend-Benutzer. Ein AddOn kann Besucher ohne Anmeldung als **Gäste** zulassen,
zum Beispiel [`fluid_demo`](https://github.com/FriendsOfREDAXO/fluid_demo), die Demo zum Ausprobieren. Ohne ein solches AddOn gibt es keine Gäste.

- `FLUID_GUEST_USER` (Subjekt `null`): Liefert ein AddOn einen `User`, arbeitet der Besucher mit dessen Rechten.
  Admins werden abgewiesen. `Editing::isGuest()` ist dann `true`.
- `Editing::guestRevision(int)`: Revision, in der Gäste arbeiten. `fluid_demo` nutzt die Live-Fassung, aber nur in
  einer Transaktion, die immer zurückgerollt wird.
- `FLUID_API_ACTION` (Parameter `action`, `article`, `user`, `guest`, `run`, `state`): Ein AddOn kann jede Aktion des
  Editors übernehmen und `['result' => [...], 'state' => [...]]` zurückgeben. `run()` führt die Aktion mit dem
  normalen Code aus (`Api::dispatch()`), `state()` liefert den Stand des Artikels. **Aktionen von Gästen führt fluid
  nie selbst aus.** Übernimmt kein AddOn, antwortet die API mit 403.
- Für Gäste aus: Präsenz, Versionen-Vorschau, Blöcke in YForm-Datensätzen, YForm-Links, Bildeditor, Medienpool,
  Postfach, Design, Website-Einstellungen, Seitenbaum, Verlauf, Backend-Links. Die Beitragsliste zeigt keine Entwürfe.
  Die Sprachwahl zeigt nur Sprachen, in denen die Seite online ist. Der Suchindex wird nicht als veraltet markiert.
- Editor: AddOns hängen sich über `FLUID_EDITOR_CONFIG` ein:
  - `scripts`: Skripte, die vor dem Editor laden.
  - `notice`: `{tag, text, buttons: [{label, event, hidden}]}`, ein Hinweis in der Leiste. Die Knöpfe lösen ihr
    Ereignis am `document` aus.
  - Im Browser: `window.fluidEditorWait` (Promises, auf die der Editor beim Start wartet), `fluid:api-request`
    (`detail.fd` ergänzen), `fluid:api-done` (`detail.json`), `fluid:notice` (Meldung zeigen),
    `window.FluidEditor.ask/discard`.
  - `window.fluidPickerOff` (Text): Statt Medienpool oder Linkmap erscheint nur dieser Hinweis.
- Interne Felder, die Gäste nicht sehen sollen, nennt `Search::NO_FIELDS` bzw. `'search' => false` am Feld. Die Demo
  leert sie, bevor Gäste etwas zu sehen bekommen.

## Aufbau des AddOns

| Pfad | Inhalt |
|---|---|
| `src/` | Namespace `FriendsOfRedaxo\Fluid`: Editor-API (`Api`), Blöcke (`BlockModule`, `Block`, `BlockData`), Routing, Blog, Daten (YForm), Medien, Suche |
| `src/Data/` | YForm-Anbindung: `YFormBridge`, Formular-Baukasten `BuiltForm`, Feld `FluidBlocks`, `RecordBlocks`, `Detail`, Postfach |
| `kits/fluid/` | das Grund-Kit: `kit.php` (Blöcke), `design.php` (Design-Tokens, Vorlagen), `views/` (Ausgaben), `functions.php`, `lang/` |
| `src/Kit.php`, `src/AsKit.php` | Kits: Anmeldung, Auswahl (`Kit::active()`), Vererbung, Assets |
| `assets/` | Editor (`editor/`), Formulare (`form.js`, `rte.js`), Kit-CSS/JS (`kits/fluid/`), Schriften, MapLibre |
| `examples/` | Vorlagen für eigene Blöcke und YForm-Tabellen |
| `pages/` | Backend-Seite AddOns → fluid |
| `tools/video/` | Aufnahme von Demo-Video und Trailer (Playwright + ffmpeg) |

## Mitentwickeln

```bash
git clone https://github.com/FriendsOfREDAXO/fluid.git
FLUID_SOURCE=$PWD/fluid bash fluid/install-dev.sh fluid-dev
```

`FLUID_SOURCE` (ebenso `YFORM_SOURCE`, `IMAGE_EDITOR_SOURCE`) bindet die lokale Arbeitskopie als Composer-Pfad-Repository
ein – als **Kopie** (`symlink: false`). Nach Änderungen: `composer reinstall friendsofredaxo/fluid && php bin/console migrate`.

> Vorsicht mit Symlinks: Ist `public/assets/addons/fluid` ein Symlink auf die Arbeitskopie, löscht `console migrate` beim
> Neukopieren der Assets die Originale. Entweder kopieren lassen (wie oben) oder vor `migrate` den Symlink entfernen.

**Upstream-Wächter** (`.github/workflows/upstream.yml`): Prüft täglich `redaxo/core` 6.x und `yakamara/yform` 6.x. Bei
neuen Commits kommt ein Kommentar ins offene Issue „Neu in …“ (Label `upstream`). Gibt es kein offenes, entsteht ein
neues. Darin stehen die Commits mit Links. Wer benachrichtigt werden will, beobachtet das Repo oder trägt sich in der
Repo-Variable `UPSTREAM_NOTIFY` ein (GitHub-Namen, mit Leerzeichen getrennt). Ein Issue schließen heißt: gelesen. Starten
lässt sich der Wächter auch von Hand (Actions → Upstream-Wächter → Run workflow).

- PHPStan (Stufe 5): mit der PHPStan-Installation des Cores, Pfade `src`, `kits/fluid/src`, `kits/fluid/functions.php`.
- Video: `node tools/video/trailer.mjs` bzw. `redaxo6-demo.mjs` – braucht Playwright, ffmpeg und eine lokale Anmelde-Hilfe
  (`LOGIN=…`), siehe Kopf der Skripte. Nur gegen Testinstallationen.

# fluid – Entwickler-Referenz

Technische Doku zu fluid für REDAXO 6. Einstieg und Installation: [README](../README.md).

## Inhalt

- [Ein eigener Block](#ein-eigener-block)
- [Extension Points](#extension-points)
- [YForm: Formulare und Datenlisten](#yform-formulare-und-datenlisten)
- [Blöcke in YForm-Tabellen](#blöcke-in-yform-tabellen-feld-fluid-blöcke)
- [Adressen, Domains, SEO](#adressen-domains-seo)
- [Sprachen](#sprachen) · [Videos](#videos) · [Blog](#blog) · [Karten](#karten)
- [Konsole](#konsole) · [Rechte](#rechte) · [Aufbau des AddOns](#aufbau-des-addons) · [Mitentwickeln](#mitentwickeln)


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

## Konsole

| Befehl | |
|---|---|
| `fluid:demo --set=redaxo6` | Demo-Website anlegen (nur in einer leeren Installation; YForm-Beispieldaten gleich mit) |
| `… --media` | Demo-Bilder des Kits in den Medienpool übernehmen |
| `… --design=redaxo` | Design-Vorlage setzen (`redaxo` zusätzlich mit REDAXO-Logo aus dem Core); andere: `kanzlei`, `handwerk`, `praxis`, `aurora` … |
| `… --replace` | vorher **alle** Seiten löschen – nur Testinstallationen |
| `… --page=slug` | nur eine Demo-Seite anhängen, z. B. `--page=projekte` |
| `… --yform` | nur die Beispielzeilen der YForm-Tabellen schreiben |
| `fluid:search-index` | Suchindex neu aufbauen (sonst automatisch nach dem Veröffentlichen) |

## Rechte

fluid nutzt die Rechte des Cores – eine Rolle wirkt auf der Website genau wie im Backend:

- **Kategorien** (Strukturrecht) und **Module** (welche Blöcke) bestimmen, was jemand bearbeiten und einfügen darf.
- **Optionen** der Rolle schalten Aktionen frei: `version[live_version]` Veröffentlichen (ohne: „Entwurf – wartet auf
  Freigabe“), `moveSlice[]` Blöcke verschieben, `publishSlice[]` Blöcke online/offline, `addArticle[]`/`addCategory[]`
  neue Seiten bzw. Menüpunkte, `editArticle[]`/`editCategory[]` Seiteneinstellungen, `moveArticle[]`/`moveCategory[]`,
  `publishArticle[]`/`publishCategory[]`, `deleteArticle[]`/`deleteCategory[]`, `article2category[]` (Menüpunkt ja/nein),
  `copyContent[]` (Inhalt aus anderer Sprache). Die Leiste zeigt nur, was erlaubt ist; die API prüft jede Aktion.
- Design und Website-Einstellungen: Admins. YForm-Tabellen: Rechte von YForm (Tabellen ansehen/bearbeiten).

## Aufbau des AddOns

| Pfad | Inhalt |
|---|---|
| `src/` | Namespace `FriendsOfRedaxo\Fluid`: Editor-API (`Api`), Blöcke (`BlockModule`, `Block`, `BlockData`), Routing, Blog, Daten (YForm), Medien, Suche |
| `src/Data/` | YForm-Anbindung: `YFormBridge`, Formular-Baukasten `BuiltForm`, Feld `FluidBlocks`, `RecordBlocks`, `Detail`, Postfach |
| `kits/fluid/` | das Kit: `kit.php` (Blöcke), `design.php` (Design-Tokens, Vorlagen), `views/` (Ausgaben), `functions.php`, `lang/` |
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

- PHPStan (Stufe 5): mit der PHPStan-Installation des Cores, Pfade `src`, `kits/fluid/src`, `kits/fluid/functions.php`.
- Video: `node tools/video/trailer.mjs` bzw. `redaxo6-demo.mjs` – braucht Playwright, ffmpeg und eine lokale Anmelde-Hilfe
  (`LOGIN=…`), siehe Kopf der Skripte. Nur gegen Testinstallationen.

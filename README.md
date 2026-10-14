# fluid – Bearbeiten auf der Website für REDAXO 6

**fluid** bringt Blöcke mit Feldschema und Bearbeiten direkt auf der Website nach REDAXO 6. Redakteure ändern Texte, Bilder,
Seiten und das Design dort, wo sie wirken; Entwickler bauen Blöcke als kleine Klassen. Die Konzepte stammen aus
[KLXM Studio](https://github.com/KLXM-Opensource/studio) (MIT).

Status: Prototyp für REDAXO 6 (Branch `6.x`) – Ziel Friends Of REDAXO.

## Was es kann

**Für Redakteure** (alles auf der Website, Leiste oben):

- Texte direkt auf der Seite schreiben, Formatierungsleiste, Bilder aus dem Medienpool, Links über die Linkmap
- Blöcke einfügen, verschieben (ziehen oder ↑ ↓), verdoppeln, kopieren und auf anderen Seiten einfügen, online/offline
- Layout mit Spalten; Spaltenbreiten, Abstände, Textbreite und Aufteilung Bild/Text mit Greifern ziehen
- Arbeitsversion: erst speichern, dann „Veröffentlichen“; Versionen wiederherstellen, Entwürfe-Übersicht, Aktivität
- Seiten: Baum mit Ziehen oder ↑ ↓ ← → (auch am Handy), neue Seiten (leer, Kopie, Vorlage), Seiteneinstellungen mit Adresse und SEO-Angaben
- Website: Name, Logo, Kontakt, Öffnungszeiten, Social Media, Hinweisleiste, Rechtliches, Sprachen, Domains, Weiterleitungen
- Mehrsprachig: Sprache in der Leiste wechseln (DE | EN), leere Seite mit „Inhalt aus … übernehmen“ füllen und übersetzen
- Design-Editor (Admins), Bildbearbeitung (AddOn image_editor), Formulare und Datenlisten (YForm), Anfragen-Postfach
- Blog / News: eine Menüseite wird zum Blog, ihre Seiten sind Beiträge (Datum, Autor, Schlagworte); Block „Beiträge“ mit
  Aufmacher, Karten oder Liste, Blättern und Schlagwort-Filter; „+ Neuer Beitrag“ direkt in der Übersicht; RSS-Feed
- Videos: eigene Datei mit automatischem Vorschaubild, Untertiteln (.vtt/.srt aus dem Medienpool) und Transkript;
  YouTube/Vimeo mit Zwei-Klick-Lösung
- Redaktionskommentare `[# … #]` – nur im Bearbeiten-Modus sichtbar

**Für Besucher:** schöne Adressen, Multidomain, Sitemap, strukturierte Daten, Suche mit Tippfehler-Toleranz, Karten
ohne Einwilligung (eigener Proxy, keine Cookies), barrierearme Ausgabe.

## Voraussetzungen

- REDAXO 6 (`redaxo/core` 6.x), PHP 8.5, MySQL 8.4 / MariaDB 10.11
- PHP-Erweiterungen: `pdo_sqlite` (Suche), `intl`, `mbstring`, GD oder Imagick (Bilder)
- optional: [YForm 6](https://github.com/yakamara/yform/tree/6.x) für Formulare und Datenlisten,
  `friendsofredaxo/image_editor` für die Bildbearbeitung

## Installation

```bash
composer require friendsofredaxo/fluid
php bin/console migrate
php bin/console fluid:demo --set=redaxo6   # optional: Demo-Seiten (nur in leeren Installationen)
```

Das Kit **fluid** bringt Template und Blöcke mit. Solange es keine Seiten gibt, zeigt die Website „Erste Schritte“: Admins
legen dort mit einem Klick eine leere Startseite oder die Beispielseiten an. Im Backend erscheint über den Slices „Auf der
Website bearbeiten“; unter **AddOns → fluid** prüft „Status“ die Umgebung (Adressen, Mail, Suche, ffmpeg …).

Nach einem Update von fluid: `php bin/console migrate` (aktualisiert Tabellen und kopiert die Assets neu).

Entfernen: `php bin/console addon:uninstall fluid` (löscht Tabellen, Einstellungen, Suchindex und Cache von fluid),
direkt danach `composer remove friendsofredaxo/fluid`. Artikel und Slices bleiben erhalten.

### Webserver: schöne Adressen

fluid löst Adressen wie `/leistungen/beratung` selbst auf. Der Webserver muss alle Anfragen ohne Datei an `index.php`
geben.

**Apache** (`public/.htaccess`):

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L]
```

**nginx:**

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

Alte Aufrufe `index.php?article_id=…` leiten per 301 auf die schöne Adresse. Ändert sich eine Adresse (Umbenennen der
Adresse, Verschieben), legt fluid automatisch eine 301-Weiterleitung an.

### Mail

Formulare (YForm) und Benachrichtigungen nutzen den Mailer des Cores: `MAILER_DSN` und `REX_MAILER_FROM` in `.env`.

## Für Entwickler

### Ein eigener Block

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

### Extension Points

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

### YForm: Formulare und Datenlisten

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

### Blöcke in YForm-Tabellen: Feld „Fluid-Blöcke“

Jede YForm-Tabelle kann Inhalt aus den Blöcken von fluid haben – ein Feld wie jedes andere in der Tabellenklasse:

```php
use FriendsOfRedaxo\Fluid\Data\FluidBlocks;

yield FluidBlocks::new('inhalt')->label('Inhalt');
yield FluidBlocks::new('inhalt')->label('Inhalt')->blocks('richtext', 'media_text', 'video');   // nur diese Blöcke
```

- Bearbeiten in der YForm-Maske: Blöcke hinzufügen, bearbeiten (dieselben Felder wie auf der Website – Medienpool,
  Linkmap, Text-Editor), verschieben, entfernen. Gespeichert als JSON (`mediumtext`).
- Ausgabe: Die Detailansicht der Datenliste zeigt das Feld automatisch als Blöcke. In eigenen Templates:
  `echo \FriendsOfRedaxo\Fluid\BlockData::render($record->get('inhalt'));` – Klartext für Listen und Suche:
  `BlockData::text(…)`.
- Layout-Spalten gibt es im Feld nicht (sie brauchen Slices); Rechte: Modulrecht des Blocks wie auf den Seiten.

Für eigene Ausgaben: `PageSettings::override(['title' => …, 'description' => …, 'image' => …, 'canonical' => …])`
setzt den Seitenkopf aus einem Block heraus (z. B. eigene Detailansichten).

Vorlagen: `examples/yform/` (`TerminTable`, `NewsTable`, `AnfrageTable`, `AnfrageForm`) – nach `src/` kopieren, `console migrate`.

### Adressen, Domains, SEO

- Adresse = Slugs der Elternseiten + eigener Slug (Tabelle `rex_fluid_route`); der Slug bleibt beim Umbenennen gleich.
- Domains (Website → Domains): **Hauptadresse**, **weitere Domains** (301 auf die Hauptadresse), **Landing-Domains**
  (eigene Domain für eine Seite, optional mit Unterseiten, Canonical „eigene Domain“ oder „Spiegel“).
- `sitemap.xml` und `robots.txt` je Domain, Canonical, hreflang, schema.org-@graph (Organisation, WebSite, WebPage,
  Brotkrumen, Blöcke).
- Weitere Sprachen bekommen ein Präfix (`/en/…`); Sprachwahl im Kopf und im mobilen Menü zeigt nur Sprachen, in denen
  die Seite online ist (`Router::alternates($article)`).

### Sprachen

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

### Videos

- **Vorschaubild:** Media-Manager-Typ `fluid_poster` – bei einem Video ein typisches Standbild per ffmpeg (bei ~10 % der
  Laufzeit), bei einem Bild nur verkleinert; Ausgabe AVIF/WebP, gespeichert im Cache des Media Managers. Ohne ffmpeg
  gibt es nur gewählte Vorschaubilder. Pfad bei Bedarf über `FFMPEG_BIN` / `FFPROBE_BIN` in `.env`.
  `MediaManager::getUrl('fluid_poster', 'film.mp4')` funktioniert auch in eigenen Templates.
- **Untertitel und Kapitel:** `.vtt` oder `.srt` im Medienpool, im Block je Spur Sprache und Art. fluid liefert sie geprüft
  als WebVTT unter `/fluid-track/{datei}` aus (SRT umgewandelt; nur erlaubte WebVTT-Tags). Option „gleich einschalten“
  wählt die Spur in der Sprache der Seite; „Transkript“ zeigt den Text darunter (`<details>`).
- Eigene Ausgabe: `fluid_video_embed($url, $datei, $poster, $format, $titel, $spuren, $einschalten, $transkript)`
  bzw. `FriendsOfRedaxo\Fluid\Media\Tracks` (`parse()`, `toVtt()`, `transcript()`).

### Blog

Beiträge sind normale Artikel in einer Kategorie mit „Blog / News“ (Seiteneinstellungen der Menüseite) – Editor, Entwurf,
Versionen, Rechte, Adressen, Sprachen und Suche gelten wie überall. Dazu Datum, Autor, Schlagworte (`rex_fluid_page`);
Teaserbild = Bild für soziale Netzwerke, Kurztext = Beschreibung. Sortierung nach Datum, neueste zuerst.

- Beitragsseiten bekommen im Template Kopf (Titel, Datum, Autor, Schlagworte, Bild) und „Älterer/Neuerer Beitrag“
  (`fluid_post_header()`, `fluid_post_footer()` im Kit), schema.org `BlogPosting`, die Übersicht `Blog`.
- RSS 2.0 unter `/{blog}/feed.xml` (EP `FLUID_BLOG_FEED`), `<link rel="alternate">` im Kopf.
- Eigene Ausgaben: `Blog::posts($blogId, ['limit' => 3, 'tag' => 'team'])` liefert `Post`-Objekte
  (`article`, `date`, `author`, `tags`, `image`, `excerpt`, `url`); `Blog::blogOf($article)`, `Blog::tags($blogId)`.
- Feldtyp `blog` (Auswahl der Blogs) für eigene Blöcke.

### Karten

MapLibre GL und OpenFreeMap über den eigenen Proxy `/proxy/ofm/…` (Cache im Cache-Ordner des AddOns). Besucher verbinden
sich nicht mit Dritten. Detailkacheln gibt es nur rund um Orte, die auf der Website als Karte vorkommen.

### Konsole

| Befehl | |
|---|---|
| `fluid:demo --set=redaxo6 [--replace] [--page=slug] [--yform]` | Demo-Seiten, eine einzelne Seite anhängen, YForm-Beispieldaten |
| `fluid:search-index` | Suchindex neu aufbauen (sonst automatisch nach dem Veröffentlichen) |

### Rechte

fluid nutzt die Rechte des Cores – eine Rolle wirkt auf der Website genau wie im Backend:

- **Kategorien** (Strukturrecht) und **Module** (welche Blöcke) bestimmen, was jemand bearbeiten und einfügen darf.
- **Optionen** der Rolle schalten Aktionen frei: `version[live_version]` Veröffentlichen (ohne: „Entwurf – wartet auf
  Freigabe“), `moveSlice[]` Blöcke verschieben, `publishSlice[]` Blöcke online/offline, `addArticle[]`/`addCategory[]`
  neue Seiten bzw. Menüpunkte, `editArticle[]`/`editCategory[]` Seiteneinstellungen, `moveArticle[]`/`moveCategory[]`,
  `publishArticle[]`/`publishCategory[]`, `deleteArticle[]`/`deleteCategory[]`, `article2category[]` (Menüpunkt ja/nein),
  `copyContent[]` (Inhalt aus anderer Sprache). Die Leiste zeigt nur, was erlaubt ist; die API prüft jede Aktion.
- Design und Website-Einstellungen: Admins. YForm-Tabellen: Rechte von YForm (Tabellen ansehen/bearbeiten).

## Lizenz und Danke

MIT. Konzept und Teile des Codes aus KLXM Studio (MIT). Symbole: Phosphor Icons (MIT). Schriften: SIL Open Font License.
Karten: MapLibre GL JS (BSD-3), OpenFreeMap, Daten © OpenStreetMap-Mitwirkende (ODbL). Suche: Loupe (MIT).

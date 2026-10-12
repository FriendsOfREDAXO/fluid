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
- Seiten: Baum mit Ziehen, neue Seiten (leer, Kopie, Vorlage), Seiteneinstellungen mit Adresse und SEO-Angaben
- Website: Name, Logo, Kontakt, Öffnungszeiten, Social Media, Hinweisleiste, Rechtliches, Domains, Weiterleitungen
- Design-Editor (Admins), Bildbearbeitung (AddOn image_editor), Formulare und Datenlisten (YForm), Anfragen-Postfach
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

Das Kit **fluid** bringt Template und Blöcke mit. Im Backend erscheint über den Slices „Auf der Website bearbeiten“.

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
`placeholder`, `width` (`half`), `variants` (nur bei bestimmten Darstellungen).

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

- **Block „Formular“** und Formular im **Block „Kontakt“**: Die Redaktion wählt ein Formular. Prüfung, CSRF, Honigtopf
  und Aktionen (`SaveRecord`, `SendMail`, `ShowMessage` …) kommen aus der Formular-Klasse; fluid gibt es im Stil des Kits aus.
- **Block „Datenliste“**: eine Tabelle als Karten, Liste oder Tabelle mit Detailansicht (`?eintrag=ID`), Filter und
  Sortierung; „Bearbeiten“ öffnet die Maske von YForm.
- **Postfach „Anfragen“**: Einträge der Tabellen, in die die Formulare der Website speichern; Status neu/erledigt.

Vorlagen: `examples/yform/` (`TerminTable`, `AnfrageTable`, `AnfrageForm`) – nach `src/` kopieren, `console migrate`.

### Adressen, Domains, SEO

- Adresse = Slugs der Elternseiten + eigener Slug (Tabelle `rex_fluid_route`); der Slug bleibt beim Umbenennen gleich.
- Domains (Website → Domains): **Hauptadresse**, **weitere Domains** (301 auf die Hauptadresse), **Landing-Domains**
  (eigene Domain für eine Seite, optional mit Unterseiten, Canonical „eigene Domain“ oder „Spiegel“).
- `sitemap.xml` und `robots.txt` je Domain, Canonical, hreflang, schema.org-@graph (Organisation, WebSite, WebPage,
  Brotkrumen, Blöcke).
- Weitere Sprachen bekommen ein Präfix (`/en/…`).

### Karten

MapLibre GL und OpenFreeMap über den eigenen Proxy `/proxy/ofm/…` (Cache im Cache-Ordner des AddOns). Besucher verbinden
sich nicht mit Dritten. Detailkacheln gibt es nur rund um Orte, die auf der Website als Karte vorkommen.

### Konsole

| Befehl | |
|---|---|
| `fluid:demo --set=redaxo6 [--replace] [--page=slug] [--yform]` | Demo-Seiten, eine einzelne Seite anhängen, YForm-Beispieldaten |
| `fluid:search-index` | Suchindex neu aufbauen (sonst automatisch nach dem Veröffentlichen) |

### Rechte

Bearbeiten auf der Website: Strukturrecht der Kategorie und Modulrecht des Blocks (wie im Backend). Design und
Website-Einstellungen: Admins. YForm-Tabellen: Rechte von YForm (Tabellen ansehen/bearbeiten).

## Lizenz und Danke

MIT. Konzept und Teile des Codes aus KLXM Studio (MIT). Symbole: Phosphor Icons (MIT). Schriften: SIL Open Font License.
Karten: MapLibre GL JS (BSD-3), OpenFreeMap, Daten © OpenStreetMap-Mitwirkende (ODbL). Suche: Loupe (MIT).

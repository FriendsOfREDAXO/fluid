# fluid – Entwickler-Referenz

Technische Doku zu fluid für REDAXO 6. Einstieg und Installation: [README](../README.md).

## Inhalt

- [Ein eigener Block](#ein-eigener-block) · [Eigene Kits](#eigene-kits)
- [Extension Points](#extension-points)
- [YForm: Formulare und Datenlisten](#yform-formulare-und-datenlisten)
- [Blöcke in YForm-Tabellen](#blöcke-in-yform-tabellen-feld-fluid-blöcke)
- [Adressen, Domains, SEO](#adressen-domains-seo)
- [Sprachen](#sprachen) · [Videos](#videos) · [Blog](#blog) · [Karten](#karten)
- [Versionen](#versionen) · [MCP-Server](#mcp-server) · [E-Mail](#e-mail)
- [Suche](#suche) · [Neu laden auf Aktion](#neu-laden-auf-aktion) · [Live-Aktualisierung](#live-aktualisierung) · [Präsenz der Redaktion](#präsenz-der-redaktion)
- [Konsole](#konsole) · [Rechte](#rechte) · [Gastzugang](#gastzugang-für-addons) · [Bausteine](#bausteine-später-eigene-addons) · [Aufbau des AddOns](#aufbau-des-addons) · [Mitentwickeln](#mitentwickeln)


## Ein eigener Block

Ein Block ist ein Modul des Cores mit anderer Basisklasse: die Felder als Attribute an Eigenschaften, die Ausgabe in
`output()`. Der Core befüllt die Eigenschaften, speichert und prüft wie bei jedem Modul; fluid gibt Bearbeiten auf der
Website, Abschnitt (Hintergrund, Abstände, Sprungmarke), Darstellungen, Layout-Spalten und das Kit dazu. Im Projekt,
ohne das Kit zu ändern (`examples/block/`):

```php
// src/Block/Teaser.php
namespace Project\Block;

use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\Link;
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Form\Field\{Text, Textarea};

use function Redaxo\Core\View\escape;

#[AsModule('fluid.teaser', 'Teaser')]
#[AsBlock(icon: 'star', group: 'Inhalt', nestable: true)]   // nestable: darf in eine Layout-Spalte
final class Teaser extends FluidBlock
{
    #[Text('Überschrift', required: true, maxLength: 80)]
    public string $title = '';

    #[Textarea('Text', rows: 3)]
    public string $text = '';

    #[Link('Link')]                                           // Feld nur von fluid
    public string $link = '';

    public function output(): string
    {
        return '<div class="wrap"><h2 class="h3"' . $this->edit('title') . '>' . escape($this->title) . '</h2>'
            . '<p' . $this->edit('text', 'lines') . '>' . nl2br(escape($this->text), false) . '</p></div>';
    }
}
```

- `$this->edit('feld')` macht ein Feld direkt auf der Seite bearbeitbar (`'lines'` mehrzeilig, `'rich'` mit
  Formatierung; in Listen `items.0.title`). `$this->context()` ist der Block mit Darstellung (`variant()`) und Abschnitt.
- Ohne eigenes `output()` gibt der Block die View seines Kits aus: `views/{typ}.php` mit `$b` (Block), `$d` (Werte als
  Array, mit Standardwerten) und `$m` (das Modul mit den typisierten Eigenschaften). So haben die Blöcke von fluid ihre
  Ausgabe im Kit, damit jedes Kit sie anders gestalten kann. Den Abschnitt (`<section>`) legt fluid um die Ausgabe.
- Gespeichert wird wie beim Core: in der JSON-Spalte `data` des Slices je Feld unter seinem Schlüssel (Name der
  Eigenschaft in snake_case, `$buttonLink` → `button_link`, anders mit `key:`), dazu `variant` (Darstellung) und `tunes`
  (Abschnitt). Echte Slices mit Rechten, Arbeitsversion, Historie und Verwendungsliste (Medien, Seiten) des Cores.
  Datenbanken mit dem früheren Format von fluid (`{values, tunes}`) stellt `console fluid:upgrade:slices` um.
- Was den Blocktyp betrifft, ist statisch: `kit()`, `fields()`, `definition()`, `label()`, `variants()`.
  `BlockModule::classFor($key)` liefert die Klasse eines Blocks, `BlockModule::all()` alle; `->data()` die Werte eines
  Slices als Array.

**Vom Modul des Cores zum Block:** von `FluidBlock` statt `Module` erben, Schlüssel mit `fluid.`, `#[AsBlock]` an die
Klasse – Felder und `output()` bleiben, wie sie sind. Für das Bearbeiten direkt auf der Seite `$this->edit('feld')` an
die Elemente setzen. Vorhandene Slices behalten ihren Modulschlüssel; neue Inhalte kommen mit dem Block.

**Felder.** Alle Felder des Cores (`Redaxo\Core\Form\Field\…`) funktionieren in Blöcken und in der Seitenleiste – auch
in gewöhnlichen Modulen. fluid bietet dieselben Felder unter **gleichem Namen** in `FriendsOfRedaxo\Fluid\Field\…` an: als
Unterklassen mit allen Parametern des Cores und **zusätzlichen, optionalen** Parametern. Ohne sie verhalten sie sich
genau wie die des Cores; der Core kann sie überall verwenden (im Backend zeigt er sein eigenes Formular).

| Feld (Core) | Ergänzungen von fluid (optional) |
|---|---|
| `Text`, `Email`, `Url`, `Textarea` | `placeholder`, `search: false` (nicht in den Suchindex; Text/Textarea) |
| `Number`, `Checkbox`, `Choice`, `DatePicker`, `TimePicker`, `DateTimePicker`, `ArticlePicker` | – |
| `MediaPicker` | `preview: true` (Core) zeigt in fluid ein Bildfeld mit Bildbearbeitung, sonst eine Dateiauswahl |
| `Collection` | `itemLabel` (Bezeichnung eines Eintrags, „+ Karte“), `titleField` (Feld als Titel des zugeklappten Eintrags) |
| alle | `width: 'half'` (halbe Breite in der Seitenleiste), `variants: ['…']` (nur in diesen Darstellungen) |

Warum Ergänzungen: Die Seitenleiste ist schmal und zeigt je Darstellung andere Felder – Breite und Darstellung gibt es
im Formular des Cores nicht. `placeholder` fehlt dem Core bei Textfeldern; `search` steuert die Suche von fluid;
`itemLabel` und `titleField` machen lange Listen in der Seitenleiste übersichtlich.

**Wiederholgruppen** sind die `Collection` des Cores: jeder Eintrag ist ein Objekt einer kleinen Klasse mit eigenen
Feldern, die Ausgabe bekommt eine Liste dieser Objekte, gespeichert wird eine Liste der Werte je Schlüssel.

```php
final class Card
{
    #[MediaPicker('Bild', preview: true)]
    public ?Media $image = null;

    #[Text('Titel', required: true, maxLength: 90)]
    public string $title = '';
}

/** @var list<Card> */
#[Collection('Karten', item: Card::class, itemLabel: 'Karte', titleField: 'title', max: 12)]
public array $items = [];
```

**Felder nur von fluid** (eigene Namen; Unterklassen von `Text`, `Textarea` bzw. `Choice` des Cores):

| Feld | | Wert |
|---|---|---|
| `RichText` | formatierter Text | bereinigtes HTML |
| `Link` | Seite (Linkmap), `#anker`, `https://…`, `mailto:`, `tel:` | `redaxo://12` bzw. Adresse |
| `Icon`, `Geo` | Symbol des Kits, Ort mit Adresssuche | Name, „Breite, Länge“ |
| `MediaCategory`, `Blog` | Kategorie des Medienpools, Blog | ID |
| `YFormTable`, `YFormForm`, `YFormField` | Tabelle, Formular-Klasse, Feld der gewählten Tabelle | Name |

Auswahlen aus Code: `choices: static function (): array { return Klasse::liste(); }` (wie beim Core; Pfeilfunktionen
sind in Attributen nicht erlaubt) oder ein `ChoiceProvider`.

**Block** (`#[AsBlock]`): `icon`, `group`, `help`, `label` (Standard: Name aus `AsModule`), `variants` (Darstellungen,
erste = Standard), `variantHelp`, `nestable` (`true` oder Liste der Varianten), `background`, `requires` (`'yform'`),
`reload` (Teil der Seite ohne Neuladen aktualisieren), `jsonld` (strukturierte Daten, z. B.
`['type' => 'faq', 'items' => 'items', 'question' => 'q', 'answer' => 'a']`), `drags` (Greifer auf der Seite:
`[['field' => 'measure', 'target' => '.prose', 'class' => 'prose--m-{v}', 'label' => 'Textbreite']]`).

Beschriftungen und Darstellungen ändern, ohne die Klasse anzufassen: Kit (`kit.php` → `'blocks'`) oder Extension Point
`FLUID_BLOCK_DEFINITION`. Neue Felder gehören als Eigenschaften in die Klasse (bzw. eine abgeleitete) – nur sie speichert
der Core.

**Gewöhnliche Module in der Seitenleiste:** fluid zeigt die Felder jedes Moduls mit Feld-Attributen (auch `Collection`
und die Felder von fluid) in seinem Formular und speichert über das Formular des Cores – mit dessen Prüfungen und
`validate()`. Nur Module mit eigenem `input()` und Mehrfachauswahlen von Medien bzw. Seiten öffnen das Formular des Cores.

## Eigene Kits

Ein Kit bestimmt die Gestaltung der ganzen Website: Seitenrahmen, CSS, Ausgabe der Blöcke, Design-Optionen. fluid bringt
das Kit `fluid` mit. Weitere Kits liegen im Projekt oder kommen als Composer-Paket; welches gilt, wählen Admins unter
**Website → Kit** (der Reiter erscheint, sobald es mehr als ein Kit gibt).

Mitgeliefert sind zwei Kits: **`fluid`** (Grund-Kit) und **`native`** („NativeCSS“, `kits/native/`). Das zweite ist zugleich
das Beispiel für ein abgeleitetes Kit: eigener Seitenrahmen und eigene Views für Einstieg, Karten, Text + Bild,
Handlungsaufruf und FAQ mit den Komponenten von [NativeCSS](https://github.com/skerbis/nativecss); alle übrigen Blöcke
und seltene Varianten kommen von `fluid`. Die Reihenfolge der Cascade Layers (`tokens, reset, base` von NativeCSS, dann
das Grund-CSS von fluid als Layer `fluid`, dann `helpers, components`, dann `native`) steht in `kits/native/template.view.php`,
die Brücke von der Design-Leiste (`--f-…`) zu den Tokens von NativeCSS (`--ncss-…`) in `assets/css/native.css`.

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

- `views/{typ}.php` – Ausgabe eines Blocktyps (`$b` = Block, `$d` = Daten, wie beim eigenen Block); die Felder stehen
  in den Blockklassen (`kits/fluid/src/Module/`)
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
    'blocks' => [                                                // je Blocktyp über die Blockklasse gelegt
        'hero' => ['variants' => ['leaf' => 'Mit Blatt']],          // neue Variante, Ausgabe in views/hero.php
    ],
    'design' => $parent['design'],                              // Style-Editor; eigene Vorlagen unter ['presets']
];
```

`backgrounds` und `assets` werden je Schlüssel ergänzt, `css` wird angehängt, alle anderen Einträge ersetzt. `blocks` legt
sich über die Definition aus der Blockklasse: `variants` und `variant_help` werden ergänzt, `fields` angehängt, alles
andere (z. B. `label`, `help`) ersetzt.

**Design-Werte** speichert jedes Kit für sich. Solange ein abgeleitetes Kit keine eigenen hat, übernimmt es die seines
Eltern-Kits – nach dem Wechsel sieht die Website also zunächst aus wie vorher.

**Assets** veröffentlicht fluid nach `assets/fluid-kits/{kit}/`: bei `console migrate` komplett, im Betrieb je Datei,
sobald sie fehlt oder sich geändert hat. Relative Pfade im CSS zeigen auf `assets/` des eigenen Kits.

### Als Composer-Paket

Ein Kit für mehrere Projekte ist ein eigenes Composer-Paket – und zwar ein **AddOn** (Typ `redaxo-addon` mit
AddOn-Klasse): REDAXO sucht Klassen mit Attributen (`#[AsKit]`) nur im Projekt und in aktiven AddOns, nicht in
beliebigen Paketen unter `vendor/`. Die Dateien des Kits (`kit.php`, `views/`, `assets/` …) liegen neben der Kit-Klasse.

```json
{
    "name": "acme/fluid-kit-nature",
    "type": "redaxo-addon",
    "require": { "friendsofredaxo/fluid": "*@dev" },
    "autoload": { "psr-4": { "Acme\\FluidKitNature\\": "src/" } },
    "extra": { "redaxo": { "addon-class": "Acme\\FluidKitNature\\NatureAddon" } }
}
```

`src/NatureAddon.php` ist eine leere AddOn-Klasse (`final class NatureAddon extends Addon {}`), `src/NatureKit.php` die
Kit-Klasse, daneben `src/kit.php`, `src/views/` und `src/assets/`. Nach `composer require` und `console migrate` steht
das Kit in der Auswahl. Ein Kit kann auch von einem anderen Kit als `fluid` erben (`parent: 'nature'`).

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
| `FLUID_STRUCTURED_DATA` | JSON-LD (`@graph`) | `article`, `url`, `org`, `lang` | schema.org ergänzen |
| `FLUID_SITE_TRANSLATIONS` | Übersetzungen (array) | `code` | feste Texte des Kits für eine Sprache ergänzen/ändern |
| `FLUID_EDITOR_CONFIG` | Einstellungen des Editors | `article`, `user` | Blöcke/Menüpunkte für Rollen ausblenden |
| `FLUID_KIT` | Schlüssel des Kits | – | Kit je Anfrage wählen, z. B. je Domain |

Das Routing hat eigene Extension Points als Klassen (wie der Core), Namespace `FriendsOfRedaxo\Fluid\Routing\ExtensionPoint`:

| Klasse | Subject | Eigenschaften | Zweck |
|---|---|---|---|
| `ArticleMeta` | `PageMeta` | `article`, `match` | Meta-Angaben der Seite, bevor Eintrag und Overrides dazukommen (fluid trägt hier die SEO-Felder ein) |
| `PageMetaResolved` | `PageMeta` | `article`, `match` | fertige Meta-Angaben kurz vor der Ausgabe – ändern, eigene Tags, JSON-LD |
| `SitemapEntries` | Einträge (`loc`, `lastmod`, `alternates`, `images`) | `landing` | Adressen der sitemap.xml ergänzen, ändern, entfernen |
| `RobotsTxt` | Text | `part` (`rules` bzw. `text`) | Regeln ersetzen (`rules`, fluid setzt hier den eigenen Text der Website) bzw. fertigen Text ergänzen |
| `RoutesRebuilt` | – | `source` | Adressen wurden neu berechnet (Seiten bzw. eine Quelle) |

Angemeldet wie im Core, z. B. als Methode mit `#[AsExtension]` – der Name kommt aus dem Typ des Parameters:

```php
#[AsExtension]
public static function head(PageMetaResolved $ep): PageMeta
{
    return $ep->subject->add(HeadTag::meta('theme-color', '#1f7a4d'));
}
```

Dazu nutzt fluid die Extension Points des Cores, z. B. `ArticleUrl` für die Adressen.

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

Der Rewriter liegt in `src/Routing/` (Namespace `FriendsOfRedaxo\Fluid\Routing`) und kennt nichts von fluid – er lässt
sich als eigenes AddOn herauslösen oder später durch yrewrite und ein URL-AddOn ersetzen (`src/Routing/README.md`).
Eingestellt wird er wie REDAXO 6 selbst im Code, ein Backend braucht er nicht.

**So arbeitet er**

- **Feste Adressen:** Jede Seite hat je Sprache einen Slug (Tabelle `rex_fluid_route`). Er entsteht beim Anlegen aus dem
  Namen und bleibt beim Umbenennen gleich; ändern lässt er sich in den Seiteneinstellungen. Adresse = Slugs der
  Elternseiten + eigener Slug. Gleiche Slugs auf einer Ebene werden durchnummeriert (`-2`, `-3` …).
- **Neu berechnet** wird nach jeder Strukturänderung (Artikel, Kategorien, Status, Sprachen – per `#[AsExtension]` am
  Router). Geänderte Adressen online stehender Seiten bekommen automatisch eine 301-Weiterleitung (`redaxo://ID`).
- **Eine Adresse je Seite:** Andere Schreibweise, Schrägstrich am Ende, eine andere Endung, `/EN/` statt `/en/`, die
  natürliche Adresse der Startseite (`/start`) und alte Links `?article_id=…` leiten per 301 auf die kanonische Adresse.
  POST-Anfragen werden nie umgeleitet.
- **Aufgelöst** wird bei `PACKAGES_INCLUDED` (früh), wenn alle AddOns geladen sind – ihre Quellen und Endpunkte sind dann
  bekannt, egal in welcher Reihenfolge sie starten. Ablauf: frühe Endpunkte · Alias-Domain → 301 · Endpunkte · Gates ·
  `?article_id` → 301 · Sprachpräfix · Endung weg · Seite, Eintrag einer Quelle oder Resolver · kanonische Adresse
  prüfen · Weiterleitungen · 404 (mit Protokoll).
- **Cache:** Die Adressen der Seiten liegen als PHP-Datei im Cache (`var/cache/routing/pages.php`, OPcache), die
  Einträge jeder Quelle in einer eigenen Datei, die erst geladen wird, wenn eine Adresse der Quelle gebraucht wird. Eine
  Adresse, die keine Seite ist, kostet eine indizierte Abfrage. `console cache:clear` leert ihn, er entsteht von selbst neu.

**Einstellen** in `Project::configure()`:

```php
use FriendsOfRedaxo\Fluid\Routing\{Router, RoutingConfig, UrlFormat};
use FriendsOfRedaxo\Fluid\Routing\Seo\{Head, HeadConfig, HeadTag};

Router::$config = new RoutingConfig(
    format: UrlFormat::mixed(),   // none() /a/b · slash() /a/b/ · html() /a/b.html · mixed() /a/ + /a/b.html · suffix('.htm')
    cache: true,                  // Adressen als PHP-Datei im Cache
    redirectBy: 'REDAXO',         // Kopfzeile X-Redirect-By ('' = keine)
    enabled: true,                // false: Rewriter ganz aus (z. B. wenn yrewrite die Adressen übernimmt)
);
Head::$config = new HeadConfig(
    siteName: null,               // og:site_name und Titel; fluid setzt den Namen aus Website → Allgemein
    tags: [HeadTag::meta('theme-color', '#1f7a4d')],   // auf jeder Seite
    twitterCard: 'summary_large_image',               // null = kein twitter:card
);
```

Gespeichert wird immer ohne Endung – ein Wechsel des Formats braucht keinen Neuaufbau, alte Schreibweisen (auch `.html`
und `.htm`) leiten per 301 um. Startseiten (`/`, `/en/`) haben nie eine Endung. Für ganz eigene Regeln lässt sich
`UrlFormat` ableiten (`append()`, `strip()`).

**Eigene Adressen für Einträge (Quellen)**

News, Termine, Produkte … bekommen eine Adresse unter ihrer Seite – gespeichert wie Seiten (`rex_fluid_route_entry`),
eindeutig gegen Seiten und andere Einträge, mit 301 nach einer Änderung (`route://quelle/ID`), in der Sitemap (mit
hreflang und Bildern) und mit Canonical, hreflang, Open Graph und schema.org im Seitenkopf. Die Seite bekommt die
Parameter des Eintrags (Request und `$_GET`) und gibt ihn aus; gibt es den Eintrag nicht mehr, kommt 404.

Aus einer Tabelle (auch YForm) nur mit Attributen – REDAXO findet die Klasse selbst (`examples/routing/NewsRoutes.php`):

```php
#[AsRouteSource('news', label: 'News')]
#[FromTable('rex_news', page: 12, where: 'status = 1', param: 'news_id')]   // language: 'clang_id' – sonst alle Sprachen
#[Slug('title')]            // withId: true → /aktuelles/titel-12
#[Title('seo_title')]       // sonst die Slug-Spalte
#[Description('teaser')]    // ohne HTML, gekürzt
#[Image('image')]           // Datei(en) des Medienpools – die erste zählt
#[Modified('updatedate')]   // Zeitstempel oder Datum
final class NewsRoutes extends TableSource {}
```

Für alles andere eine eigene Quelle mit `entries()` (und optional `meta()`, `exists()`, `editable()`, `save()`):

```php
#[AsRouteSource('events', label: 'Termine')]
final class EventRoutes extends RouteSource
{
    public function entries(): iterable
    {
        foreach (Event::upcoming() as $e) {
            yield new RouteEntry('events', (string) $e->id, $e->languageId, articleId: 7,
                slug: $e->date->format('Y') . '/' . $e->title, params: ['event' => $e->id], modified: $e->updated);
        }
    }

    public function meta(RouteEntry $entry): ?PageMeta
    {
        $e = Event::get((int) $entry->id);

        return new PageMeta(title: $e->title, description: $e->teaser, image: $e->image, type: 'article');
    }
}
```

```php
Router::urlFor('news', $id);            // Adresse (aktuelle Sprache), Router::urlFor('news', $id, 2) in Sprache 2
Router::canonicalFor('news', $id, 1);   // absolut
Routes::rebuildSource('news');          // nach dem Speichern (alle Einträge der Quelle)
Routes::put($entry); Routes::remove('news', $id);   // einzelne Einträge
Router::current()?->entry;              // Eintrag der aktuellen Adresse (oder null)
```

Konsole: `routing:rebuild` (Seiten und alle Quellen), `routing:rebuild --source=news`.

**Bearbeiten auf der Website:** Spalten mit `#[Slug]`, `#[Title]`, `#[Description]` und `#[Image]` (Standard
`editable: true`) zeigt fluid in den Seiteneinstellungen der Detailseite über den Feldern der Seite. Gespeichert wird
bei YForm-Tabellen über die Record-API (Prüfungen und Zeitstempel der Tabelle greifen, Recht auf die Tabelle nötig),
sonst über `RouteSource::save()`; danach wird die Quelle neu berechnet und der Editor springt auf die neue Adresse.

**Dynamische Adressen** ohne Speicherung (z. B. die Detailseiten der Datenlisten `/seite/titel-ID`) liefern einen
`RouteMatch` – mit kanonischer Adresse (abweichende Anfragen → 301), Parametern, Meta-Angaben und Sprachfassungen:

```php
Router::resolver(static function (string $path, int $lang, array $query): ?RouteMatch {
    // $path ohne Sprachpräfix und Endung
    return …matches… ? new RouteMatch($pageId, $lang, ['item' => $id], url: $canonical, meta: new PageMeta(title: …)) : null;
});
```

**Seitenkopf** (`Routing\Seo\Head`, im Template `Head::title()` und `Head::render()` – bzw. die Kurzformen
`PageSettings::title()`/`head()`): Titel, Beschreibung, robots, Canonical, hreflang samt `x-default`, `og:type`,
`og:url`, `og:title`, `og:description`, `og:site_name`, `og:locale` (+ `og:locale:alternate`), `og:image` mit Typ,
Maßen und Alt-Text, `article:published_time`/`modified_time`, `twitter:card` und JSON-LD. Von X/Twitter kommt nur
`twitter:card` – Titel, Text und Bild liest X aus Open Graph.

Woher die Angaben kommen (leere Werte überschreiben nie): Seite (Name, Adresse, Sprachen) → `ArticleMeta` (fluid: SEO-Titel,
Beschreibung bzw. Standard der Website, Bild, „nicht indexieren“) → Eintrag der Quelle bzw. `RouteMatch` → `Head::override()`
(Blöcke; `PageSettings::override()` ist die Kurzform) → `PageMetaResolved` (fluid: Bild für soziale Netzwerke mit
image_editor 1200 × 630 als JPEG um den Fokuspunkt, schema.org-@graph).

Eigene Tags: `HeadTag::meta()`, `::property()`, `::link()`, `::html()` (nur aus Code) und `::remove('property:og:image')`.
Gleicher Schlüssel ersetzt – so lässt sich jede Angabe des Rewriters überschreiben. Anmelden über `HeadConfig::$tags`
(jede Seite), `Head::add()` (diese Anfrage) oder `$ep->subject->add()` in `PageMetaResolved`.

**Domains, Sitemap, robots.txt**

- Domains (Website → Domains): **Hauptadresse**, **weitere Domains** (301 auf die Hauptadresse), **Landing-Domains**
  (eigene Domain für eine Seite, optional mit Unterseiten, Canonical „eigene Domain“ oder „Spiegel“).
- `sitemap.xml` je Domain (Hauptadresse bzw. Landing-Domain): Seiten, die online sind (samt Eltern), nicht auf „nicht
  indexieren“ stehen und deren Canonical auf diese Domain zeigt – mit `lastmod` (Änderungsdatum mit Uhrzeit), hreflang samt
  `x-default` (Startsprache), bis zu fünf Bildern je Adresse (Vorschaubild der Seite, Bilder der veröffentlichten Blöcke),
  den Einträgen der Quellen und den Detailseiten von Datenlisten. Ab 45 000 Adressen ein Sitemap-Index mit
  `/sitemap-1.xml`, `/sitemap-2.xml` … Im Browser zeigt `/sitemap.xsl` die Adressen als Tabelle; Sitemap und robots.txt
  kommen mit `Cache-Control: no-cache`. Ergänzen:

  ```php
  Sitemap::exclude(static fn (int $id, int $lang): bool => …);                    // Seite weglassen
  Sitemap::images(static fn (int $id, int $lang): iterable => ['/media/x.jpg']);    // Bilder einer Seite
  Sitemap::children(static fn (int $id, int $lang, string $loc): iterable => …);  // dynamische Adressen unter einer Seite
  Extension::register(SitemapEntries::class, static fn (SitemapEntries $ep) => [...$ep->subject, ['loc' => 'https://example.org/extra', 'lastmod' => null, 'alternates' => []]]);
  ```
- `robots.txt` je Domain mit Zeile „Sitemap:“; eigener Text unter Website → Recht & Suchmaschinen. Optional (Haken
  „Linkvorschau beim Teilen erlauben …“, Standard aus; im Code `Robots::allowPreviews()`): Sperrt der Text die ganze
  Website (`User-agent: *` + `Disallow: /`), dürfen Linkvorschauen trotzdem lesen (`Robots::PREVIEW_BOTS`: Facebook,
  LinkedIn, WhatsApp …) – Suchmaschinen bleiben draußen. Eigene Regeln für diese Bots haben Vorrang.
- Erstes Bild im ersten Block der Seite: ohne Angabe im Kit sofort und mit `fetchpriority="high"` (Einstieg, LCP);
  `img(…, ['eager' => false])` im Kit hat Vorrang.
- Weitere Sprachen bekommen ein Präfix (`/en/…`); die Sprachwahl zeigt nur Sprachen, in denen die Seite online ist
  (`Router::alternates($article)` – auf der Adresse eines Eintrags dessen Sprachfassungen).

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

Zwei Arten von Ständen in der Historie-Tabelle des Cores (`rex_article_slice_history`, Spalte `revision`), je Seite,
Sprache und Fassung die letzten 40 (`Versions::KEEP`):

- **Veröffentlicht (Archiv, Live-Version):** Jede Live-Schaltung legt den Stand an, der live geht. Lag die bisherige
  Live-Version noch nicht im Archiv (z. B. im Backend direkt geändert), wird sie vorher gesichert („Vor dem
  Veröffentlichen“). Geht etwas unverändert noch einmal live, entsteht kein neuer Stand.
- **Entwurf (Zwischenstände der Arbeitsversion):** beim Speichern (zusammengefasst, solange dieselbe Person innerhalb von
  2 Minuten weiterarbeitet), vor dem Verwerfen und vor dem Wiederherstellen. Sie waren nie online und werden bei der
  Live-Schaltung geleert (`Versions::publish()`).
- **Wiederherstellen** holt jeden Stand in den Entwurf; live geht er erst mit „Veröffentlichen“, Korrekturen davor sind
  möglich. Ohne Arbeitsversion (System → Einstellungen) schreibt es direkt live.
- Die Historie unterscheidet Stände nur nach ihrem Zeitpunkt (Sekunde) – fluid weicht auf die nächste freie Sekunde aus.
- Die Ansicht zeigt beide Arten getrennt (Seitenleiste: zwei Listen, Zeitleiste: Kennzeichen „Entwurf“/„Veröffentlicht“).
  Die Historie im Backend des Cores zeigt nur die veröffentlichten Stände.

- Ansicht: `assets/editor/versions.js` + `versions.css` (aus KLXM Studio übernommen), Daten von `?fluid_api=version_view`.
- Vorschau eines Stands: `?fluid_version=now|live|<revision>-<YmdHis>` rendert die Seite aus der Historie. Dafür
  tauscht `ArticleSlicesQuery` die Tabelle gegen die Zeilen des Stands. Die Vorschau gibt es nur mit Recht auf die Seite,
  ohne Leiste und nie aus dem Cache. `&fluid_mark=1` setzt `data-vdiff="new|changed"` am äußersten Element geänderter
  Blöcke (`SliceOutput`).
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

## E-Mail

fluid nutzt den Mailer des Cores (`Core::getMailer()`, Symfony Mailer). Eingerichtet wird er in `.env.local`:

```dotenv
MAILER_DSN="smtp://benutzer:passwort@smtp.example.org:587"   # null://null = Mails verwerfen (Demo, Test)
REX_MAILER_FROM="Website <noreply@example.org>"             # Absender, wenn eine Mail keinen eigenen hat
REX_MAILER_RECIPIENTS="test@example.org"                    # Staging: alle Mails nur hierhin
REX_MAILER_LOG=errors                                       # none | errors | all → var/log/mail.log
REX_MAILER_ARCHIVE=1                                        # Mails als .eml aufbewahren (auch nicht gesendete)
```

Antwortet der Mailserver nicht, wartet PHP bis `default_socket_timeout` (Standard 60 s) – in der php.ini kürzer stellen.
Ab Symfony 8.2 geht das auch in der DSN: `smtp://…?timeout=10`.

- **Statusseite** (AddOns → fluid → Status → „E-Mail“): Versand, Absender, Umleitung, Protokoll, Archiv, Testmail,
  die letzten Fehler und nicht gesendete Mails mit „Erneut senden“ – an die ursprünglichen Empfänger samt Bcc, sofern der
  Core sie als `X-Envelope-To` im Archiv vermerkt (redaxo/core#6755), sonst an An und Kopie. `Bcc` und `X-Envelope-To`
  werden vor dem Senden aus dem Kopf entfernt.
- **Formulare** (Block „Formular“) senden HTML-Mails im Stil der Website – Logo (kein SVG, das zeigen Gmail und
  Outlook nicht), Akzentfarbe des Kits, Anschrift im Fuß – mit Textteil für Mailprogramme ohne HTML.
- **Mailfehler bei Formularen** verwerfen die Eingabe nicht: Ist der Eintrag gespeichert, landet der Fehler im
  Systemprotokoll und in der Aktivität („Formular-Mail nicht gesendet“), die Bestätigung wird trotzdem versucht und
  Besucher sehen die Erfolgsmeldung – mit Hinweis, falls die Bestätigung nicht rausging (`Data\SafeMail`).
- **Aufbewahrung:** Der Cronjob `fluid_mail_archive_purge` (täglich, `#[AsCronjob]`) löscht archivierte Mails nach
  „Gesendete Mails aufbewahren (Tage)“ (Website → Recht & Suchmaschinen; Standard 90, 0 = nie).
  Dazu muss der System-Cron jede Minute `php bin/console cronjob:run` aufrufen.
- Eigene Mails im Stil der Website: `Mail::layout($titel, $html)` liefert die HTML-Hülle.

```php
Core::getMailer()->send(new Email()->to('info@example.org')->subject('Neue Bestellung')
    ->html(\FriendsOfRedaxo\Fluid\Mail\Mail::layout('Neue Bestellung', '<p>…</p>')));
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
// an der Blockklasse: #[AsBlock(reload: true)]
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
- Der Server (`Live::block()`, über `FE_OUTPUT`) rendert nur Blöcke mit `reload` (bzw. `'live'` in der Definition) der veröffentlichten
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
  nach. Ohne Bearbeitungssperre ist „Trotzdem speichern“ erlaubt, der vorige Stand bleibt als Version.
- Im Seitenbaum ein Kürzel an Seiten, die gerade jemand bearbeitet. Unter „Verlauf & Entwürfe“ → Aktivität die Liste
  „Gerade aktiv“.
- Nur für angemeldete Backend-Benutzer mit Recht auf die Seite. Gespeichert werden nur Name, Ort und Zeitpunkt der letzten
  Meldung (Cache des AddOns, 45 Sekunden), es gibt kein Protokoll. Abschalten: Config `presence` des AddOns auf `false`.

**Bearbeitungssperre** (`EditLock`, aus Studio; Website → Einstellungen → Redaktion, Standard aus, braucht die Präsenz):
Eine Seite in einer Sprache bearbeitet immer nur eine Person. Gesperrt hält, wer sie am längsten ohne Unterbrechung im
Bearbeiten-Modus hat – abgeleitet aus der Präsenz, ohne eigene Tabelle und ohne hängende Sperren (frei spätestens etwa
45 Sekunden nach dem Schließen).

- Andere sehen die Seite nur an, auch mit `?fluid_edit=1` (`Editing::requested()`), mit Hinweiszeile unter der Leiste;
  wird sie frei: „Jetzt bearbeiten“.
- Speichern lehnt fluid mit HTTP 423 ab – alle Aktionen des Editors, die die Seite ändern (`Api::LOCKED`), die
  Seitenleiste gewöhnlicher Module und MCP (Schlüssel ohne Person gelten als fremd). Gäste und Abläufe ohne Person
  (Konsole, Cronjob) sperrt nichts; das Backend des Cores kennt die Sperre nicht.
- Administratoren können übernehmen („Übernehmen“ in der Hinweiszeile): Ihr Eintrag rückt vor den bisherigen Inhaber,
  dem der Editor beim nächsten Herzschlag sagt, dass er nicht mehr speichern kann. Vermerk in der Aktivität.
- Im Code: `EditLock::on()`, `holder($article)`, `blockedFor($article, $userId)`, `assert($article, $userId)`,
  `take($article, $userId)`; Selbsttest in `console fluid:selftest`.

Teile:

| Teil | Aufgabe |
|---|---|
| `src/Presence.php` | Speicher: `beat($who, $meta)`, `leave($who)`, `list($filter)`, `setSince($who, $since)`; Schlüssel `site/type/id/user`, kennt keine Artikel |
| `src/EditLock.php` | Bearbeitungssperre aus der Präsenz: Inhaber, Prüfung beim Speichern (423), Übernehmen |
| `src/PresenceApi.php` | Anbindung: Endpunkt `/fluid-presence`, Rechte, „zuletzt gespeichert“, Attribute für die Leiste |
| `assets/editor/presence.js` | Oberfläche, nur über data-Attribute und Ereignisse gekoppelt |
| `Live` | Kanal `presence:default` – Kommen und Gehen sofort statt nach 15 Sekunden |

Schema (wie in Studio):

```
POST /fluid-presence  {type: "article", id: "{sprache}:{artikel}", label?, url?, leave?, peek?, _csrf?}
POST /fluid-presence  {…, take: 1}   Bearbeitungssperre übernehmen (Administration)
                      → {on, me, here: [Person], last: Stand|null, lock: {on, by: Person|null, mine, canTake}, live: Abo}
GET  /fluid-presence  → {on, me, items: [{type, id, label, url, …Person}], live: Abo}
Person = {user, name, initials, avatar, since, at, self}     Stand = {user, name, at, self}
```

- `[data-presence-here]` mit `data-presence-endpoint/-type/-id/-label/-url/-csrf/-live/-texts/-editing` – meldet sich
  alle 15 Sekunden (im Ansehen-Modus nur `peek`), beim Verlassen per `sendBeacon` ab.
- `[data-presence-list]` mit `data-presence-type` und `-prefix`; Einträge `[data-id]` bekommen ein Kürzel in `[data-presence-slot]`.
- `[data-presence-board]` – Liste „Gerade aktiv“, `[data-presence-board-box]` wird ohne Einträge ausgeblendet.
- Später eingefügte Listen (Seitenleisten): Ereignis `cms:presence-scan` am `document`.
- Konflikt: Der Editor löst vor schreibenden Aktionen `cms:before-save` aus (`detail.waitUntil(promise)`; `false` bricht ab).
  Die Rückfrage läuft über `window.FluidEditor.ask`. Ereignis `cms:presence` am `document`: `{here, last, conflict, lock}`.
- Gesperrt: `[data-presence-here]` trägt `data-presence-locked="1"`, die Hinweiszeile `.cms-lock-note` (mit
  `[data-presence-take]`) liegt in derselben Wurzel wie die Leiste.

Einbau in andere Systeme: `Presence.php` und `presence.js` kopieren, einen Endpunkt nach dem Schema oben schreiben
(Rechte prüfen, `beat`/`leave`/`list`, „zuletzt gespeichert“ aus der eigenen Historie) und die Attribute ausgeben.
Ohne Live-Kanal funktioniert es auch, dann mit 15 Sekunden Verzögerung.

## Konsole

| Befehl | |
|---|---|
| `fluid:demo --set=redaxo6` | Demo-Website anlegen (nur in einer leeren Installation; YForm-Beispieldaten gleich mit) |
| `… --media` | Demo-Bilder des Kits in den Medienpool übernehmen |
| `… --design=redaxo` | Design-Vorlage setzen (`redaxo` zusätzlich mit REDAXO-Logo aus dem Core); andere: `kanzlei`, `handwerk`, `praxis`, `aurora` … – auf einer Website mit Seiten nur die Vorlage |
| `… --design=native --kit=native` | Design-Vorlage für ein bestimmtes Kit setzen (Standard: das gewählte) |
| `… --replace` | vorher **alle** Seiten löschen – nur Testinstallationen |
| `… --page=slug` | nur eine Demo-Seite anhängen, z. B. `--page=projekte` |
| `… --yform` | nur die Beispielzeilen der YForm-Tabellen schreiben |
| `fluid:kit:create name` | eigenes Kit im Projekt anlegen (`--label`, `--parent`, `--view=hero,cards`, `--template`) |
| `fluid:search-index` | Suchindex neu aufbauen (sonst automatisch nach dem Veröffentlichen) |
| `fluid:selftest` | Live-Aktualisierung und Präsenz prüfen |
| `fluid:upgrade:slices` | Blöcke älterer REDAXO-6-Datenbanken in die Spalte `data` übernehmen (`--drop-old` entfernt die alten Spalten) |

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

## Bausteine (später eigene AddOns)

fluid ist aus Bausteinen gebaut, die sich später einzeln als eigene AddOns herauslösen lassen – so wie der Bildeditor,
der schon ein eigenes AddOn ist ([image_editor](https://github.com/FriendsOfREDAXO/image_editor)). Damit das ohne
Umbau geht, gelten feste Regeln:

1. **Abhängigkeiten zeigen nur in eine Richtung:** Bausteine nutzen den Kern von fluid und das Routing, aber das
   Routing kennt keinen Baustein, und der Kern kennt Bausteine nur über ihren `boot()`-Aufruf in `FluidAddon::boot()`.
2. **Anmelden statt Einbauen:** Ein Baustein meldet seine Adressen, Sitemap-Einträge und Kopf-Angaben in `boot()` an
   (Haken unten bzw. Extension Points). Nichts davon steht fest im Router oder im Template.
3. **Eigene Tabellen, eigener Namespace:** Jeder Baustein hat seinen Ordner unter `src/`, eigene Tabellen (`rex_fluid_…`)
   und keine Abfragen auf Tabellen anderer Bausteine.

| Baustein | Ordner | Meldet an | Braucht | Stand |
|---|---|---|---|---|
| **Routing** (schöne Adressen, Quellen, Domains, Weiterleitungen, 404-Protokoll, sitemap.xml, robots.txt, Seitenkopf) | `src/Routing/` | ArticleUrl, Strukturänderungen, `PACKAGES_INCLUDED` (alles per `#[AsExtension]`) | nur den Core | herauslösbar – kennt nichts von fluid |
| SEO von fluid (SEO-Felder, Bild für soziale Netzwerke, schema.org) | `src/Seo/`, `PageSettings` | `ArticleMeta`, `PageMetaResolved` | Routing, Einstellungen von fluid | bleibt im Kern |
| Suche (Loupe) | `src/Search/` | `RoutesRebuilt` | Routing, Block-Inhalte | herauslösbar nach dem Routing |
| Karten (MapLibre, eigener Proxy) | `src/Maps/` | Endpunkt `/proxy/*`, Geo in schema.org | Routing, Einstellungen | herauslösbar |
| Videos und Untertitel | `src/Media/` | Endpunkt `/fluid-track/*` | Routing | herauslösbar |
| Datenlisten, Formulare, Postfach (YForm) | `src/Data/` | Resolver Detailseiten, Sitemap-Einträge | Routing, Blöcke, YForm | bleibt vorerst im Kern |
| Blog | `src/Blog/` | Resolver `feed.xml`, RSS im Kopf (`PageMetaResolved`), schema.org | Routing, Seiteneinstellungen | herauslösbar |
| MCP-Server | `src/Mcp/` | Endpunkt `/fluid-mcp` | Kern (Blöcke, Rechte) | herauslösbar |
| E-Mail (Status, erneut senden, Layout, Aufbewahrung) | `src/Mail/` | Cronjob `fluid_mail_archive_purge` | Core-Mailer, Einstellungen, Kit (Farben) | herauslösbar |
| Live, Präsenz | `src/Live.php`, `src/PresenceApi.php` | Endpunkte | Kern | bleibt im Kern |
| Demo | eigenes AddOn [fluid_demo](https://github.com/FriendsOfREDAXO/fluid_demo) | Gastzugang | Kern | eigenständig |
| Bildeditor | eigenes AddOn [image_editor](https://github.com/FriendsOfREDAXO/image_editor) | – | Core | eigenständig |

**Haken des Routings** (`FriendsOfRedaxo\Fluid\Routing`) – Attribute werden über die Klassenerkennung des Cores gefunden,
die Aufrufe gehen in `boot()` jedes AddOns (aufgelöst wird erst bei `PACKAGES_INCLUDED`):

```php
#[AsEndpoint('/mein-dienst')]                 // an einer statischen Methode fn (string $path, string $rest): void
#[AsEndpoint('/dateien/*')]                   // Präfix, Rest als $rest
#[AsEndpoint('/mcp', early: true, param: 'mcp')]   // vor der Domain-Umleitung, auch als ?mcp=…
#[AsRouteSource('news')]                      // Quelle eigener Adressen (RouteSource/TableSource)
Router::endpoint('/x', fn (string $path, string $rest) => …);               // dasselbe ohne Attribut
Router::resolver(fn (string $path, int $lang, array $query): ?RouteMatch => …);   // dynamische Adressen
Router::onPage(fn (int $id, int $lang, array $query) => …);               // Seite gefunden (z. B. alte Parameter umleiten)
Router::gate(fn (string $path) => …);                                       // vor der Seitensuche
Router::keep('mein_param');                                                 // ?article_id=… mit diesem Parameter nicht umleiten
Sources::add(new MeineQuelle());                                            // Quelle ohne Attribut
```

Antworten schicken Bausteine mit `Router::respond($body, $contentType)`, Weiterleitungen mit `Router::redirectTo($url, 301)`.

**Herauslösen eines Bausteins** (Ablauf): Ordner in ein eigenes Paket verschieben (Namespace bleibt oder bekommt einen
eigenen), `boot()` aus `FluidAddon::boot()` in die Addon-Klasse des neuen Pakets, Tabellen über `getOwnedSchema()` des
neuen AddOns melden, Abhängigkeit in `composer.json` eintragen. Beim Routing: `Router::boot()` und `Schema::install()` in die
Addon-Klasse, `Schema::TABLES` in `getOwnedSchema()`; alles andere hängt schon per Attribut am Core.

## Aufbau des AddOns

| Pfad | Inhalt |
|---|---|
| `src/` | Namespace `FriendsOfRedaxo\Fluid`: Editor-API (`Api`), Blöcke (`BlockModule`, `Block`, `BlockData`), Routing, Blog, Daten (YForm), Medien, Suche |
| `src/Data/` | YForm-Anbindung: `YFormBridge`, Formular-Baukasten `BuiltForm`, Feld `FluidBlocks`, `RecordBlocks`, `Detail`, Postfach |
| `src/Field/` | Felder: gleichnamige Unterklassen der Felder des Cores mit Ergänzungen, Felder nur von fluid (`RichText`, `Link` …), `AsBlock`, `BlockSchema` (Schema für fluid aus den Attributen) |
| `kits/fluid/` | das Grund-Kit: `src/Module/` (Blockklassen mit Feld-Attributen), `views/` (Ausgaben), `kit.php` (Assets, Hintergründe), `design.php` (Design-Tokens, Vorlagen), `functions.php`, `lang/` |
| `src/Kit.php`, `src/AsKit.php` | Kits: Anmeldung, Auswahl (`Kit::active()`), Vererbung, Assets |
| `kits/native/` | Kit „NativeCSS“: `template.view.php`, `views/`, `kit.php`, `assets/css/native.css`, NativeCSS in `assets/ncss/` |
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

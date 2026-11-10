<?php
/*
 * Demo-Inhalte „REDAXO 6“ für das Kit fluid – `php bin/console fluid:demo --set=redaxo6 --replace`.
 * Stand: REDAXO 6 in Entwicklung (redaxo/core, Branch 6.x). Nur Aussagen, die sich im Code des Branches nachprüfen lassen.
 * Blöcke mit 'children' => [[Spalte, Block], …] werden als Layout mit Spalten-Blöcken (echte Slices) angelegt.
 */

$cta = [
    'type' => 'cta', 'tunes' => ['background' => 'accent'],
    'data' => [
        'variant' => 'band', 'eyebrow' => 'Ausprobieren',
        'title' => 'Diese Seite ist mit REDAXO 6 und fluid gebaut.',
        'text' => 'Anmelden, auf „Bearbeiten“ tippen – und jeden Text, jedes Bild und das ganze Design direkt hier ändern.',
        'button_label' => 'Wie Bearbeiten funktioniert', 'button_link' => '/bearbeiten',
        'button2_label' => 'Technik ansehen', 'button2_link' => '/technik',
    ],
];

// YForm (nur wenn installiert): Beispielzeilen für die Seite „Daten“. Die Tabellen und das Formular sind Klassen im
// Projekt (#[AsTable]/#[AsForm], Vorlagen in examples/yform) – YForm legt die Tabellen bei `console migrate` an.
$yform = [
    'rex_yf_termin' => ['rows' => [
        ['title' => 'Online-Demo: Bearbeiten auf der Website', 'date' => '2026-11-12', 'place' => 'Online', 'image' => 'fluid-formular-schritte.jpg', 'text' => "Eine Stunde fluid live: Texte direkt auf der Seite ändern, Blöcke einfügen, Design anpassen und veröffentlichen.\n\nBeispieltermin – zeigt, wie eine YForm-Tabelle als Datenliste erscheint.", 'online' => true],
        ['title' => 'Workshop: Eigene Blöcke bauen', 'date' => '2026-11-26', 'place' => 'Online', 'image' => null, 'text' => 'Ein Block ist eine Modulklasse, ein Feldschema und eine View. Wir bauen gemeinsam einen Block mit Bild, Text und Wiederholgruppe.', 'online' => true],
        ['title' => 'Sprechstunde: Von REDAXO 5 nach 6', 'date' => '2026-12-03', 'place' => 'Online', 'image' => null, 'text' => 'Fragen zu Composer, Modulen als Klassen und console migrate – mitbringen, was im eigenen Projekt ansteht.', 'online' => true],
        ['title' => 'Entwurf: noch nicht veröffentlicht', 'date' => '2026-12-17', 'place' => 'Online', 'image' => null, 'text' => 'Dieser Eintrag ist offline und erscheint nicht in der Liste.', 'online' => false],
    ]],
    'rex_yf_anfrage' => ['rows' => []],
    // Projekte (examples/yform/ProjectTable) – Seite „Projekte“: Fallstudien aus Blöcken (Feld „Fluid-Blöcke“)
    'rex_yf_project' => ['rows' => [
        ['title' => 'Stadtwerke Musterstadt: Portal in drei Sprachen', 'client' => 'Stadtwerke Musterstadt', 'year' => 2026, 'image' => 'fluid-website.jpg', 'online' => true,
            'teaser' => 'Ein Portal, drei Sprachen, eine Redaktion: Tarife, Störungsmeldungen und Neuigkeiten – gepflegt direkt auf der Website.',
            'story' => [
                ['module' => 'fluid.stats', 'data' => ['variant' => 'row', 'eyebrow' => 'Ergebnis', 'title' => 'Ein Jahr danach', 'items' => [
                    ['value' => '3', 'label' => 'Sprachen', 'text' => 'Deutsch, Englisch, Türkisch'],
                    ['value' => '−60 %', 'label' => 'Pflegeaufwand', 'text' => 'Texte direkt auf der Seite statt im Backend'],
                    ['value' => '12', 'label' => 'Redakteure', 'text' => 'mit eigenen Rechten je Bereich'],
                ]], 'tunes' => ['background' => 'muted']],
                ['module' => 'fluid.richtext', 'data' => ['variant' => 'article', 'title' => 'Die Aufgabe', 'text' => '<p>Die alte Website war über Jahre gewachsen: drei Systeme, doppelte Inhalte, lange Wege für jede Änderung. Ziel war <strong>eine</strong> Website, die das Team selbst pflegt – ohne Schulung, ohne Agentur für jeden Tippfehler.</p><h3>Der Ansatz</h3><ul><li>REDAXO 6 mit fluid: Bearbeiten direkt auf der Seite</li><li>Tarife und Meldungen als YForm-Tabellen</li><li>Mehrsprachig mit „Inhalt übernehmen“</li></ul>'], 'tunes' => []],
                ['module' => 'fluid.quote', 'data' => ['variant' => 'single', 'items' => [['text' => 'Wir ändern heute Texte in Minuten – genau dort, wo Besucher sie lesen.', 'name' => 'Erika Musterfrau', 'role' => 'Online-Redaktion', 'image' => null]]], 'tunes' => ['background' => 'accent']],
                ['module' => 'fluid.media_text', 'data' => ['variant' => 'right', 'title' => 'Störungen in Echtzeit', 'text' => '<p>Meldungen kommen aus einer YForm-Tabelle und erscheinen sofort auf der Startseite – mit eigener Adresse je Meldung.</p>', 'image' => 'fluid-backend-status.jpg'], 'tunes' => []],
            ]],
        ['title' => 'Kulturverein: Programm, Anmeldung, Archiv', 'client' => 'Kulturverein Beispielhausen', 'year' => 2025, 'image' => 'fluid-formular-schritte.jpg', 'online' => true,
            'teaser' => 'Veranstaltungen als Datenliste, Anmeldung mit mehrstufigem Formular und ein Archiv, das von selbst wächst.',
            'story' => [
                ['module' => 'fluid.features', 'data' => ['variant' => 'cards', 'eyebrow' => 'Bausteine', 'title' => 'Was der Verein nutzt', 'items' => [
                    ['icon' => 'calendar', 'title' => 'Programm', 'text' => 'Termine aus YForm, nach Datum sortiert, mit Blättern.'],
                    ['icon' => 'list-checks', 'title' => 'Anmeldung', 'text' => 'Formular-Baukasten in drei Schritten, mit Bestätigung.'],
                    ['icon' => 'archive', 'title' => 'Archiv', 'text' => 'Vergangene Termine wandern automatisch ins Archiv.'],
                ]], 'tunes' => []],
                ['module' => 'fluid.faq', 'data' => ['variant' => 'stacked', 'title' => 'Häufige Fragen', 'items' => [
                    ['q' => 'Wer pflegt die Termine?', 'a' => '<p>Der Vorstand – über die YForm-Maske oder direkt auf der Website.</p>'],
                    ['q' => 'Brauchen Mitglieder ein Konto?', 'a' => '<p>Nein. Die Anmeldung geht ohne Konto, die Daten landen im Postfach.</p>'],
                ]], 'tunes' => ['background' => 'muted']],
            ]],
        ['title' => 'Praxis Dr. Beispiel: ruhig, klar, barrierearm', 'client' => 'Praxis Dr. Beispiel', 'year' => 2025, 'image' => 'fluid-blog.jpg', 'online' => true,
            'teaser' => 'Eine Praxis-Website mit großer Schrift, hohem Kontrast und Öffnungszeiten, die sich selbst aktualisieren.',
            'story' => []],
        ['title' => 'Entwurf: noch nicht freigegeben', 'client' => '', 'year' => 2026, 'image' => null, 'teaser' => 'Offline – erscheint nicht.', 'story' => [], 'online' => false],
    ]],
    // Kurzmeldungen (examples/yform/NewsTable) – Seite „Presse“
    'rex_yf_news' => ['rows' => [
        ['title' => 'REDAXO 6: Module und Templates als Klassen', 'date' => '2026-10-09', 'teaser' => 'Im Branch 6.x sind Module und Templates PHP-Klassen mit Attribut – auffindbar per ClassDiscovery.', 'image' => 'fluid-yform-maske.jpg', 'text' => "Beispielmeldung aus der YForm-Tabelle rex_yf_news.\n\nDie Datenliste zeigt sie nach Datum sortiert, mit eigener Adresse je Meldung.", 'link' => 'https://github.com/redaxo/core', 'online' => true],
        ['title' => 'YForm 6: Tabellen und Formulare im Projekt', 'date' => '2026-10-02', 'teaser' => 'Tabellen und Formulare sind Klassen im Projekt (#[AsTable], #[AsForm]); console migrate legt die Tabellen an.', 'image' => null, 'text' => 'Beispielmeldung – Felder, Prüfungen und Aktionen legt die Klasse fest.', 'link' => 'https://github.com/yakamara/yform', 'online' => true],
        ['title' => 'fluid: Bearbeiten auf der Website', 'date' => '2026-09-25', 'teaser' => 'Blöcke als echte Slices, Arbeitsversion, Rechte wie im Backend – direkt auf der Seite.', 'image' => 'fluid-bearbeiten-datensatz.jpg', 'text' => 'Beispielmeldung mit Bild.', 'link' => '', 'online' => true],
        ['title' => 'Medien: Video-Vorschaubilder und Untertitel', 'date' => '2026-09-18', 'teaser' => 'Standbild per ffmpeg über den Media Manager, Untertitel aus dem Medienpool.', 'image' => 'fluid-design.jpg', 'text' => 'Beispielmeldung.', 'link' => '', 'online' => true],
        ['title' => 'Entwurf: noch nicht freigegeben', 'date' => '2026-10-12', 'teaser' => 'Offline – erscheint nicht.', 'image' => null, 'text' => '', 'link' => '', 'online' => false],
    ]],
];

// Codebeispiele der Seiten unter „REDAXO 6“ (geprüft gegen redaxo/core 6.x und YForm 6.x)
$code = [
    'create' => <<<'CODE'
composer create-project redaxo/project:6.x-dev mein-projekt
cd mein-projekt
CODE,
    'env' => <<<'CODE'
# .env.local – nur auf diesem Rechner, nicht im Repository
DATABASE_URL=mysql://redaxo:geheim@127.0.0.1:3306/redaxo
REX_MODE=dev
REX_BASE_URL=https://mein-projekt.test/
CODE,
    'setup' => <<<'CODE'
php bin/console setup     # Einrichtung samt Admin-Konto
php bin/console migrate   # nach jedem Update: Datenbank auf den Stand bringen
CODE,
    'tree' => <<<'CODE'
bin/console               Konsole
public/                   Wurzel des Webservers
public/index.php          Frontend
public/redaxo/index.php   Backend
src/                      Dein Code: Project.php, Module, Templates …
var/                      Cache, Daten, Logs
CODE,
    'module' => <<<'CODE'
<?php

namespace Project\Module;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Form\Field\ArticlePicker;
use Redaxo\Core\Form\Field\MediaPicker;
use Redaxo\Core\Form\Field\Text;
use Redaxo\Core\Form\Field\Textarea;
use Redaxo\Core\MediaPool\Media;

use function Redaxo\Core\View\escape;

#[AsModule('teaser', 'Teaser')]
final class TeaserModule extends Module
{
    #[Text('Überschrift', required: true, maxLength: 120)]
    public string $headline = '';

    #[Textarea('Text', rows: 4)]
    public string $text = '';

    #[MediaPicker('Bild', types: ['jpg', 'png', 'webp'])]
    public ?Media $image = null;

    #[ArticlePicker('Weiterlesen auf')]
    public ?Article $link = null;

    public function output(): string
    {
        $html = '<article class="teaser">';
        if ($this->image) {
            $html .= '<img src="' . escape($this->image->getUrl()) . '" alt="">';
        }
        $html .= '<h2>' . escape($this->headline) . '</h2>';
        $html .= '<p>' . nl2br(escape($this->text)) . '</p>';
        if ($this->link) {
            $html .= '<a href="' . escape($this->link->getUrl()) . '">Weiterlesen</a>';
        }

        return $html . '</article>';
    }
}
CODE,
    // gewöhnliches Modul: die Datei, die auch im Projekt liegt (examples/module)
    'nmodule' => (string) file_get_contents(__DIR__ . '/../../examples/module/HinweisModule.php'),
    // derselbe Hinweis als Block: die Datei, die auch im Projekt liegt
    'hblock' => (string) file_get_contents(__DIR__ . '/../../examples/block/Hinweis.php'),
    'nmodule_input' => <<<'CODE'
public function input(Form $form): string
{
    // eigener Aufbau – fluid zeigt dafür das Formular des Cores in der Seitenleiste
    return '<p class="rex-field-note">Hinweis über den Feldern</p>' . $form->rest();
}
CODE,
    'template' => <<<'CODE'
<?php

namespace Project\Template;

use Override;
use Project\Module\TeaserModule;
use Redaxo\Core\Content\ArticleContent;
use Redaxo\Core\Content\AsTemplate;
use Redaxo\Core\Content\ContentSection;
use Redaxo\Core\Content\Template;
use Redaxo\Core\View\HasView;

#[AsTemplate('page', 'Seite')]
final class PageTemplate extends Template
{
    use HasView;

    #[Override]
    public function getContentSections(): array
    {
        return [new ContentSection(1, 'Inhalt'), new ContentSection(2, 'Seitenleiste')];
    }

    #[Override]
    public function isModuleAllowed(ContentSection $section, string $module): bool
    {
        // In der Seitenleiste nur Teaser
        return 2 !== $section->id || TeaserModule::class === $module;
    }

    #[Override]
    public function render(ArticleContent $content): string
    {
        return $this->renderView($content);   // PageTemplate.view.php daneben
    }
}
CODE,
    'view' => <<<'CODE'
<?php

use Redaxo\Core\Content\ArticleContent;

use function Redaxo\Core\View\escape;

return static function (ArticleContent $content): void { ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="utf-8">
        <title><?= escape($content->article->name) ?></title>
    </head>
    <body>
        <main><?= $content->renderContent(1) ?></main>
        <aside><?= $content->renderContent(2) ?></aside>
    </body>
    </html>
<?php };
CODE,
    'project' => <<<'CODE'
<?php

namespace Project;

use Override;
use Project\Template\PageTemplate;
use Redaxo\Core\AbstractProject;
use Redaxo\Core\Content\Template;

final class Project extends AbstractProject
{
    #[Override]
    public function boot(): void
    {
        Template::setDefault(PageTemplate::class);   // Template für neue Artikel
    }
}
CODE,
    'yrequire' => <<<'CODE'
composer config repositories.yform '{"type": "vcs", "url": "https://github.com/yakamara/yform", "no-api": true}'
composer require yakamara/yform:6.x-dev@dev
php bin/console migrate
CODE,
    'ytable' => <<<'CODE'
<?php

namespace Project\YForm;

use Yakamara\YForm\Attribute\AsTable;
use Yakamara\YForm\Field\Checkbox;
use Yakamara\YForm\Field\Date;
use Yakamara\YForm\Field\Media;
use Yakamara\YForm\Field\Text;
use Yakamara\YForm\Field\Textarea;
use Yakamara\YForm\Table\ListConfig;
use Yakamara\YForm\Table\Table;

#[AsTable('rex_yf_event', label: 'Termine', icon: 'fa-calendar')]
final class EventTable extends Table
{
    public function fields(): iterable
    {
        yield Text::new('title')->label('Titel')->required()->maxLength(191)->filterable();
        yield Date::new('date')->label('Datum')->required()->filterable();
        yield Textarea::new('text')->label('Beschreibung');
        yield Media::new('image')->label('Bild')->types('jpg', 'png', 'webp');
        yield Checkbox::new('online')->label('Online')->default(true)->filterable();
    }

    public function list(ListConfig $list): ListConfig
    {
        return $list->columns('id', 'title', 'date', 'online')->sortBy('date', 'asc');
    }
}
CODE,
    'yquery' => <<<'CODE'
<?php

use Project\YForm\EventTable;

use function Redaxo\Core\View\escape;

$events = EventTable::query()
    ->where('online', 1)
    ->where('date', '>=', date('Y-m-d'))
    ->orderBy('date')
    ->limit(5)
    ->find();

foreach ($events as $event) {
    echo '<h3>' . escape($event->get('title')) . '</h3>';
    echo '<p>' . $event->get('date')->format('d.m.Y') . '</p>';
}
CODE,
    'nav' => <<<'CODE'
<?php

use FriendsOfRedaxo\NavigationArray\Component\NavigationBreadcrumb;
use FriendsOfRedaxo\NavigationArray\Navigation;
use FriendsOfRedaxo\NavigationArray\NavigationItem;

use function Redaxo\Core\View\escape;

$nav = new Navigation(depth: 1, exclude: [12]);   // erste Ebene und eine darunter, ohne Kategorie 12

// walk(): je Eintrag Markup, $children() rendert die nächste Ebene genauso
echo '<nav><ul>' . $nav->walk(static fn (NavigationItem $item, Closure $children): string => '<li' . ($item->active ? ' class="active"' : '') . '>'
    . '<a href="' . escape($item->url) . '"' . ($item->current ? ' aria-current="page"' : '') . '>' . escape($item->name) . '</a>'
    . ($item->hasChildren() ? '<ul>' . $children() . '</ul>' : '')
    . '</li>') . '</ul></nav>';

// Brotkrumen als Komponente, mit strukturierten Daten (Schema.org BreadcrumbList)
echo new NavigationBreadcrumb($nav, label: 'Sie sind hier', jsonLd: true)->render();
CODE,
    'navInstall' => <<<'CODE'
composer config repositories.navigation_array '{"type": "vcs", "url": "https://github.com/FriendsOfREDAXO/navigation_array", "no-api": true}'
composer require friendsofredaxo/navigation_array:6.x-dev
php bin/console migrate
CODE,
    'frequire' => <<<'CODE'
composer config repositories.yform '{"type": "vcs", "url": "https://github.com/yakamara/yform", "no-api": true}'
composer config repositories.navigation_array '{"type": "vcs", "url": "https://github.com/FriendsOfREDAXO/navigation_array", "no-api": true}'
composer config repositories.fluid '{"type": "vcs", "url": "https://github.com/FriendsOfREDAXO/fluid", "no-api": true}'
composer require yakamara/yform:6.x-dev@dev friendsofredaxo/fluid:*@dev
php bin/console migrate
CODE,
    'fdemo' => <<<'CODE'
# Demo-Inhalte mit Bildern und Design „REDAXO · Blau“ (nur in einer leeren Installation)
php bin/console fluid:demo --set=redaxo6 --media --design=redaxo
CODE,
    'fblock' => <<<'CODE'
<?php

namespace Project\Block;

use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\{Link, Text, Textarea};
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;

/** Eigener Block im Projekt: Felder als Attribute, Ausgabe in Teaser.view.php daneben – das Kit bleibt unverändert. */
#[AsModule('fluid.teaser', 'Teaser (Projekt)')]
#[AsBlock(icon: 'star', group: 'Inhalt', nestable: true, help: 'Beispiel für einen eigenen Block im Projekt.')]
final class Teaser extends FluidBlock
{
    #[Text('Überschrift', required: true, maxLength: 80)]
    public string $title = '';

    #[Textarea('Text', rows: 3)]
    public string $text = '';

    #[Link('Link')]
    public string $link = '';
}
CODE,
    'fview' => <<<'CODE'
<?php /** @var \FriendsOfRedaxo\Fluid\Block $b  @var array $d */ ?>
<div class="wrap">
  <div class="card" style="padding:var(--f-s-l)">
    <h2 class="h3"<?= $b->edit('title') ?>><?= e($d['title']) ?></h2>
    <p<?= $b->edit('text', 'lines') ?>><?= nl2br(e($d['text']), false) ?></p>
    <?php if ('' !== trim((string) $d['link'])): ?><a class="btn btn--primary" <?= fluid_link_attrs($d['link']) ?>>Mehr</a><?php endif; ?>
  </div>
</div>
CODE,
    'beModule' => <<<'CODE'
<?php

namespace Project\Module;

use Override;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Form\Field\Choice;
use Redaxo\Core\Form\Field\Text;
use Redaxo\Core\Form\Form;
use Redaxo\Core\View\Component\Breakpoint;
use Redaxo\Core\View\Component\Grid;
use Redaxo\Core\View\Component\GridColumn;
use Redaxo\Core\View\Component\GridColumnRule;

use function Redaxo\Core\View\escape;

#[AsModule('hero', 'Einstieg')]
final class HeroModule extends Module
{
    #[Text('Überschrift', required: true)]
    public string $headline = '';

    #[Text('Unterzeile')]
    public string $subline = '';

    #[Choice('Hintergrund', ['light' => 'hell', 'dark' => 'dunkel'])]
    public string $background = 'light';

    // Eigene Anordnung: Überschrift und Unterzeile ab mittlerer Breite nebeneinander, der Rest darunter
    #[Override]
    public function input(Form $form): string
    {
        $half = [new GridColumnRule(Breakpoint::Md, width: 6)];

        return new Grid([
            new GridColumn($form->row('headline'), $half),
            new GridColumn($form->row('subline'), $half),
        ])->render() . $form->rest();
    }

    public function output(): string
    {
        return '<header class="hero hero--' . escape($this->background) . '"><h1>' . escape($this->headline) . '</h1><p>' . escape($this->subline) . '</p></header>';
    }
}
CODE,
    'beMarkup' => <<<'CODE'
<div class="rex-field">
    <label class="rex-field-label" for="teaser-title">
        Überschrift <span class="rex-field-required" aria-hidden="true">*</span>
    </label>
    <div class="rex-field-control">
        <input class="rex-input" type="text" id="teaser-title" name="title" required>
        <p class="rex-field-note">Kurz und knapp.</p>
    </div>
</div>

<div class="rex-field">
    <label class="rex-field-label" for="teaser-url">Link</label>
    <div class="rex-field-control">
        <div class="rex-input-group">
            <span class="rex-input-group-text">https://</span>
            <input class="rex-input" type="text" id="teaser-url" name="url">
        </div>
    </div>
</div>

<div class="rex-check">
    <label><input type="checkbox" name="menu" value="1"> Im Menü zeigen</label>
</div>

<button type="submit" class="rex-button" data-variant="primary">Speichern</button>
<button type="submit" class="rex-button" data-variant="danger" data-confirm="Wirklich löschen?">Löschen</button>
CODE,
    'beSection' => <<<'CODE'
use Redaxo\Core\View\Component\Section;
use Redaxo\Core\View\Html;

// Kasten mit Titel, Inhalt mit Abstand (body) oder ohne (content, z. B. eine Tabelle)
echo new Section(title: 'Einstellungen', body: Html::raw($formHtml))->render();
echo new Section(title: 'Einträge', content: Html::raw('<table class="rex-table" data-hover>…</table>'))->render();
CODE,
    'beCss' => <<<'CODE'
/* Eigene Komponente mit eigenem Präfix – Farben nur über die Tokens des Cores,
   dann passt sie automatisch zu hell und dunkel */
.project-preview {
    padding: 15px;
    border: 1px solid var(--rex-color-border);
    background: var(--rex-color-surface-muted);
    color: var(--rex-color-text);
}

.project-preview__hint {
    color: var(--rex-color-text-muted);
}
CODE,
    'beAssets' => <<<'CODE'
<?php

namespace Project;

use Override;
use Redaxo\Core\AbstractProject;
use Redaxo\Core\Core;
use Redaxo\Core\View\Asset;

final class Project extends AbstractProject
{
    #[Override]
    public function boot(): void
    {
        // Eigene Stile und Skripte fürs Backend – als Dateien, nie inline (Content-Security-Policy)
        if (Core::isBackend()) {
            Asset::addCssFile('/assets/project/backend.css');
            Asset::addJsFile('/assets/project/backend.js');
        }
    }
}
CODE,
    'beJs' => <<<'CODE'
// Läuft für die Seite und für Markup, das später kommt (Overlays, neue Einträge einer Wiederholgruppe)
const init = (root) => {
    root.querySelectorAll('textarea[data-count]').forEach((field) => {
        if (field.dataset.countReady) {
            return;
        }
        field.dataset.countReady = '1';
        const note = document.createElement('p');
        note.className = 'rex-field-note';
        const update = () => {
            note.textContent = `${field.value.length} / ${field.dataset.count} Zeichen`;
        };
        field.after(note);
        field.addEventListener('input', update);
        update();
    });
};

init(document);
document.addEventListener('rex:ready', (event) => init(event.target));
CODE,
    'kitCreate' => <<<'CODE'
php bin/console fluid:kit:create nature --label="Nature" --view=cta

# legt an:
#   src/Kit/Nature/NatureKit.php       Anmeldung des Kits
#   src/Kit/Nature/kit.php             Definition – nur, was anders ist
#   src/Kit/Nature/assets/css/kit.css  eigenes CSS
#   src/Kit/Nature/views/cta.php       Kopie der View „Handlungsaufruf“ (--view)
CODE,
    'kitClass' => <<<'CODE'
<?php

namespace Project\Kit\Nature;

use FriendsOfRedaxo\Fluid\AsKit;
use FriendsOfRedaxo\Fluid\Kit;

/**
 * Kit „Nature“ – erbt von „Fluid“. Dateien in diesem Ordner ersetzen die des Eltern-Kits:
 * kit.php (Definition), views/{typ}.php, template.view.php, functions.php, assets/.
 */
#[AsKit('nature', 'Nature', parent: 'fluid')]
final class NatureKit extends Kit {}
CODE,
    'kitDef' => <<<'CODE'
<?php

/*
 * Kit „Nature“: nur, was vom Eltern-Kit „fluid“ abweicht ($parent ist dessen Definition).
 */

return [
    'label' => 'Nature',
    // eigenes CSS für jede Seite – nach dem CSS von „fluid“
    'css' => ['css/kit.css'],
    // zusätzlicher Hintergrund für alle Blöcke (Klasse bg-sand)
    'backgrounds' => ['sand' => 'Sand'],
    // Handlungsaufruf: zusätzliche Variante „Mit Blatt“ – Felder und alles andere aus der Blockklasse
    'blocks' => [
        'cta' => ['variants' => ['leaf' => 'Mit Blatt']],
    ],
];
CODE,
    'kitCss' => <<<'CODE'
/* Hintergrund „Sand“: aus Akzent und Hintergrund gemischt – passt so zu hell und dunkel */
.bg-sand {
    --f-sec-bg: color-mix(in srgb, #c8a96a 22%, var(--f-bg));
    --f-card: var(--f-bg);
}

/* Handlungsaufruf „Mit Blatt“ */
.cta__leaf {
    color: var(--f-a);
    font-size: 2.5rem;
}
CODE,
    'kitView' => <<<'CODE'
<?php
/** Handlungsaufruf im Kit „Nature“ – wie in „fluid“, dazu die Variante „Mit Blatt“ (Band mit Blatt). @var \FriendsOfRedaxo\Fluid\Block $b  @var array $d */
$leaf = 'leaf' === $b->variant();
$v = $leaf ? 'band' : (in_array($b->variant(), ['band', 'box', 'split', 'big'], true) ? $b->variant() : 'band');
$pic = $v === 'split' ? fluid_image($d['image'] ?? null, '(min-width: 1080px) 560px, 100vw', '4:3', 'cta__media') : '';
?>
<div class="wrap">
  <div class="cta cta--<?= e($v) ?><?= $pic !== '' ? ' cta--media' : '' ?>">
    <div class="cta__main">
    <div class="cta__text">
      <?php if ($leaf): ?><?= icon('leaf', ['class' => 'cta__leaf']) ?><?php endif; ?>
      <?php if (trim((string) ($d['eyebrow'] ?? '')) !== ''): ?><p class="eyebrow"<?= $b->edit('eyebrow') ?>><?= e($d['eyebrow']) ?></p><?php endif; ?>
      <h2 id="<?= e($b->titleId()) ?>" class="<?= $v === 'big' ? 'cta__big' : 'h2' ?> cta__title"<?= $b->edit('title') ?>><?= fluid_title((string) $d['title']) ?></h2>
      <?php if ($d['text'] !== '' || is_editing()): ?><p class="cta__lead"<?= $b->edit('text') ?>><?= nl2br(e($d['text']), false) ?></p><?php endif; ?>
    </div>
    <?= fluid_buttons($b, 'cta__actions') ?>
    </div>
    <?= $pic ?>
  </div>
</div>
CODE,
    'kitPackage' => <<<'CODE'
{
    "name": "acme/fluid-kit-nature",
    "type": "redaxo-addon",
    "require": { "friendsofredaxo/fluid": "*@dev" },
    "autoload": { "psr-4": { "Acme\\FluidKitNature\\": "src/" } },
    "extra": { "redaxo": { "addon-class": "Acme\\FluidKitNature\\NatureAddon" } }
}
CODE,
    'kitAddon' => <<<'CODE'
<?php

namespace Acme\FluidKitNature;

use Redaxo\Core\Addon\Addon;

// Leere AddOn-Klasse: damit REDAXO das Paket kennt und das Kit (src/NatureKit.php) findet
final class NatureAddon extends Addon {}
CODE,
];
$c = static fn (string $key, string $file, string $lang): array => ['file' => $file, 'lang' => $lang, 'code' => $code[$key]];

return [
    'yform' => $yform,
    // Website-Einstellungen (nur, wo noch nichts eingetragen ist)
    'settings' => ['org_name' => 'REDAXO 6 Demo', 'tagline' => 'REDAXO im Frontend, einfach.', 'search_page' => '/suche'],
    'pages' => [
        // ------------------------------------------------------------------ Startseite
        [
            'slug' => 'start', 'title' => 'Start', 'is_home' => true, 'menu' => false,
            'blocks' => [
                ['type' => 'hero', 'data' => [
                    'variant' => 'type', 'eyebrow' => 'REDAXO 6 · in Entwicklung',
                    'title' => 'REDAXO im Frontend, *einfach*.',
                    'text' => 'REDAXO 6 räumt unter der Haube auf: PHP 8.5, Composer, Module und Templates als PHP-Klassen. Das macht etwas möglich, das in REDAXO lange gefehlt hat – Seiten direkt auf der Website bearbeiten, mit echten Slices. REDAXO 6 ist noch in Entwicklung – es kann sich noch einiges ändern.',
                    'button_label' => 'Was sich ändert', 'button_link' => '/was-sich-aendert',
                    'button2_label' => 'Bearbeiten auf der Website', 'button2_link' => '/bearbeiten',
                    'points' => "Seit 2004 · MIT-Lizenz\nModule als PHP-Klassen\nBearbeiten im Kontext",
                ]],
                // Trailer direkt unter dem Einstieg (nur wenn die Datei im Medienpool liegt – sie gehört nicht zum Kit: fluid:demo --media-dir=…)
                ...(is_file(\Redaxo\Core\Filesystem\Path::media('fluid-trailer-redaxo6.mp4')) ? [
                    ['type' => 'video', 'tunes' => ['background' => 'dark'], 'data' => ['variant' => 'cinema', 'eyebrow' => 'Trailer',
                        'title' => 'fluid in fünf Minuten', 'intro' => '', 'video_url' => '', 'video_file' => 'fluid-trailer-redaxo6.mp4', 'poster' => 'fluid-trailer-redaxo6.jpg',
                        'tracks' => [['file' => 'fluid-trailer-redaxo6.de.vtt', 'lang' => 'de', 'kind' => 'subtitles', 'label' => 'Deutsch']],
                        'cc_on' => true, 'transcript' => false, 'ratio' => '16-9',
                        'caption' => 'Englische Texte im Bild, deutsche Untertitel. Foto Frankfurt: Leonhard Niederwimmer / Pixabay', 'text' => '']],
                ] : []),
                ['type' => 'media_text', 'data' => ['variant' => 'right', 'eyebrow' => 'Ausprobieren', 'title' => 'Bilder in Form bringen',
                    'text' => '<p>Unter „Bearbeiten“ wählen Sie beim Bild das Format (16:9, 4:3, quadratisch …), die Darstellung (Bild links, rechts, randlos geteilt) und ziehen die Aufteilung zwischen Bild und Text direkt auf der Seite.</p>',
                    'list' => "Bildformat in der Seitenleiste\nAufteilung per Ziehen am Bildrand\nDarstellung oben im Block wählen",
                    'image' => 'fluid-website.jpg', 'ratio' => '16:9', 'split' => 'media']],
                ['type' => 'features', 'data' => [
                    'variant' => 'cards', 'eyebrow' => 'Unter der Haube', 'title' => 'Was REDAXO 6 mitbringt',
                    'intro' => 'Ein modernes Fundament – vertraut für alle, die REDAXO kennen, und ohne Altlasten.',
                    'size' => 'm',
                    'items' => [
                        ['icon' => 'package', 'title' => 'Composer von Anfang an', 'text' => 'Der Core ist das Paket redaxo/core, ein Projekt entsteht mit composer create-project. AddOns sind Composer-Pakete – installiert heißt aktiv.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'code', 'title' => 'Module und Templates als Klassen', 'text' => 'Mit #[AsModule] und #[AsTemplate] liegen sie als PHP-Dateien im Projekt – versioniert im Git statt als Code in der Datenbank.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'terminal-window', 'title' => 'console migrate', 'text' => 'Ein Befehl bringt die Datenbank auf den Stand des Codes: Tabellen werden beschrieben statt per SQL geändert, einmalige Schritte laufen als Migrationen.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'tree-structure', 'title' => 'Struktur im Core', 'text' => 'Kategorien, Artikel, Slices, Arbeitsversion und Historie gehören zum Kern – kein AddOn „structure“ mehr.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'fingerprint', 'title' => 'Passkeys', 'text' => 'Anmelden am Backend ohne Passwort – mit Fingerabdruck, Gesichtserkennung oder Sicherheitsschlüssel (WebAuthn).', 'link_label' => '', 'link' => ''],
                        ['icon' => 'shield-check', 'title' => 'Betriebsmodi', 'text' => 'dev, live und hardened über .env: ausführliche Fehler in der Entwicklung, gehärtetes Backend auf dem Server.', 'link_label' => '', 'link' => ''],
                    ],
                ]],
                ['type' => 'stats', 'tunes' => ['background' => 'muted'], 'data' => [
                    'variant' => 'row', 'eyebrow' => 'In Zahlen', 'title' => 'REDAXO auf einen Blick', 'intro' => '',
                    'items' => [
                        ['value' => '2004', 'label' => 'erste Version', 'text' => 'Content-Management seit über 20 Jahren'],
                        ['value' => '8.5', 'label' => 'PHP mindestens', 'text' => 'für REDAXO 6'],
                        ['value' => 'MIT', 'label' => 'Lizenz', 'text' => 'frei nutzbar, auch kommerziell'],
                        ['value' => '6.x', 'label' => 'Entwicklungszweig', 'text' => 'redaxo/core auf GitHub'],
                    ],
                ]],
                ['type' => 'layout', 'data' => ['preset' => '2-1', 'valign' => 'center', 'gap' => 'normal', 'stack' => 'default', 'reverse' => false],
                    'children' => [
                        [1, ['type' => 'richtext', 'data' => [
                            'variant' => 'standard', 'eyebrow' => 'Warum jetzt', 'title' => 'Zeit für ein neues Bearbeiten', 'intro' => '',
                            'text' => '<p>In REDAXO 5 war ein Modul freier PHP-Code: Eingabe und Ausgabe ganz in der Hand der Entwickler. Das ist flexibel – aber kein Editor weiß, welche Felder ein Modul hat.</p><p>In REDAXO 6 ist ein Modul eine <strong>Klasse</strong>. Damit kann es ein <strong>Feldschema</strong> mitbringen – und aus dem Schema entstehen Backend-Formular, Bearbeiten auf der Website und Live-Vorschau.</p>',
                            'measure' => 'normal', 'meta' => '', 'dropcap' => false, 'toc' => false, 'reveal' => 'off', 'scrollspy' => 'off',
                        ]]],
                        [2, ['type' => 'quote', 'tunes' => ['background' => 'tint'], 'data' => ['variant' => 'single', 'eyebrow' => '', 'title' => '', 'items' => [
                            ['text' => 'Die Redaktion sieht, was sie ändert – und die Entwickler behalten Module, Templates und Daten im Griff.', 'name' => 'Leitgedanke', 'role' => 'fluid für REDAXO 6', 'image' => null],
                        ]]]],
                    ],
                ],
                $cta,
            ],
        ],

        // ------------------------------------------------------------------ REDAXO 6 (Übersicht mit Unterseiten)
        [
            'slug' => 'redaxo-6', 'title' => 'REDAXO 6', 'menu' => true,
            'blocks' => [
                ['type' => 'hero', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'compact', 'eyebrow' => 'Überblick',
                    'title' => 'REDAXO 6 kennenlernen', 'text' => 'Backend, Projekt, Module, Templates, YForm und Navigation – mit Screenshots und Codebeispielen zum Nachbauen.']],
                ['type' => 'richtext', 'data' => ['variant' => 'standard', 'eyebrow' => '', 'title' => '', 'intro' => '', 'meta' => '',
                    'measure' => 'normal', 'dropcap' => false, 'toc' => false, 'reveal' => 'off', 'scrollspy' => 'off',
                    'text' => '<p class="t-warn"><strong>In Entwicklung:</strong> REDAXO 6 entsteht im Branch 6.x von redaxo/core. Die Beispiele entsprechen dem Stand vom November 2026 – Namen und Details können sich bis zur Veröffentlichung noch ändern.</p><p>Hier steht, wie ein Projekt mit REDAXO 6 entsteht und wie die wichtigsten Bausteine aussehen – und was sich gegenüber REDAXO 5 ändert.</p>']],
                ['type' => 'features', 'tunes' => ['background' => 'muted'], 'data' => [
                    'variant' => 'icons', 'eyebrow' => 'Themen', 'title' => 'Die Seiten in diesem Bereich', 'intro' => '', 'size' => 'm',
                    'items' => [
                        ['icon' => 'arrows-clockwise', 'title' => 'Was sich ändert', 'text' => 'REDAXO 5 und 6 im Vergleich: Module, Templates, AddOns, Struktur, Konfiguration.', 'link_label' => 'Vergleichen', 'link' => '/was-sich-aendert'],
                        ['icon' => 'gear', 'title' => 'Technik', 'text' => 'Das Fundament: Namespaces, Module und Templates als Klassen, AddOns mit Composer, Symfony-Komponenten.', 'link_label' => 'Lesen', 'link' => '/technik'],
                        ['icon' => 'browsers', 'title' => 'Backend', 'text' => 'Rundgang mit Screenshots – hell und dunkel, Medienpool, Linkmap, auf dem Telefon.', 'link_label' => 'Ansehen', 'link' => '/backend'],
                        ['icon' => 'terminal-window', 'title' => 'Projekt anlegen', 'text' => 'Von composer create-project bis zum ersten migrate.', 'link_label' => 'Lesen', 'link' => '/projekt'],
                        ['icon' => 'code', 'title' => 'Module und Templates', 'text' => 'PHP-Klassen mit Feldern als Attributen und Templates mit Inhaltsbereichen.', 'link_label' => 'Lesen', 'link' => '/module-templates'],
                        ['icon' => 'puzzle-piece', 'title' => 'Native Module', 'text' => 'Gewöhnliche Module auf der Website pflegen – in der Seitenleiste von fluid.', 'link_label' => 'Ausprobieren', 'link' => '/native-module'],
                        ['icon' => 'list-checks', 'title' => 'YForm', 'text' => 'Tabellen als Klassen im Projekt und Daten mit wenigen Zeilen abfragen.', 'link_label' => 'Lesen', 'link' => '/yform'],
                        ['icon' => 'tree-structure', 'title' => 'Navigation', 'text' => 'Menü, Brotkrumen und strukturierte Daten als Array – mit navigation_array.', 'link_label' => 'Lesen', 'link' => '/navigation'],
                    ],
                ]],
                $cta,
            ],
        ],

        // ------------------------------------------------------------------ Was sich ändert
        [
            'slug' => 'was-sich-aendert', 'title' => 'Was sich ändert', 'menu' => true, 'parent' => 'redaxo-6',
            'blocks' => [
                ['type' => 'hero', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'compact', 'eyebrow' => 'REDAXO 5 → 6',
                    'title' => 'Was sich gegenüber REDAXO 5 ändert', 'text' => 'Vertraute Ideen, neues Fundament: Struktur, Slices und Module bleiben – nur sauberer gebaut.']],
                ['type' => 'features', 'data' => [
                    'variant' => 'list', 'eyebrow' => '', 'title' => 'Bisher und jetzt', 'intro' => '', 'size' => 'l',
                    'items' => [
                        ['icon' => 'code', 'title' => 'Module: von Code in der Datenbank zu PHP-Klassen', 'text' => 'REDAXO 5: Ein- und Ausgabe als PHP im Backend gespeichert. REDAXO 6: Klasse mit #[AsModule], input() und output() – im Projekt versioniert.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'layout', 'title' => 'Templates: von Datenbank-Code zu Klassen mit Inhaltsbereichen', 'text' => 'Ein Template erbt von Template, rendert sich selbst und legt fest, welche Module in welchem Bereich erlaubt sind.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'package', 'title' => 'AddOns: vom Installer zu Composer', 'text' => 'REDAXO 5: herunterladen, installieren, aktivieren. REDAXO 6: Composer-Paket – im Projekt eingetragen heißt aktiv.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'tree-structure', 'title' => 'Struktur: vom AddOn zum Core', 'text' => 'Struktur, Inhalte, Arbeitsversion und Historie waren das AddOn „structure“ mit Plugins – jetzt sind sie Teil des Kerns.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'brackets-curly', 'title' => 'Klassen: von rex_article zu Redaxo\\Core\\Content\\Article', 'text' => 'Namespaces, Typen und Attribute statt globaler rex_-Klassen.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'gear', 'title' => 'Konfiguration: .env und Betriebsmodi', 'text' => 'Datenbank und Modus kommen aus der Umgebung – dev, live oder hardened.', 'link_label' => '', 'link' => ''],
                    ],
                ]],
                ['type' => 'quote', 'tunes' => ['background' => 'tint'], 'data' => ['variant' => 'single', 'eyebrow' => '', 'title' => '', 'items' => [
                    ['text' => 'Slices bleiben Slices. Was sich ändert, ist, was man mit ihnen machen kann.', 'name' => 'fluid', 'role' => 'Blöcke für REDAXO 6', 'image' => null],
                ]]],
                $cta,
            ],
        ],

        // ------------------------------------------------------------------ Technik
        [
            'slug' => 'technik', 'title' => 'Technik', 'menu' => true, 'parent' => 'redaxo-6',
            'blocks' => [
                ['type' => 'hero', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'compact', 'eyebrow' => 'Technik',
                    'title' => 'Das Fundament von REDAXO 6', 'text' => 'Was im Branch 6.x von redaxo/core steckt – und wie ein Projekt entsteht.']],
                ['type' => 'steps', 'data' => [
                    'variant' => 'process', 'eyebrow' => 'Ablauf', 'title' => 'Vom leeren Ordner zur Website', 'intro' => '',
                    'marker' => 'auto', 'colors' => 'auto', 'motion' => 'auto',
                    'items' => [
                        ['meta' => 'Schritt 1', 'title' => 'composer create-project', 'text' => 'Das Projekt-Skelett redaxo/project holt den Core als Abhängigkeit.', 'icon' => ''],
                        ['meta' => 'Schritt 2', 'title' => '.env', 'text' => 'Datenbank (DATABASE_URL), Modus (dev, live, hardened) und Name der Installation.', 'icon' => ''],
                        ['meta' => 'Schritt 3', 'title' => 'console setup', 'text' => 'Einrichtung samt Admin-Konto – auch ohne Browser.', 'icon' => ''],
                        ['meta' => 'Schritt 4', 'title' => 'console migrate', 'text' => 'Bei jedem Update: Datenbank auf den Stand von Core, AddOns und Projekt bringen.', 'icon' => ''],
                    ],
                ]],
                ['type' => 'richtext', 'data' => [
                    'variant' => 'article', 'eyebrow' => '', 'title' => 'Die wichtigsten Bausteine', 'intro' => '', 'meta' => 'Stand: Branch 6.x',
                    'measure' => 'normal', 'dropcap' => false, 'toc' => true, 'reveal' => 'off', 'scrollspy' => 'mark',
                    'text' => '<h3>Namespaces statt rex_-Klassen</h3><p>Alle Klassen des Cores liegen unter <code>Redaxo\Core\…</code> – etwa <code>Redaxo\Core\Content\Article</code> oder <code>Redaxo\Core\Database\Sql</code>. Die Datenbank-Klasse hat typisierte Getter wie <code>getIntValue()</code>.</p>'
                        . '<h3>Module und Templates als PHP-Klassen</h3><p>Ein Modul erbt von <code>Module</code> und trägt das Attribut <code>#[AsModule(\'key\', \'Name\')]</code>, mit den Methoden <code>input()</code> und <code>output()</code>. Templates erben von <code>Template</code>, rendern sich selbst und legen fest, welche Module in welchem Inhaltsbereich erlaubt sind. REDAXO findet die Klassen automatisch.</p>'
                        . '<h3>AddOns sind Composer-Pakete</h3><p>Ein Paket vom Typ <code>redaxo-addon</code> ist installiert und aktiv – ohne zusätzlichen Schalter. Seine Tabellen beschreibt es in <code>install()</code>; das läuft bei jedem <code>console migrate</code> und muss deshalb wiederholbar sein. Einmalige Schritte sind Migrationen mit eigenem Protokoll.</p>'
                        . '<h3>Extension Points</h3><p>Erweiterungen hängen sich weiter über Extension Points ein – teils als eigene Klassen, etwa <code>ArticleContentUpdated</code> für jede Änderung an Inhalten.</p>'
                        . '<h3>Symfony-Komponenten</h3><p>Konsole, Mailer, HTTP-Client, HTML-Sanitizer, Dotenv und Runtime kommen aus Symfony 8 – bewährte Bausteine statt Eigenbau.</p>',
                ]],
                ['type' => 'faq', 'tunes' => ['background' => 'muted'], 'data' => [
                    'variant' => 'stacked', 'eyebrow' => 'Fragen', 'title' => 'Häufige Fragen zu REDAXO 6', 'intro' => '',
                    'items' => [
                        ['q' => 'Ist REDAXO 6 schon fertig?', 'a' => '<p>Nein. REDAXO 6 entsteht im Branch 6.x von redaxo/core. Für laufende Projekte ist REDAXO 5 der gepflegte Zweig.</p>'],
                        ['q' => 'Welche PHP-Version brauche ich?', 'a' => '<p>REDAXO 6 setzt PHP 8.5 voraus.</p>'],
                        ['q' => 'Wo liegen Module und Templates?', 'a' => '<p>Als PHP-Klassen im Projekt (bzw. in AddOns) – nicht mehr als Code in der Datenbank. So lassen sie sich versionieren, prüfen und deployen wie jeder andere Code.</p>'],
                    ],
                ]],
                $cta,
            ],
        ],

        // ------------------------------------------------------------------ Backend
        [
            'slug' => 'backend', 'title' => 'Backend', 'menu' => true, 'parent' => 'redaxo-6',
            'blocks' => [
                ['type' => 'hero', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'compact', 'eyebrow' => 'REDAXO 6',
                    'title' => 'Das neue Backend von REDAXO 6', 'text' => 'Ein Rundgang mit Screenshots: Struktur, Blöcke, Medienpool, Linkmap, hell und dunkel, auf dem Telefon.']],
                ['type' => 'richtext', 'data' => [
                    'variant' => 'standard', 'eyebrow' => '', 'title' => '', 'intro' => '', 'meta' => '',
                    'measure' => 'normal', 'dropcap' => false, 'toc' => false, 'reveal' => 'off', 'scrollspy' => 'off',
                    'text' => '<p class="t-warn"><strong>Noch in Arbeit:</strong> Das Backend von REDAXO 6 wird gerade gebaut. Die Bilder zeigen den Stand vom November 2026 (Branch 6.x von redaxo/core) – Aussehen und Details können sich bis zur Veröffentlichung noch ändern.</p>'
                        . '<p>Lange lief auch das Backend von REDAXO 6 noch auf Bootstrap 3 und jQuery. Seit dem Umbau Ende Oktober 2026 ist es frei davon: eigene Komponenten, Farben über CSS-Variablen, ohne Sass.</p>',
                ]],
                ['type' => 'media_text', 'data' => ['variant' => 'left', 'eyebrow' => 'Struktur', 'title' => 'Vertraut, nur aufgeräumter',
                    'text' => '<p>Kategorien und Artikel, Sprachen, Status und Funktionen stehen da, wo REDAXO-Nutzer sie erwarten. Neu sind ruhigere Flächen, klare Linien und die Navigation mit Hauptmenü, AddOns und eigenen Bereichen.</p>',
                    'list' => '', 'image' => 'redaxo6-backend-struktur.jpg', 'ratio' => '16:9', 'split' => 'media']],
                ['type' => 'media_text', 'data' => ['variant' => 'right', 'eyebrow' => 'Inhalte', 'title' => 'Vertraute Block-Einpflege',
                    'text' => '<p>Inhalte werden gepflegt wie bisher: Ein Artikel besteht aus Blöcken (Slices), „Block hinzufügen“ öffnet die Auswahl der erlaubten Module, und jeder Block hat seine Leiste zum Bearbeiten, Löschen, Online/Offline und Verschieben – nur im neuen Erscheinungsbild.</p>',
                    'list' => "Arbeitsversion und Liveversion\nHistorie der Inhalte\nMetadaten in der Seitenleiste", 'image' => 'redaxo6-backend-inhalte.jpg', 'ratio' => '16:9', 'split' => 'media']],
                ['type' => 'cards', 'tunes' => ['background' => 'muted'], 'data' => [
                    'variant' => 'image', 'eyebrow' => 'Hell und dunkel', 'title' => 'Ein Backend, zwei Farbschemata',
                    'intro' => 'Das Backend folgt der Einstellung des Systems – oder jeder legt im eigenen Profil hell oder dunkel fest. Hier dasselbe Formular eines Blocks in beiden Varianten.',
                    'size' => 'l', 'ratio' => '16:9', 'more_label' => '', 'more_link' => '',
                    'items' => [
                        ['image' => 'redaxo6-backend-block-hell.jpg', 'eyebrow' => '', 'title' => 'Hell', 'text' => 'Block bearbeiten: Felder, Hinweise und Knöpfe im hellen Schema.', 'link_label' => '', 'link' => ''],
                        ['image' => 'redaxo6-backend-block-dunkel.jpg', 'eyebrow' => '', 'title' => 'Dunkel', 'text' => 'Dasselbe Formular im dunklen Schema – mit gedämpftem Grün für Bearbeiten-Bereiche.', 'link_label' => '', 'link' => ''],
                    ],
                ]],
                ['type' => 'media_text', 'data' => ['variant' => 'left', 'eyebrow' => 'Medienpool', 'title' => 'Medienpool im Overlay – und mehrsprachig',
                    'text' => '<p>Der Medienpool öffnet sich als Overlay über der Seite statt in einem eigenen Fenster. Titel und Beschreibungen der Medien lassen sich jetzt je Sprache pflegen – die Sprachumschaltung steht direkt über der Liste.</p>',
                    'list' => "Kein Popup-Fenster mehr\nTitel und Alt-Text je Sprache\nAuswahl mehrerer Dateien", 'image' => 'redaxo6-backend-medienpool.jpg', 'ratio' => '16:9', 'split' => 'media']],
                ['type' => 'media_text', 'data' => ['variant' => 'right', 'eyebrow' => 'Linkmap', 'title' => 'Seiten wählen in zwei Spalten',
                    'text' => '<p>Auch die Linkmap kommt als Overlay: links die Kategorien, rechts die Artikel der gewählten Kategorie. Overlays lassen sich stapeln – etwa der Medienpool aus einem Formular in einem Overlay heraus.</p>',
                    'list' => '', 'image' => 'redaxo6-backend-linkmap.jpg', 'ratio' => '16:9', 'split' => 'media']],
                ['type' => 'media_text', 'data' => ['variant' => 'left', 'eyebrow' => 'Unterwegs', 'title' => 'Auch auf dem Telefon bedienbar',
                    'text' => '<p>Auf schmalen Bildschirmen klappt das Menü ein, und Tabellen stellen ihre Zeilen untereinander dar. So bleibt die Struktur auch auf dem Telefon lesbar.</p>',
                    'list' => '', 'image' => 'redaxo6-backend-mobil.jpg', 'ratio' => '3:4', 'split' => 'text']],
                ['type' => 'features', 'tunes' => ['background' => 'muted'], 'data' => [
                    'variant' => 'icons', 'eyebrow' => 'Unter der Haube', 'title' => 'Was technisch neu ist', 'intro' => '', 'size' => 'm',
                    'items' => [
                        ['icon' => 'code', 'title' => 'Ohne Bootstrap und jQuery', 'text' => 'Eigene Komponenten mit eigenem JavaScript – seit dem Umbau Ende Oktober 2026 ohne Bootstrap, jQuery und Sass.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'paint-brush', 'title' => 'Farben als CSS-Variablen', 'text' => 'Hell und dunkel aus denselben Variablen – AddOns nutzen sie und passen sich automatisch an.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'app-window', 'title' => 'Overlays statt Popups', 'text' => 'Medienpool, Linkmap und andere Seiten öffnen sich in gestapelten Dialogen im selben Fenster.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'shield-check', 'title' => 'Content-Security-Policy', 'text' => 'Die Backend-Seiten senden eine CSP: Skripte und Styles nur aus erlaubten Quellen.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'fingerprint', 'title' => 'Anmelden mit Passkey', 'text' => 'Neben dem Passwort geht die Anmeldung auch mit Passkeys (WebAuthn).', 'link_label' => '', 'link' => ''],
                        ['icon' => 'puzzle-piece', 'title' => 'Übergang für AddOns', 'text' => 'AddOns mit Bootstrap-Markup können übergangsweise eine Kompatibilitätsschicht laden – sie entfällt mit REDAXO 7.', 'link_label' => '', 'link' => ''],
                    ],
                ]],
                ['type' => 'richtext', 'tunes' => ['background' => 'muted'], 'data' => [
                    'variant' => 'standard', 'eyebrow' => 'Für Entwickler', 'title' => 'Module und Formulare gestalten', 'intro' => '', 'meta' => '',
                    'measure' => 'normal', 'dropcap' => false, 'toc' => false, 'reveal' => 'off', 'scrollspy' => 'off',
                    'text' => '<p>Das Backend bringt fertige Bausteine mit: Feldzeilen, Eingaben, Knöpfe, Kästen, Tabellen und ein Raster. Wer sie nutzt, muss fast nichts selbst gestalten – und bekommt hell, dunkel, Tastaturbedienung und Barrierefreiheit gleich mit.</p>'
                        . '<ul><li><strong>Am einfachsten:</strong> Felder als Attribute im Modul – das Formular kommt komplett vom Core.</li>'
                        . '<li><strong>Eigenes Markup:</strong> die Klassen des Cores (<code>rex-field</code>, <code>rex-input</code>, <code>rex-button</code> …) statt eigener.</li>'
                        . '<li><strong>Eigene Komponenten:</strong> mit eigenem Präfix und den Farb-Variablen des Cores (<code>--rex-color-…</code>).</li>'
                        . '<li><strong>Nicht:</strong> Klassen des Cores überschreiben, <code>style="…"</code>, Inline-Skripte oder <code>onclick</code> – die Content-Security-Policy blockiert sie.</li></ul>',
                ]],
                ['type' => 'code', 'data' => ['eyebrow' => '', 'title' => 'Felder als Attribute, eigene Anordnung', 'intro' => 'Ohne input() stehen alle Felder untereinander. Mit input() lassen sich einzelne Zeilen ins Raster setzen; $form->rest() gibt die übrigen aus.', 'note' => 'Weitere Bausteine: $form->widget() (nur die Eingabe), $form->label() (nur das Label).',
                    'items' => [
                        $c('beModule', 'src/Module/HeroModule.php', 'php'),
                    ]]],
                ['type' => 'code', 'data' => ['eyebrow' => '', 'title' => 'Eigenes Markup mit den Klassen des Cores', 'intro' => 'So sieht das Markup aus, das der Core selbst erzeugt. Ab 992 px steht das Label links, darunter darüber.', 'note' => 'data-confirm fragt vor dem Absenden nach – ohne eigenes JavaScript.',
                    'items' => [
                        $c('beMarkup', 'Modul-Eingabe oder Backend-Seite', 'html'),
                    ]]],
                ['type' => 'code', 'data' => ['eyebrow' => '', 'title' => 'Kästen und Tabellen auf eigenen Seiten', 'intro' => '', 'note' => '',
                    'items' => [
                        $c('beSection', 'Backend-Seite eines AddOns', 'php'),
                    ]]],
                ['type' => 'code', 'data' => ['eyebrow' => '', 'title' => 'Eigene Komponente, eigene Dateien', 'intro' => 'Stile und Skripte kommen als Dateien ins Backend. Rundungen wie der Core: Felder und Kästen eckig, nur Knöpfe und Dialoge leicht gerundet.', 'note' => '',
                    'items' => [
                        $c('beCss', 'public/assets/project/backend.css', 'css'),
                        $c('beAssets', 'src/Project.php', 'php'),
                    ]]],
                ['type' => 'code', 'data' => ['eyebrow' => '', 'title' => 'JavaScript für Markup, das später kommt', 'intro' => 'Overlays und Wiederholgruppen fügen Markup nachträglich ein. Der Core meldet das mit dem Ereignis rex:ready am neuen Element – kein jQuery nötig.', 'note' => '',
                    'items' => [
                        $c('beJs', 'public/assets/project/backend.js', 'js'),
                    ]]],
                ['type' => 'faq', 'data' => [
                    'variant' => 'stacked', 'eyebrow' => 'Fragen', 'title' => 'Fragen zum neuen Backend', 'intro' => '',
                    'items' => [
                        ['q' => 'Ist das Backend fertig?', 'a' => '<p>Nein. Es entsteht im Branch 6.x von redaxo/core und ändert sich laufend. Diese Seite zeigt einen Zwischenstand.</p>'],
                        ['q' => 'Laufen meine AddOns aus REDAXO 5 im neuen Backend?', 'a' => '<p>AddOns müssen für REDAXO 6 ohnehin angepasst werden (Namespaces, Composer). Für Backend-Seiten mit Bootstrap-Markup gibt es übergangsweise eine Kompatibilitätsschicht; besser ist es, gleich die Komponenten des Cores zu nutzen.</p>'],
                        ['q' => 'Wo kann ich mitreden?', 'a' => '<p>Im Repository redaxo/core auf GitHub – mit Issues und Pull Requests gegen den Branch 6.x.</p>'],
                    ],
                ]],
                $cta,
            ],
        ],

        [
            'slug' => 'projekt', 'title' => 'Projekt anlegen', 'menu' => true, 'parent' => 'redaxo-6',
            'blocks' => [
                ['type' => 'hero', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'compact', 'eyebrow' => 'REDAXO 6',
                    'title' => 'Ein Projekt anlegen', 'text' => 'Composer, eine .env-Datei und zwei Konsolenbefehle – dann läuft REDAXO 6.']],
                ['type' => 'richtext', 'data' => ['variant' => 'standard', 'eyebrow' => '', 'title' => '', 'intro' => '', 'meta' => '',
                    'measure' => 'normal', 'dropcap' => false, 'toc' => false, 'reveal' => 'off', 'scrollspy' => 'off',
                    'text' => '<p class="t-warn"><strong>In Entwicklung:</strong> REDAXO 6 entsteht im Branch 6.x von redaxo/core. Die Beispiele entsprechen dem Stand vom November 2026 – Namen und Details können sich bis zur Veröffentlichung noch ändern.</p><p>Voraussetzungen: PHP 8.5, Composer und eine MySQL- oder MariaDB-Datenbank. Der Webserver zeigt auf den Ordner <code>public</code>.</p>']],
                ['type' => 'code', 'data' => ['eyebrow' => '', 'title' => '1. Projekt holen', 'intro' => 'Das Projekt-Skelett redaxo/project holt den Core als Abhängigkeit. Solange es keine stabile Version gibt, ist :6.x-dev nötig.', 'note' => '',
                    'items' => [
                        $c('create', 'Terminal', 'bash'),
                    ]]],
                ['type' => 'code', 'data' => ['eyebrow' => '', 'title' => '2. Verbindung und Modus', 'intro' => 'Die Einstellungen kommen aus der Umgebung. Die Vorlage .env beschreibt alle Variablen; eigene Werte gehören in .env.local.', 'note' => 'Modi: dev (Entwicklung), live (öffentlich erreichbar) und hardened (wie live, zusätzlich gehärtet).',
                    'items' => [
                        $c('env', '.env.local', 'env'),
                    ]]],
                ['type' => 'code', 'data' => ['eyebrow' => '', 'title' => '3. Einrichten', 'intro' => 'Die Einrichtung geht ganz ohne Browser.', 'note' => '',
                    'items' => [
                        $c('setup', 'Terminal', 'bash'),
                    ]]],
                ['type' => 'code', 'data' => ['eyebrow' => '', 'title' => 'So sieht das Projekt aus', 'intro' => '', 'note' => '',
                    'items' => [
                        $c('tree', 'Ordner', 'text'),
                    ]]],
                $cta,
            ],
        ],

        [
            'slug' => 'module-templates', 'title' => 'Module und Templates', 'menu' => true, 'parent' => 'redaxo-6',
            'blocks' => [
                ['type' => 'hero', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'compact', 'eyebrow' => 'REDAXO 6',
                    'title' => 'Module und Templates als Klassen', 'text' => 'Kein PHP-Code mehr in der Datenbank: Module und Templates liegen im Projekt, versioniert wie jeder andere Code.']],
                ['type' => 'richtext', 'data' => ['variant' => 'standard', 'eyebrow' => '', 'title' => '', 'intro' => '', 'meta' => '',
                    'measure' => 'normal', 'dropcap' => false, 'toc' => false, 'reveal' => 'off', 'scrollspy' => 'off',
                    'text' => '<p class="t-warn"><strong>In Entwicklung:</strong> REDAXO 6 entsteht im Branch 6.x von redaxo/core. Die Beispiele entsprechen dem Stand vom November 2026 – Namen und Details können sich bis zur Veröffentlichung noch ändern.</p><p>Ein Modul erbt von <code>Module</code> und trägt das Attribut <code>#[AsModule]</code>. Seine Felder sind Eigenschaften mit Feldtypen als Attributen – daraus baut REDAXO das Formular im Backend, speichert die Werte als JSON im Slice und füllt die Eigenschaften für die Ausgabe. REDAXO findet die Klassen von selbst.</p>']],
                ['type' => 'code', 'data' => ['eyebrow' => '', 'title' => 'Ein Modul', 'intro' => 'Text, Textarea, Bild aus dem Medienpool und ein Link auf einen Artikel.', 'note' => 'Weitere Feldtypen: Number, Email, Url, Checkbox, Choice, DatePicker, Collection (Wiederholgruppe) …',
                    'items' => [
                        $c('module', 'src/Module/TeaserModule.php', 'php'),
                    ]]],
                ['type' => 'code', 'data' => ['eyebrow' => '', 'title' => 'Ein Template mit zwei Inhaltsbereichen', 'intro' => 'Das Template legt fest, welche Inhaltsbereiche es gibt und welche Module darin erlaubt sind. Das Markup steht in einer View-Datei daneben.', 'note' => '',
                    'items' => [
                        $c('template', 'src/Template/PageTemplate.php', 'php'),
                        $c('view', 'src/Template/PageTemplate.view.php', 'php'),
                    ]]],
                ['type' => 'code', 'data' => ['eyebrow' => '', 'title' => 'Standard-Template festlegen', 'intro' => 'Die Klasse Project ist der Einstieg ins eigene Projekt – für Konfiguration, Start und eigene Tabellen.', 'note' => '',
                    'items' => [
                        $c('project', 'src/Project.php', 'php'),
                    ]]],
                $cta,
            ],
        ],

        [
            'slug' => 'native-module', 'title' => 'Native Module', 'menu' => true, 'parent' => 'redaxo-6',
            'blocks' => [
                ['type' => 'hero', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'compact', 'eyebrow' => 'REDAXO 6',
                    'title' => 'Native Module auf der Website', 'text' => 'Gewöhnliche REDAXO-Module – ohne fluid-Felder – lassen sich mit fluid auf der Website pflegen: in derselben Seitenleiste wie die Blöcke.']],
                ['type' => 'richtext', 'data' => ['variant' => 'standard', 'eyebrow' => '', 'title' => '', 'intro' => '', 'meta' => '',
                    'measure' => 'normal', 'dropcap' => false, 'toc' => false, 'reveal' => 'off', 'scrollspy' => 'off',
                    'text' => '<p class="t-warn"><strong>In Entwicklung:</strong> REDAXO 6 entsteht im Branch 6.x von redaxo/core. Die Beispiele entsprechen dem Stand vom November 2026 – Namen und Details können sich bis zur Veröffentlichung noch ändern.</p>'
                        . '<p>„Native Module“ sind hier gewöhnliche Module von REDAXO 6: eine Klasse mit <code>#[AsModule]</code>, Feldern als Attributen und <code>output()</code> – ohne etwas von fluid. Sie stehen neben den fluid-Blöcken auf derselben Seite und werden im Backend wie immer gepflegt.</p>'
                        . '<h3>So geht fluid damit um</h3><ul>'
                        . '<li>Im Bearbeiten-Modus zeigt die Ausgabe eines Moduls beim Darüberfahren „… bearbeiten“.</li>'
                        . '<li>Die Seitenleiste zeigt seine Felder im Formular von fluid – Text, Textbereich, E-Mail, Internetadresse, Zahl, Ja/Nein, Auswahl, Datum, Uhrzeit, Bild aus dem Medienpool und Seitenlink.</li>'
                        . '<li>„Übernehmen“ prüft wie das Backend (Pflichtfelder, Länge, gültige Werte, <code>validate()</code> des Moduls), speichert in den Entwurf und erneuert die Ausgabe auf der Seite. Sichtbar für Besucher wird es mit „Veröffentlichen“.</li>'
                        . '<li>Baut ein Modul sein Formular mit eigenem <code>input()</code> oder nutzt es andere Feldtypen (z. B. Wiederholgruppen, Mehrfachauswahl), zeigt die Seitenleiste das Formular des Cores.</li>'
                        . '<li>Das Template „fluid“ erlaubt gewöhnliche Module neben den Blöcken des Kits. Neu eingefügt werden sie im Backend („Block hinzufügen“), die Blockauswahl auf der Website zeigt nur fluid-Blöcke.</li></ul>'
                        . '<p><strong>Ausprobieren:</strong> Angemeldet oben auf „Bearbeiten“ tippen und mit der Maus auf die beiden Beispiele unten zeigen. Im Demo-Zugang ohne Anmeldung ist das nicht möglich.</p>']],
                ['native' => 'hinweis', 'values' => ['headline' => 'Ein natives Modul zum Ausprobieren', 'text' => 'Dieses Modul kennt fluid nicht – es ist eine gewöhnliche Modulklasse. Im Bearbeiten-Modus öffnet „Hinweis (Beispiel) bearbeiten“ seine Felder in der Seitenleiste.', 'kind' => 'info', 'strong' => false, 'until' => null, 'image' => null, 'link' => null]],
                ['native' => 'hinweis_eigen', 'values' => ['title' => 'Modul mit eigenem Formular', 'text' => 'Sein input() baut das Formular selbst – die Seitenleiste zeigt dafür das Formular des Cores.']],
                ['type' => 'code', 'data' => ['eyebrow' => '', 'title' => 'Das Beispielmodul', 'intro' => 'Felder als Eigenschaften mit den Feld-Attributen des Cores, die Ausgabe in output(). Mehr braucht fluid nicht.', 'note' => 'Die vollständigen Beispiele liegen in examples/module des AddOns fluid.',
                    'items' => [
                        $c('nmodule', 'src/Module/HinweisModule.php', 'php'),
                    ]]],
                ['type' => 'code', 'data' => ['eyebrow' => '', 'title' => 'Eigenes Formular', 'intro' => 'Mit eigenem input() bestimmt das Modul den Aufbau seines Formulars – dann zeigt fluid das Formular des Cores.', 'note' => '',
                    'items' => [
                        $c('nmodule_input', 'src/Module/HinweisEigenesFormularModule.php', 'php'),
                    ]]],
                ['type' => 'richtext', 'data' => ['variant' => 'standard', 'eyebrow' => 'Weiter mit fluid', 'title' => 'Vom nativen Modul zum fluid-Block', 'intro' => '', 'meta' => '',
                    'measure' => 'normal', 'dropcap' => false, 'toc' => false, 'reveal' => 'off', 'scrollspy' => 'off',
                    'text' => '<p>Ein natives Modul funktioniert mit fluid ohne Änderung. Soll es sich anfühlen wie die Blöcke von fluid, sind es drei Schritte:</p>'
                        . '<ol><li><strong>Klasse:</strong> erbt von <code>FluidBlock</code> statt von <code>Module</code>; der Schlüssel beginnt mit <code>fluid.</code>.</li>'
                        . '<li><strong>Felder:</strong> bleiben Attribute an Eigenschaften – mit denselben Namen und Parametern, nur aus <code>FriendsOfRedaxo\\Fluid\\Field</code>. Dazu kommen Felder von fluid wie <code>Link</code>, <code>Media</code>, <code>RichText</code> oder <code>Repeater</code>.</li>'
                        . '<li><strong>Ausgabe:</strong> <code>view()</code> statt <code>output()</code> – in der Klasse oder als View-Datei daneben; <code>$b-&gt;edit(\'feld\')</code> macht ein Feld direkt auf der Seite bearbeitbar.</li></ol>'
                        . '<p><strong>Was man gewinnt:</strong> Texte direkt auf der Seite, Vorschau beim Tippen, der Block erscheint in der Blockauswahl der Website, Hintergrund und Abstände je Abschnitt, Layout-Spalten, Kopieren und Einfügen, Greifer – und die Gestaltung kommt aus dem Kit, auch beim Wechsel der Design-Vorlage.</p>'
                        . '<p><strong>Gut zu wissen:</strong> Der Schlüssel ändert sich, vorhandene Slices des nativen Moduls werden nicht von selbst zu Blöcken. Beides darf nebeneinander bestehen – neue Inhalte mit dem Block, alte bleiben, bis sie ersetzt sind.</p>']],
                ['type' => 'code', 'data' => ['eyebrow' => '', 'title' => 'Derselbe Hinweis als fluid-Block', 'intro' => 'Eine Klasse: Felder als Attribute, Ausgabe in view() – mehr braucht ein Block nicht.', 'note' => 'Mehr dazu in der Entwickler-Referenz (docs/entwickler.md, „Ein eigener Block“) und auf der Seite „Eigenes Kit“.',
                    'items' => [
                        $c('hblock', 'src/Block/Hinweis.php', 'php'),
                    ]]],
                // derselbe Hinweis als Block – nur, wenn examples/block/Hinweis.php im Projekt liegt
                ['type' => 'hinweis', 'data' => ['headline' => 'Derselbe Hinweis als fluid-Block', 'text' => 'Diesen Text direkt hier auf der Seite ändern – alle Felder stehen wie bei jedem Block in der Seitenleiste.', 'kind' => 'ok', 'strong' => false, 'until' => '', 'link' => '', 'image' => '']],
                $cta,
            ],
        ],

        [
            'slug' => 'yform', 'title' => 'YForm', 'menu' => true, 'parent' => 'redaxo-6', 'requires' => 'yform',
            'blocks' => [
                ['type' => 'hero', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'compact', 'eyebrow' => 'REDAXO 6',
                    'title' => 'YForm: Tabellen als Klassen', 'text' => 'Tabellen, Felder und Listen stehen im Projekt – migrate legt die Tabellen an, das Backend zeigt sie unter „Daten“.']],
                ['type' => 'richtext', 'data' => ['variant' => 'standard', 'eyebrow' => '', 'title' => '', 'intro' => '', 'meta' => '',
                    'measure' => 'normal', 'dropcap' => false, 'toc' => false, 'reveal' => 'off', 'scrollspy' => 'off',
                    'text' => '<p class="t-warn"><strong>In Entwicklung:</strong> REDAXO 6 entsteht im Branch 6.x von redaxo/core. Die Beispiele entsprechen dem Stand vom November 2026 – Namen und Details können sich bis zur Veröffentlichung noch ändern.</p><p>YForm 6 ist noch nicht stabil und kommt bis dahin direkt von GitHub. Eine Tabelle ist eine Klasse mit <code>#[AsTable]</code>; ihre Felder liefert <code>fields()</code>, die Spalten der Liste im Backend <code>list()</code>.</p>']],
                ['type' => 'code', 'data' => ['eyebrow' => '', 'title' => 'YForm installieren', 'intro' => '', 'note' => '',
                    'items' => [
                        $c('yrequire', 'Terminal', 'bash'),
                    ]]],
                ['type' => 'code', 'data' => ['eyebrow' => '', 'title' => 'Eine Tabelle', 'intro' => '', 'note' => 'filterable() zeigt ein Feld in der Suche neben der Liste im Backend – ohne filterable() gibt es dort keine Suche.',
                    'items' => [
                        $c('ytable', 'src/YForm/EventTable.php', 'php'),
                    ]]],
                ['type' => 'code', 'data' => ['eyebrow' => '', 'title' => 'Daten abfragen', 'intro' => 'Abfragen beginnen bei der Tabellenklasse. Datumsfelder liefern DateTimeImmutable.', 'note' => '',
                    'items' => [
                        $c('yquery', 'Template oder Modul', 'php'),
                    ]]],
                $cta,
            ],
        ],

        [
            'slug' => 'navigation', 'title' => 'Navigation', 'menu' => true, 'parent' => 'redaxo-6',
            'blocks' => [
                ['type' => 'hero', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'compact', 'eyebrow' => 'REDAXO 6',
                    'title' => 'Navigation als Array', 'text' => 'Menüs, Brotkrumen und strukturierte Daten – mit navigation_array, jetzt auch für REDAXO 6.']],
                ['type' => 'richtext', 'data' => ['variant' => 'standard', 'eyebrow' => '', 'title' => '', 'intro' => '', 'meta' => '',
                    'measure' => 'normal', 'dropcap' => false, 'toc' => false, 'reveal' => 'off', 'scrollspy' => 'off',
                    'text' => '<p class="t-warn"><strong>In Entwicklung:</strong> REDAXO 6 entsteht im Branch 6.x von redaxo/core. Die Beispiele entsprechen dem Stand vom November 2026 – Namen und Details können sich bis zur Veröffentlichung noch ändern.</p><p>Das AddOn <a href="https://github.com/FriendsOfREDAXO/navigation_array">navigation_array</a> gibt es jetzt auch für REDAXO 6 (Branch 6.x) – neu gebaut für PHP 8.5: <code>Navigation</code> liefert die Struktur als typisierte Einträge (<code>NavigationItem</code>: name, url, active, current, children …), <code>walk()</code> rendert sie verschachtelt, die Ausgabe bleibt bei dir. Offline-Kategorien und solche ohne Recht fehlen. fluid baut sein Menü selbst damit.</p>']],
                ['type' => 'code', 'data' => ['eyebrow' => '', 'title' => 'navigation_array installieren', 'intro' => '', 'note' => '',
                    'items' => [
                        $c('navInstall', 'Terminal', 'bash'),
                    ]]],
                ['type' => 'code', 'data' => ['eyebrow' => '', 'title' => 'Menü, Brotkrumen und JSON-LD', 'intro' => '', 'note' => 'Optionen: start, depth, ignoreOffline, exclude, filter, data, articles, languageId. Außerdem items(), find(), breadcrumb(), jsonLd() und die Komponente NavigationMenu.',
                    'items' => [
                        $c('nav', 'Template', 'php'),
                    ]]],
                $cta,
            ],
        ],

        // ------------------------------------------------------------------ fluid (Übersicht mit Unterseiten)
        [
            'slug' => 'fluid', 'title' => 'fluid', 'menu' => true,
            'blocks' => [
                ['type' => 'hero', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'compact', 'eyebrow' => 'AddOn für REDAXO 6',
                    'title' => 'fluid: Bearbeiten direkt auf der Website', 'text' => 'Was die Redaktion damit kann, welche Blöcke es gibt – und wie Entwickler eigene Blöcke und Kits bauen.']],
                ['type' => 'features', 'data' => [
                    'variant' => 'icons', 'eyebrow' => 'Themen', 'title' => 'Die Seiten in diesem Bereich', 'intro' => '', 'size' => 'm',
                    'items' => [
                        ['icon' => 'pencil-simple', 'title' => 'Bearbeiten', 'text' => 'Texte, Bilder, Blöcke und Design direkt auf der Seite – mit Entwurf und Veröffentlichen.', 'link_label' => 'Ansehen', 'link' => '/bearbeiten'],
                        ['icon' => 'squares-four', 'title' => 'Bausteine', 'text' => 'Weitere Blöcke des Kits: Logos, Bento, Reiter, Preise und mehr.', 'link_label' => 'Ansehen', 'link' => '/bausteine'],
                        ['icon' => 'terminal-window', 'title' => 'fluid-Projekt', 'text' => 'fluid einbinden, Demo anlegen und eigene Blöcke bauen.', 'link_label' => 'Lesen', 'link' => '/fluid-projekt'],
                        ['icon' => 'paint-brush', 'title' => 'Eigenes Kit', 'text' => 'Was ein Kit ist und wie man eins baut – vererbt von fluid, mit eigenen Views und eigenem CSS.', 'link_label' => 'Lesen', 'link' => '/eigenes-kit'],
                    ],
                ]],
                $cta,
            ],
        ],

        // ------------------------------------------------------------------ Bearbeiten
        [
            'slug' => 'bearbeiten', 'title' => 'Bearbeiten', 'menu' => true, 'parent' => 'fluid',
            'blocks' => [
                ['type' => 'hero', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'compact', 'eyebrow' => 'fluid',
                    'title' => 'Bearbeiten direkt auf der Website', 'text' => 'Ein Feldschema je Block – daraus entstehen Backend-Formular, Bearbeiten im Kontext, Live-Vorschau und Ausgabe.']],
                ['type' => 'media_text', 'data' => ['variant' => 'left', 'title' => 'Blöcke in Datensätzen', 'text' => '<p>Auch Einträge aus YForm-Tabellen bekommen Blöcke – bearbeitet direkt in der Detailansicht, mit derselben Seitenleiste wie auf jeder Seite.</p>',
                    'image' => 'fluid-bearbeiten-datensatz.jpg', 'ratio' => '4:3', 'split' => 'even']],
                ['type' => 'media_text', 'data' => ['variant' => 'right', 'title' => 'Formulare zum Zusammenklicken', 'text' => '<p>Felder, Schritte, Bestätigung: Formulare entstehen in der Seitenleiste und speichern über YForm.</p>',
                    'image' => 'fluid-formular-schritte.jpg', 'ratio' => '4:3', 'split' => 'even']],
                ['type' => 'features', 'data' => [
                    'variant' => 'icons', 'eyebrow' => 'Im Alltag', 'title' => 'Was die Redaktion kann', 'intro' => '', 'size' => 'm',
                    'items' => [
                        ['icon' => 'pencil-simple', 'title' => 'Text direkt ändern', 'text' => 'Überschriften und Texte an Ort und Stelle – formatiert mit fett, kursiv, Marker, Farben, Listen und Links.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'sidebar-simple', 'title' => 'Alle Felder in der Seitenleiste', 'text' => 'Darstellung, Bilder aus dem Medienpool, Links über die Linkmap – mit Vorschau beim Tippen.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'squares-four', 'title' => 'Blöcke in Spalten', 'text' => 'Layout wählen, Blöcke in die Spalten stellen – auf dem Telefon automatisch untereinander.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'palette', 'title' => 'Design mit Vorlagen', 'text' => 'Farben, Schriften, Formen, Kopf und Fuß – live auf der Seite, mit Prüfung des Kontrasts.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'eye', 'title' => 'Entwurf und Veröffentlichen', 'text' => 'Änderungen landen in der Arbeitsversion – „Veröffentlichen“ bringt sie auf die Website.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'toggle-left', 'title' => 'Online/Offline, verschieben, löschen', 'text' => 'Jeder Block hat seine Leiste – auch Blöcke in Spalten.', 'link_label' => '', 'link' => ''],
                    ],
                ]],
                ['type' => 'richtext', 'tunes' => ['background' => 'muted'], 'data' => [
                    'variant' => 'standard', 'eyebrow' => 'Im Vergleich', 'title' => 'Was fluid anders macht als MForm, MBlock und blÖcks', 'intro' => '',
                    'measure' => 'wide', 'meta' => '', 'dropcap' => false, 'toc' => false, 'reveal' => 'off', 'scrollspy' => 'off',
                    'text' => '<p>Die bewährten AddOns lösen je eine Aufgabe im Backend: <strong>MForm</strong> baut Eingabeformulare für Module, <strong>MBlock</strong> macht Feldgruppen innerhalb eines Slices wiederholbar, <strong>blÖcks</strong> ergänzt die Slice-Liste um Verschieben, Kopieren und Online/Offline.</p>'
                        . '<p><strong>fluid</strong> setzt eine Ebene tiefer an – beim Modul selbst:</p>'
                        . '<ul><li><strong>Ein Schema für alles:</strong> Die Felder eines Blocks stehen einmal im Kit. Daraus entstehen das Formular im Backend, das Bearbeiten auf der Website, die Vorschau und die Daten der Ausgabe.</li>'
                        . '<li><strong>Echte Slices:</strong> Jeder Block ist ein Slice – auch die Blöcke in Layout-Spalten. Modulrechte, Arbeitsversion, Historie, Online/Offline und das Backend gelten wie immer.</li>'
                        . '<li><strong>Native Werte:</strong> Gespeichert wird wie bei jedem Modul des Cores in der JSON-Spalte data des Slices (Felder und Abschnitt). Keine eigenen Tabellen, nichts neben REDAXO vorbei.</li>'
                        . '<li><strong>Wiederholgruppen eingebaut:</strong> Einträge hinzufügen, sortieren, entfernen – im Backend und in der Seitenleiste gleich.</li>'
                        . '<li><strong>Medienpool und Linkmap:</strong> Die Auswahl öffnet Medienpool und Linkmap des Cores als Overlay; Links auf Artikel bleiben als redaxo://ID gültig, auch wenn der Artikel umzieht.</li>'
                        . '<li><strong>Kit mit Design-System:</strong> Blöcke, Template und CSS gehören zusammen – mit Style-Editor und Vorlagen.</li></ul>',
                ]],
                ['type' => 'layout', 'data' => ['preset' => '1-1', 'valign' => 'stretch', 'gap' => 'normal', 'stack' => 'default', 'reverse' => false],
                    'children' => [
                        [1, ['type' => 'cta', 'data' => ['variant' => 'box', 'eyebrow' => 'Für Entwickler', 'title' => 'Ein Block = eine kleine Klasse',
                            'text' => 'Felder ins Kit, Ausgabe als View-Datei, fertig. Formular, Speichern und Bearbeiten auf der Website kommen von fluid.',
                            'button_label' => 'Technik', 'button_link' => '/technik', 'button2_label' => '', 'button2_link' => '']]],
                        [2, ['type' => 'cta', 'data' => ['variant' => 'box', 'eyebrow' => 'Für die Redaktion', 'title' => 'Sehen, was man ändert',
                            'text' => 'Kein Rätselraten mehr zwischen Formular und Ergebnis – bearbeitet wird dort, wo der Text steht.',
                            'button_label' => 'Zur Startseite', 'button_link' => '/start', 'button2_label' => '', 'button2_link' => '']]],
                    ],
                ],
            ],
        ],

        // ------------------------------------------------------------------ Bausteine (Blöcke des Kits im Einsatz)
        [
            'slug' => 'bausteine', 'title' => 'Bausteine', 'menu' => true, 'parent' => 'fluid',
            'blocks' => [
                ['type' => 'hero', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'compact', 'eyebrow' => 'Kit fluid',
                    'title' => 'Bausteine für jede Seite', 'text' => 'Jeder Abschnitt ist ein Block mit Feldschema – und ein echter REDAXO-Slice.']],
                ['type' => 'logos', 'data' => ['variant' => 'marquee', 'eyebrow' => '', 'title' => 'Gebaut auf bewährten Bausteinen', 'items' => [
                    ['name' => 'REDAXO', 'link' => '', 'image' => null], ['name' => 'PHP 8.5', 'link' => '', 'image' => null],
                    ['name' => 'Composer', 'link' => '', 'image' => null], ['name' => 'Symfony', 'link' => '', 'image' => null],
                    ['name' => 'Intervention Image', 'link' => '', 'image' => null], ['name' => 'Friends Of REDAXO', 'link' => '', 'image' => null],
                ]]],
                ['type' => 'bento', 'data' => ['eyebrow' => 'Auf einen Blick', 'title' => 'Was fluid mitbringt', 'intro' => '', 'items' => [
                    ['size' => 'big', 'tone' => 'dark', 'icon' => 'sparkle', 'image' => null, 'eyebrow' => 'Bearbeiten im Kontext', 'title' => 'Texte, Bilder und Design direkt auf der Seite ändern.', 'text' => 'Mit Live-Vorschau, Arbeitsversion und Veröffentlichen.', 'link' => '/bearbeiten'],
                    ['size' => 's', 'tone' => 'highlight', 'icon' => '', 'image' => null, 'eyebrow' => '18', 'title' => 'Design-Vorlagen', 'text' => 'Von der Kanzlei bis zum Startup.', 'link' => ''],
                    ['size' => 's', 'tone' => 'card', 'icon' => 'squares-four', 'image' => null, 'eyebrow' => '', 'title' => 'Layout mit Spalten', 'text' => 'Spalten-Blöcke sind echte Slices.', 'link' => ''],
                    ['size' => 'wide', 'tone' => 'accent', 'icon' => 'palette', 'image' => null, 'eyebrow' => '', 'title' => 'Style-Editor live auf der Seite', 'text' => 'Farben, Schriften, Formen – mit Kontrastprüfung.', 'link' => ''],
                    ['size' => 's', 'tone' => 'tint', 'icon' => 'pencil-simple', 'image' => null, 'eyebrow' => '', 'title' => 'Bilder bearbeiten', 'text' => 'Zuschneiden, Drehen, Entzerren – das Original bleibt.', 'link' => ''],
                    ['size' => 's', 'tone' => 'card', 'icon' => 'package', 'image' => null, 'eyebrow' => '', 'title' => 'Composer-Pakete', 'text' => 'fluid und image_editor als AddOns.', 'link' => ''],
                ]]],
                ['type' => 'tabs', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'top', 'eyebrow' => 'Für wen', 'title' => 'Ein System, drei Blickwinkel', 'intro' => '', 'items' => [
                    ['label' => 'Redaktion', 'text' => '<p>Sehen, was man ändert: Text direkt auf der Seite, alle Felder in der Seitenleiste, Bilder aus dem Medienpool, Links über die Linkmap. Änderungen landen im Entwurf – erst „Veröffentlichen“ bringt sie live.</p>', 'image' => 'fluid-bearbeiten-datensatz.jpg'],
                    ['label' => 'Entwicklung', 'text' => '<p>Ein Block ist eine kleine Klasse: Felder ins Kit, Ausgabe als View-Datei. Formular, Speichern und Bearbeiten auf der Website kommen von fluid. Daten liegen nativ in der Spalte data des Slices – keine eigenen Tabellen.</p>', 'image' => 'fluid-yform-maske.jpg'],
                    ['label' => 'Agentur', 'text' => '<p>Kits mit Design-System und Vorlagen: ein Projekt schnell auf das Erscheinungsbild der Kundschaft bringen – und die Redaktion pflegt danach selbst.</p>', 'image' => 'fluid-website.jpg'],
                ]]],
                ['type' => 'pricing', 'data' => ['variant' => 'cards', 'eyebrow' => 'Mitmachen', 'title' => 'Drei Wege zu fluid', 'intro' => 'Open Source unter MIT-Lizenz – Beispielwege, keine Preisliste.', 'items' => [
                    ['name' => 'Ausprobieren', 'badge' => '', 'price' => '0 €', 'period' => 'MIT-Lizenz', 'text' => 'Lokal mit REDAXO 6 installieren und die Demo durchklicken.', 'features' => "REDAXO 6 (Branch 6.x)\nfluid:demo legt alles an\nKeine Anmeldung nötig", 'highlight' => false, 'button_label' => 'Technik', 'button_link' => '/technik'],
                    ['name' => 'Mitmachen', 'badge' => 'Empfohlen', 'price' => '0 €', 'period' => 'Friends Of REDAXO', 'text' => 'Ideen, Fehlerberichte und Blöcke beisteuern.', 'features' => "Issues und Pull Requests\nEigene Kits und Blöcke\nÜbersetzungen", 'highlight' => true, 'button_label' => 'Was sich ändert', 'button_link' => '/was-sich-aendert'],
                    ['name' => 'Einsetzen', 'badge' => '', 'price' => '0 €', 'period' => 'sobald stabil', 'text' => 'In Projekten nutzen, wenn REDAXO 6 erscheint.', 'features' => "Composer-Paket\nArbeitsversion und Historie\nRechte wie gewohnt", 'highlight' => false, 'button_label' => 'Bearbeiten', 'button_link' => '/bearbeiten'],
                ], 'rows' => [], 'note' => '']],
            ],
        ],

        [
            'slug' => 'fluid-projekt', 'title' => 'fluid-Projekt', 'menu' => true, 'parent' => 'fluid',
            'blocks' => [
                ['type' => 'hero', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'compact', 'eyebrow' => 'fluid',
                    'title' => 'Ein Projekt mit fluid', 'text' => 'Bearbeiten direkt auf der Website: fluid einbinden, Demo anlegen, eigene Blöcke bauen.']],
                ['type' => 'richtext', 'data' => ['variant' => 'standard', 'eyebrow' => '', 'title' => '', 'intro' => '', 'meta' => '',
                    'measure' => 'normal', 'dropcap' => false, 'toc' => false, 'reveal' => 'off', 'scrollspy' => 'off',
                    'text' => '<p class="t-warn"><strong>In Entwicklung:</strong> REDAXO 6 entsteht im Branch 6.x von redaxo/core. Die Beispiele entsprechen dem Stand vom November 2026 – Namen und Details können sich bis zur Veröffentlichung noch ändern.</p><p>fluid ist ein AddOn für REDAXO 6 und noch nicht auf Packagist. Composer holt es – wie YForm 6 – direkt von GitHub. YForm ist optional; ohne fehlen nur Formulare, Datenlisten und das Postfach.</p>']],
                ['type' => 'code', 'data' => ['eyebrow' => '', 'title' => 'fluid einbinden', 'intro' => '', 'note' => '',
                    'items' => [
                        $c('frequire', 'Terminal', 'bash'),
                    ]]],
                ['type' => 'code', 'data' => ['eyebrow' => '', 'title' => 'Mit der Demo starten', 'intro' => 'Alternativ auf der Website „Erste Schritte“ nutzen.', 'note' => '',
                    'items' => [
                        $c('fdemo', 'Terminal', 'bash'),
                    ]]],
                ['type' => 'code', 'data' => ['eyebrow' => '', 'title' => 'Ein eigener Block', 'intro' => 'Felder ins Schema, Ausgabe als View-Datei daneben. Formular im Backend, Bearbeiten auf der Website und Vorschau kommen von fluid.', 'note' => 'Mit $b->edit() wird ein Feld direkt auf der Website bearbeitbar.',
                    'items' => [
                        $c('fblock', 'src/Block/Teaser.php', 'php'),
                        $c('fview', 'src/Block/Teaser.view.php', 'php'),
                    ]]],
                $cta,
            ],
        ],

        [
            'slug' => 'eigenes-kit', 'title' => 'Eigenes Kit', 'menu' => true, 'parent' => 'fluid',
            'blocks' => [
                ['type' => 'hero', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'compact', 'eyebrow' => 'fluid',
                    'title' => 'Ein eigenes Kit bauen', 'text' => 'Was ein Kit ist, wann man eins braucht – und wie es Schritt für Schritt entsteht.']],
                ['type' => 'richtext', 'data' => ['variant' => 'standard', 'eyebrow' => '', 'title' => 'Was ist ein Kit?', 'intro' => '', 'meta' => '',
                    'measure' => 'normal', 'dropcap' => false, 'toc' => false, 'reveal' => 'off', 'scrollspy' => 'off',
                    'text' => '<p class="t-warn"><strong>In Entwicklung:</strong> REDAXO 6 entsteht im Branch 6.x von redaxo/core. Die Beispiele entsprechen dem Stand vom November 2026 – Namen und Details können sich bis zur Veröffentlichung noch ändern.</p><p>Ein <strong>Kit</strong> bestimmt, wie die ganze Website aussieht: den Seitenrahmen mit Kopf, Menü und Fuß, das CSS, die Ausgabe jedes Blocks (Views) und die Möglichkeiten der Design-Leiste. Es legt auch fest, welche Darstellungen und Hintergründe ein Block anbietet.</p><p>Die <strong>Inhalte</strong> gehören zu keinem Kit. Ein Block „Handlungsaufruf“ bleibt ein Slice mit denselben Feldern – das Kit entscheidet nur, wie er aussieht. Darum lässt sich das Kit unter <em>Website → Kit</em> wechseln, ohne eine Seite anzufassen.</p><p>Jedes Kit <strong>erbt</strong> von einem anderen (Standard: <code>fluid</code>) und bringt nur mit, was es ändert. Alle Blöcke sind also immer da, und Updates von fluid kommen beim eigenen Kit mit an. Das mitgelieferte Kit „NativeCSS“ ist so gebaut: eigener Seitenrahmen und eigene Views für einige Blöcke, alles andere von <code>fluid</code>.</p>']],
                ['type' => 'features', 'tunes' => ['background' => 'muted'], 'data' => [
                    'variant' => 'icons', 'eyebrow' => 'Abwägen', 'title' => 'Braucht es ein Kit?', 'intro' => 'Drei Wege, die Website zu gestalten – vom einfachsten zum gründlichsten.', 'size' => 'm',
                    'items' => [
                        ['icon' => 'palette', 'title' => 'Design-Leiste', 'text' => 'Farben, Schriften, Formen, Kopf und Fuß – direkt auf der Seite, ohne Code. Reicht für die meisten Websites.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'squares-four', 'title' => 'Eigener Block', 'text' => 'Ein neuer Inhaltstyp mit eigenen Feldern – im Projekt, ohne das Kit zu ändern.', 'link_label' => 'So geht es', 'link' => '/fluid-projekt'],
                        ['icon' => 'code', 'title' => 'Eigenes Kit', 'text' => 'Anderes Markup und CSS für die ganze Website, eigene Darstellungen und Hintergründe – vererbt von fluid.', 'link_label' => '', 'link' => ''],
                    ],
                ]],
                ['type' => 'code', 'data' => ['eyebrow' => '', 'title' => '1. Kit anlegen', 'intro' => 'Der Befehl legt das Kit im Projekt an. Mit --view kopiert er Views des Eltern-Kits zum Anpassen, mit --template den Seitenrahmen.', 'note' => '',
                    'items' => [
                        $c('kitCreate', 'Terminal', 'bash'),
                    ]]],
                ['type' => 'code', 'data' => ['eyebrow' => '', 'title' => '2. Die Kit-Klasse', 'intro' => 'Das Attribut #[AsKit] meldet das Kit an – Schlüssel, Name und Eltern-Kit. Die Klasse selbst bleibt leer.', 'note' => '',
                    'items' => [
                        $c('kitClass', 'src/Kit/Nature/NatureKit.php', 'php'),
                    ]]],
                ['type' => 'code', 'data' => ['eyebrow' => '', 'title' => '3. Die Definition: nur, was anders ist', 'intro' => '$parent ist die Definition des Eltern-Kits. blocks, backgrounds und assets werden je Schlüssel ergänzt, css wird angehängt, alles andere ersetzt.', 'note' => '',
                    'items' => [
                        $c('kitDef', 'src/Kit/Nature/kit.php', 'php'),
                    ]]],
                ['type' => 'code', 'data' => ['eyebrow' => '', 'title' => '4. Eigenes CSS', 'intro' => 'Geladen nach dem CSS von fluid. Die Variablen der Design-Leiste (--f-…) gelten weiter – darauf aufbauen statt Farben fest einzutragen.', 'note' => '',
                    'items' => [
                        $c('kitCss', 'src/Kit/Nature/assets/css/kit.css', 'css'),
                    ]]],
                ['type' => 'code', 'data' => ['eyebrow' => '', 'title' => '5. Eine View ersetzen', 'intro' => 'Eine Datei views/{typ}.php ersetzt die Ausgabe dieses Blocks. $b ist der Block, $d seine Daten – wie beim eigenen Block.', 'note' => '',
                    'items' => [
                        $c('kitView', 'src/Kit/Nature/views/cta.php', 'php'),
                    ]]],
                ['type' => 'media_text', 'data' => ['variant' => 'left', 'eyebrow' => 'Ergebnis', 'title' => 'Der Handlungsaufruf im Kit „Nature“',
                    'text' => '<p>Hintergrund „Sand“ und Darstellung „Mit Blatt“ stehen jetzt in der Seitenleiste zur Auswahl. Alle anderen Blöcke, die Design-Leiste und der Seitenrahmen kommen unverändert von fluid.</p>',
                    'list' => "Ausgewählt unter Website → Kit\nInhalte bleiben beim Wechsel\nDesign-Werte speichert jedes Kit für sich", 'image' => 'redaxo6-kit-nature.jpg', 'ratio' => '16:9', 'split' => 'media']],
                ['type' => 'richtext', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'standard', 'eyebrow' => '', 'title' => 'Was ein Kit ersetzen kann', 'intro' => '', 'meta' => '',
                    'measure' => 'normal', 'dropcap' => false, 'toc' => false, 'reveal' => 'off', 'scrollspy' => 'off',
                    'text' => '<ul><li><strong><code>views/{typ}.php</code></strong> – die Ausgabe eines Blocktyps</li><li><strong><code>template.view.php</code></strong> – der Seitenrahmen mit Kopf, Menü und Fuß</li><li><strong><code>assets/…</code></strong> – einzelne Dateien, z. B. <code>assets/css/b-cards.css</code> statt der von fluid</li><li><strong><code>functions.php</code></strong> – zusätzliche Hilfsfunktionen für Views</li><li><strong><code>kit.php</code></strong> – die Definition: Blöcke, Darstellungen, Hintergründe, Design-Leiste</li></ul><p><strong>Design-Werte</strong> speichert jedes Kit für sich; solange ein neues Kit keine eigenen hat, übernimmt es die des Eltern-Kits. <strong>Assets</strong> veröffentlicht fluid bei <code>console migrate</code> – auf dem Server also den Kit-Ordner mit ausliefern und <code>migrate</code> ausführen. Ein Kit je Domain wählt der Extension Point <code>FLUID_KIT</code>.</p>']],
                ['type' => 'code', 'data' => ['eyebrow' => '', 'title' => 'Als Composer-Paket für mehrere Projekte', 'intro' => 'Ein Kit für mehrere Projekte ist ein AddOn: REDAXO sucht Klassen mit #[AsKit] nur im Projekt und in aktiven AddOns. Die Dateien des Kits liegen neben der Kit-Klasse in src/.', 'note' => 'Nach composer require und console migrate steht das Kit in der Auswahl.',
                    'items' => [
                        $c('kitPackage', 'composer.json', 'json'),
                        $c('kitAddon', 'src/NatureAddon.php', 'php'),
                    ]]],
                $cta,
            ],
        ],

        // ------------------------------------------------------------------ Suche (nicht im Menü – Lupe im Kopf)
        [
            'slug' => 'suche', 'title' => 'Suche', 'menu' => false,
            'blocks' => [
                ['type' => 'search', 'data' => ['eyebrow' => '', 'title' => 'Suche', 'intro' => 'Durchsucht alle veröffentlichten Seiten – auch mit Tippfehlern.', 'placeholder' => '', 'limit' => 20, 'empty_text' => '']],
            ],
        ],

        // ------------------------------------------------------------------ Daten (YForm)
        [
            'slug' => 'daten', 'title' => 'Daten', 'menu' => true, 'requires' => 'yform',
            'blocks' => [
                ['type' => 'hero', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'compact', 'eyebrow' => 'fluid + YForm',
                    'title' => 'Daten und Formulare aus *YForm*', 'text' => 'Zwei Blöcke verbinden fluid mit den Tabellen von YForm: eine Datenliste mit Detailansicht und ein Formular, das Einträge speichert. Felder und Prüfungen kommen aus dem Table Manager.']],
                ['type' => 'datalist', 'data' => ['variant' => 'cards', 'eyebrow' => 'Datenliste', 'title' => 'Termine', 'intro' => 'Beispieldaten aus der YForm-Tabelle rex_yf_termin – nur Einträge mit „Online“, nach Datum sortiert.',
                    'table' => 'rex_yf_termin', 'title_field' => 'rex_yf_termin.title', 'image_field' => 'rex_yf_termin.image', 'text_field' => 'rex_yf_termin.text', 'meta_field' => 'rex_yf_termin.date',
                    'filter_field' => 'rex_yf_termin.online', 'filter_value' => '1', 'sort_field' => 'rex_yf_termin.date', 'sort_dir' => 'asc', 'limit' => 0, 'size' => 'm', 'detail' => true, 'empty_text' => '']],
                ['type' => 'form', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'split', 'eyebrow' => 'Formular', 'title' => 'Schreiben Sie uns',
                    'intro' => 'Das Formular ist eine Klasse im Projekt (#[AsForm]): Felder, Prüfungen und Aktionen – speichern, Mail, Bestätigung – legt YForm fest. Einträge landen in „Anfragen“.',
                    'form' => 'anfrage',
                    'note' => 'Ihre Angaben verwenden wir nur zur Beantwortung.']],
            ],
        ],

        // ------------------------------------------------------------------ Presse: Kurzmeldungen aus YForm (Datenliste, Blättern, Detailseiten)
        [
            'slug' => 'presse', 'title' => 'Presse', 'menu' => true, 'requires' => 'yform',
            'blocks' => [
                ['type' => 'datalist', 'data' => ['variant' => 'list', 'eyebrow' => '', 'title' => '', 'intro' => '',
                    'table' => 'rex_yf_news', 'title_field' => 'rex_yf_news.title', 'image_field' => 'rex_yf_news.image', 'text_field' => 'rex_yf_news.teaser', 'meta_field' => 'rex_yf_news.date',
                    'filter_field' => 'rex_yf_news.online', 'filter_value' => '1', 'sort_field' => 'rex_yf_news.date', 'sort_dir' => 'desc', 'limit' => 3, 'pager' => true, 'size' => 'm', 'detail' => true, 'empty_text' => '']],
            ],
        ],

        // ------------------------------------------------------------------ Projekte: Fallstudien aus Blöcken im YForm-Datensatz (Feld „Fluid-Blöcke“)
        [
            'slug' => 'projekte', 'title' => 'Projekte', 'menu' => true, 'requires' => 'yform',
            'blocks' => [
                ['type' => 'hero', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'compact', 'eyebrow' => 'Referenzen',
                    'title' => 'Projekte, die *für sich sprechen*', 'text' => 'Jedes Projekt ist ein YForm-Eintrag – die Fallstudie darin besteht aus Blöcken und lässt sich direkt auf der Website gestalten.']],
                ['type' => 'datalist', 'data' => ['variant' => 'cards', 'eyebrow' => '', 'title' => '', 'intro' => '',
                    'table' => 'rex_yf_project', 'title_field' => 'rex_yf_project.title', 'image_field' => 'rex_yf_project.image', 'text_field' => 'rex_yf_project.teaser', 'meta_field' => 'rex_yf_project.client',
                    'filter_field' => 'rex_yf_project.online', 'filter_value' => '1', 'sort_field' => 'rex_yf_project.year', 'sort_dir' => 'desc', 'limit' => 0, 'size' => 'm', 'detail' => true, 'empty_text' => '']],
            ],
        ],

        // ------------------------------------------------------------------ Anmeldung: eigenes Formular mit drei Schritten (BuiltForm, YForm)
        [
            'slug' => 'anmeldung', 'title' => 'Anmeldung', 'menu' => false, 'requires' => 'yform',
            'blocks' => [
                ['type' => 'form', 'data' => ['variant' => 'split', 'eyebrow' => 'Formular-Baukasten', 'title' => 'Zum Workshop anmelden',
                    'intro' => 'Ein Formular, auf der Website zusammengestellt: drei Schritte, gespeichert in „Formular-Eingänge“, per E-Mail an das Team und mit Bestätigung an Sie.',
                    'form' => '', 'mode' => 'save_mail', 'mail_to' => 'team@example.test', 'mail_subject' => 'Anmeldung von {name}',
                    'confirm' => true, 'confirm_copy' => true, 'confirm_subject' => 'Ihre Anmeldung ist angekommen', 'confirm_text' => 'Hallo {name}, danke für Ihre Anmeldung zum Workshop „{workshop}“.',
                    'submit' => 'Verbindlich anmelden', 'form_name' => 'Workshop-Anmeldung', 'success' => 'Danke! Ihre Anmeldung ist angekommen – die Bestätigung kommt per E-Mail.',
                    'first_step' => 'Kontakt', 'note' => 'Beispiel – Ihre Angaben dienen nur der Anmeldung.',
                    'fields' => [
                        ['type' => 'text', 'label' => 'Name', 'required' => true, 'name' => 'name'],
                        ['type' => 'email', 'label' => 'E-Mail', 'required' => true],
                        ['type' => 'tel', 'label' => 'Telefon', 'help' => 'Für Rückfragen, optional'],
                        ['type' => 'step', 'label' => 'Teilnahme'],
                        ['type' => 'radio', 'label' => 'Workshop', 'name' => 'workshop', 'required' => true, 'options' => "Eigene Blöcke bauen\nVon REDAXO 5 nach 6\nBearbeiten auf der Website"],
                        ['type' => 'checkboxes', 'label' => 'Interessen', 'options' => "Design\nYForm\nMehrsprachigkeit"],
                        ['type' => 'date', 'label' => 'Wunschtermin'],
                        ['type' => 'step', 'label' => 'Abschluss'],
                        ['type' => 'textarea', 'label' => 'Ihre Frage vorab'],
                        ['type' => 'checkbox', 'label' => 'Ich bin mit der Verarbeitung meiner Angaben zur Anmeldung einverstanden.', 'name' => 'datenschutz', 'required' => true],
                    ]]],
            ],
        ],

        // ------------------------------------------------------------------ Blog: Menüseite mit „Blog / News“, Beiträge = Seiten darin
        [
            'slug' => 'neuigkeiten', 'title' => 'Neuigkeiten', 'menu' => true, 'blog' => true,
            'blocks' => [
                ['type' => 'posts', 'data' => ['variant' => 'featured', 'eyebrow' => '', 'title' => '', 'intro' => '', 'blog' => '', 'limit' => 6, 'pager' => true, 'tags' => true, 'size' => 'm', 'more' => '', 'empty_text' => '']],
            ],
        ],

        [
            'slug' => 'fluid-ist-da', 'title' => 'fluid für REDAXO 6', 'parent' => 'neuigkeiten',
            'post' => ['date' => '2026-10-12', 'author' => 'Friends Of REDAXO', 'tags' => 'REDAXO 6, Release', 'excerpt' => 'Bearbeiten direkt auf der Website – mit Blöcken als echten Slices, Arbeitsversion und Rechten wie im Backend.'],
            'blocks' => [
                ['type' => 'richtext', 'data' => ['variant' => 'article', 'eyebrow' => '', 'title' => '', 'intro' => '', 'measure' => 'normal',
                    'text' => '<p>fluid bringt das Bearbeiten auf der Website nach REDAXO 6. Jeder Abschnitt ist ein Block mit Feldschema – und ein echter Slice.</p><h2>Was neu ist</h2><ul><li>Texte direkt auf der Seite ändern</li><li>Entwurf speichern, dann veröffentlichen</li><li>Seiten, Sprachen und Domains im Seitenbaum und unter „Website“</li></ul><p>Dieser Beitrag ist selbst ein Beispiel: eine normale Seite im Blog „Neuigkeiten“.</p>']],
            ],
        ],

        [
            'slug' => 'blog-mit-fluid', 'title' => 'So funktioniert der Blog', 'parent' => 'neuigkeiten',
            'post' => ['date' => '2026-10-05', 'author' => 'Redaktion', 'tags' => 'Anleitung', 'excerpt' => 'Eine Menüseite wird zum Blog, ihre Seiten zu Beiträgen – mit Datum, Autor und Schlagworten.'],
            'blocks' => [
                ['type' => 'richtext', 'data' => ['variant' => 'article', 'eyebrow' => '', 'title' => '', 'intro' => '', 'measure' => 'normal',
                    'text' => '<h2>In drei Schritten</h2><ol><li>Bei einer Menüseite in den Seiteneinstellungen „Blog / News“ anhaken.</li><li>Dort den Block „Beiträge“ einfügen.</li><li>„+ Neuer Beitrag“ – Titel eingeben, schreiben, veröffentlichen.</li></ol><p>Datum, Autor und Schlagworte stehen in den Seiteneinstellungen des Beitrags. Teaserbild und Kurztext sind das Bild für soziale Netzwerke und die Beschreibung für Suchmaschinen.</p>']],
            ],
        ],

        [
            'slug' => 'sommerfest', 'title' => 'Rückblick: Sommerfest', 'parent' => 'neuigkeiten',
            'post' => ['date' => '2026-07-04', 'author' => 'Redaktion', 'tags' => 'Veranstaltung', 'excerpt' => 'Grill, Musik und viele Gäste – ein Beispielbeitrag mit Schlagwort „Veranstaltung“.'],
            'blocks' => [
                ['type' => 'richtext', 'data' => ['variant' => 'standard', 'eyebrow' => '', 'title' => '', 'intro' => '', 'measure' => 'normal',
                    'text' => '<p>Ein Beispielbeitrag, damit Übersicht, Schlagworte und „Älterer/Neuerer Beitrag“ etwas zu zeigen haben.</p>']],
            ],
        ],
    ],
];

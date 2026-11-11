<?php

namespace FriendsOfRedaxo\Fluid\Console;

use FriendsOfRedaxo\Fluid\BlockModule;
use FriendsOfRedaxo\Fluid\Data\YFormBridge;
use FriendsOfRedaxo\Fluid\FluidAddon;
use FriendsOfRedaxo\Fluid\Kit;
use Redaxo\Core\Cache;
use Redaxo\Core\Console\AbstractCommand;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\Content\ArticleHandler;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Content\CategoryHandler;
use Redaxo\Core\Content\ContentHandler;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Language\Language;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

use function is_array;
use function is_string;

/**
 * Legt die Demo-Seiten eines Kits an (Startartikel + Kategorien fürs Menü + Blöcke).
 * Blocktypen, die das Kit (noch) nicht anbietet, werden übersprungen.
 */
#[AsCommand(name: 'fluid:demo', description: 'Legt Demo-Seiten des Kits „fluid“ an (--set=fluid|redaxo6, --replace ersetzt alle Inhalte – nur Testinstallationen)')]
final class DemoCommand extends AbstractCommand
{
    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Auch anlegen, wenn schon Artikel existieren')] bool $force = false,
        #[Option(description: 'Alle Kategorien, Artikel und Slices vorher löschen (nur Testinstallationen!)')] bool $replace = false,
        #[Option(description: 'Inhalte: fluid (Beispiel-Agentur) oder redaxo6 (Seite über REDAXO 6)')] string $set = 'fluid',
        #[Option(description: 'Design des Kits auf den Standard zurücksetzen')] bool $resetDesign = false,
        #[Option(description: 'Nur diese Demo-Seiten (Slugs, mit Komma getrennt) anhängen, z. B. daten oder redaxo-6,projekt – vorhandene Inhalte bleiben')] string $page = '',
        #[Option(description: 'Nur Beispielzeilen in die YForm-Tabellen der Demo schreiben (Tabellen sind Klassen im Projekt)')] bool $yform = false,
        #[Option(description: 'Demo-Bilder des Kits in den Medienpool übernehmen (vorhandene bleiben)')] bool $media = false,
        #[Option(name: 'media-dir', description: 'Weitere Dateien aus diesem Ordner in den Medienpool übernehmen (vorhandene bleiben), z. B. einen Trailer, der nicht im Kit liegt')] string $mediaDir = '',
        #[Option(description: 'Design-Vorlage setzen, z. B. redaxo (mit REDAXO-Logo aus dem Core), kanzlei, handwerk …; auf einer Website mit Seiten nur die Vorlage')] string $design = '',
        #[Option(name: 'kit', description: 'Kit, für das --design gilt (Standard: das gewählte), z. B. native')] string $designKit = '',
        #[Option(description: 'Mit --page: vorhandene Seiten neu befüllen (ihre Blöcke in der Startsprache werden ersetzt, Seite und Adresse bleiben)')] bool $update = false,
        #[Option(name: 'lang', description: 'Übersetzung der vorhandenen Demo-Seiten anlegen (Sprachcode, z. B. en; Texte aus demo-{set}.{code}.php)')] string $translate = '',
    ): int {
        if ($media) {
            $io->note(self::media());
        }
        if ('' !== $mediaDir) {
            if (!is_dir($mediaDir)) {
                $io->error('Ordner nicht gefunden: ' . $mediaDir);

                return Command::FAILURE;
            }
            $io->note(self::media($mediaDir));
        }
        if ($yform) {
            $demo = require self::demoFile($set);
            if (!isset($demo['yform']) || !YFormBridge::available()) {
                $io->error('Keine YForm-Tabellen in dieser Demo oder YForm fehlt.');

                return Command::FAILURE;
            }
            $io->success(self::yform($demo['yform']));

            return Command::SUCCESS;
        }
        if ($resetDesign) {
            FluidAddon::instance()->removeConfig('design.' . Kit::active()->key());
            $io->note('Design zurückgesetzt.');
        }
        $sql = Sql::factory();
        if ('' !== $page || '' !== $translate) {
            $force = true;
            $replace = false;
        }
        if ($replace) {
            // Struktur hat Fremdschlüssel (article ↔ category): zum Leeren der Testinstallation kurz aussetzen
            $sql->setQuery('SET FOREIGN_KEY_CHECKS = 0');
            foreach (['rex_article_slice_history', 'rex_article_slice', 'rex_article_translation', 'rex_category_translation', 'rex_article', 'rex_category'] as $table) {
                $sql->setQuery('DELETE FROM ' . $table);
            }
            $sql->setQuery('SET FOREIGN_KEY_CHECKS = 1');
            Cache::delete();
            $io->note('Alle Inhalte gelöscht.');
        } elseif (!$force && $sql->getArray('SELECT id FROM rex_article LIMIT 1')) {
            // Website mit Seiten: nur die Design-Vorlage setzen
            if ('' !== $design) {
                $io->note(self::design($design, $designKit));

                return Command::SUCCESS;
            }
            // nur Medien übernommen (--media, --media-dir): fertig, die Seiten bleiben
            if ($media || '' !== $mediaDir) {
                return Command::SUCCESS;
            }
            $io->error('Es gibt schon Artikel. Mit --force trotzdem anlegen, mit --replace vorher alles löschen.');

            return Command::FAILURE;
        }

        // Bearbeiten auf der Website arbeitet in der Arbeitsversion (Entwurf → Veröffentlichen)
        Core::setConfig('article_work_version', true);

        $kit = Kit::active();
        $kit->definition();
        /** @var array{pages: list<array<string, mixed>>} $demo */
        $demo = require self::demoFile($set);
        $lang = Language::getStartId();
        $ids = [];
        $kept = [];   // vorhandene Seiten (nur verschoben): keine Blöcke anlegen
        // Seiten, die ein AddOn brauchen (z. B. YForm), nur anlegen, wenn es da ist
        $demo['pages'] = array_values(array_filter($demo['pages'], static fn (array $p) => 'yform' !== ($p['requires'] ?? null) || YFormBridge::available()));
        // Alle Seiten der Demo (für Seiten und Links, die schon existieren: über den Titel gefunden)
        $all = $demo['pages'];
        if ('' !== $page) {
            $slugs = array_map('trim', explode(',', $page));
            $demo['pages'] = array_values(array_filter($demo['pages'], static fn (array $p) => in_array($p['slug'], $slugs, true)));
            if (count($demo['pages']) !== count($slugs)) {
                $io->error('Nicht alle Demo-Seiten gefunden: ' . $page . ' (oder benötigtes AddOn fehlt).');

                return Command::FAILURE;
            }
        }
        if (isset($demo['yform']) && YFormBridge::available()) {
            $io->note(self::yform($demo['yform']));
        }

        // Seite einer früheren Ausführung (z. B. Elternseite beim Anhängen mit --page): über ihren Titel
        $existing = static function (string $slug) use ($all, $lang): ?int {
            $title = array_find($all, static fn (array $p) => $p['slug'] === $slug)['title'] ?? null;
            if (null === $title) {
                return null;
            }
            $row = Sql::factory()->getArray('SELECT article_id FROM rex_article_translation WHERE name = ? AND language_id = ? ORDER BY article_id LIMIT 1', [$title, $lang]);

            return isset($row[0]) ? (int) $row[0]['article_id'] : null;
        };
        if ('' !== $translate) {
            return self::translate($io, $set, $translate, $demo['pages'], $all, $existing, $kit, (array) ($demo['yform'] ?? []));
        }

        // 1. Seiten: Menüpunkte als Kategorien, alle übrigen als Artikel auf oberster Ebene
        $prio = 1 + (int) ($sql->getArray('SELECT MAX(priority) AS p FROM rex_category')[0]['p'] ?? 0);
        foreach ($demo['pages'] as $def) {
            $slug = (string) $def['slug'];
            // Startseite und Seiten außerhalb des Menüs (Impressum, Datenschutz): Artikel auf oberster Ebene;
            // 'parent' => 'slug': Seite in einer Menüseite (z. B. Beiträge eines Blogs, Angaben unter 'post')
            if (!empty($def['is_home']) || empty($def['menu'])) {
                // mit --page: vorhandene Seite weiterverwenden (Startseite = Startartikel der Website) statt eine zweite anzulegen
                $old = '' !== $page ? (!empty($def['is_home']) ? (int) Core::getConfig('start_article_id', 0) : (int) $existing($slug)) : 0;
                if ($old > 0 && null !== \Redaxo\Core\Content\Article::get($old, $lang)) {
                    $ids[$slug] = $old;
                    if ($update) {
                        Sql::factory()->setQuery('DELETE FROM rex_article_slice WHERE article_id = ? AND language_id = ?', [$old, $lang]);
                        $io->note('„' . $def['title'] . '“ wird neu befüllt.');
                    } else {
                        $kept[$slug] = true;
                    }
                    continue;
                }
                $parent = isset($def['parent']) ? ($ids[(string) $def['parent']] ?? $existing((string) $def['parent'])) : null;
                // Template „fluid“ (FluidTemplate) – gestaltet vom gewählten Kit
                $ids[$slug] = ArticleHandler::add(['category_id' => $parent, 'priority' => !empty($def['is_home']) ? 1 : 99, 'name' => (string) $def['title'], 'template' => 'fluid'])->id;
                ArticleHandler::changeStatus($ids[$slug], $lang, 1);
                if (isset($def['post']) && ($art = \Redaxo\Core\Content\Article::get($ids[$slug], $lang))) {
                    $post = (array) $def['post'];
                    \FriendsOfRedaxo\Fluid\PageSettings::save($art, $art->name, true, (string) ($post['excerpt'] ?? ''), (string) ($post['image'] ?? ''), false);
                    \FriendsOfRedaxo\Fluid\Blog\Blog::saveMeta($art, (string) ($post['date'] ?? date('Y-m-d')), (string) ($post['author'] ?? ''), (string) ($post['tags'] ?? ''));
                }
                if (!empty($def['is_home'])) {
                    Core::setConfig('start_article_id', $ids[$slug]);
                    Core::setConfig('notfound_article_id', $ids[$slug]);
                }
                continue;
            }
            // 'parent' => 'slug' bei einer Menüseite: Unterseite im Menü (Kategorie in der Kategorie)
            $parentCat = isset($def['parent']) ? ($ids[(string) $def['parent']] ?? $existing((string) $def['parent'])) : null;
            // Menüseite gibt es nur noch als Seite ohne Menüeintrag (früher „aus dem Menü genommen“): wieder zum Menüpunkt
            // machen, statt eine zweite anzulegen – Inhalte und Adresse bleiben
            if ('' !== $page && null !== ($old = $existing($slug)) && null === \Redaxo\Core\Content\Category::get($old)) {
                ArticleHandler::convertToCategory($old);
                \Redaxo\Core\Content\Category::clearInstancePool();
                \Redaxo\Core\Content\Category::clearInstanceListPool();
                \Redaxo\Core\Content\Article::clearInstancePool();
                \Redaxo\Core\Content\Article::clearInstanceListPool();
                $io->note('„' . $def['title'] . '“ steht wieder im Menü.');
            }
            // Seite gibt es schon (z. B. aus einem früheren Lauf auf oberster Ebene): mit --page an die neue Stelle verschieben,
            // Inhalte bleiben – mit --update werden die Blöcke ersetzt
            if ('' !== $page && null !== ($old = $existing($slug)) && null !== ($oldCat = \Redaxo\Core\Content\Category::get($old))) {
                if (null !== $parentCat && $oldCat->parentId !== $parentCat) {
                    CategoryHandler::move($old, $parentCat);
                    $io->note('„' . $def['title'] . '“ verschoben (vorhandene Inhalte bleiben).');
                }
                $ids[$slug] = $old;
                if ($update) {
                    Sql::factory()->setQuery('DELETE FROM rex_article_slice WHERE article_id = ? AND language_id = ?', [$old, $lang]);
                    $io->note('„' . $def['title'] . '“ wird neu befüllt.');
                } else {
                    $kept[$slug] = true;
                }
                continue;
            }
            $id = CategoryHandler::add(['parent_id' => $parentCat, 'priority' => null !== $parentCat ? 99 : $prio++, 'name' => (string) $def['title'], 'status' => 1])->id;
            $sql->setQuery('UPDATE rex_article_translation SET template = ?, status = 1 WHERE article_id = ?', ['fluid', $id]);
            $ids[$slug] = $id;
            if (!empty($def['blog'])) {
                \FriendsOfRedaxo\Fluid\Blog\Blog::setBlog($id, true);
            }
        }

        // Unterseiten im Menü in der Reihenfolge der Demo (mit --page verschobene oder neue Seiten landen sonst am Ende)
        if ('' !== $page) {
            $parents = array_unique(array_filter(array_map(static fn (array $p): ?string => !empty($p['menu']) ? ($p['parent'] ?? null) : null, $demo['pages'])));
            foreach ($parents as $parentSlug) {
                $priority = 1;
                foreach ($all as $p) {
                    if (empty($p['menu']) || ($p['parent'] ?? null) !== $parentSlug) {
                        continue;
                    }
                    $cid = $ids[(string) $p['slug']] ?? $existing((string) $p['slug']);
                    if (null !== $cid && \Redaxo\Core\Content\Category::get($cid)) {
                        CategoryHandler::edit($cid, $lang, ['priority' => $priority++]);
                    }
                }
            }
        }

        // 2. Blöcke – Links auf Demo-Seiten (/kontakt) werden zu redaxo://ID
        $mapLinks = static function (mixed $v) use (&$mapLinks, $ids, $existing): mixed {
            if (is_array($v)) {
                return array_map($mapLinks, $v);
            }
            if (is_string($v) && preg_match('~^/([a-z0-9-]+)(#[\w-]+)?$~', $v, $m) && null !== ($target = $ids[$m[1]] ?? $existing($m[1]))) {
                return 'redaxo://' . $target . ($m[2] ?? '');
            }

            return $v;
        };
        // Modul zum Blocktyp: eigenes Modul des Kits ({kit}.{typ}), sonst das von fluid (die Module gehören zu keinem Kit)
        $moduleKey = static fn (string $type): ?string => array_find([$kit->key() . '.' . $type, 'fluid.' . $type], static fn (string $k) => null !== BlockModule::classFor($k));
        $count = 0;
        $skipped = [];
        foreach ($demo['pages'] as $def) {
            if (isset($kept[(string) $def['slug']])) {
                continue;
            }
            $articleId = $ids[(string) $def['slug']];
            foreach ($def['blocks'] ?? [] as $block) {
                // gewöhnliches REDAXO-Modul ('native' => Modulschlüssel, 'values' => Felder) – nur, wenn es im Projekt liegt
                if (isset($block['native'])) {
                    if (!\Redaxo\Core\Content\Module::exists((string) $block['native'])) {
                        $skipped[$block['native']] = true;
                        continue;
                    }
                    foreach (array_unique([ArticleRevision::LIVE, FluidAddon::revision()]) as $rev) {
                        ContentHandler::addSlice($articleId, $lang, 1, (string) $block['native'], (array) ($block['values'] ?? []), revision: $rev);
                    }
                    ++$count;
                    continue;
                }
                $key = $moduleKey((string) $block['type']);
                if (null === $key) {
                    $skipped[$block['type']] = true;
                    continue;
                }
                // Layout: danach seine Spalten-Blöcke ('children' => [[Spalte, Block], …]) als eigene Slices mit tunes.col
                $slices = [[$key, $block['data'] ?? [], $block['tunes'] ?? []]];
                foreach ($block['children'] ?? [] as [$col, $child]) {
                    $slices[] = [$moduleKey((string) $child['type']) ?? 'fluid.' . $child['type'], $child['data'] ?? [], ['col' => $col] + ($child['tunes'] ?? [])];
                }
                foreach ($slices as [$k, $data, $tunes]) {
                    foreach (array_unique([ArticleRevision::LIVE, FluidAddon::revision()]) as $rev) {
                        ContentHandler::addSlice($articleId, $lang, 1, $k, BlockModule::store($mapLinks($data), $tunes), revision: $rev);
                    }
                    ++$count;
                }
            }
            ArticleCache::delete($articleId);
        }

        // Website-Einstellungen der Demo (nur leere Felder) – Links auf Demo-Seiten werden zu redaxo://ID.
        // Als leer gilt auch ein Link auf eine gelöschte Seite: ein neuer Import legt die Seiten mit neuen IDs an.
        if (isset($demo['settings']) && '' === $page) {
            $site = \FriendsOfRedaxo\Fluid\Settings::all();
            $gone = static fn (mixed $v): bool => \is_string($v) && 1 === preg_match('~^redaxo://(\d+)~', $v, $m) && null === Article::get((int) $m[1]);
            foreach ((array) $mapLinks($demo['settings']) as $k => $v) {
                if (!isset($site[$k]) || '' === $site[$k] || [] === $site[$k] || $gone($site[$k])) {
                    $site[$k] = $v;
                }
            }
            FluidAddon::instance()->setConfig(\FriendsOfRedaxo\Fluid\Settings::CONFIG, $site);
        }

        if ('' !== $design) {
            $io->note(self::design($design, $designKit));
        }
        $io->success(sprintf('%d Seiten mit %d Blöcken angelegt.%s', count($ids) - count($kept), $count, $skipped ? ' Übersprungen (noch nicht portiert): ' . implode(', ', array_keys($skipped)) : ''));

        return Command::SUCCESS;
    }

    /** Titel und Alt-Text der Demo-Bilder (Screenshots aus docs/bilder) */
    private const array MEDIA_TEXTS = [
        'fluid-website.jpg' => ['Startseite der Demo', 'Startseite der fluid-Demo mit blauem Kopf und großer Überschrift „REDAXO im Frontend, einfach.“'],
        'fluid-bearbeiten-datensatz.jpg' => ['Blöcke in einem Datensatz', 'Projekt-Detailseite im Bearbeiten-Modus: Block „Kennzahlen“ gewählt, rechts die Seitenleiste mit seinen Feldern'],
        'fluid-formular-schritte.jpg' => ['Formular in Schritten', 'Anmeldeformular mit drei Schritten: Kontakt, Teilnahme, Abschluss'],
        'fluid-blog.jpg' => ['Blog-Übersicht', 'Blog-Übersicht mit großem Aufmacher, Schlagworten und Beitragskarten'],
        'fluid-yform-maske.jpg' => ['Eingabemaske von YForm', 'Eingabemaske eines Projekts im REDAXO-Backend (YForm) mit Bild, Kurztext und den Blöcken der Fallstudie'],
        'fluid-backend-status.jpg' => ['Statusseite im Backend', 'Statusseite von fluid im REDAXO-Backend mit Prüfungen und Wartung'],
        'fluid-design.jpg' => ['Design-Leiste', 'Startseite im Bearbeiten-Modus, rechts die Design-Leiste mit den Vorlagen zum Wechseln'],
        'fluid-trailer-redaxo6.mp4' => ['Trailer: fluid für REDAXO 6', ''],
        'fluid-trailer-redaxo6.jpg' => ['Trailer: Vorschaubild', 'Schriftzug „fluid“ auf dunkelblauem Grund, darunter „In-place editing :: REDAXO like ;-)“'],
        'fluid-trailer-redaxo6.de.vtt' => ['Trailer: deutsche Untertitel', ''],
        'kaetzchen.mp4' => ['Kätzchen spielt', ''],
        'fluid-video-panel.jpg' => ['Video-Editor: Panel im Medienpool', 'Panel des Video-Editors: Vorschau des Kätzchens, Web-Tauglichkeit 100, Angaben zu Dauer, Auflösung und Codec, darunter die Knöpfe Optimieren, Schneiden und Poster wählen'],
        'fluid-video-optimieren.jpg' => ['Video-Editor: Video optimieren', 'Dialog „Video optimieren“: Web 1080p, 720p und 480p angekreuzt, darunter „als neue Fassung speichern“ und der Hinweis „3 Aufträge, sie laufen nacheinander“'],
        'fluid-video-schneiden.jpg' => ['Video-Editor: Schneiden', 'Schnitt-Editor mit Video, Zeitleiste aus Vorschaubildern und einem entfernten, schraffierten Abschnitt'],
        'fluid-video-schnell.jpg' => ['Video-Editor: schneller Schnitt', 'Zeitleiste im Modus „Schnell“ mit Markierungen der Schlüsselbilder und einem gelb markierten Vorlauf'],
        'fluid-video-untertitel.jpg' => ['Video-Editor: Untertitel', 'Untertitel-Editor: Video mit eingeblendetem Untertitel, daneben die Einträge mit Anfang, Ende und Text'],
        'fluid-video-poster.jpg' => ['Video-Editor: Poster wählen', 'Dialog „Poster wählen“ mit dem Video und Knöpfen für eine Sekunde und ein Bild vor und zurück'],
        'fluid-video-warteschlange.jpg' => ['Video-Editor: Warteschlange', 'Medienliste mit dem Hinweis „Video-Editor: ein Auftrag läuft (71 %)“ über den neuen Fassungen des Videos'],
        'fluid-backend-raster.jpg' => ['Backend: Layout als Raster', 'Slice-Liste im Backend: Block „Layout (Spalten)“ mit zwei Spalten nebeneinander, links Fließtext, rechts ein Ring-Diagramm, je Spalte „+ Block in Spalte“'],
        'fluid-backend-baum.jpg' => ['Backend: Layout als Baum', 'Slice-Liste im Backend in der Ansicht „Baum“: Layout mit Rasterskizze, darunter die Spalten-Blöcke eingerückt an einer orangen Linie'],
        'fluid-backend-menue.jpg' => ['Backend: Menü eines Spalten-Blocks', 'Schwebende Leiste über einem Spalten-Block mit geöffnetem Menü: Verdoppeln, Kopieren, Ausschneiden, Einfügen darunter, löschen, online'],
        'fluid-backend-overlay.jpg' => ['Backend: Spalten-Block im Overlay bearbeiten', 'Formular des Blocks „Diagramm“ aus Spalte 2 in einem Dialog über der Slice-Liste, unten Abbrechen, Block speichern, Block übernehmen'],
        'redaxo6-backend-struktur.jpg' => ['Backend: Struktur', 'Strukturverwaltung im Backend von REDAXO 6 mit Kategorien, Artikeln, Status und Funktionen'],
        'redaxo6-backend-inhalte.jpg' => ['Backend: Artikel bearbeiten', 'Artikel im Backend von REDAXO 6: Arbeitsversion, „Block hinzufügen“ und ein Block mit seiner Leiste, rechts die Metadaten'],
        'redaxo6-backend-block-hell.jpg' => ['Backend: Block bearbeiten (hell)', 'Formular eines Blocks im Backend von REDAXO 6 im hellen Farbschema'],
        'redaxo6-backend-block-dunkel.jpg' => ['Backend: Block bearbeiten (dunkel)', 'Formular eines Blocks im Backend von REDAXO 6 im dunklen Farbschema'],
        'redaxo6-backend-medienpool.jpg' => ['Backend: Medienpool im Overlay', 'Medienpool als Overlay über dem Backend von REDAXO 6, mit Sprachumschaltung und Medienliste'],
        'redaxo6-backend-linkmap.jpg' => ['Backend: Linkmap', 'Linkmap als Overlay mit Kategorien links und Artikeln rechts'],
        'redaxo6-backend-mobil.jpg' => ['Backend auf dem Telefon', 'Strukturverwaltung von REDAXO 6 auf einem schmalen Bildschirm mit eingeklapptem Menü'],
        'redaxo6-kit-nature.jpg' => ['Kit „Nature“: Handlungsaufruf', 'Handlungsaufruf im Beispiel-Kit „Nature“ mit sandfarbenem Hintergrund und Blatt über der Dachzeile'],
    ];

    /** Demo-Inhalte des gewählten Kits (oder seines Eltern-Kits): demo.php bzw. demo-redaxo6.php */
    /**
     * Übersetzung der vorhandenen Demo-Seiten (--lang=en): Sprache anlegen, falls nötig, je Seite Titel setzen und online
     * schalten (über den Core, damit Adressen entstehen), Blöcke dieser Sprache neu anlegen – Texte aus
     * demo-{set}.{code}.php (deutsch → Übersetzung). Technische Werte (Varianten, Links, Code, Feldnamen …) bleiben.
     *
     * @param list<array<string, mixed>> $pages
     * @param list<array<string, mixed>> $all
     * @param callable(string): ?int $existing
     * @param array<string, array{rows?: list<array<string, mixed>>}> $yform Beispiel-Datensätze: Tabellen mit Feld „Sprache“ (FluidLanguage) bekommen
     *                                                                    übersetzte Einträge; vorhandene ohne Sprache die Startsprache
     */
    private static function translate(SymfonyStyle $io, string $set, string $code, array $pages, array $all, callable $existing, Kit $kit, array $yform = []): int
    {
        $file = \dirname(self::demoFile($set)) . '/demo-' . ('redaxo6' === $set ? 'redaxo6' : 'fluid') . '.' . $code . '.php';
        if (!is_file($file)) {
            $io->error('Keine Übersetzung: ' . $file);

            return Command::FAILURE;
        }
        /** @var array<string, string> $map */
        $map = require $file;
        $language = array_find(Language::getAll(), static fn (Language $l) => $l->code === $code)
            ?? \Redaxo\Core\Language\LanguageHandler::add(['code' => $code, 'name' => 'en' === $code ? 'English' : $code, 'status' => true]);
        $lang = $language->id;
        $skip = ['type', 'variant', 'icon', 'image', 'link', 'button_link', 'button2_link', 'more_link', 'ratio', 'split', 'size', 'lang', 'code', 'file', 'name',
            'title_field', 'image_field', 'text_field', 'sort_field', 'filter_field', 'sort_dir', 'meta_field', 'filter_value', 'mail_to', 'form_name', 'options',
            'module', 'value', 'client', 'place', 'date', 'year', 'background', 'online'];
        $tr = static function (mixed $v, string $key = '') use (&$tr, $map, $skip): mixed {
            if (is_array($v)) {
                $out = [];
                foreach ($v as $k => $x) {
                    $out[$k] = $tr($x, is_string($k) ? $k : $key);
                }

                return $out;
            }

            return is_string($v) && !in_array($key, $skip, true) && isset($map[$v]) ? $map[$v] : $v;
        };
        $ids = [];
        foreach ($all as $p) {
            // Startseite: der Startartikel der Website (nicht über den Titel – der kann schon übersetzt sein)
            $id = !empty($p['is_home']) && ($start = (int) Core::getConfig('start_article_id', 0)) > 0 ? $start : $existing((string) $p['slug']);
            if (null !== $id) {
                $ids[(string) $p['slug']] = $id;
            }
        }
        $mapLinks = static function (mixed $v) use (&$mapLinks, $ids): mixed {
            if (is_array($v)) {
                return array_map($mapLinks, $v);
            }

            return is_string($v) && preg_match('~^/([a-z0-9-]+)(#[\w-]+)?$~', $v, $m) && isset($ids[$m[1]]) ? 'redaxo://' . $ids[$m[1]] . ($m[2] ?? '') : $v;
        };
        $moduleKey = static fn (string $type): ?string => array_find([$kit->key() . '.' . $type, 'fluid.' . $type], static fn (string $k) => null !== BlockModule::classFor($k));
        $pagesDone = 0;
        $count = 0;
        foreach ($pages as $def) {
            $id = $ids[(string) $def['slug']] ?? null;
            if (null === $id) {
                $io->note('Seite „' . $def['title'] . '“ gibt es nicht – übersprungen.');
                continue;
            }
            $title = $map[(string) $def['title']] ?? (string) $def['title'];
            if (\Redaxo\Core\Content\Category::get($id, $lang)) {
                CategoryHandler::edit($id, $lang, ['name' => $title]);
                CategoryHandler::changeStatus($id, $lang, 1);
            } else {
                ArticleHandler::edit($id, $lang, ['name' => $title]);
                ArticleHandler::changeStatus($id, $lang, 1);
            }
            // Adresse aus dem übersetzten Titel (sonst bliebe der deutsche Teil, den die Sprache beim Anlegen übernommen hat)
            if (empty($def['is_home'])) {
                \FriendsOfRedaxo\Fluid\Routing\Routes::setSlug($id, $lang, $title);
            }
            // vorhandene Blöcke dieser Sprache ersetzen (beide Fassungen)
            Sql::factory()->setQuery('DELETE FROM rex_article_slice WHERE article_id = ? AND language_id = ?', [$id, $lang]);
            foreach ($def['blocks'] ?? [] as $block) {
                if (isset($block['native'])) {
                    if (\Redaxo\Core\Content\Module::exists((string) $block['native'])) {
                        foreach (array_unique([ArticleRevision::LIVE, FluidAddon::revision()]) as $rev) {
                            ContentHandler::addSlice($id, $lang, 1, (string) $block['native'], (array) $tr((array) ($block['values'] ?? [])), revision: $rev);
                        }
                        ++$count;
                    }
                    continue;
                }
                $key = $moduleKey((string) $block['type']);
                if (null === $key) {
                    continue;
                }
                $slices = [[$key, $block['data'] ?? [], $block['tunes'] ?? []]];
                foreach ($block['children'] ?? [] as [$col, $child]) {
                    $slices[] = [$moduleKey((string) $child['type']) ?? 'fluid.' . $child['type'], $child['data'] ?? [], ['col' => $col] + ($child['tunes'] ?? [])];
                }
                foreach ($slices as [$k, $data, $tunes]) {
                    foreach (array_unique([ArticleRevision::LIVE, FluidAddon::revision()]) as $rev) {
                        ContentHandler::addSlice($id, $lang, 1, $k, BlockModule::store($mapLinks($tr($data)), $tunes), revision: $rev);
                    }
                    ++$count;
                }
            }
            if (isset($def['post']) && ($art = \Redaxo\Core\Content\Article::get($id, $lang))) {
                $post = (array) $tr($def['post']);
                \FriendsOfRedaxo\Fluid\PageSettings::save($art, $title, true, (string) ($post['excerpt'] ?? ''), (string) ($post['image'] ?? ''), false);
                \FriendsOfRedaxo\Fluid\Blog\Blog::saveMeta($art, (string) ($post['date'] ?? date('Y-m-d')), (string) ($post['author'] ?? ''), (string) ($post['tags'] ?? ''));
            }
            ArticleCache::delete($id);
            ++$pagesDone;
        }
        // Beispiel-Datensätze (YForm): Einträge in der Sprache anlegen
        $notes = [];
        if ([] !== $yform && YFormBridge::available()) {
            $startCode = Language::get(Language::getStartId())?->code ?? '';
            foreach ($yform as $name => $t) {
                $table = \Yakamara\YForm\Table\Tables::find((string) $name);
                $langField = $table ? array_find($table->getFields(), static fn ($f) => $f instanceof \FriendsOfRedaxo\Fluid\Data\FluidLanguage) : null;
                if (!$table || !$langField || [] === ($t['rows'] ?? [])) {
                    continue;
                }
                $col = $langField->name;
                // vorhandene Einträge ohne Sprache gehören zur Startsprache (sonst stünden sie in jeder Sprache)
                Sql::factory()->setQuery('UPDATE `' . $table->name . '` SET `' . $col . '` = ? WHERE `' . $col . '` = \'\' OR `' . $col . '` IS NULL', [$startCode]);
                if ($table::query()->where($col, $code)->exists()) {
                    continue;
                }
                foreach ($t['rows'] as $row) {
                    $rec = $table->newRecord();
                    foreach ((array) $tr($row) as $k => $v) {
                        $rec->set((string) $k, $table->getField((string) $k) instanceof \Yakamara\YForm\Field\Date && \is_string($v) ? new \DateTimeImmutable($v) : $v);
                    }
                    $rec->set($col, $code);
                    $rec->save();
                }
                $notes[] = \count($t['rows']) . ' in ' . $name;
            }
        }

        \FriendsOfRedaxo\Fluid\Routing\Routes::rebuild();
        Cache::delete();
        $io->success(sprintf('%s: %d Seiten mit %d Blöcken übersetzt.%s', $language->name, $pagesDone, $count, $notes ? ' Datensätze: ' . implode(', ', $notes) . '.' : ''));

        return Command::SUCCESS;
    }

    private static function demoFile(string $set): string
    {
        $name = 'redaxo6' === $set ? 'demo-redaxo6.php' : 'demo.php';

        return Kit::active()->file($name) ?? Kit::get(Kit::DEFAULT)?->path($name) ?? $name;
    }

    /** Ordner mit Demo-Bildern: demo/media des gewählten Kits oder seines Eltern-Kits */
    private static function demoMediaDir(): string
    {
        foreach (Kit::active()->lineage() as $kit) {
            if (is_dir($kit->path('demo/media'))) {
                return $kit->path('demo/media');
            }
        }

        return '';
    }

    /** Demo-Bilder (demo/media des Kits oder ein anderer Ordner) in den Medienpool – nur fehlende */
    private static function media(?string $dir = null): string
    {
        $dir ??= self::demoMediaDir();
        $added = [];
        foreach (glob($dir . '/*') ?: [] as $file) {
            $name = basename($file);
            if (\Redaxo\Core\MediaPool\Media::get($name)) {
                continue;
            }
            $tmp = self::tmpFile($name);
            copy($file, $tmp);
            [$title, $alt] = self::MEDIA_TEXTS[$name] ?? [pathinfo($name, PATHINFO_FILENAME), ''];
            $res = \Redaxo\Core\MediaPool\MediaHandler::add(['category_id' => null, 'title' => $title, 'file' => new \Symfony\Component\HttpFoundation\File\File($tmp)], subindexing: false);
            @unlink($tmp);
            // Alt-Text (Spalte meta_alt des Cores, je Sprache) – für Screenreader und für img()/picture()
            if ('' !== $alt) {
                try {
                    \Redaxo\Core\Database\Sql::factory()->setQuery('UPDATE rex_media_translation t JOIN rex_media m ON m.id = t.media_id SET t.meta_alt = ? WHERE m.filename = ?', [$alt, $res->fileName]);
                } catch (\Throwable) {
                    // ohne Spalte meta_alt: nur Titel
                }
            }
            \Redaxo\Core\MediaPool\Media::clearInstances($name);   // „nicht vorhanden“ aus der Prüfung oben vergessen
            $added[] = $name;
        }

        return $added ? 'Medienpool: ' . implode(', ', $added) : 'Medienpool: Demo-Bilder schon vorhanden.';
    }

    /** Design-Vorlage setzen; „redaxo“ zusätzlich mit dem REDAXO-Logo aus dem Core (grau und weiß), wenn noch kein Logo gesetzt ist */
    private static function design(string $preset, string $kitKey = ''): string
    {
        $kit = '' !== $kitKey ? Kit::get($kitKey) : Kit::active();
        if (null === $kit) {
            return 'Kit „' . $kitKey . '“ gibt es nicht (' . implode(', ', array_keys(Kit::all())) . ').';
        }
        $presets = (array) ($kit->design()->def()['presets'] ?? []);
        if (!isset($presets[$preset])) {
            return 'Design-Vorlage „' . $preset . '“ gibt es nicht (' . implode(', ', array_keys($presets)) . ').';
        }
        $kit->design()->save((array) $presets[$preset]['values']);
        $msg = 'Design-Vorlage „' . $presets[$preset]['label'] . '“ gesetzt.';
        // direkt aus der Konfiguration (Settings::all() puffert den Stand vor den Einstellungen der Demo)
        $site = (array) FluidAddon::instance()->getConfig(\FriendsOfRedaxo\Fluid\Settings::CONFIG, []);
        $logo = \Redaxo\Core\Filesystem\Path::core('assets/redaxo-logo.svg');
        if ('redaxo' === $preset && '' === (string) ($site['logo'] ?? '') && is_file($logo)) {
            $svg = (string) file_get_contents($logo);
            foreach (['redaxo-logo.svg' => $svg, 'redaxo-logo-weiss.svg' => preg_replace('~fill="#[0-9A-Fa-f]{6}"~', 'fill="#FFFFFF"', $svg)] as $name => $content) {
                if (!\Redaxo\Core\MediaPool\Media::get($name)) {
                    $tmp = self::tmpFile($name);
                    file_put_contents($tmp, $content);
                    \Redaxo\Core\MediaPool\MediaHandler::add(['category_id' => null, 'title' => 'REDAXO-Logo', 'file' => new \Symfony\Component\HttpFoundation\File\File($tmp)], subindexing: false);
                    @unlink($tmp);
                    \Redaxo\Core\MediaPool\Media::clearInstances($name);
                }
            }
            $site['logo'] = 'redaxo-logo.svg';
            $site['logo_dark'] = 'redaxo-logo-weiss.svg';
            FluidAddon::instance()->setConfig(\FriendsOfRedaxo\Fluid\Settings::CONFIG, $site);
            $msg .= ' Logo: REDAXO.';
        }

        return $msg;
    }

    /**
     * Beispielzeilen in die YForm-Tabellen der Demo (nur leere Tabellen). Die Tabellen selbst sind Klassen im Projekt
     * (#[AsTable], Vorlage: vendor/friendsofredaxo/fluid/examples/yform) – YForm legt sie bei `console migrate` an.
     *
     * @param array<string, array{rows: list<array<string, mixed>>}> $tables
     */
    private static function yform(array $tables): string
    {
        $out = [];
        foreach ($tables as $name => $t) {
            $table = \Yakamara\YForm\Table\Tables::find($name);
            if (!$table) {
                $out[] = $name . ' fehlt – Klasse aus examples/yform ins Projekt kopieren und `console migrate` ausführen';
                continue;
            }
            if ([] === ($t['rows'] ?? []) || $table::query()->exists()) {
                continue;
            }
            foreach ($t['rows'] as $row) {
                $rec = $table->newRecord();
                foreach ($row as $k => $v) {
                    $rec->set($k, $table->getField($k) instanceof \Yakamara\YForm\Field\Date && \is_string($v) ? new \DateTimeImmutable($v) : $v);
                }
                $rec->save();
            }
            $out[] = \count($t['rows']) . ' Beispielzeilen in ' . $name;
        }

        return $out ? 'YForm: ' . implode(', ', $out) : 'YForm: Beispieldaten schon vorhanden.';
    }

    /** Zwischendatei mit dem Namen der Datei im Medienpool (der Medienpool übernimmt den Dateinamen) */
    private static function tmpFile(string $name): string
    {
        $dir = \Redaxo\Core\Filesystem\Path::cache('fluid-demo');
        if (!is_dir($dir)) {
            mkdir($dir, 0o775, true);
        }

        return $dir . '/' . $name;
    }
}

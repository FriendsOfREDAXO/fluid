<?php

namespace FriendsOfRedaxo\Fluid\Console;

use FriendsOfRedaxo\Fluid\BlockModule;
use FriendsOfRedaxo\Fluid\Data\YFormBridge;
use FriendsOfRedaxo\Fluid\FluidAddon;
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidKit;
use Redaxo\Core\Cache;
use Redaxo\Core\Console\Command\AbstractCommand;
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
        #[Option(description: 'Nur diese Demo-Seite (Slug) anhängen, z. B. daten – vorhandene Inhalte bleiben')] string $page = '',
        #[Option(description: 'Nur Beispielzeilen in die YForm-Tabellen der Demo schreiben (Tabellen sind Klassen im Projekt)')] bool $yform = false,
        #[Option(description: 'Demo-Bilder des Kits in den Medienpool übernehmen (vorhandene bleiben)')] bool $media = false,
        #[Option(description: 'Design-Vorlage setzen, z. B. redaxo (mit REDAXO-Logo aus dem Core), kanzlei, handwerk …')] string $design = '',
    ): int {
        if ($media) {
            $io->note(self::media());
        }
        if ($yform) {
            $demo = require FluidKit::instance()->path('redaxo6' === $set ? 'demo-redaxo6.php' : 'demo.php');
            if (!isset($demo['yform']) || !YFormBridge::available()) {
                $io->error('Keine YForm-Tabellen in dieser Demo oder YForm fehlt.');

                return Command::FAILURE;
            }
            $io->success(self::yform($demo['yform']));

            return Command::SUCCESS;
        }
        if ($resetDesign) {
            FluidAddon::instance()->removeConfig('design.' . FluidKit::instance()->key());
            $io->note('Design zurückgesetzt.');
        }
        $sql = Sql::factory();
        if ('' !== $page) {
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
            $io->error('Es gibt schon Artikel. Mit --force trotzdem anlegen, mit --replace vorher alles löschen.');

            return Command::FAILURE;
        }

        // Bearbeiten auf der Website arbeitet in der Arbeitsversion (Entwurf → Veröffentlichen)
        Core::setConfig('article_work_version', true);

        $kit = FluidKit::instance();
        $kit->definition();
        /** @var array{pages: list<array<string, mixed>>} $demo */
        $demo = require $kit->path('redaxo6' === $set ? 'demo-redaxo6.php' : 'demo.php');
        $lang = Language::getStartId();
        $ids = [];
        // Seiten, die ein AddOn brauchen (z. B. YForm), nur anlegen, wenn es da ist
        $demo['pages'] = array_values(array_filter($demo['pages'], static fn (array $p) => 'yform' !== ($p['requires'] ?? null) || YFormBridge::available()));
        if ('' !== $page) {
            $demo['pages'] = array_values(array_filter($demo['pages'], static fn (array $p) => $p['slug'] === $page));
            if (!$demo['pages']) {
                $io->error('Keine Demo-Seite „' . $page . '“ (oder benötigtes AddOn fehlt).');

                return Command::FAILURE;
            }
        }
        if (isset($demo['yform']) && YFormBridge::available()) {
            $io->note(self::yform($demo['yform']));
        }

        // 1. Seiten: Menüpunkte als Kategorien, alle übrigen als Artikel auf oberster Ebene
        $prio = 1 + (int) ($sql->getArray('SELECT MAX(priority) AS p FROM rex_category')[0]['p'] ?? 0);
        foreach ($demo['pages'] as $def) {
            $slug = (string) $def['slug'];
            // Startseite und Seiten außerhalb des Menüs (Impressum, Datenschutz): Artikel auf oberster Ebene;
            // 'parent' => 'slug': Seite in einer Menüseite (z. B. Beiträge eines Blogs, Angaben unter 'post')
            if (!empty($def['is_home']) || empty($def['menu'])) {
                $parent = isset($def['parent']) ? ($ids[(string) $def['parent']] ?? null) : null;
                ArticleHandler::addArticle(['category_id' => $parent, 'priority' => !empty($def['is_home']) ? 1 : 99, 'name' => (string) $def['title'], 'template' => $kit->key()]);
                $ids[$slug] = (int) $sql->getArray('SELECT MAX(id) AS id FROM rex_article')[0]['id'];
                ArticleHandler::articleStatus($ids[$slug], $lang, 1);
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
            CategoryHandler::addCategory(null, ['catpriority' => $prio++, 'catname' => (string) $def['title'], 'status' => 1]);
            $id = (int) $sql->getArray('SELECT MAX(id) AS id FROM rex_article')[0]['id'];
            $sql->setQuery('UPDATE rex_article_translation SET template = ?, status = 1 WHERE article_id = ?', [$kit->key(), $id]);
            $ids[$slug] = $id;
            if (!empty($def['blog'])) {
                \FriendsOfRedaxo\Fluid\Blog\Blog::setBlog($id, true);
            }
        }

        // 2. Blöcke – Links auf Demo-Seiten (/kontakt) werden zu redaxo://ID
        $mapLinks = static function (mixed $v) use (&$mapLinks, $ids): mixed {
            if (is_array($v)) {
                return array_map($mapLinks, $v);
            }
            if (is_string($v) && preg_match('~^/([a-z0-9-]+)(#[\w-]+)?$~', $v, $m) && isset($ids[$m[1]])) {
                return 'redaxo://' . $ids[$m[1]] . ($m[2] ?? '');
            }

            return $v;
        };
        $count = 0;
        $skipped = [];
        foreach ($demo['pages'] as $def) {
            $articleId = $ids[(string) $def['slug']];
            foreach ($def['blocks'] ?? [] as $block) {
                $key = $kit->key() . '.' . $block['type'];
                if (!Module::get($key) instanceof BlockModule) {
                    $skipped[$block['type']] = true;
                    continue;
                }
                // Layout: danach seine Spalten-Blöcke ('children' => [[Spalte, Block], …]) als eigene Slices mit tunes.col
                $slices = [[$key, $block['data'] ?? [], $block['tunes'] ?? []]];
                foreach ($block['children'] ?? [] as [$col, $child]) {
                    $slices[] = [$kit->key() . '.' . $child['type'], $child['data'] ?? [], ['col' => $col] + ($child['tunes'] ?? [])];
                }
                foreach ($slices as [$k, $data, $tunes]) {
                    foreach (array_unique([ArticleRevision::LIVE, FluidAddon::revision()]) as $rev) {
                        ContentHandler::addSlice($articleId, $lang, 1, $k, [
                            'revision' => $rev,
                            'value1' => json_encode($mapLinks($data), JSON_UNESCAPED_UNICODE),
                            'value2' => json_encode((object) $tunes, JSON_UNESCAPED_UNICODE),
                        ]);
                    }
                    ++$count;
                }
            }
            ArticleCache::delete($articleId);
        }

        // Website-Einstellungen der Demo (nur leere Felder) – Links auf Demo-Seiten werden zu redaxo://ID
        if (isset($demo['settings']) && '' === $page) {
            $site = \FriendsOfRedaxo\Fluid\Settings::all();
            foreach ((array) $mapLinks($demo['settings']) as $k => $v) {
                if (!isset($site[$k]) || '' === $site[$k] || [] === $site[$k]) {
                    $site[$k] = $v;
                }
            }
            FluidAddon::instance()->setConfig(\FriendsOfRedaxo\Fluid\Settings::CONFIG, $site);
        }

        if ('' !== $design) {
            $io->note(self::design($design));
        }
        $io->success(sprintf('%d Seiten mit %d Blöcken angelegt.%s', count($ids), $count, $skipped ? ' Übersprungen (noch nicht portiert): ' . implode(', ', array_keys($skipped)) : ''));

        return Command::SUCCESS;
    }

    /** Titel und Alt-Text der Demo-Bilder (Screenshots aus docs/bilder) */
    private const array MEDIA_TEXTS = [
        'fluid-website.jpg' => ['Startseite der Demo', 'Startseite der fluid-Demo mit blauem Kopf und großer Überschrift „REDAXO im Frontend, einfach.“'],
        'fluid-bearbeiten-datensatz.jpg' => ['Blöcke in einem Datensatz', 'Detailansicht eines YForm-Eintrags im Bearbeiten-Modus, rechts die Seitenleiste mit den Feldern eines Blocks'],
        'fluid-formular-schritte.jpg' => ['Formular in Schritten', 'Anmeldeformular mit drei Schritten: Kontakt, Teilnahme, Abschluss'],
        'fluid-blog.jpg' => ['Blog-Übersicht', 'Blog-Übersicht mit großem Aufmacher, Schlagworten und Beitragskarten'],
        'fluid-yform-maske.jpg' => ['Eingabemaske von YForm', 'Eingabemaske einer YForm-Tabelle im Backend mit dem Feld „Fluid-Blöcke“'],
        'fluid-backend-status.jpg' => ['Statusseite im Backend', 'Statusseite von fluid im REDAXO-Backend mit Prüfungen und Wartung'],
        'fluid-trailer.jpg' => ['Standbild aus dem Video', 'Standbild aus dem Vorstellungsvideo von fluid'],
    ];

    /** Demo-Bilder (kits/fluid/demo/media) in den Medienpool – nur fehlende */
    private static function media(): string
    {
        $dir = FluidKit::instance()->path('demo/media');
        $added = [];
        foreach (glob($dir . '/*') ?: [] as $file) {
            $name = basename($file);
            if (\Redaxo\Core\MediaPool\Media::get($name)) {
                continue;
            }
            $tmp = \Redaxo\Core\Filesystem\Path::cache('fluid-demo-' . $name);
            copy($file, $tmp);
            [$title, $alt] = self::MEDIA_TEXTS[$name] ?? [pathinfo($name, PATHINFO_FILENAME), ''];
            $res = \Redaxo\Core\MediaPool\MediaHandler::addMedia(['category_id' => null, 'title' => $title, 'file' => ['name' => $name, 'path' => $tmp]], false);
            @unlink($tmp);
            // Alt-Text (Spalte meta_alt des Cores, je Sprache) – für Screenreader und für img()/picture()
            if ('' !== $alt) {
                try {
                    \Redaxo\Core\Database\Sql::factory()->setQuery('UPDATE rex_media_translation t JOIN rex_media m ON m.id = t.media_id SET t.meta_alt = ? WHERE m.filename = ?', [$alt, (string) ($res['filename'] ?? $name)]);
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
    private static function design(string $preset): string
    {
        $kit = FluidKit::instance();
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
                    $tmp = \Redaxo\Core\Filesystem\Path::cache('fluid-demo-' . $name);
                    file_put_contents($tmp, $content);
                    \Redaxo\Core\MediaPool\MediaHandler::addMedia(['category_id' => null, 'title' => 'REDAXO-Logo', 'file' => ['name' => $name, 'path' => $tmp]], false);
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
}


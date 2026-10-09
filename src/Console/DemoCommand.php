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
    ): int {
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
            foreach (['rex_article_slice_history', 'rex_article_slice', 'rex_article_translation', 'rex_category_translation', 'rex_article', 'rex_category'] as $table) {
                $sql->setQuery('DELETE FROM ' . $table);
            }
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
        foreach ($demo['pages'] as $page) {
            $slug = (string) $page['slug'];
            // Startseite und Seiten außerhalb des Menüs (Impressum, Datenschutz): Artikel auf oberster Ebene
            if (!empty($page['is_home']) || empty($page['menu'])) {
                ArticleHandler::addArticle(['category_id' => null, 'priority' => !empty($page['is_home']) ? 1 : 99, 'name' => (string) $page['title'], 'template' => $kit->key()]);
                $ids[$slug] = (int) $sql->getArray('SELECT MAX(id) AS id FROM rex_article')[0]['id'];
                ArticleHandler::articleStatus($ids[$slug], $lang, 1);
                if (!empty($page['is_home'])) {
                    Core::setConfig('start_article_id', $ids[$slug]);
                    Core::setConfig('notfound_article_id', $ids[$slug]);
                }
                continue;
            }
            CategoryHandler::addCategory(null, ['catpriority' => $prio++, 'catname' => (string) $page['title'], 'status' => 1]);
            $id = (int) $sql->getArray('SELECT MAX(id) AS id FROM rex_article')[0]['id'];
            $sql->setQuery('UPDATE rex_article_translation SET template = ?, status = 1 WHERE article_id = ?', [$kit->key(), $id]);
            $ids[$slug] = $id;
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
        foreach ($demo['pages'] as $page) {
            $articleId = $ids[(string) $page['slug']];
            foreach ($page['blocks'] ?? [] as $block) {
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

        $io->success(sprintf('%d Seiten mit %d Blöcken angelegt.%s', count($ids), $count, $skipped ? ' Übersprungen (noch nicht portiert): ' . implode(', ', array_keys($skipped)) : ''));

        return Command::SUCCESS;
    }

    /**
     * YForm-Tabellen der Demo anlegen (falls noch nicht da) und mit Beispielzeilen füllen (nur leere Tabellen).
     *
     * @param array<string, array{name: string, fields: list<array<string, mixed>>, rows: list<array<string, mixed>>}> $tables
     */
    private static function yform(array $tables): string
    {
        $out = [];
        foreach ($tables as $name => $t) {
            if (!\Yakamara\YForm\Manager\Table\Table::get($name)) {
                $prio = 0;
                \Yakamara\YForm\Manager\Table\Api::setTable(['table_name' => $name, 'name' => $t['name'], 'status' => 1],
                    array_map(static function (array $f) use (&$prio) { return $f + ['prio' => ++$prio]; }, $t['fields']));
                \Yakamara\YForm\Manager\Table\Table::deleteCache();
                $out[] = 'Tabelle ' . $name . ' angelegt';
            }
            // Spalten zu den Feldern anlegen (setTable legt nur die Tabelle an)
            \Yakamara\YForm\Manager\Table\Api::generateTableAndFields(\Yakamara\YForm\Manager\Table\Table::require($name));
            \Yakamara\YForm\Manager\Table\Table::deleteCache();
            $sql = Sql::factory();
            if ($t['rows'] && !$sql->getArray('SELECT id FROM ' . $sql->escapeIdentifier($name) . ' LIMIT 1')) {
                foreach ($t['rows'] as $row) {
                    $sql->setTable($name)->setValues($row)->insert();
                }
                $out[] = count($t['rows']) . ' Beispielzeilen in ' . $name;
            }
        }

        return $out ? 'YForm: ' . implode(', ', $out) : 'YForm: Tabellen schon vorhanden.';
    }
}


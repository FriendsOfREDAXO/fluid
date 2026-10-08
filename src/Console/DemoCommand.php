<?php

namespace FriendsOfRedaxo\Blocks\Console;

use FriendsOfRedaxo\Blocks\BlockModule;
use FriendsOfRedaxo\Blocks\BlocksAddon;
use FriendsOfRedaxo\Blocks\Kit\Fluid\FluidKit;
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
#[AsCommand(name: 'blocks:demo', description: 'Legt die Demo-Seiten des Kits „fluid“ an (nur für leere Testinstallationen)')]
final class DemoCommand extends AbstractCommand
{
    public function __invoke(SymfonyStyle $io, #[Option(description: 'Auch anlegen, wenn schon Artikel existieren')] bool $force = false): int
    {
        $sql = Sql::factory();
        if (!$force && $sql->getArray('SELECT id FROM rex_article LIMIT 1')) {
            $io->error('Es gibt schon Artikel. Mit --force trotzdem anlegen.');

            return Command::FAILURE;
        }

        // Bearbeiten auf der Website arbeitet in der Arbeitsversion (Entwurf → Veröffentlichen)
        Core::setConfig('article_work_version', true);

        $kit = FluidKit::instance();
        $kit->definition();
        /** @var array{pages: list<array<string, mixed>>} $demo */
        $demo = require $kit->path('demo.php');
        $lang = Language::getStartId();
        $ids = [];

        // 1. Seiten: Menüpunkte als Kategorien, alle übrigen als Artikel auf oberster Ebene
        $prio = 1;
        foreach ($demo['pages'] as $page) {
            $slug = (string) $page['slug'];
            // Startseite und Seiten außerhalb des Menüs (Impressum, Datenschutz): Artikel auf oberster Ebene
            if (!empty($page['is_home']) || empty($page['menu'])) {
                ArticleHandler::addArticle(['category_id' => null, 'priority' => !empty($page['is_home']) ? 1 : 99, 'name' => (string) $page['title'], 'template' => $kit->key()]);
                $ids[$slug] = (int) $sql->getArray('SELECT MAX(id) AS id FROM rex_article')[0]['id'];
                ArticleHandler::articleStatus($ids[$slug], $lang, 1);
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
                foreach ([ArticleRevision::LIVE, BlocksAddon::revision()] as $rev) {
                    ContentHandler::addSlice($articleId, $lang, 1, $key, [
                        'revision' => $rev,
                        'value1' => json_encode($mapLinks($block['data'] ?? []), JSON_UNESCAPED_UNICODE),
                        'value2' => json_encode($block['tunes'] ?? [], JSON_UNESCAPED_UNICODE),
                    ]);
                    if (ArticleRevision::LIVE === BlocksAddon::revision()) {
                        break;
                    }
                }
                ++$count;
            }
            ArticleCache::delete($articleId);
        }

        $io->success(sprintf('%d Seiten mit %d Blöcken angelegt.%s', count($ids), $count, $skipped ? ' Übersprungen (noch nicht portiert): ' . implode(', ', array_keys($skipped)) : ''));

        return Command::SUCCESS;
    }
}

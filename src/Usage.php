<?php

namespace FriendsOfRedaxo\Fluid;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ExtensionPoint\ArticleUsageCheck;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\MediaPool\ExtensionPoint\MediaUsageCheck;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\UsageList;

use function array_fill;
use function array_keys;
use function count;
use function implode;

/**
 * Wo werden Medien und Seiten in Blöcken verwendet? Meldet die Fundstellen an den Core (Löschschutz im Medienpool und in
 * der Struktur, Anzeige „Verwendet in“).
 *
 * Der Core liest die Verweise eines Slices aus den Feldtypen der Module (Tabelle rex_article_slice_reference). Die Felder
 * der Blöcke stehen aber in der Kit-Definition, nicht als Feldtypen – deshalb sucht fluid in der Spalte `data` selbst:
 * Medien unter ihrem Dateinamen, Seiten als `redaxo://ID` (Linkfelder, Rich-Text).
 */
final class Usage
{
    public static function boot(): void
    {
        Extension::register(MediaUsageCheck::class, static function (MediaUsageCheck $ep): void {
            // als JSON-String ("datei.jpg") oder in einer Liste ("a.jpg,datei.jpg")
            $name = preg_quote($ep->media->fileName);
            self::add($ep->usages, '(^|[",])' . $name . '([",]|$)');
        });
        Extension::register(ArticleUsageCheck::class, static function (ArticleUsageCheck $ep): void {
            self::add($ep->usages, 'redaxo://' . $ep->article->id . '([^0-9]|$)', $ep->article->id);
        });
    }

    /** Seiten, deren Blöcke (beide Fassungen) auf das Muster passen – ohne die Seite selbst */
    private static function add(UsageList $usages, string $pattern, int $exceptArticle = 0): void
    {
        $modules = array_keys(BlockModule::all());
        if (!$modules) {
            return;
        }
        $rows = Sql::factory()->getArray(
            'SELECT DISTINCT article_id, language_id FROM rex_article_slice WHERE module IN (' . implode(',', array_fill(0, count($modules), '?')) . ') AND article_id <> ? AND data REGEXP ?',
            [...$modules, $exceptArticle, $pattern],
        );
        foreach ($rows as $r) {
            $id = (int) $r['article_id'];
            $languageId = (int) $r['language_id'];
            $usages->add(I18n::rawMsg('usage_articles_content'), Article::get($id, $languageId)->name ?? (string) $id,
                Url::backendPage('content', ['article_id' => $id, 'mode' => 'edit', 'language' => $languageId]));
        }
    }
}

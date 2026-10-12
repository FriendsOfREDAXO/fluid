<?php

namespace FriendsOfRedaxo\Fluid;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\Content\ArticleHandler;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Content\CategoryHandler;
use Redaxo\Core\Content\ContentHandler;
use Redaxo\Core\Database\Sql;

/**
 * Seitenvorlagen (Modell aus KLXM Studio – Core\PageTemplates): Vorlagen sind normale Seiten in der Offline-Kategorie
 * „Seitenvorlagen“ – für Besucher unsichtbar (offline: kein Menü, keine Sitemap, 404), bearbeitet im normalen Block-Editor.
 * Beim Anlegen einer Seite wählt die Redaktion „Leere Seite“, „Kopie dieser Seite“ oder eine Vorlage und bekommt eine
 * Kopie der Blöcke (aus der Arbeitsversion der Vorlage – also so, wie sie zuletzt bearbeitet wurde).
 */
final class PageTemplates
{
    private const string CONFIG = 'templates_category';

    /** Kategorie der Vorlagen (wird beim ersten Speichern einer Vorlage angelegt) */
    public static function categoryId(bool $create = false): ?int
    {
        $id = (int) FluidAddon::instance()->getConfig(self::CONFIG, 0);
        if ($id && Category::get($id)) {
            return $id;
        }
        if (!$create) {
            return null;
        }
        CategoryHandler::addCategory(null, ['catpriority' => 9999, 'catname' => 'Seitenvorlagen', 'status' => 0]);
        $id = (int) Sql::factory()->getArray('SELECT MAX(id) AS id FROM rex_category')[0]['id'];
        FluidAddon::instance()->setConfig(self::CONFIG, $id);

        return $id;
    }

    /** @return list<array{id: int, name: string}> */
    public static function all(int $languageId): array
    {
        $cat = self::categoryId() ? Category::get((int) self::categoryId(), $languageId) : null;
        if (!$cat) {
            return [];
        }

        return array_values(array_map(static fn (Article $a) => ['id' => $a->id, 'name' => $a->name],
            array_filter($cat->getArticles(false), static fn (Article $a) => !$a->isStartArticle())));
    }

    /** Aktuelle Seite als Vorlage speichern; gibt die ID der Vorlage zurück */
    public static function saveFrom(Article $article, string $name): int
    {
        $cat = (int) self::categoryId(true);
        ArticleHandler::addArticle(['category_id' => $cat, 'priority' => 999, 'name' => trim($name) ?: $article->name, 'template' => (string) $article->templateKey, 'status' => 0]);
        $id = (int) Sql::factory()->getArray('SELECT MAX(id) AS id FROM rex_article')[0]['id'];
        self::copy($article->id, $id, $article->languageId);

        return $id;
    }

    /** Blöcke einer Seite (Arbeitsversion) in eine andere kopieren – in Arbeitsversion und Live-Fassung */
    public static function copy(int $fromId, int $toId, int $languageId): void
    {
        $work = FluidAddon::revision();
        ContentHandler::copyContent($fromId, $toId, $languageId, $languageId, $work);
        if (ArticleRevision::LIVE !== $work) {
            ArticleRevision::copyContent($toId, $languageId, $work, ArticleRevision::LIVE);
        }
        ArticleCache::delete($toId, $languageId);
    }

    public static function isTemplate(int $articleId): bool
    {
        $cat = self::categoryId();

        return null !== $cat && ($articleId === $cat || Article::get($articleId)?->categoryId === $cat);
    }
}

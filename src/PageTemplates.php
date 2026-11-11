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
        $id = CategoryHandler::add(['parent_id' => null, 'priority' => 9999, 'name' => 'Seitenvorlagen', 'status' => 0])->id;
        FluidAddon::instance()->setConfig(self::CONFIG, $id);

        return $id;
    }

    /** @return list<array{id: int, name: string, url: string}> url = Bearbeiten auf der Website */
    public static function all(int $languageId): array
    {
        $cat = self::categoryId() ? Category::get((int) self::categoryId(), $languageId) : null;
        if (!$cat) {
            return [];
        }

        return array_values(array_map(static fn (Article $a) => ['id' => $a->id, 'name' => $a->name, 'url' => \Redaxo\Core\Filesystem\Url::article($a->id, $languageId, [Editing::PARAM => 1])],
            array_filter($cat->getArticles(false), static fn (Article $a) => !$a->isStartArticle())));
    }

    /** Vorlage umbenennen (nur Seiten in „Seitenvorlagen“, nicht die Kategorie selbst) */
    public static function rename(int $id, string $name, int $languageId): void
    {
        $a = self::template($id, $languageId);
        $name = trim($name);
        if ('' === $name) {
            throw new ApiError('Bitte einen Namen eingeben.');
        }
        ArticleHandler::edit($a->id, $languageId, ['name' => mb_substr($name, 0, 191), 'template' => (string) $a->templateKey]);
    }

    /** Vorlage löschen – vorhandene Seiten, die aus ihr entstanden sind, bleiben unberührt (sie haben eine Kopie) */
    public static function delete(int $id, int $languageId): string
    {
        $a = self::template($id, $languageId);
        ArticleHandler::delete($a->id);

        return $a->name;
    }

    private static function template(int $id, int $languageId): Article
    {
        $a = Article::get($id, $languageId);
        if (null === $a || $a->isStartArticle() || $a->categoryId !== self::categoryId()) {
            throw new ApiError('Vorlage nicht gefunden.');
        }

        return $a;
    }

    /** Aktuelle Seite als Vorlage speichern; gibt die ID der Vorlage zurück */
    public static function saveFrom(Article $article, string $name): int
    {
        $cat = (int) self::categoryId(true);
        $id = ArticleHandler::add(['category_id' => $cat, 'priority' => 999, 'name' => trim($name) ?: $article->name, 'template' => (string) $article->templateKey])->id;   // neu angelegte Seiten sind offline
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

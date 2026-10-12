<?php

namespace FriendsOfRedaxo\Fluid;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\Content\ArticleHandler;
use Redaxo\Core\Content\CategoryHandler;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\MediaPool\Media;

use function Redaxo\Core\View\escape;

/**
 * Seiteneinstellungen auf der Website: Titel und Online-Status (Core) sowie Beschreibung, Social-Bild und „nicht indexieren“
 * (Tabelle rex_fluid_page – bewusst kein Meta-Schema, das gehört in REDAXO 6 dem Projekt).
 */
final class PageSettings
{
    public const string TABLE = 'rex_fluid_page';

    /** @return array{description: string, image: string, noindex: bool, title: string} */
    public static function get(int $articleId, int $languageId): array
    {
        $row = Sql::factory()->getArray('SELECT description, image, noindex, title FROM ' . self::TABLE . ' WHERE article_id = ? AND language_id = ?', [$articleId, $languageId])[0] ?? [];

        return ['description' => (string) ($row['description'] ?? ''), 'image' => (string) ($row['image'] ?? ''), 'noindex' => !empty($row['noindex']), 'title' => (string) ($row['title'] ?? '')];
    }

    /** Titel im Browser-Tab und in Suchergebnissen: eigener SEO-Titel oder „Seite – Website“ (Startseite: Website) */
    public static function title(Article $article, string $siteName): string
    {
        $own = self::get($article->id, $article->languageId)['title'];
        if ('' !== $own) {
            return $own;
        }

        return $article->id === Article::getSiteStartArticleId() ? $siteName : $article->name . ' – ' . $siteName;
    }

    /** Titel, Status und Angaben speichern */
    public static function save(Article $article, string $name, bool $online, string $description, string $image, bool $noindex, string $title = '', ?string $slug = null): void
    {
        $name = trim($name);
        if ('' === $name) {
            throw new ApiError('Bitte einen Titel angeben.');
        }
        if ($name !== $article->name) {
            ArticleHandler::editArticle($article->id, $article->languageId, ['name' => $name, 'template' => $article->templateKey]);
            if ($article->isStartArticle() && $article->categoryId) {
                CategoryHandler::editCategory($article->categoryId, $article->languageId, ['catname' => $name]);
            }
        }
        if ($online !== $article->isOnline()) {
            if ($article->isStartArticle() && $article->categoryId) {
                CategoryHandler::categoryStatus($article->categoryId, $article->languageId, $online ? 1 : 0);
            } else {
                ArticleHandler::articleStatus($article->id, $article->languageId, $online ? 1 : 0);
            }
        }
        Sql::factory()->setQuery(
            'REPLACE INTO ' . self::TABLE . ' (article_id, language_id, description, image, noindex, title) VALUES (?, ?, ?, ?, ?, ?)',
            [$article->id, $article->languageId, mb_substr(trim($description), 0, 300), '' !== $image && Media::get($image) ? $image : '', $noindex ? 1 : 0, mb_substr(trim($title), 0, 191)],
        );
        // Adresse: eigener Slug (leer = aus dem Titel); die Startseite hat immer „/“
        if (null !== $slug && $article->id !== Article::getSiteStartArticleId()) {
            $want = '' !== trim($slug) ? trim($slug) : $name;
            if (Routing\Routes::slugify($want) !== Routing\Routes::slug($article->id, $article->languageId)) {
                Routing\Routes::setSlug($article->id, $article->languageId, $want);
            }
        }
        ArticleCache::delete($article->id, $article->languageId);
    }

    /** <meta>-Angaben für das Template (Beschreibung, Social-Bild, robots) */
    public static function head(Article $article, string $siteName): string
    {
        $s = self::get($article->id, $article->languageId);
        $h = '';
        if ('' !== $s['description']) {
            $h .= '<meta name="description" content="' . escape($s['description']) . '">' . "\n"
                . '<meta property="og:description" content="' . escape($s['description']) . '">' . "\n";
        }
        $h .= Routing\Seo::head($article);
        $h .= '<meta property="og:title" content="' . escape($s['title'] ?: $article->name) . '">' . "\n"
            . '<meta property="og:site_name" content="' . escape($siteName) . '">' . "\n";
        if ('' !== $s['image'] && ($m = Media::get($s['image']))) {
            $url = class_exists(\FriendsOfRedaxo\ImageEditor\ImageEditor::class) ? \FriendsOfRedaxo\ImageEditor\ImageEditor::url($m->fileName, 1280) : $m->getUrl();
            // Netzwerke brauchen eine absolute Adresse
            $url = preg_match('~^https?://~', $url) ? $url : Url::absoluteBase(ltrim(preg_replace('~^\./~', '', $url), '/'));
            $h .= '<meta property="og:image" content="' . escape($url) . '">' . "\n";
        }
        if ($s['noindex'] || !$article->isOnline()) {
            $h .= '<meta name="robots" content="noindex, nofollow">' . "\n";
        }

        return $h;
    }
}

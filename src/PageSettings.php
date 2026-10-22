<?php

namespace FriendsOfRedaxo\Fluid;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\Content\ArticleHandler;
use Redaxo\Core\Content\ArticleRevision;
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

    /** @var array<string, true>|null Seiten auf „nicht indexieren“ (Artikel|Sprache) */
    private static ?array $noindex = null;

    /** Steht die Seite auf „nicht indexieren“? (Sitemap, eine Abfrage je Anfrage) */
    public static function noindex(int $articleId, int $languageId): bool
    {
        if (null === self::$noindex) {
            self::$noindex = [];
            foreach (\Redaxo\Core\Database\Sql::factory()->getArray('SELECT article_id, language_id FROM ' . self::TABLE . ' WHERE noindex = 1') as $r) {
                self::$noindex[$r['article_id'] . '|' . $r['language_id']] = true;
            }
        }

        return isset(self::$noindex[$articleId . '|' . $languageId]);
    }

    /** @return array{description: string, image: string, noindex: bool, title: string} */
    public static function get(int $articleId, int $languageId): array
    {
        $row = Sql::factory()->getArray('SELECT description, image, noindex, title FROM ' . self::TABLE . ' WHERE article_id = ? AND language_id = ?', [$articleId, $languageId])[0] ?? [];

        return ['description' => (string) ($row['description'] ?? ''), 'image' => (string) ($row['image'] ?? ''), 'noindex' => !empty($row['noindex']), 'title' => (string) ($row['title'] ?? '')];
    }

    /** @var array{title?: string, description?: string, image?: string, canonical?: string} */
    private static array $override = [];

    /**
     * Angaben für den Seitenkopf aus einer Ausgabe überschreiben – z. B. Detailansicht eines Eintrags:
     * PageSettings::override(['title' => …, 'description' => …, 'image' => 'datei.jpg', 'canonical' => 'https://…']).
     * Muss beim Rendern der Blöcke passieren (das Template rendert den Inhalt vor dem Kopf).
     */
    public static function override(array $values): void
    {
        self::$override = array_filter($values, static fn ($v) => \is_string($v) && '' !== trim($v)) + self::$override;
    }

    /** @internal */
    public static function overridden(string $key): ?string
    {
        return self::$override[$key] ?? null;
    }

    /** Titel im Browser-Tab und in Suchergebnissen: eigener SEO-Titel oder „Seite – Website“ (Startseite: Website) */
    public static function title(Article $article, string $siteName): string
    {
        if (null !== ($t = self::$override['title'] ?? null)) {
            return $t . ' – ' . $siteName;
        }
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
            ArticleHandler::edit($article->id, $article->languageId, ['name' => $name, 'template' => $article->templateKey]);
            if ($article->isStartArticle() && $article->categoryId) {
                CategoryHandler::edit($article->categoryId, $article->languageId, ['name' => $name]);
            }
        }
        if ($online !== $article->isOnline()) {
            if ($article->isStartArticle() && $article->categoryId) {
                CategoryHandler::changeStatus($article->categoryId, $article->languageId, $online ? 1 : 0);
            } else {
                ArticleHandler::changeStatus($article->id, $article->languageId, $online ? 1 : 0);
            }
        }
        Sql::factory()->setQuery(
            'INSERT INTO ' . self::TABLE . ' (article_id, language_id, description, image, noindex, title) VALUES (?, ?, ?, ?, ?, ?)'
            . ' ON DUPLICATE KEY UPDATE description = VALUES(description), image = VALUES(image), noindex = VALUES(noindex), title = VALUES(title)',
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
        $s = array_merge(self::get($article->id, $article->languageId), array_intersect_key(self::$override, ['title' => 1, 'description' => 1, 'image' => 1]));
        // Ohne eigene Beschreibung: Standard aus „Website“
        if ('' === $s['description']) {
            $s['description'] = trim((string) Settings::get('default_meta_description', ''));
        }
        $h = '';
        if ('' !== $s['description']) {
            $h .= '<meta name="description" content="' . escape($s['description']) . '">' . "\n"
                . '<meta property="og:description" content="' . escape($s['description']) . '">' . "\n";
        }
        $h .= Seo\Head::head($article);
        $h .= '<meta property="og:title" content="' . escape($s['title'] ?: $article->name) . '">' . "\n"
            . '<meta property="og:site_name" content="' . escape($siteName) . '">' . "\n";
        if ('' !== $s['image'] && ($m = Media::get($s['image']))) {
            // Mit image_editor: 1200 × 630 als JPEG um den Fokuspunkt (Format der Netzwerke, LinkedIn kann kein WebP)
            $share = class_exists(\FriendsOfRedaxo\ImageEditor\ImageEditor::class) ? \FriendsOfRedaxo\ImageEditor\ImageEditor::shareUrl($m->fileName) : null;
            $url = $share ?? $m->getUrl();
            // Netzwerke brauchen eine absolute Adresse
            $url = preg_match('~^https?://~', $url) ? $url : Url::absoluteBase(ltrim(preg_replace('~^\./~', '', $url), '/'));
            $h .= '<meta property="og:image" content="' . escape($url) . '">' . "\n";
            if (null !== $share) {
                $h .= '<meta property="og:image:type" content="image/jpeg"><meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">' . "\n";
            }
            if ('' !== $alt = trim((string) ($m->getValue('meta_alt') ?? ''))) {
                $h .= '<meta property="og:image:alt" content="' . escape($alt) . '">' . "\n";
            }
        }
        if ($s['noindex'] || !$article->isOnline()) {
            $h .= '<meta name="robots" content="noindex, nofollow">' . "\n";
        }

        return $h;
    }

    /**
     * Bilder einer Seite für die Sitemap: Vorschaubild, dann Bilder der veröffentlichten, sichtbaren Blöcke (Verweise des
     * Cores, rex_article_slice_reference).
     *
     * @return list<string>
     */
    public static function sitemapImages(int $articleId, int $languageId): array
    {
        $files = [];
        $own = (string) (self::get($articleId, $languageId)['image'] ?? '');
        if ('' !== $own) {
            $files[] = $own;
        }
        $rows = Sql::factory()->getArray(
            'SELECT r.target FROM rex_article_slice_reference r JOIN rex_article_slice s ON s.id = r.slice_id
             WHERE r.type = ? AND s.article_id = ? AND s.language_id = ? AND s.revision = ? AND s.status = 1 ORDER BY s.content_section_id, s.priority',
            ['media', $articleId, $languageId, ArticleRevision::LIVE],
        );
        foreach ($rows as $r) {
            $files[] = (string) $r['target'];
        }
        $out = [];
        foreach (array_unique($files) as $file) {
            if (1 === preg_match('~\.(jpe?g|png|webp|avif|gif|svg)$~i', $file) && ($m = Media::get($file))) {
                $out[] = $m->getUrl();
            }
        }

        return $out;
    }
}

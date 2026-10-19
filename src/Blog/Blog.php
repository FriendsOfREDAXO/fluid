<?php

namespace FriendsOfRedaxo\Fluid\Blog;

use FriendsOfRedaxo\Fluid\PageSettings;
use FriendsOfRedaxo\Fluid\Routing\Router;
use FriendsOfRedaxo\Fluid\Settings;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Language\Language;

use function Redaxo\Core\View\escape;

/**
 * Blog / News: Eine Kategorie (Menüpunkt) wird in ihren Seiteneinstellungen zum Blog – ihre Seiten sind dann Beiträge.
 * Beiträge sind normale Artikel mit Blöcken (Entwurf, Versionen, Rechte, Adressen, Sprachen, Suche – alles vom Rest);
 * dazu kommen Datum, Autor und Schlagworte (Tabelle rex_fluid_page). Teaserbild = Bild für soziale Netzwerke,
 * Kurztext = Beschreibung für Suchmaschinen. Sortierung nach Datum, neueste zuerst; RSS unter {blog}/feed.xml.
 *
 *     Blog::posts($blogId, ['limit' => 3]);          // neueste drei Beiträge
 *     Blog::blogOf(Article::getCurrent());           // ID des Blogs, wenn die Seite ein Beitrag ist
 */
final class Blog
{
    /** Beim Routing und SEO anmelden: RSS /{blog}/feed.xml, Feed-Link im Seitenkopf, schema.org Blog/BlogPosting */
    public static function boot(): void
    {
        Router::resolver(static function (string $full, int $lang): ?int {
            if (preg_match('~^(.+)/feed\.xml$~', $full, $m) && null !== ($bid = \FriendsOfRedaxo\Fluid\Routing\Routes::find($m[1], $lang)) && self::isBlog($bid)) {
                Language::setCurrentId($lang);
                Router::respond(self::feed($bid), 'application/rss+xml; charset=utf-8');
            }

            return null;
        });
        Extension::register('FLUID_SEO_HEAD', static function (ExtensionPoint $ep): string {
            $article = $ep->getParam('article');
            $blog = self::isBlog($article->id) && $article->isStartArticle() ? $article->id : self::blogOf($article);
            $h = (string) $ep->subject;
            if ($blog && ($b = Article::get($blog, $article->languageId))) {
                $h .= '<link rel="alternate" type="application/rss+xml" title="' . \Redaxo\Core\View\escape($b->name) . '" href="'
                    . \Redaxo\Core\View\escape(rtrim(Router::canonical($blog, $article->languageId), '/') . '/feed.xml') . '">' . "\n";
            }

            return $h;
        });
        // Übersicht = Blog, Beitrag = BlogPosting (Datum, Autor, Bild)
        Extension::register('FLUID_STRUCTURED_DATA', static function (ExtensionPoint $ep): array {
            $data = (array) $ep->subject;
            $article = $ep->getParam('article');
            $url = (string) $ep->getParam('url');
            $org = (string) $ep->getParam('org');
            if (self::isBlog($article->id) && $article->isStartArticle()) {
                $data['@graph'][] = ['@type' => 'Blog', '@id' => $url . '#blog', 'url' => $url, 'name' => $article->name, 'mainEntityOfPage' => ['@id' => $url . '#webpage'], 'publisher' => ['@id' => $org]];
            } elseif (null !== ($blog = self::blogOf($article)) && ($p = Post::get($article->id, $article->languageId))) {
                $data['@graph'][] = array_filter([
                    '@type' => 'BlogPosting', '@id' => $url . '#post', 'headline' => mb_substr($article->name, 0, 110), 'url' => $url,
                    'datePublished' => $p->date->format('Y-m-d'), 'dateModified' => $article->updateDate ? date('c', $article->updateDate) : null,
                    'author' => '' !== $p->author ? ['@type' => 'Person', 'name' => $p->author] : ['@id' => $org],
                    'publisher' => ['@id' => $org], 'description' => '' !== $p->excerpt ? $p->excerpt : null,
                    'image' => '' !== $p->image ? Router::absolute(\Redaxo\Core\MediaManager\MediaManager::getUrl('rex_media_large', $p->image)) : null,
                    'keywords' => $p->tags ? implode(', ', $p->tags) : null, 'inLanguage' => (string) $ep->getParam('lang'),
                    'mainEntityOfPage' => ['@id' => $url . '#webpage'], 'isPartOf' => ['@id' => Router::canonical($blog, $article->languageId) . '#blog'],
                ]);
            }

            return $data;
        });
    }

    /** @var array<int, bool>|null */
    private static ?array $blogs = null;

    /** Kategorien, die Blogs sind: [ID => Name] */
    public static function all(): array
    {
        $out = [];
        foreach (array_keys(self::ids()) as $id) {
            if ($c = Category::get($id)) {
                $out[$id] = $c->name;
            }
        }

        return $out;
    }

    private static function ids(): array
    {
        if (null === self::$blogs) {
            self::$blogs = [];
            foreach (Sql::factory()->getArray('SELECT DISTINCT article_id FROM ' . PageSettings::TABLE . ' WHERE blog = 1') as $r) {
                self::$blogs[(int) $r['article_id']] = true;
            }
        }

        return self::$blogs;
    }

    public static function isBlog(int $categoryId): bool
    {
        return isset(self::ids()[$categoryId]);
    }

    /** Blog-Kategorie, wenn die Seite ein Beitrag ist (Artikel, kein Startartikel, in einer Blog-Kategorie) */
    public static function blogOf(?Article $a): ?int
    {
        return $a && !$a->isStartArticle() && $a->categoryId && self::isBlog($a->categoryId) ? $a->categoryId : null;
    }

    public static function setBlog(int $categoryId, bool $on): void
    {
        foreach (Language::getAll() as $l) {
            Sql::factory()->setQuery('INSERT INTO ' . PageSettings::TABLE . ' (article_id, language_id, blog) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE blog = VALUES(blog)',
                [$categoryId, $l->id, $on ? 1 : 0]);
        }
        self::$blogs = null;
    }

    /** Datum, Autor, Schlagworte eines Beitrags (je Sprache; Datum gilt für alle Sprachen) */
    public static function saveMeta(Article $a, string $date, string $author, string $tags): void
    {
        $date = preg_match('~^\d{4}-\d{2}-\d{2}$~', $date) ? $date : null;
        $tags = implode(', ', self::splitTags($tags));
        Sql::factory()->setQuery('INSERT INTO ' . PageSettings::TABLE . ' (article_id, language_id, author, tags) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE author = VALUES(author), tags = VALUES(tags)',
            [$a->id, $a->languageId, mb_substr(trim($author), 0, 120), mb_substr($tags, 0, 255)]);
        foreach (Language::getAll() as $l) {
            Sql::factory()->setQuery('INSERT INTO ' . PageSettings::TABLE . ' (article_id, language_id, post_date) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE post_date = VALUES(post_date)',
                [$a->id, $l->id, $date]);
        }
    }

    /** @return list<string> */
    public static function splitTags(string $tags): array
    {
        $out = [];
        foreach (preg_split('~[,;]~', $tags) ?: [] as $t) {
            $t = trim(preg_replace('~\s+~u', ' ', $t) ?? '');
            if ('' !== $t && !\in_array(mb_strtolower($t), array_map('mb_strtolower', $out), true)) {
                $out[] = mb_substr($t, 0, 40);
            }
        }

        return \array_slice($out, 0, 12);
    }

    public static function tagSlug(string $tag): string
    {
        return \FriendsOfRedaxo\Fluid\Routing\Routes::slugify($tag);
    }

    /**
     * Beiträge eines Blogs (0 = aller Blogs), neueste zuerst; nur online (außer $drafts für die Redaktion).
     *
     * @param array{limit?: int, offset?: int, tag?: string, drafts?: bool, language?: int, exclude?: int} $opt
     * @return array{total: int, posts: list<Post>}
     */
    public static function posts(int $blogId, array $opt = []): array
    {
        $lang = $opt['language'] ?? Language::getCurrentId();
        $blogIds = $blogId ? [$blogId] : array_keys(self::ids());
        if (!$blogIds) {
            return ['total' => 0, 'posts' => []];
        }
        $where = 'a.parent_id IN (' . implode(',', array_map('intval', $blogIds)) . ') AND a.id NOT IN (SELECT id FROM rex_category)';
        $params = [$lang];
        if (empty($opt['drafts'])) {
            $where .= ' AND t.status = 1';
        }
        if (!empty($opt['exclude'])) {
            $where .= ' AND a.id <> ' . (int) $opt['exclude'];
        }
        $tag = trim((string) ($opt['tag'] ?? ''));
        $sql = 'SELECT a.id, p.post_date, p.tags, a.createdate FROM rex_article a JOIN rex_article_translation t ON t.article_id = a.id AND t.language_id = ?'
            . ' LEFT JOIN ' . PageSettings::TABLE . ' p ON p.article_id = a.id AND p.language_id = t.language_id WHERE ' . $where
            . ' ORDER BY COALESCE(p.post_date, DATE(a.createdate)) DESC, a.id DESC';
        $rows = Sql::factory()->getArray($sql, $params);
        if ('' !== $tag) {
            $rows = array_values(array_filter($rows, static fn (array $r) => \in_array($tag, array_map(self::tagSlug(...), self::splitTags((string) $r['tags'])), true)));
        }
        $total = \count($rows);
        $rows = \array_slice($rows, max(0, (int) ($opt['offset'] ?? 0)), ($opt['limit'] ?? 0) > 0 ? (int) $opt['limit'] : null);
        $posts = [];
        foreach ($rows as $r) {
            if ($p = Post::get((int) $r['id'], $lang)) {
                $posts[] = $p;
            }
        }

        return ['total' => $total, 'posts' => $posts];
    }

    /** Vorheriger (älterer) und nächster (neuerer) Beitrag */
    public static function neighbors(Article $a): array
    {
        $blog = self::blogOf($a);
        if (!$blog) {
            return [null, null];
        }
        $all = self::posts($blog, ['language' => $a->languageId])['posts'];
        $ids = array_map(static fn (Post $p) => $p->article->id, $all);
        $i = array_search($a->id, $ids, true);
        if (false === $i) {
            return [null, null];
        }

        return [$all[$i + 1] ?? null, $all[$i - 1] ?? null];
    }

    /** Alle Schlagworte eines Blogs mit Anzahl: [slug => [Name, Anzahl]] */
    public static function tags(int $blogId): array
    {
        $out = [];
        foreach (self::posts($blogId)['posts'] as $p) {
            foreach ($p->tags as $t) {
                $s = self::tagSlug($t);
                $out[$s] = [$out[$s][0] ?? $t, ($out[$s][1] ?? 0) + 1];
            }
        }
        uasort($out, static fn ($x, $y) => $y[1] <=> $x[1] ?: strcmp($x[0], $y[0]));

        return $out;
    }

    /** RSS 2.0 eines Blogs (Sprache der Anfrage) */
    public static function feed(int $blogId): string
    {
        $lang = Language::getCurrentId();
        $blog = Article::get($blogId, $lang);
        $site = Settings::name();
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>'
            . '<title>' . escape(($blog->name ?? 'Blog') . ' – ' . $site) . '</title>'
            . '<link>' . escape(Router::canonical($blogId, $lang)) . '</link>'
            . '<description>' . escape((string) (PageSettings::get($blogId, $lang)['description'] ?: $site)) . '</description>'
            . '<language>' . escape(Language::get($lang)->code ?? 'de') . '</language>'
            . '<atom:link href="' . escape(rtrim(Router::canonical($blogId, $lang), '/') . '/feed.xml') . '" rel="self" type="application/rss+xml"/>';
        foreach (self::posts($blogId, ['limit' => 30])['posts'] as $p) {
            $url = Router::canonical($p->article->id, $lang);
            $xml .= '<item><title>' . escape($p->article->name) . '</title><link>' . escape($url) . '</link><guid isPermaLink="true">' . escape($url) . '</guid>'
                . '<pubDate>' . $p->date->format(DATE_RSS) . '</pubDate>'
                . ('' !== $p->excerpt ? '<description>' . escape($p->excerpt) . '</description>' : '')
                . implode('', array_map(static fn ($t) => '<category>' . escape($t) . '</category>', $p->tags))
                . '</item>';
        }

        return (string) Extension::dispatch(new ExtensionPoint('FLUID_BLOG_FEED', $xml . '</channel></rss>', ['blog' => $blogId]));
    }
}

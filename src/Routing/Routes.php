<?php

namespace FriendsOfRedaxo\Fluid\Routing;

use FriendsOfRedaxo\Fluid\Routing\ExtensionPoint\RoutesRebuilt;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Language\Language;
use Throwable;

use function array_fill;
use function array_filter;
use function array_keys;
use function array_map;
use function explode;
use function function_exists;
use function implode;
use function is_array;
use function is_file;
use function json_decode;
use function json_encode;
use function mb_strtolower;
use function preg_replace;
use function strtr;
use function trim;
use function var_export;

use const JSON_UNESCAPED_UNICODE;

/**
 * Gespeicherte Adressen (Modell aus KLXM Studio – Core\Pages):
 *
 *  - Seiten (rex_fluid_route): je Sprache ein fester Slug. Er entsteht beim Anlegen aus dem Namen und bleibt beim
 *    Umbenennen gleich (stabile Adressen); ändern lässt er sich über setSlug(). path = Slugs der Elternkategorien + eigener
 *    Slug, Startseite = ''.
 *  - Einträge der Quellen (rex_fluid_route_entry): Pfad der Seite + Slug des Eintrags ({@see RouteSource}).
 *  - Pfade werden nach Strukturänderungen neu berechnet (rebuild); geänderte Pfade bekommen automatisch eine
 *    301-Weiterleitung. Gespeichert wird ohne Sprachpräfix (/en/…) und ohne Endung ({@see UrlFormat}).
 *  - Cache: Adressen der Seiten als PHP-Datei (OPcache) – eine Datei für alle Seiten, eine je Quelle (erst bei Bedarf
 *    geladen). Eine Adresse, die keine Seite ist, kostet eine indizierte Abfrage.
 */
final class Routes
{
    public const string TABLE = 'rex_fluid_route';
    public const string ENTRY_TABLE = 'rex_fluid_route_entry';

    /** @var array<int, array<int, array{0: string, 1: string, 2: bool}>>|null Seite => Sprache => [slug, path, Menüpunkt] */
    private static ?array $pages = null;

    /** @var array<int, array<string, int>>|null Sprache => path => Seite */
    private static ?array $byPath = null;

    /** @var array<string, array<string, array<int, array{0: string, 1: int}>>> Quelle => ID => Sprache => [path, Seite] */
    private static array $entries = [];

    // ------------------------------------------------------------------ Slugs

    public static function slugify(string $s): string
    {
        $s = strtr(mb_strtolower(trim($s)), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', '’' => '', "'" => '', '´' => '', '`' => '']);
        $s = (string) preg_replace('~[^a-z0-9]+~', '-', $s);

        return trim($s, '-') ?: 'seite';
    }

    /** Slug mit „/“ (Einträge): jeder Teil bereinigt, leere fallen weg */
    public static function slugifyPath(string $s): string
    {
        $parts = array_filter(array_map('trim', explode('/', $s)), static fn (string $p) => '' !== $p);

        return implode('/', array_map(self::slugify(...), $parts)) ?: 'seite';
    }

    // ------------------------------------------------------------------ Seiten

    /** Pfad ('' = Startseite) einer Seite in einer Sprache – oder null */
    public static function path(int $articleId, int $languageId): ?string
    {
        return self::pages()[$articleId][$languageId][1] ?? null;
    }

    public static function slug(int $articleId, int $languageId): ?string
    {
        return self::pages()[$articleId][$languageId][0] ?? null;
    }

    /** Menüpunkt (Startartikel einer Kategorie)? – für die Endung im gemischten Format */
    public static function isCategory(int $articleId, int $languageId): bool
    {
        return self::pages()[$articleId][$languageId][2] ?? false;
    }

    /** Seite zu einem Pfad (ohne Sprachpräfix, ohne Endung, ohne Schrägstriche außen) */
    public static function find(string $path, int $languageId): ?int
    {
        return self::byPath()[$languageId][$path] ?? null;
    }

    /** Wie find(), aber ohne Groß-/Kleinschreibung (für die Weiterleitung auf die kanonische Schreibweise) */
    public static function findInsensitive(string $path, int $languageId): ?int
    {
        $want = mb_strtolower($path);
        foreach (self::byPath()[$languageId] ?? [] as $p => $id) {
            if (mb_strtolower((string) $p) === $want) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Alle Seiten mit Adresse: Seite => Sprache => ['slug' => …, 'path' => …].
     *
     * @return array<int, array<int, array{slug: string, path: string}>>
     */
    public static function all(): array
    {
        return array_map(static fn (array $byLang) => array_map(static fn (array $r) => ['slug' => $r[0], 'path' => $r[1]], $byLang), self::pages());
    }

    /** Eigenen Slug setzen (Seiteneinstellungen); leer = aus dem Namen. Gibt den tatsächlich vergebenen Slug zurück. */
    public static function setSlug(int $articleId, int $languageId, string $slug): string
    {
        $slug = self::slugify($slug);
        Sql::factory()->setQuery('INSERT INTO ' . self::TABLE . ' (article_id, language_id, slug, path) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE slug = VALUES(slug)', [$articleId, $languageId, $slug, '']);
        self::invalidate();
        self::rebuild();

        return self::slug($articleId, $languageId) ?? $slug;
    }

    /**
     * Pfade aller Seiten neu berechnen (nach Strukturänderungen), danach die Quellen, wenn sich ein Pfad geändert hat.
     * Fehlende Slugs entstehen aus dem Namen, gleiche Slugs auf einer Ebene werden durchnummeriert (-2, -3 …).
     * Gelöschte Seiten fallen heraus.
     */
    public static function rebuild(bool $sources = false): void
    {
        $sql = Sql::factory();
        $rows = $sql->getArray('SELECT a.id, a.parent_id, a.path, a.priority, t.language_id, t.name, t.status, r.slug, r.path AS route
            FROM rex_article a JOIN rex_article_translation t ON t.article_id = a.id
            LEFT JOIN ' . self::TABLE . ' r ON r.article_id = a.id AND r.language_id = t.language_id
            ORDER BY a.priority, a.id');
        $home = Article::getSiteStartArticleId();
        $byLang = [];
        foreach ($rows as $r) {
            $byLang[(int) $r['language_id']][(int) $r['id']] = $r;
        }
        $changes = [];
        $keep = [];
        foreach ($byLang as $lang => $items) {
            // Slugs: vorhandene behalten, fehlende aus dem Namen; je Ebene eindeutig
            $taken = [];
            $slugs = [];
            foreach ($items as $id => $r) {
                $level = (string) ($r['parent_id'] ?? '');
                $base = '' !== (string) $r['slug'] ? (string) $r['slug'] : self::slugify((string) $r['name']);
                $s = $base;
                for ($n = 2; isset($taken[$level][$s]); ++$n) {
                    $s = $base . '-' . $n;
                }
                $taken[$level][$s] = true;
                $slugs[$id] = $s;
            }
            foreach ($items as $id => $r) {
                $anc = array_values(array_filter(array_map('intval', explode('|', (string) $r['path']))));
                $parts = array_map(static fn (int $a) => $slugs[$a] ?? (string) $a, $anc);
                $parts[] = $slugs[$id];
                $path = $id === $home ? '' : implode('/', $parts);
                $keep[$id . '|' . $lang] = true;
                if (null === $r['route'] || $path !== (string) $r['route'] || $slugs[$id] !== (string) $r['slug']) {
                    $sql->setQuery('INSERT INTO ' . self::TABLE . ' (article_id, language_id, slug, path) VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE slug = VALUES(slug), path = VALUES(path)', [$id, $lang, $slugs[$id], $path]);
                    if (null === $r['route'] || $path !== (string) $r['route']) {
                        $sources = true;   // Einträge unter dieser Seite bekommen einen neuen Pfad
                    }
                    if (null !== $r['route'] && $path !== (string) $r['route']) {
                        $changes[] = ['target' => 'redaxo://' . $id, 'lang' => $lang, 'old' => (string) $r['route'], 'new' => $path, 'online' => 1 === (int) $r['status']];
                    }
                }
            }
        }
        // Gelöschte Seiten/Sprachen entfernen
        foreach ($sql->getArray('SELECT article_id, language_id FROM ' . self::TABLE) as $e) {
            if (!isset($keep[$e['article_id'] . '|' . $e['language_id']])) {
                $sql->setQuery('DELETE FROM ' . self::TABLE . ' WHERE article_id = ? AND language_id = ?', [$e['article_id'], $e['language_id']]);
                $sources = true;
            }
        }
        self::invalidate();
        Redirects::pathsChanged($changes);
        if ($sources) {
            foreach (Sources::all() as $key => $_) {
                self::rebuildSource($key, false);
            }
            // Einträge von Quellen, die es nicht mehr gibt (AddOn entfernt)
            $known = array_keys(Sources::all());
            $sql->setQuery('DELETE FROM ' . self::ENTRY_TABLE . ($known ? ' WHERE source NOT IN (' . implode(',', array_fill(0, \count($known), '?')) . ')' : ''), $known);
        }
        Extension::dispatch(new RoutesRebuilt());
    }

    /** Sprachpräfix ('' für die Startsprache, sonst '/en') */
    public static function prefix(int $languageId): string
    {
        if (Language::count() < 2 || $languageId === Language::getStartId()) {
            return '';
        }

        return '/' . (Language::get($languageId)->code ?? (string) $languageId);
    }

    // ------------------------------------------------------------------ Einträge der Quellen

    /** Pfad eines Eintrags (ohne Sprachpräfix und Endung) – oder null */
    public static function entryPath(string $source, string $id, int $languageId): ?string
    {
        return self::entriesOf($source)[$id][$languageId][0] ?? null;
    }

    /** Seite, die einen Eintrag anzeigt – oder null */
    public static function entryArticle(string $source, string $id, int $languageId): ?int
    {
        return self::entriesOf($source)[$id][$languageId][1] ?? null;
    }

    /** @return list<int> Sprachen, in denen es einen Eintrag gibt */
    public static function entryLanguages(string $source, string $id): array
    {
        return array_keys(self::entriesOf($source)[$id] ?? []);
    }

    /** Eintrag zu einem Pfad (ohne Sprachpräfix, ohne Endung) – Groß-/Kleinschreibung egal, der Router leitet dann um */
    public static function findEntry(string $path, int $languageId): ?RouteEntry
    {
        try {
            $r = Sql::factory()->getArray('SELECT * FROM ' . self::ENTRY_TABLE . ' WHERE language_id = ? AND path = ? LIMIT 1', [$languageId, $path])[0] ?? null;
        } catch (Throwable) {
            return null;
        }

        return null !== $r ? self::entryOf($r) : null;
    }

    /**
     * Gespeicherte Einträge (Sitemap): alle bzw. einer Quelle.
     *
     * @return iterable<RouteEntry>
     */
    public static function entries(?string $source = null): iterable
    {
        try {
            $rows = null === $source
                ? Sql::factory()->getArray('SELECT * FROM ' . self::ENTRY_TABLE . ' ORDER BY source, entity, language_id')
                : Sql::factory()->getArray('SELECT * FROM ' . self::ENTRY_TABLE . ' WHERE source = ? ORDER BY entity, language_id', [$source]);
        } catch (Throwable) {
            return [];
        }
        foreach ($rows as $r) {
            yield self::entryOf($r);
        }
    }

    /**
     * Alle Einträge einer Quelle neu berechnen. Gibt die Zahl der Adressen zurück.
     *
     * @param bool $notify Extension Point RoutesRebuilt auslösen
     */
    public static function rebuildSource(string $key, bool $notify = true): int
    {
        $source = Sources::get($key);
        $sql = Sql::factory();
        $old = [];
        foreach ($sql->getArray('SELECT entity, language_id, path FROM ' . self::ENTRY_TABLE . ' WHERE source = ?', [$key]) as $r) {
            $old[$r['entity'] . '|' . $r['language_id']] = (string) $r['path'];
        }
        $taken = self::takenPaths($key);
        $new = [];
        $changes = [];
        foreach (null !== $source ? $source->entries() : [] as $e) {
            $path = self::entryPathFor($e, $taken);
            if (null === $path) {
                continue;
            }
            $k = $e->id . '|' . $e->languageId;
            $new[$k] = true;
            self::store($e, $path);
            if (isset($old[$k]) && $old[$k] !== $path) {
                $changes[] = ['target' => 'route://' . $key . '/' . $e->id, 'lang' => $e->languageId, 'old' => $old[$k], 'new' => $path, 'online' => true];
            }
        }
        foreach ($old as $k => $_) {
            if (!isset($new[$k])) {
                [$id, $lang] = explode('|', $k);
                $sql->setQuery('DELETE FROM ' . self::ENTRY_TABLE . ' WHERE source = ? AND entity = ? AND language_id = ?', [$key, $id, (int) $lang]);
            }
        }
        unset(self::$entries[$key]);
        self::writeEntryCache($key);
        Redirects::pathsChanged($changes);
        if ($notify) {
            Extension::dispatch(new RoutesRebuilt($key));
        }

        return \count($new);
    }

    /** Einzelne Einträge speichern bzw. neu berechnen (z. B. nach dem Speichern eines Datensatzes) */
    public static function put(RouteEntry ...$entries): void
    {
        $touched = [];
        foreach ($entries as $e) {
            $taken = self::takenPaths($e->source, $e);
            $old = self::entryPath($e->source, $e->id, $e->languageId);
            $path = self::entryPathFor($e, $taken);
            if (null === $path) {
                continue;
            }
            self::store($e, $path);
            if (null !== $old && $old !== $path) {
                Redirects::pathsChanged([['target' => 'route://' . $e->source . '/' . $e->id, 'lang' => $e->languageId, 'old' => $old, 'new' => $path, 'online' => true]]);
            }
            $touched[$e->source] = true;
        }
        foreach ($touched as $source => $_) {
            unset(self::$entries[$source]);
            self::writeEntryCache($source);
        }
    }

    /** Eintrag entfernen (alle Sprachen bzw. eine) */
    public static function remove(string $source, string $id, ?int $languageId = null): void
    {
        Sql::factory()->setQuery('DELETE FROM ' . self::ENTRY_TABLE . ' WHERE source = ? AND entity = ?' . (null !== $languageId ? ' AND language_id = ?' : ''),
            null !== $languageId ? [$source, $id, $languageId] : [$source, $id]);
        unset(self::$entries[$source]);
        self::writeEntryCache($source);
    }

    /** Pfad eines Eintrags: Seite + Slug, eindeutig gegen Seiten und andere Einträge; null = Seite ohne Adresse */
    private static function entryPathFor(RouteEntry $e, array &$taken): ?string
    {
        $page = self::path($e->articleId, $e->languageId);
        if (null === $page) {
            return null;
        }
        $base = ('' !== $page ? $page . '/' : '') . self::slugifyPath($e->slug);
        $path = $base;
        for ($n = 2; isset($taken[$e->languageId][mb_strtolower($path)]); ++$n) {
            $path = $base . '-' . $n;
        }
        $taken[$e->languageId][mb_strtolower($path)] = true;

        return $path;
    }

    /**
     * Belegte Pfade (klein geschrieben): Seiten und Einträge anderer Quellen bzw. andere Einträge derselben Quelle.
     *
     * @return array<int, array<string, true>>
     */
    private static function takenPaths(string $source, ?RouteEntry $except = null): array
    {
        $taken = [];
        foreach (self::pages() as $byLang) {
            foreach ($byLang as $lang => $r) {
                $taken[$lang][mb_strtolower($r[1])] = true;
            }
        }
        $sql = Sql::factory();
        $rows = null === $except
            ? $sql->getArray('SELECT language_id, path FROM ' . self::ENTRY_TABLE . ' WHERE source <> ?', [$source])
            : $sql->getArray('SELECT language_id, path FROM ' . self::ENTRY_TABLE . ' WHERE NOT (source = ? AND entity = ? AND language_id = ?)', [$source, $except->id, $except->languageId]);
        foreach ($rows as $r) {
            $taken[(int) $r['language_id']][mb_strtolower((string) $r['path'])] = true;
        }

        return $taken;
    }

    private static function store(RouteEntry $e, string $path): void
    {
        Sql::factory()->setQuery('INSERT INTO ' . self::ENTRY_TABLE . ' (source, entity, language_id, article_id, slug, path, params, modified, sitemap, images)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE article_id = VALUES(article_id), slug = VALUES(slug), path = VALUES(path), params = VALUES(params),
                modified = VALUES(modified), sitemap = VALUES(sitemap), images = VALUES(images)', [
            $e->source, $e->id, $e->languageId, $e->articleId, mb_substr($e->slug, 0, 191), $path,
            json_encode($e->params, JSON_UNESCAPED_UNICODE), $e->modified, $e->sitemap ? 1 : 0, json_encode($e->images, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /** @param array<string, mixed> $r */
    private static function entryOf(array $r): RouteEntry
    {
        $params = json_decode((string) ($r['params'] ?? ''), true);
        $images = json_decode((string) ($r['images'] ?? ''), true);

        return new RouteEntry((string) $r['source'], (string) $r['entity'], (int) $r['language_id'], (int) $r['article_id'], (string) $r['slug'],
            is_array($params) ? $params : [], null !== $r['modified'] ? (int) $r['modified'] : null, (bool) $r['sitemap'], is_array($images) ? $images : [], (string) $r['path']);
    }

    // ------------------------------------------------------------------ Cache

    /** Gespeicherte Adressen im Speicher vergessen (z. B. nach Änderungen durch einen anderen Prozess) */
    public static function reset(): void
    {
        self::$pages = self::$byPath = null;
        self::$entries = [];
    }

    /** Cache der Seiten verwerfen (nach dem Neuaufbau) */
    private static function invalidate(): void
    {
        self::reset();
        self::write('pages.php', null);
    }

    /** @return array<int, array<int, array{0: string, 1: string, 2: bool}>> */
    private static function pages(): array
    {
        if (null !== self::$pages) {
            return self::$pages;
        }
        $cached = self::read('pages.php');
        if (null !== $cached) {
            return self::$pages = $cached;
        }
        $out = [];
        try {
            foreach (Sql::factory()->getArray('SELECT r.article_id, r.language_id, r.slug, r.path, c.id AS category FROM ' . self::TABLE . ' r LEFT JOIN rex_category c ON c.id = r.article_id') as $r) {
                $out[(int) $r['article_id']][(int) $r['language_id']] = [(string) $r['slug'], (string) $r['path'], null !== $r['category'] && '' !== (string) $r['path']];
            }
        } catch (Throwable) {
            return self::$pages = [];   // Tabelle noch nicht angelegt
        }
        self::write('pages.php', $out);

        return self::$pages = $out;
    }

    /** @return array<int, array<string, int>> */
    private static function byPath(): array
    {
        if (null === self::$byPath) {
            self::$byPath = [];
            foreach (self::pages() as $id => $byLang) {
                foreach ($byLang as $lang => $r) {
                    self::$byPath[$lang][$r[1]] = $id;
                }
            }
        }

        return self::$byPath;
    }

    /** @return array<string, array<int, array{0: string, 1: int}>> */
    private static function entriesOf(string $source): array
    {
        if (isset(self::$entries[$source])) {
            return self::$entries[$source];
        }
        $file = 'entries.' . self::fileKey($source) . '.php';
        $cached = self::read($file);
        if (null !== $cached) {
            return self::$entries[$source] = $cached;
        }

        return self::$entries[$source] = self::writeEntryCache($source);
    }

    /** @return array<string, array<int, array{0: string, 1: int}>> */
    private static function writeEntryCache(string $source): array
    {
        $out = [];
        try {
            foreach (Sql::factory()->getArray('SELECT entity, language_id, path, article_id FROM ' . self::ENTRY_TABLE . ' WHERE source = ?', [$source]) as $r) {
                $out[(string) $r['entity']][(int) $r['language_id']] = [(string) $r['path'], (int) $r['article_id']];
            }
        } catch (Throwable) {
            return [];
        }
        self::write('entries.' . self::fileKey($source) . '.php', $out);

        return $out;
    }

    private static function fileKey(string $source): string
    {
        return (string) preg_replace('~[^a-z0-9_-]+~i', '_', $source);
    }

    private static function cacheFile(string $name): string
    {
        return Path::cache('routing/' . $name);
    }

    private static function read(string $name): ?array
    {
        if (!Router::config()->cache) {
            return null;
        }
        $file = self::cacheFile($name);
        if (!is_file($file)) {
            return null;
        }
        $data = @include $file;

        return is_array($data) ? $data : null;
    }

    /** Cache-Datei schreiben bzw. löschen (null) */
    private static function write(string $name, ?array $data): void
    {
        if (!Router::config()->cache) {
            return;
        }
        $file = self::cacheFile($name);
        if (null === $data) {
            File::delete($file);
        } else {
            File::put($file, '<?php return ' . var_export($data, true) . ";\n");
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }
    }
}

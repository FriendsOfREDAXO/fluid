<?php

namespace FriendsOfRedaxo\Fluid\Routing;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Language\Language;

use function array_filter;
use function array_map;
use function explode;
use function implode;
use function mb_strtolower;
use function preg_replace;
use function strtr;
use function trim;

/**
 * Gespeicherte Adressen (Tabelle rex_fluid_route, Modell aus KLXM Studio – Core\Pages):
 *
 *  - Jede Seite hat je Sprache einen festen Slug. Er entsteht beim Anlegen aus dem Namen und bleibt beim Umbenennen gleich
 *    (stabile Adressen); ändern lässt er sich in den Seiteneinstellungen.
 *  - path = Slugs der Elternkategorien + eigener Slug, Startseite = ''. Wird nach jeder Strukturänderung neu berechnet
 *    (rebuild); geänderte Pfade online stehender Seiten bekommen automatisch eine 301-Weiterleitung (Redirects::pathsChanged).
 *  - Weitere Sprachen haben ein Präfix (/en/…), das nicht gespeichert wird.
 *  - Auflösen einer Adresse = eine indizierte Abfrage (language_id, path).
 */
final class Routes
{
    public const string TABLE = 'rex_fluid_route';

    /** @var array<int, array<int, array{slug: string, path: string}>>|null */
    private static ?array $cache = null;

    public static function slugify(string $s): string
    {
        $s = strtr(mb_strtolower(trim($s)), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', '’' => '', "'" => '', '´' => '', '`' => '']);
        $s = (string) preg_replace('~[^a-z0-9]+~', '-', $s);

        return trim($s, '-') ?: 'seite';
    }

    /** Pfad ('' = Startseite) einer Seite in einer Sprache – oder null */
    public static function path(int $articleId, int $languageId): ?string
    {
        return self::all()[$articleId][$languageId]['path'] ?? null;
    }

    public static function slug(int $articleId, int $languageId): ?string
    {
        return self::all()[$articleId][$languageId]['slug'] ?? null;
    }

    /** Seite zu einem Pfad (ohne Sprachpräfix, ohne Schrägstriche außen) */
    public static function find(string $path, int $languageId): ?int
    {
        $id = Sql::factory()->getArray('SELECT article_id FROM ' . self::TABLE . ' WHERE language_id = ? AND path = ? AND BINARY path = ? LIMIT 1', [$languageId, $path, $path])[0]['article_id'] ?? null;

        return null === $id ? null : (int) $id;
    }

    /** Wie find(), aber ohne Groß-/Kleinschreibung (für die Weiterleitung auf die kanonische Schreibweise) */
    public static function findInsensitive(string $path, int $languageId): ?int
    {
        $id = Sql::factory()->getArray('SELECT article_id FROM ' . self::TABLE . ' WHERE language_id = ? AND LOWER(path) = ? LIMIT 1', [$languageId, mb_strtolower($path)])[0]['article_id'] ?? null;

        return null === $id ? null : (int) $id;
    }

    /** @return array<int, array<int, array{slug: string, path: string}>> */
    public static function all(): array
    {
        if (null !== self::$cache) {
            return self::$cache;
        }
        $out = [];
        try {
            foreach (Sql::factory()->getArray('SELECT article_id, language_id, slug, path FROM ' . self::TABLE) as $r) {
                $out[(int) $r['article_id']][(int) $r['language_id']] = ['slug' => (string) $r['slug'], 'path' => (string) $r['path']];
            }
        } catch (\Throwable) {
            // Tabelle noch nicht angelegt
        }

        return self::$cache = $out;
    }

    /** Eigenen Slug setzen (Seiteneinstellungen); leer = aus dem Namen. Gibt den tatsächlich vergebenen Slug zurück. */
    public static function setSlug(int $articleId, int $languageId, string $slug): string
    {
        $slug = self::slugify($slug);
        Sql::factory()->setQuery('INSERT INTO ' . self::TABLE . ' (article_id, language_id, slug, path) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE slug = VALUES(slug)', [$articleId, $languageId, $slug, '']);
        self::rebuild();

        return self::slug($articleId, $languageId) ?? $slug;
    }

    /**
     * Pfade aller Seiten neu berechnen (nach Strukturänderungen). Fehlende Slugs entstehen aus dem Namen, gleiche Slugs auf
     * einer Ebene werden durchnummeriert (-2, -3 …). Gelöschte Seiten fallen heraus.
     */
    public static function rebuild(): void
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
                $keep[] = [$id, $lang];
                if (null === $r['route'] || $path !== (string) $r['route'] || $slugs[$id] !== (string) $r['slug']) {
                    $sql->setQuery('INSERT INTO ' . self::TABLE . ' (article_id, language_id, slug, path) VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE slug = VALUES(slug), path = VALUES(path)', [$id, $lang, $slugs[$id], $path]);
                    if (null !== $r['route'] && $path !== (string) $r['route']) {
                        $changes[] = ['id' => $id, 'lang' => $lang, 'old' => (string) $r['route'], 'new' => $path, 'online' => 1 === (int) $r['status']];
                    }
                }
            }
        }
        // Gelöschte Seiten/Sprachen entfernen
        $existing = $sql->getArray('SELECT article_id, language_id FROM ' . self::TABLE);
        $alive = array_flip(array_map(static fn (array $k) => $k[0] . '|' . $k[1], $keep));
        foreach ($existing as $e) {
            if (!isset($alive[$e['article_id'] . '|' . $e['language_id']])) {
                $sql->setQuery('DELETE FROM ' . self::TABLE . ' WHERE article_id = ? AND language_id = ?', [$e['article_id'], $e['language_id']]);
            }
        }
        self::$cache = null;
        Redirects::pathsChanged($changes);
        \FriendsOfRedaxo\Fluid\Search\Search::markDirty();
    }

    /** Sprachpräfix ('' für die Startsprache, sonst '/en') */
    public static function prefix(int $languageId): string
    {
        if (Language::count() < 2 || $languageId === Language::getStartId()) {
            return '';
        }

        return '/' . (Language::get($languageId)?->code ?? (string) $languageId);
    }
}

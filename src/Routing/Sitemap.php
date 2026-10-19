<?php

namespace FriendsOfRedaxo\Fluid\Routing;

use Redaxo\Core\Content\Article;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Language\Language;

use function array_slice;
use function ceil;
use function count;
use function date;
use function is_array;

use function Redaxo\Core\View\escape;

/**
 * sitemap.xml der Hauptadresse bzw. einer Landing-Domain.
 *
 * Seiten: online (samt Eltern), mit gespeicherter Adresse, Canonical auf dieser Domain, nicht die Fehlerseite – mit lastmod
 * und hreflang (inkl. x-default = Startsprache). Ab MAX Adressen: Sitemap-Index (/sitemap.xml) mit Teilen
 * (/sitemap-1.xml …).
 *
 * Bausteine ergänzen, ohne dass das Routing sie kennt:
 *   Sitemap::exclude(fn (int $id, int $lang): bool)            Seite weglassen (z. B. „nicht indexieren“)
 *   Sitemap::children(fn (int $id, int $lang, string $loc): iterable)  weitere Adressen unter einer Seite
 *                                                              (z. B. Detailseiten einer Datenliste): ['loc' => …, 'lastmod' => int|null]
 *   EP FLUID_SITEMAP (Liste aller Einträge, Param landing)       hinzufügen, ändern, entfernen
 */
final class Sitemap
{
    /** Höchstzahl Adressen je Datei (Protokoll: 50 000) */
    public const int MAX = 45000;

    /** @var list<callable(int, int): bool> */
    private static array $excludes = [];

    /** @var list<callable(int, int, string): iterable<array{loc: string, lastmod?: int|null}>> */
    private static array $children = [];

    public static function exclude(callable $filter): void
    {
        self::$excludes[] = $filter;
    }

    public static function children(callable $provider): void
    {
        self::$children[] = $provider;
    }

    /**
     * Alle Einträge der Domain.
     *
     * @return list<array{loc: string, lastmod: int|null, alternates: array<string, string>}>
     */
    public static function entries(?Domain $landing): array
    {
        $origin = $landing ? Router::origin($landing) : null;
        $startLang = Language::getStartId();
        $out = [];
        foreach (Routes::all() as $id => $byLang) {
            if ($id === Article::getNotfoundArticleId() && $id !== Article::getSiteStartArticleId()) {
                continue;
            }
            $pages = [];
            foreach ($byLang as $lang => $_) {
                $a = Article::get($id, $lang);
                if (!$a || !$a->isOnlineIncludingParents() || self::excluded($id, $lang)) {
                    continue;
                }
                $loc = Router::canonical($id, $lang);
                // Nur Adressen dieser Domain (Canonical zeigt auf eine Landing-Domain → steht in deren Sitemap)
                if (null !== $origin ? !str_starts_with($loc, $origin . '/') && $loc !== $origin : self::onLanding($id, $a->path)) {
                    continue;
                }
                $pages[$lang] = [$loc, $a->updateDate > 0 ? $a->updateDate : null];
            }
            $alternates = [];
            if (count($pages) > 1) {
                foreach ($pages as $lang => [$loc]) {
                    $alternates[Language::get($lang)->code ?? (string) $lang] = $loc;
                }
                if (isset($pages[$startLang])) {
                    $alternates['x-default'] = $pages[$startLang][0];
                }
            }
            foreach ($pages as $lang => [$loc, $time]) {
                $out[] = ['loc' => $loc, 'lastmod' => $time, 'alternates' => $alternates];
                foreach (self::$children as $provider) {
                    foreach ($provider($id, $lang, $loc) as $child) {
                        $out[] = ['loc' => (string) $child['loc'], 'lastmod' => $child['lastmod'] ?? null, 'alternates' => []];
                    }
                }
            }
        }

        $out = Extension::dispatch(new ExtensionPoint('FLUID_SITEMAP', $out, ['landing' => $landing]));

        return is_array($out) ? array_values($out) : [];
    }

    /**
     * Inhalt von /sitemap.xml ($part = 0) bzw. /sitemap-{part}.xml; null = diesen Teil gibt es nicht.
     */
    public static function xml(?Domain $landing, int $part = 0): ?string
    {
        $entries = self::entries($landing);
        $parts = (int) ceil(count($entries) / self::MAX);
        if ($parts > 1 && 0 === $part) {
            return self::index($landing, $parts);
        }
        if ($part > 0) {
            if ($part > $parts || $parts < 2) {
                return null;
            }
            $entries = array_slice($entries, ($part - 1) * self::MAX, self::MAX);
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        foreach ($entries as $e) {
            $xml .= '  <url><loc>' . escape($e['loc']) . '</loc>' . (null !== $e['lastmod'] ? '<lastmod>' . date('Y-m-d', $e['lastmod']) . '</lastmod>' : '');
            foreach ($e['alternates'] as $code => $href) {
                $xml .= '<xhtml:link rel="alternate" hreflang="' . escape($code) . '" href="' . escape($href) . '"/>';
            }
            $xml .= "</url>\n";
        }

        return $xml . '</urlset>' . "\n";
    }

    private static function index(?Domain $landing, int $parts): string
    {
        $base = rtrim($landing ? Router::origin($landing) : Router::absolute(Router::base() . '/'), '/');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        for ($i = 1; $i <= $parts; ++$i) {
            $xml .= '  <sitemap><loc>' . escape($base . '/sitemap-' . $i . '.xml') . "</loc></sitemap>\n";
        }

        return $xml . '</sitemapindex>' . "\n";
    }

    private static function excluded(int $id, int $lang): bool
    {
        foreach (self::$excludes as $filter) {
            if ($filter($id, $lang)) {
                return true;
            }
        }

        return false;
    }

    private static function onLanding(int $id, array $ancestors): bool
    {
        $l = Domains::landingFor($id, $ancestors);

        return null !== $l && $l->canonicalOwn;
    }
}

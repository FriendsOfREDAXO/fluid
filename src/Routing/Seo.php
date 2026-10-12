<?php

namespace FriendsOfRedaxo\Fluid\Routing;

use FriendsOfRedaxo\Fluid\FluidAddon;
use FriendsOfRedaxo\Fluid\PageSettings;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Language\Language;

use function count;
use function date;
use function implode;
use function in_array;
use function strtotime;

use function Redaxo\Core\View\escape;

/**
 * Suchmaschinen: sitemap.xml (Hauptadresse bzw. Landing-Domain, mit hreflang), robots.txt und die Angaben im Seitenkopf
 * (Canonical, hreflang). In die Sitemap kommen nur Seiten, die online sind (samt Eltern), nicht auf „nicht indexieren“
 * stehen und deren Canonical auf diese Domain zeigt.
 */
final class Seo
{
    public static function sitemap(?Domain $landing): string
    {
        $noindex = [];
        foreach (Sql::factory()->getArray('SELECT article_id, language_id FROM ' . PageSettings::TABLE . ' WHERE noindex = 1') as $r) {
            $noindex[$r['article_id'] . '|' . $r['language_id']] = true;
        }
        $origin = $landing ? Router::origin($landing) : null;
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        foreach (Routes::all() as $id => $byLang) {
            if ($id === Article::getNotfoundArticleId() && $id !== Article::getSiteStartArticleId()) {
                continue;
            }
            $alternates = [];
            foreach ($byLang as $lang => $_) {
                $a = Article::get($id, $lang);
                if (!$a || !$a->isOnlineIncludingParents() || isset($noindex[$id . '|' . $lang])) {
                    continue;
                }
                $loc = Router::canonical($id, $lang);
                // Nur Adressen dieser Domain (Canonical zeigt auf eine Landing-Domain → steht in deren Sitemap)
                if (null !== $origin ? !str_starts_with($loc, $origin . '/') && $loc !== $origin : self::onLanding($id, $a->path)) {
                    continue;
                }
                $alternates[$lang] = [$loc, (int) strtotime((string) $a->updateDate ?: 'now')];
            }
            foreach ($alternates as $lang => [$loc, $time]) {
                $xml .= '  <url><loc>' . escape($loc) . '</loc><lastmod>' . date('Y-m-d', $time ?: time()) . '</lastmod>';
                if (count($alternates) > 1) {
                    foreach ($alternates as $l => [$href]) {
                        $xml .= '<xhtml:link rel="alternate" hreflang="' . escape(Language::get($l)?->code ?? '') . '" href="' . escape($href) . '"/>';
                    }
                }
                $xml .= "</url>\n";
            }
        }

        return $xml . '</urlset>' . "\n";
    }

    private static function onLanding(int $id, array $ancestors): bool
    {
        $l = Domains::landingFor($id, $ancestors);

        return null !== $l && $l->canonicalOwn;
    }

    /** robots.txt: eigener Text (Website-Einstellungen) oder Standard; Sitemap-Zeile immer */
    public static function robots(): string
    {
        $own = trim((string) FluidAddon::instance()->getConfig('robots', ''));
        $txt = '' !== $own ? $own : "User-agent: *\nDisallow: /redaxo/\nDisallow: /*?fluid_edit=\nDisallow: /*?rex_version=";
        $r = Core::getRequest();

        return $txt . "\n\nSitemap: " . $r->getSchemeAndHttpHost() . Router::base() . "/sitemap.xml\n";
    }

    /** Canonical und hreflang für den Seitenkopf */
    public static function head(Article $article): string
    {
        $h = '';
        if ('' !== ($c = Router::canonical($article->id, $article->languageId))) {
            $h .= '<link rel="canonical" href="' . escape($c) . '">' . "\n";
        }
        $langs = Routes::all()[$article->id] ?? [];
        if (count($langs) > 1) {
            foreach ($langs as $lang => $_) {
                $a = Article::get($article->id, $lang);
                if ($a && $a->isOnline()) {
                    $h .= '<link rel="alternate" hreflang="' . escape(Language::get($lang)?->code ?? '') . '" href="' . escape(Router::canonical($article->id, $lang)) . '">' . "\n";
                }
            }
        }

        return $h;
    }
}

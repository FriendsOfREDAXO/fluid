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
                        $xml .= '<xhtml:link rel="alternate" hreflang="' . escape(Language::get($l)->code ?? '') . '" href="' . escape($href) . '"/>';
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
        $own = trim((string) \FriendsOfRedaxo\Fluid\Settings::get('robots', ''));
        $txt = '' !== $own ? $own : "User-agent: *\nDisallow: /redaxo/\nDisallow: /*?fluid_edit=\nDisallow: /*?rex_version=";
        $r = Core::getRequest();

        return (string) \Redaxo\Core\ExtensionPoint\Extension::dispatch(new \Redaxo\Core\ExtensionPoint\ExtensionPoint('FLUID_ROBOTS',
            $txt . "\n\nSitemap: " . $r->getSchemeAndHttpHost() . Router::base() . "/sitemap.xml\n"));
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
                    $h .= '<link rel="alternate" hreflang="' . escape(Language::get($lang)->code ?? '') . '" href="' . escape(Router::canonical($article->id, $lang)) . '">' . "\n";
                }
            }
        }

        if (!\FriendsOfRedaxo\Fluid\Editing::active()) {
            $h .= '<script type="application/ld+json">' . json_encode(StructuredData::graph($article), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>' . "\n";
        }

        return $h;
    }

    /** schema.org-Knoten der Organisation: Name, Logo, Adresse, Telefon, E-Mail, Geo, Öffnungszeiten, Social Media */
    public static function organizationNode(): array
    {
        $s = static fn (string $k) => trim((string) \FriendsOfRedaxo\Fluid\Settings::get($k, ''));
        $type = $s('schema_type') ?: 'Organization';
        $origin = Router::canonical(Article::getSiteStartArticleId(), Language::getCurrentId());
        $data = ['@type' => $type, '@id' => $origin . '#org', 'name' => \FriendsOfRedaxo\Fluid\Settings::name(), 'url' => $origin];
        if ('' !== $s('logo') && ($m = \Redaxo\Core\MediaPool\Media::get($s('logo')))) {
            $data['logo'] = Router::absolute($m->getUrl());
        }
        if ('' !== $s('street') || '' !== $s('city')) {
            $data['address'] = array_filter(['@type' => 'PostalAddress', 'streetAddress' => $s('street'), 'postalCode' => $s('zip'), 'addressLocality' => $s('city'), 'addressCountry' => $s('country')]);
        }
        if ('' !== $s('phone')) {
            $data['telephone'] = $s('phone');
        }
        if (filter_var($s('email'), FILTER_VALIDATE_EMAIL)) {
            $data['email'] = $s('email');
        }
        if ($geo = \FriendsOfRedaxo\Fluid\Maps\Maps::siteLocation()) {
            $data['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $geo[0], 'longitude' => $geo[1]];
        }
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $spec = [];
        foreach ((array) \FriendsOfRedaxo\Fluid\Settings::get('hours', []) as $r) {
            if (isset($days[(int) ($r['tag'] ?? -1)]) && !empty($r['von']) && !empty($r['bis'])) {
                $slots = !empty($r['pause_von']) && !empty($r['pause_bis']) ? [[$r['von'], $r['pause_von']], [$r['pause_bis'], $r['bis']]] : [[$r['von'], $r['bis']]];
                foreach ($slots as [$o, $c]) {
                    $spec[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $days[(int) $r['tag']], 'opens' => $o, 'closes' => $c];
                }
            }
        }
        if ($spec && 'Organization' !== $type && 'Person' !== $type) {
            $data['openingHoursSpecification'] = $spec;
        }
        $same = array_values(array_filter(array_map(static fn ($x) => (string) ($x['url'] ?? ''), (array) \FriendsOfRedaxo\Fluid\Settings::get('social', [])), static fn ($u) => (bool) preg_match('~^https?://~', $u)));
        if ($same) {
            $data['sameAs'] = $same;
        }

        return $data;
    }
}

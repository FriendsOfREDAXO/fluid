<?php

namespace FriendsOfRedaxo\Fluid\Seo;

use FriendsOfRedaxo\Fluid\Routing\Router;
use FriendsOfRedaxo\Fluid\Routing\Routes;
use Redaxo\Core\Content\Article;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Language\Language;

use function count;

use function Redaxo\Core\View\escape;

/**
 * Angaben für Suchmaschinen im Seitenkopf: Canonical, hreflang, schema.org (StructuredData) – aus den Seiten- und
 * Website-Einstellungen von fluid. Adressen kommen vom Routing (Router::canonical). Bausteine ergänzen eigene Angaben über
 * FLUID_SEO_HEAD (HTML im Kopf, z. B. RSS-Feed) und FLUID_STRUCTURED_DATA (Knoten im @graph).
 */
final class Head
{
    /** Canonical und hreflang für den Seitenkopf */
    public static function head(Article $article): string
    {
        $h = '';
        if ('' !== ($c = \FriendsOfRedaxo\Fluid\PageSettings::overridden('canonical') ?? Router::canonical($article->id, $article->languageId))) {
            $h .= '<link rel="canonical" href="' . escape($c) . '">' . "\n";
        }
        $langs = Routes::all()[$article->id] ?? [];
        if (count($langs) > 1 && null === \FriendsOfRedaxo\Fluid\PageSettings::overridden('canonical')) {
            foreach ($langs as $lang => $_) {
                $a = Article::get($article->id, $lang);
                if ($a && $a->isOnline()) {
                    $h .= '<link rel="alternate" hreflang="' . escape(Language::get($lang)->code ?? '') . '" href="' . escape(Router::canonical($article->id, $lang)) . '">' . "\n";
                }
            }
        }

        // Weitere Angaben der Bausteine (z. B. RSS-Feed des Blogs)
        $h .= (string) Extension::dispatch(new ExtensionPoint('FLUID_SEO_HEAD', '', ['article' => $article]));

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

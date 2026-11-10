<?php

namespace FriendsOfRedaxo\Fluid\Data;

use FriendsOfRedaxo\Fluid\Routing\Sitemap;
use FriendsOfRedaxo\Fluid\Routing\Router;
use FriendsOfRedaxo\Fluid\BlockModule;
use FriendsOfRedaxo\Fluid\Routing\RouteMatch;
use FriendsOfRedaxo\Fluid\Routing\Routes;
use FriendsOfRedaxo\Fluid\Routing\Seo\PageMeta;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Database\Sql;

/**
 * Detailansicht der Datenliste mit schöner Adresse: /{seite}/{titel}-{id} statt ?eintrag={id}.
 *
 * Der Router fragt match() für Adressen, die keine Seite sind: Endet die Adresse auf „-{Zahl}“ und hat die Seite davor
 * eine Datenliste mit Detailansicht, zeigt er diese Seite mit dem Eintrag. Alte Links ?eintrag={id} leiten per 301 um.
 */
final class Detail
{
    /**
     * Beim Routing anmelden: Detailansicht /seite/titel-ID (falscher Titel-Teil → 301), alter Link ?eintrag=ID → 301,
     * Detailseiten in der sitemap.xml.
     */
    public static function boot(): void
    {
        Router::resolver(static function (string $full, int $lang): ?RouteMatch {
            if (null === ($dm = self::match($full, $lang))) {
                return null;
            }
            [$pid, $eid] = $dm;
            $cfg = (array) self::config($pid, $lang);
            $title = self::title($cfg, $eid);
            if (null === $title) {
                return null;
            }
            // dieselbe Sache in anderen Sprachen: Seite mit Datenliste, Eintrag erfüllt dort die Bedingung
            $alternates = [];
            foreach (Language::getAll() as $l) {
                $c = $l->id === $lang ? $cfg : self::config($pid, $l->id);
                $t = $l->id === $lang ? $title : ($c ? self::title($c, $eid) : null);
                if (null !== $t && '' !== ($page = Router::url($pid, $l->id))) {
                    $alternates[$l->id] = self::url($page, $t, $eid);
                }
            }

            // Titel lassen sich ändern (auch zurück): Weiterleitung auf die richtige Adresse nicht zwischenspeichern
            return new RouteMatch($pid, $lang, [self::PARAM => $eid], self::url(Router::url($pid, $lang), $title, $eid),
                cacheRedirect: false, meta: self::meta($cfg, $eid, $title), alternates: $alternates);
        });
        Router::onPage(static function (int $id, int $lang, array $query): void {
            if (isset($query[self::PARAM]) && ($cfg = self::config($id, $lang)) && null !== ($t = self::title($cfg, (int) $query[self::PARAM]))) {
                $eid = (int) $query[self::PARAM];
                unset($query[self::PARAM]);
                Router::redirectTo(self::url(Router::url($id, $lang, $query), $t, $eid), 301, false);
            }
        });
        Sitemap::children(static function (int $id, int $lang, string $loc): iterable {
            if ($cfg = self::config($id, $lang)) {
                foreach (self::entries($cfg) as [$eid, $etitle]) {
                    yield ['loc' => self::url($loc, $etitle, $eid)];
                }
            }
        });
    }

    public const string PARAM = 'eintrag';

    /** Einstellungen der ersten Datenliste mit Detailansicht auf einer Seite (Live-Fassung) */
    public static function config(int $articleId, int $languageId): ?array
    {
        static $cache = [];
        $key = $articleId . '/' . $languageId;
        if (!\array_key_exists($key, $cache)) {
            $cache[$key] = null;
            $rows = Sql::factory()->getArray("SELECT data FROM rex_article_slice WHERE article_id = ? AND language_id = ? AND revision = ? AND status = 1 AND module LIKE '%.datalist' ORDER BY priority",
                [$articleId, $languageId, ArticleRevision::LIVE]);
            foreach ($rows as $r) {
                $d = BlockModule::stored($r['data'])['values'];
                if (!empty($d['detail']) && '' !== (string) ($d['table'] ?? '')) {
                    $cache[$key] = $d + ['_language' => $languageId];   // Sprache der Seite (Feld „Sprache“ der Tabelle)
                    break;
                }
            }
        }

        return $cache[$key];
    }

    /** Letzter Teil der Adresse: „titel-des-eintrags-12“ */
    public static function slug(string $title, int $id): string
    {
        $s = substr(Routes::slugify($title), 0, 80);

        return ('' !== trim($s, '-') ? rtrim($s, '-') . '-' : '') . $id;
    }

    /** Adresse eines Eintrags unter der Adresse seiner Seite – im Adressformat des Routings (/daten/titel-5.html) */
    public static function url(string $pageUrl, string $title, int $id): string
    {
        [$path, $query] = array_pad(explode('?', $pageUrl, 2), 2, '');
        $format = Router::config()->format;

        return $format->strip($path) . '/' . $format->append(self::slug($title, $id)) . ('' !== $query ? '?' . $query : '');
    }

    /** Meta-Angaben eines Eintrags für den Seitenkopf: Titel, Beschreibung (Textfeld), Bild */
    public static function meta(array $config, int $id, string $title): PageMeta
    {
        $t = YFormBridge::table((string) $config['table']);
        $row = $t ? YFormBridge::row($t, $id, YFormBridge::field($t, (string) ($config['filter_field'] ?? '')), trim((string) ($config['filter_value'] ?? '')), $config['_language'] ?? null) : null;
        if (!$t || !$row) {
            return new PageMeta(title: $title);
        }
        $textF = YFormBridge::field($t, (string) ($config['text_field'] ?? ''), [\Yakamara\YForm\Field\Textarea::class]);
        $imageF = YFormBridge::field($t, (string) ($config['image_field'] ?? ''), [\Yakamara\YForm\Field\Media::class]);
        $text = $textF ? trim((string) preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags(YFormBridge::text($row, $textF))))) : '';

        return new PageMeta(
            title: '' !== $title ? $title : null,
            description: '' !== $text ? mb_strimwidth($text, 0, 160, '…') : null,
            image: $imageF && '' !== ($f = YFormBridge::file($row, $imageF)) ? $f : null,
            type: 'article',
        );
    }

    /** Adresse ohne Seite: [Seiten-ID, Eintrags-ID], wenn sie auf eine Detailansicht passt */
    public static function match(string $full, int $languageId): ?array
    {
        if (!preg_match('~^(?:(.*)/)?(?:[a-z0-9-]*-)?(\d+)$~', $full, $m)) {
            return null;
        }
        $page = '' === $m[1] ? Article::getSiteStartArticleId() : Routes::find($m[1], $languageId);

        return null !== $page && null !== self::config($page, $languageId) ? [$page, (int) $m[2]] : null;
    }

    /** Alle Einträge einer Datenliste mit Detailansicht: [[id, titel], …] (Sitemap) */
    public static function entries(array $config, int $limit = 1000): array
    {
        $t = YFormBridge::table((string) $config['table']);
        if (!$t) {
            return [];
        }
        $f = YFormBridge::field($t, (string) ($config['title_field'] ?? ''), [\Yakamara\YForm\Field\Text::class]);
        $rows = YFormBridge::rows($t, ['filter' => YFormBridge::field($t, (string) ($config['filter_field'] ?? '')), 'value' => trim((string) ($config['filter_value'] ?? '')), 'limit' => $limit, 'language' => $config['_language'] ?? null]);

        return array_map(static fn ($r) => [(int) $r->id, $f ? YFormBridge::text($r, $f) : ''], $rows);
    }

    /** Titel eines Eintrags für die Adresse (Titelfeld der Datenliste) */
    public static function title(array $config, int $id): ?string
    {
        $t = YFormBridge::table((string) $config['table']);
        if (!$t) {
            return null;
        }
        $f = YFormBridge::field($t, (string) ($config['title_field'] ?? ''), [\Yakamara\YForm\Field\Text::class]);
        $row = YFormBridge::row($t, $id, YFormBridge::field($t, (string) ($config['filter_field'] ?? '')), trim((string) ($config['filter_value'] ?? '')), $config['_language'] ?? null);

        return $row ? ($f ? YFormBridge::text($row, $f) : '') : null;
    }
}

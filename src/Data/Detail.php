<?php

namespace FriendsOfRedaxo\Fluid\Data;

use FriendsOfRedaxo\Fluid\BlockModule;
use FriendsOfRedaxo\Fluid\Routing\Routes;
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
    public const string PARAM = 'eintrag';

    /** Einstellungen der ersten Datenliste mit Detailansicht auf einer Seite (Live-Fassung) */
    public static function config(int $articleId, int $languageId): ?array
    {
        static $cache = [];
        $key = $articleId . '/' . $languageId;
        if (!\array_key_exists($key, $cache)) {
            $cache[$key] = null;
            $rows = Sql::factory()->getArray("SELECT value1 FROM rex_article_slice WHERE article_id = ? AND language_id = ? AND revision = ? AND status = 1 AND module LIKE '%.datalist' ORDER BY priority",
                [$articleId, $languageId, ArticleRevision::LIVE]);
            foreach ($rows as $r) {
                $d = BlockModule::decode((string) $r['value1']);
                if (!empty($d['detail']) && '' !== (string) ($d['table'] ?? '')) {
                    $cache[$key] = $d;
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

    public static function url(string $pageUrl, string $title, int $id): string
    {
        [$path, $query] = array_pad(explode('?', $pageUrl, 2), 2, '');

        return rtrim($path, '/') . '/' . self::slug($title, $id) . ('' !== $query ? '?' . $query : '');
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
        $rows = YFormBridge::rows($t, ['filter' => YFormBridge::field($t, (string) ($config['filter_field'] ?? '')), 'value' => trim((string) ($config['filter_value'] ?? '')), 'limit' => $limit]);

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
        $row = YFormBridge::row($t, $id, YFormBridge::field($t, (string) ($config['filter_field'] ?? '')), trim((string) ($config['filter_value'] ?? '')));

        return $row ? ($f ? YFormBridge::text($row, $f) : '') : null;
    }
}

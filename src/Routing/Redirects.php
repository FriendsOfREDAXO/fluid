<?php

namespace FriendsOfRedaxo\Fluid\Routing;

use Normalizer;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Filesystem\Url;
use Throwable;

use function array_fill;
use function array_pad;
use function count;
use function explode;
use function implode;
use function in_array;
use function mb_check_encoding;
use function mb_substr;
use function preg_match;
use function preg_replace;
use function rtrim;
use function sha1;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;
use function trim;

/**
 * Weiterleitungen (Tabelle rex_fluid_redirect) und 404-Protokoll (rex_fluid_404) – Regeln aus KLXM Studio (Core\Redirects):
 *
 *  - Quelle: Pfad ohne Domain, optional mit ?query, optional mit * am Ende (Präfix). Ziel: redaxo://ID[#anker], /pfad oder
 *    https://…; „*“ im Ziel übernimmt den Rest. Code 301, 302 oder 410 (gelöscht).
 *  - Greift nur, wenn keine Seite passt. Vergleich ohne Schrägstrich am Ende, ASCII-Groß/Klein und Prozent-Kodierung egal.
 *  - Exakte Quelle vor Platzhalter, längster Präfix gewinnt. Query der Anfrage bleibt erhalten. Keine Ketten, keine Schleifen.
 *  - redaxo://ID folgt umbenannten und verschobenen Seiten automatisch; geänderte Pfade legen „alter Pfad → redaxo://ID“ an.
 *  - 404-Protokoll: die letzten MAX_404 verschiedenen Pfade mit Anzahl – ohne IP, ohne Referrer.
 */
final class Redirects
{
    public const string TABLE = 'rex_fluid_redirect';
    public const string TABLE_404 = 'rex_fluid_404';
    public const array CODES = [301, 302, 410];
    public const int MAX_404 = 200;
    /** Anfragen, die nie ins 404-Protokoll kommen (Angriffs-Scans, Dateien) */
    private const string NO_LOG = '~^/(wp-|wordpress|xmlrpc|\.env|\.git|\.well-known|cgi-bin|phpmyadmin|pma|vendor/|assets/|media/|apple-touch-icon|browserconfig\.xml|favicon)|\.(map|php\d?|asp|aspx|jsp|cgi|sql|bak|zip|gz|ini|log)$~i';

    public static function normalize(string $in): string
    {
        $s = trim($in);
        if ('' === $s) {
            return '';
        }
        if (preg_match('~^(https?:)?//~i', $s)) {
            $p = parse_url(str_starts_with($s, '//') ? 'http:' . $s : $s);
            if (!$p) {
                return '';
            }
            $s = ($p['path'] ?? '/') . (isset($p['query']) && '' !== $p['query'] ? '?' . $p['query'] : '');
        }
        $s = explode('#', $s, 2)[0];
        [$path, $query] = array_pad(explode('?', $s, 2), 2, null);
        $dec = rawurldecode((string) $path);
        $path = mb_check_encoding($dec, 'UTF-8') ? $dec : (string) $path;
        $path = (string) preg_replace('~[\x00-\x1F\x7F]~', '', $path);
        $path = '/' . ltrim((string) preg_replace('~/{2,}~', '/', $path), '/');
        if (class_exists(Normalizer::class)) {
            $path = Normalizer::normalize($path, Normalizer::FORM_C) ?: $path;
        }
        $query = null !== $query ? trim($query) : '';

        return $path . ('' !== $query ? '?' . $query : '');
    }

    /** Vergleichsschlüssel: ohne Schrägstrich am Ende (außer „/“), ASCII-Kleinbuchstaben */
    public static function key(string $normalized): string
    {
        [$path, $query] = array_pad(explode('?', $normalized, 2), 2, '');
        if ('/' !== $path) {
            $path = rtrim($path, '/');
        }

        return strtolower('' === $path ? '/' : $path) . ('' !== $query ? '?' . strtolower($query) : '');
    }

    public static function isWildcard(string $source): bool
    {
        return !str_contains($source, '?') && str_ends_with($source, '*');
    }

    /**
     * Passende Regel: [regel, rest] oder null (reine Funktion). Exakt vor Platzhalter, längster Präfix gewinnt.
     *
     * @param list<array<string, mixed>> $rules
     */
    public static function pick(array $rules, string $path, string $query = ''): ?array
    {
        $trail = '/' !== $path && str_ends_with($path, '/');
        $base = '/' === $path ? '/' : rtrim($path, '/');
        $keys = '' !== $query ? [self::key($base . '?' . $query), self::key($base)] : [self::key($base)];
        foreach ($keys as $k) {
            foreach ($rules as $r) {
                if ((int) ($r['active'] ?? 1) && !(int) ($r['wildcard'] ?? 0) && self::key(self::normalize((string) $r['source'])) === $k) {
                    return [$r, ''];
                }
            }
        }
        $best = null;
        $bestLen = -1;
        $hay = strtolower($base) . '/';
        foreach ($rules as $r) {
            if (!(int) ($r['active'] ?? 1) || !(int) ($r['wildcard'] ?? 0)) {
                continue;
            }
            $prefix = strtolower(substr(self::normalize((string) $r['source']), 0, -1));
            if ('' === $prefix || !str_starts_with($hay, $prefix) || strlen($prefix) <= $bestLen) {
                continue;
            }
            $best = $r;
            $bestLen = strlen($prefix);
        }
        if (null === $best) {
            return null;
        }
        $rest = rtrim(substr($base . '/', $bestLen), '/');
        if ('' !== $rest && $trail) {
            $rest .= '/';
        }

        return [$best, $rest];
    }

    /** @return array{rule: array<string, mixed>, code: int, location: string}|null */
    public static function match(string $path, string $query = ''): ?array
    {
        $path = self::normalize($path);
        if ('' === $path) {
            return null;
        }
        $base = explode('?', $path, 2)[0];
        $trimmed = '/' === $base ? '/' : rtrim($base, '/');
        $hashes = [sha1(self::key($trimmed))];
        if ('' !== $query) {
            array_unshift($hashes, sha1(self::key($trimmed . '?' . $query)));
        }
        try {
            $rules = Sql::factory()->getArray('SELECT * FROM ' . self::TABLE . ' WHERE active = 1 AND (source_hash IN (' . implode(',', array_fill(0, count($hashes), '?')) . ') OR wildcard = 1) ORDER BY id', $hashes);
        } catch (Throwable) {
            return null;
        }
        $picked = self::pick($rules, $base, $query);
        if (!$picked) {
            return null;
        }
        [$rule, $rest] = $picked;
        $code = in_array((int) $rule['code'], self::CODES, true) ? (int) $rule['code'] : 301;
        if (410 === $code) {
            return ['rule' => $rule, 'code' => 410, 'location' => ''];
        }
        $loc = self::targetUrl((string) $rule['target'], $rest);
        if (null === $loc) {
            return null;
        }
        if (!str_contains((string) $rule['source'], '?') && '' !== $query) {
            [$u, $frag] = array_pad(explode('#', $loc, 2), 2, null);
            $loc = $u . (str_contains($u, '?') ? '&' : '?') . $query . (null !== $frag ? '#' . $frag : '');
        }
        // Schleife (Ziel = Quelle): keine Weiterleitung
        $locPath = preg_match('~^https?://~i', $loc) ? (string) parse_url($loc, PHP_URL_PATH) : explode('?', explode('#', $loc, 2)[0], 2)[0];
        if (self::key(self::normalize($locPath)) === self::key($trimmed)) {
            return null;
        }

        return ['rule' => $rule, 'code' => $code, 'location' => $loc];
    }

    /** Zieladresse oder null (Seite fehlt bzw. ist offline) */
    public static function targetUrl(string $target, string $rest = ''): ?string
    {
        $t = trim($target);
        if (preg_match('~^redaxo://(\d+)(#[\w-]+)?$~', $t, $m)) {
            $a = Article::get((int) $m[1]);

            return $a && $a->isOnline() ? Url::article($a->id) . ($m[2] ?? '') : null;
        }
        if (preg_match('~^https?://~i', $t)) {
            return str_replace('*', $rest, $t);
        }
        if (str_starts_with($t, '/')) {
            return Router::base() . str_replace('*', $rest, $t);
        }

        return null;
    }

    public static function hit(int $id): void
    {
        try {
            Sql::factory()->setQuery('UPDATE ' . self::TABLE . ' SET hits = hits + 1, last_hit = NOW() WHERE id = ?', [$id]);
        } catch (Throwable) {
            // Zählen ist nie wichtiger als die Weiterleitung selbst
        }
    }

    /**
     * Geänderte Pfade (Routes::rebuild): neue Adresse aus exakten Regeln entfernen (echte Seite geht vor), alte Adresse
     * online stehender Seiten → redaxo://ID (vorhandene Regel derselben Quelle übernimmt das Ziel – keine Ketten).
     *
     * @param list<array{id: int, lang: int, old: string, new: string, online: bool}> $changes
     */
    public static function pathsChanged(array $changes): void
    {
        $sql = Sql::factory();
        foreach ($changes as $c) {
            $prefix = Routes::prefix($c['lang']);
            $new = self::normalize($prefix . '/' . $c['new']);
            $sql->setQuery('DELETE FROM ' . self::TABLE . ' WHERE source_hash = ? AND wildcard = 0', [sha1(self::key($new))]);
            if (!$c['online'] || '' === $c['old'] || $c['old'] === $c['new']) {
                continue;
            }
            $old = self::normalize($prefix . '/' . $c['old']);
            $target = 'redaxo://' . $c['id'];
            $row = $sql->getArray('SELECT id FROM ' . self::TABLE . ' WHERE source_hash = ? AND wildcard = 0 LIMIT 1', [sha1(self::key($old))])[0] ?? null;
            if ($row) {
                $sql->setQuery('UPDATE ' . self::TABLE . ' SET target = ?, code = 301, active = 1, updated = NOW() WHERE id = ?', [$target, $row['id']]);
                continue;
            }
            self::save(['source' => $old, 'target' => $target, 'code' => 301, 'note' => mb_substr('Automatisch: Adresse geändert zu ' . $prefix . '/' . $c['new'], 0, 190), 'origin' => 'auto']);
        }
    }

    /** Regel anlegen oder ändern (id > 0); gibt die ID zurück */
    public static function save(array $r, int $id = 0): int
    {
        $source = self::normalize((string) $r['source']);
        $wild = self::isWildcard($source);
        $values = [
            'source' => $source, 'source_hash' => sha1(self::key($wild ? $source : $source)), 'wildcard' => $wild ? 1 : 0,
            'target' => trim((string) $r['target']), 'code' => in_array((int) ($r['code'] ?? 301), self::CODES, true) ? (int) $r['code'] : 301,
            'note' => mb_substr(trim((string) ($r['note'] ?? '')), 0, 190), 'origin' => (string) ($r['origin'] ?? 'manual'), 'active' => (int) ($r['active'] ?? 1),
        ];
        $sql = Sql::factory()->setTable(self::TABLE)->setValues($values);
        if ($id > 0) {
            $sql->setWhere(['id' => $id])->setRawValue('updated', 'NOW()')->update();

            return $id;
        }
        $sql->setRawValue('created', 'NOW()')->setRawValue('updated', 'NOW()')->insert();

        return (int) $sql->getLastId();
    }

    /** 404 merken (ohne IP); begrenzt auf MAX_404 verschiedene Pfade */
    public static function log404(string $path): void
    {
        if (preg_match(self::NO_LOG, $path)) {
            return;
        }
        try {
            $sql = Sql::factory();
            $hash = sha1(self::key($path));
            $sql->setQuery('UPDATE ' . self::TABLE_404 . ' SET hits = hits + 1, last_seen = NOW() WHERE path_hash = ?', [$hash]);
            if (0 === $sql->getRows()) {
                $sql->setQuery('INSERT INTO ' . self::TABLE_404 . ' (path, path_hash, hits, first_seen, last_seen) VALUES (?, ?, 1, NOW(), NOW())', [mb_substr($path, 0, 500), $hash]);
                $sql->setQuery('DELETE FROM ' . self::TABLE_404 . ' WHERE id NOT IN (SELECT id FROM (SELECT id FROM ' . self::TABLE_404 . ' ORDER BY last_seen DESC LIMIT ' . self::MAX_404 . ') k)');
            }
        } catch (Throwable) {
            // Protokoll ist nie wichtiger als die Seite
        }
    }
}

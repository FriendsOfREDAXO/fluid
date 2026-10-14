<?php

namespace FriendsOfRedaxo\Fluid;

use Redaxo\Core\Filesystem\Path;

/**
 * Präsenz der Redaktion: wer arbeitet gerade wo – aus KLXM Studio (Core\Presence, MIT), unverändert bis auf die Ablage.
 * Bewusst klein und ohne Bezug zu Artikeln/Datensätzen – kennt nur die Schlüssel site/type/id/user und Zeitstempel.
 * Die Anbindung an fluid (Artikel, Rechte, Endpunkt, Leiste) steckt in PresenceApi.
 *
 *   Presence::beat(['site' => 'default', 'type' => 'article', 'id' => '1:12', 'user' => '7'], ['name' => 'Anna B.', 'label' => 'Startseite', 'url' => '/?fluid_edit=1']);
 *   Presence::leave($key);                 Presence::list(['site' => 'default', 'type' => 'article', 'id' => '1:12']);
 *
 * Ablage: Cache des AddOns (presence/presence.json, eine Datei für die Installation),
 * Schreiben unter Dateisperre. Einträge ohne Meldung seit TTL Sekunden fallen heraus (Browser melden sich alle ~15 s).
 * Keine Historie: nur Name/Avatar, Ort und Zeitpunkt der letzten Meldung – nichts wird protokolliert.
 * Transport für schnelle Änderungen: Live, Kanal „presence:{site}“ (Version steigt beim Kommen und Gehen).
 */
final class Presence
{
    public const TTL = 45;
    public const KEYS = ['site', 'type', 'id', 'user'];

    private static function file(): string
    {
        return Path::addonCache('fluid', 'presence/presence.json');
    }

    /** Eintrag melden/auffrischen. $meta: name, initials, avatar, label, url (frei wählbare Anzeige-Angaben). Ergebnis: neu? */
    public static function beat(array $who, array $meta = [], ?int $now = null): bool
    {
        $now ??= time();
        $k = self::key($who);
        $new = false;
        self::edit(function (array $all) use ($k, $who, $meta, $now, &$new): array {
            $cur = $all[$k] ?? null;
            $new = $cur === null;
            $all[$k] = array_intersect_key($who, array_flip(self::KEYS)) + array_map(fn($v) => mb_substr((string) $v, 0, 300), $meta)
                + ['since' => (int) ($cur['since'] ?? $now), 'at' => $now];
            $all[$k]['since'] = (int) ($cur['since'] ?? $now);
            $all[$k]['at'] = $now;
            return $all;
        }, $now);
        if ($new) Live::touch('presence:' . $who['site']);
        return $new;
    }

    /** Abmelden (Seite verlassen) */
    public static function leave(array $who): void
    {
        $k = self::key($who);
        $gone = false;
        self::edit(function (array $all) use ($k, &$gone): array {
            $gone = isset($all[$k]);
            unset($all[$k]);
            return $all;
        });
        if ($gone) Live::touch('presence:' . $who['site']);
    }

    /**
     * Aktive Einträge, gefiltert nach beliebigen Schlüsseln (z. B. ['site' => 'default'] oder ['site' => …, 'type' => 'page', 'id' => '12']),
     * neueste Meldung zuerst. Abgelaufene fallen weg.
     * @return list<array>
     */
    public static function list(array $filter = [], ?int $now = null): array
    {
        $now ??= time();
        $out = [];
        foreach (self::read() as $e) {
            if ((int) ($e['at'] ?? 0) < $now - self::TTL) continue;
            foreach ($filter as $fk => $fv) if ((string) ($e[$fk] ?? '') !== (string) $fv) continue 2;
            $out[] = $e;
        }
        usort($out, fn($a, $b) => (int) $b['at'] <=> (int) $a['at']);
        return $out;
    }

    public static function key(array $who): string
    {
        foreach (self::KEYS as $k) if (!isset($who[$k]) || (string) $who[$k] === '') throw new \InvalidArgumentException("Presence: Schlüssel „{$k}“ fehlt.");
        return implode('|', array_map(fn($k) => str_replace('|', '/', (string) $who[$k]), self::KEYS));
    }

    private static function read(): array
    {
        $raw = @file_get_contents(self::file());
        $d = $raw ? json_decode($raw, true) : null;
        return is_array($d) ? $d : [];
    }

    /** Lesen-Ändern-Schreiben unter Sperre; räumt Abgelaufenes mit auf */
    private static function edit(callable $fn, ?int $now = null): void
    {
        $now ??= time();
        $f = self::file();
        if (!is_dir(dirname($f))) @mkdir(dirname($f), 0o775, true);
        $h = @fopen($f, 'c+');
        if (!$h) return;
        try {
            flock($h, LOCK_EX);
            $raw = stream_get_contents($h);
            $all = $raw ? (json_decode($raw, true) ?: []) : [];
            $all = array_filter($all, fn($e) => is_array($e) && (int) ($e['at'] ?? 0) >= $now - self::TTL);
            $all = $fn($all);
            ftruncate($h, 0);
            rewind($h);
            fwrite($h, json_encode($all, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            fflush($h);
        } finally {
            flock($h, LOCK_UN);
            fclose($h);
        }
    }

    // ------------------------------------------------------------------ Selbsttest (Status-Seite)

    /** @return array{ok: int, fails: list<string>} */
    public static function selftest(): array
    {
        $ok = 0; $fails = [];
        $eq = function (string $what, mixed $got, mixed $want) use (&$ok, &$fails): void {
            if ($got === $want) { $ok++; return; }
            $fails[] = $what . ': erwartet ' . var_export($want, true) . ', erhalten ' . var_export($got, true);
        };
        $site = 'selftest-' . bin2hex(random_bytes(3));
        $a = ['site' => $site, 'type' => 'page', 'id' => '1', 'user' => 'a'];
        $b = ['site' => $site, 'type' => 'page', 'id' => '1', 'user' => 'b'];
        $now = time();
        $eq('erste Meldung ist neu', self::beat($a, ['name' => 'Anna'], $now - 20), true);
        $eq('zweite Meldung derselben Person ist nicht neu', self::beat($a, ['name' => 'Anna'], $now), false);
        self::beat($b, ['name' => 'Ben'], $now);
        $eq('zwei Personen auf der Seite', count(self::list(['site' => $site, 'type' => 'page', 'id' => '1'], $now)), 2);
        $eq('„seit“ bleibt beim Auffrischen', self::list(['site' => $site, 'user' => 'a'], $now)[0]['since'] ?? null, $now - 20);
        $eq('Filter nach Website', count(self::list(['site' => $site . '-x'], $now)), 0);
        $eq('abgelaufen nach TTL', count(self::list(['site' => $site], $now + self::TTL + 1)), 0);
        self::leave($b);
        $eq('Abmelden entfernt', count(self::list(['site' => $site], $now)), 1);
        self::leave($a);
        $eq('fehlender Schlüssel wird abgewiesen', (function () { try { self::key(['site' => 'x']); return false; } catch (\InvalidArgumentException) { return true; } })(), true);
        $eq('Schlüssel ohne Trennzeichen-Missbrauch', self::key(['site' => 'a|b', 'type' => 't', 'id' => '1', 'user' => 'u']), 'a/b|t|1|u');
        return ['ok' => $ok, 'fails' => $fails];
    }
}

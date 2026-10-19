<?php

namespace FriendsOfRedaxo\Fluid\Maps;

use FriendsOfRedaxo\Fluid\Routing\Router;
use Generator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Redaxo\Core\Core;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Security\BackendLogin;

use function function_exists;
use function in_array;
use function is_callable;
use function is_dir;
use function is_file;
use function strlen;

/**
 * Eigener Proxy für Dienste Dritter (aus KLXM Studio – Core\Proxy): Besucher laden Karten-Kacheln, Stile und Schriften
 * über /proxy/{quelle}/… vom eigenen Server – ihr Browser verbindet sich nie mit Dritten (keine Einwilligung, keine Cookies).
 *
 * Quelle: upstream (https), allow (Regex der Pfade), types, ttl, rewrite (Upstream-Adressen in JSON/CSS umschreiben),
 * guard (z. B. Kartengebiet), public (false = nur serverseitig über http()), max (Bytes).
 * Cache: Cache-Ordner des AddOns (gzip für Text/Protobuf), Nachladen je Besucher begrenzt (Hash der IP, nicht gespeichert).
 */
final class Proxy
{
    /** Eigener Proxy für Karten (Kacheln, Stile, Schriften) unter /proxy/{dienst}/… – Besucher verbinden sich nie mit Dritten */
    public static function boot(): void
    {
        Router::endpoint('/proxy/*', static function (string $path, string $rest): void {
            if (preg_match('~^([a-z][a-z0-9_-]{1,30})(/.*)?$~', $rest, $m)) {
                self::handle($m[1], (string) ($m[2] ?? ''));
            }
        });
    }

    private const array DEFAULT_TYPES = ['image/', 'application/json', 'application/x-protobuf', 'application/vnd.mapbox-vector-tile',
        'application/octet-stream', 'font/', 'application/font', 'text/css', 'application/geo+json'];
    private const array COMPRESS = ['application/json', 'application/x-protobuf', 'application/vnd.mapbox-vector-tile', 'application/octet-stream', 'text/', 'application/geo+json'];
    /** Nachladen beim Anbieter: höchstens so viele Abrufe je Besucher in 10 Minuten (Schutz als freier Kachelserver) */
    private const int RATE = 2000;

    /** @var array<string, array<string, mixed>> */
    private static array $sources = [];
    private static bool $booted = false;

    public static function register(string $key, array $def): void
    {
        if (!preg_match('~^[a-z][a-z0-9_-]{1,30}$~', $key) || !preg_match('~^https://[a-z0-9.-]+(:\d+)?(/.*)?$~i', (string) ($def['upstream'] ?? ''))) {
            throw new InvalidArgumentException("Proxy-Quelle „{$key}“: Name oder upstream (https://…) ungültig.");
        }
        $def['upstream'] = rtrim($def['upstream'], '/');
        self::$sources[$key] = $def + ['label' => $key, 'public' => true, 'rewrite' => false, 'ttl' => 86400, 'max' => 5 * 1024 * 1024];
    }

    /** @return array<string, array<string, mixed>> */
    public static function sources(): array
    {
        if (!self::$booted) {
            self::$booted = true;
            Maps::registerProxy();
        }

        return self::$sources;
    }

    /** Öffentliche, absolute Adresse (MapLibre-Stile brauchen absolute URLs) */
    public static function url(string $key, string $path = ''): string
    {
        $r = Core::getRequest();

        return $r->getSchemeAndHttpHost() . Router::base() . '/proxy/' . $key . ('' !== $path ? '/' . ltrim($path, '/') : '');
    }

    /** /proxy/{key}/{pfad} beantworten (Router, vor dem Rendern) */
    public static function handle(string $key, string $path): never
    {
        $src = self::sources()[$key] ?? null;
        $path = ltrim($path, '/');
        if (!$src || empty($src['public']) || str_contains($path, '..') || !preg_match('~^[\w.\-/@+, ]*$~u', $path)
            || (!empty($src['allow']) && !preg_match('~' . str_replace('~', '\~', $src['allow']) . '~', $path))) {
            self::out('', 'text/plain', 404, 3600);
        }
        // Angemeldete Redaktion darf überall hin (z. B. neuen Ort wählen)
        if (isset($src['guard']) && is_callable($src['guard']) && !BackendLogin::hasSession() && !($src['guard'])($path)) {
            self::out('', 'text/plain', 204, 3600);
        }
        $file = self::cacheFile($key, $path);
        $meta = is_file("$file.json") ? (json_decode((string) file_get_contents("$file.json"), true) ?: null) : null;
        $fresh = $meta && (time() - (int) $meta['fetched']) < (int) $meta['ttl'];
        $state = 'HIT';
        if (!$fresh) {
            if (self::tooMany()) {
                if (!$meta) {
                    self::out('', 'text/plain', 429, 60);
                }
                $state = 'STALE';
            } else {
                $res = self::fetch($src, $src['upstream'] . '/' . implode('/', array_map('rawurlencode', explode('/', $path))));
                if ($res && 200 === $res['status']) {
                    $meta = self::store($key, $path, $src, $res);
                    $state = 'MISS';
                } elseif ($res && in_array($res['status'], [204, 404], true)) {
                    $meta = self::store($key, $path, $src, ['status' => $res['status'], 'body' => '', 'type' => 'text/plain'], 3600);
                    $state = 'MISS';
                } elseif (!$meta) {
                    self::out('', 'text/plain', 502, 60);
                } else {
                    $state = 'STALE';
                }
            }
        }
        if (200 !== (int) $meta['status']) {
            self::out('', 'text/plain', 204, 3600);
        }
        $etag = '"' . $meta['etag'] . '"';
        if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag && empty($src['rewrite'])) {
            http_response_code(304);
            header('ETag: ' . $etag);
            exit;
        }
        $body = (string) file_get_contents($file);
        $gz = !empty($meta['gz']);
        if (!empty($src['rewrite']) && self::isText($meta['type'])) {
            $body = self::rewrite($gz ? (string) gzdecode($body) : $body, $key, $src);
            $gz = false;
        }
        if ($gz && !str_contains((string) ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''), 'gzip')) {
            $body = (string) gzdecode($body);
            $gz = false;
        }
        header('ETag: ' . $etag);
        header('X-Proxy-Cache: ' . $state);
        if ($gz) {
            header('Content-Encoding: gzip');
            header('Vary: Accept-Encoding');
        }
        self::out($body, $meta['type'], 200, (int) $meta['ttl']);
    }

    private static function out(string $body, string $type, int $status, int $ttl): never
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: ' . $type);
        header('Cache-Control: public, max-age=' . min($ttl, 30 * 86400));
        header('Access-Control-Allow-Origin: *');
        header('Cross-Origin-Resource-Policy: same-site');
        header('X-Robots-Tag: noindex');
        echo $body;
        exit;
    }

    private static function rewrite(string $body, string $key, array $src): string
    {
        $local = self::url($key);

        return strtr($body, [$src['upstream'] => $local, str_replace('/', '\/', $src['upstream']) => str_replace('/', '\/', $local)]);
    }

    private static function isText(string $type): bool
    {
        return str_starts_with($type, 'application/json') || str_starts_with($type, 'text/') || str_contains($type, '+json');
    }

    // ------------------------------------------------------------------ Cache und Begrenzung

    private static function dir(?string $key = null): string
    {
        return Path::addonCache('fluid', 'proxy' . (null !== $key ? '/' . $key : ''));
    }

    private static function cacheFile(string $key, string $path): string
    {
        $h = sha1($path);

        return self::dir($key) . '/' . substr($h, 0, 2) . '/' . $h;
    }

    /** Abrufe je Besucher (Hash der IP, nur im Cache-Ordner, 10-Minuten-Fenster) */
    private static function tooMany(): bool
    {
        $id = substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . __FILE__), 0, 24);
        $file = self::dir('_rate') . '/' . $id . '-' . intdiv(time(), 600);
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0775, true);
        }
        $n = (int) @file_get_contents($file) + 1;
        @file_put_contents($file, (string) $n, LOCK_EX);

        return $n > self::RATE;
    }

    private static function store(string $key, string $path, array $src, array $res, ?int $ttl = null): array
    {
        $file = self::cacheFile($key, $path);
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0775, true);
        }
        $type = strtolower(trim(explode(';', (string) $res['type'])[0])) ?: 'application/octet-stream';
        if (self::isText($type)) {
            $type .= '; charset=utf-8';
        }
        $body = (string) $res['body'];
        $gz = false;
        foreach (self::COMPRESS as $c) {
            if (str_starts_with($type, $c) && strlen($body) > 512 && function_exists('gzencode')) {
                $body = (string) gzencode($body, 6);
                $gz = true;
                break;
            }
        }
        $t = $ttl ?? (is_callable($src['ttl']) ? (int) ($src['ttl'])($path) : (int) $src['ttl']);
        $meta = ['status' => (int) $res['status'], 'type' => $type, 'fetched' => time(), 'ttl' => max(60, $t), 'gz' => $gz, 'etag' => substr(sha1($body), 0, 16)];
        @file_put_contents($file, $body, LOCK_EX);
        @file_put_contents("$file.json", json_encode($meta, JSON_UNESCAPED_SLASHES), LOCK_EX);
        if (1 === random_int(1, 300)) {
            self::prune();
        }

        return $meta;
    }

    /** Cache auf höchstens 500 MB begrenzen (älteste Einträge zuerst) */
    public static function prune(int $limitMb = 500): void
    {
        $files = [];
        $total = 0;
        foreach (self::files() as $f) {
            $size = (int) @filesize($f);
            $total += $size;
            $files[] = [$f, (int) @filemtime($f), $size];
        }
        if ($total <= $limitMb * 1024 * 1024) {
            return;
        }
        usort($files, static fn ($a, $b) => $a[1] <=> $b[1]);
        foreach ($files as [$f, , $size]) {
            @unlink($f);
            @unlink("$f.json");
            $total -= $size;
            if ($total < $limitMb * 1024 * 1024 * 0.8) {
                break;
            }
        }
    }

    private static function files(): Generator
    {
        $dir = self::dir();
        if (!is_dir($dir)) {
            return;
        }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile() && !str_ends_with($f->getFilename(), '.json') && !str_contains($f->getPathname(), '/_rate/')) {
                yield $f->getPathname();
            }
        }
    }

    // ------------------------------------------------------------------ ausgehende Anfragen

    /**
     * HTTP-GET an eine registrierte Quelle (serverseitig, z. B. Geokodierung). Nur Hosts registrierter Quellen,
     * https, keine Weiterleitungen, kurze Timeouts.
     *
     * @return array{status: int, body: string, type: string}|null
     */
    public static function http(string $url, array $headers = []): ?array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach (self::sources() as $src) {
            if ($host === strtolower((string) parse_url($src['upstream'], PHP_URL_HOST))) {
                return self::fetch($src, $url, $headers);
            }
        }

        return null;
    }

    private static function fetch(array $src, string $url, array $extra = []): ?array
    {
        if (!str_starts_with($url, 'https://')) {
            return null;
        }
        $max = (int) ($src['max'] ?? 5 * 1024 * 1024);
        $ua = 'REDAXO-fluid (+' . (Core::hasRequest() ? Core::getRequest()->getHost() : 'server') . ')';
        try {
            $res = Core::getHttpClient()->request('GET', $url, [
                'headers' => array_merge(['Accept' => '*/*', 'User-Agent' => $ua], $extra),
                'max_redirects' => 0, 'timeout' => 8, 'max_duration' => 10,
            ]);
            $status = $res->getStatusCode();
            $body = $res->getContent(false);
            $type = (string) ($res->getHeaders(false)['content-type'][0] ?? '');
        } catch (\Throwable) {
            return null;
        }
        if (strlen($body) > $max) {
            return null;
        }
        if (200 === $status) {
            $t = strtolower($type);
            $ok = false;
            foreach ((array) ($src['types'] ?? self::DEFAULT_TYPES) as $a) {
                if (str_starts_with($t, strtolower($a))) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok && '' === $t && str_ends_with((string) parse_url($url, PHP_URL_PATH), '.pbf')) {
                $type = 'application/x-protobuf';
                $ok = true;
            }
            if (!$ok) {
                return null;
            }
        }

        return ['status' => $status, 'body' => $body, 'type' => $type];
    }
}

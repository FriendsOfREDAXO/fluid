<?php

namespace FriendsOfRedaxo\Fluid\Routing;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Core;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Security\BackendLogin;

use function array_intersect;
use function array_keys;
use function explode;
use function http_build_query;
use function in_array;
use function rawurldecode;
use function rtrim;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;
use function trim;

/**
 * Schöne Adressen für REDAXO 6: /technik statt index.php?article_id=40 (gespeicherte Pfade, Routes), mit Hauptadresse,
 * Alias- und Landing-Domains (Domains), Weiterleitungen und 404-Protokoll (Redirects), sitemap.xml (Sitemap) und
 * robots.txt (Robots).
 *
 * Eigenständiger Baustein (später als eigenes AddOn denkbar): kennt keine anderen Teile von fluid. Die melden sich an:
 *   Router::endpoint('/pfad' | '/präfix/*' | fn (string $path): bool, $handler)   eigene Adressen vor den Seiten
 *   Router::gate(fn (string $path))                    vor der Seitensuche (z. B. „Erste Schritte“ ohne Startseite)
 *   Router::resolver(fn (string $path, int $lang, array $query): ?int)   Pfade, die keine Seite sind → anzuzeigende Seite
 *   Router::onPage(fn (int $id, int $lang, array $query))                 Seite gefunden (z. B. alte Parameter umleiten)
 *   Router::keep('param', …)                           Parameter, bei denen ?article_id=… nicht umgeleitet wird
 *   Sitemap::exclude/children, EP FLUID_SITEMAP, FLUID_ROBOTS(_RULES), FLUID_ROUTES_REBUILT
 *
 * Ablauf einer Anfrage (vor dem Rendern): frühe Endpunkte · Alias-Host → 301 Hauptadresse · Endpunkte (sitemap.xml,
 * robots.txt …) · Gates · alte ?article_id=… → 301 · Sprachpräfix · Pfad → Seite (eine Abfrage) · Resolver · andere
 * Schreibweise/Schrägstrich → 301 · Weiterleitung · sonst Seite „nicht gefunden“ mit 404. Offline-Seiten nur mit
 * Backend-Anmeldung.
 */
final class Router
{
    /** Parameter, bei denen ein ?article_id-Aufruf bleibt, wie er ist (API, Arbeitsversion, Media Manager; weitere per keep()) */
    private static array $keep = ['rex-api-call', 'rex_version', 'rex_history_date', 'rex_media_type', 'rex_media_file'];

    /** @var list<array{match: string|callable, handler: callable, afterPackages: bool, early: bool}> */
    private static array $endpoints = [];

    /** @var list<callable(string): void> */
    private static array $gates = [];

    /** @var list<callable(string, int, array): ?int> */
    private static array $resolvers = [];

    /** @var list<callable(int, int, array): void> */
    private static array $pageHooks = [];

    private static ?Domain $landing = null;
    /** Nur GET/HEAD werden umgeleitet – POST des Editors an schöne Adressen nie */
    private static bool $canRedirect = true;

    // ------------------------------------------------------------------ Anmeldung der Bausteine (vor boot())

    /**
     * Eigene Adresse, die vor den Seiten beantwortet wird. $match: exakter Pfad („/fluid-live“), Präfix mit „*“
     * („/proxy/*“, der Rest geht an den Handler) oder fn (string $path): bool. $handler(string $path, string $rest)
     * beendet die Anfrage selbst. afterPackages: erst wenn alle AddOns geladen sind. early: noch vor der Umleitung von
     * Alias-Domains (Dienste wie SSE, MCP).
     */
    public static function endpoint(string|callable $match, callable $handler, bool $afterPackages = false, bool $early = false): void
    {
        self::$endpoints[] = ['match' => $match, 'handler' => $handler, 'afterPackages' => $afterPackages, 'early' => $early];
    }

    public static function gate(callable $gate): void
    {
        self::$gates[] = $gate;
    }

    public static function resolver(callable $resolver): void
    {
        self::$resolvers[] = $resolver;
    }

    public static function onPage(callable $hook): void
    {
        self::$pageHooks[] = $hook;
    }

    public static function keep(string ...$params): void
    {
        self::$keep = [...self::$keep, ...$params];
    }

    /** Pfad eines Endpunkts, falls er passt: Rest nach dem Präfix (bzw. '') – sonst null */
    private static function matches(string|callable $match, string $path): ?string
    {
        if (\is_callable($match) && !\is_string($match)) {
            return $match($path) ? '' : null;
        }
        if (str_ends_with($match, '*')) {
            $prefix = substr($match, 0, -1);

            return str_starts_with($path . '/', $prefix) || str_starts_with($path, $prefix) ? (string) substr($path, \strlen($prefix)) : null;
        }

        return $path === $match ? '' : null;
    }

    private static function dispatchEndpoints(string $path, bool $early): bool
    {
        foreach (self::$endpoints as $e) {
            if ($e['early'] !== $early || null === ($rest = self::matches($e['match'], $path))) {
                continue;
            }
            if ($e['afterPackages']) {
                Extension::register('PACKAGES_INCLUDED', static fn () => ($e['handler'])($path, $rest));

                return true;
            }
            ($e['handler'])($path, $rest);
        }

        return false;
    }

    /** Ist der Pfad ein angemeldeter Endpunkt? (z. B. damit ein Gate ihn durchlässt) */
    public static function isEndpoint(string $path): bool
    {
        foreach (self::$endpoints as $e) {
            if (null !== self::matches($e['match'], $path)) {
                return true;
            }
        }

        return false;
    }

    public static function boot(): void
    {
        // eigene Endpunkte des Routings
        self::endpoint('/sitemap.xml', static fn () => self::respond(Sitemap::xml(self::$landing) ?? '', 'application/xml; charset=utf-8'));
        self::endpoint(static fn (string $p): bool => (bool) preg_match('~^/sitemap-[1-9][0-9]*\.xml$~', $p), static function (string $p): void {
            $xml = Sitemap::xml(self::$landing, (int) substr($p, 9, -4));
            if (null !== $xml) {
                self::respond($xml, 'application/xml; charset=utf-8');
            }
        });
        self::endpoint('/robots.txt', static fn () => self::respond(Robots::text(), 'text/plain; charset=utf-8'));
        Extension::register('URL_REWRITE', static fn (ExtensionPoint $ep): string => self::url((int) $ep->getParam('id'), (int) $ep->getParam('language'), (array) $ep->getParam('params')));
        // Strukturänderungen → Pfade neu berechnen (automatische Weiterleitungen inklusive)
        foreach (['ART_ADDED', 'ART_UPDATED', 'ART_DELETED', 'ART_MOVED', 'ART_COPIED', 'ART_STATUS', 'ART_TO_CAT', 'ART_TO_STARTARTICLE',
            'CAT_ADDED', 'CAT_UPDATED', 'CAT_DELETED', 'CAT_MOVED', 'CAT_STATUS', 'CAT_TO_ART'] as $ep) {
            Extension::register($ep, static function (ExtensionPoint $ep) {
                Routes::rebuild();

                return $ep->subject;
            });
        }
        // Sprachen angelegt, geändert, gelöscht → Adressen neu
        foreach ([\Redaxo\Core\Language\ExtensionPoint\LanguageAdded::class, \Redaxo\Core\Language\ExtensionPoint\LanguageUpdated::class, \Redaxo\Core\Language\ExtensionPoint\LanguageDeleted::class] as $ep) {
            Extension::register($ep, static fn () => Routes::rebuild());
        }
        if (Core::isFrontend() && Core::hasRequest()) {
            // Asset-, Medien- und Controller-Adressen ab dem Basis-Pfad (sonst brechen sie auf /seite/unterseite)
            \Redaxo\Core\Filesystem\Url::init(new RootPathProvider(Core::getProject(), Core::getRequest()->getBasePath()));
            self::resolve();
        }
    }

    private static function resolve(): void
    {
        $r = Core::getRequest();
        $host = Domains::host($r->getHost());
        $raw = rawurldecode($r->getPathInfo());
        $path = '/' . trim($raw, '/');
        if ('/index.php' === $path) {
            $path = '/';
        }
        // frühe Endpunkte (Dienste wie SSE, MCP – oft erst nach allen AddOns)
        if (self::dispatchEndpoints($path, true)) {
            return;
        }
        $query = $r->query->all();
        self::$canRedirect = in_array($r->getMethod(), ['GET', 'HEAD'], true);
        $domain = Domains::forHost($host);

        // Weitere Domain → Hauptadresse
        if (DomainRole::Alias === $domain?->role && ($primary = Domains::primary()) && !Domains::isLocal($host)) {
            self::redirect(self::origin($primary) . $r->getRequestUri(), 301);
        }
        self::$landing = DomainRole::Landing === $domain?->role ? $domain : null;

        // Endpunkte (sitemap.xml, robots.txt, Bausteine) und Gates (z. B. „Erste Schritte“ ohne Startseite)
        if (self::dispatchEndpoints($path, false)) {
            return;
        }
        foreach (self::$gates as $gate) {
            $gate($path);
        }

        // ?article_id=…: Editor, API, Medien bleiben; einfache Aufrufe → schöne Adresse (301)
        if (isset($query['article_id']) || isset($query['rex_media_type']) || isset($query['rex-api-call'])) {
            if ('/' === $path && isset($query['article_id']) && 'GET' === $r->getMethod() && !array_intersect(self::$keep, array_keys($query))) {
                $id = (int) $query['article_id'];
                $lang = (int) ($query['language'] ?? Language::getStartId());
                unset($query['article_id'], $query['language']);
                if (Article::get($id, $lang) && '' !== ($to = self::url($id, $lang, $query))) {
                    self::redirect(self::absolute($to), 301);
                }
            }
            if ('/' === $path) {
                return;
            }
        }

        // Sprache aus dem Präfix
        $lang = Language::getStartId();
        $rest = trim($path, '/');
        if (Language::count() > 1) {
            $first = explode('/', $rest, 2)[0];
            foreach (Language::getAll() as $l) {
                if ($l->id !== Language::getStartId() && $l->code === strtolower($first)) {
                    $lang = $l->id;
                    $rest = (string) substr($rest, strlen($first) + 1);
                    // eine Adresse je Sprache: „/EN/…“ und „/en“ → „/en/…“ bzw. „/en/“
                    if (($l->code !== $first || ('' === $rest && !str_ends_with($raw, '/')))) {
                        $id = '' === $rest ? Article::getSiteStartArticleId() : Routes::find($rest, $lang);
                        if (null !== $id) {
                            self::redirect(self::absolute(self::url($id, $lang, $query)), 301);
                        }
                    }
                    break;
                }
            }
        }

        // Landing-Domain: „/“ = Einstiegsseite, Unterseiten relativ dazu
        $full = $rest;
        if (self::$landing) {
            $base = (string) Routes::path((int) self::$landing->articleId, $lang);
            if ('' === $rest) {
                self::select((int) self::$landing->articleId, $lang);

                return;
            }
            $full = self::$landing->subtree ? ltrim($base . '/' . $rest, '/') : $rest;
        }

        $id = Routes::find($full, $lang);
        if (null !== $id && (!self::$landing || self::inLanding($id))) {
            // Schrägstrich am Ende → ohne
            if ('' !== $rest && str_ends_with($raw, '/')) {
                self::redirect(self::absolute(self::url($id, $lang, $query)), 301);
            }
            foreach (self::$pageHooks as $hook) {
                $hook($id, $lang, $query);
            }
            self::select($id, $lang);

            return;
        }
        // Pfade, die keine Seite sind (Bausteine: Detailseiten, Feeds …) – Ergebnis ist die anzuzeigende Seite
        foreach (self::$resolvers as $resolver) {
            $pid = $resolver($full, $lang, $query);
            if (null !== $pid && (!self::$landing || self::inLanding($pid))) {
                self::select($pid, $lang);

                return;
            }
        }
        if (null !== $id && self::$landing) {
            // Seite außerhalb der Landingpage → Hauptadresse
            self::redirect(self::absolute(self::url($id, $lang, $query)), 302);
        }
        // Natürliche Adresse der Startseite (/start) → „/“
        $home = Article::getSiteStartArticleId();
        if (!self::$landing && strtolower($full) === Routes::slug($home, $lang)) {
            self::redirect(self::absolute(self::url($home, $lang, $query)), 301);
        }
        // Andere Schreibweise → kanonische Adresse
        if (null !== ($id = Routes::findInsensitive($full, $lang))) {
            self::redirect(self::absolute(self::url($id, $lang, $query)), 301);
        }
        // Weiterleitungen
        if (null !== ($hit = Redirects::match($path, (string) parse_url($r->getRequestUri(), PHP_URL_QUERY)))) {
            Redirects::hit((int) $hit['rule']['id']);
            if (410 === $hit['code']) {
                self::notFound($lang, '410 Gone');

                return;
            }
            self::redirect(self::absolute($hit['location']), $hit['code']);
        }
        Redirects::log404($path);
        self::notFound($lang, Response::HTTP_NOT_FOUND);
    }

    private static function select(int $id, int $lang): void
    {
        $article = Article::get($id, $lang);
        // Offline: nur mit Backend-Anmeldung (Vorschau für die Redaktion)
        if (!$article || (!$article->isOnline() && !BackendLogin::hasSession())) {
            self::notFound($lang, Response::HTTP_NOT_FOUND);

            return;
        }
        Article::setCurrentId($id);
        Language::setCurrentId($lang);
    }

    private static function notFound(int $lang, string $status): void
    {
        Article::setCurrentId(Article::getNotfoundArticleId());
        Language::setCurrentId($lang);
        Response::setStatus($status);
    }

    // ------------------------------------------------------------------ Adressen

    /**
     * Adresse einer Seite: relativ auf derselben Domain, absolut auf eine andere (Landing ↔ Hauptadresse) und im Backend.
     * '' = keine gespeicherte Adresse (Core-Standard index.php?article_id=…).
     */
    public static function url(int $id, int $languageId, array $params = []): string
    {
        $languageId = $languageId ?: Language::getCurrentId();
        $path = Routes::path($id, $languageId);
        if (null === $path) {
            return '';
        }
        $prefix = Routes::prefix($languageId);
        $query = $params ? '?' . http_build_query($params) : '';
        $local = $prefix . ('' === $path ? '/' : '/' . $path);

        if (self::$landing) {
            if (self::inLanding($id)) {
                $base = (string) Routes::path((int) self::$landing->articleId, $languageId);
                $rel = $id === self::$landing->articleId ? '' : ltrim((string) substr($path, strlen($base)), '/');

                return self::base() . $prefix . '/' . $rel . $query;
            }
            $primary = Domains::primary();

            return ($primary ? self::origin($primary) : '') . $local . $query;
        }
        if (Core::isBackend() || !Core::hasRequest()) {
            $primary = Domains::primary();

            return ($primary && !Core::hasRequest() ? self::origin($primary) : self::currentOrigin()) . $local . $query;
        }

        return self::base() . $local . $query;
    }

    /** Kanonische, absolute Adresse einer Seite (Landing „own“ → Landing-Domain, sonst Hauptadresse) */
    /**
     * Sprachfassungen einer Seite: alle Sprachen, die online sind und in denen die Seite online ist (für die Sprachwahl).
     * Mit $all auch offline (für die Redaktion).
     *
     * @return list<array{id: int, code: string, name: string, url: string, current: bool, online: bool}>
     */
    public static function alternates(Article $article, bool $all = false): array
    {
        $out = [];
        foreach (Language::getAll() as $l) {
            $a = Article::get($article->id, $l->id);
            $online = $l->isOnline() && $a && $a->isOnline();
            if (!$a || (!$all && !$online)) {
                continue;
            }
            $out[] = ['id' => $l->id, 'code' => $l->code, 'name' => $l->name, 'url' => self::url($article->id, $l->id), 'current' => $l->id === $article->languageId, 'online' => $online];
        }

        return $out;
    }

    public static function canonical(int $id, int $languageId): string
    {
        $path = Routes::path($id, $languageId);
        if (null === $path) {
            return '';
        }
        $prefix = Routes::prefix($languageId);
        $a = Article::get($id, $languageId);
        $landing = $a ? Domains::landingFor($id, $a->path) : null;
        if ($landing && $landing->canonicalOwn) {
            $base = (string) Routes::path((int) $landing->articleId, $languageId);
            $rel = $id === $landing->articleId ? '' : ltrim((string) substr($path, strlen($base)), '/');

            return self::origin($landing) . $prefix . '/' . $rel;
        }
        $primary = Domains::primary();

        return ($primary ? self::origin($primary) : self::currentOrigin()) . $prefix . ('' === $path ? '/' : '/' . $path);
    }

    private static function inLanding(int $id): bool
    {
        $l = self::$landing;
        if (!$l || null === $l->articleId) {
            return false;
        }
        if ($id === $l->articleId) {
            return true;
        }

        return $l->subtree && in_array($l->articleId, Article::get($id)->path ?? [], true);
    }

    /** Aktuelle Landing-Domain (oder null) – z. B. für ein auf die Landingpage begrenztes Menü */
    public static function landing(): ?Domain
    {
        return self::$landing;
    }

    /** Basis-Pfad der Website (Unterordner), ohne Schrägstrich am Ende */
    public static function base(): string
    {
        if (!Core::hasRequest()) {
            return '';
        }
        $base = Core::getRequest()->getBasePath();
        if (Core::isBackend()) {
            $base = substr($base, 0, (int) strrpos($base, '/'));
        }

        return rtrim($base, '/');
    }

    public static function origin(Domain $d): string
    {
        if (Core::hasRequest() && Domains::host(Core::getRequest()->getHost()) === $d->host) {
            return self::currentOrigin();
        }

        // Nicht-Standard-Port (lokale Entwicklung) mitnehmen
        $port = Core::hasRequest() ? (int) Core::getRequest()->getPort() : 443;
        $portPart = in_array($port, [80, 443], true) ? '' : ':' . $port;

        return (Core::hasRequest() ? Core::getRequest()->getScheme() : 'https') . '://' . $d->host . $portPart . self::base();
    }

    private static function currentOrigin(): string
    {
        return Core::hasRequest() ? Core::getRequest()->getSchemeAndHttpHost() . self::base() : rtrim((string) Core::getProject()->baseUrl, '/');
    }

    public static function absolute(string $url): string
    {
        return preg_match('~^https?://~i', $url) ? $url : self::currentOrigin() . (str_starts_with($url, self::base() . '/') ? substr($url, strlen(self::base())) : $url);
    }

    /** Weiterleitung (nur bei GET/HEAD) – für Bausteine; relative Adressen werden absolut */
    public static function redirectTo(string $url, int $code = 301): void
    {
        self::redirect(self::absolute($url), $code);
    }

    private static function redirect(string $url, int $code): void
    {
        if (!self::$canRedirect) {
            return;
        }
        header('Location: ' . (string) preg_replace_callback('~[^\x21-\x7E]+~', static fn ($m) => rawurlencode($m[0]), $url), true, $code);
        header('Cache-Control: ' . (301 === $code ? 'public, max-age=3600' : 'no-cache'));
        header('X-Redirect-By: fluid');
        exit;
    }

    /** Antwort senden und beenden (für Endpunkte und Resolver der Bausteine) */
    public static function respond(string $body, string $type): never
    {
        header('Content-Type: ' . $type);
        echo $body;
        exit;
    }
}

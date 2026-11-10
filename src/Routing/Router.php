<?php

namespace FriendsOfRedaxo\Fluid\Routing;

use FriendsOfRedaxo\Fluid\Routing\Attribute\AsEndpoint;
use Redaxo\Core\ClassDiscovery;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ExtensionPoint as ContentEp;
use Redaxo\Core\Core;
use Redaxo\Core\ExtensionPoint\AsExtension;
use Redaxo\Core\ExtensionPoint\ExtensionLevel;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Language\ExtensionPoint as LanguageEp;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Security\BackendLogin;
use Throwable;

use function array_diff_key;
use function array_intersect;
use function array_keys;
use function explode;
use function http_build_query;
use function in_array;
use function is_callable;
use function is_string;
use function parse_url;
use function preg_match;
use function preg_replace_callback;
use function rawurldecode;
use function rawurlencode;
use function rtrim;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strrpos;
use function strtolower;
use function substr;
use function trim;

use const PHP_URL_HOST;
use const PHP_URL_PATH;
use const PHP_URL_QUERY;

/**
 * Schöne Adressen für REDAXO 6: /technik statt index.php?article_id=40 (gespeicherte Pfade, Routes), Einträge eigener
 * Quellen (/news/sommerfest-12, RouteSource), Hauptadresse, Alias- und Landing-Domains (Domains), Weiterleitungen und
 * 404-Protokoll (Redirects), sitemap.xml (Sitemap), robots.txt (Robots) und Seitenkopf (Seo\Head).
 *
 * Eigenständiger Baustein: kennt keine anderen Teile von fluid und lässt sich als eigenes AddOn herauslösen bzw. durch
 * yrewrite + ein URL-AddOn ersetzen (siehe README.md in diesem Ordner). Konfiguration im Projekt:
 *
 *     Router::$config = new RoutingConfig(format: UrlFormat::html());
 *
 * Erweitern (alles ohne Wissen des Routings):
 *   #[AsRouteSource] + RouteSource/TableSource         Einträge mit eigener Adresse (gespeichert, Sitemap, Meta)
 *   #[AsEndpoint('/pfad')] an einer statischen Methode  eigene Adressen vor den Seiten (bzw. Router::endpoint())
 *   Router::resolver(fn (string $path, int $lang, array $query): ?RouteMatch)   dynamische Adressen ohne Speicherung
 *   Router::gate(fn (string $path))                    vor der Seitensuche (z. B. „Erste Schritte“ ohne Startseite)
 *   Router::onPage(fn (int $id, int $lang, array $query))   Seite gefunden (z. B. alte Parameter umleiten)
 *   Router::keep('param', …)                           Parameter, bei denen ?article_id=… nicht umgeleitet wird
 *   Extension Points: RoutesRebuilt, SitemapEntries, RobotsTxt, ArticleMeta, PageMetaResolved
 *
 * Ablauf einer Anfrage (PACKAGES_INCLUDED, früh – alle AddOns sind geladen): frühe Endpunkte · Alias-Host → 301
 * Hauptadresse · Endpunkte (sitemap.xml, robots.txt …) · Gates · alte ?article_id=… → 301 · Sprachpräfix · Endung weg ·
 * Pfad → Seite bzw. Eintrag bzw. Resolver · andere Schreibweise als die kanonische Adresse → 301 · Weiterleitung · sonst
 * Seite „nicht gefunden“ mit 404. Offline-Seiten nur mit Backend-Anmeldung.
 */
final class Router
{
    /** Einstellungen des Projekts (Project::configure()); null = Vorgaben */
    public static ?RoutingConfig $config = null;

    /** Parameter, bei denen ein ?article_id-Aufruf bleibt, wie er ist (API, Arbeitsversion, Media Manager; weitere per keep()) */
    private static array $keep = ['rex-api-call', 'rex_version', 'rex_history_date', 'rex_media_type', 'rex_media_file'];

    /** @var list<array{match: string|callable, handler: callable, early: bool}> */
    private static array $endpoints = [];

    /** @var list<array{match: string|callable, handler: callable, early: bool}>|null per #[AsEndpoint] gefunden */
    private static ?array $discovered = null;

    /** @var list<callable(string): void> */
    private static array $gates = [];

    /** @var list<callable(string, int, array): ?RouteMatch> */
    private static array $resolvers = [];

    /** @var list<callable(int, int, array): void> */
    private static array $pageHooks = [];

    private static ?Domain $landing = null;
    private static ?RouteMatch $current = null;
    private static bool $handled = false;
    /** Nur GET/HEAD werden umgeleitet – POST des Editors an schöne Adressen nie */
    private static bool $canRedirect = true;

    public static function config(): RoutingConfig
    {
        return self::$config ??= new RoutingConfig();
    }

    // ------------------------------------------------------------------ Anmeldung der Bausteine

    /**
     * Eigene Adresse, die vor den Seiten beantwortet wird (wie #[AsEndpoint]). $match: exakter Pfad („/fluid-live“),
     * Präfix mit „*“ („/proxy/*“, der Rest geht an den Handler) oder fn (string $path): bool. $handler(string $path,
     * string $rest) beendet die Anfrage selbst. early: noch vor der Umleitung von Alias-Domains (Dienste wie SSE, MCP).
     */
    public static function endpoint(string|callable $match, callable $handler, bool $early = false): void
    {
        self::$endpoints[] = ['match' => $match, 'handler' => $handler, 'early' => $early];
    }

    public static function gate(callable $gate): void
    {
        self::$gates[] = $gate;
    }

    /** Dynamische Adressen (ohne Speicherung): fn (string $path, int $lang, array $query): ?RouteMatch */
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

    /** @return list<array{match: string|callable, handler: callable, early: bool}> */
    private static function allEndpoints(): array
    {
        if (null === self::$discovered) {
            self::$discovered = [];
            try {
                foreach (ClassDiscovery::getInstance()->discoverByMethodAttribute(AsEndpoint::class) as $e) {
                    if (!$e['isStatic']) {
                        continue;
                    }
                    /** @var AsEndpoint $a */
                    $a = $e['attribute'];
                    $match = null === $a->param ? $a->path : static fn (string $p): bool => null !== self::matches($a->path, $p) || (Core::hasRequest() && Core::getRequest()->query->has($a->param));
                    self::$discovered[] = ['match' => $match, 'handler' => [$e['class'], $e['method']], 'early' => $a->early];
                }
            } catch (Throwable) {
                // Klassenerkennung nicht verfügbar (Setup)
            }
        }

        return [...self::$discovered, ...self::$endpoints];
    }

    /** Pfad eines Endpunkts, falls er passt: Rest nach dem Präfix (bzw. '') – sonst null */
    private static function matches(string|callable $match, string $path): ?string
    {
        if (is_callable($match) && !is_string($match)) {
            return $match($path) ? '' : null;
        }
        if (str_ends_with($match, '*')) {
            $prefix = substr($match, 0, -1);

            return str_starts_with($path . '/', $prefix) || str_starts_with($path, $prefix) ? (string) substr($path, strlen($prefix)) : null;
        }

        return $path === $match ? '' : null;
    }

    private static function runEndpoints(string $path, bool $early): bool
    {
        foreach (self::allEndpoints() as $e) {
            if ($e['early'] === $early && null !== ($rest = self::matches($e['match'], $path))) {
                ($e['handler'])($path, $rest);
            }
        }

        return false;
    }

    /** Ist der Pfad ein angemeldeter Endpunkt? (z. B. damit ein Gate ihn durchlässt) */
    public static function isEndpoint(string $path): bool
    {
        foreach (self::allEndpoints() as $e) {
            if (null !== self::matches($e['match'], $path)) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------ Start

    /** Im Frontend: Asset-, Medien- und Controller-Adressen ab dem Basis-Pfad (sonst brechen sie auf /seite/unterseite) */
    public static function boot(): void
    {
        if (self::config()->enabled && Core::isFrontend() && Core::hasRequest()) {
            \Redaxo\Core\Filesystem\Url::init(new RootPathProvider(Core::getProject(), Core::getRequest()->getBasePath()));
        }
    }

    /** @internal Adresse → Seite, sobald alle AddOns geladen sind (ihre Quellen und Endpunkte sind dann bekannt) */
    #[AsExtension('PACKAGES_INCLUDED', ExtensionLevel::Early)]
    public static function handleRequest(): void
    {
        if (self::$handled || !self::config()->enabled || !Core::isFrontend() || !Core::hasRequest()) {
            return;
        }
        self::$handled = true;
        self::resolve();
    }

    /** @internal Adresse einer Seite; ohne Pfad (null) bleibt die Adresse des Cores */
    #[AsExtension]
    public static function articleUrl(ContentEp\ArticleUrl $ep): ?string
    {
        if (!self::config()->enabled) {
            return null;
        }
        $url = self::url($ep->articleId, $ep->languageId, $ep->params);

        return '' !== $url ? $url : null;
    }

    /** @internal Struktur bzw. Sprachen geändert → Pfade neu (automatische Weiterleitungen inklusive) */
    #[AsExtension(ContentEp\ArticleAdded::class)]
    #[AsExtension(ContentEp\ArticleUpdated::class)]
    #[AsExtension(ContentEp\ArticleDeleted::class)]
    #[AsExtension(ContentEp\ArticleMoved::class)]
    #[AsExtension(ContentEp\ArticleCopied::class)]
    #[AsExtension(ContentEp\ArticleStatusChanged::class)]
    #[AsExtension(ContentEp\ArticleConvertedToCategory::class)]
    #[AsExtension(ContentEp\ArticleConvertedToStartArticle::class)]
    #[AsExtension(ContentEp\CategoryAdded::class)]
    #[AsExtension(ContentEp\CategoryUpdated::class)]
    #[AsExtension(ContentEp\CategoryDeleted::class)]
    #[AsExtension(ContentEp\CategoryMoved::class)]
    #[AsExtension(ContentEp\CategoryStatusChanged::class)]
    #[AsExtension(ContentEp\CategoryConvertedToArticle::class)]
    #[AsExtension(LanguageEp\LanguageAdded::class)]
    #[AsExtension(LanguageEp\LanguageUpdated::class)]
    #[AsExtension(LanguageEp\LanguageDeleted::class)]
    public static function structureChanged(): void
    {
        if (self::config()->enabled) {
            Routes::rebuild();
        }
    }

    // ------------------------------------------------------------------ Auflösen

    private static function resolve(): void
    {
        $r = Core::getRequest();
        $host = Domains::host($r->getHost());
        $raw = rawurldecode($r->getPathInfo());
        $path = '/' . trim($raw, '/');
        if ('/index.php' === $path) {
            $path = $raw = '/';
        }
        // frühe Endpunkte (Dienste wie SSE, MCP)
        self::runEndpoints($path, true);
        $query = $r->query->all();
        self::$canRedirect = in_array($r->getMethod(), ['GET', 'HEAD'], true);
        $domain = Domains::forHost($host);

        // Weitere Domain → Hauptadresse
        if (DomainRole::Alias === $domain?->role && ($primary = Domains::primary()) && !Domains::isLocal($host)) {
            self::redirect(self::origin($primary) . $r->getRequestUri(), 301);
        }
        self::$landing = DomainRole::Landing === $domain?->role ? $domain : null;

        // Endpunkte (sitemap.xml, robots.txt, Bausteine) und Gates (z. B. „Erste Schritte“ ohne Startseite)
        self::runEndpoints($path, false);
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

        // Sprache aus dem Präfix, Endung weg
        $lang = Language::getStartId();
        $rest = trim($path, '/');
        if (Language::count() > 1) {
            $first = explode('/', $rest, 2)[0];
            foreach (Language::getAll() as $l) {
                if ($l->id !== Language::getStartId() && $l->code === strtolower($first)) {
                    $lang = $l->id;
                    $rest = (string) substr($rest, strlen($first) + 1);
                    break;
                }
            }
        }
        $key = self::config()->format->strip($rest);

        // Landing-Domain: „/“ = Einstiegsseite, Unterseiten relativ dazu
        $full = $key;
        $landingId = self::$landing?->articleId;
        if (null !== $landingId) {
            $base = (string) Routes::path((int) $landingId, $lang);
            $full = '' === $key ? $base : (self::$landing->subtree ? ltrim($base . '/' . $key, '/') : $key);
        }

        $match = self::match($full, $lang, $query);
        if (null === $match && null !== $landingId && !self::$landing->subtree && '' !== $key) {
            // Einträge der Einstiegsseite einer Landing-Domain ohne Unterseiten
            $e = Routes::findEntry(ltrim(Routes::path((int) $landingId, $lang) . '/' . $key, '/'), $lang);
            $match = null !== $e && $e->articleId === $landingId ? self::entryMatch($e) : null;
        }
        if (null !== $match) {
            if (self::$landing && !self::inLanding($match->articleId)) {
                // Seite außerhalb der Landingpage → Hauptadresse
                self::redirect(self::absolute(self::url($match->articleId, $lang, $query)), 302);
            } else {
                // eine Adresse je Seite: andere Schreibweise, Endung, Schrägstrich, Sprachpräfix → kanonische Adresse
                $want = self::wanted($match, array_diff_key($query, $match->params));
                if ('' !== $want && self::pathOf($want) !== $raw) {
                    self::redirect(self::absolute($want), 301, $match->cacheRedirect);
                }
                if (null === $match->entry && [] === $match->params) {
                    foreach (self::$pageHooks as $hook) {
                        $hook($match->articleId, $lang, $query);
                    }
                }
                self::select($match);

                return;
            }
        }
        // Natürliche Adresse der Startseite (/start) → „/“
        $home = Article::getSiteStartArticleId();
        if (!self::$landing && '' !== $key && strtolower($full) === Routes::slug($home, $lang)) {
            self::redirect(self::absolute(self::url($home, $lang, $query)), 301);
        }
        // Weiterleitungen (auch für die Schreibweise ohne Endung)
        $qs = (string) parse_url($r->getRequestUri(), PHP_URL_QUERY);
        $plain = '/' . ltrim(Routes::prefix($lang) . '/' . $key, '/');
        $hit = Redirects::match($path, $qs) ?? ($plain !== $path ? Redirects::match($plain, $qs) : null);
        if (null !== $hit) {
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

    /** Seite, Eintrag einer Quelle oder Ergebnis eines Resolvers zu einem Pfad (ohne Präfix und Endung) */
    private static function match(string $full, int $lang, array $query): ?RouteMatch
    {
        if ('' === $full) {
            return new RouteMatch(Article::getSiteStartArticleId(), $lang);
        }
        $id = Routes::find($full, $lang) ?? Routes::findInsensitive($full, $lang);
        if (null !== $id) {
            return new RouteMatch($id, $lang);
        }
        if (null !== ($e = Routes::findEntry($full, $lang))) {
            return self::entryMatch($e);
        }
        foreach (self::$resolvers as $resolver) {
            $m = $resolver($full, $lang, $query);
            if ($m instanceof RouteMatch) {
                return $m;
            }
        }

        return null;
    }

    private static function entryMatch(RouteEntry $e): RouteMatch
    {
        $alternates = [];
        foreach (Routes::entryLanguages($e->source, $e->id) as $lang) {
            $alternates[$lang] = self::canonicalFor($e->source, $e->id, $lang);
        }

        return new RouteMatch($e->articleId, $e->languageId, $e->params, self::urlFor($e->source, $e->id, $e->languageId), true, null, $alternates, $e);
    }

    /** Kanonische Adresse eines Treffers mit den Parametern der Anfrage */
    private static function wanted(RouteMatch $m, array $query): string
    {
        if (null === $m->url) {
            return self::url($m->articleId, $m->languageId, $query);
        }
        if ([] === $query) {
            return $m->url;
        }
        [$u, $q] = array_pad(explode('?', $m->url, 2), 2, '');
        parse_str($q, $own);

        return $u . '?' . http_build_query($query + $own);
    }

    /** Pfad einer Adresse ab dem Basis-Pfad, wie ihn die Anfrage hat (andere Domain: null-sicher anders) */
    private static function pathOf(string $url): string
    {
        if (preg_match('~^https?://~i', $url) && strtolower((string) parse_url($url, PHP_URL_HOST)) !== strtolower(Domains::host(Core::getRequest()->getHost()))) {
            return "\0" . $url;
        }
        $p = rawurldecode((string) parse_url($url, PHP_URL_PATH));
        $base = self::base();

        return '' !== $base && str_starts_with($p, $base) ? (string) substr($p, strlen($base)) : $p;
    }

    private static function select(RouteMatch $m): void
    {
        $article = Article::get($m->articleId, $m->languageId);
        // Offline: nur mit Backend-Anmeldung (Vorschau für die Redaktion); Einträge, die es nicht mehr gibt: 404
        if (!$article || (!$article->isOnline() && !BackendLogin::hasSession())
            || (null !== $m->entry && !(Sources::get($m->entry->source)?->exists($m->entry) ?? false))) {
            self::notFound($m->languageId, Response::HTTP_NOT_FOUND);

            return;
        }
        if ([] !== $m->params) {
            $r = Core::getRequest();
            foreach ($m->params as $k => $v) {
                $r->query->set($k, (string) $v);
                $_GET[$k] = (string) $v;
            }
        }
        self::$current = $m;
        Article::setCurrentId($m->articleId);
        Language::setCurrentId($m->languageId);
    }

    private static function notFound(int $lang, string $status): void
    {
        self::$current = null;
        Article::setCurrentId(Article::getNotfoundArticleId());
        Language::setCurrentId($lang);
        Response::setStatus($status);
    }

    /** Treffer der aktuellen Anfrage (Seite, Eintrag einer Quelle, Resolver) – null bei 404 bzw. außerhalb des Frontends */
    public static function current(): ?RouteMatch
    {
        return self::$current;
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

        return null === $path ? '' : self::build($id, $path, Routes::isCategory($id, $languageId), $languageId, $params);
    }

    /** Adresse eines Eintrags einer Quelle ('' = keine) */
    public static function urlFor(string $source, string|int $id, ?int $languageId = null, array $params = []): string
    {
        $languageId = $languageId ?: Language::getCurrentId();
        $path = Routes::entryPath($source, (string) $id, $languageId);
        $article = null !== $path ? Routes::entryArticle($source, (string) $id, $languageId) : null;

        return null === $article ? '' : self::build($article, $path, false, $languageId, $params);
    }

    /** Adresse aus Pfad (ohne Präfix und Endung) und der Seite, zu der er gehört */
    private static function build(int $articleId, string $path, bool $category, int $languageId, array $params): string
    {
        $fmt = self::config()->format;
        $prefix = Routes::prefix($languageId);
        $query = $params ? '?' . http_build_query($params) : '';
        $local = $prefix . ('' === $path ? '/' : '/' . $fmt->append($path, $category));

        if (self::$landing) {
            if (self::inLanding($articleId)) {
                $base = (string) Routes::path((int) self::$landing->articleId, $languageId);
                $rel = $path === $base ? '' : ltrim((string) substr($path, strlen($base)), '/');

                return self::base() . $prefix . '/' . ('' !== $rel ? $fmt->append($rel, $category) : '') . $query;
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

    /**
     * Sprachfassungen einer Seite: alle Sprachen, die online sind und in denen die Seite online ist (für die Sprachwahl).
     * Mit $all auch offline (für die Redaktion). Auf der Adresse eines Eintrags: dessen Sprachfassungen.
     *
     * @return list<array{id: int, code: string, name: string, url: string, current: bool, online: bool}>
     */
    public static function alternates(Article $article, bool $all = false): array
    {
        $entry = self::$current?->entry;
        $entry = null !== $entry && $entry->articleId === $article->id ? $entry : null;
        $out = [];
        foreach (Language::getAll() as $l) {
            $a = Article::get($article->id, $l->id);
            $online = $l->isOnline() && $a && $a->isOnline();
            if (!$a || (!$all && !$online)) {
                continue;
            }
            $url = null !== $entry ? self::urlFor($entry->source, $entry->id, $l->id) : self::url($article->id, $l->id);
            if ('' === $url && null !== $entry) {
                $url = self::url($article->id, $l->id);   // Eintrag gibt es in der Sprache nicht: Seite
            }
            $out[] = ['id' => $l->id, 'code' => $l->code, 'name' => $l->name, 'url' => $url, 'current' => $l->id === $article->languageId, 'online' => $online];
        }

        return $out;
    }

    /** Kanonische, absolute Adresse einer Seite (Landing „own“ → Landing-Domain, sonst Hauptadresse) */
    public static function canonical(int $id, int $languageId): string
    {
        $path = Routes::path($id, $languageId);

        return null === $path ? '' : self::canonicalPath($id, $path, Routes::isCategory($id, $languageId), $languageId);
    }

    /** Kanonische, absolute Adresse eines Eintrags ('' = keine) */
    public static function canonicalFor(string $source, string|int $id, int $languageId): string
    {
        $path = Routes::entryPath($source, (string) $id, $languageId);
        $article = null !== $path ? Routes::entryArticle($source, (string) $id, $languageId) : null;

        return null === $article ? '' : self::canonicalPath($article, $path, false, $languageId);
    }

    private static function canonicalPath(int $id, string $path, bool $category, int $languageId): string
    {
        $fmt = self::config()->format;
        $prefix = Routes::prefix($languageId);
        $a = Article::get($id, $languageId);
        $landing = $a ? Domains::landingFor($id, $a->path) : null;
        if ($landing && $landing->canonicalOwn) {
            $base = (string) Routes::path((int) $landing->articleId, $languageId);
            $rel = $path === $base ? '' : ltrim((string) substr($path, strlen($base)), '/');

            return self::origin($landing) . $prefix . '/' . ('' !== $rel ? $fmt->append($rel, $category) : '');
        }
        $primary = Domains::primary();

        return ($primary ? self::origin($primary) : self::currentOrigin()) . $prefix . ('' === $path ? '/' : '/' . $fmt->append($path, $category));
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

    /**
     * Weiterleitung (nur bei GET/HEAD) – für Bausteine; relative Adressen werden absolut.
     *
     * @param bool $cache 301 eine Stunde zwischenspeichern – nicht bei Adressen, die sich ändern können (Titel eines Datensatzes)
     */
    public static function redirectTo(string $url, int $code = 301, bool $cache = true): void
    {
        self::redirect(self::absolute($url), $code, $cache);
    }

    private static function redirect(string $url, int $code, bool $cache = true): void
    {
        if (!self::$canRedirect) {
            return;
        }
        header('Location: ' . (string) preg_replace_callback('~[^\x21-\x7E]+~', static fn ($m) => rawurlencode($m[0]), $url), true, $code);
        header('Cache-Control: ' . (301 === $code && $cache ? 'public, max-age=3600' : 'no-cache'));
        if ('' !== self::config()->redirectBy) {
            header('X-Redirect-By: ' . self::config()->redirectBy);
        }
        exit;
    }

    /**
     * Antwort senden und beenden (für Endpunkte und Resolver der Bausteine).
     *
     * @param list<string> $headers weitere Kopfzeilen
     */
    public static function respond(string $body, string $type, array $headers = []): never
    {
        header('Content-Type: ' . $type);
        foreach ($headers as $h) {
            header($h);
        }
        echo $body;
        exit;
    }

    // ------------------------------------------------------------------ eigene Endpunkte

    /** @internal Sitemap und robots.txt nicht in Proxy-Caches festhalten (sonst gilt nach einer Änderung noch lange die alte) */
    #[AsEndpoint('/sitemap.xml')]
    public static function sitemap(): void
    {
        self::respond(Sitemap::xml(self::$landing) ?? '', 'application/xml; charset=utf-8', ['Cache-Control: no-cache']);
    }

    /** @internal Teile ab Sitemap::MAX Adressen: /sitemap-1.xml … */
    #[AsEndpoint('/sitemap-*')]
    public static function sitemapPart(string $path, string $rest): void
    {
        if (preg_match('~^([1-9][0-9]*)\.xml$~', $rest, $m) && null !== ($xml = Sitemap::xml(self::$landing, (int) $m[1]))) {
            self::respond($xml, 'application/xml; charset=utf-8', ['Cache-Control: no-cache']);
        }
    }

    /** @internal */
    #[AsEndpoint('/sitemap.xsl')]
    public static function sitemapXsl(): void
    {
        self::respond(Sitemap::xsl(), 'text/xsl; charset=utf-8');
    }

    /** @internal */
    #[AsEndpoint('/robots.txt')]
    public static function robots(): void
    {
        self::respond(Robots::text(), 'text/plain; charset=utf-8', ['Cache-Control: no-cache']);
    }
}

<?php

namespace FriendsOfRedaxo\Fluid\Routing;

use FriendsOfRedaxo\Fluid\Api;
use FriendsOfRedaxo\Fluid\Editing;
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
 * Alias- und Landing-Domains (Domains), Weiterleitungen und 404-Protokoll (Redirects), sitemap.xml und robots.txt (Seo).
 *
 * Ablauf einer Anfrage (FluidAddon::boot, vor dem Rendern): Alias-Host → 301 Hauptadresse · sitemap.xml/robots.txt ·
 * alte ?article_id=… → 301 · Sprachpräfix · Pfad → Seite (eine Abfrage) · andere Schreibweise/Schrägstrich → 301 ·
 * Weiterleitung · sonst Seite „nicht gefunden“ mit 404. Offline-Seiten sind nur mit Backend-Anmeldung erreichbar.
 */
final class Router
{
    /** Parameter, bei denen ein ?article_id-Aufruf bleibt, wie er ist (Editor, API, Arbeitsversion, Media Manager) */
    private const array KEEP = ['rex-api-call', Api::PARAM, Editing::PARAM, 'rex_version', 'rex_history_date', 'rex_media_type', 'rex_media_file'];

    private static ?Domain $landing = null;
    /** Nur GET/HEAD werden umgeleitet – POST des Editors an schöne Adressen nie */
    private static bool $canRedirect = true;

    public static function boot(): void
    {
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
        $query = $r->query->all();
        self::$canRedirect = in_array($r->getMethod(), ['GET', 'HEAD'], true);
        $domain = Domains::forHost($host);

        // Weitere Domain → Hauptadresse
        if (DomainRole::Alias === $domain?->role && ($primary = Domains::primary()) && !Domains::isLocal($host)) {
            self::redirect(self::origin($primary) . $r->getRequestUri(), 301);
        }
        self::$landing = DomainRole::Landing === $domain?->role ? $domain : null;

        // Eigener Proxy für Karten (Kacheln, Stile, Schriften) – Besucher verbinden sich nie mit Dritten
        if (preg_match('~^/proxy/([a-z][a-z0-9_-]{1,30})(/.*)?$~', $path, $m)) {
            \FriendsOfRedaxo\Fluid\Maps\Proxy::handle($m[1], (string) ($m[2] ?? ''));
        }
        // Noch keine Startseite (frische Installation): Erste Schritte statt Fehler
        if (\FriendsOfRedaxo\Fluid\Welcome::needed() && !str_starts_with($path, '/proxy/') && !\in_array($path, ['/sitemap.xml', '/robots.txt'], true)) {
            \FriendsOfRedaxo\Fluid\Welcome::handle();
        }
        // Untertitel aus dem Medienpool, geprüft als WebVTT
        if (str_starts_with($path, \FriendsOfRedaxo\Fluid\Media\Tracks::ROUTE)) {
            \FriendsOfRedaxo\Fluid\Media\Tracks::handle(substr($path, \strlen(\FriendsOfRedaxo\Fluid\Media\Tracks::ROUTE)));
        }
        if ('/sitemap.xml' === $path) {
            self::send(Seo::sitemap(self::$landing), 'application/xml; charset=utf-8');
        }
        if ('/robots.txt' === $path) {
            self::send(Seo::robots(), 'text/plain; charset=utf-8');
        }

        // ?article_id=…: Editor, API, Medien bleiben; einfache Aufrufe → schöne Adresse (301)
        if (isset($query['article_id']) || isset($query['rex_media_type']) || isset($query['rex-api-call'])) {
            if ('/' === $path && isset($query['article_id']) && 'GET' === $r->getMethod() && !array_intersect(self::KEEP, array_keys($query))) {
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

        // RSS eines Blogs: /{blog}/feed.xml
        if (preg_match('~^(.+)/feed\.xml$~', $full, $fm) && null !== ($bid = Routes::find($fm[1], $lang)) && \FriendsOfRedaxo\Fluid\Blog\Blog::isBlog($bid)) {
            Language::setCurrentId($lang);
            self::send(\FriendsOfRedaxo\Fluid\Blog\Blog::feed($bid), 'application/rss+xml; charset=utf-8');
        }

        $id = Routes::find($full, $lang);
        if (null !== $id && (!self::$landing || self::inLanding($id))) {
            // Schrägstrich am Ende → ohne
            if ('' !== $rest && str_ends_with($raw, '/')) {
                self::redirect(self::absolute(self::url($id, $lang, $query)), 301);
            }
            self::select($id, $lang);

            return;
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

    private static function send(string $body, string $type): never
    {
        header('Content-Type: ' . $type);
        echo $body;
        exit;
    }
}

<?php

namespace FriendsOfRedaxo\Fluid\Routing\Seo;

use FriendsOfRedaxo\Fluid\Routing\ExtensionPoint\ArticleMeta;
use FriendsOfRedaxo\Fluid\Routing\ExtensionPoint\PageMetaResolved;
use FriendsOfRedaxo\Fluid\Routing\Router;
use FriendsOfRedaxo\Fluid\Routing\Routes;
use FriendsOfRedaxo\Fluid\Routing\Sources;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Core;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\Language\Language;
use Redaxo\Core\MediaPool\Media;

use function count;
use function date;
use function is_array;
use function json_encode;
use function preg_match;
use function str_replace;

use const DATE_ATOM;
use const JSON_HEX_TAG;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Seitenkopf für Suchmaschinen und soziale Netzwerke – für Seiten und für Einträge der Quellen gleich:
 * Titel, Beschreibung, robots, Canonical, hreflang (inkl. x-default), Open Graph (og:url, og:type, og:locale, Bild mit
 * Maßen), article:*-Zeiten, twitter:card, eigene Tags und JSON-LD.
 *
 *     <title><?= escape(Head::title()) ?></title>
 *     <?= Head::render() ?>
 *
 * Woher die Angaben kommen (leere Werte überschreiben nie): Seite (Name, Adresse, Sprachen) → Extension Point ArticleMeta
 * (z. B. SEO-Felder der Seite) → Eintrag einer Quelle bzw. eines Resolvers (RouteSource::meta) → Head::override() (Blöcke,
 * Templates) → Extension Point PageMetaResolved. Eigene Tags: HeadConfig::$tags (jede Seite), Head::add() (diese Anfrage),
 * PageMeta::add() in einem Extension Point. Gleicher Schlüssel ersetzt – so lässt sich jede Angabe überschreiben.
 */
final class Head
{
    /** Einstellungen des Projekts (Project::configure()); null = Vorgaben */
    public static ?HeadConfig $config = null;

    /** @var array<string, HeadTag> eigene Tags dieser Anfrage */
    private static array $tags = [];

    private static ?PageMeta $override = null;

    /** @var array<string, PageMeta> */
    private static array $resolved = [];

    public static function config(): HeadConfig
    {
        return self::$config ??= new HeadConfig();
    }

    /** Eigene Tags für diese Anfrage (gleicher Schlüssel ersetzt) */
    public static function add(HeadTag ...$tags): void
    {
        foreach ($tags as $t) {
            self::$tags[$t->key] = $t;
        }
        self::$resolved = [];
    }

    /**
     * Angaben aus einer Ausgabe überschreiben – z. B. ein Block, der einen Eintrag zeigt. Muss vor dem Seitenkopf passieren
     * (Templates rendern den Inhalt vor dem Kopf). Arrays: title, description, image, canonical (bzw. url), type …
     *
     * @param PageMeta|array<string, mixed> $meta
     */
    public static function override(PageMeta|array $meta): void
    {
        if (is_array($meta)) {
            $m = new PageMeta();
            foreach ($meta as $k => $v) {
                $k = 'canonical' === $k ? 'url' : $k;
                if (property_exists($m, $k) && null !== $v && '' !== $v) {
                    $m->$k = $v;
                }
            }
            $meta = $m;
        }
        self::$override = (self::$override ?? new PageMeta())->merge($meta);
        self::$resolved = [];
    }

    /** Angaben der aktuellen (bzw. einer) Seite – samt Eintrag einer Quelle, wenn die Adresse einer ist */
    public static function meta(?Article $article = null): PageMeta
    {
        $article ??= Article::getCurrent();
        if (null === $article) {
            return new PageMeta();
        }
        $key = $article->id . '|' . $article->languageId;
        if (isset(self::$resolved[$key])) {
            return self::$resolved[$key];
        }
        $match = Router::current();
        $match = null !== $match && $match->articleId === $article->id && $match->languageId === $article->languageId ? $match : null;

        $m = new PageMeta(
            title: $article->name,
            siteName: self::config()->siteName ?? Core::getProject()->instanceName,
            url: '' !== ($c = Router::canonical($article->id, $article->languageId)) ? $c : null,
            locale: Language::get($article->languageId)->code ?? null,
            noindex: !$article->isOnline() ? true : null,
            nofollow: !$article->isOnline() ? true : null,
            modified: $article->updateDate > 0 ? $article->updateDate : null,
        );
        $m->alternates = self::pageAlternates($article);
        $m = Extension::dispatch(new ArticleMeta($m, $article, $match));

        if (null !== $match) {
            if (null !== $match->url) {
                $m->url = Router::absolute($match->url);
                $m->alternates = [];
                foreach ($match->alternates as $lang => $url) {
                    if ('' !== $url && ($l = Language::get($lang)) && $l->isOnline()) {
                        $m->alternates[$l->code] = Router::absolute($url);
                    }
                }
                $m->alternates = self::withDefault($m->alternates);
            }
            if (null !== $match->entry && null !== ($own = Sources::get($match->entry->source)?->meta($match->entry))) {
                $m->merge($own);
            }
            if (null !== $match->meta) {
                $m->merge($match->meta);
            }
        }
        if (null !== self::$override) {
            if (null !== self::$override->url) {
                $m->alternates = [];   // eigene Adresse: Sprachfassungen der Seite passen nicht mehr
            }
            $m->merge(self::$override);
        }
        $m->tags = [...self::tagsOf(self::config()->tags), ...$m->tags, ...self::$tags];
        $m = Extension::dispatch(new PageMetaResolved($m, $article, $match));

        return self::$resolved[$key] = $m;
    }

    /** Titel im Browser-Tab: eigener Titel, sonst „Seite – Website“ (Startseite: Website) */
    public static function title(?Article $article = null): string
    {
        $article ??= Article::getCurrent();
        $m = self::meta($article);
        if (null !== $m->documentTitle && '' !== $m->documentTitle) {
            return $m->documentTitle;
        }
        $site = (string) $m->siteName;
        $isHome = null !== $article && $article->id === Article::getSiteStartArticleId() && null === Router::current()?->entry && null === Router::current()?->url;
        if ($isHome || '' === (string) $m->title) {
            return $site;
        }

        return '' !== $site ? $m->title . self::config()->titleSeparator . $site : (string) $m->title;
    }

    /** Alle Tags des Seitenkopfs (ohne <title>) */
    public static function render(?Article $article = null): string
    {
        $m = self::meta($article);
        $t = [];
        $add = static function (HeadTag $tag) use (&$t): void {
            $t[$tag->key] = $tag;
        };
        if (null !== $m->description && '' !== $m->description) {
            $add(HeadTag::meta('description', $m->description));
        }
        if ($m->noindex || $m->nofollow) {
            $add(HeadTag::meta('robots', ($m->noindex ? 'noindex' : 'index') . ', ' . ($m->nofollow ? 'nofollow' : 'follow')));
        }
        if (null !== $m->url) {
            $add(HeadTag::link('canonical', $m->url));
        }
        if (count($m->alternates) > 1) {
            foreach ($m->alternates as $code => $href) {
                $add(HeadTag::link('alternate', $href, ['hreflang' => $code]));
            }
        }
        $add(HeadTag::property('og:type', $m->type));
        if (null !== $m->url) {
            $add(HeadTag::property('og:url', $m->url));
        }
        $add(HeadTag::property('og:title', (string) ($m->title ?: $m->siteName)));
        if (null !== $m->description && '' !== $m->description) {
            $add(HeadTag::property('og:description', $m->description));
        }
        if (null !== $m->siteName && '' !== $m->siteName) {
            $add(HeadTag::property('og:site_name', $m->siteName));
        }
        if (null !== $m->locale) {
            $add(HeadTag::property('og:locale', self::ogLocale($m->locale)));
            foreach ($m->alternates as $code => $_) {
                if ('x-default' !== $code && $code !== $m->locale) {
                    $t['property:og:locale:alternate:' . $code] = HeadTag::property('og:locale:alternate', self::ogLocale($code));
                }
            }
        }
        $image = null !== $m->image && '' !== $m->image ? self::image($m) : null;
        if (null !== $image) {
            $add(HeadTag::property('og:image', $image['url']));
            foreach (['type', 'width', 'height', 'alt'] as $k) {
                if (null !== ($image[$k] ?? null) && '' !== $image[$k]) {
                    $add(HeadTag::property('og:image:' . $k, (string) $image[$k]));
                }
            }
        }
        if ('article' === $m->type) {
            if (null !== $m->published) {
                $add(HeadTag::property('article:published_time', date(DATE_ATOM, $m->published)));
            }
            if (null !== $m->modified) {
                $add(HeadTag::property('article:modified_time', date(DATE_ATOM, $m->modified)));
            }
        }
        if (null !== ($card = self::config()->twitterCard)) {
            $add(HeadTag::meta('twitter:card', null !== $image ? $card : 'summary'));
        }
        foreach ($m->tags as $tag) {
            $t[$tag->key] = $tag;   // eigene Tags ersetzen die des Routings
        }
        $h = '';
        foreach ($t as $tag) {
            $h .= '' !== ($out = $tag->render()) ? $out . "\n" : '';
        }
        if ([] !== $m->jsonLd) {
            $h .= '<script type="application/ld+json">' . json_encode(['@context' => 'https://schema.org', '@graph' => $m->jsonLd], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>' . "\n";
        }

        return $h;
    }

    /** Absolute Adresse eines Bildes: Datei des Medienpools oder schon absolute Adresse ('' = gibt es nicht) */
    public static function imageUrl(string $fileOrUrl): string
    {
        if ('' === $fileOrUrl || preg_match('~^https?://~i', $fileOrUrl)) {
            return $fileOrUrl;
        }
        $media = Media::get($fileOrUrl);

        return null !== $media ? Router::absolute(str_replace('./', '/', $media->getUrl())) : '';
    }

    /** @return array{url: string, type?: string|null, width?: int|null, height?: int|null, alt?: string|null}|null */
    private static function image(PageMeta $m): ?array
    {
        $url = self::imageUrl((string) $m->image);
        if ('' === $url) {
            return null;
        }
        $media = preg_match('~^https?://~i', (string) $m->image) ? null : Media::get((string) $m->image);

        return [
            'url' => $url,
            'type' => $m->imageType ?? $media?->type,
            'width' => $m->imageWidth ?? (null === $m->imageType ? $media?->width : null),
            'height' => $m->imageHeight ?? (null === $m->imageType ? $media?->height : null),
            'alt' => $m->imageAlt ?? (null !== $media && '' !== ($alt = trim((string) ($media->getValue('meta_alt') ?? $media->getValue('med_alt') ?? ''))) ? $alt : null),
        ];
    }

    /** hreflang der Seite: Sprachen, in denen sie online ist (inkl. x-default) */
    private static function pageAlternates(Article $article): array
    {
        $out = [];
        foreach (Routes::all()[$article->id] ?? [] as $lang => $_) {
            $a = Article::get($article->id, $lang);
            $l = Language::get($lang);
            if ($a && $a->isOnline() && $l && $l->isOnline() && '' !== ($c = Router::canonical($article->id, $lang))) {
                $out[$l->code] = $c;
            }
        }

        return self::withDefault($out);
    }

    /** @param array<string, string> $alternates */
    private static function withDefault(array $alternates): array
    {
        $start = Language::get(Language::getStartId())?->code;
        if (count($alternates) > 1 && null !== $start && isset($alternates[$start])) {
            $alternates['x-default'] = $alternates[$start];
        }

        return $alternates;
    }

    /** „de“ → „de_DE“, „en“ → „en_US“ (Open Graph erwartet Sprache_Land), „de-ch“ → „de_CH“ */
    private static function ogLocale(string $code): string
    {
        if (preg_match('~^([a-z]{2})[-_]([a-z]{2})$~i', $code, $m)) {
            return strtolower($m[1]) . '_' . strtoupper($m[2]);
        }
        $map = ['en' => 'en_US', 'da' => 'da_DK', 'cs' => 'cs_CZ', 'el' => 'el_GR', 'sv' => 'sv_SE', 'ja' => 'ja_JP', 'ko' => 'ko_KR', 'zh' => 'zh_CN', 'uk' => 'uk_UA', 'sl' => 'sl_SI', 'et' => 'et_EE', 'nb' => 'nb_NO'];

        return $map[strtolower($code)] ?? strtolower($code) . '_' . strtoupper($code);
    }

    /** @param list<HeadTag> $tags @return array<string, HeadTag> */
    private static function tagsOf(array $tags): array
    {
        $out = [];
        foreach ($tags as $t) {
            $out[$t->key] = $t;
        }

        return $out;
    }
}

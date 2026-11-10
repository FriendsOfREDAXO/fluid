<?php

namespace FriendsOfRedaxo\Fluid;

use FriendsOfRedaxo\Fluid\Data\Detail;
use FriendsOfRedaxo\Fluid\Data\YFormBridge;
use FriendsOfRedaxo\Fluid\Routing\Router;
use FriendsOfRedaxo\Fluid\Routing\Routes;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\StructurePermission;
use Redaxo\Core\Language\Language;
use Throwable;

use function array_key_exists;
use function parse_url;
use function preg_match;
use function preg_replace;
use function str_contains;
use function str_replace;
use function rawurldecode;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;
use function trim;

use function Redaxo\Core\View\escape;

/**
 * „Ziel bearbeiten“: kleine Aktion an Karten und Kacheln, die auf eine andere Seite oder einen Datensatz zeigen
 * (aus KLXM Studio). Nur im Bearbeiten-Modus und nur mit Recht – Besucher bekommen nichts (keine Abfrage, kein Markup).
 *
 *     <?= $b->targetEdit($it['link'], $it['title']) ?>   als erstes Kind der Karte
 *
 * Verweise wie im Feld „Link“: `redaxo://12` (auch `#anker`) oder eine Adresse dieser Website („/leistungen/marke“,
 * „https://eigene-domain/…“, Detailseiten einer Datenliste „/termine/titel-5“). Fremde Adressen, mailto:, tel:, Anker
 * der Seite und Sonderwerte („phone“, „email“) ergeben nichts.
 *
 *  - Seite (Recht auf ihre Kategorie): öffnet sie im Bearbeiten-Modus.
 *  - Datensatz (Bearbeitungsrecht für die YForm-Tabelle): öffnet die Maske von YForm im Overlay; die Seite bleibt offen
 *    und gibt den Block nach dem Speichern neu aus. Mit Strg/⌘ bzw. ohne Skript: die Maske im Backend.
 */
final class TargetEdit
{
    /** @var array<string, array{kind: string, title: string, href: string}|null> */
    private static array $memo = [];

    /** Die Aktion als HTML – leer für Besucher, ohne Recht und ohne internes Ziel */
    public static function html(?string $ref, ?string $label = null): string
    {
        if (null === $ref || '' === trim($ref) || !Editing::active() || Editing::isGuest()) {
            return '';
        }
        $target = self::target($ref);
        if (null === $target) {
            return '';
        }
        $title = trim(str_replace('*', '', (string) $label)) ?: $target['title'];
        $page = 'page' === $target['kind'];
        $aria = ($page ? 'Seite „' : 'Datensatz „') . $title . '“ bearbeiten';

        return '<a class="cms-target-edit' . ($page ? '' : ' fdl-edit') . '" href="' . escape($target['href']) . '" data-cms-target="' . $target['kind'] . '"'
            . ($page ? '' : ' target="_blank" rel="noopener"')
            . ' aria-label="' . escape($aria) . '" title="' . escape($aria) . '" contenteditable="false">'
            . ($page
                ? '<svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false"><rect x="1.8" y="2.6" width="12.4" height="10.8" rx="1.8" fill="none" stroke="currentColor" stroke-width="1.4"/><path d="M1.8 5.8h12.4" stroke="currentColor" stroke-width="1.4"/></svg>'
                : '<svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false"><ellipse cx="8" cy="3.6" rx="5.2" ry="2" fill="none" stroke="currentColor" stroke-width="1.4"/><path d="M2.8 3.6v8.8c0 1.1 2.3 2 5.2 2s5.2-.9 5.2-2V3.6M2.8 8c0 1.1 2.3 2 5.2 2s5.2-.9 5.2-2" fill="none" stroke="currentColor" stroke-width="1.4"/></svg>')
            . '<span>' . ($page ? 'Seite' : 'Datensatz') . '</span></a>';
    }

    /**
     * Ziel samt Rechteprüfung – null ohne Recht bzw. ohne internes Ziel.
     *
     * @return array{kind: 'page'|'entry', title: string, href: string}|null
     */
    public static function target(string $ref): ?array
    {
        $ref = trim($ref);
        if (array_key_exists($ref, self::$memo)) {
            return self::$memo[$ref];
        }
        try {
            return self::$memo[$ref] = self::lookup($ref);
        } catch (Throwable) {
            return self::$memo[$ref] = null;
        }
    }

    /** @return array{kind: 'page'|'entry', title: string, href: string}|null */
    private static function lookup(string $ref): ?array
    {
        $user = Editing::user();
        if (null === $user || '' === $ref || '#' === $ref[0]) {
            return null;
        }
        if (preg_match('~^(?:redaxo://|page:)?(\d+)(?:#[\w-]+)?$~', $ref, $m)) {
            return self::page(Article::get((int) $m[1]));
        }
        $path = self::localPath($ref);
        if (null === $path) {
            return null;
        }
        [$path, $languageId] = self::language($path);
        if ('' === $path) {
            return self::page(Article::get(Article::getSiteStartArticleId(), $languageId));
        }
        $path = Router::config()->format->strip($path);
        $id = Routes::find($path, $languageId);
        if (null !== $id) {
            return self::page(Article::get($id, $languageId));
        }
        // Detailseite einer Datenliste: /{seite}/{titel}-{id}
        $match = Detail::match($path, $languageId);
        if (null === $match) {
            return null;
        }
        [$pageId, $entryId] = $match;
        $config = Detail::config($pageId, $languageId);
        $table = null !== $config ? YFormBridge::table((string) $config['table']) : null;
        $href = null !== $table ? YFormBridge::editUrl($table, $entryId) : null;   // prüft das Bearbeitungsrecht
        if (null === $href) {
            return null;
        }

        return ['kind' => 'entry', 'title' => Detail::title($config, $entryId) ?? '#' . $entryId, 'href' => $href];
    }

    /** @return array{kind: 'page', title: string, href: string}|null */
    private static function page(?Article $article): ?array
    {
        $user = Editing::user();
        if (null === $article || null === $user) {
            return null;
        }
        $perm = $user->getComplexPerm('structure');
        if (!$user->admin && !($perm instanceof StructurePermission && $perm->hasCategoryPerm($article->categoryId))) {
            return null;
        }
        $url = $article->getUrl();

        return ['kind' => 'page', 'title' => $article->name, 'href' => $url . (str_contains($url, '?') ? '&' : '?') . Editing::PARAM . '=1'];
    }

    /** Pfad einer Adresse dieser Website (ohne Basis, Anfrage, Anker) – null für fremde Adressen und Sonderwerte */
    private static function localPath(string $href): ?string
    {
        if (preg_match('~^(mailto|tel|sms|javascript|data):~i', $href)) {
            return null;
        }
        $u = parse_url($href);
        if (false === $u) {
            return null;
        }
        if (isset($u['host'])) {
            $own = strtolower((string) preg_replace('~:\d+$~', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
            if ('' === $own || strtolower($u['host']) !== $own) {
                return null;
            }
        } elseif (!isset($u['path']) || !str_starts_with($u['path'], '/') || str_starts_with($href, '//')) {
            return null;   // relative Angaben, Sonderwerte des Kits
        }
        $path = (string) ($u['path'] ?? '/');
        $base = (string) parse_url(Router::base(), PHP_URL_PATH);
        if ('' !== $base && '/' !== $base && str_starts_with($path, $base . '/')) {
            $path = substr($path, strlen($base));
        }

        return trim(rawurldecode($path), '/');
    }

    /**
     * Sprachpräfix abtrennen („en/leistungen“ → [„leistungen“, ID von en]); ohne Präfix die aktuelle Sprache.
     *
     * @return array{string, int}
     */
    private static function language(string $path): array
    {
        foreach (Language::getAll() as $language) {
            $prefix = trim(Routes::prefix($language->id), '/');
            if ('' !== $prefix && ($path === $prefix || str_starts_with($path, $prefix . '/'))) {
                return [trim(substr($path, strlen($prefix)), '/'), $language->id];
            }
        }

        return [$path, Language::getCurrentId()];
    }
}

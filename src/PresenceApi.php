<?php

namespace FriendsOfRedaxo\Fluid;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\StructurePermission;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Security\User;

use function Redaxo\Core\View\escape;

/**
 * Präsenz der Redaktion (Presence) – Anbindung an fluid: Artikel je Sprache (type „article“, id = „{sprache}:{artikel}“).
 * Endpunkt /fluid-presence, Antworten nach dem Schema von KLXM Studio (dasselbe Skript assets/editor/presence.js):
 *
 *   POST {type, id, label?, url?, leave?, peek?}   → {on, me, here: [Person…], last: ?Stand, live: Abo}
 *   GET                                            → {on, me, items: [{type, id, label, url, …Person}], live: Abo}
 *   Person = {user, name, initials, avatar, since, at, self}    Stand = {user, name, at, self} (zuletzt gespeichert, aus der Aktivität)
 *
 * Nur für angemeldete Backend-Benutzer mit Recht auf den Artikel; Besucher sehen nie etwas, der Artikel-Cache bleibt
 * unberührt. Abschaltbar in der Konfiguration des AddOns (presence = false).
 */
final class PresenceApi
{
    /** Endpunkt der Präsenz beim Routing anmelden – erst nach allen AddOns */
    public static function boot(): void
    {
        \FriendsOfRedaxo\Fluid\Routing\Router::endpoint('/' . self::PATH, static fn () => self::handle(), afterPackages: true, early: true);
    }

    public const string PATH = 'fluid-presence';
    public const string SITE = 'default';
    private const array SAVES = ['save', 'publish', 'restore', 'discard', 'block_add', 'block_delete', 'page_settings'];

    public static function on(): bool
    {
        return (bool) FluidAddon::instance()->getConfig('presence', true);
    }

    public static function endpoint(): string
    {
        return Routing\Router::base() . '/' . self::PATH;
    }

    /** Texte für presence.js (wie die Leiste: Sie) */
    public static function texts(): array
    {
        return ['editing1' => '{names} bearbeitet gerade', 'editingN' => '{names} bearbeiten gerade', 'last' => 'Zuletzt gespeichert: {name}, {ago}',
            'now' => 'gerade eben', 'min' => 'vor {n} Min.', 'hour' => 'vor {n} Std.', 'day' => 'am {date}',
            'conflict' => '{name} hat inzwischen gespeichert', 'conflictShort' => 'Inzwischen gespeichert', 'conflictTitle' => 'Inzwischen von {name} gespeichert',
            'conflictBody' => 'Seit Sie diese Seite geöffnet haben, hat {name} gespeichert ({ago}). Wenn Sie jetzt speichern, kann das diese Änderungen überschreiben – der vorige Stand bleibt als Version erhalten.',
            'conflictOk' => 'Trotzdem speichern', 'cancel' => 'Abbrechen'];
    }

    private static function common(): string
    {
        return ' data-presence-endpoint="' . escape(self::endpoint()) . '" data-presence-live="' . escape(Live::endpoint()) . '"'
            . ' data-presence-texts="' . escape((string) json_encode(self::texts(), JSON_UNESCAPED_UNICODE)) . '"';
    }

    /**
     * Angaben für die Leiste (Toolbar): Attribute für [data-presence-here] und für Übersichten (Seitenbaum, „Gerade aktiv“).
     *
     * @return array{here: string, list: string, board: string, js: string}|null
     */
    public static function toolbar(Article $article, bool $editing): ?array
    {
        if (!self::on()) {
            return null;
        }
        $a = FluidAddon::instance();
        $file = $a->getPath('assets/editor/presence.js');

        return [
            'here' => ' data-presence-here data-presence-type="article" data-presence-id="' . $article->languageId . ':' . $article->id . '"'
                . ' data-presence-label="' . escape($article->name) . '" data-presence-url="' . escape(Url::article($article->id, $article->languageId, [Editing::PARAM => 1])) . '"'
                . ' data-presence-csrf="' . escape(CsrfToken::factory(Api::CSRF)->getValue()) . '" data-presence-editing="' . ($editing ? '1' : '0') . '"' . self::common(),
            'list' => ' data-presence-list data-presence-type="article" data-presence-prefix="' . $article->languageId . ':"' . self::common(),
            'board' => ' data-presence-board' . self::common(),
            'js' => $a->getAssetsUrl('editor/presence.js') . '?v=' . (is_file($file) ? substr(md5((string) filemtime($file)), 0, 8) : '0'),
        ];
    }

    /** /fluid-presence (Router, nach PACKAGES_INCLUDED) */
    public static function handle(): never
    {
        $user = Editing::user();
        if (!$user || Editing::isGuest()) {   // Gastzugang (z. B. AddOn fluid_demo): Gäste erscheinen nicht in der Präsenz
            self::send(['on' => false, 'error' => 'Keine Berechtigung.'], 403);
        }
        if (!self::on()) {
            self::send(['on' => false, 'items' => []]);
        }
        $me = (string) $user->id;
        if ('POST' !== ($_SERVER['REQUEST_METHOD'] ?? 'GET')) {
            $items = array_map(static fn ($e) => ['type' => (string) $e['type'], 'id' => (string) $e['id'], 'label' => (string) ($e['label'] ?? ''), 'url' => (string) ($e['url'] ?? '')] + self::out($e, $me),
                array_values(array_filter(Presence::list(['site' => self::SITE]), static fn ($e) => null !== self::article((string) $e['type'], (string) $e['id'], $user))));
            self::send(['on' => true, 'me' => $me, 'items' => $items, 'live' => Live::token(['presence:' . self::SITE])]);
        }
        // JSON (fetch) oder Formulardaten (sendBeacon beim Verlassen)
        $in = str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'json') ? (array) json_decode((string) file_get_contents('php://input'), true) : $_POST;
        $csrf = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $in['_csrf'] ?? '');
        $_REQUEST[CsrfToken::PARAM] = $csrf;   // Token ist bei jeder Ausgabe maskiert – Prüfung über den Core
        if ('' === $csrf || !CsrfToken::factory(Api::CSRF)->isValid()) {
            self::send(['on' => true, 'error' => 'Sitzung abgelaufen.'], 419);
        }
        $type = (string) ($in['type'] ?? '');
        $id = (string) ($in['id'] ?? '');
        $article = self::article($type, $id, $user);
        if (!$article) {
            self::send(['on' => true, 'error' => 'Unbekannt.'], 404);
        }
        $who = ['site' => self::SITE, 'type' => $type, 'id' => $id, 'user' => $me];
        if (!empty($in['leave'])) {
            Presence::leave($who);
            self::send(['on' => true, 'left' => true]);
        }
        // peek: nur nachsehen (Ansehen-Modus) – ohne sich selbst als „bearbeitet gerade“ zu melden
        if (empty($in['peek'])) {
            Presence::beat($who, self::person($user) + ['label' => mb_substr((string) ($in['label'] ?? $article->name), 0, 120), 'url' => self::localUrl((string) ($in['url'] ?? ''))]);
        }
        $here = array_map(static fn ($e) => self::out($e, $me), Presence::list(['site' => self::SITE, 'type' => $type, 'id' => $id]));
        self::send(['on' => true, 'me' => $me, 'here' => $here, 'last' => self::last($article, $user), 'live' => Live::token(['presence:' . self::SITE])]);
    }

    /** Artikel zu type/id, wenn die Person ihn bearbeiten darf – sonst null */
    private static function article(string $type, string $id, User $user): ?Article
    {
        if ('article' !== $type || !preg_match('~^(\d+):(\d+)$~', $id, $m)) {
            return null;
        }
        $article = Article::get((int) $m[2], (int) $m[1]);
        $perm = $user->getComplexPerm('structure');

        return $article && $perm instanceof StructurePermission && $perm->hasCategoryPerm($article->categoryId) ? $article : null;
    }

    /** nur Pfade derselben Website (Link aus Übersichten) */
    private static function localUrl(string $url): string
    {
        return preg_match('~^/(?!/)[^\s"<>]*$~', $url) ? mb_substr($url, 0, 300) : '';
    }

    private static function name(User $u): string
    {
        return (string) ($u->name ?: $u->login);
    }

    private static function person(User $u): array
    {
        preg_match_all('~\pL[\pL\pN]*~u', preg_replace('~\(.*?\)~u', '', self::name($u)) ?? '', $m);   // Wörter ohne Klammerzusatz
        $parts = $m[0] ?: ['?'];
        $ini = mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1) . (\count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : ''));

        return ['name' => self::name($u), 'initials' => $ini, 'avatar' => ''];
    }

    private static function out(array $e, string $me): array
    {
        return ['user' => (string) $e['user'], 'name' => (string) ($e['name'] ?? ''), 'initials' => (string) ($e['initials'] ?? ''), 'avatar' => (string) ($e['avatar'] ?? ''),
            'since' => (int) $e['since'], 'at' => (int) $e['at'], 'self' => (string) $e['user'] === $me];
    }

    /** Zuletzt gespeichert (Aktivität): Name und Zeitpunkt – die Aktivität kennt Namen, keine Benutzer-IDs */
    private static function last(Article $article, User $user): ?array
    {
        try {
            $row = Sql::factory()->getArray('SELECT user, created FROM ' . Activity::TABLE . ' WHERE article_id = ? AND action IN (' . implode(',', array_fill(0, \count(self::SAVES), '?')) . ') ORDER BY id DESC LIMIT 1',
                [$article->id, ...self::SAVES])[0] ?? null;
        } catch (\Throwable) {
            return null;
        }
        if (!$row) {
            return null;
        }

        return ['user' => (string) $row['user'], 'name' => (string) $row['user'], 'at' => (int) strtotime((string) $row['created']), 'self' => (string) $row['user'] === self::name($user)];
    }

    private static function send(array $data, int $status = 200): never
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

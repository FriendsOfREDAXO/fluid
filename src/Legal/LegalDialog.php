<?php

namespace FriendsOfRedaxo\Fluid\Legal;

use FriendsOfRedaxo\Fluid\BlockModule;
use FriendsOfRedaxo\Fluid\FluidAddon;
use FriendsOfRedaxo\Fluid\Routing\Attribute\AsEndpoint;
use FriendsOfRedaxo\Fluid\Routing\Router;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Language\Language;

use function array_key_exists;
use function html_entity_decode;
use function htmlspecialchars;
use function http_response_code;
use function is_array;
use function is_string;
use function json_encode;
use function nl2br;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function rawurlencode;
use function str_contains;
use function strip_tags;
use function strtolower;
use function trim;

use function Redaxo\Core\View\escape;

/**
 * Rechtstexte im Dialog (optional, aus KLXM Studio – Core\LegalDialog): Der Link „Datenschutzhinweise“ an Formularen
 * öffnet die Datenschutzerklärung über dem Formular statt auf einer neuen Seite – Eingaben bleiben erhalten.
 *
 * Einschalten in Project::configure(): `Legal::$config = new LegalConfig(dialog: true)`. Dann liefert
 * `GET /_legal/privacy?lang=ID` Titel und bereinigtes HTML der Datenschutzseite (Überschriften, Absätze, Listen, Links –
 * ohne Knöpfe, Bilder, Formulare), und LegalDialog::attrs() markiert Links für assets/js/legal-dialog.js. Ohne
 * JavaScript, mit Strg/⌘ oder bei Fehlern bleibt es ein gewöhnlicher Link.
 *
 *     <a href="<?= escape(Legal::url('privacy')) ?>"<?= LegalDialog::attrs() ?>>Datenschutzhinweise</a>
 */
final class LegalDialog
{
    private static bool $assets = false;

    /** Attribute für einen Link zur Datenschutzseite – leer, wenn der Dialog aus ist; beim ersten Aufruf auch das Skript */
    public static function attrs(string $type = 'privacy'): string
    {
        if (!Legal::config()->dialog || null === Legal::article($type)) {
            return '';
        }
        $addon = FluidAddon::instance();

        return ' data-legal-src="' . escape(Router::base() . '/_legal/' . $type . '?lang=' . rawurlencode((string) Language::getCurrentId())) . '"'
            . ' data-legal-css="' . escape($addon->getAssetsUrl('css/legal-dialog.css')) . '"';
    }

    /** Skript des Dialogs – einmal je Seite, nur wenn der Dialog an ist */
    public static function script(): string
    {
        if (self::$assets || !Legal::config()->dialog) {
            return '';
        }
        self::$assets = true;

        return '<script type="module" src="' . escape(FluidAddon::instance()->getAssetsUrl('js/legal-dialog.js')) . '"></script>';
    }

    #[AsEndpoint('/_legal/*')]
    public static function serve(string $path, string $rest): void
    {
        $type = trim($rest, '/');
        $languageId = (int) ($_GET['lang'] ?? 0);
        if (!Legal::config()->dialog || !array_key_exists($type, Legal::KEYS) || !Language::exists($languageId)) {
            http_response_code(404);
            Router::respond('{"ok":false}', 'application/json');
        }
        Language::setCurrentId($languageId);
        $article = Legal::article($type);
        if (null === $article) {
            http_response_code(404);
            Router::respond('{"ok":false}', 'application/json');
        }
        $t = static fn (string $s): string => \function_exists('lt') ? lt($s) : $s;
        Router::respond((string) json_encode([
            'ok' => true,
            'title' => $article->name,
            'html' => self::fragment($article),
            'url' => $article->getUrl(),
            'close' => $t('Schließen'),
            'open' => $t('Als Seite öffnen'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'application/json; charset=utf-8', ['Cache-Control: public, max-age=300', 'X-Robots-Tag: noindex']);
    }

    /** Text der veröffentlichten Blöcke: Überschriften, Einleitung, Fließtext (bereinigt), Einträge (z. B. FAQ) */
    public static function fragment(Article $article): string
    {
        $rows = Sql::factory()->getArray(
            'SELECT data FROM rex_article_slice WHERE article_id = ? AND language_id = ? AND revision = ? AND status = 1 ORDER BY content_section_id, priority',
            [$article->id, $article->languageId, ArticleRevision::LIVE],
        );
        $h = '';
        foreach ($rows as $row) {
            $d = BlockModule::stored((string) $row['data'])['values'];
            if ('' !== trim((string) ($d['title'] ?? ''))) {
                $h .= '<h2>' . self::plain((string) $d['title']) . '</h2>';
            }
            foreach (['intro', 'lead', 'text', 'body', 'content'] as $k) {
                if (is_string($d[$k] ?? null)) {
                    $h .= self::text($d[$k]);
                }
            }
            foreach ((array) ($d['items'] ?? []) as $it) {
                if (!is_array($it)) {
                    continue;
                }
                foreach (['title', 'question', 'q', 'label'] as $k) {
                    if ('' !== trim((string) ($it[$k] ?? ''))) {
                        $h .= '<h3>' . self::plain((string) $it[$k]) . '</h3>';
                        break;
                    }
                }
                foreach (['answer', 'a', 'text', 'body'] as $k) {
                    if (is_string($it[$k] ?? null)) {
                        $h .= self::text($it[$k]);
                    }
                }
            }
        }

        return $h;
    }

    private static function plain(string $s): string
    {
        return htmlspecialchars(trim((string) preg_replace('~\*([^*]+)\*~u', '$1', $s)));
    }

    /** Fließtext: HTML auf wenige Tags ohne Attribute (außer href von Links) reduziert, Klartext als Absatz */
    private static function text(string $v): string
    {
        $v = trim($v);
        if ('' === $v) {
            return '';
        }
        if (!str_contains($v, '<')) {
            return '<p>' . nl2br(htmlspecialchars($v), false) . '</p>';
        }
        $v = strip_tags($v, '<h2><h3><h4><p><br><ul><ol><li><a><strong><b><em><i>');
        // Attribute entfernen – nur sichere href an Links bleiben
        $v = (string) preg_replace_callback('~<(\w+)(\s[^>]*)?>~', static function (array $m): string {
            if ('a' === strtolower($m[1]) && preg_match('~\shref\s*=\s*"((?:https?:|mailto:|tel:|/|#)[^"]*)"~i', $m[2] ?? '', $h)) {
                return '<a href="' . htmlspecialchars(html_entity_decode($h[1])) . '">';
            }

            return '<' . $m[1] . '>';
        }, $v);

        return $v;
    }
}

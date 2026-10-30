<?php

namespace FriendsOfRedaxo\Fluid\Backend;

use FriendsOfRedaxo\Fluid\Kit;
use FriendsOfRedaxo\Fluid\Mail\Mail;
use FriendsOfRedaxo\Fluid\Media\Ffmpeg;
use FriendsOfRedaxo\Fluid\Routing\Domains;
use FriendsOfRedaxo\Fluid\Routing\Redirects;
use FriendsOfRedaxo\Fluid\Routing\Routes;
use FriendsOfRedaxo\Fluid\Search\Search;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\View\Component\Section;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\Message;

use function Redaxo\Core\View\escape;

/**
 * Backend → AddOns → fluid → Status: Prüfung der Umgebung und Wartung (Adressen, Suchindex, Karten-Cache).
 *
 * @internal
 */
final class StatusPage
{
    private const string CSRF = 'fluid_status';

    public static function run(): string
    {
        $out = self::action();
        $start = Article::getSiteStartArticle();
        $kit = Kit::active();
        $routes = (int) (Sql::factory()->getArray('SELECT COUNT(*) AS n FROM ' . Routes::TABLE)[0]['n'] ?? 0);
        $n404 = (int) (Sql::factory()->getArray('SELECT COUNT(*) AS n FROM ' . Redirects::TABLE_404 . ' WHERE last_seen > NOW() - INTERVAL 30 DAY')[0]['n'] ?? 0);
        $primary = Domains::primary();
        $mail = Mail::status();
        $site = $start ? $start->getUrl(['fluid_edit' => 1]) : \Redaxo\Core\Filesystem\Url::frontend();

        // [Status, Bereich, Text]; Status: ok | warn | info
        $rows = [
            [$start ? 'ok' : 'warn', 'Startseite', $start ? escape($start->name) . ' – Template ' . escape((string) $start->templateKey) . ('fluid' === $start->templateKey ? '' : ' (nicht das Template von fluid)') : 'Noch keine Seiten – die Website zeigt „Erste Schritte“.'],
            ['ok', 'Kit', escape($kit->label()) . ' (' . escape($kit->key()) . ')' . (null !== ($parent = $kit->parent()) ? ' – erbt von ' . escape($parent->label()) : '')
                . (\count(Kit::all()) > 1 ? ' · ' . \count(Kit::all()) . ' Kits verfügbar, Auswahl unter Website-Einstellungen → Kit' : '')],
            [Core::getConfig('article_work_version') ? 'ok' : 'warn', 'Arbeitsversion', Core::getConfig('article_work_version') ? 'an – Änderungen erst als Entwurf, dann „Veröffentlichen“' : 'aus – Änderungen gehen sofort live (System → Einstellungen → Arbeitsversion)'],
            [$routes > 0 ? 'ok' : 'warn', 'Schöne Adressen', $routes . ' Adressen. Der Webserver muss Anfragen ohne Datei an index.php geben (README → Webserver). Test: <a href="' . escape(\Redaxo\Core\Filesystem\Url::frontend() . 'sitemap.xml') . '" target="_blank" rel="noopener">sitemap.xml</a>'],
            [$primary ? 'ok' : 'info', 'Hauptadresse', $primary ? escape($primary->host) : 'nicht festgelegt – Adressen folgen dem Aufruf (Website → Domains)'],
            ['info', 'Sprachen', \count(Language::getAll()) . ' (' . escape(implode(', ', array_map(static fn (Language $l) => $l->code . ($l->isOnline() ? '' : ' offline'), Language::getAll()))) . ')'],
            [$mail['configured'] && '' !== $mail['from'] ? 'ok' : 'warn', 'Mail', '' !== $mail['error']
                ? 'nicht eingerichtet: ' . escape($mail['error']) . ' – MAILER_DSN in .env.local setzen'
                : ($mail['configured'] ? escape($mail['transport']) . ('' !== $mail['from'] ? ' · Absender ' . escape($mail['from']) : ' · REX_MAILER_FROM fehlt') : 'MAILER_DSN „null://“ – Mails werden verworfen')
                . ($mail['detour'] ? ' · <strong>alle Mails an ' . escape(implode(', ', $mail['detour'])) . '</strong> (REX_MAILER_RECIPIENTS)' : '') . ' – Details unten unter „E-Mail“'],
            [\extension_loaded('pdo_sqlite') ? 'ok' : 'warn', 'Suche', \extension_loaded('pdo_sqlite') ? 'Loupe (SQLite) verfügbar' : 'PHP-Erweiterung pdo_sqlite fehlt – Block „Suche“ ohne Treffer'],
            [Ffmpeg::available() ? 'ok' : 'info', 'Video-Vorschaubilder', Ffmpeg::available() ? 'ffmpeg ' . escape(Ffmpeg::bin()) : 'ffmpeg nicht gefunden – nur gewählte Vorschaubilder (Pfad über FFMPEG_BIN/FFPROBE_BIN)'],
            [Addon::exists('yform') ? 'ok' : 'info', 'YForm', Addon::exists('yform') ? 'vorhanden – Blöcke „Formular“, „Datenliste“, Postfach' : 'nicht installiert – Formulare und Datenlisten ausgeblendet'],
            [class_exists(\FriendsOfRedaxo\ImageEditor\ImageEditorAddon::class) ? 'ok' : 'info', 'Bildbearbeitung', class_exists(\FriendsOfRedaxo\ImageEditor\ImageEditorAddon::class) ? 'image_editor vorhanden' : 'optional: friendsofredaxo/image_editor'],
            [$n404 > 0 ? 'info' : 'ok', '404-Protokoll', $n404 . ' nicht gefundene Adressen in 30 Tagen – auf der Website unter Website → Weiterleitungen'],
        ];
        // Kopf: Zusammenfassung (Ampel) und Absprung auf die Website
        $count = array_count_values(array_column($rows, 0)) + ['ok' => 0, 'warn' => 0, 'info' => 0];
        $state = $count['warn'] > 0 ? 'warn' : 'ok';
        $out .= '<div class="fluid-status-hero" data-state="' . $state . '">'
            . '<div class="fluid-status-hero__icon" aria-hidden="true">' . self::icon($state) . '</div>'
            . '<div class="fluid-status-hero__text"><h2>' . ('warn' === $state ? $count['warn'] . ' ' . (1 === $count['warn'] ? 'Punkt braucht' : 'Punkte brauchen') . ' Aufmerksamkeit' : 'Alles bereit') . '</h2>'
            . '<p>' . $count['ok'] . ' in Ordnung · ' . $count['info'] . ' ' . (1 === $count['info'] ? 'Hinweis' : 'Hinweise') . ($count['warn'] ? ' · ' . $count['warn'] . ' zu prüfen' : '') . '</p></div>'
            . '<a class="rex-button" data-variant="primary" href="' . escape($site) . '"><i class="rex-icon fa-solid fa-pen-to-square" aria-hidden="true"></i> Auf der Website bearbeiten</a>'
            . '</div>';

        // Prüfungen als Kacheln, Warnungen zuerst
        $order = ['warn' => 0, 'info' => 1, 'ok' => 2];
        usort($rows, static fn (array $a, array $b) => $order[$a[0]] <=> $order[$b[0]]);
        $label = ['ok' => 'in Ordnung', 'warn' => 'prüfen', 'info' => 'Hinweis'];
        $tiles = '<ul class="fluid-status-checks">';
        foreach ($rows as [$st, $name, $text]) {
            $tiles .= '<li class="fluid-status-check" data-state="' . $st . '">'
                . '<span class="fluid-status-check__icon">' . self::icon($st) . '<span class="rex-u-visually-hidden">' . $label[$st] . ': </span></span>'
                . '<div><h3>' . escape($name) . '</h3><p>' . $text . '</p></div></li>';
        }
        $tiles .= '</ul>';
        $out .= new Section(title: 'Umgebung', body: Html::raw($tiles))->render();

        $csrf = CsrfToken::factory(self::CSRF)->getHiddenField();
        $out .= self::mailSection($mail, $csrf);

        // Wartung: je Aufgabe eine Kachel mit Erklärung
        $task = static fn (string $do, string $icon, string $title, string $help, string $button) => '<form method="post" class="fluid-status-task">' . $csrf
            . '<span class="fluid-status-task__icon" aria-hidden="true"><i class="rex-icon fa-solid ' . $icon . '"></i></span><h3>' . escape($title) . '</h3><p>' . escape($help) . '</p>'
            . '<p class="fluid-status-task__action"><button class="rex-button" data-size="sm" name="fluid_do" value="' . $do . '">' . escape($button) . '</button></p></form>';
        $out .= new Section(
            title: 'Wartung',
            body: Html::raw('<p class="rex-u-text-muted">Normalerweise nicht nötig – fluid hält das selbst aktuell.</p><div class="fluid-status-tasks">'
                . $task('routes', 'fa-route', 'Adressen', 'Nach einem Import oder einer kopierten Datenbank.', 'Neu aufbauen')
                . $task('search', 'fa-magnifying-glass', 'Suchindex', 'Auch per Konsole: fluid:search-index', 'Neu aufbauen')
                . $task('proxy', 'fa-map-location-dot', 'Karten-Cache', 'Kacheln und Stile der Karten (' . self::size(Path::addonCache('fluid', 'proxy')) . '), werden neu geladen.', 'Leeren')
                . '</div>'),
        )->render();

        return $out;
    }

    private static function action(): string
    {
        $do = Request::post('fluid_do', 'string', '');
        if ('' === $do) {
            return '';
        }
        if (!CsrfToken::factory(self::CSRF)->isValid()) {
            return Message::error('Sitzung abgelaufen – bitte noch einmal.');
        }

        return match ($do) {
            'routes' => (static function () {
                Routes::rebuild();

                return Message::success('Adressen neu aufgebaut.');
            })(),
            'search' => Message::success(Search::rebuild() . ' Seiten im Suchindex.'),
            'mailtest' => (static function () {
                $to = trim(Request::post('mail_to', 'string', ''));
                if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    return Message::error('Bitte eine gültige E-Mail-Adresse angeben.');
                }
                $err = Mail::test($to);

                return '' === $err ? Message::success('Testmail an ' . escape($to) . ' gesendet.') : Message::error('Testmail nicht gesendet: ' . escape($err));
            })(),
            'resend' => (static function () {
                $err = Mail::resend(Request::post('mail_file', 'string', ''));

                return '' === $err ? Message::success('Mail erneut gesendet.') : Message::error('Nicht gesendet: ' . escape($err));
            })(),
            'proxy' => (static function () {
                Dir::delete(Path::addonCache('fluid', 'proxy'));

                return Message::success('Karten-Cache geleert.');
            })(),
            default => '',
        };
    }

    /**
     * Abschnitt „E-Mail“: Einrichtung, Testmail, letzte Fehler, nicht gesendete Mails (erneut senden).
     *
     * @param array<string, mixed> $mail
     */
    private static function mailSection(array $mail, string $csrf): string
    {
        $keep = trim((string) \FriendsOfRedaxo\Fluid\Settings::get('mail_keep_days', ''));
        $facts = [
            'Versand' => '' !== $mail['error'] ? '<span class="rex-u-text-danger">' . escape($mail['error']) . '</span>' : '<code>' . escape($mail['transport']) . '</code>',
            'Absender' => '' !== $mail['from'] ? escape($mail['from']) : '<span class="rex-u-text-warning">REX_MAILER_FROM fehlt – Mails ohne eigenen Absender scheitern</span>',
            'Umleitung' => $mail['detour'] ? 'alle Mails an <strong>' . escape(implode(', ', $mail['detour'])) . '</strong>' : 'aus – Mails gehen an die echten Empfänger',
            'Protokoll' => escape($mail['log']) . ' <span class="rex-u-text-muted">(REX_MAILER_LOG: none, errors, all)</span>',
            'Archiv' => $mail['archive'] ? 'an – Aufbewahrung ' . ('' === $keep ? Mail::KEEP_DAYS . ' Tage (Standard)' : ('0' === $keep ? 'unbegrenzt' : (int) $keep . ' Tage')) . ', Cronjob „fluid_mail_archive_purge“' : 'aus <span class="rex-u-text-muted">(REX_MAILER_ARCHIVE=1 bewahrt Mails als .eml auf, nicht gesendete lassen sich dann erneut senden)</span>',
        ];
        $body = '<dl class="fluid-status-facts">';
        foreach ($facts as $k => $v) {
            $body .= '<dt>' . escape($k) . '</dt><dd>' . $v . '</dd>';
        }
        $body .= '</dl>';

        $me = (string) (Core::getUser()->email ?? '');
        $body .= '<form method="post" class="fluid-status-mailtest">' . $csrf
            . '<label class="rex-field-label" for="fluid-mail-to">Testmail senden an</label>'
            . '<div class="rex-input-group"><input id="fluid-mail-to" class="rex-input" type="email" name="mail_to" required value="' . escape($me) . '" placeholder="name@example.org">'
            . '<span class="rex-input-group-actions"><button class="rex-button" data-variant="field" name="fluid_do" value="mailtest"><i class="rex-icon fa-solid fa-paper-plane" aria-hidden="true"></i> Senden</button></span></div></form>';

        $table = static function (array $items, string $last, ?\Closure $action = null): string {
            $h = '<table class="rex-table" data-density="compact" data-hover><thead><tr><th class="rex-table-date">Zeit</th><th>An</th><th>Betreff</th><th>' . $last . '</th></tr></thead><tbody>';
            foreach ($items as $m) {
                $h .= '<tr><td class="rex-table-date rex-u-nowrap" data-title="Zeit">' . escape(date('d.m.Y H:i', $m['time'])) . '</td>'
                    . '<td data-title="An">' . escape($m['to']) . '</td><td data-title="Betreff">' . escape($m['subject']) . '</td>'
                    . '<td data-title="' . $last . '">' . ($action ? $action($m) : '<span class="rex-u-text-danger rex-u-small">' . escape($m['error']) . '</span>') . '</td></tr>';
            }

            return $h . '</tbody></table>';
        };
        if ($mail['errors']) {
            $body .= '<h3 class="fluid-status-sub">Letzte Fehler</h3>' . $table($mail['errors'], 'Fehler');
        }
        $failed = Mail::failed();
        if ($failed) {
            $body .= '<h3 class="fluid-status-sub">Nicht gesendet (Archiv)</h3><p class="rex-field-note">„Erneut senden“ geht an die ursprünglichen Empfänger, auch Bcc, sofern der Core sie im Archiv vermerkt (X-Envelope-To); bei älteren Mails an An und Kopie.</p>'
                . $table($failed, 'Aktion', static fn (array $m) => '<form method="post">' . $csrf . '<input type="hidden" name="mail_file" value="' . escape($m['file']) . '">'
                    . '<button class="rex-button" data-size="xs" name="fluid_do" value="resend">Erneut senden</button></form>');
        }

        return (string) new Section(title: 'E-Mail', body: Html::raw($body))->render();
    }

    /** Symbol je Zustand (ok, warn, info) */
    private static function icon(string $state): string
    {
        return '<i class="rex-icon fa-solid ' . ['ok' => 'fa-circle-check', 'warn' => 'fa-triangle-exclamation', 'info' => 'fa-circle-info'][$state] . '" aria-hidden="true"></i>';
    }

    private static function size(string $dir): string
    {
        $b = 0;
        if (is_dir($dir)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
                $b += $file->getSize();
            }
        }

        return $b > 1048576 ? round($b / 1048576, 1) . ' MB' : round($b / 1024) . ' KB';
    }
}

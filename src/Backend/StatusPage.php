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
use Redaxo\Core\View\Fragment;
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
        $icon = ['ok' => '<i class="rex-icon fa-check text-success"></i>', 'warn' => '<i class="rex-icon fa-triangle-exclamation text-warning"></i>', 'info' => '<i class="rex-icon fa-circle-info text-muted"></i>'];
        $table = '<table class="table table-hover"><tbody>';
        foreach ($rows as [$st, $label, $text]) {
            $table .= '<tr><td class="rex-table-icon">' . $icon[$st] . '</td><th scope="row" style="width:12rem">' . escape($label) . '</th><td>' . $text . '</td></tr>';
        }
        $table .= '</tbody></table>';

        $f = new Fragment();
        $f->setVar('title', 'Status', false);
        $f->setVar('options', '<a class="btn btn-xs btn-primary" href="' . escape($site) . '">Auf der Website bearbeiten</a>', false);
        $f->setVar('content', $table, false);
        $out .= $f->parse('core/page/section.php');

        $csrf = CsrfToken::factory(self::CSRF)->getHiddenField();
        $btn = static fn (string $do, string $label, string $help) => '<form method="post" style="display:inline-block;margin:0 .5rem .5rem 0">' . $csrf
            . '<button class="btn btn-default" name="fluid_do" value="' . $do . '" title="' . escape($help) . '">' . escape($label) . '</button></form>';
        $out .= self::mailSection($mail, $csrf);

        $f = new Fragment();
        $f->setVar('title', 'Wartung', false);
        $f->setVar('body', '<p>Normalerweise nicht nötig – fluid hält das selbst aktuell.</p>'
            . $btn('routes', 'Adressen neu aufbauen', 'nach Import oder Datenbank-Kopie')
            . $btn('search', 'Suchindex neu aufbauen', 'auch per Konsole: fluid:search-index')
            . $btn('proxy', 'Karten-Cache leeren (' . self::size(Path::addonCache('fluid', 'proxy')) . ')', 'Kacheln und Stile werden neu geladen'), false);
        $out .= $f->parse('core/page/section.php');

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
        $row = static fn (string $label, string $html) => '<tr><th scope="row" style="width:12rem">' . escape($label) . '</th><td>' . $html . '</td></tr>';
        $keep = trim((string) \FriendsOfRedaxo\Fluid\Settings::get('mail_keep_days', ''));
        $body = '<table class="table"><tbody>'
            . $row('Versand', '' !== $mail['error'] ? '<span class="text-danger">' . escape($mail['error']) . '</span>' : escape($mail['transport']))
            . $row('Absender', '' !== $mail['from'] ? escape($mail['from']) : '<span class="text-warning">REX_MAILER_FROM fehlt – Mails ohne eigenen Absender scheitern</span>')
            . $row('Umleitung', $mail['detour'] ? 'alle Mails an <strong>' . escape(implode(', ', $mail['detour'])) . '</strong>' : 'aus – Mails gehen an die echten Empfänger')
            . $row('Protokoll', escape($mail['log']) . ' (REX_MAILER_LOG: none, errors, all)')
            . $row('Archiv', $mail['archive'] ? 'an – Aufbewahrung ' . ('' === $keep ? Mail::KEEP_DAYS . ' Tage (Standard)' : ('0' === $keep ? 'unbegrenzt' : (int) $keep . ' Tage')) . ', Cronjob „fluid_mail_archive_purge“' : 'aus (REX_MAILER_ARCHIVE=1 bewahrt Mails als .eml auf, nicht gesendete lassen sich dann erneut senden)')
            . '</tbody></table>';

        $me = (string) (Core::getUser()->email ?? '');
        $body .= '<form method="post" class="form-inline" style="margin:0 0 1rem">' . $csrf
            . '<label for="fluid-mail-to" style="margin-right:.5rem">Testmail an</label><input id="fluid-mail-to" class="form-control" type="email" name="mail_to" required value="' . escape($me) . '" style="width:18rem;margin-right:.5rem">'
            . '<button class="btn btn-default" name="fluid_do" value="mailtest">Testmail senden</button></form>';

        if ($mail['errors']) {
            $body .= '<h4>Letzte Fehler</h4><table class="table table-condensed"><tbody>';
            foreach ($mail['errors'] as $e) {
                $body .= '<tr><td style="white-space:nowrap">' . escape(date('d.m.Y H:i', $e['time'])) . '</td><td>' . escape($e['to']) . '</td><td>' . escape($e['subject']) . '</td><td class="text-danger">' . escape($e['error']) . '</td></tr>';
            }
            $body .= '</tbody></table>';
        }

        $failed = Mail::failed();
        if ($failed) {
            $body .= '<h4>Nicht gesendet (Archiv)</h4><p class="help-block">„Erneut senden“ geht an die Empfänger aus An und Kopie. Bcc-Empfänger stehen nicht im Archiv und bekommen die Mail nicht erneut.</p><table class="table table-condensed"><tbody>';
            foreach ($failed as $m) {
                $body .= '<tr><td style="white-space:nowrap">' . escape(date('d.m.Y H:i', $m['time'])) . '</td><td>' . escape($m['to']) . '</td><td>' . escape($m['subject']) . '</td><td style="text-align:right">'
                    . '<form method="post" style="margin:0">' . $csrf . '<input type="hidden" name="mail_file" value="' . escape($m['file']) . '">'
                    . '<button class="btn btn-xs btn-default" name="fluid_do" value="resend">Erneut senden</button></form></td></tr>';
            }
            $body .= '</tbody></table>';
        }

        $f = new Fragment();
        $f->setVar('title', 'E-Mail', false);
        $f->setVar('body', $body, false);

        return $f->parse('core/page/section.php');
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

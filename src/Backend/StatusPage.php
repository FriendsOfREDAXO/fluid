<?php

namespace FriendsOfRedaxo\Fluid\Backend;

use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidKit;
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
        $kitKey = FluidKit::instance()->key();
        $routes = (int) (Sql::factory()->getArray('SELECT COUNT(*) AS n FROM ' . Routes::TABLE)[0]['n'] ?? 0);
        $n404 = (int) (Sql::factory()->getArray('SELECT COUNT(*) AS n FROM ' . Redirects::TABLE_404 . ' WHERE last_seen > NOW() - INTERVAL 30 DAY')[0]['n'] ?? 0);
        $primary = Domains::primary();
        $mailer = '' !== (string) ($_SERVER['MAILER_DSN'] ?? getenv('MAILER_DSN') ?: '');
        $from = '' !== (string) ($_SERVER['REX_MAILER_FROM'] ?? getenv('REX_MAILER_FROM') ?: '');
        $site = $start ? $start->getUrl(['fluid_edit' => 1]) : \Redaxo\Core\Filesystem\Url::frontend();

        // [Status, Bereich, Text]; Status: ok | warn | info
        $rows = [
            [$start ? 'ok' : 'warn', 'Startseite', $start ? escape($start->name) . ' – Template ' . escape((string) $start->templateKey) . ($start->templateKey === $kitKey ? '' : ' (nicht das Kit fluid)') : 'Noch keine Seiten – die Website zeigt „Erste Schritte“.'],
            [Core::getConfig('article_work_version') ? 'ok' : 'warn', 'Arbeitsversion', Core::getConfig('article_work_version') ? 'an – Änderungen erst als Entwurf, dann „Veröffentlichen“' : 'aus – Änderungen gehen sofort live (System → Einstellungen → Arbeitsversion)'],
            [$routes > 0 ? 'ok' : 'warn', 'Schöne Adressen', $routes . ' Adressen. Der Webserver muss Anfragen ohne Datei an index.php geben (README → Webserver). Test: <a href="' . escape(\Redaxo\Core\Filesystem\Url::frontend() . 'sitemap.xml') . '" target="_blank" rel="noopener">sitemap.xml</a>'],
            [$primary ? 'ok' : 'info', 'Hauptadresse', $primary ? escape($primary->host) : 'nicht festgelegt – Adressen folgen dem Aufruf (Website → Domains)'],
            ['info', 'Sprachen', \count(Language::getAll()) . ' (' . escape(implode(', ', array_map(static fn (Language $l) => $l->code . ($l->isOnline() ? '' : ' offline'), Language::getAll()))) . ')'],
            [$mailer && $from ? 'ok' : 'warn', 'Mail', $mailer && $from ? 'MAILER_DSN und REX_MAILER_FROM gesetzt' : 'MAILER_DSN bzw. REX_MAILER_FROM fehlen in .env – Formulare können keine Mails senden'],
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
            'proxy' => (static function () {
                Dir::delete(Path::addonCache('fluid', 'proxy'));

                return Message::success('Karten-Cache geleert.');
            })(),
            default => '',
        };
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

<?php

namespace FriendsOfRedaxo\Fluid;

use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidKit;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleHandler;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Security\CsrfToken;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

use function Redaxo\Core\View\escape;

/**
 * Erste Schritte: Solange es keine Startseite gibt, zeigt die Website statt eines Fehlers diese Seite.
 * Admins (mit Backend-Anmeldung) legen hier eine leere Startseite oder die Beispielseiten an, Besucher sehen
 * „Diese Website wird gerade eingerichtet“ (503).
 */
final class Welcome
{
    private const string CSRF = 'fluid_welcome';

    public static function needed(): bool
    {
        return null === Article::getSiteStartArticle();
    }

    public static function handle(): never
    {
        $user = Editing::user();
        $admin = (bool) $user?->admin;
        $msg = '';
        if ($admin && 'POST' === Core::getRequest()->getMethod()) {
            if (!CsrfToken::factory(self::CSRF)->isValid()) {
                $msg = 'Sitzung abgelaufen – bitte noch einmal.';
            } else {
                try {
                    'demo' === Request::post('start', 'string', '') ? self::demo() : self::blank();
                    while (ob_get_level()) {
                        ob_end_clean();
                    }
                    header('Location: ' . Core::getRequest()->getBasePath() . '/?' . Editing::PARAM . '=1', true, 303);
                    exit;
                } catch (\Throwable $e) {
                    $msg = 'Das hat nicht geklappt: ' . $e->getMessage();
                }
            }
        }
        while (ob_get_level()) {
            ob_end_clean();
        }
        http_response_code($admin ? 200 : 503);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        if (!$admin) {
            header('Retry-After: 3600');
        }
        $name = escape(Settings::name() ?: 'REDAXO');
        $backend = escape(\Redaxo\Core\Filesystem\Url::backendController());
        $body = $admin
            ? '<h1>Willkommen bei fluid</h1><p>Die Website hat noch keine Seiten. Wie möchten Sie anfangen?</p>'
                . ('' !== $msg ? '<p class="err">' . escape($msg) . '</p>' : '')
                . '<form method="post">' . CsrfToken::factory(self::CSRF)->getHiddenField()
                . '<button name="start" value="blank" class="primary">Leere Startseite anlegen</button>'
                . '<button name="start" value="demo">Mit Beispielseiten starten</button></form>'
                . '<p class="small">Beides öffnet danach den Bearbeiten-Modus. Die Beispielseiten (Start, Technik, Kontakt …) lassen sich später einzeln löschen. '
                . 'Alternativ in der Konsole: <code>php bin/console fluid:demo --set=redaxo6</code></p>'
            : '<h1>' . $name . '</h1><p>Diese Website wird gerade eingerichtet.</p>'
                . '<p class="small"><a href="' . $backend . '">Anmelden</a></p>';
        echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
            . '<title>' . $name . '</title><style>'
            . 'body{margin:0;min-height:100vh;display:grid;place-items:center;font:16px/1.55 system-ui,sans-serif;background:#f6f7fb;color:#1c1d26}'
            . 'main{max-width:34rem;margin:1rem;padding:2.25rem;background:#fff;border-radius:16px;box-shadow:0 10px 40px rgb(20 20 50/.08)}'
            . 'h1{margin:0 0 .5rem;font-size:1.6rem;letter-spacing:-.01em}form{display:flex;flex-wrap:wrap;gap:.6rem;margin:1.25rem 0}'
            . 'button{font:inherit;font-weight:600;padding:.7rem 1.1rem;border-radius:10px;border:1px solid #c9cbe0;background:#fff;color:#1c1d26;cursor:pointer}'
            . 'button.primary{background:#4f46e5;border-color:#4f46e5;color:#fff}.small{font-size:.875rem;color:#55576a}.err{color:#b42318}code{font-size:.85em}'
            . 'a{color:#4338ca}</style></head><body><main>' . $body . '</main></body></html>';
        exit;
    }

    /** Leere Startseite mit dem Kit fluid: online, Startseite und Fehlerseite, Arbeitsversion an */
    private static function blank(): void
    {
        Core::setConfig('article_work_version', true);
        $kit = FluidKit::instance();
        ArticleHandler::addArticle(['category_id' => null, 'priority' => 1, 'name' => 'Startseite', 'template' => $kit->key()]);
        $id = (int) Sql::factory()->getArray('SELECT MAX(id) AS id FROM rex_article')[0]['id'];
        foreach (Language::getAll() as $l) {
            ArticleHandler::articleStatus($id, $l->id, 1);
        }
        Core::setConfig('start_article_id', $id);
        Core::setConfig('notfound_article_id', $id);
        Routing\Routes::rebuild();
        Activity::log('page_add', $id, 'Startseite angelegt');
    }

    private static function demo(): void
    {
        $out = new BufferedOutput();
        $cmd = new Console\DemoCommand();
        $code = $cmd->run(new ArrayInput(['--set' => 'redaxo6']), $out);
        if (0 !== $code) {
            throw new \RuntimeException(trim(strip_tags($out->fetch())) ?: 'Beispielseiten konnten nicht angelegt werden.');
        }
        Routing\Routes::rebuild();
    }
}

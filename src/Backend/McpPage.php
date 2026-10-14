<?php

namespace FriendsOfRedaxo\Fluid\Backend;

use FriendsOfRedaxo\Fluid\Data\YFormBridge;
use FriendsOfRedaxo\Fluid\FluidAddon;
use FriendsOfRedaxo\Fluid\Mcp\Access;
use FriendsOfRedaxo\Fluid\Mcp\Server;
use FriendsOfRedaxo\Fluid\Mcp\Tokens;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

use function in_array;
use function is_array;
use function Redaxo\Core\View\escape;

/**
 * Backend → AddOns → fluid → MCP: Server einschalten, öffentlichen Zugang festlegen, Zugangsschlüssel verwalten.
 * Alles ist standardmäßig aus; was öffentlich ist, entscheidet der Admin.
 *
 * @internal
 */
final class McpPage
{
    private const string CSRF = 'fluid_mcp';

    public static function run(): string
    {
        $out = self::action();
        $cfg = Access::config();
        $csrf = CsrfToken::factory(self::CSRF)->getHiddenField();
        $tables = YFormBridge::tables();

        // ---------------------------------------------------------------- Einstellungen
        $check = static fn (string $name, bool $on, string $label, string $help = '') => '<div class="checkbox"><label><input type="checkbox" name="mcp[' . $name . ']" value="1"' . ($on ? ' checked' : '') . '> ' . $label . '</label>'
            . ('' !== $help ? '<p class="help-block">' . $help . '</p>' : '') . '</div>';
        $body = '<form method="post">' . $csrf . '<input type="hidden" name="mcp_do" value="settings">'
            . $check('enabled', (bool) $cfg['enabled'], '<strong>MCP-Server einschalten</strong>', 'Adresse: <code>' . escape(Server::url()) . '</code> – KI-Werkzeuge (Claude, ChatGPT, Cursor …) lesen und bearbeiten die Website über Zugangsschlüssel.')
            . '<hr><h4>Öffentlicher Zugang (ohne Schlüssel)</h4>'
            . $check('public', (bool) $cfg['public'], 'Öffentlich lesbar machen', 'Jeder kann dann ohne Schlüssel lesen – nur veröffentlichte Inhalte, nie Entwürfe, nie schreiben. Höchstens 60 Anfragen je 5 Minuten und IP.')
            . '<div style="margin-left:1.5rem">'
            . $check('public_pages', (bool) $cfg['public_pages'], 'Seiten (online) mit ihren Blöcken')
            . $check('public_search', (bool) $cfg['public_search'], 'Suche')
            . $check('public_blog', (bool) $cfg['public_blog'], 'Blog-Beiträge');
        if ([] !== $tables) {
            $body .= '<p><strong>Datentabellen öffentlich lesbar</strong> – nur Tabellen ohne persönliche Daten wählen!</p>';
            foreach ($tables as $name => $label) {
                $body .= '<div class="checkbox"><label><input type="checkbox" name="mcp[public_tables][]" value="' . escape($name) . '"' . (in_array($name, (array) $cfg['public_tables'], true) ? ' checked' : '') . '> ' . escape($label) . '</label></div>';
            }
        }
        $body .= '</div>';
        if ([] !== $tables) {
            $body .= '<hr><h4>Datentabellen für Schlüssel</h4><p class="help-block">Welche YForm-Tabellen Schlüssel lesen bzw. ändern dürfen (ändern nur Schlüssel mit „Lesen und ändern“). Postfach-Tabellen mit Anfragen nur freigeben, wenn es sein muss.</p>'
                . '<div style="max-width:40rem">';
            foreach ($tables as $name => $label) {
                $mode = ((array) $cfg['tables'])[$name] ?? '';
                $id = 'mcp-t-' . md5($name);
                $sel = '<select class="form-control" id="' . $id . '" name="mcp[tables][' . escape($name) . ']">';
                foreach (['' => 'kein Zugriff', 'read' => 'lesen', 'write' => 'lesen und ändern'] as $v => $l) {
                    $sel .= '<option value="' . $v . '"' . ($v === $mode ? ' selected' : '') . '>' . $l . '</option>';
                }
                $body .= '<div class="form-group"><label for="' . $id . '">' . escape($label) . '</label>' . $sel . '</select></div>';
            }
            $body .= '</div>';
        }
        $body .= '<hr><div class="form-group"><label for="mcp-instr">Hinweise für die KI (optional)</label>'
            . '<textarea class="form-control" id="mcp-instr" name="mcp[instructions]" rows="2" placeholder="z. B. Wir duzen unsere Besucher. Keine Preise nennen.">' . escape((string) $cfg['instructions']) . '</textarea></div>'
            . '<button class="btn btn-save">Speichern</button></form>';
        $f = new Fragment();
        $f->setVar('title', 'MCP-Server', false);
        $f->setVar('body', $body, false);
        $out .= $f->parse('core/page/section.php');

        // ---------------------------------------------------------------- Schlüssel
        $rows = '';
        foreach (Tokens::all() as $t) {
            $rows .= '<tr><td><strong>' . escape((string) $t['name']) . '</strong><br><code>' . escape((string) $t['prefix']) . '…</code></td>'
                . '<td>' . escape((string) ($t['login'] ?? '–')) . '</td>'
                . '<td>' . escape(Tokens::SCOPES[$t['scope']] ?? (string) $t['scope']) . ($t['publish'] ? '<br><small>+ veröffentlichen</small>' : '') . '</td>'
                . '<td><small>angelegt ' . escape((string) $t['created']) . '<br>zuletzt ' . escape((string) ($t['last_used'] ?: '–')) . ($t['client'] ? '<br>' . escape((string) $t['client']) : '') . ($t['expires'] ? '<br>gültig bis ' . escape((string) $t['expires']) : '') . '</small></td>'
                . '<td><form method="post" onsubmit="return confirm(\'Schlüssel widerrufen? Programme damit verlieren sofort den Zugang.\')">' . $csrf
                . '<input type="hidden" name="mcp_do" value="revoke"><input type="hidden" name="id" value="' . (int) $t['id'] . '"><button class="btn btn-xs btn-delete">Widerrufen</button></form></td></tr>';
        }
        $users = '';
        foreach (Sql::factory()->getArray('SELECT id, login, name FROM rex_user WHERE status = 1 ORDER BY login') as $u) {
            $users .= '<option value="' . (int) $u['id'] . '">' . escape($u['login'] . ($u['name'] ? ' (' . $u['name'] . ')' : '')) . '</option>';
        }
        $body = ('' !== $rows ? '<div class="table-responsive"><table class="table"><thead><tr><th>Name</th><th>Benutzer</th><th>Darf</th><th>Nutzung</th><th></th></tr></thead><tbody>' . $rows . '</tbody></table></div>' : '<p>Noch keine Schlüssel.</p>')
            . '<h4>Neuer Schlüssel</h4><form method="post" class="row">' . $csrf . '<input type="hidden" name="mcp_do" value="create">'
            . '<div class="col-sm-3 form-group"><label>Name</label><input class="form-control" name="name" required placeholder="z. B. Claude – Thomas"></div>'
            . '<div class="col-sm-3 form-group"><label>Handelt als Benutzer</label><select class="form-control" name="user">' . $users . '</select><p class="help-block">Dessen Rechte gelten.</p></div>'
            . '<div class="col-sm-2 form-group"><label>Darf</label><select class="form-control" name="scope"><option value="read">nur lesen</option><option value="write">lesen und ändern</option></select></div>'
            . '<div class="col-sm-2 form-group"><label>Gültig bis</label><input class="form-control" type="date" name="expires"></div>'
            . '<div class="col-sm-2 form-group"><label>&nbsp;</label><div class="checkbox" style="margin:0"><label><input type="checkbox" name="publish" value="1"> darf veröffentlichen</label></div></div>'
            . '<div class="col-sm-12"><button class="btn btn-save">Schlüssel erzeugen</button> <span class="help-block" style="display:inline">Der Schlüssel erscheint nur einmal.</span></div></form>';
        $f = new Fragment();
        $f->setVar('title', 'Zugangsschlüssel', false);
        $f->setVar('body', $body, false);
        $out .= $f->parse('core/page/section.php');

        return $out;
    }

    private static function action(): string
    {
        $do = Request::post('mcp_do', 'string', '');
        if ('' === $do) {
            return '';
        }
        if (!CsrfToken::factory(self::CSRF)->isValid()) {
            return Message::error('Sitzung abgelaufen – bitte noch einmal.');
        }
        $addon = FluidAddon::instance();

        return match ($do) {
            'settings' => (static function () use ($addon): string {
                $in = Request::post('mcp', 'array', []);
                $known = array_keys(YFormBridge::tables());
                $tables = [];
                foreach ((array) ($in['tables'] ?? []) as $name => $mode) {
                    if (in_array($name, $known, true) && in_array($mode, ['read', 'write'], true)) {
                        $tables[$name] = $mode;
                    }
                }
                $addon->setConfig('mcp', [
                    'enabled' => !empty($in['enabled']),
                    'public' => !empty($in['public']),
                    'public_pages' => !empty($in['public_pages']),
                    'public_search' => !empty($in['public_search']),
                    'public_blog' => !empty($in['public_blog']),
                    'public_tables' => array_values(array_intersect(is_array($in['public_tables'] ?? null) ? $in['public_tables'] : [], $known)),
                    'tables' => $tables,
                    'instructions' => mb_substr(trim((string) ($in['instructions'] ?? '')), 0, 1000),
                ]);

                return Message::success('Gespeichert.');
            })(),
            'create' => (static function (): string {
                $user = Request::post('user', 'int', 0);
                if ($user <= 0) {
                    return Message::error('Bitte einen Benutzer wählen.');
                }
                $expires = Request::post('expires', 'string', '');
                $t = Tokens::create(Request::post('name', 'string', ''), Request::post('scope', 'string', 'read'), $user, '1' === Request::post('publish', 'string', ''), 1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires) ? $expires : null);
                $url = Server::url();
                $cli = 'claude mcp add --transport http fluid ' . $url . ' --header "Authorization: Bearer ' . $t['token'] . '"';
                $json = json_encode(['mcpServers' => ['fluid' => ['type' => 'http', 'url' => $url, 'headers' => ['Authorization' => 'Bearer ' . $t['token']]]]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                return Message::success('<p><strong>Schlüssel erzeugt – jetzt kopieren, er wird nicht wieder angezeigt:</strong></p>'
                    . '<pre style="user-select:all">' . escape($t['token']) . '</pre>'
                    . '<p>Claude Code:</p><pre style="white-space:pre-wrap;user-select:all">' . escape($cli) . '</pre>'
                    . '<p>Andere Programme (JSON):</p><pre style="user-select:all">' . escape((string) $json) . '</pre>'
                    . (Access::config()['enabled'] ? '' : '<p><strong>Hinweis:</strong> Der MCP-Server ist noch ausgeschaltet.</p>'));
            })(),
            'revoke' => (static function (): string {
                Tokens::revoke(Request::post('id', 'int', 0));

                return Message::success('Schlüssel widerrufen.');
            })(),
            default => '',
        };
    }
}

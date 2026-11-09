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
use Redaxo\Core\View\Component\Section;
use Redaxo\Core\View\Html;
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

        // ---------------------------------------------------------------- Einstellungen (Markup und Komponenten des Cores)
        $field = static fn (string $label, string $control, string $note = '', string $for = '') => '<div class="rex-field"><' . ('' !== $for ? 'label for="' . $for . '"' : 'span') . ' class="rex-field-label">' . $label . '</' . ('' !== $for ? 'label' : 'span') . '>'
            . '<div class="rex-field-control">' . $control . ('' !== $note ? '<p class="rex-field-note">' . $note . '</p>' : '') . '</div></div>';
        $check = static fn (string $name, bool $on, string $label) => '<div class="rex-check"><label><input type="checkbox" name="mcp[' . $name . ']" value="1"' . ($on ? ' checked' : '') . '> ' . $label . '</label></div>';
        $body = '<form method="post">' . $csrf . '<input type="hidden" name="mcp_do" value="settings">'
            . $field('MCP-Server', $check('enabled', (bool) $cfg['enabled'], 'einschalten'), 'Adresse: <code>' . escape(Server::url()) . '</code> – KI-Werkzeuge (Claude, ChatGPT, Cursor …) lesen und bearbeiten die Website über Zugangsschlüssel.')
            . $field('Öffentlicher Zugang', $check('public', (bool) $cfg['public'], 'ohne Schlüssel lesbar')
                . '<div class="fluid-mcp-sub">' . $check('public_pages', (bool) $cfg['public_pages'], 'Seiten (online) mit ihren Blöcken')
                . $check('public_search', (bool) $cfg['public_search'], 'Suche') . $check('public_blog', (bool) $cfg['public_blog'], 'Blog-Beiträge') . '</div>',
                'Jeder kann dann ohne Schlüssel lesen – nur veröffentlichte Inhalte, nie Entwürfe, nie schreiben. Höchstens 60 Anfragen je 5 Minuten und IP.');
        if ([] !== $tables) {
            $pub = '';
            foreach ($tables as $name => $label) {
                $pub .= '<div class="rex-check"><label><input type="checkbox" name="mcp[public_tables][]" value="' . escape($name) . '"' . (in_array($name, (array) $cfg['public_tables'], true) ? ' checked' : '') . '> ' . escape($label) . '</label></div>';
            }
            $body .= $field('Datentabellen öffentlich', $pub, 'Nur Tabellen ohne persönliche Daten wählen!');
            foreach ($tables as $name => $label) {
                $mode = ((array) $cfg['tables'])[$name] ?? '';
                $id = 'mcp-t-' . md5($name);
                $sel = '<select class="rex-input" id="' . $id . '" name="mcp[tables][' . escape($name) . ']">';
                foreach (['' => 'kein Zugriff', 'read' => 'lesen', 'write' => 'lesen und ändern'] as $v => $l) {
                    $sel .= '<option value="' . $v . '"' . ($v === $mode ? ' selected' : '') . '>' . $l . '</option>';
                }
                $body .= $field('Schlüssel: ' . escape($label), $sel . '</select>', '', $id);
            }
            $body .= '<p class="rex-field-note">Welche YForm-Tabellen Schlüssel lesen bzw. ändern dürfen (ändern nur Schlüssel mit „Lesen und ändern“). Postfach-Tabellen mit Anfragen nur freigeben, wenn es sein muss.</p>';
        }
        $body .= $field('Hinweise für die KI', '<textarea class="rex-input" id="mcp-instr" name="mcp[instructions]" rows="2" placeholder="z. B. Wir duzen unsere Besucher. Keine Preise nennen.">' . escape((string) $cfg['instructions']) . '</textarea>', 'Optional.', 'mcp-instr')
            . '<div class="rex-button-toolbar"><button type="submit" class="rex-button" data-variant="primary">Speichern</button></div></form>';
        $out .= new Section(title: 'MCP-Server', body: Html::raw($body))->render();

        // ---------------------------------------------------------------- Schlüssel
        $rows = '';
        foreach (Tokens::all() as $t) {
            $rows .= '<tr><td><strong>' . escape((string) $t['name']) . '</strong><br><code>' . escape((string) $t['prefix']) . '…</code></td>'
                . '<td>' . escape((string) ($t['login'] ?? '–')) . '</td>'
                . '<td>' . escape(Tokens::SCOPES[$t['scope']] ?? (string) $t['scope']) . ($t['publish'] ? '<br><small>+ veröffentlichen</small>' : '') . '</td>'
                . '<td><small>angelegt ' . escape((string) $t['created']) . '<br>zuletzt ' . escape((string) ($t['last_used'] ?: '–')) . ($t['client'] ? '<br>' . escape((string) $t['client']) : '') . ($t['expires'] ? '<br>gültig bis ' . escape((string) $t['expires']) : '') . '</small></td>'
                . '<td><form method="post">' . $csrf
                . '<input type="hidden" name="mcp_do" value="revoke"><input type="hidden" name="id" value="' . (int) $t['id'] . '">'
                . '<button type="submit" class="rex-button" data-variant="danger" data-size="xs" data-confirm="Schlüssel widerrufen? Programme damit verlieren sofort den Zugang.">Widerrufen</button></form></td></tr>';
        }
        $out .= new Section(
            title: 'Zugangsschlüssel',
            body: '' === $rows ? Html::raw('<p>Noch keine Schlüssel.</p>') : '',
            content: '' !== $rows ? Html::raw('<table class="rex-table" data-hover><thead><tr><th>Name</th><th>Benutzer</th><th>Darf</th><th>Nutzung</th><th></th></tr></thead><tbody>' . $rows . '</tbody></table>') : '',
        )->render();

        $users = '';
        foreach (Sql::factory()->getArray('SELECT id, login, name FROM rex_user WHERE status = 1 ORDER BY login') as $u) {
            $users .= '<option value="' . (int) $u['id'] . '">' . escape($u['login'] . ($u['name'] ? ' (' . $u['name'] . ')' : '')) . '</option>';
        }
        $body = '<form method="post">' . $csrf . '<input type="hidden" name="mcp_do" value="create">'
            . $field('Name', '<input class="rex-input" id="mcp-name" name="name" required placeholder="z. B. Claude – Thomas">', '', 'mcp-name')
            . $field('Handelt als Benutzer', '<select class="rex-input" id="mcp-user" name="user">' . $users . '</select>', 'Dessen Rechte gelten.', 'mcp-user')
            . $field('Darf', '<select class="rex-input" id="mcp-scope" name="scope"><option value="read">nur lesen</option><option value="write">lesen und ändern</option></select>', '', 'mcp-scope')
            . $field('Gültig bis', '<input class="rex-input" type="date" id="mcp-expires" name="expires">', 'Optional.', 'mcp-expires')
            . $field('Veröffentlichen', '<div class="rex-check"><label><input type="checkbox" name="publish" value="1"> darf veröffentlichen</label></div>')
            . '<div class="rex-button-toolbar"><button type="submit" class="rex-button" data-variant="primary">Schlüssel erzeugen</button><span class="rex-u-text-muted">Der Schlüssel erscheint nur einmal.</span></div></form>';
        $out .= new Section(title: 'Neuer Schlüssel', body: Html::raw($body))->render();

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
                    . '<pre class="fluid-mcp-secret">' . escape($t['token']) . '</pre>'
                    . '<p>Claude Code:</p><pre class="fluid-mcp-secret fluid-mcp-secret--wrap">' . escape($cli) . '</pre>'
                    . '<p>Andere Programme (JSON):</p><pre class="fluid-mcp-secret">' . escape((string) $json) . '</pre>'
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

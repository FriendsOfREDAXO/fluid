<?php

namespace FriendsOfRedaxo\Fluid;

use FriendsOfRedaxo\Fluid\Form\FieldForm;
use FriendsOfRedaxo\Fluid\Form\Picker;
use FriendsOfRedaxo\Fluid\Maps\Maps;
use FriendsOfRedaxo\Fluid\Routing\DomainRole;
use FriendsOfRedaxo\Fluid\Routing\Domains;
use FriendsOfRedaxo\Fluid\Routing\Redirects;
use FriendsOfRedaxo\Fluid\Routing\Routes;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Security\User;

use function Redaxo\Core\View\escape;

/**
 * Seitenleiste „Website“ auf der Website (nur Admins): Reiter der Website-Einstellungen (Settings::schema),
 * Domains (Hauptadresse, weitere Domains, Landing-Domains) und Weiterleitungen mit 404-Protokoll.
 * Jede Aktion antwortet mit dem HTML des Reiters – der Editor ersetzt nur den Inhalt der Seitenleiste.
 */
final class SiteAdmin
{
    /** @return array<string, string> Reiter: Schlüssel => Beschriftung */
    public static function tabs(): array
    {
        $tabs = [];
        foreach (Settings::schema() as $k => $g) {
            $tabs[$k] = $g['label'];
        }

        return $tabs + ['domains' => 'Domains', 'redirects' => 'Weiterleitungen'];
    }

    public static function handle(string $action, User $user): array
    {
        if (!$user->admin) {
            throw new ApiError('Die Website-Einstellungen dürfen nur Admins ändern.');
        }
        $tab = Request::post('tab', 'string', 'base');
        $msg = '';
        switch ($action) {
            case 'site_save':
                Settings::save($tab, (array) (Request::post('site', 'array', [])));
                $msg = 'Gespeichert.';
                break;
            case 'domain_save':
                $msg = self::domainSave();
                $tab = 'domains';
                break;
            case 'domain_delete':
                Sql::factory()->setQuery('DELETE FROM ' . Domains::TABLE . ' WHERE id = ?', [Request::post('id', 'int', 0)]);
                Domains::reset();
                $msg = 'Domain entfernt.';
                $tab = 'domains';
                break;
            case 'redirect_save':
                $msg = self::redirectSave();
                $tab = 'redirects';
                break;
            case 'redirect_delete':
                Sql::factory()->setQuery('DELETE FROM ' . Redirects::TABLE . ' WHERE id = ?', [Request::post('id', 'int', 0)]);
                $msg = 'Weiterleitung gelöscht.';
                $tab = 'redirects';
                break;
            case 'geocode':
                return ['results' => Maps::geocode(Request::post('q', 'string', ''))];
        }

        return ['html' => self::render($tab), 'tab' => $tab, 'tabs' => self::tabs(), 'message' => $msg, 'reload' => 'site_save' === $action];
    }

    public static function render(string $tab): string
    {
        return match ($tab) {
            'domains' => self::domains(),
            'redirects' => self::redirects(),
            default => isset(Settings::schema()[$tab])
                ? '<form data-site-form="site_save" data-tab="' . escape($tab) . '">' . FieldForm::standalone(Settings::schema()[$tab]['fields'], Settings::all(), 'site')
                    . '<div class="site-actions"><button type="submit" class="btn btn--primary">Speichern</button></div></form>'
                : '',
        };
    }

    // ------------------------------------------------------------------ Domains

    private static function domains(): string
    {
        $roles = ['primary' => 'Hauptadresse', 'alias' => 'Weitere Domain (→ Hauptadresse)', 'landing' => 'Landing-Domain (eigene Seite)'];
        $row = static function (array $d) use ($roles): string {
            $id = (int) ($d['id'] ?? 0);
            $sel = '';
            foreach ($roles as $k => $l) {
                $sel .= '<option value="' . $k . '"' . ($k === ($d['role'] ?? 'alias') ? ' selected' : '') . '>' . escape($l) . '</option>';
            }

            return '<form class="site-card" data-site-form="domain_save" data-tab="domains"><input type="hidden" name="id" value="' . $id . '">'
                . '<div class="blocks-form__grid">'
                . '<div class="blocks-form__field is-half"><label class="control-label">Domain</label><input class="form-control" name="host" value="' . escape((string) ($d['host'] ?? '')) . '" placeholder="www.beispiel.de" required></div>'
                . '<div class="blocks-form__field is-half"><label class="control-label">Rolle</label><select class="form-control" name="role" data-domain-role>' . $sel . '</select></div>'
                . '<div class="blocks-form__field" data-landing-only><label class="control-label">Seite der Landing-Domain</label>'
                . Picker::link('dom_page_' . $id, 'page', $d['article_id'] ? 'redaxo://' . (int) $d['article_id'] : '', false) . '</div>'
                . '<div class="blocks-form__field is-half" data-landing-only><label class="blocks-form__check"><input type="checkbox" name="subtree" value="1"' . (!empty($d['subtree']) ? ' checked' : '') . '> mit Unterseiten</label></div>'
                . '<div class="blocks-form__field is-half" data-landing-only><label class="control-label">Canonical</label><select class="form-control" name="canonical">'
                . '<option value="own"' . ('mirror' !== ($d['canonical'] ?? 'own') ? ' selected' : '') . '>Landing-Domain ist maßgeblich</option>'
                . '<option value="mirror"' . ('mirror' === ($d['canonical'] ?? '') ? ' selected' : '') . '>Spiegel (Hauptadresse maßgeblich)</option></select></div>'
                . '</div><div class="site-actions">' . ($id ? '<button type="button" class="btn" data-site-act="domain_delete" data-id="' . $id . '" data-confirm="Domain entfernen?">Entfernen</button>' : '')
                . '<button type="submit" class="btn btn--primary">' . ($id ? 'Speichern' : 'Domain hinzufügen') . '</button></div></form>';
        };
        $h = '<p class="site-intro">Die <b>Hauptadresse</b> nutzen Canonical, Sitemap und Links von außen. <b>Weitere Domains</b> leiten per 301 dorthin. '
            . 'Eine <b>Landing-Domain</b> zeigt eine Seite (auf Wunsch mit Unterseiten) unter eigener Adresse. Alle Domains müssen im Hosting auf diese Installation zeigen.</p>';
        try {
            $rows = Sql::factory()->getArray('SELECT * FROM ' . Domains::TABLE . ' ORDER BY FIELD(role, "primary", "alias", "landing"), host');
        } catch (\Throwable) {
            $rows = [];
        }
        foreach ($rows as $r) {
            $h .= $row($r);
        }

        return $h . '<h3 class="site-h">Neue Domain</h3>' . $row(['role' => Domains::primary() ? 'alias' : 'primary', 'article_id' => null]);
    }

    private static function domainSave(): string
    {
        $host = Domains::host(Request::post('host', 'string', ''));
        if (!preg_match('~^[a-z0-9.-]+\.[a-z]{2,}$|^localhost$|^[0-9.]+$~', $host)) {
            throw new ApiError('Bitte eine Domain wie „www.beispiel.de“ angeben.');
        }
        $role = DomainRole::tryFrom(Request::post('role', 'string', 'alias')) ?? DomainRole::Alias;
        $page = (int) preg_replace('~\D~', '', Request::post('page', 'string', ''));
        if (DomainRole::Landing === $role && !Article::get($page)) {
            throw new ApiError('Bitte die Seite der Landing-Domain wählen.');
        }
        $id = Request::post('id', 'int', 0);
        $sql = Sql::factory();
        if (DomainRole::Primary === $role) {
            $sql->setQuery('UPDATE ' . Domains::TABLE . ' SET role = "alias" WHERE role = "primary" AND id <> ?', [$id]);   // nur eine Hauptadresse
        }
        $dup = $sql->getArray('SELECT id FROM ' . Domains::TABLE . ' WHERE host = ? AND id <> ?', [$host, $id]);
        if ($dup) {
            throw new ApiError('Diese Domain ist schon eingetragen.');
        }
        $sql->setTable(Domains::TABLE)->setValues([
            'host' => $host, 'role' => $role->value, 'article_id' => DomainRole::Landing === $role ? $page : null,
            'subtree' => DomainRole::Landing === $role ? (int) Request::post('subtree', 'bool', false) : 0,
            'canonical' => 'mirror' === Request::post('canonical', 'string', 'own') ? 'mirror' : 'own',
        ]);
        $id ? $sql->setWhere(['id' => $id])->update() : $sql->insert();
        Domains::reset();

        return 'Domain gespeichert.';
    }

    // ------------------------------------------------------------------ Weiterleitungen

    private static function redirects(): string
    {
        $h = '<p class="site-intro">Ändert sich die Adresse einer Seite, entsteht die Weiterleitung automatisch. Hier legen Sie eigene an – z. B. für alte Adressen nach einem Relaunch. '
            . '„/alt/*“ leitet alles darunter um; „*“ im Ziel übernimmt den Rest.</p>'
            . '<form class="site-card" data-site-form="redirect_save" data-tab="redirects"><div class="blocks-form__grid">'
            . '<div class="blocks-form__field"><label class="control-label">Alte Adresse</label><input class="form-control" name="source" placeholder="/alte-seite oder /alt/*" required data-redirect-source></div>'
            . '<div class="blocks-form__field"><label class="control-label">Ziel</label>' . Picker::link('redirect_target', 'target', '', false) . '<p class="help-block">Seite, /pfad oder https://…</p></div>'
            . '<div class="blocks-form__field is-half"><label class="control-label">Art</label><select class="form-control" name="code"><option value="301">301 – dauerhaft</option><option value="302">302 – vorübergehend</option><option value="410">410 – gelöscht (ohne Ziel)</option></select></div>'
            . '<div class="blocks-form__field is-half"><label class="control-label">Notiz (optional)</label><input class="form-control" name="note" maxlength="190"></div>'
            . '</div><div class="site-actions"><button type="submit" class="btn btn--primary">Weiterleitung anlegen</button></div></form>';
        try {
            $rules = Sql::factory()->getArray('SELECT * FROM ' . Redirects::TABLE . ' ORDER BY updated DESC, id DESC LIMIT 200');
            $nf = Sql::factory()->getArray('SELECT path, hits, last_seen FROM ' . Redirects::TABLE_404 . ' ORDER BY hits DESC, last_seen DESC LIMIT 20');
        } catch (\Throwable) {
            $rules = $nf = [];
        }
        if ($rules) {
            $h .= '<h3 class="site-h">Weiterleitungen (' . count($rules) . ')</h3><ul class="site-list" role="list">';
            foreach ($rules as $r) {
                $target = (string) $r['target'];
                if (preg_match('~^redaxo://(\d+)~', $target, $m) && ($a = Article::get((int) $m[1]))) {
                    $target = $a->name . ' (' . (Routes::prefix($a->languageId) . '/' . Routes::path($a->id, $a->languageId)) . ')';
                }
                $h .= '<li class="site-list__item"><span class="site-list__main"><code>' . escape((string) $r['source']) . '</code> → ' . escape(410 === (int) $r['code'] ? 'gelöscht (410)' : $target)
                    . '<small>' . (int) $r['code'] . ' · ' . ('auto' === $r['origin'] ? 'automatisch' : 'manuell') . ' · ' . (int) $r['hits'] . ' Aufrufe' . ('' !== (string) $r['note'] && 'auto' !== $r['origin'] ? ' · ' . escape((string) $r['note']) : '') . '</small></span>'
                    . '<button type="button" class="btn btn--icon" data-site-act="redirect_delete" data-id="' . (int) $r['id'] . '" data-confirm="Weiterleitung löschen?" aria-label="Löschen">✕</button></li>';
            }
            $h .= '</ul>';
        }
        if ($nf) {
            $h .= '<h3 class="site-h">Nicht gefunden (404)</h3><p class="help-block">Adressen, die Besucher aufgerufen haben und die es nicht gibt – ohne IP. Häufige alte Adressen umleiten.</p><ul class="site-list" role="list">';
            foreach ($nf as $r) {
                $h .= '<li class="site-list__item"><span class="site-list__main"><code>' . escape((string) $r['path']) . '</code><small>' . (int) $r['hits'] . ' Aufrufe</small></span>'
                    . '<button type="button" class="btn" data-prefill-source="' . escape((string) $r['path']) . '">Umleiten …</button></li>';
            }
            $h .= '</ul>';
        }

        return $h;
    }

    private static function redirectSave(): string
    {
        $source = Redirects::normalize(Request::post('source', 'string', ''));
        $code = Request::post('code', 'int', 301);
        $target = trim(Request::post('target', 'string', ''));
        if ('' === $source || '/' === $source) {
            throw new ApiError('Bitte die alte Adresse angeben (z. B. /alte-seite).');
        }
        if (410 !== $code && '' === $target) {
            throw new ApiError('Bitte ein Ziel wählen.');
        }
        if (preg_match('~^\d+$~', $target)) {
            $target = 'redaxo://' . $target;
        }
        Redirects::save(['source' => $source, 'target' => 410 === $code ? '' : $target, 'code' => $code, 'note' => Request::post('note', 'string', '')]);

        return 'Weiterleitung angelegt.';
    }
}

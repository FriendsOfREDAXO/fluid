<?php

namespace FriendsOfRedaxo\Fluid;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Content\ContentHandler;
use Redaxo\Core\Content\ExtensionPoint\ArticleContentUpdated;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Content\ModulePermission;
use Redaxo\Core\Content\Template;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Security\User;
use Throwable;

use function count;
use function in_array;

use function is_array;

/**
 * Endpunkte des Editors auf der Website: `{Seitenadresse}?fluid_api={aktion}` (POST, CSRF-Token `_csrf_token`).
 *
 * Gearbeitet wird immer in der Revision des Editors (Arbeitsversion, sofern eingeschaltet) des aufgerufenen Artikels.
 * Rechte: Kategorie-Recht auf den Artikel (Editing::user()) und Modul-Recht für Hinzufügen/Speichern.
 */
final class Api
{
    public const string PARAM = 'fluid_api';
    public const string CSRF = 'fluid_editor';

    public static function handle(): never
    {
        $user = Editing::user();
        try {
            if (!$user) {
                self::send(['ok' => false, 'error' => 'Keine Berechtigung – bitte im Backend anmelden.'], 403);
            }
            if ('POST' !== ($_SERVER['REQUEST_METHOD'] ?? '') || !CsrfToken::factory(self::CSRF)->isValid()) {
                self::send(['ok' => false, 'error' => 'Sitzung abgelaufen – bitte Seite neu laden.'], 419);
            }
            $article = Article::getCurrent() ?? self::fail('Artikel nicht gefunden.', 404);
            $result = Editing::render(fn () => match (Request::get(self::PARAM, 'string')) {
                'form' => self::form($article),
                'preview' => self::preview($article),
                'save' => self::save($article, $user),
                'add' => self::add($article, $user),
                'move' => self::move($article),
                'status' => self::status($article),
                'delete' => self::delete($article),
                'publish' => self::publish($article),
                'discard' => self::discard($article),
                default => self::fail('Unbekannte Aktion.', 400),
            });
            self::send(['ok' => true, 'state' => self::state($article)] + $result);
        } catch (ApiError $e) {
            self::send(['ok' => false, 'error' => $e->getMessage()], $e->getCode() ?: 400);
        } catch (Throwable $e) {
            self::send(['ok' => false, 'error' => 'Fehler: ' . $e->getMessage()], 500);
        }
    }

    /** Stand des Artikels: Unterscheidet sich die Arbeitsversion von der veröffentlichten Fassung? */
    public static function state(Article $article): array
    {
        if (ArticleRevision::LIVE === FluidAddon::revision()) {
            return ['draft' => false, 'workVersion' => false];
        }
        $hash = static function (int $rev) use ($article): string {
            $rows = Sql::factory()->getArray(
                'SELECT module, ctype_id, status, value1, value2 FROM rex_article_slice WHERE article_id = ? AND language_id = ? AND revision = ? ORDER BY ctype_id, priority',
                [$article->id, $article->languageId, $rev],
            );

            return md5(json_encode($rows));
        };

        return ['draft' => $hash(ArticleRevision::LIVE) !== $hash(FluidAddon::revision()), 'workVersion' => true];
    }

    /** @return array{html: string, label: string} */
    private static function form(Article $article): array
    {
        [$slice, $module] = self::slice($article);

        return [
            'html' => Form\FieldForm::render($module, BlockModule::decode($slice->getValue(1)), BlockModule::decode($slice->getValue(2)), 'f', false),
            'label' => (string) ($module->definition()['label'] ?? $module->name),
        ];
    }

    /** @return array{html: string} */
    private static function preview(Article $article): array
    {
        [$slice, $module] = self::slice($article);
        [$data, $tunes] = self::input($slice);
        $parent = Layout::parentOf($slice);
        if ($parent && Layout::col($slice) === (int) ($tunes['col'] ?? 0)) {
            $pm = Module::get($parent->moduleKey);
            $bg = $pm instanceof BlockModule ? (string) $pm->block($parent)->tunes['background'] : '';

            return ['html' => $module->renderChild($slice, $bg, $data, $tunes), 'target' => $slice->id];
        }

        return ['html' => $module->renderBlock($slice, $data, $tunes), 'target' => $slice->id];
    }

    /** Speichern: ganzes Formular (`f[1]`, `f[2]`) oder einzelne Felder direkt auf der Seite (`patch[pfad] = wert`) */
    private static function save(Article $article, User $user): array
    {
        [$slice, $module] = self::slice($article);
        self::requireModulePerm($user, $module);
        [$data, $tunes] = self::input($slice);

        $sql = Sql::factory();
        $sql->setTable('rex_article_slice');
        $sql->setWhere(['id' => $slice->id, 'article_id' => $article->id, 'revision' => FluidAddon::revision()]);
        $sql->setValue('value1', json_encode($data, JSON_UNESCAPED_UNICODE));
        $sql->setValue('value2', json_encode($tunes, JSON_UNESCAPED_UNICODE));
        $sql->addGlobalUpdateFields($user->login);
        $sql->update();
        self::changed($article, $slice->id, 'slice_updated');

        return self::render($article, $slice->id, true);
    }

    private static function add(Article $article, User $user): array
    {
        $key = Request::post('module', 'string');
        $module = Module::get($key);
        if (!$module instanceof BlockModule) {
            self::fail('Unbekannter Blocktyp.');
        }
        self::requireModulePerm($user, $module);
        if (!Template::checkModuleAllowed((string) $article->templateKey, 1, $module->key)) {
            self::fail('Dieser Block ist im Template der Seite nicht erlaubt.', 403);
        }
        $after = Request::post('after', 'int', 0);
        $col = max(0, min(4, Request::post('col', 'int', 0)));
        if ($col > 0 && !$module->nestable()) {
            self::fail('Dieser Block kann nicht in einer Spalte stehen.');
        }
        $priority = 1;
        if ($after > 0) {
            $prev = ArticleSlice::getArticleSliceById($after, $article->languageId, FluidAddon::revision());
            // Hinter einem Layout auf oberster Ebene bzw. „in diese Spalte“ des Layouts: ans Ende seiner Gruppe
            if ($prev && ((0 === $col && (Layout::isLayout($prev) || null !== Layout::parentOf($prev))) || ($col > 0 && Layout::isLayout($prev)))) {
                $group = Layout::isLayout($prev) ? $prev : Layout::parentOf($prev);
                $kids = Layout::children($group);
                $prev = $kids ? end($kids) : $group;
            }
            $priority = $prev ? $prev->priority + 1 : 1;
        }
        // Standardwerte der Felder (Variante, Auswahlfelder …) gleich mit speichern
        $data = Form\FieldForm::withDefaults($module->fields(), [], $module->variants());
        $tunes = isset($module->definition()['background']) && 0 === $col ? ['background' => $module->definition()['background']] : [];
        if ($col > 0) {
            $tunes['col'] = $col;
        }
        ContentHandler::addSlice($article->id, $article->languageId, 1, $module->key, [
            'revision' => FluidAddon::revision(),
            'priority' => $priority,
            'value1' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'value2' => json_encode((object) $tunes),
        ]);
        $id = (int) Sql::factory()->getArray(
            'SELECT id FROM rex_article_slice WHERE article_id = ? AND language_id = ? AND revision = ? AND module = ? ORDER BY createdate DESC, id DESC LIMIT 1',
            [$article->id, $article->languageId, FluidAddon::revision(), $module->key],
        )[0]['id'];
        return ['slice' => $id] + self::render($article, $id, false);
    }

    /**
     * Verschieben: Blöcke auf oberster Ebene samt Spalten-Blöcken (Layout = Gruppe), Spalten-Blöcke innerhalb ihrer Spalte.
     * Danach Prioritäten neu durchnummeriert.
     */
    private static function move(Article $article): array
    {
        [$slice] = self::slice($article);
        $up = 'up' === Request::post('dir', 'string');
        $rows = Sql::factory()->getArray(
            'SELECT id, value2 FROM rex_article_slice WHERE article_id = ? AND language_id = ? AND ctype_id = ? AND revision = ? ORDER BY priority',
            [$article->id, $article->languageId, $slice->contentSectionId, FluidAddon::revision()],
        );
        $ids = array_map(static fn (array $r) => (int) $r['id'], $rows);
        $cols = array_map(static fn (array $r) => max(0, (int) (BlockModule::decode((string) $r['value2'])['col'] ?? 0)), $rows);
        $layouts = array_map(static fn (int $id) => Layout::isLayout(ArticleSlice::getArticleSliceById($id, $article->languageId, FluidAddon::revision())), $ids);

        // Einheiten: Slice auf oberster Ebene + folgende Spalten-Blöcke, wenn er ein Layout ist
        $units = [];
        $owner = [];   // Index → [Einheit, Spalte] für Spalten-Blöcke
        for ($i = 0, $n = count($ids); $i < $n; ++$i) {
            $unit = [$ids[$i]];
            if ($layouts[$i]) {
                while ($i + 1 < $n && $cols[$i + 1] > 0) {
                    $unit[] = $ids[++$i];
                }
            }
            $units[] = $unit;
        }

        $pos = array_search($slice->id, $ids, true);
        $inLayout = null !== Layout::parentOf($slice);
        if (!$inLayout) {
            $u = array_find_key($units, static fn (array $unit) => $unit[0] === $slice->id);
            $v = $up ? $u - 1 : $u + 1;
            if (null === $u || !isset($units[$v])) {
                self::fail($up ? 'Der Block steht schon oben.' : 'Der Block steht schon unten.');
            }
            [$units[$u], $units[$v]] = [$units[$v], $units[$u]];
        } else {
            // Nachbar in derselben Spalte derselben Gruppe tauschen
            $u = array_find_key($units, static fn (array $unit) => in_array($slice->id, $unit, true));
            $unit = $units[$u];
            $col = Layout::col($slice);
            $same = array_values(array_filter($unit, static fn (int $id) => $id !== $unit[0] && $cols[array_search($id, $ids, true)] === $col));
            $k = array_search($slice->id, $same, true);
            $other = $same[$up ? $k - 1 : $k + 1] ?? null;
            if (null === $other) {
                self::fail($up ? 'Der Block steht schon oben in der Spalte.' : 'Der Block steht schon unten in der Spalte.');
            }
            $a = array_search($slice->id, $unit, true);
            $b = array_search($other, $unit, true);
            [$unit[$a], $unit[$b]] = [$unit[$b], $unit[$a]];
            $units[$u] = $unit;
        }
        unset($pos);

        $prio = 0;
        $upd = Sql::factory();
        foreach (array_merge(...$units) as $id) {
            $upd->setQuery('UPDATE rex_article_slice SET priority = ? WHERE id = ?', [++$prio, $id]);
        }
        ArticleCache::delete($article->id, $article->languageId);

        return [];
    }

    private static function status(Article $article): array
    {
        [$slice, $module] = self::slice($article);
        ContentHandler::sliceStatus($slice->id, 1 === $slice->status ? 0 : 1);

        return ['online' => 1 !== $slice->status] + self::render($article, $slice->id, false);
    }

    private static function delete(Article $article): array
    {
        [$slice] = self::slice($article);
        $layout = Layout::isLayout($slice);
        ContentHandler::deleteSlice($slice->id);   // Spalten-Blöcke eines Layouts: FluidAddon (SLICE_DELETE)

        return $layout ? ['reload' => true] : [];
    }

    private static function publish(Article $article): array
    {
        if (ArticleRevision::LIVE === FluidAddon::revision()) {
            return [];
        }
        ArticleRevision::copyContent($article->id, $article->languageId, ArticleRevision::WORK, ArticleRevision::LIVE);
        ArticleCache::delete($article->id, $article->languageId);

        return ['message' => 'Veröffentlicht.'];
    }

    private static function discard(Article $article): array
    {
        if (ArticleRevision::LIVE === FluidAddon::revision()) {
            return [];
        }
        ArticleRevision::copyContent($article->id, $article->languageId, ArticleRevision::LIVE, ArticleRevision::WORK);

        return ['reload' => true];
    }

    // ------------------------------------------------------------------ Hilfen

    /**
     * HTML eines Blocks nach einer Änderung: Abschnitt bzw. Spalten-Block. Mit $wholeLayout das ganze Layout
     * (z. B. wenn ein Block die Spalte gewechselt hat). `target` = data-block, das ersetzt wird.
     *
     * @return array{html: string, target: int}
     */
    private static function render(Article $article, int $id, bool $wholeLayout): array
    {
        $slice = self::reload($article, $id);
        $module = Module::get($slice->moduleKey);
        if (!$module instanceof BlockModule) {
            return ['html' => '', 'target' => $id];
        }
        $parent = Layout::parentOf($slice);
        if ($parent && !$wholeLayout) {
            $pm = Module::get($parent->moduleKey);
            $bg = $pm instanceof BlockModule ? (string) $pm->block($parent)->tunes['background'] : '';

            return ['html' => $module->renderChild($slice, $bg), 'target' => $id];
        }
        if ($parent) {
            $pm = Module::get($parent->moduleKey);

            return ['html' => $pm instanceof BlockModule ? $pm->renderBlock($parent) : '', 'target' => $parent->id];
        }

        return ['html' => $module->renderBlock($slice), 'target' => $id];
    }

    /** @return array{ArticleSlice, BlockModule} */
    private static function slice(Article $article): array
    {
        $slice = self::reload($article, Request::post('slice', 'int', 0));
        $module = Module::get($slice->moduleKey);
        if (!$module instanceof BlockModule) {
            self::fail('Dieser Block hat kein Feldschema und lässt sich nur im Backend bearbeiten.');
        }

        return [$slice, $module];
    }

    private static function reload(Article $article, int $id): ArticleSlice
    {
        $slice = ArticleSlice::getArticleSliceById($id, $article->languageId, FluidAddon::revision());
        if (!$slice || $slice->articleId !== $article->id) {
            self::fail('Block nicht gefunden.', 404);
        }

        return $slice;
    }

    /**
     * Eingaben: komplettes Formular (`f[1]`/`f[2]`) oder Teiländerungen (`patch`) auf Basis der gespeicherten Daten.
     *
     * @return array{array<string, mixed>, array<string, mixed>}
     */
    private static function input(ArticleSlice $slice): array
    {
        $data = BlockModule::decode($slice->getValue(1));
        $tunes = BlockModule::decode($slice->getValue(2));
        $f = Request::post('f', 'array', []);
        if (isset($f[1]) && is_array($f[1])) {
            $data = self::clean($f[1]);
        }
        if (isset($f[2]) && is_array($f[2])) {
            $tunes = self::clean($f[2]);
        }
        foreach (Request::post('patch', 'array', []) as $path => $value) {
            $data = self::setPath($data, (string) $path, (string) $value);
        }

        return [$data, $tunes];
    }

    /** Wiederholgruppen neu nummerieren (Reihenfolge im Formular = Reihenfolge der Einträge) */
    private static function clean(array $a): array
    {
        foreach ($a as $k => $v) {
            if (is_array($v)) {
                $v = self::clean($v);
                $a[$k] = [] !== $v && array_filter(array_keys($v), is_int(...)) === array_keys($v) ? array_values($v) : $v;
            }
        }

        return $a;
    }

    /** `items.0.title` → $data['items'][0]['title'] */
    private static function setPath(array $data, string $path, string $value): array
    {
        $keys = explode('.', $path);
        $ref = &$data;
        foreach ($keys as $i => $k) {
            $k = ctype_digit($k) ? (int) $k : $k;
            if ($i === count($keys) - 1) {
                $ref[$k] = $value;
                break;
            }
            if (!isset($ref[$k]) || !is_array($ref[$k])) {
                $ref[$k] = [];
            }
            $ref = &$ref[$k];
        }

        return $data;
    }

    private static function changed(Article $article, int $sliceId, string $type): void
    {
        ArticleCache::delete($article->id, $article->languageId);
        $slice = ArticleSlice::getArticleSliceById($sliceId, $article->languageId, FluidAddon::revision());
        Extension::dispatch(new ArticleContentUpdated($article, $type, $slice, 'Block gespeichert.'));
    }

    private static function requireModulePerm(User $user, BlockModule $module): void
    {
        $perm = $user->getComplexPerm('modules');
        if (!$perm instanceof ModulePermission || !$perm->hasPerm($module->key)) {
            self::fail('Keine Berechtigung für diesen Blocktyp.', 403);
        }
    }

    private static function fail(string $message, int $code = 400): never
    {
        throw new ApiError($message, $code);
    }

    private static function send(array $data, int $status = 200): never
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

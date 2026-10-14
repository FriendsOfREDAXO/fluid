<?php

namespace FriendsOfRedaxo\Fluid\Data;

use FriendsOfRedaxo\Fluid\Activity;
use FriendsOfRedaxo\Fluid\Api;
use FriendsOfRedaxo\Fluid\ApiError;
use FriendsOfRedaxo\Fluid\BlockData;
use FriendsOfRedaxo\Fluid\BlockModule;
use FriendsOfRedaxo\Fluid\Editing;
use FriendsOfRedaxo\Fluid\Form\FieldForm;
use Redaxo\Core\Content\ModulePermission;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Security\User;
use Yakamara\YForm\Data\Record;
use Yakamara\YForm\Permission\Access;
use Yakamara\YForm\Table\Table;

use function Redaxo\Core\View\escape;

/**
 * Blöcke eines YForm-Datensatzes (Feld „Fluid-Blöcke“) direkt auf der Website bearbeiten – mit demselben Editor wie die
 * Seiten: Texte auf der Seite, Seitenleiste, hinzufügen, verschieben, verdoppeln, kopieren/einfügen, löschen.
 *
 * Block-ID im Editor: `r~{tabelle}~{eintrag}~{feld}~{uid}` (uid = feste Kennung des Blocks, leer = Anfang der Liste).
 * Gespeichert wird über die Record-API von YForm (Prüfung, Hooks); Recht: YForm „Tabelle bearbeiten“ + Modulrecht.
 * Datensätze kennen keinen Entwurf – Änderungen sind sofort gespeichert.
 */
final class RecordBlocks
{
    public const array ACTIONS = ['form', 'preview', 'save', 'render', 'add', 'delete', 'move', 'reorder', 'duplicate', 'copy', 'paste', 'status', 'col_move'];

    public static function is(string $id): bool
    {
        return str_starts_with($id, 'r~');
    }

    public static function id(string $table, int $record, string $field, string $uid = ''): string
    {
        return 'r~' . $table . '~' . $record . '~' . $field . '~' . $uid;
    }

    /** @return array{0: string, 1: int, 2: string, 3: string}|null Tabelle, Eintrag, Feld, uid */
    public static function parse(string $id): ?array
    {
        return preg_match('~^r\~([a-z0-9_]+)\~(\d+)\~([a-z0-9_]+)\~([a-z0-9]*)$~', $id, $m) ? [$m[1], (int) $m[2], $m[3], $m[4]] : null;
    }

    /** Darf der Benutzer die Blöcke dieses Feldes bearbeiten? */
    public static function canEdit(?User $user, Table $table): bool
    {
        return null !== $user && ($user->admin || Access::canEdit($table, $user));
    }

    // ------------------------------------------------------------------ Ausgabe

    /**
     * Blöcke eines Datensatzes für die Website – im Bearbeiten-Modus (mit Recht) bearbeitbar, sonst wie BlockData::render.
     */
    public static function render(Table $table, Record $record, FluidBlocks $field, int $articleId): string
    {
        $user = Editing::user();
        if (!Editing::active() || !self::canEdit($user, $table)) {
            return BlockData::render($record->get($field->name), 'r' . $record->id . '-' . $field->name, $articleId);
        }
        $name = $table->definition->name;
        $blocks = BlockData::decode($record->get($field->name));
        $withUids = BlockData::withUids($blocks);
        if ($withUids !== $blocks) {
            // ältere Daten: Kennungen einmalig speichern, damit die IDs im Editor stabil sind
            $record->set($field->name, $withUids);
            $record->save();
        }
        $html = '';
        $prev = null;
        foreach ($withUids as $item) {
            [$b, $out] = self::renderItem($name, $record->id, $field->name, $item, $prev, $articleId);
            $html .= $out;
            $prev = $b;
        }
        $start = self::id($name, $record->id, $field->name);
        $empty = '' === $html
            ? '<div class="fbr-empty"><p>Noch keine Blöcke für diesen Eintrag.</p><button type="button" class="btn btn--primary btn--sm" data-record-add="' . escape($start) . '">+ Ersten Block einfügen</button></div>'
            : '';

        return '<div class="df-blocks df-blocks--edit" data-record-blocks="' . escape($start) . '" data-allowed="' . escape(implode(',', array_keys(BlockData::modules($field->allowed)))) . '">'
            . '<p class="fbr-note">Blöcke dieses Eintrags – Änderungen werden sofort gespeichert (Datensatz, kein Entwurf).</p>'
            . $empty . $html . '</div>';
    }

    /** @return array{0: \FriendsOfRedaxo\Fluid\Block, 1: string} */
    private static function renderItem(string $table, int $rid, string $field, array $item, mixed $prev, int $articleId): array
    {
        $m = BlockData::module($item['module']) ?? throw new ApiError('Unbekannter Block.');
        $uid = (string) ($item['uid'] ?? '');
        $b = $m->blockFromData('r-' . $uid, $item['data'], $item['tunes'], $prev instanceof \FriendsOfRedaxo\Fluid\Block ? $prev : null, $articleId);

        return [$b, $m->renderData($b, self::id($table, $rid, $field, $uid))];
    }

    // ------------------------------------------------------------------ API (vom Editor über Api::handle)

    /** @return array<string, mixed> */
    public static function handle(string $action, User $user, int $articleId): array
    {
        $ref = (string) (Request::post('slice', 'string', '') ?: Request::post('after', 'string', ''));
        [$tname, $rid, $fname, $uid] = self::parse($ref) ?? throw new ApiError('Block nicht gefunden.');
        $table = YFormBridge::table($tname) ?? throw new ApiError('Tabelle nicht gefunden.');
        if (!self::canEdit($user, $table)) {
            throw new ApiError('Dafür fehlt Ihnen das Recht, diese YForm-Tabelle zu bearbeiten.');
        }
        $field = $table->getField($fname);
        if (!$field instanceof FluidBlocks) {
            throw new ApiError('Feld nicht gefunden.');
        }
        $record = $table::query()->where('id', $rid)->first() ?? throw new ApiError('Eintrag nicht gefunden.');
        $blocks = BlockData::withUids(BlockData::decode($record->get($fname)));
        $index = static function (string $uid) use (&$blocks): int {
            foreach ($blocks as $i => $b) {
                if (($b['uid'] ?? '') === $uid) {
                    return $i;
                }
            }
            throw new ApiError('Block nicht gefunden – bitte Seite neu laden.');
        };
        $module = static function (string $key) use ($user, $field): BlockModule {
            $m = BlockData::module($key);
            if (!$m || !isset(BlockData::modules($field->allowed)[$key])) {
                throw new ApiError('Dieser Block ist in diesem Feld nicht vorgesehen.');
            }
            $perm = $user->getComplexPerm('modules');
            if (!$user->admin && (!$perm instanceof ModulePermission || !$perm->hasPerm($key))) {
                throw new ApiError('Keine Berechtigung für diesen Block.');
            }

            return $m;
        };
        $store = static function () use (&$blocks, $record, $fname, $tname, $rid, $articleId): void {
            $record->set($fname, array_values($blocks));
            $record->save();
            Activity::log('save', $articleId, 'Eintrag ' . $tname . ' #' . $rid);
        };
        $one = static function (int $i) use (&$blocks, $tname, $rid, $fname, $articleId): array {
            [, $html] = self::renderItem($tname, $rid, $fname, $blocks[$i], null, $articleId);

            return ['html' => $html, 'target' => self::id($tname, $rid, $fname, (string) $blocks[$i]['uid'])];
        };

        switch ($action) {
            case 'form':
                $i = $index($uid);
                $m = $module($blocks[$i]['module']);

                return ['html' => FieldForm::render($m, $blocks[$i]['data'], $blocks[$i]['tunes'], 'f', false), 'label' => (string) ($m->definition()['label'] ?? $m->name)];
            case 'preview':
                $i = $index($uid);
                [$blocks[$i]['data'], $blocks[$i]['tunes']] = Api::applyInput($blocks[$i]['data'], $blocks[$i]['tunes']);

                return $one($i);
            case 'save':
                $i = $index($uid);
                $m = $module($blocks[$i]['module']);
                [$data, $tunes] = Api::applyInput($blocks[$i]['data'], $blocks[$i]['tunes']);
                $blocks[$i]['data'] = FieldForm::withDefaults($m->fields(), $data, $m->variants());
                $blocks[$i]['tunes'] = $tunes;
                $store();

                return $one($i);
            case 'render':
                return $one($index($uid));
            case 'add':
            case 'duplicate':
            case 'paste':
                if ('add' === $action) {
                    $m = $module(Request::post('module', 'string', ''));
                    $new = ['module' => $m->key, 'data' => FieldForm::withDefaults($m->fields(), [], $m->variants()), 'tunes' => []];
                } elseif ('duplicate' === $action) {
                    $new = $blocks[$index($uid)];
                } else {
                    $clip = json_decode(Request::post('clip', 'string', ''), true);
                    if (!\is_array($clip) || isset($clip['children'])) {
                        throw new ApiError('Dieser Block lässt sich hier nicht einfügen.');
                    }
                    $m = $module((string) ($clip['module'] ?? ''));
                    $new = ['module' => $m->key, 'data' => BlockModule::decode((string) ($clip['value1'] ?? '')), 'tunes' => array_diff_key(BlockModule::decode((string) ($clip['value2'] ?? '')), ['col' => 1])];
                }
                $new['uid'] = BlockData::uid();
                $at = '' === $uid ? 0 : $index($uid) + 1;
                $wasEmpty = [] === $blocks;
                array_splice($blocks, $at, 0, [$new]);
                $store();

                // erster Block: Seite neu laden (der Platzhalter „Noch keine Blöcke“ verschwindet)
                return ['slice' => self::id($tname, $rid, $fname, $new['uid'])] + ($wasEmpty ? ['refresh' => true] : $one($at));
            case 'delete':
                array_splice($blocks, $index($uid), 1);
                $store();

                return [] === $blocks ? ['reload' => true] : [];
            case 'move':
                $i = $index($uid);
                $j = 'up' === Request::post('dir', 'string') ? $i - 1 : $i + 1;
                if (isset($blocks[$j])) {
                    [$blocks[$i], $blocks[$j]] = [$blocks[$j], $blocks[$i]];
                    $store();
                }

                return [];
            case 'reorder':
                $item = $blocks[$index($uid)];
                $blocks = array_values(array_filter($blocks, static fn ($b) => ($b['uid'] ?? '') !== $uid));
                $before = self::parse(Request::post('before', 'string', ''));
                $pos = $before && '' !== $before[3] ? array_search($before[3], array_column($blocks, 'uid'), true) : false;
                array_splice($blocks, false === $pos ? \count($blocks) : (int) $pos, 0, [$item]);
                $store();

                return [];
            case 'copy':
                $b = $blocks[$index($uid)];
                $m = BlockData::module($b['module']);

                return ['clip' => ['module' => $b['module'], 'value1' => json_encode($b['data'], JSON_UNESCAPED_UNICODE), 'value2' => json_encode($b['tunes'], JSON_UNESCAPED_UNICODE)], 'label' => $m->name ?? $b['module']];
            default:
                throw new ApiError('Blöcke eines Eintrags haben keinen Online-Schalter und keine Spalten.');
        }
    }
}

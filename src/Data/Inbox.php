<?php

namespace FriendsOfRedaxo\Fluid\Data;

use FriendsOfRedaxo\Fluid\ApiError;
use FriendsOfRedaxo\Fluid\BlockModule;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Security\User;
use Yakamara\YForm\Field\CreatedAt;
use Yakamara\YForm\Field\Email;
use Yakamara\YForm\Permission\Access;
use Yakamara\YForm\Table\Table;

/**
 * Anfragen-Postfach (Modell aus KLXM Studio – Anfragen): Einträge der Tabellen, in die die Formulare der Website
 * speichern (Blöcke „Formular“/„Kontakt“ → #[AsForm]-Klasse → Aktion SaveRecord). Status neu/erledigt in rex_fluid_inbox –
 * die YForm-Tabellen bleiben unverändert. Sichtbar nur mit Recht „Tabellen ansehen“, Löschen mit „bearbeiten“ (YForm).
 */
final class Inbox
{
    public const string TABLE = 'rex_fluid_inbox';

    /** @return list<Table> */
    public static function tables(User $user): array
    {
        if (!YFormBridge::available()) {
            return [];
        }
        $names = [];
        foreach (Sql::factory()->getArray('SELECT value1 FROM rex_article_slice WHERE module IN ("fluid.form", "fluid.contact")') as $r) {
            $form = YFormBridge::formDefinition((string) (BlockModule::decode((string) $r['value1'])['form'] ?? ''));
            foreach ($form ? YFormBridge::formTables($form) : [] as $t) {
                $names[$t] = true;
            }
        }
        $out = [];
        foreach (array_keys($names) as $n) {
            if (($t = YFormBridge::table($n)) && Access::canView($t, $user)) {
                $out[] = $t;
            }
        }

        return $out;
    }

    public static function countNew(User $user): int
    {
        $n = 0;
        foreach (self::tables($user) as $t) {
            $n += (int) Sql::factory()->getArray('SELECT COUNT(*) AS n FROM ' . Sql::factory()->escapeIdentifier($t->definition->name) . ' d
                LEFT JOIN ' . self::TABLE . ' s ON s.table_name = ? AND s.dataset_id = d.id WHERE COALESCE(s.state, "new") = "new"', [$t->definition->name])[0]['n'];
        }

        return $n;
    }

    /** @return list<array<string, mixed>> neueste zuerst (je Tabelle höchstens 100) */
    public static function list(User $user, string $filter = 'new'): array
    {
        $out = [];
        foreach (self::tables($user) as $t) {
            $name = $t->definition->name;
            $states = [];
            foreach (Sql::factory()->getArray('SELECT dataset_id, state FROM ' . self::TABLE . ' WHERE table_name = ?', [$name]) as $s) {
                $states[(int) $s['dataset_id']] = (string) $s['state'];
            }
            $fields = YFormBridge::detailFields($t);
            $stamp = null;
            $email = null;
            foreach ($t->getFields() as $f) {
                $stamp ??= $f instanceof CreatedAt ? $f : null;
                $email ??= $f instanceof Email ? $f : null;
            }
            foreach ($t::query()->orderBy('id', 'DESC')->limit(100)->find() as $rec) {
                $state = $states[$rec->id] ?? 'new';
                if ('all' !== $filter && $state !== $filter) {
                    continue;
                }
                $values = [];
                foreach ($fields as $f) {
                    if ('' !== ($v = YFormBridge::text($rec, $f))) {
                        $values[] = ['label' => $f->translatedLabel(), 'value' => $v];
                    }
                }
                $out[] = [
                    'table' => $name, 'tableLabel' => $t->label(), 'id' => $rec->id, 'state' => $state,
                    'when' => $stamp ? ($rec->get($stamp->name)?->format('Y-m-d H:i:s') ?? '') : '',
                    'email' => $email ? YFormBridge::text($rec, $email) : '',
                    'title' => $values[0]['value'] ?? ('#' . $rec->id), 'values' => $values,
                    'edit' => YFormBridge::editUrl($t, $rec->id), 'canDelete' => Access::canEdit($t, $user),
                ];
            }
        }
        usort($out, static fn ($a, $b) => strcmp($b['when'] ?: sprintf('%012d', $b['id']), $a['when'] ?: sprintf('%012d', $a['id'])));

        return $out;
    }

    public static function setState(User $user, string $table, int $id, string $state): void
    {
        self::guard($user, $table, false);
        Sql::factory()->setQuery('REPLACE INTO ' . self::TABLE . ' (table_name, dataset_id, state, updated, user) VALUES (?, ?, ?, NOW(), ?)',
            [$table, $id, 'done' === $state ? 'done' : 'new', $user->login]);
    }

    public static function delete(User $user, string $table, int $id): void
    {
        $t = self::guard($user, $table, true);
        $t::find($id)?->delete();
        Sql::factory()->setQuery('DELETE FROM ' . self::TABLE . ' WHERE table_name = ? AND dataset_id = ?', [$table, $id]);
    }

    private static function guard(User $user, string $table, bool $edit): Table
    {
        foreach (self::tables($user) as $t) {
            if ($t->definition->name === $table && (!$edit || Access::canEdit($t, $user))) {
                return $t;
            }
        }
        throw new ApiError('Keine Berechtigung für diese Anfragen.');
    }
}

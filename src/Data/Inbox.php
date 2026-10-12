<?php

namespace FriendsOfRedaxo\Fluid\Data;

use FriendsOfRedaxo\Fluid\ApiError;
use FriendsOfRedaxo\Fluid\BlockModule;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Security\User;
use Yakamara\YForm\Manager\Dataset;
use Yakamara\YForm\Manager\Field;
use Yakamara\YForm\Manager\Table\Table;

use function count;
use function in_array;

/**
 * Anfragen-Postfach (Modell aus KLXM Studio – Anfragen): Einträge der YForm-Tabellen, die auf der Website als Formular
 * stehen (Blöcke „Formular“ und „Kontakt“). Status neu/erledigt in rex_fluid_inbox – die YForm-Tabellen bleiben unverändert.
 * Sichtbar nur mit Leserecht auf die Tabelle (YForm-Rechte).
 */
final class Inbox
{
    public const string TABLE = 'rex_fluid_inbox';

    /** @return list<Table> Tabellen mit Formular auf der Website, die der Benutzer sehen darf */
    public static function tables(User $user): array
    {
        if (!YFormBridge::available()) {
            return [];
        }
        $names = [];
        foreach (Sql::factory()->getArray('SELECT value1 FROM rex_article_slice WHERE module IN ("fluid.form", "fluid.contact")') as $r) {
            $d = BlockModule::decode((string) $r['value1']);
            foreach (['table', 'form_table'] as $k) {
                if ('' !== ($t = (string) ($d[$k] ?? ''))) {
                    $names[$t] = true;
                }
            }
        }
        $out = [];
        foreach (array_keys($names) as $n) {
            $t = YFormBridge::table($n);
            if ($t && ($user->admin || $t->isGranted('VIEW', $user))) {
                $out[] = $t;
            }
        }

        return $out;
    }

    /** Anzahl neuer Einträge (für die Leiste) */
    public static function countNew(User $user): int
    {
        $n = 0;
        foreach (self::tables($user) as $t) {
            $n += (int) Sql::factory()->getArray('SELECT COUNT(*) AS n FROM ' . Sql::factory()->escapeIdentifier($t->getTableName()) . ' d
                LEFT JOIN ' . self::TABLE . ' s ON s.table_name = ? AND s.dataset_id = d.id WHERE COALESCE(s.state, "new") = "new"', [$t->getTableName()])[0]['n'];
        }

        return $n;
    }

    /** @return list<array<string, mixed>> neueste zuerst (je Tabelle höchstens 100) */
    public static function list(User $user, string $filter = 'new'): array
    {
        $out = [];
        foreach (self::tables($user) as $t) {
            $states = [];
            foreach (Sql::factory()->getArray('SELECT dataset_id, state FROM ' . self::TABLE . ' WHERE table_name = ?', [$t->getTableName()]) as $s) {
                $states[(int) $s['dataset_id']] = (string) $s['state'];
            }
            $fields = YFormBridge::detailFields($t);
            $stamp = null;
            $email = null;
            foreach ($t->getValueFields() as $f) {
                if (!$stamp && in_array($f->getTypeName(), ['datestamp', 'datetime'], true)) {
                    $stamp = $f;
                }
                if (!$email && 'email' === $f->getTypeName()) {
                    $email = $f;
                }
            }
            $q = Dataset::query($t->getTableName())->resetOrderBy()->orderBy('id', 'DESC')->limit(100);
            foreach ($q->find() as $ds) {
                $state = $states[$ds->getId()] ?? 'new';
                if ('all' !== $filter && $state !== $filter) {
                    continue;
                }
                $values = [];
                foreach ($fields as $f) {
                    $v = YFormBridge::text($ds, $f);
                    if ('' !== $v) {
                        $values[] = ['label' => (string) ($f->getLabel() ?: $f->getName()), 'value' => $v];
                    }
                }
                $out[] = [
                    'table' => $t->getTableName(), 'tableLabel' => $t->getNameLocalized(), 'id' => $ds->getId(), 'state' => $state,
                    'when' => $stamp && $ds->hasValue($stamp->getName()) ? (string) $ds->getValue($stamp->getName()) : '',
                    'email' => $email ? trim((string) $ds->getValue($email->getName())) : '',
                    'title' => $values[0]['value'] ?? ('#' . $ds->getId()), 'values' => $values,
                    'edit' => YFormBridge::editUrl($t, $ds->getId()),
                    'canDelete' => $user->admin || $t->isGranted('EDIT', $user),
                ];
            }
        }
        usort($out, static fn ($a, $b) => strcmp($b['when'] ?: sprintf('%012d', $b['id']), $a['when'] ?: sprintf('%012d', $a['id'])));

        return $out;
    }

    public static function setState(User $user, string $table, int $id, string $state): void
    {
        self::guard($user, $table, 'VIEW');
        Sql::factory()->setQuery('REPLACE INTO ' . self::TABLE . ' (table_name, dataset_id, state, updated, user) VALUES (?, ?, ?, NOW(), ?)',
            [$table, $id, 'done' === $state ? 'done' : 'new', $user->login]);
    }

    public static function delete(User $user, string $table, int $id): void
    {
        self::guard($user, $table, 'EDIT');
        Dataset::get($id, $table)?->delete();
        Sql::factory()->setQuery('DELETE FROM ' . self::TABLE . ' WHERE table_name = ? AND dataset_id = ?', [$table, $id]);
    }

    private static function guard(User $user, string $table, string $perm): void
    {
        foreach (self::tables($user) as $t) {
            if ($t->getTableName() === $table && ($user->admin || $t->isGranted($perm, $user))) {
                return;
            }
        }
        throw new ApiError('Keine Berechtigung für diese Anfragen.');
    }
}

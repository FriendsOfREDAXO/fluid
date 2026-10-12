<?php

namespace FriendsOfRedaxo\Fluid;

use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Content\ArticleSliceHistory;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Table;

use function count;
use function in_array;

/**
 * Versionen einer Seite (Modell aus KLXM Studio – Pages::addRevision): Stände beim Speichern (Entwurf) und vor dem
 * Veröffentlichen (Live), gespeichert in der Historie-Tabelle des Cores (rex_article_slice_history, Spalte revision).
 * Wiederherstellen schreibt einen Stand in den Entwurf – live geht er erst mit „Veröffentlichen“. Je Seite bleiben die
 * letzten KEEP Stände; der aktuelle Entwurf wird vor dem Wiederherstellen selbst gesichert (nichts geht verloren).
 */
final class Versions
{
    public const int KEEP = 40;
    /** Speichern innerhalb dieser Sekunden fasst ein Stand zusammen (Tippen, mehrere Blöcke) */
    private const int MERGE = 120;

    public const array TYPES = ['fluid_save' => 'Gespeichert', 'fluid_publish' => 'Vor dem Veröffentlichen', 'fluid_restore' => 'Vor dem Wiederherstellen', 'fluid_discard' => 'Vor dem Verwerfen'];

    public static function snapshot(int $articleId, int $languageId, int $revision, string $type): void
    {
        ArticleSliceHistory::checkTables();
        $sql = Sql::factory();
        $table = ArticleSliceHistory::getTable();
        $user = \Redaxo\Core\Core::getUser()?->login ?? Editing::user()?->login ?? '';
        // Speichern kurz hintereinander: letzten eigenen Stand ersetzen statt neuen anlegen
        if ('fluid_save' === $type) {
            $last = $sql->getArray('SELECT history_date, history_user, history_type FROM ' . $table . ' WHERE article_id = ? AND language_id = ? AND revision = ? ORDER BY history_date DESC LIMIT 1', [$articleId, $languageId, $revision])[0] ?? null;
            if ($last && 'fluid_save' === $last['history_type'] && $last['history_user'] === $user && time() - strtotime((string) $last['history_date']) < self::MERGE) {
                $sql->setQuery('DELETE FROM ' . $table . ' WHERE article_id = ? AND language_id = ? AND revision = ? AND history_date = ?', [$articleId, $languageId, $revision, $last['history_date']]);
            }
        }
        $date = date(Sql::FORMAT_DATETIME);
        $rows = $sql->getArray('SELECT * FROM rex_article_slice WHERE article_id = ? AND language_id = ? AND revision = ?', [$articleId, $languageId, $revision]);
        $cols = array_map(static fn ($c) => $c->name, Table::get($table)->getColumns());
        foreach ($rows ?: [['__empty' => true]] as $row) {
            $ins = Sql::factory()->setTable($table);
            if (isset($row['__empty'])) {
                // leere Seite: Markierungszeile, damit der Stand sichtbar und wiederherstellbar ist
                $ins->setValues(['article_id' => $articleId, 'language_id' => $languageId, 'revision' => $revision, 'slice_id' => 0, 'module' => '', 'ctype_id' => 1, 'priority' => 0, 'status' => 0]);
            } else {
                foreach ($row as $k => $v) {
                    if ('id' === $k) {
                        $ins->setValue('slice_id', $v);
                    } elseif (in_array($k, $cols, true)) {
                        $ins->setValue($k, $v);
                    }
                }
            }
            $ins->setValue('history_type', $type)->setValue('history_date', $date)->setValue('history_user', $user)->insert();
        }
        // Aufräumen: nur die letzten KEEP Stände je Seite/Fassung
        $dates = array_column($sql->getArray('SELECT DISTINCT history_date FROM ' . $table . ' WHERE article_id = ? AND language_id = ? AND revision = ? ORDER BY history_date DESC', [$articleId, $languageId, $revision]), 'history_date');
        if (count($dates) > self::KEEP) {
            $sql->setQuery('DELETE FROM ' . $table . ' WHERE article_id = ? AND language_id = ? AND revision = ? AND history_date < ?', [$articleId, $languageId, $revision, $dates[self::KEEP - 1]]);
        }
    }

    /** @return list<array{date: string, type: string, label: string, user: string, revision: int, blocks: int}> */
    public static function list(int $articleId, int $languageId): array
    {
        ArticleSliceHistory::checkTables();
        $rows = Sql::factory()->getArray(
            'SELECT history_date, history_type, history_user, revision, SUM(slice_id > 0) AS blocks FROM ' . ArticleSliceHistory::getTable()
            . ' WHERE article_id = ? AND language_id = ? GROUP BY history_date, history_type, history_user, revision ORDER BY history_date DESC LIMIT 60',
            [$articleId, $languageId],
        );

        return array_map(static fn (array $r) => [
            'date' => (string) $r['history_date'], 'type' => (string) $r['history_type'],
            'label' => self::TYPES[$r['history_type']] ?? ('Live: ' . str_replace('_', ' ', (string) $r['history_type'])),
            'user' => (string) $r['history_user'], 'revision' => (int) $r['revision'], 'blocks' => (int) $r['blocks'],
        ], $rows);
    }

    /** Stand in den Entwurf zurückholen (aktueller Entwurf wird vorher gesichert) */
    public static function restore(int $articleId, int $languageId, string $date, int $fromRevision): void
    {
        $table = ArticleSliceHistory::getTable();
        $sql = Sql::factory();
        $rows = $sql->getArray('SELECT * FROM ' . $table . ' WHERE article_id = ? AND language_id = ? AND revision = ? AND history_date = ?', [$articleId, $languageId, $fromRevision, $date]);
        if (!$rows) {
            throw new ApiError('Diesen Stand gibt es nicht mehr.');
        }
        $target = FluidAddon::revision();
        self::snapshot($articleId, $languageId, $target, 'fluid_restore');
        $sql->setQuery('DELETE FROM rex_article_slice WHERE article_id = ? AND language_id = ? AND revision = ?', [$articleId, $languageId, $target]);
        $cols = array_map(static fn ($c) => $c->name, Table::get('rex_article_slice')->getColumns());
        foreach ($rows as $row) {
            if (0 === (int) $row['slice_id']) {
                continue;   // Markierung „leere Seite“
            }
            $ins = Sql::factory()->setTable('rex_article_slice');
            foreach ($cols as $c) {
                if ('id' !== $c && array_key_exists($c, $row)) {
                    $ins->setValue($c, $row[$c]);
                }
            }
            $ins->setValue('revision', $target)->insert();
        }
        ArticleCache::delete($articleId, $languageId);
    }

    public static function isWorkVersion(): bool
    {
        return ArticleRevision::LIVE !== FluidAddon::revision();
    }
}

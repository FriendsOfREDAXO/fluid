<?php

namespace FriendsOfRedaxo\Fluid;

use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Content\ArticleSliceHistory;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Filesystem\Url;
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
        $user = \Redaxo\Core\Core::getUser()->login ?? Editing::user()->login ?? '';
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
                $ins->setValues(array_intersect_key(['article_id' => $articleId, 'language_id' => $languageId, 'revision' => $revision, 'slice_id' => 0, 'module' => '', 'ctype_id' => 1, 'priority' => 0, 'status' => 0,
                    'createdate' => $date, 'updatedate' => $date, 'createuser' => $user, 'updateuser' => $user], array_flip($cols)));
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

    // ------------------------------------------------------------------ Versionen-Ansicht (wie KLXM Studio: Zeitleiste, Vorschau, Vergleich)

    /** Vorschau eines Stands auf der Website: ?fluid_version=now|live|<revision>-<YmdHis> (&fluid_mark=1 hebt Änderungen hervor) */
    public const string PARAM = 'fluid_version';

    /** @var array{revision: int, date: ?string}|null Stand, den die laufende Anfrage zeigt */
    public static ?array $preview = null;

    /** @var array<int, string> Slice-ID → new|changed (Hervorhebung in der Vorschau) */
    public static array $marks = [];

    /** @return array{revision: int, date: ?string}|null */
    public static function parseKey(string $key): ?array
    {
        return match (true) {
            'now' === $key => ['revision' => FluidAddon::revision(), 'date' => null],
            'live' === $key => ['revision' => ArticleRevision::LIVE, 'date' => null],
            1 === preg_match('/^(\d)-(\d{14})$/', $key, $m) && false !== $d = \DateTimeImmutable::createFromFormat('YmdHis', $m[2]) => ['revision' => (int) $m[1], 'date' => $d->format(Sql::FORMAT_DATETIME)],
            default => null,
        };
    }

    private static function key(int $revision, string $date): string
    {
        return $revision . '-' . date('YmdHis', (int) strtotime($date));
    }

    /** Spalten, die zum Inhalt eines Blocks gehören (für Vergleich und Gleichheit von Ständen) */
    private const array META = ['id', 'slice_id', 'article_id', 'language_id', 'revision', 'priority', 'createdate', 'updatedate', 'createuser', 'updateuser', 'history_type', 'history_date', 'history_user'];

    /**
     * Stände einer Seite, neueste zuerst. Erster Eintrag ist „Jetzt“ (Entwurf bzw. live); ältere Stände mit gleichem Inhalt
     * wie ein neuerer fallen weg.
     *
     * @return list<array{key: string, now: bool, revision: int, date: string, type: string, user: string, blocks: array<int, string>, modules: array<int, string>}>
     */
    public static function states(int $articleId, int $languageId): array
    {
        ArticleSliceHistory::checkTables();
        $sql = Sql::factory();
        $table = ArticleSliceHistory::getTable();
        $sig = static function (array $rows): array {
            $blocks = [];
            $modules = [];
            foreach ($rows as $row) {
                $id = (int) ($row['slice_id'] ?? $row['id']);
                if ($id <= 0) {
                    continue;
                }
                $blocks[$id] = md5((string) json_encode(array_diff_key($row, array_flip(self::META))));
                $modules[$id] = (string) $row['module'];
            }

            return [$blocks, $modules];
        };

        $revision = FluidAddon::revision();
        $rows = $sql->getArray('SELECT * FROM rex_article_slice WHERE article_id = ? AND language_id = ? AND revision = ? ORDER BY ctype_id, priority', [$articleId, $languageId, $revision]);
        [$blocks, $modules] = $sig($rows);
        $changed = array_filter(array_column($rows, 'updatedate'));
        // Wiederherstellen kopiert alte Zeilen (alte updatedate) – „Jetzt“ ist mindestens so neu wie der neueste gesicherte Stand
        $changed[] = (string) ($sql->getArray('SELECT MAX(history_date) AS d FROM ' . $table . ' WHERE article_id = ? AND language_id = ?', [$articleId, $languageId])[0]['d'] ?? '');
        $changed = array_filter($changed);
        $out = [['key' => 'now', 'now' => true, 'revision' => $revision, 'date' => $changed ? (string) max($changed) : date(Sql::FORMAT_DATETIME), 'type' => 'now', 'user' => '', 'blocks' => $blocks, 'modules' => $modules]];
        // gleich = gleiche Blöcke in gleicher Reihenfolge (IDs zählen nicht: Live-Fassung hat eigene Slice-IDs)
        $seen = [md5((string) json_encode(array_values($blocks))) => true];

        $groups = $sql->getArray('SELECT history_date, revision, MAX(history_type) AS history_type, MAX(history_user) AS history_user FROM ' . $table
            . ' WHERE article_id = ? AND language_id = ? GROUP BY history_date, revision ORDER BY history_date DESC LIMIT 60', [$articleId, $languageId]);
        foreach ($groups as $g) {
            $rows = $sql->getArray('SELECT * FROM ' . $table . ' WHERE article_id = ? AND language_id = ? AND revision = ? AND history_date = ? ORDER BY ctype_id, priority',
                [$articleId, $languageId, (int) $g['revision'], $g['history_date']]);
            [$blocks, $modules] = $sig($rows);
            $hash = md5((string) json_encode(array_values($blocks)));
            if (isset($seen[$hash])) {
                continue;   // gleicher Inhalt wie ein neuerer Stand
            }
            $seen[$hash] = true;
            $out[] = ['key' => self::key((int) $g['revision'], (string) $g['history_date']), 'now' => false, 'revision' => (int) $g['revision'], 'date' => (string) $g['history_date'],
                'type' => (string) $g['history_type'], 'user' => (string) $g['history_user'], 'blocks' => $blocks, 'modules' => $modules];
        }

        return $out;
    }

    /**
     * Unterschied zweier Stände je Block: neu, geändert, entfernt (Bezeichnungen der entfernten Blöcke).
     * Zuordnung erst über die Slice-ID, dann über gleichen Inhalt, dann über Blocktyp in Reihenfolge – Veröffentlichen und
     * Wiederherstellen legen die Blöcke mit neuen IDs an.
     *
     * @param array<int, string> $blocks
     * @param array<int, string> $modules
     * @param array<int, string>|null $base
     * @param array<int, string> $baseModules
     * @return array{marks: array<int, string>, new: int, changed: int, removed: list<string>}
     */
    public static function diff(array $blocks, array $modules, ?array $base, array $baseModules = []): array
    {
        $out = ['marks' => [], 'new' => 0, 'changed' => 0, 'removed' => []];
        if (null === $base) {
            return $out;
        }
        $open = $base;            // noch nicht zugeordnete Blöcke des älteren Stands
        $pending = [];            // Blöcke ohne Partner nach ID
        foreach ($blocks as $id => $hash) {
            if (isset($open[$id])) {
                if ($open[$id] !== $hash) {
                    $out['marks'][$id] = 'changed';
                }
                unset($open[$id]);
            } else {
                $pending[$id] = $hash;
            }
        }
        foreach ($pending as $id => $hash) {
            if (false !== $match = array_search($hash, $open, true)) {
                unset($open[$match], $pending[$id]);   // gleicher Inhalt, nur neue ID
            }
        }
        foreach ($pending as $id => $hash) {
            foreach ($open as $oid => $ohash) {
                if (($baseModules[$oid] ?? '') === ($modules[$id] ?? '')) {
                    $out['marks'][$id] = 'changed';
                    unset($open[$oid], $pending[$id]);
                    break;
                }
            }
        }
        foreach ($pending as $id => $hash) {
            $out['marks'][$id] = 'new';
        }
        foreach (array_keys($open) as $oid) {
            $out['removed'][] = self::moduleLabel($baseModules[$oid] ?? '');
        }
        $counts = array_count_values($out['marks']);
        $out['new'] = $counts['new'] ?? 0;
        $out['changed'] = $counts['changed'] ?? 0;

        return $out;
    }

    private static function moduleLabel(string $key): string
    {
        $m = Module::get($key);
        if ($m instanceof BlockModule) {
            return (string) ($m->definition()['label'] ?? $m->name);
        }

        return $m->name ?? $key;
    }

    /** Daten für die Versionen-Ansicht im Editor (assets/editor/versions.js) */
    public static function view(Article $article): array
    {
        $states = self::states($article->id, $article->languageId);
        $url = static fn (string $key, bool $mark = false): string => Url::article($article->id, $article->languageId, [self::PARAM => $key] + ($mark ? ['fluid_mark' => 1] : []));
        $now = time();
        $versions = [];
        foreach ($states as $i => $st) {
            $older = $states[$i + 1] ?? null;
            $d = self::diff($st['blocks'], $st['modules'], $older['blocks'] ?? null, $older['modules'] ?? []);
            $versions[] = [
                'id' => $st['key'], 'now' => $st['now'],
                ...self::when($st['date'], $now),
                'note' => $st['now'] ? (self::isWorkVersion() && self::hasChanges($article) ? 'Entwurf mit unveröffentlichten Änderungen' : 'Aktueller Stand') : (self::TYPES[$st['type']] ?? 'Gespeichert'),
                'user' => $st['user'],
                'preview' => $url($st['key']), 'preview_mark' => $url($st['key'], true),
                'diff' => ['new' => $d['new'], 'changed' => $d['changed'], 'removed' => $d['removed'], 'first' => null === $older],
            ];
        }
        $hasLive = (bool) Sql::factory()->getArray('SELECT id FROM rex_article_slice WHERE article_id = ? AND language_id = ? AND revision = ? LIMIT 1', [$article->id, $article->languageId, ArticleRevision::LIVE]);

        return [
            'kind' => 'page', 'title' => $article->name, 'versions' => $versions,
            'live' => $url(self::isWorkVersion() ? 'live' : 'now'), 'has_live' => $hasLive,
            'today' => self::when(date(Sql::FORMAT_DATETIME), $now)['day'],
            'can_restore' => self::isWorkVersion(),
        ];
    }

    private static function hasChanges(Article $article): bool
    {
        $sig = static fn (int $rev): string => md5((string) json_encode(array_map(
            static fn (array $r) => array_diff_key($r, array_flip(self::META)),
            Sql::factory()->getArray('SELECT * FROM rex_article_slice WHERE article_id = ? AND language_id = ? AND revision = ? ORDER BY ctype_id, priority', [$article->id, $article->languageId, $rev]),
        )));

        return $sig(ArticleRevision::WORK) !== $sig(ArticleRevision::LIVE);
    }

    /** Zeitangaben eines Stands: day (Gruppe der Zeitleiste), time, ago, at, ts, hm (Uhr), dm (Kalenderblatt) */
    private static function when(string $date, int $now): array
    {
        $ts = strtotime($date) ?: $now;
        $fmt = static fn (string $pattern): string => class_exists(\IntlDateFormatter::class)
            ? (string) (new \IntlDateFormatter('de_DE', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, date_default_timezone_get(), null, $pattern))->format($ts)
            : date('d.m.Y', $ts);
        $diff = $now - $ts;
        $ago = match (true) {
            $diff < 60 => 'gerade eben',
            $diff < 3600 => 'vor ' . intdiv($diff, 60) . ' Min.',
            $diff < 86400 => 'vor ' . intdiv($diff, 3600) . ' Std.',
            $diff < 172800 => 'gestern',
            default => 'vor ' . intdiv($diff, 86400) . ' Tagen',
        };

        return ['label' => $ago, 'day' => $fmt('d. MMMM y'), 'time' => date('H:i', $ts), 'ago' => $ago, 'at' => $fmt('d. MMMM y') . ', ' . date('H:i', $ts) . ' Uhr',
            'ts' => $ts, 'hm' => [(int) date('G', $ts), (int) date('i', $ts)], 'dm' => [(int) date('j', $ts), rtrim($fmt('MMM'), '.')]];
    }

    /**
     * Abfrage der Blöcke für die Vorschau eines älteren Stands: statt rex_article_slice die Zeilen aus der Historie
     * (slice_id als id, damit Anker und Hervorhebung zu den Blöcken passen).
     */
    public static function sliceQuery(string $query): string
    {
        if (null === self::$preview || null === self::$preview['date']) {
            return $query;
        }
        $sql = Sql::factory();
        $history = array_map(static fn ($c) => $c->name, Table::get(ArticleSliceHistory::getTable())->getColumns());
        $cols = [];
        foreach (Table::get('rex_article_slice')->getColumns() as $c) {
            if ('id' !== $c->name && in_array($c->name, $history, true)) {
                $cols[] = $sql->escapeIdentifier($c->name);
            }
        }
        $derived = '(SELECT slice_id AS id, ' . implode(', ', $cols) . ' FROM ' . $sql->escapeIdentifier(ArticleSliceHistory::getTable())
            . ' WHERE slice_id > 0 AND history_date = ' . $sql->escape(self::$preview['date']) . ') AS rex_article_slice';

        return (string) preg_replace('/FROM\s+rex_article_slice\b/', 'FROM ' . $derived, $query, 1);
    }

    /** Hervorhebung: data-vdiff am äußersten Element eines Blocks */
    public static function markSlice(string $html, int $sliceId): string
    {
        if (!isset(self::$marks[$sliceId])) {
            return $html;
        }

        return (string) preg_replace('~^(\s*<[a-zA-Z][a-zA-Z0-9-]*)(?=[\s>/])~', '$1 data-vdiff="' . self::$marks[$sliceId] . '"', $html, 1);
    }

    /** Hervorhebungen für die Vorschau: Unterschied des Stands zum nächstälteren */
    public static function prepareMarks(int $articleId, int $languageId, string $key): void
    {
        $states = self::states($articleId, $languageId);
        foreach ($states as $i => $st) {
            if ($st['key'] === $key && isset($states[$i + 1])) {
                self::$marks = self::diff($st['blocks'], $st['modules'], $states[$i + 1]['blocks'], $states[$i + 1]['modules'])['marks'];
            }
        }
    }

    public static function isWorkVersion(): bool
    {
        return ArticleRevision::LIVE !== FluidAddon::revision();
    }
}

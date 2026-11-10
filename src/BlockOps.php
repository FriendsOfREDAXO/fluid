<?php

namespace FriendsOfRedaxo\Fluid;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Content\ContentHandler;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Content\ModulePermission;
use Redaxo\Core\Content\Template;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Security\User;

use function array_map;
use function array_merge;
use function array_search;
use function array_splice;
use function count;
use function in_array;
use function is_array;

/**
 * Blöcke ziehen, verdoppeln, kopieren und einfügen (wie in KLXM Studio). Ein Layout zählt mit seinen Spalten-Blöcken
 * als eine Einheit; Kopien eines Layouts nehmen die Spalten mit. Die Zwischenablage liegt im Browser (localStorage) –
 * so lässt sich ein Block auch auf einer anderen Seite einfügen. Eingefügt wird nur, was Template und Rechte erlauben.
 */
final class BlockOps
{
    /** @return list<list<int>> Einheiten in Reihenfolge: [Slice, …Spalten-Blöcke bei Layout] */
    private static function units(Article $article): array
    {
        $rows = Sql::factory()->getArray(
            'SELECT id, data FROM rex_article_slice WHERE article_id = ? AND language_id = ? AND content_section_id = 1 AND revision = ? ORDER BY priority',
            [$article->id, $article->languageId, FluidAddon::revision()],
        );
        $units = [];
        for ($i = 0, $n = count($rows); $i < $n; ++$i) {
            $id = (int) $rows[$i]['id'];
            $unit = [$id];
            if (Layout::isLayout(ArticleSlice::getArticleSliceById($id, $article->languageId, FluidAddon::revision()))) {
                while ($i + 1 < $n && (int) (BlockModule::stored($rows[$i + 1]['data'])['tunes']['col'] ?? 0) > 0) {
                    $unit[] = (int) $rows[++$i]['id'];
                }
            }
            $units[] = $unit;
        }

        return $units;
    }

    private static function renumber(Article $article, array $units): void
    {
        $prio = 0;
        $upd = Sql::factory();
        foreach (array_merge(...$units) as $id) {
            $upd->setQuery('UPDATE rex_article_slice SET priority = ? WHERE id = ?', [++$prio, $id]);
        }
        ArticleCache::delete($article->id, $article->languageId);
    }

    /**
     * Neuen Slice an genau diese Position setzen (die folgenden rücken nach). ContentHandler::addSlice() sortiert gleiche
     * Prioritäten nach dem Änderungsdatum – entstehen Blöcke in derselben Sekunde, wäre die Reihenfolge sonst zufällig
     * (z. B. ein Spalten-Block hinter einem fremden Block statt unter seinem Layout).
     */
    public static function place(ArticleSlice $slice, int $priority): void
    {
        $sql = Sql::factory();
        $where = 'article_id = ? AND language_id = ? AND content_section_id = ? AND revision = ?';
        $params = [$slice->articleId, $slice->languageId, $slice->contentSectionId, $slice->revision];
        $ids = array_map(static fn (array $r) => (int) $r['id'], $sql->getArray('SELECT id FROM rex_article_slice WHERE ' . $where . ' AND id <> ? ORDER BY priority, id', [...$params, $slice->id]));
        array_splice($ids, max(0, min(count($ids), $priority - 1)), 0, [$slice->id]);
        foreach ($ids as $i => $id) {
            $sql->setQuery('UPDATE rex_article_slice SET priority = ? WHERE id = ?', [$i + 1, $id]);
        }
    }

    /** Block (oberste Ebene) vor einen anderen ziehen; $before = 0 → ans Ende */
    public static function reorder(Article $article, int $sliceId, int $before): void
    {
        $units = self::units($article);
        $from = null;
        foreach ($units as $k => $u) {
            if ($u[0] === $sliceId) {
                $from = $k;
            }
        }
        if (null === $from) {
            throw new ApiError('Nur Abschnitte lassen sich so verschieben – Blöcke in Spalten mit den Pfeilen.');
        }
        $unit = $units[$from];
        array_splice($units, $from, 1);
        $to = count($units);
        foreach ($units as $k => $u) {
            if ($u[0] === $before) {
                $to = $k;
            }
        }
        array_splice($units, $to, 0, [$unit]);
        self::renumber($article, $units);
    }

    /** Block in die Nachbarspalte seines Layouts (← →), ans Ende dieser Spalte */
    public static function column(Article $article, int $sliceId, int $dir): void
    {
        $slice = ArticleSlice::getArticleSliceById($sliceId, $article->languageId, FluidAddon::revision()) ?? throw new ApiError('Block nicht gefunden.');
        $parent = Layout::parentOf($slice) ?? throw new ApiError('Der Block steht in keinem Layout.');
        $n = Layout::count(BlockModule::valuesOf($parent));
        $col = min($n, Layout::col($slice)) + $dir;
        if ($col < 1 || $col > $n) {
            throw new ApiError($dir < 0 ? 'Der Block steht schon in der ersten Spalte.' : 'Der Block steht schon in der letzten Spalte.');
        }
        $tunes = BlockModule::tunesOf($slice);
        $tunes['col'] = $col;
        Sql::factory()->setQuery('UPDATE rex_article_slice SET data = ? WHERE id = ?', [BlockModule::encodeStore(BlockModule::valuesOf($slice), $tunes), $slice->id]);
        // ans Ende der neuen Spalte: in der Gruppe hinter den letzten Block dieser Spalte
        $units = self::units($article);
        foreach ($units as $k => $u) {
            if ($u[0] === $parent->id) {
                $unit = array_values(array_filter($u, static fn (int $id) => $id !== $slice->id));
                $last = 0;
                foreach ($unit as $i => $id) {
                    if ($i > 0 && Layout::col(ArticleSlice::getArticleSliceById($id, $article->languageId, FluidAddon::revision())) <= $col) {
                        $last = $i;
                    }
                }
                array_splice($unit, $last + 1, 0, [$slice->id]);
                $units[$k] = $unit;
            }
        }
        self::renumber($article, $units);
    }

    /** Inhalt eines Blocks für die Zwischenablage (mit Spalten-Blöcken bei Layout): module, values, tunes, children */
    public static function payload(Article $article, int $sliceId): array
    {
        $slice = ArticleSlice::getArticleSliceById($sliceId, $article->languageId, FluidAddon::revision()) ?? throw new ApiError('Block nicht gefunden.');
        $item = static fn (ArticleSlice $s) => ['module' => $s->moduleKey, 'values' => (object) BlockModule::valuesOf($s), 'tunes' => (object) BlockModule::tunesOf($s)];
        $out = $item($slice);
        if (Layout::isLayout($slice)) {
            $out['children'] = array_map($item, Layout::children($slice));
        }
        $label = self::label($slice->moduleKey);

        return ['clip' => $out, 'label' => $label];
    }

    /** Darf der Block hier eingefügt werden? fluid-Blöcke und gewöhnliche Module des Cores – Rechte und Template */
    private static function check(User $user, string $template, string $key): void
    {
        if (!Module::exists($key)) {
            throw new ApiError('Dieser Block lässt sich hier nicht einfügen.');
        }
        $label = self::label($key);
        $perm = $user->getComplexPerm('modules');
        if (!$user->admin && (!$perm instanceof ModulePermission || !$perm->hasPerm($key))) {
            throw new ApiError('Keine Berechtigung für den Block „' . $label . '“.');
        }
        if (!Template::checkModuleAllowed($template, 1, $key)) {
            throw new ApiError('Der Block „' . $label . '“ ist im Template dieser Seite nicht erlaubt.');
        }
    }

    /** Name eines Blocks bzw. Moduls */
    public static function label(string $key): string
    {
        $m = BlockModule::classFor($key);
        if (null !== $m) {
            return $m::label();
        }
        $class = Module::getClass($key);

        return null !== $class ? $class::name() : $key;
    }

    /** Einfügen hinter einem Block (oberste Ebene; 0 = ganz oben); gibt die ID des neuen Abschnitts zurück */
    public static function insert(Article $article, User $user, int $after, array $clip): int
    {
        $template = (string) $article->templateKey;
        $check = static fn (string $key) => self::check($user, $template, $key);
        $items = [$clip];
        foreach ((array) ($clip['children'] ?? []) as $c) {
            if (is_array($c)) {
                $items[] = $c;
            }
        }
        foreach ($items as $it) {
            $check((string) ($it['module'] ?? ''));
        }
        // Position: hinter der Einheit von $after
        $units = self::units($article);
        $prio = 1;
        foreach ($units as $u) {
            if (in_array($after, $u, true)) {
                $prio = (int) Sql::factory()->getArray('SELECT MAX(priority) AS p FROM rex_article_slice WHERE id IN (' . implode(',', array_map('intval', $u)) . ')')[0]['p'] + 1;
            }
        }
        $first = 0;
        foreach ($items as $i => $it) {
            [$values, $tunes] = self::clipValues($it);
            if (0 === $i) {
                unset($tunes['col']);   // eingefügter Abschnitt steht auf oberster Ebene
            }
            $key = (string) $it['module'];
            // gewöhnliche Module: Werte wie gespeichert (ohne Abschnitt von fluid)
            $data = null !== BlockModule::classFor($key) ? BlockModule::store($values, $tunes) : $values;
            $new = ContentHandler::addSlice($article->id, $article->languageId, 1, $key, $data, $prio + $i, FluidAddon::revision());
            self::place($new, $prio + $i);
            $id = $new->id;
            if (0 === $i) {
                $first = $id;
            }
        }
        ArticleCache::delete($article->id, $article->languageId);

        return $first;
    }

    /**
     * Einfügen hinter einem Block – auch in einer Layout-Spalte: Steht $after in einer Spalte, kommt der Block direkt
     * dahinter in dieselbe Spalte (nur Blöcke, die in Spalten stehen dürfen, kein Layout). Sonst wie insert().
     */
    public static function insertAfter(Article $article, User $user, int $after, array $clip): int
    {
        $target = $after > 0 ? ArticleSlice::getArticleSliceById($after, $article->languageId, FluidAddon::revision()) : null;
        $col = null !== $target && null !== Layout::parentOf($target) ? Layout::col($target) : 0;
        if (0 === $col) {
            return self::insert($article, $user, $after, $clip);
        }
        $key = (string) ($clip['module'] ?? '');
        $m = BlockModule::classFor($key);
        if (null === $m || !empty($clip['children']) || !$m::nestable()) {
            throw new ApiError('Dieser Block lässt sich nicht in eine Spalte einfügen.');
        }
        $perm = $user->getComplexPerm('modules');
        if (!$user->admin && (!$perm instanceof ModulePermission || !$perm->hasPerm($key))) {
            throw new ApiError('Keine Berechtigung für den Block „' . $m::label() . '“.');
        }
        if (!Template::checkModuleAllowed((string) $article->templateKey, 1, $key)) {
            throw new ApiError('Der Block „' . $m::label() . '“ ist im Template dieser Seite nicht erlaubt.');
        }
        [$values, $tunes] = self::clipValues($clip);
        $tunes['col'] = $col;
        $new = ContentHandler::addSlice($article->id, $article->languageId, $target->contentSectionId, $key, BlockModule::store($values, $tunes), $target->priority + 1, FluidAddon::revision());
        self::place($new, $target->priority + 1);
        ArticleCache::delete($article->id, $article->languageId);

        return $new->id;
    }

    /**
     * Ausschneiden abschließen: den Quell-Block (bei Layout samt Spalten) löschen, nachdem die Kopie eingefügt ist.
     * Nur mit Recht auf seine Seite; fehlt er schon, passiert nichts.
     *
     * @param array{article?: int, language?: int, slice?: int} $cut
     */
    public static function finishCut(User $user, array $cut, int $inserted): void
    {
        $id = (int) ($cut['slice'] ?? 0);
        $source = $id > 0 ? ArticleSlice::getArticleSliceById($id, (int) ($cut['language'] ?? 0), FluidAddon::revision()) : null;
        if (null === $source || $source->id === $inserted) {
            return;
        }
        $article = Article::get($source->articleId, $source->languageId);
        $perm = $user->getComplexPerm('structure');
        if (null === $article || (!$user->admin && !($perm instanceof \Redaxo\Core\Content\StructurePermission && $perm->hasCategoryPerm($article->categoryId)))) {
            throw new ApiError('Eingefügt – das Original lässt sich ohne Recht auf seine Seite nicht entfernen.');
        }
        ContentHandler::deleteSlice($source->id);   // Spalten-Blöcke eines Layouts: FluidAddon (SliceDeleting)
        ArticleCache::delete($article->id, $article->languageId);
    }

    /**
     * Werte eines Eintrags der Zwischenablage; ältere Einträge (vor REDAXO-Core 9132fc1) tragen value1/value2 als JSON.
     *
     * @param array<array-key, mixed> $it
     * @return array{array<string, mixed>, array<string, mixed>}
     */
    public static function clipValues(array $it): array
    {
        // aus payload() direkt (Verdoppeln) stehen Objekte darin, aus dem Browser (JSON) Arrays
        $array = static fn (mixed $v): array => \is_object($v) ? (array) json_decode((string) json_encode($v), true) : (is_array($v) ? $v : []);
        $values = $it['values'] ?? BlockModule::decode(\is_string($it['value1'] ?? null) ? $it['value1'] : null);
        $tunes = $it['tunes'] ?? BlockModule::decode(\is_string($it['value2'] ?? null) ? $it['value2'] : null);

        return [$array($values), $array($tunes)];
    }
}

<?php

namespace FriendsOfRedaxo\Fluid\Routing;

use FriendsOfRedaxo\Fluid\Routing\Attribute\Description;
use FriendsOfRedaxo\Fluid\Routing\Attribute\FromTable;
use FriendsOfRedaxo\Fluid\Routing\Attribute\Image;
use FriendsOfRedaxo\Fluid\Routing\Attribute\MetaColumn;
use FriendsOfRedaxo\Fluid\Routing\Attribute\Modified;
use FriendsOfRedaxo\Fluid\Routing\Attribute\Slug;
use FriendsOfRedaxo\Fluid\Routing\Attribute\Title;
use FriendsOfRedaxo\Fluid\Routing\Seo\PageMeta;
use LogicException;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Language\Language;
use ReflectionClass;

use function array_filter;
use function array_key_exists;
use function array_map;
use function explode;
use function html_entity_decode;
use function is_numeric;
use function mb_strimwidth;
use function preg_replace;
use function sprintf;
use function strip_tags;
use function strtotime;
use function trim;

/**
 * Datensätze einer Tabelle als Adressen – nur mit Attributen, ohne Code:
 *
 *     #[AsRouteSource('news', label: 'News')]
 *     #[FromTable('rex_news', page: 12, where: 'status = 1', language: 'clang_id', param: 'news_id')]
 *     #[Slug('title', withId: true)]            /aktuelles/sommerfest-12
 *     #[Title('seo_title')]                     Titel (sonst die Slug-Spalte)
 *     #[Description('teaser')]                  ohne HTML, gekürzt auf 300 Zeichen
 *     #[Image('image')]                         Datei(en) des Medienpools – die erste zählt
 *     #[Modified('updatedate')]                 Zeitstempel oder Datum (Sitemap, article:modified_time)
 *     final class NewsRoutes extends TableSource {}
 *
 * Die Seite bekommt die ID als Parameter (news_id) und gibt den Eintrag aus. Spalten mit editable: true (Standard) kann
 * ein Editor anbieten. Für Besonderes Methoden überschreiben (rows(), slug(), meta() …).
 */
abstract class TableSource extends RouteSource
{
    private ?FromTable $table = null;

    /** @var array<string, MetaColumn>|null Rolle => Spalte */
    private ?array $columns = null;

    /** @var array<string, array<string, mixed>|null> */
    private array $rowCache = [];

    public function table(): FromTable
    {
        $this->init();

        return $this->table;
    }

    /** @return array<string, MetaColumn> Rolle (slug, title, description, image, modified) => Spalte */
    public function columns(): array
    {
        $this->init();

        return $this->columns;
    }

    public function entries(): iterable
    {
        foreach ($this->rows() as $row) {
            $id = (string) $row[$this->table()->id];
            $langs = null !== $this->table()->language ? [(int) $row[$this->table()->language]] : Language::getAllIds();
            foreach ($langs as $lang) {
                yield new RouteEntry(
                    $this->key(), $id, $lang, $this->page($row, $lang), $this->slug($row),
                    params: [$this->table()->param => $id],
                    modified: $this->modified($row),
                    images: $this->images($row),
                );
            }
        }
    }

    public function exists(RouteEntry $entry): bool
    {
        return null !== $this->row($entry);
    }

    public function meta(RouteEntry $entry): ?PageMeta
    {
        $row = $this->row($entry);
        if (null === $row) {
            return null;
        }
        $c = $this->columns();
        $text = static fn (?MetaColumn $col): ?string => null !== $col && '' !== ($v = trim((string) ($row[$col->column] ?? ''))) ? $v : null;
        $desc = $text($c['description'] ?? null);

        return new PageMeta(
            title: $text($c['title'] ?? null) ?? $text($c['slug'] ?? null),
            description: null !== $desc ? mb_strimwidth(trim((string) preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags($desc)))), 0, 300, '…') : null,
            image: $this->images($row)[0] ?? null,
            type: 'article',
            modified: $this->modified($row),
        );
    }

    public function editable(RouteEntry $entry): array
    {
        $row = $this->row($entry);
        if (null === $row) {
            return [];
        }
        $out = [];
        foreach ($this->columns() as $role => $col) {
            if ($col->editable && 'none' !== $col->kind()) {
                $out[] = new EditableField($role, $col->column, $col->label(), $col->kind(), (string) ($row[$col->column] ?? ''));
            }
        }

        return $out;
    }

    public function save(RouteEntry $entry, array $values): void
    {
        $set = [];
        foreach ($this->editable($entry) as $f) {
            if (array_key_exists($f->name, $values)) {
                $set[$f->name] = trim($values[$f->name]);
            }
        }
        if ($set) {
            $sql = Sql::factory()->setTable($this->table()->table)->setValues($set)->setWhere([$this->table()->id => $entry->id]);
            $sql->update();
        }
        unset($this->rowCache[$entry->id . '|' . $entry->languageId]);
        Routes::rebuildSource($this->key());
    }

    /**
     * Sichtbare Zeilen der Tabelle.
     *
     * @return list<array<string, mixed>>
     */
    protected function rows(): array
    {
        $t = $this->table();

        return Sql::factory()->getArray('SELECT * FROM ' . Sql::factory()->escapeIdentifier($t->table) . ('' !== $t->where ? ' WHERE ' . $t->where : ''));
    }

    /** Seite, die den Eintrag zeigt (z. B. je Kategorie eine andere) */
    protected function page(array $row, int $languageId): int
    {
        return $this->table()->page;
    }

    protected function slug(array $row): string
    {
        $slug = $this->columns()['slug'] ?? null;
        $text = null !== $slug ? trim((string) ($row[$slug->column] ?? '')) : '';
        $id = (string) $row[$this->table()->id];
        if ('' === $text) {
            return $id;
        }

        return $slug instanceof Slug && $slug->withId ? Routes::slugify($text) . '-' . $id : $text;
    }

    protected function modified(array $row): ?int
    {
        $col = $this->columns()['modified'] ?? null;
        $v = null !== $col ? ($row[$col->column] ?? null) : null;
        if (null === $v || '' === $v) {
            return null;
        }

        return is_numeric($v) ? (int) $v : (strtotime((string) $v) ?: null);
    }

    /** @return list<string> */
    protected function images(array $row): array
    {
        $col = $this->columns()['image'] ?? null;

        return null !== $col ? array_values(array_filter(array_map('trim', explode(',', (string) ($row[$col->column] ?? ''))))) : [];
    }

    /** Zeile eines Eintrags, wenn er sichtbar ist */
    protected function row(RouteEntry $entry): ?array
    {
        $key = $entry->id . '|' . $entry->languageId;
        if (!array_key_exists($key, $this->rowCache)) {
            $t = $this->table();
            $sql = Sql::factory();
            $where = $sql->escapeIdentifier($t->id) . ' = ?' . (null !== $t->language ? ' AND ' . $sql->escapeIdentifier($t->language) . ' = ?' : '') . ('' !== $t->where ? ' AND (' . $t->where . ')' : '');
            $params = null !== $t->language ? [$entry->id, $entry->languageId] : [$entry->id];
            $this->rowCache[$key] = $sql->getArray('SELECT * FROM ' . $sql->escapeIdentifier($t->table) . ' WHERE ' . $where . ' LIMIT 1', $params)[0] ?? null;
        }

        return $this->rowCache[$key];
    }

    private function init(): void
    {
        if (null !== $this->table) {
            return;
        }
        $ref = new ReflectionClass($this);
        $t = $ref->getAttributes(FromTable::class)[0] ?? null;
        if (null === $t) {
            throw new LogicException(sprintf('%s braucht #[FromTable(…)].', static::class));
        }
        $this->table = $t->newInstance();
        $this->columns = [];
        foreach ([Slug::class, Title::class, Description::class, Image::class, Modified::class] as $class) {
            foreach ($ref->getAttributes($class) as $a) {
                $col = $a->newInstance();
                $this->columns[$col->role()] = $col;
            }
        }
    }
}

<?php

namespace FriendsOfRedaxo\Fluid\Navigation;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\Category;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function in_array;
use function is_array;
use function json_encode;
use function max;
use function rtrim;

use const JSON_HEX_TAG;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Navigation als Array – die API von navigation_array (REDAXO 5, FriendsOfRedaxo\NavigationArray\BuildArray) für REDAXO 6.
 *
 * Eigenständig gebaut (nur Core-Klassen), damit es später ein eigenes AddOn werden kann.
 *
 *     $nav = NavigationArray::create()->setDepth(3)->setExcludedCategories([12]);
 *     foreach ($nav->generate() as $item) { … $item['catName'], $item['url'], $item['active'], $item['children'] … }
 *     $nav->walk(fn (array $item, int $level) => …);
 *     $crumbs = $nav->getBreadcrumb();
 *
 * Ein Eintrag: catId, parentId, level (0 = erste Ebene), catName, url, hasChildren, children, path (IDs der Eltern),
 * active (im Pfad der aktuellen Kategorie), current (die aktuelle Kategorie); mit setIncludeArticles() zusätzlich
 * articles (Artikel der Kategorie ohne Startartikel). Offline-Kategorien und solche ohne Recht (isPermitted(), z. B.
 * über einen Extension Point eines Login-AddOns) fehlen. Extension Point NAVIGATION_ARRAY_GENERATE_ITEM: Eintrag
 * ergänzen (Subject: der Eintrag, Parameter category).
 */
final class NavigationArray
{
    /** @var int|list<int> */
    private int|array $start = 0;
    private int $depth = 2;
    private bool $ignoreOffline = true;
    /** @var list<int> */
    private array $excluded = [];
    /** @var (callable(Category): bool)|null */
    private $filter;
    /** @var (callable(Category): array<string, mixed>)|null */
    private $customData;
    private bool $articles = false;
    private ?int $languageId = null;

    /** @param int|list<int> $start */
    public function __construct(int|array $start = 0, int $depth = 2, bool $ignoreOffline = true)
    {
        $this->start = $start;
        $this->depth = $depth;
        $this->ignoreOffline = $ignoreOffline;
    }

    public static function create(): self
    {
        return new self();
    }

    /**
     * Startkategorie: 0 oder -1 = oberste Ebene, eine ID = deren Unterkategorien, mehrere IDs = genau diese Kategorien.
     *
     * @param int|list<int> $start
     */
    public function setStart(int|array $start): self
    {
        $this->start = is_array($start) ? array_map('intval', $start) : $start;

        return $this;
    }

    /** Anzahl der Ebenen (1 = nur die erste) */
    public function setDepth(int $depth): self
    {
        $this->depth = max(1, $depth);

        return $this;
    }

    /** true (Standard): Offline-Kategorien und -Artikel weglassen */
    public function setIgnore(bool $ignoreOffline): self
    {
        $this->ignoreOffline = $ignoreOffline;

        return $this;
    }

    /** @param list<int> $ids Kategorien (samt Unterkategorien) weglassen */
    public function setExcludedCategories(array $ids): self
    {
        $this->excluded = array_map('intval', $ids);

        return $this;
    }

    /** @param callable(Category): bool $callback false = Kategorie (samt Unterkategorien) weglassen */
    public function setCategoryFilterCallback(callable $callback): self
    {
        $this->filter = $callback;

        return $this;
    }

    /** @param callable(Category): array<string, mixed> $callback zusätzliche Schlüssel je Eintrag */
    public function setCustomDataCallback(callable $callback): self
    {
        $this->customData = $callback;

        return $this;
    }

    /** Artikel jeder Kategorie (ohne Startartikel) als Schlüssel articles mitliefern */
    public function setIncludeArticles(bool $include = true): self
    {
        $this->articles = $include;

        return $this;
    }

    public function setLanguage(?int $languageId): self
    {
        $this->languageId = $languageId;

        return $this;
    }

    /** @return list<array<string, mixed>> */
    public function generate(): array
    {
        $current = Category::getCurrent($this->languageId);
        $path = $current ? [...$current->path, $current->id] : [];

        return $this->build($this->roots(), 0, $current, $path);
    }

    /**
     * Alle Einträge der Reihe nach (Tiefe zuerst) an den Callback geben.
     *
     * @param callable(array<string, mixed>, int): void $callback Eintrag und Ebene
     */
    public function walk(callable $callback): void
    {
        $go = static function (array $items) use (&$go, $callback): void {
            foreach ($items as $item) {
                $callback($item, (int) $item['level']);
                $go($item['children']);
            }
        };
        $go($this->generate());
    }

    /**
     * Eintrag einer Kategorie (mit Unterkategorien bis zur eingestellten Tiefe) – auch außerhalb der Startkategorie.
     *
     * @return array<string, mixed>|null
     */
    public function getCategory(int $id): ?array
    {
        $cat = Category::get($id, $this->languageId);
        if (!$cat || !$this->allowed($cat)) {
            return null;
        }
        $current = Category::getCurrent($this->languageId);
        $path = $current ? [...$current->path, $current->id] : [];

        return $this->build([$cat], 0, $current, $path)[0] ?? null;
    }

    /**
     * Pfad von der obersten Ebene bis zur aktuellen Kategorie, auf Wunsch mit dem aktuellen Artikel und eigenen Einträgen
     * (z. B. Datensatz einer Detailseite).
     *
     * @param list<array{name: string, url: string}> $append
     * @return list<array{name: string, url: string, current: bool}>
     */
    public function getBreadcrumb(bool $withArticle = true, array $append = []): array
    {
        $out = [];
        $current = Category::getCurrent($this->languageId);
        $tree = $current ? $current->getParentTree() : [];
        if ($current && !in_array($current, $tree, true)) {
            $tree[] = $current;   // getParentTree() liefert je nach Element nur die Eltern
        }
        foreach ($tree as $cat) {
            if ($this->allowed($cat)) {
                $out[] = ['name' => $cat->name, 'url' => $cat->getUrl(), 'current' => false];
            }
        }
        $article = Article::getCurrent($this->languageId);
        if ($withArticle && $article && !$article->isStartArticle() && !$article->isSiteStartArticle()) {
            $out[] = ['name' => $article->name, 'url' => $article->getUrl(), 'current' => false];
        }
        foreach ($append as $a) {
            $out[] = ['name' => $a['name'], 'url' => $a['url'], 'current' => false];
        }
        if ($out) {
            $out[count($out) - 1]['current'] = true;
        }

        return $out;
    }

    /**
     * Brotkrumen als schema.org BreadcrumbList (script-Tag); $baseUrl macht relative Adressen absolut.
     *
     * @param list<array{name: string, url: string}> $append
     */
    public function toJsonLd(string $baseUrl = '', bool $withArticle = true, array $append = []): string
    {
        $items = [];
        foreach ($this->getBreadcrumb($withArticle, $append) as $i => $c) {
            $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c['name'], 'item' => rtrim($baseUrl, '/') . $c['url']];
        }
        if (!$items) {
            return '';
        }

        return '<script type="application/ld+json">' . json_encode(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>';
    }

    /** @return list<Category> */
    private function roots(): array
    {
        if (is_array($this->start)) {
            return array_values(array_filter(array_map(fn (int $id) => Category::get($id, $this->languageId), $this->start)));
        }
        if ($this->start > 0) {
            return Category::get($this->start, $this->languageId)?->getChildren($this->ignoreOffline) ?? [];
        }

        return Category::getRootCategories($this->ignoreOffline, $this->languageId);
    }

    private function allowed(Category $cat): bool
    {
        return !in_array($cat->id, $this->excluded, true)
            && (!$this->ignoreOffline || $cat->isOnline())
            && $cat->isPermitted()
            && (null === $this->filter || ($this->filter)($cat));
    }

    /**
     * @param list<Category> $cats
     * @param list<int> $path
     * @return list<array<string, mixed>>
     */
    private function build(array $cats, int $level, ?Category $current, array $path): array
    {
        $out = [];
        foreach ($cats as $cat) {
            if (!$this->allowed($cat)) {
                continue;
            }
            $children = $level + 1 < $this->depth ? $this->build($cat->getChildren($this->ignoreOffline), $level + 1, $current, $path) : [];
            $item = [
                'catId' => $cat->id,
                'parentId' => $cat->parentId ?? 0,
                'level' => $level,
                'catName' => $cat->name,
                'url' => $cat->getUrl(),
                'hasChildren' => [] !== $children,
                'children' => $children,
                'path' => $cat->path,
                'active' => in_array($cat->id, $path, true),
                'current' => null !== $current && $current->id === $cat->id,
            ];
            if ($this->articles) {
                $currentArticle = Article::getCurrentId();
                $item['articles'] = array_values(array_map(static fn (Article $a) => [
                    'id' => $a->id, 'name' => $a->name, 'url' => $a->getUrl(), 'current' => $a->id === $currentArticle,
                ], array_filter($cat->getArticles($this->ignoreOffline), static fn (Article $a) => !$a->isStartArticle() && $a->isPermitted())));
            }
            if (null !== $this->customData) {
                $item = [...$item, ...($this->customData)($cat)];
            }
            $item = (array) Extension::dispatch(new ExtensionPoint('NAVIGATION_ARRAY_GENERATE_ITEM', $item, ['category' => $cat]));
            $out[] = $item;
        }

        return $out;
    }
}

<?php

namespace FriendsOfRedaxo\Fluid;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleHandler;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Content\CategoryHandler;
use Redaxo\Core\Content\StructurePermission;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Security\User;
use Throwable;

use function array_map;
use function count;
use function in_array;

/**
 * Seitenleiste „Seiten“ auf der Website (Modell aus KLXM Studio – Seitenbaum): alle Seiten als Baum, sortieren und
 * verschieben per Ziehen, online/offline, „im Menü“ (REDAXO: Kategorie ↔ Artikel), löschen, neue Seite an einer Stelle.
 *
 * Menüpunkte (Kategorien) stehen je Ebene vor den Seiten ohne Menüeintrag (Artikel) – beide Gruppen haben in REDAXO
 * eigene Reihenfolgen. Rechte: Strukturrecht der jeweiligen Kategorie (Admins dürfen alles).
 */
final class Structure
{
    /**
     * Menüpunkt „nicht im Menü“: Metafeld „Nicht in der Navigation zeigen“ von navigation_array (Spalte
     * meta_nav_hidden der Kategorie, für alle Sprachen) – Stelle, Unterseiten und Status bleiben, die Navigation lässt
     * die Kategorie weg. Im Backend unter Kategorie → Metadaten.
     */
    public static function isHidden(int $categoryId): bool
    {
        $cat = Category::get($categoryId);

        return null !== $cat && \FriendsOfRedaxo\NavigationArray\NavigationMeta::of($cat)->navHidden;
    }

    private static function setHidden(int $categoryId, int $languageId, bool $hidden): void
    {
        CategoryHandler::edit($categoryId, $languageId, [], ['nav_hidden' => $hidden]);
    }

    /** @return list<array<string, mixed>> */
    public static function tree(int $languageId): array
    {
        $home = Article::getSiteStartArticleId();
        $types = ArticleHandler::statusTypes();   // 0 offline, 1 online, weitere von AddOns (z. B. gesperrt)
        $node = static function (Article $a, bool $menu, array $children = []) use ($home, $types): array {
            $status = (int) $a->getValue('status');

            return ['id' => $a->id, 'name' => $a->name, 'url' => $a->getUrl(), 'online' => $a->isOnline(), 'menu' => $menu,
                'status' => $status, 'statusLabel' => (string) ($types[$status][0] ?? ''),
                'hidden' => $menu && self::isHidden($a->id),
                'home' => $a->id === $home, 'blog' => $menu && Blog\Blog::isBlog($a->id), 'parent' => $a->categoryId && !$menu ? $a->categoryId : ($menu ? (Category::get($a->id)->parentId ?? 0) : 0),
                'children' => $children];
        };
        $walk = static function (?Category $cat) use (&$walk, $node, $languageId): array {
            $out = [];
            $cats = $cat ? $cat->getChildren(false) : Category::getRootCategories(false, $languageId);
            foreach ($cats as $c) {
                if (PageTemplates::categoryId() === $c->id) {
                    continue;   // Seitenvorlagen: eigene Liste im Dialog „Neue Seite“
                }
                $start = Article::get($c->id, $languageId);
                if ($start) {
                    $out[] = $node($start, true, $walk($c));
                }
            }
            $arts = $cat ? $cat->getArticles(false) : Article::getRootArticles(false, $languageId);
            foreach ($arts as $a) {
                if (!$a->isStartArticle()) {
                    $out[] = $node($a, false);
                }
            }

            return $out;
        };

        return $walk(null);
    }

    public static function handle(string $action, User $user, int $languageId): array
    {
        $id = Request::post('id', 'int', 0);
        $msg = '';
        if ('tree' !== $action) {
            $a = Article::get($id, $languageId) ?? throw new ApiError('Seite nicht gefunden.');
            self::requirePerm($user, $a->isStartArticle() && $a->categoryId === $a->id ? (Category::get($a->id)?->parentId) : $a->categoryId);
            $isCat = null !== Category::get($a->id) && $a->isStartArticle();
            Perm::require($user, match ($action) {
                'tree_move' => Perm::forPage($a, 'move'),
                'tree_status' => Perm::forPage($a, 'publish'),
                'tree_menu' => 'article2category[]',
                default => Perm::forPage($a, 'delete'),
            });
            switch ($action) {
                case 'tree_move':
                    $msg = self::move($a, $isCat, Request::post('parent', 'int', 0) ?: null, max(0, Request::post('index', 'int', 0)), $user, $languageId);
                    break;
                case 'tree_status':
                    $on = !$a->isOnline();
                    $isCat ? CategoryHandler::changeStatus($a->id, $languageId, $on ? 1 : 0) : ArticleHandler::changeStatus($a->id, $languageId, $on ? 1 : 0);
                    $msg = '„' . $a->name . '“ ist jetzt ' . ($on ? 'online.' : 'offline.');
                    break;
                case 'tree_menu':
                    if ($isCat) {
                        // Menüpunkt: nur aus dem Menü ausblenden bzw. wieder zeigen – Stelle, Unterseiten und Status bleiben
                        $hide = !self::isHidden($a->id);
                        self::setHidden($a->id, $languageId, $hide);
                        $msg = $hide ? '„' . $a->name . '“ erscheint nicht mehr im Menü – die Seite bleibt online und an ihrer Stelle.' : '„' . $a->name . '“ steht wieder im Menü.';
                    } else {
                        // Seite ohne Menüeintrag: Menüpunkte sind in REDAXO Kategorien
                        ArticleHandler::convertToCategory($a->id);
                        $msg = '„' . $a->name . '“ steht jetzt im Menü.';
                    }
                    break;
                case 'tree_delete':
                    if ($a->id === Article::getSiteStartArticleId() || $a->id === Article::getNotfoundArticleId()) {
                        throw new ApiError('Startseite und Seite „nicht gefunden“ lassen sich nicht löschen.');
                    }
                    $isCat ? CategoryHandler::delete($a->id) : ArticleHandler::delete($a->id);
                    $msg = '„' . $a->name . '“ gelöscht.';
                    break;
            }
            \FriendsOfRedaxo\Fluid\Routing\Routes::rebuild();
        }

        return ['tree' => self::tree($languageId), 'message' => $msg];
    }

    /** Ziehen: neue Elternseite (null = oberste Ebene) und Position innerhalb der eigenen Gruppe (Menüpunkte bzw. Seiten) */
    private static function move(Article $a, bool $isCat, ?int $parent, int $index, User $user, int $languageId): string
    {
        if (null !== $parent && !Category::get($parent)) {
            throw new ApiError('Hier lassen sich keine Unterseiten anlegen – die Zielseite steht nicht im Menü.');
        }
        self::requirePerm($user, $parent);
        if ($isCat) {
            $cat = Category::get($a->id) ?? throw new ApiError('Seite nicht gefunden.');
            if (null !== $parent && ($parent === $a->id || in_array($a->id, Category::get($parent)->path ?? [], true))) {
                throw new ApiError('Eine Seite kann nicht unter sich selbst stehen.');
            }
            if ($parent !== $cat->parentId) {
                CategoryHandler::move($a->id, $parent);   // wirft bei Fehlern
            }
            CategoryHandler::edit($a->id, $languageId, ['priority' => $index + 1]);
        } else {
            if ($parent !== $a->categoryId) {
                ArticleHandler::move($a->id, $a->categoryId, $parent);
            }
            $fresh = Article::get($a->id, $languageId) ?? $a;
            ArticleHandler::edit($a->id, $languageId, ['name' => $fresh->name, 'priority' => $index + 1, 'template' => $fresh->templateKey]);
        }

        return '„' . $a->name . '“ verschoben.';
    }

    private static function requirePerm(User $user, ?int $categoryId): void
    {
        if ($user->admin) {
            return;
        }
        $perm = $user->getComplexPerm('structure');
        if (!$perm instanceof StructurePermission || !$perm->hasCategoryPerm($categoryId)) {
            throw new ApiError('Keine Berechtigung für diesen Bereich der Seitenstruktur.');
        }
    }
}

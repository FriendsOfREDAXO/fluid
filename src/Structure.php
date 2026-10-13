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
    /** @return list<array<string, mixed>> */
    public static function tree(int $languageId): array
    {
        $home = Article::getSiteStartArticleId();
        $node = static function (Article $a, bool $menu, array $children = []) use ($home): array {
            return ['id' => $a->id, 'name' => $a->name, 'url' => $a->getUrl(), 'online' => $a->isOnline(), 'menu' => $menu,
                'home' => $a->id === $home, 'parent' => $a->categoryId && !$menu ? $a->categoryId : ($menu ? (Category::get($a->id)?->parentId ?? 0) : 0),
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
                    $isCat ? CategoryHandler::categoryStatus($a->id, $languageId, $on ? 1 : 0) : ArticleHandler::articleStatus($a->id, $languageId, $on ? 1 : 0);
                    $msg = '„' . $a->name . '“ ist jetzt ' . ($on ? 'online.' : 'offline.');
                    break;
                case 'tree_menu':
                    if ($isCat) {
                        if (count(Category::get($a->id)?->getChildren() ?? []) || count(array_filter(Category::get($a->id)?->getArticles() ?? [], static fn (Article $x) => !$x->isStartArticle()))) {
                            throw new ApiError('„' . $a->name . '“ hat Unterseiten – erst diese verschieben, dann aus dem Menü nehmen.');
                        }
                        ArticleHandler::category2article($a->id) || throw new ApiError('Konnte nicht aus dem Menü genommen werden.');
                        $msg = '„' . $a->name . '“ steht nicht mehr im Menü.';
                    } else {
                        ArticleHandler::article2category($a->id) || throw new ApiError('Konnte nicht ins Menü aufgenommen werden.');
                        $msg = '„' . $a->name . '“ steht jetzt im Menü.';
                    }
                    break;
                case 'tree_delete':
                    if ($a->id === Article::getSiteStartArticleId() || $a->id === Article::getNotfoundArticleId()) {
                        throw new ApiError('Startseite und Seite „nicht gefunden“ lassen sich nicht löschen.');
                    }
                    $isCat ? CategoryHandler::deleteCategory($a->id) : ArticleHandler::deleteArticle($a->id);
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
            if (null !== $parent && ($parent === $a->id || in_array($a->id, Category::get($parent)?->path ?? [], true))) {
                throw new ApiError('Eine Seite kann nicht unter sich selbst stehen.');
            }
            if ($parent !== $cat->parentId) {
                CategoryHandler::moveCategory($a->id, $parent) || throw new ApiError('Verschieben ist nicht möglich.');
            }
            CategoryHandler::editCategory($a->id, $languageId, ['catpriority' => $index + 1]);
        } else {
            if ($parent !== $a->categoryId) {
                ArticleHandler::moveArticle($a->id, $a->categoryId, $parent) || throw new ApiError('Verschieben ist nicht möglich.');
            }
            $fresh = Article::get($a->id, $languageId) ?? $a;
            ArticleHandler::editArticle($a->id, $languageId, ['name' => $fresh->name, 'priority' => $index + 1, 'template' => $fresh->templateKey]);
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

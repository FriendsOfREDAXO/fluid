<?php

namespace FriendsOfRedaxo\Fluid\Picker;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Content\StructurePermission;
use Redaxo\Core\Security\User;

/**
 * Seitenauswahl auf der Website (FrontendPicker): Kategorien und Artikel der Struktur, wie die Linkmap des Cores.
 * Ohne das Recht `linkmap[all_categories]` sieht die Person nur ihre Mountpoints (ab dort als oberste Ebene).
 */
final class ArticleTree
{
    /**
     * Seiten in der Reihenfolge der Struktur. `parent`: Kategorie, in der die Seite steht (0 = oberste Ebene);
     * `cat`: Startartikel einer Kategorie – sie trägt die Unterkategorien und Artikel.
     *
     * @return list<array{id: int, name: string, path: string, online: bool, parent: int, cat: bool}>
     */
    public static function all(User $user, int $languageId): array
    {
        $perm = $user->getComplexPerm('structure');
        $mountpoints = !$user->hasPerm('linkmap[all_categories]') && $perm instanceof StructurePermission ? $perm->getMountpointCategories() : [];

        $out = [];
        $add = static function (Article $a, string $path, int $parent, bool $cat) use (&$out): void {
            $out[] = ['id' => $a->id, 'name' => $a->name, 'path' => $path, 'online' => $a->isOnline(), 'parent' => $parent, 'cat' => $cat];
        };
        $walk = static function (array $cats, string $path, int $parent) use (&$walk, $add): void {
            foreach ($cats as $c) {
                /** @var Category $c */
                $p = '' === $path ? $c->name : $path . ' › ' . $c->name;
                foreach ($c->getArticles() as $a) {
                    $a->isStartArticle() ? $add($a, $p, $parent, true) : $add($a, $p . ' › ' . $a->name, $c->id, false);
                }
                $walk($c->getChildren(), $p, $c->id);
            }
        };

        if ($mountpoints) {
            $walk(array_values(array_filter(array_map(static fn (Category $c) => Category::get($c->id, $languageId), $mountpoints))), '', 0);

            return $out;
        }
        foreach (Article::getRootArticles(false, $languageId) as $a) {
            $add($a, 'Hauptebene', 0, false);
        }
        $walk(Category::getRootCategories(false, $languageId), '', 0);

        return $out;
    }
}

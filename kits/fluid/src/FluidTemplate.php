<?php

namespace FriendsOfRedaxo\Blocks\Kit\Fluid;

use FriendsOfRedaxo\Blocks\BlockModule;
use Override;
use Redaxo\Core\Content\ArticleContent;
use Redaxo\Core\Content\AsTemplate;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Content\ContentSection;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Content\Template;
use Redaxo\Core\View\HasView;

/**
 * Seitenrahmen des Kits „fluid“: Kopf mit Menü aus der Struktur, Inhalt (Blöcke), Fuß.
 * Markup und Klassen wie im Studio-Kit, damit dessen CSS unverändert greift.
 */
#[AsTemplate('fluid', 'Fluid')]
final class FluidTemplate extends Template
{
    use HasView;

    #[Override]
    public function render(ArticleContent $content): string
    {
        FluidKit::instance()->definition();   // lädt functions.php des Kits

        return $this->renderView($content);
    }

    #[Override]
    public function isModuleAllowed(ContentSection $section, Module $module): bool
    {
        return $module instanceof BlockModule && $module->kit() instanceof FluidKit;
    }

    /**
     * Menü aus der Struktur (Online-Kategorien, zwei Ebenen) im Format der fluid-Navigation.
     *
     * @return list<array{label: string, href: string, current: bool, active: bool, children: list<array<string, mixed>>}>
     */
    public static function menu(int $depth = 2): array
    {
        $current = Category::getCurrent();
        $path = $current ? [...$current->path, $current->id] : [];
        $build = static function (array $cats, int $level) use (&$build, $current, $path, $depth): array {
            $out = [];
            foreach ($cats as $c) {
                $out[] = [
                    'label' => $c->name,
                    'href' => $c->getUrl(),
                    'current' => $current && $current->id === $c->id,
                    'active' => in_array($c->id, $path, true),
                    'children' => $level < $depth ? $build($c->getChildren(true), $level + 1) : [],
                ];
            }

            return $out;
        };

        return $build(Category::getRootCategories(true), 1);
    }
}

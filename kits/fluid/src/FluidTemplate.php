<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid;

use FriendsOfRedaxo\Fluid\BlockModule;
use FriendsOfRedaxo\Fluid\Kit;
use Override;
use Redaxo\Core\Content\ArticleContent;
use Redaxo\Core\Content\AsTemplate;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Content\ContentSection;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Content\Template;
use Redaxo\Core\View\HasView;
use Redaxo\Core\View\Renderer;

/**
 * Seitenrahmen von fluid: Kopf mit Menü aus der Struktur, Inhalt (Blöcke), Fuß – gestaltet vom gewählten Kit.
 * Markup und Klassen wie im Studio-Kit, damit dessen CSS unverändert greift. Ein Kit kann den Rahmen mit eigener
 * `template.view.php` ersetzen (gleiche Signatur wie FluidTemplate.view.php).
 */
#[AsTemplate('fluid', 'Fluid')]
final class FluidTemplate extends Template
{
    use HasView;

    #[Override]
    public function render(ArticleContent $content): string
    {
        $kit = Kit::active();
        $kit->definition();   // lädt functions.php des Kits (und seiner Vorfahren)
        if (null !== $own = $kit->file('template.view.php')) {
            return Renderer::render($own, [$content]);
        }

        return $this->renderView($content);
    }

    #[Override]
    public function isModuleAllowed(ContentSection $section, Module $module): bool
    {
        return $module instanceof BlockModule && $module->kit() === Kit::active();
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

        // Landing-Domain: Menü auf die Landingpage begrenzt (Unterseiten der Einstiegsseite, sonst keins)
        if ($landing = \FriendsOfRedaxo\Fluid\Routing\Router::landing()) {
            $cat = $landing->subtree ? Category::get((int) $landing->articleId) : null;

            return $cat ? $build($cat->getChildren(true), 1) : [];
        }

        return $build(Category::getRootCategories(true), 1);
    }
}

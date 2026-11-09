<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid;

use FriendsOfRedaxo\Fluid\BlockModule;
use FriendsOfRedaxo\Fluid\Kit;
use Override;
use Redaxo\Core\Content\ArticleContent;
use Redaxo\Core\Content\AsTemplate;
use FriendsOfRedaxo\Fluid\Navigation\NavigationArray;
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
    public function isModuleAllowed(ContentSection $section, string $module): bool
    {
        return is_a($module, BlockModule::class, true) && $module::kit() === Kit::active();
    }

    /**
     * Menü aus der Struktur (Online-Kategorien, zwei Ebenen) im Format der fluid-Navigation.
     *
     * @return list<array{label: string, href: string, current: bool, active: bool, children: list<array<string, mixed>>}>
     */
    public static function menu(int $depth = 2): array
    {
        $nav = NavigationArray::create()->setDepth($depth);
        // Landing-Domain: Menü auf die Landingpage begrenzt (Unterseiten der Einstiegsseite, sonst keins)
        if ($landing = \FriendsOfRedaxo\Fluid\Routing\Router::landing()) {
            if (!$landing->subtree || !Category::get((int) $landing->articleId)) {
                return [];
            }
            $nav->setStart((int) $landing->articleId);
        }
        $map = static function (array $items) use (&$map): array {
            return array_map(static fn (array $i) => [
                'label' => $i['catName'], 'href' => $i['url'], 'current' => $i['current'], 'active' => $i['active'], 'children' => $map($i['children']),
            ], $items);
        };

        return $map($nav->generate());
    }
}

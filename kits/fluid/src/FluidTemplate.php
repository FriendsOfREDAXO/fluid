<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid;

use FriendsOfRedaxo\Fluid\BlockModule;
use FriendsOfRedaxo\Fluid\Kit;
use FriendsOfRedaxo\NavigationArray\Navigation;
use FriendsOfRedaxo\NavigationArray\NavigationItem;
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
    public function isModuleAllowed(ContentSection $section, string $module): bool
    {
        // fluid-Blöcke nur die des aktiven Kits; gewöhnliche Module des Projekts oder von AddOns wie üblich in REDAXO –
        // im Backend pflegbar und auf der Website über die Seitenleiste (Formular des Cores)
        return is_a($module, BlockModule::class, true) ? $module::kit() === Kit::active() : true;
    }

    /**
     * Menü aus der Struktur (Online-Kategorien, zwei Ebenen; navigation_array) im Format der fluid-Navigation.
     *
     * @return list<array{label: string, href: string, current: bool, active: bool, children: list<array<string, mixed>>}>
     */
    public static function menu(int $depth = 2): array
    {
        // Landing-Domain: Menü auf die Landingpage begrenzt (Unterseiten der Einstiegsseite, sonst keins)
        $start = 0;
        if ($landing = \FriendsOfRedaxo\Fluid\Routing\Router::landing()) {
            if (!$landing->subtree || !Category::get((int) $landing->articleId)) {
                return [];
            }
            $start = (int) $landing->articleId;
        }
        // navigation_array: depth zählt die Ebenen unterhalb der ersten
        $map = static function (NavigationItem $i) use (&$map): array {
            return ['label' => $i->name, 'href' => $i->url, 'current' => $i->current, 'active' => $i->active, 'children' => array_map($map, $i->children)];
        };

        // Menüpunkte „nicht im Menü“ (Metafeld von navigation_array, Seitenbaum) lässt Navigation samt Unterseiten weg
        return array_map($map, new Navigation(start: $start, depth: $depth - 1)->items());
    }
}

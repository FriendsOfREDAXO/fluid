<?php

namespace FriendsOfRedaxo\Fluid;

use Override;

use function count;
use function Redaxo\Core\View\escape;

/**
 * Modul „Layout (Spalten)“ – je Kit eine kleine Klasse, z. B.:
 *
 *     #[AsModule('fluid.layout', 'Layout (Spalten)')]
 *     final class Layout extends LayoutBlock { public function kit(): Kit { return Kit::active(); } }
 *
 * Felder und Ausgabe: Layout. CSS: css/layout.css des Kits (Abbildung 'layout' in kit.php → assets).
 */
abstract class LayoutBlock extends BlockModule
{
    #[Override]
    public function type(): string
    {
        return Layout::TYPE;
    }

    #[Override]
    public function definition(): array
    {
        return Layout::definition();
    }

    #[Override]
    public function nestable(string $variant = ''): bool
    {
        return false;
    }

    #[Override]
    protected function renderInner(Block $b): string
    {
        // Backend: Kurzinfo statt Raster – die Spalten-Blöcke folgen als eigene Slices
        if (self::backendPreview()) {
            $n = count(Layout::children($b->slice));

            return '<p class="blocks-be-layout"><i class="rex-icon fa-table-columns"></i> Raster ' . escape(Layout::PRESET_LABELS[Layout::preset($b->data)])
                . ' · ' . $n . ' ' . (1 === $n ? 'Block' : 'Blöcke') . ' in den Spalten (folgen unten, „Spalte im Layout“ unter „Abschnitt“)</p>';
        }

        return Layout::render($b);
    }
}

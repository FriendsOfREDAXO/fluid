<?php

namespace FriendsOfRedaxo\Fluid;

use FriendsOfRedaxo\Fluid\Field\Checkbox;
use FriendsOfRedaxo\Fluid\Field\Choice;
use FriendsOfRedaxo\Fluid\Form\Stored;
use Override;

use function count;
use function Redaxo\Core\View\escape;

/**
 * Modul „Layout (Spalten)“ – je Kit eine kleine Klasse, z. B.:
 *
 *     #[AsModule('fluid.layout', 'Layout (Spalten)')]
 *     final class Layout extends LayoutBlock { public static function kit(): Kit { return Kit::active(); } }
 *
 * Felder: hier. Ausgabe: Layout::render(). CSS: css/layout.css des Kits (Abbildung 'layout' in kit.php → assets).
 */
abstract class LayoutBlock extends BlockModule
{
    #[Choice('Raster', choices: Layout::PRESET_LABELS, required: true,
        note: 'Weniger Spalten als bisher: Die Blöcke der wegfallenden Spalten wandern in die letzte Spalte – es geht nichts verloren.')]
    public string $preset = '1-1';

    #[Choice('Ausrichtung vertikal', choices: ['top' => 'Oben', 'center' => 'Mitte', 'bottom' => 'Unten', 'stretch' => 'Gestreckt (gleich hoch)'], required: true, width: 'half')]
    public string $valign = 'top';

    #[Choice('Abstand zwischen den Spalten', choices: ['small' => 'Klein', 'normal' => 'Normal', 'large' => 'Groß'], required: true, width: 'half')]
    public string $gap = 'normal';

    #[Choice('Auf schmalen Bildschirmen', choices: ['default' => 'Untereinander (unter ca. 768 px)', 'tablet' => 'Schon auf Tablets untereinander (unter ca. 1024 px)'], required: true)]
    public string $stack = 'default';

    #[Checkbox('Reihenfolge mobil umkehren (letzte Spalte zuerst)')]
    public bool $reverse = false;

    /** @var array{preset?: string, w?: list<int>} gezogene Spaltenbreiten (Griff auf der Website) */
    #[Stored]
    public array $widths = [];

    #[Override]
    public static function type(): string
    {
        return Layout::TYPE;
    }

    #[Override]
    public static function definition(): array
    {
        return [
            'label' => 'Layout (Spalten)', 'group' => 'Layout',
            'help' => 'Blöcke in Spalten nebeneinander: Raster wählen, dann in jede Spalte Blöcke einfügen („+ Block in diese Spalte“). Auf schmalen Bildschirmen stehen die Spalten untereinander. Hintergrund, Abstände und Sprungmarke gelten für den ganzen Abschnitt.',
            'fields' => Field\BlockSchema::fields(static::class),
        ];
    }

    #[Override]
    public static function nestable(string $variant = ''): bool
    {
        return false;
    }

    #[Override]
    public function output(): string
    {
        $b = $this->context();
        // Backend: Kurzinfo statt Raster – die Spalten-Blöcke folgen als eigene Slices
        if (self::backendPreview() && Backend\SliceGrid::active() && null !== $b->slice) {
            $count = count(Layout::children($b->slice));

            return '<p class="blocks-be-layout"><i class="rex-icon fa-table-columns" aria-hidden="true"></i> Raster ' . escape(Layout::PRESET_LABELS[Layout::preset($b->data)])
                . ' · ' . $count . ' ' . (1 === $count ? 'Block' : 'Blöcke') . '</p>' . Backend\SliceGrid::grid($b->slice, $b->data);
        }
        if (self::backendPreview()) {
            // Rasterskizze: Spalten in ihrer Breite (Zwölftel), darin die Blöcke als Sprungmarken zu ihren Slices darunter
            $n = Layout::count($b->data);
            $cols = array_fill(1, $n, '');
            $children = Layout::children($b->slice);
            foreach ($children as $child) {
                $cols[min(Layout::col($child), $n)] .= '<a class="blocks-be-chip' . (1 !== $child->status ? ' is-offline' : '') . '" href="#slice' . $child->id . '">'
                    . escape(BlockModule::summary($child)) . '</a>';
            }
            $units = Layout::units($b->data);
            $grid = '';
            foreach ($cols as $i => $chips) {
                $grid .= '<div class="blocks-be-gcol blocks-be-w' . max(1, min(12, (int) ($units[$i - 1] ?? 6))) . '"><span class="blocks-be-gcol__label">Spalte ' . $i . '</span>'
                    . ('' !== $chips ? $chips : '<span class="blocks-be-empty">leer</span>') . '</div>';
            }
            $count = count($children);

            return '<p class="blocks-be-layout"><i class="rex-icon fa-table-columns" aria-hidden="true"></i> Raster ' . escape(Layout::PRESET_LABELS[Layout::preset($b->data)])
                . ' · ' . $count . ' ' . (1 === $count ? 'Block' : 'Blöcke') . ' in den Spalten</p><div class="blocks-be-grid">' . $grid . '</div>';
        }

        return Layout::render($b);
    }
}

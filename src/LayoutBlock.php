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
        if (self::backendPreview()) {
            $children = Layout::children($b->slice);
            $cols = array_fill(1, Layout::count($b->data), []);
            foreach ($children as $child) {
                $cols[min(Layout::col($child), Layout::count($b->data))][] = escape(BlockModule::summary($child));
            }
            $list = '';
            foreach ($cols as $i => $names) {
                $list .= '<li><strong>Spalte ' . $i . ':</strong> ' . ([] !== $names ? implode(' · ', $names) : '<em>leer</em>') . '</li>';
            }
            $n = count($children);

            return '<p class="blocks-be-layout"><i class="rex-icon fa-table-columns"></i> Raster ' . escape(Layout::PRESET_LABELS[Layout::preset($b->data)])
                . ' · ' . $n . ' ' . (1 === $n ? 'Block' : 'Blöcke') . ' in den Spalten – sie folgen eingerückt darunter</p><ul class="blocks-be-cols">' . $list . '</ul>';
        }

        return Layout::render($b);
    }
}

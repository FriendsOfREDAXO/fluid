<?php

namespace Project\Block;

use FriendsOfRedaxo\Fluid\Block;
use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\{Checkbox, Choice, DatePicker, Link, Media, Text, Textarea};
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use Override;
use Redaxo\Core\Content\AsModule;

use function Redaxo\Core\View\escape;

/**
 * Das Beispiel-Modul „Hinweis“ (examples/module/HinweisModule.php) als fluid-Block – alles in einer Datei:
 *
 *  1. erbt von FluidBlock statt Module, der Schlüssel beginnt mit „fluid.“, #[AsBlock] für die Blockauswahl;
 *  2. Feld-Attribute von fluid statt der des Cores (gleiche Namen und Parameter, dazu Link, Media, RichText, Repeater …);
 *  3. view() statt output(): `$d` enthält die Felder, `$b->edit('feld')` macht ein Feld auf der Seite bearbeitbar.
 */
#[AsModule('fluid.hinweis', 'Hinweis')]
#[AsBlock(icon: 'info', group: 'Inhalt', nestable: true, help: 'Kurzer Hinweis mit Art, Bild und Link.')]
final class Hinweis extends FluidBlock
{
    #[Text('Überschrift', required: true, maxLength: 120)]
    public string $headline = '';

    #[Textarea('Text', rows: 4)]
    public string $text = '';

    #[Choice('Art', choices: ['info' => 'Information', 'warn' => 'Warnung', 'ok' => 'Erledigt'], width: 'half')]
    public string $kind = 'info';

    #[Checkbox('Hervorheben', width: 'half')]
    public bool $strong = false;

    #[DatePicker('Gültig bis', width: 'half')]
    public string $until = '';

    #[Link('Mehr dazu', width: 'half')]
    public string $link = '';

    #[Media('Bild')]
    public string $image = '';

    #[Override]
    protected static function view(Block $b, array $d): string
    {
        $color = ['warn' => '#dc2626', 'ok' => '#16a34a'][$d['kind']] ?? 'var(--f-a)';
        $html = '<div class="wrap"><aside class="card" style="padding:var(--f-s-l);border-left:6px solid ' . $color . ($d['strong'] ? ';box-shadow:0 0 0 2px ' . $color : '') . '">'
            . '<h2 class="h3"' . $b->edit('headline') . '>' . escape($d['headline']) . '</h2>'
            . '<p' . $b->edit('text', 'lines') . '>' . nl2br(escape($d['text']), false) . '</p>';
        if ('' !== $d['until']) {
            $html .= '<p class="muted">Gültig bis ' . escape(date('d.m.Y', (int) strtotime($d['until']))) . '</p>';
        }
        if ('' !== $d['image']) {
            $html .= \img($d['image'], '24rem');
        }
        if ('' !== trim($d['link'])) {
            $html .= '<p><a ' . \fluid_link_attrs($d['link']) . '>Mehr dazu →</a></p>';
        }

        return $html . '</aside></div>';
    }
}

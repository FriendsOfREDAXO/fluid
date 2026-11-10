<?php

namespace Project\Block;

use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\{Checkbox, Choice, DatePicker, Link, MediaPicker, Text, Textarea};
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use Override;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\MediaPool\Media;

use function Redaxo\Core\View\escape;

/**
 * Das Beispiel-Modul „Hinweis“ (examples/module/HinweisModule.php) als fluid-Block. Gegenüber dem Modul ändert sich:
 *
 *  1. erbt von FluidBlock statt Module, der Schlüssel beginnt mit „fluid.“, #[AsBlock] für die Blockauswahl;
 *  2. die Felder bleiben die des Cores – hier die gleichnamigen von fluid, die zusätzlich `width` (halbe Breite in der
 *     Seitenleiste) kennen; dazu Link, ein Feld nur von fluid;
 *  3. output() bleibt output(): `$this->edit('feld')` macht ein Feld direkt auf der Seite bearbeitbar.
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
    public ?\DateTimeImmutable $until = null;

    #[Link('Mehr dazu', width: 'half')]
    public string $link = '';

    #[MediaPicker('Bild', preview: true)]
    public ?Media $image = null;

    #[Override]
    public function output(): string
    {
        $color = ['warn' => '#dc2626', 'ok' => '#16a34a'][$this->kind] ?? 'var(--f-a)';
        $html = '<div class="wrap"><aside class="card" style="padding:var(--f-s-l);border-left:6px solid ' . $color . ($this->strong ? ';box-shadow:0 0 0 2px ' . $color : '') . '">'
            . '<h2 class="h3"' . $this->edit('headline') . '>' . escape($this->headline) . '</h2>'
            . '<p' . $this->edit('text', 'lines') . '>' . nl2br(escape($this->text), false) . '</p>';
        if ($this->until) {
            $html .= '<p class="muted">Gültig bis ' . escape($this->until->format('d.m.Y')) . '</p>';
        }
        if ($this->image) {
            $html .= \img($this->image->fileName, '24rem');
        }
        if ('' !== trim($this->link)) {
            $html .= '<p><a ' . \fluid_link_attrs($this->link) . '>Mehr dazu →</a></p>';
        }

        return $html . '</aside></div>';
    }
}

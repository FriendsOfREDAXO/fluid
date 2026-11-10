<?php

namespace Project\Block;

use FriendsOfRedaxo\Fluid\InlineStyle;
use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\Link;
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use Override;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Form\Field\{Text, Textarea};

use function Redaxo\Core\View\escape;

/**
 * Eigener Block im Projekt – mit den Feldern des Cores (ohne Ergänzungen von fluid) und Link, einem Feld nur von fluid.
 * Die Ausgabe nutzt die Hilfen des Kits (Klassen wrap, card, btn) – das Kit selbst bleibt unverändert.
 */
#[AsModule('fluid.teaser', 'Teaser (Projekt)')]
#[AsBlock(icon: 'star', group: 'Inhalt', nestable: true, help: 'Beispiel für einen eigenen Block im Projekt.')]
final class Teaser extends FluidBlock
{
    #[Text('Überschrift', required: true, maxLength: 80)]
    public string $title = '';

    #[Textarea('Text', rows: 3)]
    public string $text = '';

    #[Link('Link')]
    public string $link = '';

    #[Override]
    public function output(): string
    {
        // keine style-Attribute (CSP): InlineStyle vergibt eine Klasse, die Regel steht im <style nonce> der Seite
        return '<div class="wrap"><div class="card ' . InlineStyle::class('padding:var(--f-s-l)') . '">'
            . '<h2 class="h3"' . $this->edit('title') . '>' . escape($this->title) . '</h2>'
            . '<p' . $this->edit('text', 'lines') . '>' . nl2br(escape($this->text), false) . '</p>'
            . ('' !== trim($this->link) ? '<a class="btn btn--primary" ' . \fluid_link_attrs($this->link) . '>Mehr</a>' : '')
            . '</div></div>';
    }
}

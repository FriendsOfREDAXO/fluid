<?php

namespace Project\Block;

use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\{Link, Text, Textarea};
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;

/** Eigener Block im Projekt: Felder als Attribute, Ausgabe in Teaser.view.php daneben – das Kit bleibt unverändert. */
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
}

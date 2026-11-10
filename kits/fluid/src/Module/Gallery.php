<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module;

use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\{Checkbox, Choice, Collection, Text, Textarea};
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use FriendsOfRedaxo\Fluid\Kit\Fluid\Module\Item\GalleryImage;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.gallery', 'Bildergalerie')]
#[AsBlock(
    icon: 'images',
    group: 'Medien',
    help: 'Bilder als Raster, Mosaik oder in bündigen Zeilen. Ein Klick öffnet das Bild groß (Lightbox: Tastatur, Wischen, Esc). Alt-Texte kommen aus dem Medienpool.',
    variants: [
        'grid' => 'Raster (gleiches Format)',
        'masonry' => 'Mosaik (Originalformat, Spalten)',
        'justified' => 'Zeilen (Originalformat, bündig)',
    ],
    nestable: true,
)]
final class Gallery extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half')]
    public string $title = '';

    #[Textarea('Einleitung (optional)', maxLength: 400, rows: 2)]
    public string $intro = '';

    #[Collection('Bilder', item: GalleryImage::class, itemLabel: 'Bild', titleField: 'caption', max: 60)]
    public array $images = [];

    #[Choice('Spalten', required: true, width: 'half', choices: ['2' => '2 Spalten', '3' => '3 Spalten', '4' => '4 Spalten', '5' => '5 Spalten'],
        note: 'Bei „Zeilen“: Zeilenhöhe (mehr Spalten = kleinere Bilder).')]
    public string $columns = '3';

    #[Choice('Bildformat', required: true, width: 'half', choices: self::RATIOS, variants: ['grid'])]
    public string $ratio = '4:3';

    #[Checkbox('Bildunterschriften anzeigen', width: 'half')]
    public bool $captions = true;

    #[Checkbox('Bilder per Klick groß anzeigen (Lightbox)', width: 'half')]
    public bool $lightbox = true;
}

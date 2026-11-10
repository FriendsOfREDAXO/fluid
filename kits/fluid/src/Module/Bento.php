<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module;

use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\{Choice, Icon, Link, Media, Repeater, Text, Textarea};
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.bento', 'Bento-Raster')]
#[AsBlock(
    icon: 'squares-four',
    group: 'Inhalt',
    help: 'Kacheln unterschiedlicher Größe in einem dichten Raster – für Leistungen, Zahlen und Bilder. Kacheln rücken bei wenig Platz automatisch untereinander.',
    nestable: false,
)]
final class Bento extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half')]
    public string $title = '';

    #[Textarea('Einleitung (optional)', maxLength: 400, rows: 2)]
    public string $intro = '';

    #[Repeater(
        'Kacheln',
        item: 'Kachel',
        titleField: 'title',
        max: 10,
        fields: [
            new Choice(
                'Größe',
                key: 'size',
                required: true,
                choices: ['s' => 'Normal', 'wide' => 'Breit', 'tall' => 'Hoch', 'big' => 'Groß (breit + hoch)'],
                width: 'half',
                default: 's',
            ),
            new Choice(
                'Fläche',
                key: 'tone',
                required: true,
                choices: [
                    'card' => 'Karte',
                    'tint' => 'Akzent hell',
                    'highlight' => 'Zweitfarbe',
                    'accent' => 'Akzentfarbe',
                    'dark' => 'Dunkel',
                    'image' => 'Bild mit Text darauf',
                ],
                width: 'half',
                default: 'card',
            ),
            new Icon('Symbol (optional)', key: 'icon', width: 'half'),
            new Media('Bild (optional)', key: 'image', width: 'half'),
            new Text('Dachzeile oder große Zahl (optional)', key: 'eyebrow', maxLength: 30, width: 'half'),
            new Text('Titel', key: 'title', required: true, maxLength: 80, width: 'half'),
            new Textarea('Text', key: 'text', maxLength: 240, rows: 2),
            new Link('Link (ganze Kachel, optional)', key: 'link'),
        ],
    )]
    public array $items = [];
}

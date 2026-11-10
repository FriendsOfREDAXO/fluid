<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module;

use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\{Media, Repeater, Text, Textarea};
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.quote', 'Zitat / Stimmen')]
#[AsBlock(
    icon: 'quotes',
    group: 'Inhalt',
    help: '„Groß“: ein Zitat als Blickfang (Pull-Quote). „Raster“, „Band“ und „Laufband“ für mehrere Stimmen (Laufband hält bei Maus oder Fokus an). Nur echte, freigegebene Stimmen verwenden.',
    variants: [
        'single' => 'Groß (ein Zitat)',
        'grid' => 'Raster (Mauerwerk)',
        'reel' => 'Wischbares Band',
        'marquee' => 'Laufband (läuft langsam durch)',
    ],
    nestable: ['single', 'grid'],
)]
final class Quote extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half')]
    public string $title = '';

    #[Repeater(
        'Zitate',
        item: 'Zitat',
        titleField: 'name',
        max: 12,
        fields: [
            new Textarea('Zitat', key: 'text', required: true, maxLength: 480, rows: 3),
            new Text('Name', key: 'name', width: 'half'),
            new Text('Funktion / Organisation', key: 'role', width: 'half'),
            new Media('Porträt (optional, 1:1)', key: 'image'),
        ],
    )]
    public array $items = [];
}

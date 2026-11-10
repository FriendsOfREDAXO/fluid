<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module;

use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\{Link, Media, Repeater, Text};
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.logos', 'Logos (Partner, Kunden)')]
#[AsBlock(
    icon: 'handshake',
    group: 'Medien',
    help: 'Als ruhiges Raster oder Laufband (ohne JavaScript; steht bei „Bewegung reduzieren“ still und hält bei Maus/Fokus an). Ohne Bild erscheint der Name als Schriftzug. Nur Logos mit Freigabe verwenden.',
    variants: ['grid' => 'Raster', 'marquee' => 'Laufband'],
    nestable: ['grid'],
)]
final class Logos extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half')]
    public string $title = '';

    #[Repeater(
        'Logos',
        item: 'Logo',
        titleField: 'name',
        max: 16,
        fields: [
            new Text('Name', key: 'name', required: true, width: 'half'),
            new Link('Link (optional)', key: 'link', width: 'half'),
            new Media('Logo (SVG oder PNG, transparent)', key: 'image'),
        ],
    )]
    public array $items = [];
}

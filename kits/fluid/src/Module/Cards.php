<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module;

use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\{Choice, Collection, Link, MediaPicker, Text, Textarea};
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;
use FriendsOfRedaxo\Fluid\Kit\Fluid\Module\Item\CardsItem;

#[AsModule('fluid.cards', 'Karten')]
#[AsBlock(
    icon: 'copy',
    group: 'Inhalt',
    help: 'Teaser mit Bild – klassisch, Text auf dem Bild, quer oder als wischbares Band (Reel). Karten stellen sich auf ihre eigene Breite ein.',
    variants: [
        'image' => 'Bild oben',
        'overlay' => 'Text auf dem Bild',
        'horizontal' => 'Quer (Bild links)',
        'reel' => 'Wischbares Band (Reel)',
    ],
    nestable: ['image', 'horizontal', 'overlay'],
)]
final class Cards extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half')]
    public string $title = '';

    #[Textarea('Einleitung (optional)', maxLength: 400, rows: 2)]
    public string $intro = '';

    #[Choice(
        'Breite je Eintrag (mindestens)',
        required: true,
        choices: self::SIZES,
        width: 'half',
        note: 'Wie viele Einträge nebeneinander passen, ergibt sich aus dem verfügbaren Platz – auf jedem Gerät.',
    )]
    public string $size = 'm';

    #[Choice('Bildformat', required: true, choices: self::RATIOS, width: 'half')]
    public string $ratio = '3:2';

    #[Collection('Karten', item: CardsItem::class, itemLabel: 'Karte', titleField: 'title', max: 12)]
    public array $items = [];

    #[Text('Button unter den Karten (optional)', maxLength: 32, width: 'half')]
    public string $moreLabel = '';

    #[Link('Button-Link', width: 'half')]
    public string $moreLink = '';
}

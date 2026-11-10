<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module;

use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\{Link, MediaPicker, Text, Textarea};
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\MediaPool\Media;

#[AsModule('fluid.cta', 'Handlungsaufruf')]
#[AsBlock(
    icon: 'megaphone',
    group: 'Inhalt',
    background: 'accent',
    variants: [
        'band' => 'Band über die volle Breite',
        'box' => 'Hervorgehobene Box',
        'split' => 'Mit Bild',
        'big' => 'Großer Schriftzug',
    ],
    nestable: ['box', 'big'],
)]
final class Cta extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60)]
    public string $eyebrow = '';

    #[Text('Überschrift', required: true, maxLength: 110)]
    public string $title = '';

    #[Textarea('Text (optional)', maxLength: 300, rows: 2)]
    public string $text = '';

    #[Text('Button 1 – Beschriftung', maxLength: 32, width: 'half')]
    public string $buttonLabel = '';

    #[Link(
        'Button 1 – Link',
        width: 'half',
        note: 'Seite, #anker, https://… – Sonderwerte „phone“ und „email“ nutzen „Website“.',
    )]
    public string $buttonLink = '';

    #[Text('Button 2 – Beschriftung', maxLength: 32, width: 'half')]
    public string $button2Label = '';

    #[Link('Button 2 – Link', width: 'half')]
    public string $button2Link = '';

    #[MediaPicker('Bild (bei „Mit Bild“)', preview: true)]
    public ?Media $image = null;
}

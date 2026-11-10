<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module;

use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\{Choice, Link, MediaPicker, RichText, Text, Textarea};
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\MediaPool\Media;

#[AsModule('fluid.media_text', 'Text + Bild')]
#[AsBlock(
    icon: 'square-half',
    group: 'Inhalt',
    help: '„Abwechselnd“: mehrere Blöcke nacheinander wechseln automatisch die Seite. „Geteilt“: Bild und Text je halb, randlos und auf Wunsch bildschirmhoch.',
    variants: [
        'auto' => 'Abwechselnd (automatisch)',
        'right' => 'Bild rechts',
        'left' => 'Bild links',
        'split' => 'Geteilt, randlos (Split-Screen)',
        'overlap' => 'Text-Karte über großem Bild',
    ],
    drags: [
        [
            'field' => 'split',
            'target' => '.mt__media',
            'class' => 'mt--s-{v}',
            'apply' => '.mt',
            'label' => 'Aufteilung',
        ],
    ],
)]
final class MediaText extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', required: true, maxLength: 110, width: 'half')]
    public string $title = '';

    #[RichText('Text')]
    public string $text = '';

    #[Textarea('Aufzählung mit Häkchen (ein Punkt pro Zeile, optional)', rows: 3)]
    public string $list = '';

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

    #[MediaPicker('Bild', width: 'half', note: 'Min. 1200 px breit.', preview: true)]
    public ?Media $image = null;

    #[Choice('Bildformat', required: true, choices: self::RATIOS, width: 'half')]
    public string $ratio = '4:3';

    #[Choice(
        'Aufteilung Bild/Text',
        required: true,
        choices: [
            'text' => 'Text breiter',
            'even' => 'Ausgewogen',
            'media' => 'Bild breiter',
            'mediaxl' => 'Bild deutlich breiter',
        ],
        width: 'half',
        variants: ['auto', 'right', 'left'],
        note: 'Auch auf der Seite einstellbar: am inneren Rand des Bildes ziehen.',
    )]
    public string $split = 'even';
}

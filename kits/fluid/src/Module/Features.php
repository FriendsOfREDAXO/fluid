<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module;

use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\{Choice, Icon, Link, Media, Repeater, Text, Textarea};
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.features', 'Merkmale / Leistungen')]
#[AsBlock(
    icon: 'squares-four',
    group: 'Inhalt',
    help: 'Leistungen, Vorteile oder Angebote mit Symbol oder Bild. Anzahl nebeneinander ergibt sich aus dem Platz.',
    variants: [
        'cards' => 'Karten',
        'icons' => 'Große Symbole, ohne Rahmen',
        'list' => 'Liste mit Symbol links',
        'numbered' => 'Nummeriert',
    ],
    nestable: true,
)]
final class Features extends FluidBlock
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

    #[Repeater(
        'Einträge',
        item: 'Eintrag',
        titleField: 'title',
        max: 12,
        fields: [
            new Icon('Symbol', key: 'icon', width: 'half'),
            new Media('oder Bild (3:2)', key: 'image', width: 'half'),
            new Text('Titel', key: 'title', required: true, maxLength: 70),
            new Textarea('Text', key: 'text', maxLength: 320, rows: 3),
            new Text('Link – Beschriftung (optional)', key: 'link_label', maxLength: 32, width: 'half'),
            new Link('Link', key: 'link', width: 'half'),
        ],
    )]
    public array $items = [];
}

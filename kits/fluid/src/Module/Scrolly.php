<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module;

use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\{Choice, Media, Repeater, Text, Textarea};
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.scrolly', 'Scrollytelling')]
#[AsBlock(
    icon: 'article',
    group: 'Inhalt',
    help: 'Ein Bild bleibt stehen, während die Texte daneben vorbeiziehen; das Bild wechselt passend zum Abschnitt. Bei wenig Platz stehen Bild und Text jeweils untereinander.',
    variants: ['left' => 'Bild links', 'right' => 'Bild rechts'],
)]
final class Scrolly extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half')]
    public string $title = '';

    #[Textarea('Einleitung (optional)', maxLength: 400, rows: 2)]
    public string $intro = '';

    #[Choice('Bildformat', required: true, choices: self::RATIOS, width: 'half')]
    public string $ratio = '4:5';

    #[Repeater(
        'Abschnitte',
        item: 'Abschnitt',
        titleField: 'title',
        max: 8,
        fields: [
            new Text('Dachzeile (optional)', key: 'eyebrow', maxLength: 40, width: 'half'),
            new Text('Titel', key: 'title', required: true, maxLength: 90, width: 'half'),
            new Textarea('Text', key: 'text', maxLength: 600, rows: 4),
            new Media('Bild', key: 'image'),
        ],
    )]
    public array $items = [];
}

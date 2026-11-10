<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module;

use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\{Choice, Icon, Repeater, Text, Textarea};
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.steps', 'Ablauf / Zeitleiste')]
#[AsBlock(
    icon: 'path',
    group: 'Inhalt',
    help: 'Nummerierte Schritte, eine Zeitleiste mit Datum/Phase oder ein Prozess mit Verbindungslinien.',
    variants: ['numbers' => 'Nummerierte Schritte', 'timeline' => 'Zeitleiste', 'process' => 'Prozess mit Pfeilen'],
    nestable: ['numbers', 'timeline'],
)]
final class Steps extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half')]
    public string $title = '';

    #[Textarea('Einleitung (optional)', maxLength: 400, rows: 2)]
    public string $intro = '';

    #[Choice(
        'Kreise',
        required: true,
        choices: [
            'auto' => 'Wie Design',
            'filled' => 'Gefüllt',
            'soft' => 'Zart gefüllt mit Rand',
            'ring' => 'Nur Rand',
            'dot' => 'Kleiner Punkt (ohne Nummer)',
        ],
        width: 'half',
        variants: ['timeline', 'process'],
    )]
    public string $marker = 'auto';

    #[Choice(
        'Farben',
        required: true,
        choices: [
            'auto' => 'Wie Design',
            'alternate' => 'Farbig abwechselnd',
            'accent' => 'Eine Farbe (Akzent)',
            'neutral' => 'Neutral (Schriftfarbe)',
        ],
        width: 'half',
        variants: ['timeline', 'process', 'numbers'],
    )]
    public string $colors = 'auto';

    #[Choice(
        'Animation',
        required: true,
        choices: [
            'auto' => 'Wie Website (einblenden)',
            'sequence' => 'Nacheinander einblenden',
            'draw' => 'Linie wächst beim Scrollen (Zeitleiste)',
            'none' => 'Keine Animation',
        ],
        width: 'half',
        note: 'Animationen entfallen, wenn Besucher in ihrem System „Bewegung reduzieren“ eingestellt haben. „Nacheinander einblenden“ braucht die Bewegung der Website (Design).',
    )]
    public string $motion = 'auto';

    #[Repeater(
        'Schritte',
        item: 'Schritt',
        titleField: 'title',
        max: 12,
        fields: [
            new Text(
                'Phase / Datum (optional)',
                key: 'meta',
                maxLength: 40,
                placeholder: 'z. B. Woche 1',
                width: 'half',
            ),
            new Text('Titel', key: 'title', required: true, maxLength: 80, width: 'half'),
            new Textarea('Text', key: 'text', maxLength: 400, rows: 3),
            new Icon('Symbol statt Nummer (optional)', key: 'icon'),
        ],
    )]
    public array $items = [];
}

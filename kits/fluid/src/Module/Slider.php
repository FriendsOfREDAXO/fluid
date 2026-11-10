<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module;

use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\{Checkbox, Choice, Collection, Text, Textarea};
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use FriendsOfRedaxo\Fluid\Kit\Fluid\Module\Item\Slide;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.slider', 'Slider')]
#[AsBlock(
    icon: 'slideshow',
    group: 'Medien',
    help: 'Bilder oder Folien mit Text nacheinander – per Pfeil, Punkt, Tastatur oder Wischen. Ohne JavaScript ein wischbarer Streifen. Automatisch weiter nur mit Pause-Knopf, Halt bei Maus und Fokus, nie bei „Bewegung reduzieren“.',
    variants: [
        'slide' => 'Schieben',
        'fade' => 'Überblenden',
    ],
    nestable: true,
)]
final class Slider extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half')]
    public string $title = '';

    #[Textarea('Einleitung (optional)', maxLength: 400, rows: 2)]
    public string $intro = '';

    #[Collection('Folien', item: Slide::class, itemLabel: 'Folie', titleField: 'title', max: 12)]
    public array $slides = [];

    #[Choice('Format / Höhe', required: true, width: 'half', choices: [
        '16:9' => 'Breitbild 16:9', '21:9' => 'Kino 21:9', '3:2' => 'Querformat 3:2', '4:3' => 'Querformat 4:3', 'screen' => 'Vollbild (Bildschirmhöhe)',
    ])]
    public string $height = '16:9';

    #[Checkbox('Endlos (nach der letzten Folie wieder die erste)', width: 'half')]
    public bool $loop = true;

    #[Checkbox('Pfeile anzeigen', width: 'half')]
    public bool $arrows = true;

    #[Checkbox('Punkte anzeigen', width: 'half')]
    public bool $dots = true;

    #[Checkbox('Automatisch weiterblättern', width: 'half',
        note: 'Mit Pause-Knopf; hält bei Maus und Fokus an und bleibt bei „Bewegung reduzieren“ aus.')]
    public bool $autoplay = false;

    #[Choice('Anzeigedauer je Folie', required: true, width: 'half', choices: ['4' => '4 Sekunden', '6' => '6 Sekunden', '8' => '8 Sekunden', '10' => '10 Sekunden'])]
    public string $interval = '6';
}

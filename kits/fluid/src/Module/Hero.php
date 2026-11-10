<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module;

use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\{Choice, File, Icon, Link, Media, Repeater, Text, Textarea};
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.hero', 'Einstieg (Hero)')]
#[AsBlock(
    icon: 'star',
    group: 'Kopf',
    help: 'Große Überschrift der Seite (H1). Pro Seite genau einmal verwenden – „Seitenkopf“ für Unterseiten. Alle Varianten ordnen sich nach verfügbarem Platz an.',
    variants: [
        'split' => 'Text und Bild nebeneinander',
        'centered' => 'Zentriert, Bild darunter',
        'fullbleed' => 'Vollflächiges Bild oder Video mit Abdunkelung',
        'cards' => 'Mit gestapelten Karten',
        'type' => 'Großer Schriftzug (typografisch)',
        'compact' => 'Seitenkopf (ohne Bild)',
        'scale' => 'Fließende Skala (Überschrift + Seitenspalte)',
    ],
    variantHelp: [
        'split' => 'Startseite mit einem aussagekräftigen Bild – der Klassiker.',
        'centered' => 'Kurze, starke Botschaft; Bild im Breitbild darunter.',
        'fullbleed' => 'Stimmung zählt: Raum, Landschaft, Werkstatt – großes Bild oder kurzes stummes Video hinter dem Text.',
        'cards' => 'Drei Stärken sollen sofort ins Auge fallen, z. B. Leistungen oder Versprechen.',
        'type' => 'Ohne gutes Foto oder für eine klare Haltung: die Überschrift ist das Bild.',
        'compact' => 'Unterseiten: Seitentitel mit kurzer Einleitung.',
        'scale' => 'Typografischer Auftritt mit Fakten: große Überschrift, daneben Text und Buttons, darunter bis zu vier Kennzahlen oder Schlagworte. Passt sich dem eigenen Platz an – funktioniert auch in schmalen Spalten.',
    ],
)]
final class Hero extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60)]
    public string $eyebrow = '';

    #[Text(
        'Überschrift (H1)',
        required: true,
        maxLength: 110,
        note: 'Ein Wort in *Sternchen* wird hervorgehoben (Akzentfarbe bzw. kursiv bei Serifen).',
    )]
    public string $title = '';

    #[Textarea('Text', maxLength: 360, rows: 3)]
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

    #[Textarea(
        'Kurze Pluspunkte (eine pro Zeile, optional)',
        rows: 3,
        note: 'Erscheinen als Häkchen unter den Buttons, z. B. „Termine auch samstags“.',
    )]
    public string $points = '';

    #[Media('Bild', width: 'half', note: 'Min. 1600 px breit. Alt-Text in der Mediathek pflegen.')]
    public string $image = '';

    #[Choice(
        'Bildformat',
        required: true,
        choices: self::RATIOS,
        width: 'half',
        note: 'Bei „Zentriert“ immer 16:9; bei „Vollflächig“ füllt das Bild den Abschnitt.',
    )]
    public string $ratio = '4:5';

    #[File(
        'Hintergrundvideo (MP4, stumm, nur „Vollflächig“)',
        width: 'half',
        variants: ['fullbleed'],
        note: 'Kurz und klein (< 8 MB). Startet nur ohne „Bewegung reduzieren“ und hat eine Pause-Schaltfläche. Das Bild dient als Standbild.',
    )]
    public string $video = '';

    #[Choice(
        'Abdunkelung (nur „Vollflächig“)',
        required: true,
        choices: ['strong' => 'Stark (empfohlen)', 'medium' => 'Mittel', 'gradient' => 'Verlauf von unten'],
        width: 'half',
        variants: ['fullbleed'],
    )]
    public string $overlay = 'strong';

    #[Repeater(
        'Karten (nur „Mit gestapelten Karten“)',
        item: 'Karte',
        titleField: 'title',
        max: 3,
        variants: ['cards'],
        fields: [
            new Icon('Symbol', key: 'icon', width: 'half'),
            new Text('Titel', key: 'title', required: true, maxLength: 50, width: 'half'),
            new Text('Text', key: 'text', maxLength: 120),
        ],
    )]
    public array $cards = [];

    #[Textarea(
        'Stufenleiste: Kennzahlen oder Schlagworte (eine pro Zeile, bis zu 4)',
        rows: 4,
        variants: ['scale'],
        note: '„Wert: Bezeichnung“ ergibt eine Kennzahl, z. B. „24 h: Rückmeldung“; eine Zeile ohne Doppelpunkt ein Schlagwort. Jede Stufe ist eine Schriftstufe kleiner – wie die Typo-Skala. Nur belegbare Zahlen.',
    )]
    public string $scaleItems = '';
}

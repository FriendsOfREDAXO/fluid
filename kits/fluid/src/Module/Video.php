<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module;

use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\{Checkbox, Choice, File, Media, Repeater, RichText, Text, Textarea, Url};
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.video', 'Video')]
#[AsBlock(
    icon: 'play-circle',
    group: 'Medien',
    help: 'YouTube/Vimeo mit Zwei-Klick-Lösung (lädt erst nach Klick, ohne Cookies bis dahin) oder eigene Videodatei aus dem Medienpool – mit Vorschaubild, Untertiteln und Transkript.',
    variants: ['wide' => 'Breit', 'text' => 'Mit Text daneben', 'cinema' => 'Kino (randlos, dunkel)'],
    nestable: ['wide'],
)]
final class Video extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half')]
    public string $title = '';

    #[Textarea('Einleitung (optional)', maxLength: 400, rows: 2)]
    public string $intro = '';

    #[Url('YouTube- oder Vimeo-Link', width: 'half')]
    public string $videoUrl = '';

    #[File(
        'oder eigene Videodatei',
        types: ['mp4', 'm4v', 'webm', 'mov'],
        width: 'half',
        note: 'Datei aus dem Medienpool (MP4 oder WebM).',
    )]
    public string $videoFile = '';

    #[Media(
        'Eigenes Vorschaubild (optional)',
        width: 'half',
        note: 'Ohne Auswahl zeigt eine eigene Videodatei automatisch ein Standbild aus dem Video.',
    )]
    public string $poster = '';

    #[Repeater(
        'Untertitel und Kapitel (eigene Videodatei)',
        item: 'Spur',
        titleField: 'lang',
        note: 'WebVTT- (.vtt) oder SubRip-Datei (.srt) aus dem Medienpool – Besucher schalten sie im Player ein.',
        fields: [
            new File('Datei (.vtt oder .srt)', key: 'file', required: true, types: ['vtt', 'srt']),
            new Text(
                'Sprache (Kürzel)',
                key: 'lang',
                required: true,
                maxLength: 10,
                placeholder: 'de',
                width: 'half',
            ),
            new Choice(
                'Art',
                key: 'kind',
                choices: [
                    'subtitles' => 'Untertitel',
                    'captions' => 'Untertitel für Gehörlose (mit Geräuschen)',
                    'chapters' => 'Kapitel',
                ],
                width: 'half',
                default: 'subtitles',
            ),
            new Text(
                'Bezeichnung im Player (optional)',
                key: 'label',
                maxLength: 40,
                placeholder: 'automatisch, z. B. „Deutsch“',
            ),
        ],
    )]
    public array $tracks = [];

    #[Checkbox('Untertitel in der Sprache der Seite gleich einschalten', width: 'half')]
    public bool $ccOn = false;

    #[Checkbox('Transkript unter dem Video („Text zum Video“)', width: 'half')]
    public bool $transcript = false;

    #[Choice(
        'Format',
        required: true,
        choices: ['16-9' => '16:9', '4-3' => '4:3', '21-9' => '21:9'],
        width: 'half',
    )]
    public string $ratio = '16-9';

    #[Text('Bildunterschrift (optional)', maxLength: 200)]
    public string $caption = '';

    #[RichText('Text daneben (bei „Mit Text daneben“)')]
    public string $text = '';
}

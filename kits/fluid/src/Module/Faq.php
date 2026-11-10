<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module;

use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\{Repeater, RichText, Text, Textarea};
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.faq', 'Fragen & Antworten')]
#[AsBlock(
    icon: 'question',
    group: 'Inhalt',
    help: 'Aufklappbare Fragen (ohne JavaScript). Suchmaschinen erhalten die Fragen als strukturierte Daten (FAQPage).',
    variants: ['split' => 'Überschrift links, Fragen rechts', 'stacked' => 'Untereinander, zentriert'],
    nestable: ['stacked'],
    jsonld: ['type' => 'faq', 'items' => 'items', 'question' => 'q', 'answer' => 'a'],
)]
final class Faq extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', required: true, maxLength: 110, width: 'half')]
    public string $title = '';

    #[Textarea('Einleitung (optional)', maxLength: 400, rows: 2)]
    public string $intro = '';

    #[Repeater(
        'Fragen',
        item: 'Frage',
        titleField: 'q',
        fields: [
            new Text('Frage', key: 'q', required: true, maxLength: 160),
            new RichText('Antwort', key: 'a', required: true),
        ],
    )]
    public array $items = [];
}

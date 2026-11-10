<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module;

use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\{Checkbox, Link, Repeater, Text, Textarea};
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.pricing', 'Pakete / Vergleich')]
#[AsBlock(
    icon: 'currency-eur',
    group: 'Inhalt',
    help: 'Pakete als Karten oder als Vergleichstabelle. Ein Paket lässt sich hervorheben. Nur verbindliche, aktuelle Preise angeben.',
    variants: ['cards' => 'Karten', 'table' => 'Vergleichstabelle'],
)]
final class Pricing extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half')]
    public string $title = '';

    #[Textarea('Einleitung (optional)', maxLength: 400, rows: 2)]
    public string $intro = '';

    #[Repeater(
        'Pakete',
        item: 'Paket',
        titleField: 'name',
        max: 4,
        fields: [
            new Text('Name', key: 'name', required: true, maxLength: 40, width: 'half'),
            new Text(
                'Etikett (optional)',
                key: 'badge',
                maxLength: 24,
                placeholder: 'z. B. Empfohlen',
                width: 'half',
            ),
            new Text('Preis', key: 'price', maxLength: 24, placeholder: 'z. B. ab 490 €', width: 'half'),
            new Text(
                'Zeitraum / Zusatz',
                key: 'period',
                maxLength: 32,
                placeholder: 'z. B. pro Monat',
                width: 'half',
            ),
            new Textarea('Kurzbeschreibung', key: 'text', maxLength: 220, rows: 2),
            new Textarea('Leistungen (eine pro Zeile, bei „Karten“)', key: 'features', rows: 4),
            new Checkbox('Hervorheben', key: 'highlight', default: false),
            new Text('Button – Beschriftung', key: 'button_label', maxLength: 32, width: 'half'),
            new Link('Button – Link', key: 'button_link', width: 'half'),
        ],
    )]
    public array $items = [];

    #[Repeater(
        'Vergleichszeilen (bei „Vergleichstabelle“)',
        item: 'Zeile',
        titleField: 'label',
        max: 20,
        fields: [
            new Text('Merkmal', key: 'label', required: true, maxLength: 80),
            new Textarea(
                'Werte je Paket (eine Zeile pro Paket, in Reihenfolge)',
                key: 'values',
                rows: 3,
                note: '„ja“ oder „✓“ = enthalten, „nein“ oder „–“ = nicht enthalten, sonst freier Text (z. B. „3 Termine“).',
            ),
        ],
    )]
    public array $rows = [];

    #[Text(
        'Hinweis unter den Paketen (optional)',
        maxLength: 200,
        placeholder: 'z. B. Alle Preise inkl. MwSt.',
    )]
    public string $note = '';
}

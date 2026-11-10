<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module;

use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\{Checkbox, Collection, Link, Text, Textarea};
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;
use FriendsOfRedaxo\Fluid\Kit\Fluid\Module\Item\PricingItem;
use FriendsOfRedaxo\Fluid\Kit\Fluid\Module\Item\PricingRow;

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

    #[Collection('Pakete', item: PricingItem::class, itemLabel: 'Paket', titleField: 'name', max: 4)]
    public array $items = [];

    #[Collection(
        'Vergleichszeilen (bei „Vergleichstabelle“)',
        item: PricingRow::class,
        itemLabel: 'Zeile',
        titleField: 'label',
        max: 20,
    )]
    public array $rows = [];

    #[Text(
        'Hinweis unter den Paketen (optional)',
        maxLength: 200,
        placeholder: 'z. B. Alle Preise inkl. MwSt.',
    )]
    public string $note = '';
}

<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module;

use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\{Collection, Text, Textarea};
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;
use FriendsOfRedaxo\Fluid\Kit\Fluid\Module\Item\StatsItem;

#[AsModule('fluid.stats', 'Kennzahlen')]
#[AsBlock(
    icon: 'chart-bar',
    group: 'Inhalt',
    help: 'Nur belegbare Zahlen verwenden.',
    variants: [
        'row' => 'Große Zahlen in einer Reihe',
        'cards' => 'Karten',
        'split' => 'Überschrift links, Zahlen rechts',
    ],
    nestable: ['row', 'cards'],
)]
final class Stats extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half')]
    public string $title = '';

    #[Textarea('Einleitung (optional)', maxLength: 400, rows: 2)]
    public string $intro = '';

    #[Collection('Kennzahlen', item: StatsItem::class, itemLabel: 'Kennzahl', titleField: 'value', max: 6)]
    public array $items = [];
}

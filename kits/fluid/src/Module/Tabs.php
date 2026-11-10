<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module;

use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\{Collection, MediaPicker, RichText, Text, Textarea};
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;
use FriendsOfRedaxo\Fluid\Kit\Fluid\Module\Item\TabsItem;

#[AsModule('fluid.tabs', 'Reiter (Tabs)')]
#[AsBlock(
    icon: 'list-bullets',
    group: 'Inhalt',
    help: 'Mehrere Inhalte auf engem Raum, umschaltbar über Reiter. „Reiter links“ stellt sich bei wenig Platz automatisch oben an. Ohne JavaScript stehen alle Inhalte untereinander.',
    variants: ['top' => 'Reiter oben', 'side' => 'Reiter links'],
)]
final class Tabs extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half')]
    public string $title = '';

    #[Textarea('Einleitung (optional)', maxLength: 400, rows: 2)]
    public string $intro = '';

    #[Collection('Reiter', item: TabsItem::class, itemLabel: 'Reiter', titleField: 'label', max: 8)]
    public array $items = [];
}

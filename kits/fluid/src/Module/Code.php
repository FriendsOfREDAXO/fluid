<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module;

use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\{Choice, Collection, Text, Textarea};
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;
use FriendsOfRedaxo\Fluid\Kit\Fluid\Module\Item\CodeItem;

#[AsModule('fluid.code', 'Code')]
#[AsBlock(
    icon: 'code',
    group: 'Inhalt',
    help: 'Codebeispiele zum Kopieren – eine oder mehrere Dateien untereinander (z. B. Klasse und View). Der Code wird so ausgegeben, wie er eingegeben ist.',
    nestable: true,
)]
final class Code extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half')]
    public string $title = '';

    #[Textarea('Einleitung (optional)', maxLength: 400, rows: 2)]
    public string $intro = '';

    #[Collection('Dateien', item: CodeItem::class, itemLabel: 'Datei', titleField: 'file', min: 1, max: 6)]
    public array $items = [];

    #[Text('Hinweis unter dem Code (optional)', maxLength: 240)]
    public string $note = '';
}

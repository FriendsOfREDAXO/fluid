<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module;

use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\{Number, Text, Textarea};
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.search', 'Suche')]
#[AsBlock(
    icon: 'magnifying-glass',
    group: 'Website',
    help: 'Suchfeld mit Treffern der ganzen Website (Tippfehler-tolerant, ohne Cookies). Die Seite mit diesem Block unter „Website“ → Kopf eintragen – dann erscheint im Kopf eine Lupe.',
    reload: true,
)]
final class Search extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half')]
    public string $title = '';

    #[Textarea('Einleitung (optional)', maxLength: 400, rows: 2)]
    public string $intro = '';

    #[Text('Platzhalter im Suchfeld', maxLength: 60, placeholder: 'Wonach suchen Sie?', width: 'half')]
    public string $placeholder = '';

    #[Number('Höchstens Treffer', width: 'half')]
    public int $limit = 20;

    #[Text('Hinweis ohne Treffer (optional)', maxLength: 200, placeholder: 'z. B. Rufen Sie uns gern an.')]
    public string $emptyText = '';
}

<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module;

use FriendsOfRedaxo\Fluid\Kit;
use FriendsOfRedaxo\Fluid\LayoutBlock;
use Override;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.layout', 'Layout (Spalten)')]
final class Layout extends LayoutBlock
{
    #[Override]
    public function kit(): Kit
    {
        return Kit::active();
    }
}

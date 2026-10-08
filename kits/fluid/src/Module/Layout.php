<?php

namespace FriendsOfRedaxo\Blocks\Kit\Fluid\Module;

use FriendsOfRedaxo\Blocks\Kit;
use FriendsOfRedaxo\Blocks\Kit\Fluid\FluidKit;
use FriendsOfRedaxo\Blocks\LayoutBlock;
use Override;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.layout', 'Layout (Spalten)')]
final class Layout extends LayoutBlock
{
    #[Override]
    public function kit(): Kit
    {
        return FluidKit::instance();
    }
}

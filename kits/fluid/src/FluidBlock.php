<?php

namespace FriendsOfRedaxo\Blocks\Kit\Fluid;

use FriendsOfRedaxo\Blocks\BlockModule;
use FriendsOfRedaxo\Blocks\Kit;
use Override;

/** Basis der Module des Kits „fluid“ – Felder und Ausgabe kommen aus kit.php und views/{typ}.php. */
abstract class FluidBlock extends BlockModule
{
    #[Override]
    public function kit(): Kit
    {
        return FluidKit::instance();
    }
}

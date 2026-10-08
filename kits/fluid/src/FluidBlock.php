<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid;

use FriendsOfRedaxo\Fluid\BlockModule;
use FriendsOfRedaxo\Fluid\Kit;
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

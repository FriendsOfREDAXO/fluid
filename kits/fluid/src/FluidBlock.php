<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid;

use FriendsOfRedaxo\Fluid\BlockModule;
use FriendsOfRedaxo\Fluid\Kit;
use Override;

/**
 * Basis der Block-Module von fluid – Felder und Ausgabe kommen aus dem gewählten Kit (kit.php, views/{typ}.php).
 * Die Module gehören zu keinem bestimmten Kit: Beim Wechsel des Kits bleiben die Inhalte, nur die Gestaltung ändert sich.
 */
abstract class FluidBlock extends BlockModule
{
    #[Override]
    public function kit(): Kit
    {
        return Kit::active();
    }
}

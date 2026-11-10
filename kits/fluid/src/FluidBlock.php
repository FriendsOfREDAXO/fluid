<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid;

use FriendsOfRedaxo\Fluid\BlockModule;
use FriendsOfRedaxo\Fluid\Kit;
use Override;

/**
 * Basis der Block-Module von fluid – Felder als Attribute an der Klasse, Ausgabe aus dem gewählten Kit (views/{typ}.php).
 * Die Module gehören zu keinem bestimmten Kit: Beim Wechsel des Kits bleiben die Inhalte, nur die Gestaltung ändert sich.
 */
abstract class FluidBlock extends BlockModule
{
    /** Bildformate (#[Choice(…, choices: self::RATIOS)]) */
    public const array RATIOS = ['16:9' => 'Breitbild 16:9', '3:2' => 'Querformat 3:2', '4:3' => 'Querformat 4:3', '1:1' => 'Quadrat 1:1', '4:5' => 'Hochformat 4:5', '3:4' => 'Hochformat 3:4'];

    /** Mindestbreite je Eintrag (breakpointlos: das Raster füllt die Zeile selbst) */
    public const array SIZES = ['s' => 'Schmal (viele nebeneinander)', 'm' => 'Mittel', 'l' => 'Breit (wenige nebeneinander)'];

    #[Override]
    public static function kit(): Kit
    {
        return Kit::active();
    }
}

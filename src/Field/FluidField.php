<?php

namespace FriendsOfRedaxo\Fluid\Field;

/**
 * Feld mit Ergänzungen von fluid (Breite, Darstellungen, Platzhalter … bzw. eigener Feldtyp). Die Felder des Cores
 * funktionieren in Blöcken genauso – nur ohne diese Ergänzungen.
 */
interface FluidField
{
    /**
     * Ergänzungen für das Formular von fluid (Format von Form\FieldForm), z. B. ['width' => 'half', 'variants' => [...]];
     * 'type' nur bei Feldtypen, die es nur in fluid gibt.
     *
     * @return array<string, mixed>
     */
    public function fluid(): array;
}

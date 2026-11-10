<?php

namespace FriendsOfRedaxo\Fluid\Legal;

/**
 * Einstellungen der Rechtstexte, gesetzt vom Projekt (wie die Policies des Cores) – in Project::configure():
 *
 *     Legal::$config = new LegalConfig(dialog: true);
 *
 * Ohne Angabe ist der Dialog aus: Links auf Rechtstexte bleiben gewöhnliche Links.
 */
final readonly class LegalConfig
{
    /**
     * @param bool $dialog Datenschutzhinweise an Formularen im Dialog über der Seite öffnen (Eingaben bleiben erhalten)
     */
    public function __construct(
        public bool $dialog = false,
    ) {}
}

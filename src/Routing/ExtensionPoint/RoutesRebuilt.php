<?php

namespace FriendsOfRedaxo\Fluid\Routing\ExtensionPoint;

use Redaxo\Core\ExtensionPoint\ExtensionPoint;

/**
 * Adressen wurden neu berechnet (Seiten nach einer Strukturänderung bzw. eine Quelle) – z. B. Suchindex neu.
 *
 * @extends ExtensionPoint<null>
 */
final class RoutesRebuilt extends ExtensionPoint
{
    /** @param string|null $source Kennung der Quelle; null = Seiten (und alle Quellen) */
    public function __construct(
        public readonly ?string $source = null,
    ) {
        parent::__construct(self::class, null, [], true);
    }
}

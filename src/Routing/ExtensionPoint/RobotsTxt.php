<?php

namespace FriendsOfRedaxo\Fluid\Routing\ExtensionPoint;

use Redaxo\Core\ExtensionPoint\ExtensionPoint;

/**
 * robots.txt: $rules sind die Regeln (Subject beim ersten Aufruf mit $part = 'rules' – ersetzen, z. B. eigener Text),
 * danach der fertige Text inklusive Sitemap-Zeile ($part = 'text' – ergänzen).
 *
 * @extends ExtensionPoint<string>
 */
final class RobotsTxt extends ExtensionPoint
{
    /** @param 'rules'|'text' $part */
    public function __construct(
        string $text,
        public readonly string $part = 'text',
    ) {
        parent::__construct(self::class, $text);
    }
}

<?php

namespace FriendsOfRedaxo\Fluid\Routing;

use Redaxo\Core\Core;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;

/**
 * robots.txt: Regeln plus Zeile „Sitemap:“ (immer).
 *   EP FLUID_ROBOTS_RULES  Regeln ersetzen (z. B. eigener Text aus den Website-Einstellungen von fluid)
 *   EP FLUID_ROBOTS        fertiger Text inklusive Sitemap-Zeile – Regeln ergänzen
 */
final class Robots
{
    public const string DEFAULT = "User-agent: *\nDisallow: /redaxo/\nDisallow: /*?rex_version=";

    public static function text(): string
    {
        $rules = trim((string) Extension::dispatch(new ExtensionPoint('FLUID_ROBOTS_RULES', self::DEFAULT)));
        $r = Core::getRequest();

        return (string) Extension::dispatch(new ExtensionPoint('FLUID_ROBOTS',
            $rules . "\n\nSitemap: " . $r->getSchemeAndHttpHost() . Router::base() . "/sitemap.xml\n"));
    }
}

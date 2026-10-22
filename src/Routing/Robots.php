<?php

namespace FriendsOfRedaxo\Fluid\Routing;

use Redaxo\Core\Core;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;

/**
 * robots.txt: Regeln plus Zeile „Sitemap:“ (immer).
 * Sperren die Regeln alles (`Disallow: /` für alle), dürfen Linkvorschauen beim Teilen trotzdem lesen (PREVIEW_BOTS) –
 * Facebook, LinkedIn & Co. halten sich an die robots.txt und zeigten sonst nur ein graues Bild. Suchmaschinen bleiben draußen.
 *   EP FLUID_ROBOTS_RULES  Regeln ersetzen (z. B. eigener Text aus den Website-Einstellungen von fluid)
 *   EP FLUID_ROBOTS        fertiger Text inklusive Sitemap-Zeile – Regeln ergänzen
 */
final class Robots
{
    public const string DEFAULT = "User-agent: *\nDisallow: /redaxo/\nDisallow: /*?rex_version=";

    /** Linkvorschau beim Teilen (Bild, Titel, Text) – lesen nur, was jemand teilt; keine Suchmaschinen */
    public const array PREVIEW_BOTS = ['facebookexternalhit', 'Facebot', 'LinkedInBot', 'Twitterbot', 'Slackbot-LinkExpanding', 'Slackbot',
        'WhatsApp', 'TelegramBot', 'Discordbot', 'Mastodon', 'Pinterestbot', 'SkypeUriPreview'];

    public static function text(): string
    {
        $rules = trim((string) Extension::dispatch(new ExtensionPoint('FLUID_ROBOTS_RULES', self::DEFAULT)));
        if (self::locked($rules)) {
            $rules .= "\n\n# Linkvorschau beim Teilen (keine Suchmaschinen)\nUser-agent: " . implode("\nUser-agent: ", self::PREVIEW_BOTS)
                . "\nDisallow: " . Router::base() . "/redaxo/\nAllow: /";
        }
        $r = Core::getRequest();

        return (string) Extension::dispatch(new ExtensionPoint('FLUID_ROBOTS',
            $rules . "\n\nSitemap: " . $r->getSchemeAndHttpHost() . Router::base() . "/sitemap.xml\n"));
    }

    /** Sperren die Regeln für alle Bots die ganze Website – und erwähnen keinen Vorschau-Bot? */
    private static function locked(string $rules): bool
    {
        if (1 !== preg_match('~^User-agent:\s*\*\s*\R(?:(?!User-agent).*\R)*?Disallow:\s*/\s*$~mi', $rules)) {
            return false;
        }
        foreach (self::PREVIEW_BOTS as $bot) {
            if (false !== stripos($rules, $bot)) {
                return false;   // eigene Regeln für Vorschau-Bots: nicht eingreifen
            }
        }

        return true;
    }
}

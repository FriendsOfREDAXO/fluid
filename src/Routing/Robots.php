<?php

namespace FriendsOfRedaxo\Fluid\Routing;

use Redaxo\Core\Core;
use Redaxo\Core\ExtensionPoint\Extension;
use FriendsOfRedaxo\Fluid\Routing\ExtensionPoint\RobotsTxt;

/**
 * robots.txt: Regeln plus Zeile „Sitemap:“ (immer).
 * Optional (allowPreviews(), Standard aus): Sperren die Regeln alles (`Disallow: /` für alle), dürfen Linkvorschauen beim
 * Teilen trotzdem lesen (PREVIEW_BOTS) – Facebook, LinkedIn & Co. halten sich an die robots.txt und zeigten sonst nur ein
 * graues Bild. Suchmaschinen bleiben draußen. Nur ausdrücklich, denn wer alles sperrt, meint das oft so (Test, Vorschau).
 *   Extension Point RobotsTxt, part 'rules'  Regeln ersetzen (z. B. eigener Text aus den Website-Einstellungen)
 *   Extension Point RobotsTxt, part 'text'   fertiger Text inklusive Sitemap-Zeile – Regeln ergänzen
 */
final class Robots
{
    public const string DEFAULT = "User-agent: *\nDisallow: /redaxo/\nDisallow: /*?rex_version=";

    /** Linkvorschau beim Teilen (Bild, Titel, Text) – lesen nur, was jemand teilt; keine Suchmaschinen */
    public const array PREVIEW_BOTS = ['facebookexternalhit', 'Facebot', 'LinkedInBot', 'Twitterbot', 'Slackbot-LinkExpanding', 'Slackbot',
        'WhatsApp', 'TelegramBot', 'Discordbot', 'Mastodon', 'Pinterestbot', 'SkypeUriPreview'];

    private static bool $previews = false;

    /** Linkvorschauen auch bei gesperrter Website erlauben (z. B. Live-Website vor dem Start) */
    public static function allowPreviews(bool $allow = true): void
    {
        self::$previews = $allow;
    }

    public static function text(): string
    {
        $rules = trim((string) Extension::dispatch(new RobotsTxt(self::DEFAULT, 'rules')));
        if (self::$previews && self::locked($rules)) {
            $rules .= "\n\n# Linkvorschau beim Teilen (keine Suchmaschinen)\nUser-agent: " . implode("\nUser-agent: ", self::PREVIEW_BOTS)
                . "\nDisallow: " . Router::base() . "/redaxo/\nAllow: /";
        }
        $r = Core::getRequest();

        return (string) Extension::dispatch(new RobotsTxt($rules . "\n\nSitemap: " . $r->getSchemeAndHttpHost() . Router::base() . "/sitemap.xml\n"));
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

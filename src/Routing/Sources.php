<?php

namespace FriendsOfRedaxo\Fluid\Routing;

use FriendsOfRedaxo\Fluid\Routing\Attribute\AsRouteSource;
use Redaxo\Core\ClassDiscovery;
use Throwable;

/**
 * Alle Quellen eigener Adressen: per #[AsRouteSource] gefunden (Klassenerkennung des Cores) und per add() angemeldet.
 */
final class Sources
{
    /** @var array<string, RouteSource>|null */
    private static ?array $all = null;

    /** @var list<RouteSource> */
    private static array $added = [];

    public static function add(RouteSource $source): void
    {
        self::$added[] = $source;
        self::$all = null;
    }

    /** @return array<string, RouteSource> Kennung => Quelle */
    public static function all(): array
    {
        if (null !== self::$all) {
            return self::$all;
        }
        $all = [];
        try {
            foreach (ClassDiscovery::getInstance()->discoverByAttribute(AsRouteSource::class, RouteSource::class) as $class => $_) {
                $s = new $class();
                $all[$s->key()] = $s;
            }
        } catch (Throwable) {
            // Klassenerkennung nicht verfügbar (Setup)
        }
        foreach (self::$added as $s) {
            $all[$s->key()] = $s;
        }

        return self::$all = $all;
    }

    public static function get(string $key): ?RouteSource
    {
        return self::all()[$key] ?? null;
    }
}

<?php

namespace FriendsOfRedaxo\Fluid\Routing;

/**
 * Einstellungen des Routings, gesetzt vom Projekt (wie die Policies des Cores) – in Project::configure():
 *
 *     Router::$config = new RoutingConfig(format: UrlFormat::html());
 *
 * Ohne Angabe gelten die Vorgaben (Adressen ohne Endung, Cache an).
 */
final readonly class RoutingConfig
{
    /**
     * @param UrlFormat $format Endung der Adressen (ohne, „/“, „.html“, gemischt, eigene); jede andere Schreibweise leitet per 301 um
     * @param bool $cache Adressen der Seiten und Quellen als PHP-Datei im Cache halten (sonst je Anfrage aus der Datenbank)
     * @param string $redirectBy Kopfzeile X-Redirect-By bei Weiterleitungen ('' = keine)
     * @param bool $enabled false = das Routing bleibt ganz außen vor (z. B. wenn yrewrite die Adressen übernimmt)
     */
    public function __construct(
        public UrlFormat $format = new UrlFormat(),
        public bool $cache = true,
        public string $redirectBy = 'REDAXO',
        public bool $enabled = true,
    ) {}
}

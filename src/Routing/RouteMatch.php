<?php

namespace FriendsOfRedaxo\Fluid\Routing;

use FriendsOfRedaxo\Fluid\Routing\Seo\PageMeta;

/**
 * Ergebnis der Auflösung einer Adresse, die keine Seite ist: welche Seite angezeigt wird, mit welchen Parametern – und
 * optional die kanonische Adresse (weicht die Anfrage ab, leitet der Router per 301 um), Meta-Angaben und Sprachfassungen.
 *
 * Gespeicherte Einträge der Quellen bekommen sie vom Router; dynamische Resolver (Router::resolver) geben sie zurück.
 */
final readonly class RouteMatch
{
    /**
     * @param array<string, scalar> $params Parameter für die Seite (Request und $_GET)
     * @param string|null $url kanonische Adresse (relativ wie Router::url oder absolut); null = keine Prüfung
     * @param bool $cacheRedirect 301 im Browser zwischenspeichern – nicht bei Adressen, die sich ändern können (Titel)
     * @param array<int, string> $alternates Sprach-ID => Adresse derselben Sache in anderen Sprachen (hreflang)
     */
    public function __construct(
        public int $articleId,
        public int $languageId,
        public array $params = [],
        public ?string $url = null,
        public bool $cacheRedirect = true,
        public ?PageMeta $meta = null,
        public array $alternates = [],
        public ?RouteEntry $entry = null,
    ) {}
}

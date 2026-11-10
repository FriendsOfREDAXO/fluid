<?php

namespace FriendsOfRedaxo\Fluid\Routing\Seo;

/**
 * Einstellungen für den Seitenkopf, gesetzt vom Projekt in Project::configure():
 *
 *     Head::$config = new HeadConfig(siteName: 'Muster GmbH', tags: [HeadTag::meta('theme-color', '#0a5')]);
 */
final readonly class HeadConfig
{
    /**
     * @param string|null $siteName Name der Website (og:site_name, Titel); null = Name der Instanz bzw. was ein AddOn setzt
     * @param list<HeadTag> $tags eigene Tags auf jeder Seite (gleicher Schlüssel ersetzt die Angabe des Routings)
     * @param string|null $twitterCard Karte für X/Twitter: mit Bild „summary_large_image“, sonst „summary“; null = kein Tag
     *     (die übrigen twitter:*-Angaben liest X aus og:*)
     * @param string $titleSeparator zwischen Seitentitel und Name der Website
     */
    public function __construct(
        public ?string $siteName = null,
        public array $tags = [],
        public ?string $twitterCard = 'summary_large_image',
        public string $titleSeparator = ' – ',
    ) {}
}

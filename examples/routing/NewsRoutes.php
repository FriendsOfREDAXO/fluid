<?php

namespace Project\Routing;

use FriendsOfRedaxo\Fluid\Routing\Attribute\AsRouteSource;
use FriendsOfRedaxo\Fluid\Routing\Attribute\Description;
use FriendsOfRedaxo\Fluid\Routing\Attribute\FromTable;
use FriendsOfRedaxo\Fluid\Routing\Attribute\Image;
use FriendsOfRedaxo\Fluid\Routing\Attribute\Modified;
use FriendsOfRedaxo\Fluid\Routing\Attribute\Slug;
use FriendsOfRedaxo\Fluid\Routing\Attribute\Title;
use FriendsOfRedaxo\Fluid\Routing\TableSource;

/**
 * News aus einer Tabelle unter der Seite „Aktuelles“ (ID 12): /aktuelles/sommerfest-im-garten
 *
 * Die Seite bekommt die ID als ?news_id=… und gibt den Eintrag aus. Adresse, Sitemap, Canonical, hreflang, Open Graph
 * und die 301-Weiterleitung nach einer Titeländerung kommen vom Routing. Titel, Beschreibung, Bild und Adresse lassen
 * sich in fluid in den Seiteneinstellungen der Detailseite bearbeiten.
 */
#[AsRouteSource('news', label: 'News')]
#[FromTable('rex_news', page: 12, where: 'status = 1', param: 'news_id')]
#[Slug('title')]
#[Title('seo_title')]
#[Description('teaser')]
#[Image('image')]
#[Modified('updatedate')]
final class NewsRoutes extends TableSource {}

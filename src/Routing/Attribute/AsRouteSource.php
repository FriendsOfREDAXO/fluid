<?php

namespace FriendsOfRedaxo\Fluid\Routing\Attribute;

use Attribute;

/**
 * Kennzeichnet eine Quelle eigener Adressen ({@see \FriendsOfRedaxo\Fluid\Routing\RouteSource}) – gefunden über die
 * Klassenerkennung des Cores, wie #[AsModule] oder #[AsMetaSchema].
 *
 *     #[AsRouteSource('news', label: 'News')]
 *     #[FromTable('rex_news', page: 12, where: 'status = 1')]
 *     #[Slug('title', withId: true)]
 *     #[Title('title')] #[Description('teaser')] #[Image('image')] #[Modified('updatedate')]
 *     final class NewsRoutes extends TableSource {}
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsRouteSource
{
    /**
     * @param non-empty-string $key Kennung der Quelle (Kleinbuchstaben, Ziffern, „-“, „_“, „:“), z. B. 'news'
     */
    public function __construct(
        public string $key,
        public ?string $label = null,
    ) {}
}

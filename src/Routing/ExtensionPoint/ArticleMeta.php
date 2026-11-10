<?php

namespace FriendsOfRedaxo\Fluid\Routing\ExtensionPoint;

use FriendsOfRedaxo\Fluid\Routing\RouteMatch;
use FriendsOfRedaxo\Fluid\Routing\Seo\PageMeta;
use Redaxo\Core\Content\Article;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;

/**
 * Meta-Angaben der Seite, bevor der Eintrag einer Quelle und Overrides dazukommen – z. B. SEO-Felder der Seite eintragen. Das Objekt im Subject direkt ändern
 * ($ep->subject->description = …) oder ein neues zurückgeben.
 *
 * @extends ExtensionPoint<PageMeta>
 */
final class ArticleMeta extends ExtensionPoint
{
    /** @param RouteMatch|null $match Eintrag einer Quelle bzw. eines Resolvers, wenn die Adresse keine Seite ist */
    public function __construct(
        PageMeta $meta,
        public readonly Article $article,
        public readonly ?RouteMatch $match = null,
    ) {
        parent::__construct(self::class, $meta);
    }
}

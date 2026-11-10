<?php

namespace FriendsOfRedaxo\Fluid\Routing\Seo;

/**
 * Meta-Angaben einer Seite bzw. eines Eintrags – daraus entsteht der Seitenkopf ({@see Head::render()}).
 *
 * Reihenfolge: Seite (Name, Adresse, Sprachen) → ArticleMeta (z. B. SEO-Felder der Seite) → Eintrag einer Quelle bzw.
 * eines Resolvers → Head::override() (Blöcke) → PageMetaResolved (letzte Änderungen). Leere Werte überschreiben nie.
 */
final class PageMeta
{
    /** @var array<string, HeadTag> */
    public array $tags = [];

    /**
     * @param string|null $title Titel der Seite (og:title); der Titel im Browser-Tab ist Titel + Name der Website
     * @param string|null $documentTitle eigener, vollständiger Titel im Browser-Tab (ersetzt Titel + Name der Website)
     * @param string|null $image Bild: Datei des Medienpools oder absolute Adresse
     * @param string $type og:type, z. B. website, article, product
     * @param string|null $url kanonische, absolute Adresse
     * @param array<string, string> $alternates hreflang => absolute Adresse (inkl. x-default)
     * @param list<array<string, mixed>> $jsonLd Knoten für den @graph (schema.org)
     */
    public function __construct(
        public ?string $title = null,
        public ?string $documentTitle = null,
        public ?string $siteName = null,
        public ?string $description = null,
        public ?string $image = null,
        public ?string $imageAlt = null,
        public ?int $imageWidth = null,
        public ?int $imageHeight = null,
        public ?string $imageType = null,
        public string $type = 'website',
        public ?string $url = null,
        public ?string $locale = null,
        public array $alternates = [],
        public ?bool $noindex = null,
        public ?bool $nofollow = null,
        public ?int $published = null,
        public ?int $modified = null,
        public array $jsonLd = [],
    ) {}

    /** Gesetzte Werte von $other übernehmen (leere Zeichenketten und null nicht); Tags und JSON-LD kommen dazu */
    public function merge(self $other): static
    {
        foreach (['title', 'documentTitle', 'siteName', 'description', 'image', 'imageAlt', 'imageWidth', 'imageHeight', 'imageType', 'url', 'locale', 'noindex', 'nofollow', 'published', 'modified'] as $k) {
            if (null !== $other->$k && '' !== $other->$k) {
                $this->$k = $other->$k;
            }
        }
        // neuer Titel ohne eigenen Tab-Titel: der alte Tab-Titel (z. B. SEO-Titel der Listenseite) passt nicht mehr
        if (null !== $other->title && '' !== $other->title && null === $other->documentTitle) {
            $this->documentTitle = null;
        }
        // neues Bild: Maße des alten gelten nicht mehr
        if (null !== $other->image && '' !== $other->image && null === $other->imageWidth) {
            $this->imageWidth = $this->imageHeight = null;
            $this->imageType = $other->imageType;
        }
        if ('website' !== $other->type) {
            $this->type = $other->type;
        }
        if ([] !== $other->alternates) {
            $this->alternates = $other->alternates;
        }
        $this->add(...array_values($other->tags));
        $this->jsonLd = [...$this->jsonLd, ...$other->jsonLd];

        return $this;
    }

    public function add(HeadTag ...$tags): static
    {
        foreach ($tags as $t) {
            unset($this->tags[$t->key]);   // neu ans Ende
            $this->tags[$t->key] = $t;
        }

        return $this;
    }
}

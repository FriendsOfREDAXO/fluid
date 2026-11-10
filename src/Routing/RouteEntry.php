<?php

namespace FriendsOfRedaxo\Fluid\Routing;

/**
 * Eine gespeicherte Adresse einer Quelle: ein Eintrag (Datensatz, Produkt …) in einer Sprache, angezeigt von einer Seite.
 *
 * Der Pfad entsteht beim Neuaufbau: Pfad der Seite + „/“ + Slug (eindeutig gemacht mit -2, -3 …). Ein Slug darf „/“
 * enthalten (z. B. „2026/titel“); die Teile werden wie Seitennamen bereinigt.
 */
final readonly class RouteEntry
{
    /**
     * @param string $source Kennung der Quelle
     * @param string $id ID des Eintrags in der Quelle
     * @param int $articleId Seite, unter der die Adresse liegt und die den Eintrag anzeigt
     * @param array<string, scalar> $params Parameter, die die Seite beim Aufruf bekommt (Request und $_GET)
     * @param int|null $modified Zeitpunkt der letzten Änderung (Sitemap)
     * @param bool $sitemap in die sitemap.xml aufnehmen
     * @param list<string> $images Bilder für die Sitemap (Dateien des Medienpools oder absolute Adressen)
     * @param string|null $path vom Router vergebener Pfad (ohne Sprachpräfix und Endung) – nur beim Lesen gesetzt
     */
    public function __construct(
        public string $source,
        public string $id,
        public int $languageId,
        public int $articleId,
        public string $slug,
        public array $params = [],
        public ?int $modified = null,
        public bool $sitemap = true,
        public array $images = [],
        public ?string $path = null,
    ) {}

    /** Schlüssel „quelle:id“ (z. B. für den Editor) */
    public function key(): string
    {
        return $this->source . ':' . $this->id;
    }
}

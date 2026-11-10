<?php

namespace FriendsOfRedaxo\Fluid\Routing;

use FriendsOfRedaxo\Fluid\Routing\Attribute\AsRouteSource;
use FriendsOfRedaxo\Fluid\Routing\Seo\PageMeta;
use LogicException;
use ReflectionClass;

use function sprintf;

/**
 * Quelle eigener Adressen: Einträge (Datensätze, Produkte, Termine …), die unter einer Seite eine eigene Adresse
 * bekommen. Der Router speichert die Pfade (eine indizierte Abfrage je Aufruf, Cache, eindeutige Slugs, 301 bei geänderter
 * Adresse), nimmt sie in die Sitemap auf (mit hreflang) und setzt Canonical, hreflang und Meta-Angaben des Eintrags.
 *
 * Mit #[AsRouteSource] gekennzeichnet findet der Core sie selbst (Klassenerkennung); sonst Sources::add(new …).
 * Für Tabellen genügen Attribute ({@see TableSource}); für alles andere entries() selbst schreiben:
 *
 *     #[AsRouteSource('events')]
 *     final class EventRoutes extends RouteSource
 *     {
 *         public function entries(): iterable
 *         {
 *             foreach (Event::all() as $e) {
 *                 yield new RouteEntry('events', (string) $e->id, $e->languageId, articleId: 7, slug: $e->title, params: ['event' => $e->id]);
 *             }
 *         }
 *     }
 *
 * Nach Änderungen: Routes::rebuildSource('events') (alle) bzw. Routes::put($entry) / Routes::remove('events', $id).
 * Adresse erzeugen: Router::urlFor('events', $id).
 */
abstract class RouteSource
{
    private ?AsRouteSource $attribute = null;

    /**
     * Alle Einträge – je Sprache einer. Die Quelle von RouteEntry muss key() sein.
     *
     * @return iterable<RouteEntry>
     */
    abstract public function entries(): iterable;

    public function key(): string
    {
        return $this->attribute()->key;
    }

    public function label(): string
    {
        return $this->attribute()->label ?? $this->key();
    }

    /** Gibt es den Eintrag noch (sichtbar)? Sonst 404 – auch wenn die gespeicherte Adresse noch nicht neu berechnet ist */
    public function exists(RouteEntry $entry): bool
    {
        return true;
    }

    /** Meta-Angaben des Eintrags (Titel, Beschreibung, Bild …); null = die der Seite */
    public function meta(RouteEntry $entry): ?PageMeta
    {
        return null;
    }

    /**
     * Felder, die ein Editor anbieten darf (fluid: Seiteneinstellungen der Detailseite). Leer = nur lesen.
     *
     * @return list<EditableField>
     */
    public function editable(RouteEntry $entry): array
    {
        return [];
    }

    /**
     * Werte der editierbaren Felder speichern (Name => Wert); danach die Adresse neu berechnen.
     *
     * @param array<string, string> $values
     */
    public function save(RouteEntry $entry, array $values): void
    {
        throw new LogicException(sprintf('Die Quelle „%s“ lässt sich nicht bearbeiten.', $this->key()));
    }

    private function attribute(): AsRouteSource
    {
        if (null === $this->attribute) {
            $attrs = (new ReflectionClass($this))->getAttributes(AsRouteSource::class);
            if (!$attrs) {
                throw new LogicException(sprintf('%s braucht #[AsRouteSource] oder eine eigene key()-Methode.', static::class));
            }
            $this->attribute = $attrs[0]->newInstance();
        }

        return $this->attribute;
    }
}

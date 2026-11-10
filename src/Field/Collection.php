<?php

namespace FriendsOfRedaxo\Fluid\Field;

use Attribute;
use Redaxo\Core\Form\Field\Collection as CoreCollection;
use Redaxo\Core\View\Renderable;

/**
 * Feld des Cores (`Collection`) mit den Ergänzungen von fluid – alle zusätzlichen Parameter sind optional, ohne sie
 * verhält es sich genau wie das Feld des Cores (siehe docs/entwickler.md, „Felder“).
 *
 * Jeder Eintrag ist ein Objekt der Klasse `item` mit eigenen Feldern (Eigenschaften mit Feld-Attributen). Gespeichert
 * wird eine Liste der Werte je Feldschlüssel – auf der Website bearbeitet fluid sie wie eine Wiederholgruppe.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Collection extends CoreCollection implements FluidField
{
    /**
     * @param string|null $itemLabel fluid: Bezeichnung eines Eintrags („+ Karte“)
     * @param string|null $titleField fluid: Feld (Schlüssel), das im zugeklappten Eintrag als Titel steht
     * @param 'half'|null $width fluid: halbe Breite im Formular
     * @param list<string>|null $variants fluid: nur in diesen Darstellungen des Blocks
     */
    public function __construct(
        string|Renderable $label,
        /** @var class-string */
        string $item,
        string|Renderable|null $note = null,
        bool $required = false,
        ?string $key = null,
        ?int $min = null,
        ?int $max = null,
        public readonly ?string $itemLabel = null,
        public readonly ?string $titleField = null,
        public readonly ?string $width = null,
        /** @var list<string>|null */
        public readonly ?array $variants = null,
    ) {
        parent::__construct($label, $item, $note, $required, $key, $min, $max);
    }

    public function fluid(): array
    {
        return array_filter(['width' => $this->width, 'variants' => $this->variants, 'item_label' => $this->itemLabel, 'title_field' => $this->titleField], static fn ($v) => null !== $v);
    }
}

<?php

namespace FriendsOfRedaxo\Fluid\Field;

use Attribute;
use Redaxo\Core\Form\Field\MediaPicker as CoreMediaPicker;
use Redaxo\Core\View\Renderable;

/**
 * Feld des Cores (`MediaPicker`) mit den Ergänzungen von fluid – alle zusätzlichen Parameter sind optional, ohne sie
 * verhält es sich genau wie das Feld des Cores (siehe docs/entwickler.md, „Felder“).
 *
 * In fluid: mit `preview: true` ein Bildfeld (Vorschau, Bildbearbeitung), sonst die Auswahl einer Datei.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class MediaPicker extends CoreMediaPicker implements FluidField
{
    /**
     * @param 'half'|null $width fluid: halbe Breite im Formular
     * @param list<string>|null $variants fluid: nur in diesen Darstellungen des Blocks
     */
    public function __construct(
        string|Renderable $label,
        bool $multiple = false,
        string|Renderable|null $note = null,
        bool $required = false,
        ?string $key = null,
        ?int $categoryId = null,
        /** @var list<string> */
        array $types = [],
        bool $preview = false,
        public readonly ?string $width = null,
        /** @var list<string>|null */
        public readonly ?array $variants = null,
    ) {
        parent::__construct($label, $multiple, $note, $required, $key, $categoryId, $types, $preview);
    }

    public function fluid(): array
    {
        return array_filter(['width' => $this->width, 'variants' => $this->variants], static fn ($v) => null !== $v);
    }
}

<?php

namespace FriendsOfRedaxo\Fluid\Field;

use Attribute;
use Redaxo\Core\Form\Field\Textarea as CoreTextarea;
use Redaxo\Core\Form\Reference;
use Redaxo\Core\Form\Field\ReferencesItems;
use Redaxo\Core\View\Renderable;

/**
 * Text mit Formatierung (Absätze, fett, kursiv, Listen, Links). Gespeichert wird bereinigtes HTML.
 *
 * Nur in fluid (eigener Name). In gewöhnlichen Formularen des Cores verhält es sich wie das Feld, von dem es erbt.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class RichText extends CoreTextarea implements FluidField, ReferencesItems
{
    /**
     * @param 'half'|null $width fluid: halbe Breite im Formular
     * @param list<string>|null $variants fluid: nur in diesen Darstellungen des Blocks
     */
    public function __construct(
        string|Renderable $label,
        string|Renderable|null $note = null,
        bool $required = false,
        ?string $key = null,
        public readonly ?string $width = null,
        /** @var list<string>|null */
        public readonly ?array $variants = null,
    ) {
        parent::__construct($label, $note, $required, $key, 8);
    }

    public function fluid(): array
    {
        return array_filter(['type' => 'richtext', 'width' => $this->width, 'variants' => $this->variants], static fn ($v) => null !== $v);
    }

    /** @return list<Reference> verlinkte Seiten (redaxo://ID) */
    public function references(mixed $value): array
    {
        return Links::references($value);
    }
}

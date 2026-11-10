<?php

namespace FriendsOfRedaxo\Fluid\Field;

use Attribute;
use Redaxo\Core\Form\Field\Email as CoreEmail;
use Redaxo\Core\View\Renderable;

/**
 * Feld des Cores (`Email`) mit den Ergänzungen von fluid – alle zusätzlichen Parameter sind optional, ohne sie
 * verhält es sich genau wie das Feld des Cores (siehe docs/entwickler.md, „Felder“).
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Email extends CoreEmail implements FluidField
{
    /**
     * @param 'half'|null $width fluid: halbe Breite im Formular
     * @param list<string>|null $variants fluid: nur in diesen Darstellungen des Blocks
     * @param string|null $placeholder fluid: Platzhalter im Eingabefeld
     */
    public function __construct(
        string|Renderable $label,
        string|Renderable|null $note = null,
        bool $required = false,
        ?string $key = null,
        ?int $maxLength = null,
        public readonly ?string $width = null,
        /** @var list<string>|null */
        public readonly ?array $variants = null,
        public readonly ?string $placeholder = null,
    ) {
        parent::__construct($label, $note, $required, $key, $maxLength);
    }

    public function fluid(): array
    {
        return array_filter(['width' => $this->width, 'variants' => $this->variants, 'placeholder' => $this->placeholder], static fn ($v) => null !== $v);
    }
}

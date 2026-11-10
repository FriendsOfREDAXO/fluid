<?php

namespace FriendsOfRedaxo\Fluid\Field;

use Attribute;
use Redaxo\Core\Form\Field\Choice as CoreChoice;
use Redaxo\Core\Form\ChoiceProvider;
use Redaxo\Core\Translation\Translatable;
use Closure;
use Redaxo\Core\View\Renderable;

/**
 * Feld des Cores (`Choice`) mit den Ergänzungen von fluid – alle zusätzlichen Parameter sind optional, ohne sie
 * verhält es sich genau wie das Feld des Cores (siehe docs/entwickler.md, „Felder“).
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Choice extends CoreChoice implements FluidField
{
    /**
     * @param 'half'|null $width fluid: halbe Breite im Formular
     * @param list<string>|null $variants fluid: nur in diesen Darstellungen des Blocks
     */
    public function __construct(
        string|Renderable $label,
        array|ChoiceProvider|Closure $choices,
        bool $multiple = false,
        bool $expanded = false,
        string|Translatable|null $placeholder = null,
        string|Renderable|null $note = null,
        bool $required = false,
        ?string $key = null,
        public readonly ?string $width = null,
        /** @var list<string>|null */
        public readonly ?array $variants = null,
    ) {
        parent::__construct($label, $choices, $multiple, $expanded, $placeholder, $note, $required, $key);
    }

    public function fluid(): array
    {
        return array_filter(['width' => $this->width, 'variants' => $this->variants], static fn ($v) => null !== $v);
    }
}

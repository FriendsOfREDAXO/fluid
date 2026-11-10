<?php

namespace FriendsOfRedaxo\Fluid\Field;

use Attribute;
use Redaxo\Core\Form\Field\ArticlePicker as CoreArticlePicker;
use Redaxo\Core\View\Renderable;

/**
 * Feld des Cores (`ArticlePicker`) mit den Ergänzungen von fluid – alle zusätzlichen Parameter sind optional, ohne sie
 * verhält es sich genau wie das Feld des Cores (siehe docs/entwickler.md, „Felder“).
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class ArticlePicker extends CoreArticlePicker implements FluidField
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
        public readonly ?string $width = null,
        /** @var list<string>|null */
        public readonly ?array $variants = null,
    ) {
        parent::__construct($label, $multiple, $note, $required, $key, $categoryId);
    }

    public function fluid(): array
    {
        return array_filter(['width' => $this->width, 'variants' => $this->variants], static fn ($v) => null !== $v);
    }

    /** Auch `redaxo://ID` (Linkmap von fluid) annehmen – sonst wie der Core */
    #[\Override]
    public function fromRaw(string|array|null $raw, \Redaxo\Core\Form\ValueType $type): array|\Redaxo\Core\Content\Article|null
    {
        $strip = static fn (mixed $v): mixed => \is_string($v) ? (string) preg_replace('~^redaxo://~', '', $v) : $v;

        return parent::fromRaw(\is_array($raw) ? array_map($strip, $raw) : $strip($raw), $type);
    }
}

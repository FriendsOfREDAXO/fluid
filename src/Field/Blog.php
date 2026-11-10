<?php

namespace FriendsOfRedaxo\Fluid\Field;

use Attribute;
use Redaxo\Core\Form\Field\Choice as CoreChoice;
use Redaxo\Core\View\Renderable;

/**
 * Blog (Kategorie mit „Blog / News“ in den Seiteneinstellungen); leer = alle Blogs.
 *
 * Nur in fluid (eigener Name). In gewöhnlichen Formularen des Cores verhält es sich wie das Feld, von dem es erbt.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Blog extends CoreChoice implements FluidField
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
        parent::__construct($label, static fn (): array => \FriendsOfRedaxo\Fluid\Blog\Blog::all(), placeholder: 'Alle Blogs', note: $note, required: $required, key: $key);
    }

    public function fluid(): array
    {
        return array_filter(['type' => 'blog', 'width' => $this->width, 'variants' => $this->variants], static fn ($v) => null !== $v);
    }
}

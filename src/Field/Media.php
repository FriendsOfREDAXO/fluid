<?php

namespace FriendsOfRedaxo\Fluid\Field;

use Attribute;

/** Bild aus dem Medienpool */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Media extends Field
{
    public function __construct(
        string $label,
        ?string $note = null,
        bool $required = false,
        ?string $key = null,
        public readonly ?array $types = null,
        ?string $width = null,
        ?array $variants = null,
        mixed $default = null,
    ) {
        parent::__construct($label, $note, $required, $key, $width, $variants, $default);
    }

    #[\Override]
    public function type(): string
    {
        return 'media';
    }

    #[\Override]
    protected function extra(): array
    {
        return [
            ...(null !== $this->types ? ['types' => $this->types] : []),
        ];
    }
}

<?php

namespace FriendsOfRedaxo\Fluid\Field;

use Attribute;

/** Adresse (https://…) */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Url extends Field
{
    public function __construct(
        string $label,
        ?string $note = null,
        bool $required = false,
        ?string $key = null,
        public readonly ?string $placeholder = null,
        ?string $width = null,
        ?array $variants = null,
        mixed $default = null,
    ) {
        parent::__construct($label, $note, $required, $key, $width, $variants, $default);
    }

    #[\Override]
    public function type(): string
    {
        return 'url';
    }

    #[\Override]
    protected function extra(): array
    {
        return [
            ...(null !== $this->placeholder ? ['placeholder' => $this->placeholder] : []),
        ];
    }
}

<?php

namespace FriendsOfRedaxo\Fluid\Field;

use Attribute;

/** Mehrzeiliger Text */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Textarea extends Field
{
    public function __construct(
        string $label,
        ?string $note = null,
        bool $required = false,
        ?string $key = null,
        public readonly ?int $rows = null,
        public readonly ?int $maxLength = null,
        public readonly ?string $placeholder = null,
        public readonly bool $search = true,
        ?string $width = null,
        ?array $variants = null,
        mixed $default = null,
    ) {
        parent::__construct($label, $note, $required, $key, $width, $variants, $default);
    }

    #[\Override]
    public function type(): string
    {
        return 'textarea';
    }

    #[\Override]
    protected function extra(): array
    {
        return [
            ...(null !== $this->rows ? ['rows' => $this->rows] : []),
            ...(null !== $this->maxLength ? ['max' => $this->maxLength] : []),
            ...(null !== $this->placeholder ? ['placeholder' => $this->placeholder] : []),
            ...($this->search ? [] : ['search' => false]),
        ];
    }
}

<?php

namespace FriendsOfRedaxo\Fluid\Field;

use Attribute;

/** Auswahl; choices als Liste oder [Klasse::class, 'methode'] (wird beim Lesen aufgerufen) */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Choice extends Field
{
    public function __construct(
        string $label,
        ?string $note = null,
        bool $required = false,
        ?string $key = null,
        public readonly array $choices = [],
        ?string $width = null,
        ?array $variants = null,
        mixed $default = null,
    ) {
        parent::__construct($label, $note, $required, $key, $width, $variants, $default);
    }

    #[\Override]
    public function type(): string
    {
        return 'select';
    }

    #[\Override]
    protected function extra(): array
    {
        return [
            'options' => self::resolve($this->choices),
        ];
    }

    /** @return array<array-key, string> */
    private static function resolve(array $choices): array
    {
        return 2 === count($choices) && array_is_list($choices) && is_string($choices[0]) && class_exists($choices[0]) && is_callable($choices)
            ? (array) $choices()
            : $choices;
    }
}

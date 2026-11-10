<?php

namespace FriendsOfRedaxo\Fluid\Field;

use Attribute;

/**
 * Wiederholgruppe: Liste von Einträgen mit eigenen Feldern.
 *
 *     #[Repeater('Karten', item: 'Karte', titleField: 'title', max: 12, fields: [
 *         new Media('Bild', key: 'image'),
 *         new Text('Titel', key: 'title', required: true),
 *     ])]
 *     public array $items = [];
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Repeater extends Field
{
    public function __construct(
        string $label,
        /** @var list<Field> Felder eines Eintrags (mit key:) */
        public readonly array $fields = [],
        /** Bezeichnung eines Eintrags („Karte hinzufügen“) */
        public readonly ?string $item = null,
        /** Feld, das im zugeklappten Eintrag als Titel steht (Standard: erstes Feld) */
        public readonly ?string $titleField = null,
        public readonly ?int $min = null,
        public readonly ?int $max = null,
        ?string $note = null,
        bool $required = false,
        ?string $key = null,
        ?string $width = null,
        ?array $variants = null,
        mixed $default = null,
    ) {
        parent::__construct($label, $note, $required, $key, $width, $variants, $default);
    }

    #[\Override]
    public function type(): string
    {
        return 'repeater';
    }

    #[\Override]
    protected function extra(): array
    {
        return [
            ...(null !== $this->item ? ['item_label' => $this->item] : []),
            ...(null !== $this->titleField ? ['title_field' => $this->titleField] : []),
            ...(null !== $this->min ? ['min_items' => $this->min] : []),
            ...(null !== $this->max ? ['max_items' => $this->max] : []),
            'fields' => array_map(static fn (Field $f) => $f->schema(), $this->fields),
        ];
    }
}

<?php

namespace FriendsOfRedaxo\Fluid\Field;


use function preg_replace;
use function strtolower;

/**
 * Feld eines Blocks als Attribut an einer Eigenschaft der Blockklasse – Namen und Parameter wie die Feld-Attribute des
 * Cores (label, note, required, key). Der Schlüssel ist der Name der Eigenschaft in snake_case (`$buttonLink` →
 * `button_link`), der Standardwert der Vorgabewert der Eigenschaft. In Wiederholgruppen (#[Repeater]) stehen Felder
 * ohne Eigenschaft: `new Text('Titel', key: 'title', default: '…')`.
 *
 * Zusätzlich zum Core: `width: 'half'` (halbe Breite im Formular), `variants: ['…']` (nur in diesen Varianten des
 * Blocks), `default:` (Standardwert ohne Eigenschaft).
 *
 * Gespeichert wird weiter in `values` des Slices; im Formular, im Editor und in der View gilt das Schema aus
 * {@see self::schema()}.
 */
abstract class Field
{
    public function __construct(
        public readonly string $label,
        public readonly ?string $note = null,
        public readonly bool $required = false,
        public readonly ?string $key = null,
        public readonly ?string $width = null,
        /** @var list<string>|null */
        public readonly ?array $variants = null,
        public readonly mixed $default = null,
    ) {}

    /** Feldtyp im Schema (text, select, media …) */
    abstract public function type(): string;

    /**
     * Schema des Felds (Format von FieldForm).
     *
     * @return array<string, mixed>
     */
    final public function schema(?string $property = null, mixed $default = null): array
    {
        $name = $this->key ?? (null !== $property ? self::snake($property) : null);
        if (null === $name) {
            throw new \LogicException('Feld „' . $this->label . '“ ohne Eigenschaft braucht key:');
        }
        $s = ['name' => $name, 'label' => $this->label, 'type' => $this->type()];
        if ($this->required) {
            $s['required'] = true;
        }
        if (null !== $this->note) {
            $s['help'] = $this->note;
        }
        if (null !== $this->width) {
            $s['width'] = $this->width;
        }
        if (null !== $this->variants) {
            $s['variants'] = $this->variants;
        }
        $s += $this->extra();
        $default = $this->default ?? $default;
        if (null !== $default && '' !== $default && [] !== $default && !(false === $default && 'bool' === $this->type())) {
            $s['default'] = $default;
        }

        return $s;
    }

    /**
     * Weitere Angaben des Feldtyps im Schema
     *
     * @return array<string, mixed>
     */
    protected function extra(): array
    {
        return [];
    }

    public static function snake(string $property): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $property));
    }
}

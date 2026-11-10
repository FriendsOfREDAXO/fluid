<?php

namespace FriendsOfRedaxo\Fluid\Form;

use Attribute;
use Override;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Form\BoundField;
use Redaxo\Core\Form\Field\Field;
use Redaxo\Core\Form\InvalidValueException;
use Redaxo\Core\Form\ValueType;
use Redaxo\Core\Form\Widget\Widget;
use Redaxo\Core\Translation\Translatable;

use function array_filter;
use function array_keys;
use function array_values;
use function is_array;
use function is_int;

/**
 * Interne Werte eines Blocks, die kein Feld im Formular sind: Darstellung (`variant`), Abschnitts-Optionen (`tunes`) und
 * gezogene Spaltenbreiten des Layouts (`widths`). Gespeichert wie jedes Feld des Cores unter seinem Schlüssel in der
 * JSON-Spalte `data` des Slices; das Formular zeichnet fluid (Form\FieldForm).
 *
 * @internal
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Stored extends Field
{
    public function __construct(?string $key = null)
    {
        parent::__construct('', key: $key);
    }

    #[Override]
    public function supports(ValueType $type): bool
    {
        return $type->isAnyOf('array', 'string');
    }

    #[Override]
    public function widget(BoundField $field): Widget
    {
        throw new LogicException('Stored values are rendered by Form\\FieldForm.');
    }

    /** @return string|array<array-key, mixed> */
    #[Override]
    public function toRaw(mixed $value): string|array
    {
        return is_array($value) ? $value : (string) $value;
    }

    /** @return string|array<array-key, mixed> */
    #[Override]
    public function fromRaw(string|array|null $raw, ValueType $type): string|array
    {
        if (is_array($raw)) {
            return self::clean($raw);
        }

        return $type->isAnyOf('array') ? [] : (string) $raw;
    }

    /** @return string|array<array-key, mixed> */
    #[Override]
    public function encode(mixed $value): string|array
    {
        return is_array($value) ? $value : (string) $value;
    }

    /** @return string|array<array-key, mixed> */
    #[Override]
    public function decode(mixed $stored, ValueType $type): string|array
    {
        if ($type->isAnyOf('array')) {
            if (!is_array($stored)) {
                throw new InvalidValueException(new Translatable('form_invalid_value'));
            }

            return $stored;
        }

        return \is_scalar($stored) ? (string) $stored : '';
    }

    /**
     * Formularwerte aufräumen: durchnummerierte Listen (Wiederholgruppen) neu zählen.
     *
     * @param array<array-key, mixed> $a
     * @return array<array-key, mixed>
     */
    public static function clean(array $a): array
    {
        foreach ($a as $k => $v) {
            if (is_array($v)) {
                $v = self::clean($v);
                $a[$k] = [] !== $v && array_filter(array_keys($v), is_int(...)) === array_keys($v) ? array_values($v) : $v;
            }
        }

        return $a;
    }
}

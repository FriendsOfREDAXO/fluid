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
 * Feldtyp der Block-Module: speichert die Werte eines Blocks (Felder laut Kit-Definition oder Abschnitts-Optionen) als
 * verschachteltes Array in der JSON-Spalte `data` des Slices.
 *
 * Die Felder eines Blocks stehen nicht als Eigenschaften im Modul, sondern in der Kit-Definition – deshalb ein Feld für
 * alle Werte statt eines Felds je Wert. Das Formular zeichnet BlockModule::input() (FieldForm), nicht das Feld selbst.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class BlockValues extends Field
{
    public function __construct(?string $key = null)
    {
        parent::__construct('', key: $key);
    }

    #[Override]
    public function supports(ValueType $type): bool
    {
        return $type->isAnyOf('array');
    }

    #[Override]
    public function widget(BoundField $field): Widget
    {
        throw new LogicException('Block values are rendered by BlockModule::input().');
    }

    /** @return array<array-key, mixed> */
    #[Override]
    public function toRaw(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /** @return array<array-key, mixed> */
    #[Override]
    public function fromRaw(string|array|null $raw, ValueType $type): array
    {
        if (null === $raw || '' === $raw) {
            return [];
        }
        if (!is_array($raw)) {
            throw new InvalidValueException(new Translatable('form_invalid_value'));
        }

        return self::clean($raw);
    }

    /** @return array<array-key, mixed> */
    #[Override]
    public function encode(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /** @return array<array-key, mixed> */
    #[Override]
    public function decode(mixed $stored, ValueType $type): array
    {
        if (!is_array($stored)) {
            throw new InvalidValueException(new Translatable('form_invalid_value'));
        }

        return $stored;
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

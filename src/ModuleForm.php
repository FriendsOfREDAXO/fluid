<?php

namespace FriendsOfRedaxo\Fluid;

use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use FriendsOfRedaxo\Fluid\Form\FieldForm;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Content\ContentHandler;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Form\Field\ArticlePicker;
use Redaxo\Core\Form\Field\Checkbox;
use Redaxo\Core\Form\Field\Choice;
use Redaxo\Core\Form\Field\DatePicker;
use Redaxo\Core\Form\Field\Email;
use Redaxo\Core\Form\Field\Field;
use Redaxo\Core\Form\Field\MediaPicker;
use Redaxo\Core\Form\Field\Number;
use Redaxo\Core\Form\Field\Text;
use Redaxo\Core\Form\Field\Textarea;
use Redaxo\Core\Form\Field\TimePicker;
use Redaxo\Core\Form\Field\Url;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\Translation\Translatable;
use Throwable;

use function in_array;
use function is_a;
use function is_string;
use function mb_strlen;
use function preg_replace;
use function strtolower;
use function trim;

/**
 * Gewöhnliche REDAXO-Module (keine fluid-Blöcke) in der Seitenleiste der Website – im Formular von fluid.
 *
 * Nur öffentliche API des Cores: die Feld-Attribute der Modulklasse (#[Text], #[MediaPicker] …) per Reflection,
 * `Module::of()` für die aktuellen Werte, `Field::toRaw()`/`encode()`, `Module::validate()` und
 * `ContentHandler::editSlice()`. Die Umwandlung der Eingaben in die Typen der Eigenschaften übernimmt diese Klasse für
 * die üblichen Feldtypen. Module mit eigenem `input()` oder anderen Feldtypen (z. B. Collection, Mehrfachauswahl)
 * öffnen weiter das Formular des Cores im Rahmen (null von {@see self::fields()}).
 */
final class ModuleForm
{
    /**
     * Felder der Modulklasse im Schema von fluid – null, wenn das Modul nicht vollständig abgebildet werden kann.
     *
     * @param class-string<Module> $class
     * @return list<array{name: string, prop: string, kind: string, field: Field, schema: array<string, mixed>}>|null
     */
    public static function fields(string $class): ?array
    {
        // eigenes input(): Aufbau des Formulars steht im Code des Moduls – nur der Core kann es zeigen
        if (Module::class !== new ReflectionMethod($class, 'input')->getDeclaringClass()->getName()) {
            return null;
        }
        $out = [];
        foreach (new \ReflectionClass($class)->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            if ($prop->isStatic()) {
                continue;
            }
            $attr = $prop->getAttributes(Field::class, \ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;
            if (null === $attr) {
                continue;
            }
            /** @var Field $field */
            $field = $attr->newInstance();
            $label = self::text($field->label);
            $key = $field->key ?? strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $prop->name));
            $base = ['name' => $key, 'label' => $label, 'required' => $field->required, 'help' => null === $field->note ? '' : self::text($field->note)];
            [$kind, $schema] = match (true) {
                $field instanceof Email => ['text', ['type' => 'text', 'placeholder' => 'name@example.org']],
                $field instanceof Url => ['text', ['type' => 'url']],
                $field instanceof Text => ['text', ['type' => 'text'] + (null !== $field->maxLength ? ['max' => $field->maxLength] : [])],
                $field instanceof Textarea => ['text', ['type' => 'textarea', 'rows' => $field->rows] + (null !== $field->maxLength ? ['max' => $field->maxLength] : [])],
                $field instanceof Number => ['number', ['type' => 'number']],
                $field instanceof Checkbox => ['bool', ['type' => 'bool']],
                $field instanceof Choice && !$field->multiple => ['choice', ['type' => 'select', 'options' => self::options($field)]],
                $field instanceof DatePicker => ['date', ['type' => 'date']],
                $field instanceof TimePicker => ['time', ['type' => 'time']],
                $field instanceof MediaPicker && !$field->multiple => ['media', ['type' => 'media'] + ([] !== $field->types ? ['types' => $field->types] : [])],
                $field instanceof ArticlePicker && !$field->multiple => ['article', ['type' => 'link']],
                default => [null, []],
            };
            if (null === $kind) {
                return null;
            }
            $out[] = ['name' => $key, 'prop' => $prop->name, 'kind' => $kind, 'field' => $field, 'schema' => $schema + $base];
        }

        return $out;
    }

    /** API „module_form“: Formular in der Seitenleiste oder null (dann Formular des Cores im Rahmen) */
    public static function form(ArticleSlice $slice): ?string
    {
        $class = Module::getClass($slice->moduleKey);
        $fields = null !== $class ? self::fields($class) : null;
        if (null === $fields) {
            return null;
        }
        $module = Module::of($slice);
        $values = [];
        foreach ($fields as $f) {
            $raw = $f['field']->toRaw($module->{$f['prop']});
            $raw = is_string($raw) ? $raw : '';
            $values[$f['name']] = match ($f['kind']) {
                'bool' => '' !== $raw && '0' !== $raw,
                'article' => '' !== $raw ? 'redaxo://' . $raw : '',
                default => $raw,
            };
        }

        return FieldForm::standalone(array_map(static fn (array $f) => $f['schema'], $fields), $values, 'm');
    }

    /**
     * API „module_save“: Eingaben prüfen, umwandeln und speichern.
     *
     * @param array<string, mixed> $input
     */
    public static function save(ArticleSlice $slice, array $input): void
    {
        $class = Module::getClass($slice->moduleKey);
        $fields = null !== $class ? self::fields($class) : null;
        if (null === $fields) {
            throw new ApiError('Dieses Modul lässt sich nur im Formular des Backends bearbeiten.');
        }
        $module = Module::of($slice);
        $stored = [];
        foreach ($fields as $f) {
            $prop = new ReflectionProperty($module, $f['prop']);
            $type = $prop->getType();
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : 'string';
            $nullable = null === $type || $type->allowsNull();
            $raw = trim((string) ($input[$f['name']] ?? ''));
            $label = $f['schema']['label'];
            if ($f['field']->required && '' === $raw && 'bool' !== $f['kind']) {
                throw new ApiError('„' . $label . '“ ist ein Pflichtfeld.');
            }
            $value = self::convert($f, $raw, $typeName, $nullable, $label);
            $prop->setValue($module, $value);
            $stored[$f['name']] = $f['field']->encode($value);
        }
        $error = $module->validate();
        if (null !== $error) {
            throw new ApiError(self::text($error));
        }
        $module->beforeSave();
        ContentHandler::editSlice($slice->id, $stored);
        $module->afterSave();
    }

    /** @param array{kind: string, field: Field, schema: array<string, mixed>} $f */
    private static function convert(array $f, string $raw, string $type, bool $nullable, string $label): mixed
    {
        $empty = static fn () => $nullable ? null : ('int' === $type || 'float' === $type ? 0 : ('bool' === $type ? false : ''));
        $invalid = static fn (): never => throw new ApiError('„' . $label . '“ enthält keinen gültigen Wert.');

        switch ($f['kind']) {
            case 'text':
                $max = $f['schema']['max'] ?? null;
                if (null !== $max && mb_strlen($raw) > $max) {
                    throw new ApiError('„' . $label . '“ ist zu lang (höchstens ' . $max . ' Zeichen).');
                }
                if ('' !== $raw && $f['field'] instanceof Email && false === filter_var($raw, FILTER_VALIDATE_EMAIL)) {
                    $invalid();
                }
                if ('' !== $raw && $f['field'] instanceof Url && false === filter_var($raw, FILTER_VALIDATE_URL)) {
                    $invalid();
                }

                return '' === $raw && $nullable ? null : $raw;
            case 'number':
                if ('' === $raw) {
                    return $empty();
                }
                if (!is_numeric($raw)) {
                    $invalid();
                }

                return 'int' === $type ? (int) $raw : ('float' === $type ? (float) $raw : $raw);
            case 'bool':
                return '' !== $raw && '0' !== $raw;
            case 'choice':
                if ('' === $raw) {
                    return $empty();
                }
                if (!$f['field'] instanceof Choice || !array_key_exists($raw, self::options($f['field']))) {
                    $invalid();
                }
                if (is_a($type, BackedEnum::class, true)) {
                    return $type::tryFrom(is_numeric($raw) && 'int' === (string) new \ReflectionEnum($type)->getBackingType() ? (int) $raw : $raw) ?? $invalid();
                }

                return 'int' === $type ? (int) $raw : $raw;
            case 'date':
            case 'time':
                if ('' === $raw) {
                    return $empty();
                }
                $format = 'date' === $f['kind'] ? 'Y-m-d' : 'H:i';
                $date = DateTimeImmutable::createFromFormat('!' . $format, $raw) ?: $invalid();
                if ($date->format($format) !== $raw) {
                    $invalid();
                }

                return is_a($type, DateTimeInterface::class, true) ? $date : $raw;
            case 'media':
                if ('' === $raw) {
                    return $empty();
                }
                $media = Media::get($raw) ?? $invalid();
                $types = $f['schema']['types'] ?? [];
                if ([] !== $types && !in_array(strtolower(pathinfo($raw, PATHINFO_EXTENSION)), $types, true)) {
                    $invalid();
                }

                return is_a($type, Media::class, true) ? $media : $raw;
            case 'article':
                $id = (int) preg_replace('~^redaxo://~', '', $raw);
                if (0 === $id) {
                    return $empty();
                }
                $article = Article::get($id) ?? $invalid();

                return is_a($type, Article::class, true) ? $article : ('int' === $type ? $id : (string) $id);
        }

        return $raw;
    }

    /** @return array<string, string> */
    private static function options(Choice $field): array
    {
        $out = [];
        foreach ($field->choices() as $value => $label) {
            if (is_array($label)) {   // Gruppen: flach
                foreach ($label as $v => $l) {
                    $out[(string) $v] = self::text($l);
                }
                continue;
            }
            $out[(string) $value] = self::text($label);
        }

        return $out;
    }

    private static function text(mixed $v): string
    {
        try {
            return match (true) {
                $v instanceof Translatable => $v->translate(),
                is_string($v) => $v,
                $v instanceof \Redaxo\Core\View\Renderable => trim(strip_tags((string) $v->render())),
                default => (string) $v,
            };
        } catch (Throwable) {
            return '';
        }
    }
}

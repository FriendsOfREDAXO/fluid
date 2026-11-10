<?php

namespace FriendsOfRedaxo\Fluid;

use FriendsOfRedaxo\Fluid\Form\FieldForm;
use ReflectionMethod;
use ReflectionProperty;
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
use Redaxo\Core\Form\Form;
use Redaxo\Core\Translation\Translatable;
use Throwable;

use function is_string;
use function preg_replace;
use function strtolower;
use function trim;

/**
 * Gewöhnliche REDAXO-Module (keine fluid-Blöcke) in der Seitenleiste der Website – im Formular von fluid.
 *
 * Nur öffentliche API des Cores: die Feld-Attribute der Modulklasse (#[Text], #[MediaPicker] …) per Reflection,
 * `Module::of()` für die aktuellen Werte, `Field::toRaw()`/`encode()`, das Formular des Cores (`Form::submit()` wandelt
 * um und prüft wie im Backend), `Module::validate()` und `ContentHandler::editSlice()`. Module mit eigenem `input()` oder anderen Feldtypen (z. B. Collection, Mehrfachauswahl)
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
        // Eingaben wie die Felder des Cores sie senden (nach Eigenschaft), umgewandelt und geprüft vom Formular des Cores
        $raw = [];
        foreach ($fields as $f) {
            $value = trim((string) ($input[$f['name']] ?? ''));
            $raw[$f['prop']] = match ($f['kind']) {
                'bool' => '' !== $value && '0' !== $value ? '1' : '',
                'article' => (string) preg_replace('~^redaxo://~', '', $value),
                default => $value,
            };
        }
        $form = new Form($module, name: 'fluid_module', csrf: false);
        if (!$form->submit($raw)) {
            $labels = array_column(array_map(static fn (array $f) => [$f['prop'], $f['schema']['label']], $fields), 1, 0);
            $messages = [];
            foreach ($form->getFieldErrors() as $prop => $error) {
                $messages[] = '„' . ($labels[$prop] ?? $prop) . '“: ' . self::text($error);
            }
            throw new ApiError(implode(' ', $messages) ?: 'Die Eingaben sind nicht gültig.');
        }
        $error = $module->validate();
        if (null !== $error) {
            throw new ApiError(self::text($error));
        }
        $stored = [];
        foreach ($fields as $f) {
            $stored[$f['name']] = $f['field']->encode($module->{$f['prop']});
        }
        $module->beforeSave();
        // afterSave() auf einer frischen Instanz des gespeicherten Slices (wie im Backend)
        Module::of(ContentHandler::editSlice($slice->id, $stored))->afterSave();
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

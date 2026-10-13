<?php

namespace FriendsOfRedaxo\Fluid\Form;

use FriendsOfRedaxo\Fluid\Data\YFormBridge;
use FriendsOfRedaxo\Fluid\BlockModule;

use function array_key_exists;
use function array_values;
use function count;
use function implode;
use function in_array;
use function is_array;
use function Redaxo\Core\View\escape;

/**
 * Formular aus dem Feldschema eines Blocks (Format der Studio-Kits).
 *
 * Feldtypen: text, textarea, richtext, select, bool, number, media, file, link, icon, repeater.
 * Optionen je Feld: name, label, type, help, required, default, max, rows, placeholder, options, width (half),
 * variants (nur bei diesen Varianten sichtbar), fields/item_label/title_field/max_items (repeater).
 *
 * Feldnamen: `{prefix}[1][feld]` für die Felder, `{prefix}[2][option]` für die Abschnitts-Optionen. Im Backend ist der
 * Präfix `REX_INPUT_VALUE` – REDAXO speichert die Arrays dann als JSON in value1/value2.
 */
final class FieldForm
{
    private static int $uid = 0;

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $tunes
     */
    public static function render(BlockModule $module, array $data, array $tunes, string $prefix, bool $backend = true): string
    {
        $variants = $module->variants();
        $data = self::withDefaults($module->fields(), $data, $variants);
        $variant = (string) ($data['variant'] ?? '');

        $h = '<div class="blocks-form" data-blocks-form data-variant="' . escape($variant) . '">';
        $help = (string) ($module->definition()['help'] ?? '');
        if ('' !== $help) {
            $h .= '<p class="blocks-form__intro">' . escape($help) . '</p>';
        }
        if ($variants) {
            $h .= self::row('Darstellung', self::select($prefix . '[1][variant]', $variants, $variant, ['data-blocks-variant' => '']), '', false);
        }
        $h .= self::fields($module->fields(), $data, $prefix . '[1]', $backend);
        // Layout: gezogene Spaltenbreiten (Griff auf der Website) beim Speichern des Formulars behalten
        if (is_array($data['widths'] ?? null) && is_array($data['widths']['w'] ?? null)) {
            $h .= '<input type="hidden" name="' . escape($prefix . '[1][widths][preset]') . '" value="' . escape((string) ($data['widths']['preset'] ?? '')) . '">';
            foreach (array_values($data['widths']['w']) as $i => $w) {
                $h .= '<input type="hidden" name="' . escape($prefix . '[1][widths][w][' . $i . ']') . '" value="' . (int) $w . '">';
            }
        }

        // Abschnitts-Optionen
        $bgs = $module->kit()->backgrounds();
        $h .= '<details class="blocks-form__tunes"><summary>Abschnitt</summary><div class="blocks-form__grid">';
        $h .= self::row('Hintergrund', self::select($prefix . '[2][background]', ['' => 'Standard'] + $bgs, (string) ($tunes['background'] ?? '')), 'In einer Layout-Spalte: ein anderer Hintergrund als das Layout macht den Block zur Karte.', true);
        $h .= self::row('Sprungmarke (Anker)', '<input class="form-control" type="text" name="' . escape($prefix . '[2][anchor]') . '" value="' . escape((string) ($tunes['anchor'] ?? '')) . '" pattern="[a-z0-9\-]*" placeholder="z. B. kontakt">', 'Nur Kleinbuchstaben, Ziffern und Bindestriche.', true);
        $space = ['none' => 'Kein', 'small' => 'Klein', 'normal' => 'Normal', 'large' => 'Groß'];
        $h .= self::row('Abstand oben', self::select($prefix . '[2][spaceTop]', $space, (string) ($tunes['spaceTop'] ?? 'normal')), '', true);
        $h .= self::row('Abstand unten', self::select($prefix . '[2][spaceBottom]', $space, (string) ($tunes['spaceBottom'] ?? 'normal')), '', true);
        if ($module->nestable()) {
            $cols = ['' => 'Nicht in einem Layout', '1' => 'Spalte 1', '2' => 'Spalte 2', '3' => 'Spalte 3', '4' => 'Spalte 4'];
            $h .= self::row('Spalte im Layout', self::select($prefix . '[2][col]', $cols, (string) ($tunes['col'] ?? '')),
                'Wirkt, wenn der Block direkt hinter einem Block „Layout (Spalten)“ steht (oder hinter weiteren Blöcken dieses Layouts).', false);
        }
        $h .= '</div></details>';

        return $h . '</div>';
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @param array<string, mixed> $data
     */
    /** Beliebige Felder (z. B. Website-Einstellungen) als Formular auf der Website */
    public static function standalone(array $fields, array $data, string $base): string
    {
        return '<div class="blocks-form" data-blocks-form>' . self::fields($fields, self::withDefaults($fields, $data), $base, false) . '</div>';
    }

    private static function fields(array $fields, array $data, string $base, bool $backend): string
    {
        $h = '<div class="blocks-form__grid">';
        foreach ($fields as $f) {
            $name = (string) $f['name'];
            $only = isset($f['variants']) ? ' data-variants="' . escape(implode(' ', (array) $f['variants'])) . '"' : '';
            $half = 'half' === ($f['width'] ?? '');
            $h .= '<div class="blocks-form__field' . ($half ? ' is-half' : '') . '"' . $only . '>'
                . self::control($f, $data[$name] ?? null, $base . '[' . $name . ']', $backend)
                . '</div>';
        }

        return $h . '</div>';
    }

    /** @param array<string, mixed> $f */
    private static function control(array $f, mixed $value, string $name, bool $backend): string
    {
        $type = (string) ($f['type'] ?? 'text');
        $label = (string) ($f['label'] ?? $f['name']);
        $help = (string) ($f['help'] ?? '');
        $req = !empty($f['required']) ? ' required' : '';
        $ph = isset($f['placeholder']) ? ' placeholder="' . escape((string) $f['placeholder']) . '"' : '';
        $max = isset($f['max']) ? ' maxlength="' . (int) $f['max'] . '"' : '';
        $id = 'blocks-f' . ++self::$uid;
        $n = escape($name);

        $input = match ($type) {
            'textarea' => '<textarea class="form-control" id="' . $id . '" name="' . $n . '" rows="' . (int) ($f['rows'] ?? 3) . '"' . $req . $max . $ph . '>' . escape((string) $value) . '</textarea>',
            'richtext' => '<textarea class="form-control blocks-rich" id="' . $id . '" name="' . $n . '" rows="8" data-blocks-rich' . $req . '>' . escape((string) $value) . '</textarea>',
            'select' => self::select($name, (array) ($f['options'] ?? []), (string) $value, ['id' => $id], !$req),
            'bool' => '<input type="hidden" name="' . $n . '" value="0"><label class="blocks-form__check"><input type="checkbox" id="' . $id . '" name="' . $n . '" value="1"' . ($value ? ' checked' : '') . '> ' . escape($label) . '</label>',
            'number' => '<input class="form-control" type="number" id="' . $id . '" name="' . $n . '" value="' . escape((string) $value) . '"' . $req . '>',
            // Medienpool und Linkmap (REDAXO-Popups); ID aus dem Feldnamen, damit neue Einträge von Wiederholgruppen eindeutig bleiben
            'media', 'file' => Picker::media(self::nameId($name), $name, (string) $value, 'media' === $type, $backend, isset($f['types']) ? (array) $f['types'] : null),
            // Medienkategorie des Medienpools (z. B. Downloads „alle Dateien einer Kategorie“)
            'mediacategory' => self::select($name, self::mediaCategories(), (string) $value, ['id' => $id], true),
            // Tabelle aus YForm (Table Manager) – Formular-Block, Datenliste
            'yform_table' => [] !== ($t = YFormBridge::tables())
                ? self::select($name, $t, (string) $value, ['id' => $id, 'data-yform-table' => ''], !$req)
                : '<p class="help-block">' . (YFormBridge::available() ? 'Noch keine Tabelle – im Projekt eine Klasse mit #[AsTable] anlegen (siehe YForm-README).' : 'Dafür wird das AddOn YForm benötigt.') . '</p>',
            'yform_field' => self::yformField($name, (string) $value, $id),
            // Blog (Kategorie mit „Blog / News“ in den Seiteneinstellungen); leer = alle Blogs
            'blog' => [] !== ($bl = \FriendsOfRedaxo\Fluid\Blog\Blog::all())
                ? self::select($name, ['' => 'Alle Blogs'] + $bl, (string) $value, ['id' => $id], false)
                : '<p class="help-block">Noch kein Blog – bei einer Menüseite in den Seiteneinstellungen „Blog / News“ anhaken.</p>',
            // Formular aus YForm (#[AsForm]-Klasse im Projekt)
            'yform_form' => [] !== ($fo = YFormBridge::forms())
                ? self::select($name, $fo, (string) $value, ['id' => $id], !$req)
                : '<p class="help-block">' . (YFormBridge::available() ? 'Noch kein Formular – im Projekt eine Klasse mit #[AsForm] anlegen (siehe YForm-README).' : 'Dafür wird das AddOn YForm benötigt.') . '</p>',
            'time' => '<input class="form-control" type="time" id="' . $id . '" name="' . $n . '" value="' . escape((string) $value) . '"' . $req . '>',
            // Standort „Breite, Länge“ mit Geokodierung aus den Adressfeldern (Website-Einstellungen)
            'geo' => '<div class="blocks-geo"><input class="form-control" type="text" id="' . $id . '" name="' . $n . '" value="' . escape((string) $value) . '" placeholder="51.4512, 6.6263" pattern="^-?\\d{1,2}(\\.\\d+)?,\\s*-?\\d{1,3}(\\.\\d+)?$" data-geo>'
                . '<button type="button" class="blocks-btn" data-geocode>Aus Adresse ermitteln</button></div>',
            'url' => '<input class="form-control" type="url" id="' . $id . '" name="' . $n . '" value="' . escape((string) $value) . '"' . $req . $ph . ' placeholder="https://…">',
            'link' => Picker::link(self::nameId($name), $name, (string) $value, $backend),
            'repeater' => self::repeater($f, is_array($value) ? $value : [], $name, $backend),
            default => '<input class="form-control" type="text" id="' . $id . '" name="' . $n . '" value="' . escape((string) $value) . '"' . $req . $max . $ph . '>',
        };

        if ('bool' === $type) {
            return $input . ('' !== $help ? '<p class="help-block">' . escape($help) . '</p>' : '');
        }
        if ('repeater' === $type) {
            return '<fieldset class="blocks-form__repeater"><legend>' . escape($label) . '</legend>' . $input
                . ('' !== $help ? '<p class="help-block">' . escape($help) . '</p>' : '') . '</fieldset>';
        }

        return '<label class="control-label" id="' . $id . '-label" for="' . $id . '">' . escape($label) . ('' !== $req ? ' <span aria-hidden="true">*</span>' : '') . '</label>'
            . $input . ('' !== $help ? '<p class="help-block">' . escape($help) . '</p>' : '');
    }

    /**
     * @param array<string, mixed> $f
     * @param array<array-key, mixed> $items
     */
    private static function repeater(array $f, array $items, string $name, bool $backend): string
    {
        /** @var list<array<string, mixed>> $sub */
        $sub = $f['fields'] ?? [];
        $itemLabel = (string) ($f['item_label'] ?? 'Eintrag');
        $titleField = (string) ($f['title_field'] ?? ($sub[0]['name'] ?? ''));
        $maxItems = (int) ($f['max_items'] ?? 0);

        $row = static function (array $item, string $key) use ($sub, $name, $itemLabel, $titleField, $backend): string {
            $title = trim((string) ($item[$titleField] ?? ''));
            // Auswahlfeld als Titel: Beschriftung der Option (z. B. „Montag“ statt „1“)
            foreach ($sub as $sf) {
                if (($sf['name'] ?? '') === $titleField && 'select' === ($sf['type'] ?? '') && isset($sf['options'][$title])) {
                    $title = (string) $sf['options'][$title];
                }
            }

            return '<details class="blocks-rep__item" data-blocks-item' . ('' === $title ? ' open' : '') . '>'
                . '<summary><span class="blocks-rep__title" data-blocks-item-title data-field="' . escape($titleField) . '">' . escape('' !== $title ? $title : $itemLabel) . '</span>'
                . '<span class="blocks-rep__tools">'
                . '<button type="button" class="blocks-btn" data-blocks-up title="Nach oben">↑</button>'
                . '<button type="button" class="blocks-btn" data-blocks-down title="Nach unten">↓</button>'
                . '<button type="button" class="blocks-btn blocks-btn--danger" data-blocks-remove title="Entfernen">✕</button>'
                . '</span></summary>'
                . self::fields($sub, self::withDefaults($sub, $item), $name . '[' . $key . ']', $backend)
                . '</details>';
        };

        $h = '<div class="blocks-rep" data-blocks-repeater data-max="' . $maxItems . '">';
        $h .= '<div class="blocks-rep__list" data-blocks-list>';
        foreach (array_values($items) as $i => $item) {
            $h .= $row(is_array($item) ? $item : [], (string) $i);
        }
        $h .= '</div>';
        $h .= '<template data-blocks-template>' . $row([], '__i__') . '</template>';
        $h .= '<button type="button" class="blocks-btn blocks-btn--add" data-blocks-add' . ($maxItems && count($items) >= $maxItems ? ' disabled' : '') . '>+ ' . escape($itemLabel) . '</button>';

        return $h . '</div>';
    }

    /**
     * @param array<array-key, mixed> $options
     * @param array<string, string> $attrs
     */
    private static function select(string $name, array $options, string $value, array $attrs = [], bool $empty = false): string
    {
        $a = '';
        foreach ($attrs as $k => $v) {
            $a .= ' ' . $k . ('' !== $v ? '="' . escape($v) . '"' : '');
        }
        $h = '<select class="form-control" name="' . escape($name) . '"' . $a . '>';
        if ($empty) {
            $h .= '<option value="">–</option>';
        }
        foreach ($options as $k => $label) {
            $h .= '<option value="' . escape((string) $k) . '"' . ((string) $k === $value ? ' selected' : '') . '>' . escape((string) $label) . '</option>';
        }

        return $h . '</select>';
    }

    /** Feld einer YForm-Tabelle („tabelle.feld“), gruppiert nach Tabelle; form.js blendet die Gruppen anderer Tabellen aus */
    private static function yformField(string $name, string $value, string $id): string
    {
        $h = '<select class="form-control" name="' . escape($name) . '" id="' . $id . '" data-yform-field><option value="">–</option>';
        foreach (YFormBridge::fieldOptions() as $table => $fields) {
            $h .= '<optgroup label="' . escape($table) . '" data-table="' . escape($table) . '">';
            foreach ($fields as $k => $label) {
                $v = $table . '.' . $k;
                $h .= '<option value="' . escape($v) . '"' . ($v === $value ? ' selected' : '') . '>' . escape($label) . '</option>';
            }
            $h .= '</optgroup>';
        }

        return $h . '</select>';
    }

    /** @return array<int, string> Medienkategorien als Baum (eingerückt) */
    private static function mediaCategories(): array
    {
        $out = [];
        $walk = static function (array $cats, int $depth) use (&$walk, &$out): void {
            foreach ($cats as $c) {
                $out[$c->id] = str_repeat('– ', $depth) . $c->name;
                $walk($c->getChildren(), $depth + 1);
            }
        };
        $walk(\Redaxo\Core\MediaPool\MediaCategory::getRootCategories(), 0);

        return $out;
    }

    /** Element-ID aus dem Feldnamen (`f[1][items][__i__][image]` → `blocks_f_1_items___i___image`) */
    private static function nameId(string $name): string
    {
        return 'blocks_' . trim((string) preg_replace('~[^A-Za-z0-9_]+~', '_', $name), '_');
    }

    private static function row(string $label, string $control, string $help, bool $half): string
    {
        return '<div class="blocks-form__field' . ($half ? ' is-half' : '') . '"><label class="control-label">' . escape($label) . '</label>' . $control
            . ('' !== $help ? '<p class="help-block">' . escape($help) . '</p>' : '') . '</div>';
    }

    /**
     * Daten mit Standardwerten auffüllen; Listen neu nummerieren.
     *
     * @param list<array<string, mixed>> $fields
     * @param array<string, mixed> $data
     * @param array<string, string> $variants
     * @return array<string, mixed>
     */
    public static function withDefaults(array $fields, array $data, array $variants = []): array
    {
        foreach ($fields as $f) {
            $name = (string) $f['name'];
            $type = (string) ($f['type'] ?? 'text');
            if ('repeater' === $type) {
                $items = is_array($data[$name] ?? null) ? array_values($data[$name]) : [];
                /** @var list<array<string, mixed>> $sub */
                $sub = $f['fields'] ?? [];
                $data[$name] = array_map(static fn ($it) => self::withDefaults($sub, is_array($it) ? $it : []), $items);
                continue;
            }
            if (!array_key_exists($name, $data) || null === $data[$name]) {
                $data[$name] = $f['default'] ?? ('bool' === $type ? false : '');
            }
            if ('bool' === $type) {
                $data[$name] = in_array($data[$name], [true, 1, '1', 'on'], true);
            }
        }
        if ($variants && !isset($variants[(string) ($data['variant'] ?? '')])) {
            $data['variant'] = (string) array_key_first($variants);
        }

        return $data;
    }
}

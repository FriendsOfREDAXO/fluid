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
 * Feldnamen: `{prefix}[1][feld]` für die Felder, `{prefix}[2][option]` für die Abschnitts-Optionen. Im Backend
 * (BlockModule::input()) `slice[values][feld]` und `slice[tunes][option]` – das Formular des Cores schreibt sie in die
 * Eigenschaften des Moduls (Feldtyp BlockValues).
 */
final class FieldForm
{
    private static int $uid = 0;

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $tunes
     */
    public static function render(string $module, array $data, array $tunes, string $prefix, bool $backend = true, string $dataKey = '1', string $tunesKey = '2'): string
    {
        $p1 = $prefix . '[' . $dataKey . ']';
        $p2 = $prefix . '[' . $tunesKey . ']';
        $variants = $module::variants();
        $data = self::withDefaults($module::fields(), $data, $variants);
        $variant = (string) ($data['variant'] ?? '');

        $h = '<div class="blocks-form" data-blocks-form data-variant="' . escape($variant) . '">';
        $help = (string) ($module::definition()['help'] ?? '');
        if ('' !== $help) {
            $h .= '<p class="blocks-form__intro">' . escape($help) . '</p>';
        }
        if ($variants) {
            $h .= self::row('Darstellung', self::select($p1 . '[variant]', $variants, $variant, ['data-blocks-variant' => '']), '', false);
        }
        $h .= self::fields($module::fields(), $data, $p1, $backend);
        // Layout: gezogene Spaltenbreiten (Griff auf der Website) beim Speichern des Formulars behalten
        if (is_array($data['widths'] ?? null) && is_array($data['widths']['w'] ?? null)) {
            $h .= '<input type="hidden" name="' . escape($p1 . '[widths][preset]') . '" value="' . escape((string) ($data['widths']['preset'] ?? '')) . '">';
            foreach (array_values($data['widths']['w']) as $i => $w) {
                $h .= '<input type="hidden" name="' . escape($p1 . '[widths][w][' . $i . ']') . '" value="' . (int) $w . '">';
            }
        }

        // Abschnitts-Optionen
        $bgs = $module::kit()->backgrounds();
        $h .= '<details class="blocks-form__tunes"><summary>Abschnitt</summary><div class="blocks-form__grid">';
        $h .= self::row('Hintergrund', self::select($p2 . '[background]', ['' => 'Standard'] + $bgs, (string) ($tunes['background'] ?? '')), 'In einer Layout-Spalte: ein anderer Hintergrund als das Layout macht den Block zur Karte.', true);
        $h .= self::row('Sprungmarke (Anker)', '<input class="rex-input" type="text" name="' . escape($p2 . '[anchor]') . '" value="' . escape((string) ($tunes['anchor'] ?? '')) . '" pattern="[a-z0-9\-]*" placeholder="z. B. kontakt">', 'Nur Kleinbuchstaben, Ziffern und Bindestriche.', true);
        $space = ['none' => 'Kein', 'small' => 'Klein', 'normal' => 'Normal', 'large' => 'Groß'];
        $h .= self::row('Abstand oben', self::select($p2 . '[spaceTop]', $space, (string) ($tunes['spaceTop'] ?? 'normal')), '', true);
        $h .= self::row('Abstand unten', self::select($p2 . '[spaceBottom]', $space, (string) ($tunes['spaceBottom'] ?? 'normal')), '', true);
        if ($module::nestable()) {
            $cols = ['' => 'Nicht in einem Layout', '1' => 'Spalte 1', '2' => 'Spalte 2', '3' => 'Spalte 3', '4' => 'Spalte 4'];
            $h .= self::row('Spalte im Layout', self::select($p2 . '[col]', $cols, (string) ($tunes['col'] ?? '')),
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
            'textarea' => '<textarea class="rex-input" id="' . $id . '" name="' . $n . '" rows="' . (int) ($f['rows'] ?? 3) . '"' . $req . $max . $ph . '>' . escape((string) $value) . '</textarea>',
            'richtext' => '<textarea class="rex-input blocks-rich" id="' . $id . '" name="' . $n . '" rows="8" data-blocks-rich' . $req . '>' . escape((string) $value) . '</textarea>',
            'select' => self::select($name, (array) ($f['options'] ?? []), (string) $value, ['id' => $id], !$req),
            'bool' => '<input type="hidden" name="' . $n . '" value="0"><div class="rex-check"><label class="blocks-form__check"><input type="checkbox" id="' . $id . '" name="' . $n . '" value="1"' . ($value ? ' checked' : '') . '> ' . escape($label) . '</label></div>',
            'number' => '<input class="rex-input" type="number" id="' . $id . '" name="' . $n . '" value="' . escape((string) $value) . '"' . $req . '>',
            // Medienpool und Linkmap (REDAXO-Popups); ID aus dem Feldnamen, damit neue Einträge von Wiederholgruppen eindeutig bleiben
            'media', 'file' => Picker::media(self::nameId($name), $name, (string) $value, 'media' === $type, $backend, isset($f['types']) ? (array) $f['types'] : null),
            // Medienkategorie des Medienpools (z. B. Downloads „alle Dateien einer Kategorie“)
            'mediacategory' => self::select($name, self::mediaCategories(), (string) $value, ['id' => $id], true),
            // Tabelle aus YForm (Table Manager) – Formular-Block, Datenliste
            'yform_table' => [] !== ($t = YFormBridge::tables())
                ? self::select($name, $t, (string) $value, ['id' => $id, 'data-yform-table' => ''], !$req)
                : '<p class="rex-field-note">' . (YFormBridge::available() ? 'Noch keine Tabelle – im Projekt eine Klasse mit #[AsTable] anlegen (siehe YForm-README).' : 'Dafür wird das AddOn YForm benötigt.') . '</p>',
            'yform_field' => self::yformField($name, (string) $value, $id),
            // Blog (Kategorie mit „Blog / News“ in den Seiteneinstellungen); leer = alle Blogs
            'blog' => [] !== ($bl = \FriendsOfRedaxo\Fluid\Blog\Blog::all())
                ? self::select($name, ['' => 'Alle Blogs'] + $bl, (string) $value, ['id' => $id], false)
                : '<p class="rex-field-note">Noch kein Blog – bei einer Menüseite in den Seiteneinstellungen „Blog / News“ anhaken.</p>',
            // Formular aus YForm (#[AsForm]-Klasse im Projekt)
            'yform_form' => [] !== ($fo = YFormBridge::forms())
                ? self::select($name, $fo, (string) $value, ['id' => $id], !$req)
                : '<p class="rex-field-note">' . (YFormBridge::available() ? 'Noch kein Formular – im Projekt eine Klasse mit #[AsForm] anlegen (siehe YForm-README).' : 'Dafür wird das AddOn YForm benötigt.') . '</p>',
            'time' => '<input class="rex-input" type="time" id="' . $id . '" name="' . $n . '" value="' . escape((string) $value) . '"' . $req . '>',
            'date' => '<input class="rex-input" type="date" id="' . $id . '" name="' . $n . '" value="' . escape((string) $value) . '"' . $req . '>',
            // Standort „Breite, Länge“ mit Geokodierung aus den Adressfeldern (Website-Einstellungen)
            'geo' => '<div class="blocks-geo rex-input-group"><input class="rex-input" type="text" id="' . $id . '" name="' . $n . '" value="' . escape((string) $value) . '" placeholder="51.4512, 6.6263" pattern="^-?\\d{1,2}(\\.\\d+)?,\\s*-?\\d{1,3}(\\.\\d+)?$" data-geo>'
                . '<span class="rex-input-group-actions"><button type="button" class="blocks-btn rex-button" data-variant="field" data-geocode>Aus Adresse ermitteln</button></span></div>',
            'url' => '<input class="rex-input" type="url" id="' . $id . '" name="' . $n . '" value="' . escape((string) $value) . '"' . $req . $ph . ' placeholder="https://…">',
            'link' => Picker::link(self::nameId($name), $name, (string) $value, $backend),
            'repeater' => self::repeater($f, is_array($value) ? $value : [], $name, $backend),
            default => '<input class="rex-input" type="text" id="' . $id . '" name="' . $n . '" value="' . escape((string) $value) . '"' . $req . $max . $ph . '>',
        };

        if ('bool' === $type) {
            return $input . ('' !== $help ? '<p class="rex-field-note">' . escape($help) . '</p>' : '');
        }
        if ('repeater' === $type) {
            return '<fieldset class="' . ($backend ? 'rex-field" data-layout="vertical' : 'blocks-form__repeater') . '"><legend class="rex-field-label">' . escape($label) . '</legend>' . $input
                . ('' !== $help ? '<p class="rex-field-note">' . escape($help) . '</p>' : '') . '</fieldset>';
        }

        return '<label class="rex-field-label" id="' . $id . '-label" for="' . $id . '">' . escape($label) . ('' !== $req ? ' <span class="rex-field-required" aria-hidden="true">*</span>' : '') . '</label>'
            . $input . ('' !== $help ? '<p class="rex-field-note">' . escape($help) . '</p>' : '');
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

            $label = escape('' !== $title ? $title : $itemLabel);
            $titleAttr = 'data-blocks-item-title data-field="' . escape($titleField) . '"';
            $fields = self::fields($sub, self::withDefaults($sub, $item), $name . '[' . $key . ']', $backend);
            // Knöpfe wie die Sammelfelder des Cores (CollectionInput): im Backend Icons, auf der Website Zeichen
            $tool = static fn (string $attr, string $label, string $icon, string $sign, string $class = 'blocks-btn'): string => '<button type="button" class="' . $class . '" ' . $attr
                . ' title="' . $label . '" aria-label="' . $label . '">' . ($backend ? '<i class="rex-icon ' . $icon . '" aria-hidden="true"></i>' : $sign) . '</button>';

            if ($backend) {
                // eigene Komponente (fluid-rep-item, assets/form.css): einzeiliger Kopf mit Titel und Knopfgruppe des Cores;
                // auf- und zuklappen übernimmt der Core (data-toggle="collapse", Hilfsklasse .collapse/.in)
                $id = 'blocks-rep-' . self::nameId($name . '[' . $key . ']');
                $open = '' === $title;

                return '<section class="fluid-rep-item" data-blocks-item><header class="fluid-rep-item__head">'
                    . '<button type="button" class="fluid-rep-item__toggle' . ($open ? '' : ' collapsed') . '" data-toggle="collapse" data-target="#' . $id . '" aria-controls="' . $id . '" aria-expanded="' . ($open ? 'true' : 'false') . '">'
                    . '<span class="fluid-rep-item__title" ' . $titleAttr . '>' . $label . '</span></button>'
                    . '<div class="fluid-rep-item__tools"><div class="rex-button-group" data-size="xs">'
                    . $tool('data-blocks-up', 'Nach oben', 'rex-icon-up', '↑', 'rex-button')
                    . $tool('data-blocks-down', 'Nach unten', 'rex-icon-down', '↓', 'rex-button')
                    . $tool('data-blocks-remove data-variant="danger"', 'Entfernen', 'rex-icon-delete', '✕', 'rex-button')
                    . '</div></div></header>'
                    . '<div id="' . $id . '" class="collapse' . ($open ? ' in' : '') . '"><div class="fluid-rep-item__body">' . $fields . '</div></div>'
                    . '</section>';
            }

            return '<details class="blocks-rep__item" data-blocks-item' . ('' === $title ? ' open' : '') . '>'
                . '<summary><span class="blocks-rep__title" ' . $titleAttr . '>' . $label . '</span>'
                . '<span class="blocks-rep__tools">'
                . $tool('data-blocks-up', 'Nach oben', 'rex-icon-up', '↑')
                . $tool('data-blocks-down', 'Nach unten', 'rex-icon-down', '↓')
                . $tool('data-blocks-remove', 'Entfernen', 'rex-icon-delete', '✕', 'blocks-btn blocks-btn--danger')
                . '</span></summary>'
                . '<div class="blocks-rep__body">' . $fields . '</div>'
                . '</details>';
        };

        $h = '<div class="blocks-rep" data-blocks-repeater data-max="' . $maxItems . '">';
        $h .= '<div class="blocks-rep__list" data-blocks-list>';
        foreach (array_values($items) as $i => $item) {
            $h .= $row(is_array($item) ? $item : [], (string) $i);
        }
        $h .= '</div>';
        $h .= '<template data-blocks-template>' . $row([], '__i__') . '</template>';
        $h .= '<button type="button" class="' . ($backend ? 'rex-button' : 'blocks-btn blocks-btn--add') . '" data-blocks-add' . ($maxItems && count($items) >= $maxItems ? ' disabled' : '') . '>'
            . ($backend ? '<i class="rex-icon rex-icon-add" aria-hidden="true"></i> ' : '+ ') . escape($itemLabel) . '</button>';

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
        $h = '<select class="rex-input" name="' . escape($name) . '"' . $a . '>';
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
        $h = '<select class="rex-input" name="' . escape($name) . '" id="' . $id . '" data-yform-field><option value="">–</option>';
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
        return '<div class="blocks-form__field' . ($half ? ' is-half' : '') . '"><label class="rex-field-label">' . escape($label) . '</label>' . $control
            . ('' !== $help ? '<p class="rex-field-note">' . escape($help) . '</p>' : '') . '</div>';
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
                // Vorgabe nur, wenn die Wiederholgruppe noch nie gespeichert wurde (neuer Block)
                $items = is_array($data[$name] ?? null) ? array_values($data[$name]) : (!array_key_exists($name, $data) && is_array($f['default'] ?? null) ? $f['default'] : []);
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

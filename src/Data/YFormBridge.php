<?php

namespace FriendsOfRedaxo\Fluid\Data;

use FriendsOfRedaxo\Fluid\Editing;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Filesystem\Url;
use Yakamara\YForm\Data\Record;
use Yakamara\YForm\Field\Checkbox;
use Yakamara\YForm\Field\CreatedAt;
use Yakamara\YForm\Field\CreatedBy;
use Yakamara\YForm\Field\Email;
use Yakamara\YForm\Field\Field;
use Yakamara\YForm\Field\Hidden;
use Yakamara\YForm\Field\Markup;
use Yakamara\YForm\Field\Media;
use Yakamara\YForm\Field\Password;
use Yakamara\YForm\Field\Text;
use Yakamara\YForm\Field\Textarea;
use Yakamara\YForm\Field\UpdatedAt;
use Yakamara\YForm\Field\UpdatedBy;
use Yakamara\YForm\Field\Url as UrlField;
use Yakamara\YForm\Field\Uuid;
use Yakamara\YForm\Form\Definition\FormDefinition;
use Yakamara\YForm\Form\Definition\Forms;
use Yakamara\YForm\Form\Theme\Theme;
use Yakamara\YForm\Permission\Access;
use Yakamara\YForm\Table\Table;
use Yakamara\YForm\Table\Tables;

use function in_array;
use function Redaxo\Core\View\escape;

/**
 * Anbindung an YForm für REDAXO 6 (yakamara/yform, Neubau: Tabellen und Formulare sind Klassen im Projekt).
 * Ausschließlich mit den Mitteln von YForm:
 *  - Formulare: #[AsForm]-Klassen; ausgeführt mit FormDefinition::run() – Prüfung, CSRF, Honigtopf und Aktionen
 *    (SaveRecord, SendMail, ShowMessage …) kommen aus der Formular-Klasse. fluid liefert nur ein Theme im Stil des Kits.
 *  - Datenliste: #[AsTable]-Klassen; Abfragen über Table::query(), Werte über Field::textValue() der Feldklassen.
 *  - Rechte: Permission\Access (Tabellen ansehen/bearbeiten), Bearbeiten in der Datenseite von YForm.
 */
final class YFormBridge
{
    public static function available(): bool
    {
        return class_exists(Tables::class) && Addon::exists('yform');   // REDAXO 6: installiert = aktiv
    }

    // ------------------------------------------------------------------ Formulare

    /** @return array<string, string> Formulare für die Auswahl im Block (Name => Beschriftung) */
    public static function forms(): array
    {
        if (!self::available()) {
            return [];
        }
        $out = [];
        foreach (Forms::all() as $name => $form) {
            $out[$name] = $form->label() . ($form->definition->module ? '' : ' (' . $name . ')');
        }

        return $out;
    }

    public static function formDefinition(string $name): ?FormDefinition
    {
        return self::available() && '' !== $name ? Forms::find($name) : null;
    }

    /** Formular ausführen (YForm verarbeitet Absenden, Prüfung und Aktionen) – im Theme des Kits, sofern das Projekt keins setzt */
    public static function runForm(FormDefinition $form): string
    {
        $before = Forms::$defaultTheme;
        Forms::$defaultTheme ??= self::theme();
        try {
            return '<div class="fyf-wrap">' . $form->run() . '</div>';
        } finally {
            Forms::$defaultTheme = $before;
        }
    }

    /** Theme mit den Klassen der öffentlichen Formulare des Kits (css/dataform.css, .dff) */
    public static function theme(): Theme
    {
        return new Theme(
            form: 'dff fyf', group: 'dff-f', groupInvalid: 'is-invalid', label: '', control: '', staticControl: '', controlInvalid: '',
            select: '', checkWrapper: 'dff-check', checkInput: '', checkLabel: '', radioWrapper: 'dff-check', help: 'dff-help',
            error: 'dff-err', row: 'dff-grid', column: '', buttons: 'dff-actions', submit: 'btn btn--primary', secondary: 'btn btn--ghost',
            alertError: 'dff-summary', alertSuccess: 'dff-done',
        );
    }

    /** Tabellen, in die ein Formular speichert (Aktion SaveRecord) @return list<string> */
    public static function formTables(FormDefinition $form): array
    {
        $out = [];
        foreach ($form->actions() as $action) {
            if ($action instanceof \Yakamara\YForm\Form\Action\SaveRecord && ($t = Tables::find($action->table))) {
                $out[] = $t->definition->name;
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------ Tabellen

    /** @return array<string, string> Tabellen für die Auswahl im Block (Tabellenname => Beschriftung) */
    public static function tables(): array
    {
        if (!self::available()) {
            return [];
        }
        $out = [];
        foreach (Tables::all() as $name => $t) {
            $out[$name] = $t->label() . ' (' . $name . ')';
        }

        return $out;
    }

    public static function table(string $name): ?Table
    {
        return self::available() && '' !== $name ? Tables::find($name) : null;
    }

    /** @return array<string, array<string, string>> Felder je Tabelle (Tabellenname => [Feldname => Beschriftung]) */
    public static function fieldOptions(): array
    {
        $out = [];
        foreach (array_keys(self::tables()) as $name) {
            foreach (self::detailFields(Tables::get($name), true) as $f) {
                $out[$name][$f->name] = $f->translatedLabel() . ' (' . $f->name . ')';
            }
        }

        return $out;
    }

    /** Feld aus „tabelle.feld“ (Auswahl im Block) – oder das erste Feld einer der Klassen @param list<class-string<Field>> $types */
    public static function field(Table $table, string $choice, array $types = []): ?Field
    {
        $prefix = $table->definition->name . '.';
        if (str_starts_with($choice, $prefix) && $table->hasField(substr($choice, \strlen($prefix)))) {
            return $table->getField(substr($choice, \strlen($prefix)));
        }
        foreach ($types as $type) {
            foreach ($table->getFields() as $f) {
                if ($f::class === $type) {
                    return $f;
                }
            }
        }

        return null;
    }

    /** @return list<Field> Felder mit Inhalt für Besucher (ohne Technik-Felder) */
    public static function detailFields(Table $table, bool $withMeta = false): array
    {
        $skip = [Markup::class, Hidden::class, Password::class, Uuid::class, CreatedBy::class, UpdatedBy::class, UpdatedAt::class];
        if (!$withMeta) {
            $skip[] = CreatedAt::class;
        }

        return array_values(array_filter($table->getFields(), static fn (Field $f) => $f->storesValue() && !in_array($f::class, $skip, true)));
    }

    /**
     * @param array{filter?: ?Field, value?: string, sort?: ?Field, dir?: string, limit?: int} $o
     *
     * @return list<Record>
     */
    public static function rows(Table $table, array $o): array
    {
        $q = $table::query();
        if (($f = $o['filter'] ?? null) && '' !== ($o['value'] ?? '')) {
            $q->where($f->name, $f instanceof Checkbox ? (bool) $o['value'] : $o['value']);
        }
        if ($f = $o['sort'] ?? null) {
            $q->orderBy($f->name, 'desc' === ($o['dir'] ?? '') ? 'DESC' : 'ASC');
        }
        if (($o['limit'] ?? 0) > 0) {
            $q->limit((int) $o['limit']);
        }

        return array_values(iterator_to_array($q->find()));
    }

    /**
     * Wie rows(), aber seitenweise (YForm Query::paginate): ['rows' => …, 'total' => …, 'page' => …, 'pages' => …]
     */
    public static function page(Table $table, array $o, int $page, int $perPage): array
    {
        $q = $table::query();
        if (($f = $o['filter'] ?? null) && '' !== ($o['value'] ?? '')) {
            $q->where($f->name, $f instanceof Checkbox ? (bool) $o['value'] : $o['value']);
        }
        if ($f = $o['sort'] ?? null) {
            $q->orderBy($f->name, 'desc' === ($o['dir'] ?? '') ? 'DESC' : 'ASC');
        }
        $p = $q->paginate(max(1, $page), max(1, $perPage));

        return ['rows' => array_values(iterator_to_array($p->items)), 'total' => $p->total, 'page' => $p->page, 'pages' => $p->pages];
    }

    /** Eintrag für die Detailansicht – nur, wenn er die Bedingung der Liste erfüllt */
    public static function row(Table $table, int $id, ?Field $filter, string $value): ?Record
    {
        $q = $table::query()->where('id', $id);
        if ($filter && '' !== $value) {
            $q->where($filter->name, $filter instanceof Checkbox ? (bool) $value : $value);
        }

        return $q->first();
    }

    /** Wert als Text – Ausgabe der Feldklasse (Auswahl-Beschriftung, Datumsformat, Ja/Nein …) */
    public static function text(Record $record, Field $f): string
    {
        return trim($f->textValue($record->get($f->name)));
    }

    /** Wert als HTML (Zeilenumbrüche, E-Mail und Links anklickbar) */
    public static function html(Record $record, Field $f): string
    {
        $v = self::text($record, $f);
        if ('' === $v) {
            return '';
        }

        return match (true) {
            $f instanceof Email => '<a href="mailto:' . escape($v) . '">' . escape($v) . '</a>',
            $f instanceof UrlField, (bool) preg_match('~^https?://\S+$~', $v) => '<a href="' . escape($v) . '" rel="noopener">' . escape((string) preg_replace('~^https?://(www\.)?~', '', $v)) . '</a>',
            default => nl2br(escape($v), false),
        };
    }

    /** Erste Datei eines Medienfelds (oder '') */
    public static function file(Record $record, ?Field $f): string
    {
        if (!$f instanceof Media) {
            return '';
        }

        return trim(explode(',', $f->textValue($record->get($f->name)))[0]);
    }

    public static function isText(?Field $f): bool
    {
        return $f instanceof Text || $f instanceof Textarea;
    }

    /** Bearbeiten in der Datenseite von YForm (nur mit Bearbeitungsrecht); ohne ID: neuer Eintrag */
    public static function editUrl(Table $table, ?int $id = null): ?string
    {
        $user = Editing::user();
        if (!$user || !Access::canEdit($table, $user)) {
            return null;
        }

        return Url::backendPage('yform/data', ['table' => $table->definition->name] + ($id ? ['func' => 'edit', 'id' => $id] : ['func' => 'add']));
    }
}

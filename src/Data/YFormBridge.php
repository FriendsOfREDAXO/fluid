<?php

namespace FriendsOfRedaxo\Fluid\Data;

use FriendsOfRedaxo\Fluid\Editing;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Core;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;
use Yakamara\YForm\Manager\Dataset;
use Yakamara\YForm\Manager\Field;
use Yakamara\YForm\Manager\Table\Table;
use Yakamara\YForm\YForm;

use function in_array;
use function is_array;
use function strlen;
use function Redaxo\Core\View\escape;

/**
 * Anbindung an YForm (yakamara/yform, optional): Formular-Block und Datenliste nutzen die Tabellen des Table Managers.
 *
 * Felder, Pflichtangaben und Prüfungen kommen aus der Tabelle (Backend: YForm › Table Manager), der Block legt nur
 * Beschriftung, Bestätigung und Benachrichtigung fest. Spamschutz ohne Cookies und ohne Session: Honigtopf-Feld und
 * Mindestzeit zwischen Anzeige und Absenden (wie die öffentlichen Formulare in KLXM Studio) – die Seite bleibt cachebar.
 */
final class YFormBridge
{
    /** Mindestzeit in Sekunden zwischen Anzeige und Absenden (schnellere Sendungen sind Bots) */
    private const int MIN_SECONDS = 3;

    public static function available(): bool
    {
        return class_exists(YForm::class) && Addon::exists('yform');   // REDAXO 6: installiert = aktiv
    }

    /** @return array<string, string> Tabellen für die Auswahl im Block (Tabellenname => Name) */
    public static function tables(): array
    {
        if (!self::available()) {
            return [];
        }
        $out = [];
        foreach (Table::getAll() as $t) {
            if ($t->isActive()) {
                $out[$t->getTableName()] = $t->getNameLocalized() . ' (' . $t->getTableName() . ')';
            }
        }

        return $out;
    }

    /** @return array<string, array<string, string>> Felder je Tabelle (Tabellenname => [Feldname => Beschriftung]) */
    public static function fieldOptions(): array
    {
        $out = [];
        foreach (array_keys(self::tables()) as $name) {
            foreach (Table::get($name)?->getValueFields() ?? [] as $f) {
                if (in_array($f->getTypeName(), ['submit', 'html', 'php', 'fieldset', 'csrf', 'validate'], true)) {
                    continue;
                }
                $out[$name][$f->getName()] = ('' !== (string) $f->getLabel() ? $f->getLabel() : $f->getName()) . ' (' . $f->getName() . ')';
            }
        }

        return $out;
    }

    public static function table(string $name): ?Table
    {
        if (!self::available() || '' === $name) {
            return null;
        }
        $t = Table::get($name);

        return $t && $t->isActive() ? $t : null;
    }

    /**
     * Formular einer Tabelle ausgeben und verarbeiten (Eintrag speichern, optional E-Mail an die Redaktion).
     *
     * @param array{id: string, action: string, submit?: string, success?: string, notify?: string, subject?: string} $o
     */
    public static function form(Table $table, array $o): string
    {
        $name = 'fluid_' . preg_replace('/\W/', '', $o['id']);
        $sent = 'post' === Request::requestMethod() && null !== Request::post('fluid_form', 'string', null) && $name === Request::post('fluid_form', 'string');
        // Spamschutz: Honigtopf gefüllt oder zu schnell → wie Erfolg antworten, aber nichts speichern
        if ($sent && ('' !== Request::post('fluid_hp', 'string', '') || time() - Request::post('fluid_t', 'int', 0) < self::MIN_SECONDS)) {
            return self::done($o['success'] ?? '');
        }

        $dataset = Dataset::create($table->getTableName());
        $yform = $dataset->getForm();
        $yform->setObjectparams('form_name', $name);
        $yform->setObjectparams('form_wrap_id', $name . '-wrap');
        $yform->setObjectparams('form_class', 'dff fyf');
        $yform->setObjectparams('form_wrap_class', 'fyf-wrap');
        $yform->setObjectparams('form_action', $o['action']);
        $yform->setObjectparams('form_anchor', $o['id']);
        $yform->setObjectparams('submit_btn_label', '' !== trim($o['submit'] ?? '') ? $o['submit'] : 'Absenden');
        $yform->setObjectparams('csrf_protection', false);   // keine Session für Besucher – Schutz über Honigtopf/Zeit
        $yform->setObjectparams('hide_field_warning_messages', false);
        $yform->setObjectparams('form_hiddenfields', ['fluid_form' => $name, 'fluid_t' => (string) time()]);
        $yform->setValueField('html', ['fluid_hp', '', '<div class="dff-hp" aria-hidden="true"><label>Bitte leer lassen <input type="text" name="fluid_hp" tabindex="-1" autocomplete="off"></label></div>']);

        $html = $dataset->executeForm($yform);
        if (!$yform->objparams['actions_executed']) {
            return $html;
        }
        if ('' !== trim($o['notify'] ?? '')) {
            self::notify($table, $dataset, trim($o['notify']), trim($o['subject'] ?? ''));
        }

        return self::done($o['success'] ?? '');
    }

    private static function done(string $text): string
    {
        $text = '' !== trim($text) ? $text : 'Vielen Dank! Ihre Nachricht ist angekommen.';

        return '<div class="dff-done" role="status" tabindex="-1"><p>' . nl2br(escape($text), false) . '</p></div>';
    }

    /** Benachrichtigung: alle Felder als Text (Core-Mailer, MAILER_DSN); Fehler beim Versand blockieren die Antwort nicht */
    private static function notify(Table $table, Dataset $dataset, string $to, string $subject): void
    {
        $lines = [];
        $reply = null;
        foreach ($table->getValueFields() as $field) {
            $key = $field->getName();
            if (!$dataset->hasValue($key)) {
                continue;
            }
            $value = trim((string) $dataset->getValue($key));
            $lines[] = $field->getLabel() . ': ' . ('' !== $value ? $value : '–');
            if ('email' === $field->getTypeName() && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $reply = $value;
            }
        }
        try {
            $mail = new Email()
                ->to(...array_map(static fn (string $a) => new Address(trim($a)), explode(',', $to)))
                ->subject('' !== $subject ? $subject : 'Neue Anfrage: ' . $table->getNameLocalized())
                ->text(implode("\n", $lines) . "\n\n— " . Core::getProject()->instanceName);
            if ($reply) {
                $mail->replyTo($reply);
            }
            Core::getMailer()->send($mail);
        } catch (Throwable) {
            // Eintrag ist gespeichert; Versandfehler stehen im Mail-Log des Cores
        }
    }

    // ------------------------------------------------------------------ Datenliste

    /** Feld einer Tabelle aus „tabelle.feld“ (Auswahl im Block) – oder das erste passende Feld */
    public static function field(Table $table, string $choice, array $types = []): ?Field
    {
        $prefix = $table->getTableName() . '.';
        $fields = array_values(array_filter($table->getValueFields(), static fn (Field $f) => !in_array($f->getTypeName(), ['submit', 'html', 'php', 'fieldset', 'csrf'], true)));
        if (str_starts_with($choice, $prefix)) {
            foreach ($fields as $f) {
                if ($f->getName() === substr($choice, strlen($prefix))) {
                    return $f;
                }
            }
        }
        if ([] === $types) {
            return null;
        }
        foreach ($fields as $f) {
            if (in_array($f->getTypeName(), $types, true)) {
                return $f;
            }
        }

        return null;
    }

    /** @return list<Field> Felder für die Detailansicht (ohne technische) */
    public static function detailFields(Table $table): array
    {
        return array_values(array_filter($table->getValueFields(), static fn (Field $f) => !in_array($f->getTypeName(), ['submit', 'html', 'php', 'fieldset', 'csrf', 'hidden', 'datestamp', 'ip', 'uuid', 'generate_key', 'hashvalue', 'password', 'prio', 'index', 'signature', 'objparams'], true)));
    }

    /**
     * @param array{filter?: ?Field, value?: string, sort?: ?Field, dir?: string, limit?: int} $o
     *
     * @return list<Dataset>
     */
    public static function rows(Table $table, array $o): array
    {
        $q = Dataset::query($table->getTableName());
        if (($f = $o['filter'] ?? null) && '' !== ($o['value'] ?? '')) {
            $q->where($f->getName(), $o['value']);
        }
        if ($f = $o['sort'] ?? null) {
            $q->resetOrderBy()->orderBy($f->getName(), 'desc' === ($o['dir'] ?? '') ? 'DESC' : 'ASC');
        }
        if (($o['limit'] ?? 0) > 0) {
            $q->limit((int) $o['limit']);
        }

        return array_values(iterator_to_array($q->find()));
    }

    /** Eintrag für die Detailansicht – nur, wenn er die Bedingung der Liste erfüllt */
    public static function row(Table $table, int $id, ?Field $filter, string $value): ?Dataset
    {
        $q = Dataset::query($table->getTableName())->where('id', $id);
        if ($filter && '' !== $value) {
            $q->where($filter->getName(), $value);
        }

        return $q->findOne();
    }

    /** Wert als Text (Auswahl: Beschriftung, Datum lokal, Ja/Nein) */
    public static function text(Dataset $ds, Field $f): string
    {
        $raw = $ds->hasValue($f->getName()) ? $ds->getValue($f->getName()) : null;
        $v = trim((string) $raw);
        if ('' === $v) {
            return '';
        }

        return match ($f->getTypeName()) {
            'choice' => implode(', ', array_map(static fn (string $k) => self::choices($f)[$k] ?? $k, explode(',', $v))),
            'checkbox' => '1' === $v ? 'Ja' : 'Nein',
            'date' => false !== ($t = strtotime($v)) ? date('d.m.Y', $t) : $v,
            'datetime', 'datestamp' => false !== ($t = strtotime($v)) ? date('d.m.Y, H:i', $t) . ' Uhr' : $v,
            default => $v,
        };
    }

    /** Wert als HTML (Text mit Zeilenumbrüchen, E-Mail und Links anklickbar; Medien über $image) */
    public static function html(Dataset $ds, Field $f): string
    {
        $v = self::text($ds, $f);
        if ('' === $v) {
            return '';
        }

        return match (true) {
            'email' === $f->getTypeName() => '<a href="mailto:' . escape($v) . '">' . escape($v) . '</a>',
            (bool) preg_match('~^https?://\S+$~', $v) => '<a href="' . escape($v) . '" rel="noopener">' . escape(preg_replace('~^https?://(www\.)?~', '', $v)) . '</a>',
            'textarea' === $f->getTypeName() && $v !== strip_tags($v) => $v,   // HTML aus einem Editor-Feld (vom Backend gepflegt)
            default => nl2br(escape($v), false),
        };
    }

    /** @return array<string, string> Auswahl-Optionen (Wert => Beschriftung) – JSON {"Label":"wert"} oder „Label=wert,…“ */
    private static function choices(Field $f): array
    {
        $raw = trim((string) $f->getElement('choices'));
        $out = [];
        $json = json_decode($raw, true);
        if (is_array($json)) {
            foreach ($json as $label => $value) {
                $out[(string) $value] = (string) $label;
            }

            return $out;
        }
        foreach (explode(',', $raw) as $pair) {
            [$label, $value] = array_pad(explode('=', $pair, 2), 2, null);
            $out[trim((string) ($value ?? $label))] = trim((string) $label);
        }

        return $out;
    }

    /** Bearbeiten im Backend (YForm › Daten) – für angemeldete Benutzer mit Recht auf die Tabelle */
    public static function editUrl(Table $table, ?int $id = null): ?string
    {
        $user = Editing::user();
        if (!$user || !$table->isGranted('EDIT', $user)) {
            return null;
        }

        // über YFormEditApi: Token für func=edit/add gilt nur, wenn es im Backend erzeugt wird
        return Url::backendPage('yform/manager/data_edit', ['table_name' => $table->getTableName(), 'rex-api-call' => 'fluid_yform_edit'] + ($id ? ['data_id' => $id] : []));
    }
}

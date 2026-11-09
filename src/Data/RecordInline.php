<?php

namespace FriendsOfRedaxo\Fluid\Data;

use FriendsOfRedaxo\Fluid\ApiError;
use FriendsOfRedaxo\Fluid\Editing;
use FriendsOfRedaxo\Fluid\Routing\Router;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Security\User;
use Redaxo\Core\Translation\I18n;
use Yakamara\YForm\Data\Record;
use Yakamara\YForm\Field\Article as ArticleField;
use Yakamara\YForm\Field\Checkbox;
use Yakamara\YForm\Field\Choice;
use Yakamara\YForm\Field\CreatedAt;
use Yakamara\YForm\Field\Date;
use Yakamara\YForm\Field\DateTime;
use Yakamara\YForm\Field\Decimal;
use Yakamara\YForm\Field\Email;
use Yakamara\YForm\Field\Field;
use Yakamara\YForm\Field\Integer;
use Yakamara\YForm\Field\Media;
use Yakamara\YForm\Field\Text;
use Yakamara\YForm\Field\Textarea;
use Yakamara\YForm\Field\Time;
use Yakamara\YForm\Field\Url;
use Yakamara\YForm\Table\Table;
use Yakamara\YForm\Validation\ValidationException;

use function implode;
use function is_array;
use function json_encode;
use function preg_replace;
use function Redaxo\Core\View\escape;
use function str_replace;
use function trim;

use const JSON_UNESCAPED_UNICODE;

/**
 * Felder eines Datensatzes direkt in der Detailansicht bearbeiten (Datenliste mit Detailansicht). Im Bearbeitungsmodus
 * sind Texte auf der Seite editierbar; Adresse, E-Mail, Datum, Zeit, Zahl und Auswahl öffnen ein kleines Eingabefeld an
 * der Stelle, Ja/Nein schaltet per Klick, Bild und Seitenlink öffnen die Auswahl (Medienpool, Linkmap). Gespeichert wird
 * sofort über die Record-API von YForm (`fromInput()` wie das Formular, Prüfungen der Tabelle greifen). Relationen,
 * Uploads und das Feld „Fluid-Blöcke“ bleiben der YForm-Maske im Overlay („Eintrag bearbeiten“) bzw. den Blöcken.
 *
 * Datensätze kennen keinen Entwurf: Änderungen sind sofort gespeichert, wie bei den Blöcken eines Datensatzes.
 */
final class RecordInline
{
    /** Art der Bearbeitung auf der Seite, null = nur in der Maske */
    public static function kind(?Field $field): ?string
    {
        return match (true) {
            null === $field, $field instanceof CreatedAt => null,
            Text::class === $field::class => 'text',
            Textarea::class === $field::class => 'multi',
            $field instanceof Url => 'url',
            $field instanceof Email => 'email',
            $field instanceof DateTime => 'datetime',
            $field instanceof Date => 'date',
            $field instanceof Time => 'time',
            $field instanceof Integer => 'int',
            $field instanceof Decimal => 'dec',
            $field instanceof Checkbox => 'check',
            $field instanceof Choice => 'choice',
            $field instanceof Media && !$field->multiple => 'media',
            $field instanceof ArticleField && !$field->multiple => 'article',
            default => null,
        };
    }

    public static function supports(?Field $field): bool
    {
        return null !== self::kind($field);
    }

    /** Attribute für ein editierbares Feld – leer, wenn nicht bearbeitet wird oder das Recht fehlt */
    public static function attrs(Table $table, Record $record, ?Field $field): string
    {
        $kind = self::kind($field);
        if (null === $kind || !Editing::active() || Editing::isGuest() || !RecordBlocks::canEdit(Editing::user(), $table)) {
            return '';
        }
        $value = $record->get($field->name);
        $attrs = [
            'data-rec-field' => $table->definition->name . '~' . $record->id . '~' . $field->name,
            'data-rec-label' => $field->translatedLabel(),
            'data-rec-kind' => $kind,
        ];
        if ('multi' === $kind) {
            $attrs['data-rec-multi'] = '';
        }
        // Wert im Format der Eingabe (wie das Formular von YForm)
        $attrs['data-rec-value'] = match ($kind) {
            'text', 'multi' => null,
            'date', 'datetime' => $field instanceof Date ? $field->inputValue($value) : '',
            'check' => $value ? '1' : '0',
            'choice' => json_encode(array_values(array_map('strval', array_filter(is_array($value) ? $value : [$value], static fn ($v) => null !== $v && '' !== $v)))),
            default => is_array($value) ? implode(',', $value) : (string) $value,
        };
        if ($field instanceof Choice) {
            $attrs['data-rec-options'] = json_encode(array_map(static fn ($k, $v) => [(string) $k, I18n::translate((string) $v, false)], array_keys($field->flatOptions()), $field->flatOptions()), JSON_UNESCAPED_UNICODE);
            if ($field->multiple) {
                $attrs['data-rec-many'] = '';
            }
        }
        if ($field instanceof Media && [] !== $field->types) {
            $attrs['data-rec-types'] = implode(',', $field->types);
        }

        $out = '';
        foreach ($attrs as $k => $v) {
            if (null !== $v) {
                $out .= ' ' . $k . ('' === $v && 'data-rec-value' !== $k ? '' : '="' . escape((string) $v) . '"');
            }
        }

        return $out;
    }

    /**
     * API „record_field“: ein Feld speichern, Ausgabe zurück – beim Titelfeld der Detailansicht auch die neue Adresse.
     *
     * @return array{html: string, text: string, url?: string}
     */
    public static function save(User $user, Article $article): array
    {
        $ref = explode('~', Request::post('field', 'string', ''));
        if (3 !== \count($ref)) {
            throw new ApiError('Feld nicht gefunden.');
        }
        [$tname, $rid, $fname] = $ref;
        $table = YFormBridge::table($tname) ?? throw new ApiError('Tabelle nicht gefunden.');
        if (Editing::isGuest() || !RecordBlocks::canEdit($user, $table)) {
            throw new ApiError('Dafür fehlt Ihnen das Recht, diese YForm-Tabelle zu bearbeiten.');
        }
        $field = $table->getField($fname);
        $kind = self::kind($field) ?? throw new ApiError('Dieses Feld lässt sich nur in der Maske bearbeiten.');
        $record = $table::query()->where('id', (int) $rid)->first() ?? throw new ApiError('Eintrag nicht gefunden.');

        $input = Request::post('value', 'string', '');
        $input = match ($kind) {
            'text' => trim((string) preg_replace('/\s+/u', ' ', $input)),
            'multi' => trim((string) preg_replace("/\n{3,}/", "\n\n", str_replace("\r\n", "\n", $input))),
            'url', 'email' => trim($input),
            // Mehrfachauswahl: Werte als JSON-Liste
            'choice' => $field instanceof Choice && $field->multiple ? (array) (json_decode($input, true) ?: []) : $input,
            default => $input,
        };
        $record->set($fname, $field->fromInput($input));
        try {
            $record->save();
        } catch (ValidationException $e) {
            throw new ApiError(implode(' ', $e->errors->for($fname) ?: $e->errors->all()) ?: 'Der Wert ist nicht gültig.');
        }

        $out = ['html' => YFormBridge::html($record, $field), 'text' => YFormBridge::text($record, $field)];
        $cfg = Detail::config($article->id, $article->languageId);
        if ($cfg && $cfg['table'] === $tname && YFormBridge::field($table, (string) ($cfg['title_field'] ?? ''))?->name === $fname) {
            $out['url'] = (string) parse_url(Detail::url(Router::url($article->id, $article->languageId), $out['text'], $record->id), PHP_URL_PATH);
        }

        return $out;
    }
}

<?php

namespace FriendsOfRedaxo\Fluid\Data;

use FriendsOfRedaxo\Fluid\ApiError;
use FriendsOfRedaxo\Fluid\Editing;
use FriendsOfRedaxo\Fluid\Routing\Router;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Security\User;
use Yakamara\YForm\Data\Record;
use Yakamara\YForm\Field\Field;
use Yakamara\YForm\Field\Text;
use Yakamara\YForm\Field\Textarea;
use Yakamara\YForm\Table\Table;
use Yakamara\YForm\Validation\ValidationException;

use function implode;
use function preg_replace;
use function Redaxo\Core\View\escape;
use function str_replace;
use function trim;

/**
 * Textfelder eines Datensatzes direkt in der Detailansicht bearbeiten (Datenliste mit Detailansicht): Titel und
 * einfache Textfelder sind im Bearbeitungsmodus editierbar, gespeichert wird beim Verlassen des Feldes über die
 * Record-API von YForm (Prüfungen der Tabelle greifen). Alle übrigen Felder – Bild, Datum, Auswahl, Relationen –
 * bearbeitet die YForm-Maske im Overlay („Eintrag bearbeiten“).
 *
 * Datensätze kennen keinen Entwurf: Änderungen sind sofort gespeichert, wie bei den Blöcken eines Datensatzes.
 */
final class RecordInline
{
    /** Feld lässt sich auf der Seite bearbeiten: Textzeile (ohne E-Mail, URL …) oder einfacher Textbereich */
    public static function supports(?Field $field): bool
    {
        return null !== $field && (Text::class === $field::class || Textarea::class === $field::class);
    }

    /** Attribute für ein editierbares Feld – leer, wenn nicht bearbeitet wird oder das Recht fehlt */
    public static function attrs(Table $table, Record $record, ?Field $field): string
    {
        if (!self::supports($field) || !Editing::active() || Editing::isGuest() || !RecordBlocks::canEdit(Editing::user(), $table)) {
            return '';
        }

        return ' data-rec-field="' . escape($table->definition->name . '~' . $record->id . '~' . $field->name) . '"'
            . ' data-rec-label="' . escape($field->translatedLabel()) . '"'
            . ($field instanceof Textarea ? ' data-rec-multi' : '');
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
        if (!self::supports($field)) {
            throw new ApiError('Dieses Feld lässt sich nur in der Maske bearbeiten.');
        }
        $record = $table::query()->where('id', (int) $rid)->first() ?? throw new ApiError('Eintrag nicht gefunden.');

        $value = str_replace("\r\n", "\n", Request::post('value', 'string', ''));
        $value = $field instanceof Textarea
            ? trim((string) preg_replace("/\n{3,}/", "\n\n", $value))
            : trim((string) preg_replace('/\s+/u', ' ', $value));
        $record->set($fname, $value);
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

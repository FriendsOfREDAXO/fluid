<?php

namespace FriendsOfRedaxo\Fluid\Form;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Language\Language;
use Redaxo\Core\MediaManager\MediaManager;
use Redaxo\Core\MediaPool\Media;

use function in_array;
use function Redaxo\Core\View\escape;

/**
 * Auswahl von Medien (Bilder, Dateien) und Artikeln – im Backend und im Editor auf der Website, ohne Popup-Fenster.
 * Im Backend öffnet assets/form.js Medienpool bzw. Linkmap des Cores als Overlay (rex.pick), auf der Website eine eigene
 * Auswahl im Editor (Media\Library, Links).
 *
 * Link-Felder speichern `redaxo://ID` (Artikel) oder eine Adresse (https://…, mailto:, tel:, #anker).
 */
final class Picker
{
    public const array IMAGE_TYPES = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'];
    public const array VIDEO_TYPES = ['mp4', 'm4v', 'mov', 'webm', 'mkv', 'ogv', 'avi'];

    /** @param list<string>|null $types erlaubte Endungen (Feldoption `types`, z. B. ['vtt', 'srt']) */
    public static function media(string $id, string $name, string $value, bool $images, bool $backend, ?array $types = null): string
    {
        $types ??= $images ? self::IMAGE_TYPES : null;
        $preview = MediaManager::getUrl('rex_media_small', '__FILE__');
        $media = '' !== $value ? Media::get($value) : null;
        $thumb = $media && in_array(strtolower($media->getExtension()), self::IMAGE_TYPES, true) ? '<img src="' . escape(self::thumb($media->fileName)) . '" alt="">' : '';
        // Addon image_editor: „Bearbeiten“ öffnet den Bildeditor (Zuschneiden, Drehen …) – API im jeweiligen Bereich
        $ie = $images && class_exists(\FriendsOfRedaxo\ImageEditor\ImageEditorAddon::class) ? \FriendsOfRedaxo\ImageEditor\ImageEditorAddon::config() : null;
        // Addon video_editor: „Video bearbeiten“ öffnet das Video im Medienpool (Optimieren, Poster …) – nur mit Recht
        $ve = !$images && (null === $types || [] !== array_intersect($types, self::VIDEO_TYPES)) && class_exists(\FriendsOfRedaxo\VideoEditor\VideoEditor::class)
            ? \FriendsOfRedaxo\VideoEditor\VideoEditor::editUrlTemplate() : null;

        return '<div class="blocks-pick blocks-pick--media" data-blocks-pick="media" data-types="' . escape(implode(',', $types ?? [])) . '" data-preview="' . escape($preview) . '"'
            . ($ie ? ' data-ie-api="' . escape($ie['api']) . '" data-ie-js="' . escape($ie['js']) . '" data-ie-css="' . escape($ie['css']) . '"' : '')
            . ($ve ? ' data-ve-edit="' . escape($ve) . '"' : '') . '>'
            . '<div class="blocks-pick__row">'
            . '<span class="blocks-pick__thumb" data-blocks-pick-thumb' . ('' === $thumb ? ' hidden' : '') . '>' . $thumb . '</span>'
            . self::group($backend, '<input class="rex-input" type="text" id="' . escape($id) . '" name="' . escape($name) . '" value="' . escape($value) . '" placeholder="Datei aus dem Medienpool">', 'Medien auswählen', 'rex-icon-open-mediapool', 'Auswählen', null !== $ie, null !== $ve)
            . '</div></div>';
    }

    public static function link(string $id, string $name, string $value, bool $backend): string
    {
        $art = preg_match('~^redaxo://(\d+)~', $value, $m) ? Article::get((int) $m[1]) : null;
        $category = $art->categoryId ?? Category::getCurrent()->id ?? 0;

        return '<div class="blocks-pick blocks-pick--link" data-blocks-pick="link" data-category="' . $category . '" data-language="' . Language::getCurrentId() . '">'
            . '<div class="blocks-pick__row">'
            . self::group($backend, '<input class="rex-input" type="text" id="' . escape($id) . '" name="' . escape($name) . '" value="' . escape($value) . '" placeholder="Seite, https://…, E-Mail, tel: oder #anker">', 'Seite auswählen', 'rex-icon-open-linkmap', 'Seite wählen')
            . '</div>'
            . '<span class="blocks-pick__name" data-blocks-pick-name>' . escape($art ? $art->name . ' [' . $art->id . ']' : '') . '</span>'
            . '</div>';
    }

    /** Vorschaubild (mit Addon image_editor die bearbeitete Fassung) */
    public static function thumb(string $file): string
    {
        return class_exists(\FriendsOfRedaxo\ImageEditor\ImageEditor::class)
            ? \FriendsOfRedaxo\ImageEditor\ImageEditor::url($file, 320)
            : MediaManager::getUrl('rex_media_small', $file);
    }

    /** Eingabefeld mit Knöpfen – im Backend mit den Klassen der Core-Widgets (rex-input-group), auf der Website schlicht */
    private static function group(bool $backend, string $input, string $title, string $icon, string $label, bool $edit = false, bool $video = false): string
    {
        if ($backend) {
            $btn = static fn (string $attr, string $title, string $icon): string => '<button type="button" class="rex-button" data-variant="field" ' . $attr
                . ' title="' . escape($title) . '" aria-label="' . escape($title) . '"><i class="rex-icon ' . $icon . '" aria-hidden="true"></i></button>';

            return '<div class="blocks-pick__field"><div class="rex-input-group">' . $input . '<span class="rex-input-group-actions">'
                . $btn('data-blocks-pick-open', $title, $icon)
                . ($edit ? $btn('data-blocks-pick-edit', 'Bild bearbeiten (Zuschneiden, Drehen …)', 'fa-solid fa-crop-simple')
                    . $btn('data-blocks-pick-adjust', 'Bild anpassen (Effekte, Fokuspunkt)', 'fa-solid fa-sliders') : '')
                . ($video ? $btn('data-blocks-pick-video', 'Video bearbeiten (Optimieren, Poster …) – öffnet den Medienpool', 'fa-solid fa-film') : '')
                . $btn('data-blocks-pick-clear', 'Entfernen', 'rex-icon-delete-media')
                . '</span></div></div>';
        }

        return $input . '<span class="blocks-pick__btns">'
            . '<button type="button" class="blocks-btn" data-blocks-pick-open title="' . escape($title) . '">' . escape($label) . '</button>'
            . ($edit ? '<button type="button" class="blocks-btn" data-blocks-pick-edit title="Bild bearbeiten (Zuschneiden, Drehen …)">Bearbeiten</button>'
                . '<button type="button" class="blocks-btn" data-blocks-pick-adjust title="Bild anpassen (Effekte, Fokuspunkt)">Anpassen</button>' : '')
            . ($video ? '<button type="button" class="blocks-btn" data-blocks-pick-video title="Video bearbeiten (Optimieren, Poster …) – öffnet den Medienpool">Video bearbeiten</button>' : '')
            . '<button type="button" class="blocks-btn" data-blocks-pick-clear title="Entfernen" aria-label="Entfernen">✕</button>'
            . '</span>';
    }
}

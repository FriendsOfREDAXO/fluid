<?php

namespace FriendsOfRedaxo\Blocks\Form;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Language\Language;
use Redaxo\Core\MediaManager\MediaManager;
use Redaxo\Core\MediaPool\Media;

use function in_array;
use function Redaxo\Core\View\escape;

/**
 * Auswahl über die REDAXO-Popups: Medienpool (Bilder, Dateien) und Linkmap (Artikel) – im Backend und im Editor auf der Website.
 * Die Popups melden die Auswahl mit `rex:selectMedia` bzw. `rex:selectLink` an das öffnende Fenster (assets/form.js).
 *
 * Link-Felder speichern `redaxo://ID` (Artikel) oder eine Adresse (https://…, mailto:, tel:, #anker).
 */
final class Picker
{
    public const array IMAGE_TYPES = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'];

    public static function media(string $id, string $name, string $value, bool $images, bool $backend): string
    {
        $url = Url::backendPage('mediapool/media', ['opener_input_field' => 'blocks_pick'] + ($images ? ['types' => implode(',', self::IMAGE_TYPES)] : []));
        $preview = MediaManager::getUrl('rex_media_small', '__FILE__');
        $media = '' !== $value ? Media::get($value) : null;
        $thumb = $media && in_array(strtolower($media->getExtension()), self::IMAGE_TYPES, true) ? '<img src="' . escape(MediaManager::getUrl('rex_media_small', $media)) . '" alt="">' : '';

        return '<div class="blocks-pick blocks-pick--media" data-blocks-pick="media" data-url="' . escape($url) . '" data-preview="' . escape($preview) . '">'
            . '<div class="blocks-pick__row">'
            . '<span class="blocks-pick__thumb" data-blocks-pick-thumb' . ('' === $thumb ? ' hidden' : '') . '>' . $thumb . '</span>'
            . self::group($backend, '<input class="form-control" type="text" id="' . escape($id) . '" name="' . escape($name) . '" value="' . escape($value) . '" placeholder="Datei aus dem Medienpool">', 'Medienpool öffnen', 'rex-icon-open-mediapool', 'Medienpool')
            . '</div></div>';
    }

    public static function link(string $id, string $name, string $value, bool $backend): string
    {
        $art = preg_match('~^redaxo://(\d+)~', $value, $m) ? Article::get((int) $m[1]) : null;
        $url = Url::backendPage('linkmap', [
            'opener_input_field' => 'blocks_pick',
            'category_id' => $art?->categoryId ?? Category::getCurrent()?->id ?? 0,
            'language' => Language::getCurrentId(),
        ]);

        return '<div class="blocks-pick blocks-pick--link" data-blocks-pick="link" data-url="' . escape($url) . '">'
            . '<div class="blocks-pick__row">'
            . self::group($backend, '<input class="form-control" type="text" id="' . escape($id) . '" name="' . escape($name) . '" value="' . escape($value) . '" placeholder="Artikel (Linkmap), https://…, E-Mail, tel: oder #anker">', 'Linkmap öffnen', 'rex-icon-open-linkmap', 'Linkmap')
            . '</div>'
            . '<span class="blocks-pick__name" data-blocks-pick-name>' . escape($art ? $art->name . ' [' . $art->id . ']' : '') . '</span>'
            . '</div>';
    }

    /** Eingabefeld mit Knöpfen – im Backend als Bootstrap-Gruppe wie die REDAXO-Widgets */
    private static function group(bool $backend, string $input, string $title, string $icon, string $label): string
    {
        if ($backend) {
            return '<div class="input-group">' . $input . '<span class="input-group-btn">'
                . '<button type="button" class="btn btn-popup" data-blocks-pick-open title="' . escape($title) . '"><i class="rex-icon ' . $icon . '"></i></button>'
                . '<button type="button" class="btn btn-popup" data-blocks-pick-clear title="Entfernen"><i class="rex-icon rex-icon-delete-media"></i></button>'
                . '</span></div>';
        }

        return $input . '<span class="blocks-pick__btns">'
            . '<button type="button" class="blocks-btn" data-blocks-pick-open title="' . escape($title) . '">' . escape($label) . '</button>'
            . '<button type="button" class="blocks-btn" data-blocks-pick-clear title="Entfernen" aria-label="Entfernen">✕</button>'
            . '</span>';
    }
}

<?php

namespace FriendsOfRedaxo\Fluid\Picker;

use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Security\BackendLogin;

use function Redaxo\Core\View\escape;

/**
 * Medien- und Seitenauswahl für die Website – das Gegenstück zu `rex.pick()` im Backend, für jedes AddOn nutzbar.
 *
 * Einbinden (nur für angemeldete Backend-Benutzer): `echo FrontendPicker::head();` im `<head>` bzw. vor `</body>`.
 * Aufruf im Browser, mit derselben Antwort wie `rex.pick()`:
 *
 *     const [media] = await FrontendPicker.pick('media', { types: ['jpg', 'png'] });
 *     const [page] = await FrontendPicker.pick('article', { category: 5 });   // page.value = Artikel-ID
 *
 * Eigenständig angelegt (Picker\*, assets/picker), damit es später ein eigenes AddOn werden kann.
 */
final class FrontendPicker
{
    /** Skript und Konfiguration; leer ohne Backend-Sitzung */
    public static function head(?int $languageId = null): string
    {
        if (!BackendLogin::createUser()) {
            return '';
        }
        $assets = Addon::require('fluid')->getAssetsUrl('picker/');
        $config = [
            'api' => Url::frontendController(PickerApi::getUrlParams()),
            'css' => $assets . 'picker.css?v=' . self::version('picker.css'),
            'language' => $languageId ?? Language::getCurrentId(),
        ];

        return '<script type="application/json" id="frontend-picker-config">'
            . json_encode($config, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>'
            . '<script src="' . escape($assets . 'picker.js?v=' . self::version('picker.js')) . '" defer></script>';
    }

    private static function version(string $file): string
    {
        $path = Addon::require('fluid')->getPath('assets/picker/' . $file);

        return is_file($path) ? substr(md5((string) filemtime($path)), 0, 8) : '0';
    }
}

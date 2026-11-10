<?php

namespace FriendsOfRedaxo\Fluid;

use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionLevel;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Http\Response;

use function implode;
use function md5;
use function preg_split;
use function str_contains;
use function str_replace;
use function stripos;
use function substr;
use function substr_replace;
use function trim;

use function Redaxo\Core\View\escape;

/**
 * Keine style-Attribute (Content-Security-Policy, im Backend erzwungen): Werte, die sich nicht über feste Klassen
 * abbilden lassen (Fokuspunkt eines Bildes, Rasterbreite …), bekommen eine Klasse – die Regeln stehen gesammelt in
 * einem <style nonce> im Kopf der Seite (OUTPUT_FILTER, Website und Backend).
 *
 *     '<img class="' . InlineStyle::class('object-position:30% 40%') . '" …>'
 *
 * Die Regeln tragen !important, damit sie wie vorher die style-Attribute über den Stylesheets stehen.
 */
final class InlineStyle
{
    /** @var array<string, string> Klasse → Deklarationen */
    private static array $rules = [];

    private static bool $booted = false;

    /** Klasse für diese Deklarationen ('' bei leeren) */
    public static function class(string $declarations): string
    {
        $declarations = trim($declarations, " ;\t\n");
        if ('' === $declarations) {
            return '';
        }
        $class = 'fs-' . substr(md5($declarations), 0, 10);
        self::$rules[$class] = $declarations;
        self::boot();

        return $class;
    }

    /** Attribut class="…" (leer bei leeren Deklarationen), mit weiteren Klassen */
    public static function attr(string $declarations, string $classes = ''): string
    {
        $all = trim($classes . ' ' . self::class($declarations));

        return '' !== $all ? ' class="' . escape($all) . '"' : '';
    }

    /** <style nonce> mit allen Regeln – leer, wenn keine anfielen */
    public static function tag(): string
    {
        if (!self::$rules) {
            return '';
        }
        $css = '';
        foreach (self::$rules as $class => $declarations) {
            $parts = [];
            foreach (preg_split('~;(?![^(]*\))~', $declarations) ?: [] as $d) {
                $d = trim($d);
                if ('' !== $d) {
                    $parts[] = str_contains($d, '!important') ? $d : $d . ' !important';
                }
            }
            $css .= '.' . $class . '{' . str_replace('</', '<\/', implode(';', $parts)) . '}';
        }
        self::$rules = [];

        return '<style nonce="' . escape(Response::getNonce()) . '">' . $css . '</style>';
    }

    private static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;
        Extension::register('OUTPUT_FILTER', static function (ExtensionPoint $ep): string {
            $html = (string) $ep->subject;
            $tag = self::tag();
            if ('' === $tag) {
                return $html;
            }
            $pos = stripos($html, '</head>');
            $pos = false !== $pos ? $pos : stripos($html, '</body>');

            return false !== $pos ? substr_replace($html, $tag, $pos, 0) : $html . $tag;
        }, ExtensionLevel::Late);
    }
}

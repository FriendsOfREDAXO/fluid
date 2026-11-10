<?php

namespace FriendsOfRedaxo\Fluid\Routing;

use InvalidArgumentException;

use function in_array;
use function preg_match;
use function sprintf;
use function str_ends_with;
use function strlen;
use function strtolower;
use function substr;

/**
 * Endung der Adressen. Gespeichert werden Pfade immer ohne Endung – das Format kommt erst beim Erzeugen dazu und fällt
 * beim Auflösen wieder weg. Ein Wechsel braucht deshalb keinen Neuaufbau: alte Schreibweisen leiten per 301 um.
 *
 *     UrlFormat::none()            /leistungen/beratung          (Vorgabe)
 *     UrlFormat::slash()           /leistungen/beratung/
 *     UrlFormat::html()            /leistungen/beratung.html
 *     UrlFormat::mixed()           /leistungen/  ·  /leistungen/beratung.html   (Menüpunkte mit „/“, Seiten mit „.html“)
 *     UrlFormat::suffix('.htm')    eigene Endung
 *
 * Startseiten (/ und /en/) haben nie eine Endung. Für ganz eigene Regeln lässt sich die Klasse ableiten (append/strip).
 */
readonly class UrlFormat
{
    /** Endungen, die beim Auflösen immer erkannt werden (für den Wechsel von bzw. zu .html) */
    public const array KNOWN = ['.html', '.htm'];

    /**
     * @param string $page Endung von Seiten: '' (keine), '/' oder '.' + Buchstaben/Ziffern
     * @param string|null $category Endung von Menüpunkten (Startartikel einer Kategorie); null = wie Seiten
     */
    public function __construct(
        public string $page = '',
        public ?string $category = null,
    ) {
        foreach ([$page, $category] as $s) {
            if (null !== $s && !in_array($s, ['', '/'], true) && !preg_match('~^\.[a-z0-9]{1,10}$~', $s)) {
                throw new InvalidArgumentException(sprintf('Ungültige Endung „%s“: erlaubt sind "", "/" oder z. B. ".html".', $s));
            }
        }
    }

    public static function none(): self
    {
        return new self();
    }

    public static function slash(): self
    {
        return new self('/');
    }

    public static function html(): self
    {
        return new self('.html');
    }

    public static function mixed(): self
    {
        return new self('.html', '/');
    }

    public static function suffix(string $suffix): self
    {
        return new self($suffix);
    }

    /** Pfad (ohne Schrägstrich außen, nicht leer) mit Endung */
    public function append(string $path, bool $category = false): string
    {
        return '' === $path ? '' : $path . ($category ? $this->category ?? $this->page : $this->page);
    }

    /** Pfad ohne Schrägstrich am Ende und ohne bekannte Endung – Schlüssel für die Suche */
    public function strip(string $path): string
    {
        $path = rtrim($path, '/');
        $lower = strtolower($path);
        foreach ([$this->page, (string) $this->category, ...self::KNOWN] as $s) {
            if (strlen($s) > 1 && str_ends_with($lower, $s)) {
                return substr($path, 0, -strlen($s));
            }
        }

        return $path;
    }
}

<?php

namespace FriendsOfRedaxo\Fluid\Routing\Seo;

use function implode;
use function in_array;
use function Redaxo\Core\View\escape;

/**
 * Ein Tag im Seitenkopf. Tags mit demselben Schlüssel ersetzen sich – so kann ein AddOn oder das Projekt jede Angabe
 * des Routings überschreiben (z. B. og:title) oder eigene ergänzen:
 *
 *     HeadTag::meta('theme-color', '#0a5')
 *     HeadTag::property('og:video', 'https://…')
 *     HeadTag::link('preconnect', 'https://fonts.example.org')
 *     HeadTag::html('matomo', '<script src="…" defer></script>')   nur aus Code, nie aus Eingaben von Redakteuren
 */
final readonly class HeadTag
{
    /** link-Arten, die es je Seite nur einmal gibt (Schlüssel ohne Adresse) */
    private const array UNIQUE_LINKS = ['canonical', 'icon', 'apple-touch-icon', 'manifest', 'license', 'author'];

    /** @param array<string, string|int|bool|null> $attrs */
    private function __construct(
        public string $key,
        public string $tag,
        public array $attrs = [],
        public ?string $html = null,
    ) {}

    public static function meta(string $name, string $content): self
    {
        return new self('name:' . $name, 'meta', ['name' => $name, 'content' => $content]);
    }

    public static function property(string $property, string $content): self
    {
        return new self('property:' . $property, 'meta', ['property' => $property, 'content' => $content]);
    }

    /** @param array<string, string|int|bool|null> $attrs weitere Attribute (hreflang, type, title, sizes …) */
    public static function link(string $rel, string $href, array $attrs = []): self
    {
        $key = 'link:' . $rel . (isset($attrs['hreflang']) ? ':' . $attrs['hreflang'] : (in_array($rel, self::UNIQUE_LINKS, true) ? '' : ':' . $href));

        return new self($key, 'link', ['rel' => $rel, 'href' => $href] + $attrs);
    }

    /** Fertiges HTML unter einem eigenen Schlüssel – nur für Code (Projekt, AddOns), nicht für Eingaben */
    public static function html(string $key, string $html): self
    {
        return new self('html:' . $key, 'html', [], $html);
    }

    /** Tag entfernen: derselbe Schlüssel, leerer Inhalt (z. B. HeadTag::remove('property:og:image')) */
    public static function remove(string $key): self
    {
        return new self($key, 'remove');
    }

    public function render(): string
    {
        if ('html' === $this->tag) {
            return (string) $this->html;
        }
        if ('remove' === $this->tag) {
            return '';
        }
        $a = [];
        foreach ($this->attrs as $k => $v) {
            if (null === $v || false === $v) {
                continue;
            }
            $a[] = true === $v ? escape($k) : escape($k) . '="' . escape((string) $v) . '"';
        }

        return '<' . $this->tag . ' ' . implode(' ', $a) . '>';
    }
}

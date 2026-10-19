<?php

namespace FriendsOfRedaxo\Fluid;

use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;

use function array_key_exists;
use function in_array;
use function is_file;
use function Redaxo\Core\View\escape;

/**
 * Style-Editor (aus KLXM Studio, Core\Design): Das Kit erklärt in design.php, was sich einstellen lässt
 * (Gruppen mit Tokens, Schriften, Vorlagen, Dunkel-Modus); daraus entstehen CSS-Variablen und Klassen am <html>.
 *
 * Token-Typen: color (#RRGGBB, optional Dunkel-Wert „name@dark“), range (Zahl + unit), choice (options; 'values' → Variable,
 * 'class' → Klasse am <html>), bool ('class' bzw. Variable 1/0), font (Schlüssel aus 'fonts' → Schrift-Stapel).
 * Schriften mit 'fontsource' => id liegen selbst gehostet unter assets/fonts/{id}/font.css (OFL, siehe LICENSE.txt je Schrift).
 *
 * Gespeichert in der Konfiguration des Addons („design.{kit}“), Ausgabe als Datei assets/fluid-design/design-{kit}-{hash}.css.
 */
final class Design
{
    /** @var array<string, array<string, mixed>> */
    private static array $values = [];

    public function __construct(private readonly Kit $kit) {}

    public static function for(Kit $kit): self
    {
        return new self($kit);
    }

    /** @return array<string, mixed> */
    public function def(): array
    {
        /** @var array<string, mixed> */
        return $this->kit->definition()['design'] ?? [];
    }

    /** @return array<string, array<string, mixed>> */
    public function tokens(): array
    {
        $out = [];
        foreach ((array) ($this->def()['groups'] ?? []) as $g) {
            foreach ((array) ($g['tokens'] ?? []) as $t) {
                if (!empty($t['name'])) {
                    $out[(string) $t['name']] = $t + ['group' => $g['id'] ?? ''];
                }
            }
        }

        return $out;
    }

    /** @return array<string, array<string, mixed>> Schriften mit 'href', wenn selbst gehostet vorhanden */
    public function fonts(): array
    {
        $fonts = (array) ($this->def()['fonts'] ?? []);
        foreach ($fonts as $k => $f) {
            $id = (string) ($f['fontsource'] ?? '');
            if ('' !== $id && is_file(FluidAddon::instance()->getPath('assets/fonts/' . $id . '/font.css'))) {
                $fonts[$k]['href'] = FluidAddon::instance()->getAssetsUrl('fonts/' . $id . '/font.css');
            }
        }

        return $fonts;
    }

    /**
     * Gespeicherte Werte, ergänzt um Standardwerte. Ein Kit ohne eigene Werte übernimmt die seines Eltern-Kits
     * (Farben, Schriften …) – so sieht die Website nach dem Wechsel auf ein abgeleitetes Kit nicht plötzlich anders aus.
     *
     * @return array<string, mixed>
     */
    public function values(): array
    {
        return self::$values[$this->kit->key()] ??= $this->normalize($this->stored());
    }

    /** @return array<string, mixed> */
    private function stored(): array
    {
        foreach ($this->kit->lineage() as $kit) {
            $v = FluidAddon::instance()->getConfig('design.' . $kit->key());
            if (\is_array($v) && [] !== $v) {
                return $v;
            }
        }

        return [];
    }

    public function get(string $name): mixed
    {
        return $this->values()[$name] ?? ($this->tokens()[$name]['default'] ?? null);
    }

    /**
     * Eingaben auf erlaubte Werte begrenzen; Unbekanntes fällt weg.
     *
     * @param array<string, mixed> $in
     * @return array<string, mixed>
     */
    public function normalize(array $in): array
    {
        $out = [];
        $fonts = $this->fonts();
        foreach ($this->tokens() as $n => $t) {
            $v = $in[$n] ?? null;
            $out[$n] = match ($t['type'] ?? 'color') {
                'color' => self::color($v) ?? self::color($t['default'] ?? '') ?? '#000000',
                'range' => is_numeric($v) ? max((float) ($t['min'] ?? 0), min((float) ($t['max'] ?? 999), (float) $v)) : (float) ($t['default'] ?? 0),
                'choice' => array_key_exists((string) $v, (array) ($t['options'] ?? [])) ? (string) $v : (string) ($t['default'] ?? array_key_first((array) ($t['options'] ?? [])) ?? ''),
                'bool' => null === $v ? (bool) ($t['default'] ?? false) : in_array($v, [true, 1, '1', 'on', 'true'], true),
                'font' => isset($fonts[(string) $v]) ? (string) $v : (string) ($t['default'] ?? array_key_first($fonts) ?? ''),
                default => null,
            };
            if ('color' === ($t['type'] ?? '') && isset($t['dark'])) {
                $out[$n . '@dark'] = self::color($in[$n . '@dark'] ?? null) ?? self::color((string) $t['dark']) ?? $out[$n];
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return $this->normalize([]);
    }

    public static function color(mixed $v): ?string
    {
        $s = strtoupper(trim((string) $v));
        if (preg_match('~^#?([0-9A-F]{3})$~', $s, $m)) {
            $s = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
        }
        if ('' !== $s && '#' !== $s[0]) {
            $s = '#' . $s;
        }

        return preg_match('~^#[0-9A-F]{6}$~', $s) ? $s : null;
    }

    // ------------------------------------------------------------------ Ausgabe

    /** CSS mit allen Variablen (hell + dunkel) */
    public function css(?array $values = null): string
    {
        $values ??= $this->values();
        $fonts = $this->fonts();
        $light = $dark = [];
        foreach ($this->tokens() as $n => $t) {
            $v = $values[$n] ?? null;
            $var = (string) ($t['var'] ?? '');
            $css = match ($t['type'] ?? 'color') {
                'color' => $v,
                'range' => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.') . (string) ($t['unit'] ?? ''),
                'choice' => isset($t['values']) ? (string) ($t['values'][$v] ?? '') : null,
                'bool' => isset($t['var']) ? ($v ? '1' : '0') : null,
                'font' => (string) ($fonts[$v]['stack'] ?? ''),
                default => null,
            };
            if ('' !== $var && null !== $css && '' !== $css) {
                $light[] = $var . ':' . $css;
            }
            if ('' !== $var && isset($values[$n . '@dark'])) {
                $dark[] = $var . ':' . $values[$n . '@dark'];
            }
        }
        $out = ':root{' . implode(';', $light) . '}';
        $d = (array) ($this->def()['dark'] ?? []);
        if ($dark && !empty($d['scope'])) {
            $rule = $d['scope'] . '{' . implode(';', $dark) . '}';
            $out .= "\n" . (!empty($d['media']) ? '@media ' . $d['media'] . '{' . $rule . '}' : $rule);
        }

        return $out . "\n";
    }

    /** Klassen für <html> aus choice-/bool-Werten mit 'class' */
    public function classes(?array $values = null): string
    {
        $values ??= $this->values();
        $out = [];
        foreach ($this->tokens() as $n => $t) {
            if (empty($t['class'])) {
                continue;
            }
            $v = $values[$n] ?? null;
            if ('bool' === ($t['type'] ?? '')) {
                if ($v) {
                    $out[] = (string) $t['class'];
                }
                continue;
            }
            $out[] = str_replace('{value}', (string) preg_replace('~[^a-z0-9_-]~i', '', (string) $v), (string) $t['class']);
        }

        return implode(' ', $out);
    }

    /** @return list<string> Adressen der Schriftdateien (font.css) der gewählten Schriften */
    public function fontLinks(?array $values = null): array
    {
        $values ??= $this->values();
        $fonts = $this->fonts();
        $out = [];
        foreach ($this->tokens() as $n => $t) {
            if ('font' === ($t['type'] ?? '') && !empty($fonts[$values[$n] ?? '']['href'])) {
                $out[] = (string) $fonts[$values[$n]]['href'];
            }
        }

        return array_values(array_unique($out));
    }

    /** Öffentliche CSS-Datei der Werte → URL (null = Standardwerte des Kits, keine Datei nötig) */
    public function fileUrl(): ?string
    {
        if ($this->values() == $this->defaults()) {
            return null;
        }
        $css = $this->css();
        $name = 'design-' . $this->kit->key() . '-' . substr(sha1($css), 0, 10) . '.css';
        $file = Path::assets('fluid-design/' . $name);
        if (!is_file($file)) {
            foreach (glob(Path::assets('fluid-design/design-' . $this->kit->key() . '-*.css')) ?: [] as $old) {
                @unlink($old);
            }
            File::put($file, "/* Design-Werte (Style-Editor) – automatisch erzeugt */\n" . $css);
        }

        return Url::assets('fluid-design/' . $name);
    }

    /** <link>-Tags: Schriften + Design-Variablen (nach dem CSS des Kits einbinden) */
    public function head(): string
    {
        $h = '';
        foreach ($this->fontLinks() as $href) {
            $h .= '<link rel="stylesheet" href="' . escape($href) . '" data-fluid-font>' . "\n";
        }
        $url = $this->fileUrl();

        return $h . ($url ? '<link rel="stylesheet" href="' . escape($url) . '" data-fluid-design>' . "\n" : '');
    }

    /** @param array<string, mixed> $values */
    public function save(array $values): void
    {
        $clean = $this->normalize($values);
        FluidAddon::instance()->setConfig('design.' . $this->kit->key(), $clean);
        self::$values[$this->kit->key()] = $clean;
    }

    /** Definition für den Editor (Gruppen, Schriften, Vorlagen) – JSON-tauglich */
    public function schema(): array
    {
        $def = $this->def();

        return [
            'groups' => array_values(array_map(static fn ($g) => ['id' => (string) ($g['id'] ?? ''), 'label' => (string) ($g['label'] ?? ''),
                'tokens' => array_values(array_filter((array) ($g['tokens'] ?? []), static fn ($t) => !empty($t['name'])))], (array) ($def['groups'] ?? []))),
            'fonts' => array_map(static fn ($f) => ['label' => (string) ($f['label'] ?? ''), 'stack' => (string) ($f['stack'] ?? '')], $this->fonts()),
            'presets' => array_map(static fn ($p) => ['label' => (string) ($p['label'] ?? ''), 'description' => (string) ($p['description'] ?? ''), 'values' => (array) ($p['values'] ?? [])], (array) ($def['presets'] ?? [])),
            'values' => $this->values(),
            'defaults' => $this->defaults(),
        ];
    }

    /**
     * Kontrastprüfungen (WCAG) für Tokens mit 'contrast' => ['with' => Farbe|Token, 'min' => 4.5].
     *
     * @return list<array{token: string, mode: string, ratio: float, min: float, ok: bool}>
     */
    public function checks(?array $values = null): array
    {
        $values ??= $this->values();
        $tokens = $this->tokens();
        $resolve = static function (string $ref, bool $dark) use ($values, $tokens): ?string {
            if (isset($tokens[$ref])) {
                return $dark ? ($values[$ref . '@dark'] ?? $values[$ref] ?? null) : ($values[$ref] ?? null);
            }

            return self::color($ref);
        };
        $out = [];
        foreach ($tokens as $n => $t) {
            if ('color' !== ($t['type'] ?? 'color') || empty($t['contrast']['with'])) {
                continue;
            }
            $min = (float) ($t['contrast']['min'] ?? 4.5);
            foreach (['light', 'dark'] as $mode) {
                $fg = 'light' === $mode ? ($values[$n] ?? null) : ($values[$n . '@dark'] ?? null);
                $with = 'dark' === $mode ? (string) ($t['contrast']['dark_with'] ?? (isset($tokens[$t['contrast']['with']]) ? $t['contrast']['with'] : '')) : (string) $t['contrast']['with'];
                $bg = '' !== $with ? $resolve($with, 'dark' === $mode && isset($tokens[$with])) : null;
                if (!$fg || !$bg) {
                    continue;
                }
                $r = self::contrast((string) $fg, $bg);
                $out[] = ['token' => $n, 'mode' => $mode, 'ratio' => $r, 'min' => $min, 'ok' => $r >= $min];
            }
        }

        return $out;
    }

    /** Kontrast nach WCAG 2.x (1–21) */
    public static function contrast(string $a, string $b): float
    {
        $l = static function (string $hex): float {
            $hex = ltrim($hex, '#');
            $c = array_map(static fn ($i) => hexdec(substr($hex, $i, 2)) / 255, [0, 2, 4]);
            $c = array_map(static fn ($x) => $x <= 0.03928 ? $x / 12.92 : (($x + 0.055) / 1.055) ** 2.4, $c);

            return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
        };
        [$x, $y] = [$l($a), $l($b)];

        return round((max($x, $y) + 0.05) / (min($x, $y) + 0.05), 2);
    }
}

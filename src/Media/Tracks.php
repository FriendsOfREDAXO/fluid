<?php

namespace FriendsOfRedaxo\Fluid\Media;

use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\MediaPool\Media;

/**
 * Untertitel, Kapitel und Transkripte für Videos (Barrierefreiheit, WCAG 1.2.2/1.2.3) – Modell aus KLXM Studio (MediaTracks).
 *
 * Die Redaktion legt .vtt- oder .srt-Dateien in den Medienpool und wählt sie im Block „Video“ (Sprache, Art). fluid liefert
 * sie geprüft und gesäubert als WebVTT unter /fluid-track/{datei} aus (SRT wird umgewandelt; nur erlaubte WebVTT-Tags,
 * keine STYLE-/REGION-Blöcke). Ergebnis im Cache des AddOns, neu bei geänderter Datei.
 */
final class Tracks
{
    /** Untertitel aus dem Medienpool unter /fluid-track/{datei} (geprüft als WebVTT) */
    public static function boot(): void
    {
        \FriendsOfRedaxo\Fluid\Routing\Router::endpoint(self::ROUTE . '*', static fn (string $path, string $rest) => self::handle($rest));
    }

    public const string ROUTE = '/fluid-track/';
    public const array EXT = ['vtt', 'srt'];
    public const array KINDS = ['subtitles' => 'Untertitel', 'captions' => 'Untertitel für Gehörlose (mit Geräuschen)', 'chapters' => 'Kapitel'];
    private const int MAX_BYTES = 2 * 1024 * 1024;
    private const int MAX_CUES = 5000;

    public static function url(string $file): string
    {
        $m = Media::get($file);

        return \Redaxo\Core\Core::getRequest()->getBasePath() . self::ROUTE . rawurlencode($file) . ($m ? '?v=' . $m->updateDate : '');
    }

    /** Antwort für /fluid-track/{datei} (vom Router aufgerufen) */
    public static function handle(string $file): never
    {
        $m = Media::get(rawurldecode($file));
        if (!$m || !\in_array(strtolower($m->getExtension()), self::EXT, true) || !is_file($src = Path::media($m->fileName))) {
            http_response_code(404);
            exit;
        }
        $cache = Path::addonCache('fluid', 'tracks/' . md5($m->fileName . '|' . filemtime($src)) . '.vtt');
        if (!is_file($cache)) {
            @mkdir(\dirname($cache), 0775, true);
            [$cues] = self::parse((string) file_get_contents($src));
            file_put_contents($cache, self::toVtt($cues));
        }
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: text/vtt; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        readfile($cache);
        exit;
    }

    /** Cues einer Datei aus dem Medienpool (für das Transkript) */
    public static function cuesOf(string $file): array
    {
        $m = '' !== $file ? Media::get($file) : null;
        if (!$m || !\in_array(strtolower($m->getExtension()), self::EXT, true) || !is_file($src = Path::media($m->fileName))) {
            return [];
        }

        return self::parse((string) file_get_contents($src))[0];
    }

    /**
     * WebVTT oder SRT einlesen. Liefert [$cues, $fehler]; Cue = ['start' => ms, 'end' => ms, 'text' => '…', 'settings' => '…'].
     *
     * @return array{0: list<array{start: int, end: int, text: string, settings: string}>, 1: ?string}
     */
    public static function parse(string $raw): array
    {
        if (\strlen($raw) > self::MAX_BYTES) {
            return [[], 'Datei ist zu groß (max. 2 MB).'];
        }
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }
        if (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
            $raw = (string) @mb_convert_encoding(substr($raw, 2), 'UTF-8', str_starts_with($raw, "\xFF\xFE") ? 'UTF-16LE' : 'UTF-16BE');
        }
        if (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = (string) mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        if (str_contains($raw, "\0")) {
            return [[], 'Keine gültige Untertitel-Datei.'];
        }
        $cues = [];
        $time = '(?:(\d{1,3}):)?(\d{1,2}):(\d{1,2})[.,](\d{1,3})';
        foreach (preg_split("~\n[ \t]*\n~", trim($raw)) ?: [] as $i => $b) {
            $lines = explode("\n", trim($b, "\n"));
            if (0 === $i && preg_match('~^WEBVTT\b~', $lines[0])) {
                array_shift($lines);
                while ($lines && !str_contains($lines[0], '-->')) {
                    array_shift($lines);
                }
                if (!$lines) {
                    continue;
                }
            }
            if (preg_match('~^(NOTE|STYLE|REGION)\b~', $lines[0])) {
                continue;
            }
            if (!str_contains($lines[0], '-->') && isset($lines[1]) && str_contains($lines[1], '-->')) {
                array_shift($lines);
            }
            if (!preg_match('~^' . $time . '\s*-->\s*' . $time . '(.*)$~', trim($lines[0]), $mm)) {
                continue;
            }
            $cues[] = ['start' => self::ms($mm[1], $mm[2], $mm[3], $mm[4]), 'end' => self::ms($mm[5], $mm[6], $mm[7], $mm[8]), 'text' => implode("\n", \array_slice($lines, 1)), 'settings' => $mm[9]];
            if (\count($cues) > self::MAX_CUES) {
                return [[], 'Zu viele Einträge.'];
            }
        }

        return $cues ? [self::cleanCues($cues), null] : [[], 'Keine Untertitel gefunden – bitte eine WebVTT- (.vtt) oder SubRip-Datei (.srt) wählen.'];
    }

    private static function ms(string $h, string $m, string $s, string $f): int
    {
        return (((int) $h * 60 + (int) $m) * 60 + (int) $s) * 1000 + (int) str_pad($f, 3, '0');
    }

    private static function cleanCues(array $in): array
    {
        $out = [];
        foreach ($in as $c) {
            $text = self::cleanText((string) $c['text']);
            if ('' === $text) {
                continue;
            }
            $start = max(0, (int) $c['start']);
            $end = (int) $c['end'] <= $start ? $start + 1000 : (int) $c['end'];
            $out[] = ['start' => min($start, 359999999), 'end' => min($end, 359999999), 'text' => $text, 'settings' => self::cleanSettings((string) $c['settings'])];
        }
        usort($out, static fn ($a, $b) => $a['start'] <=> $b['start'] ?: $a['end'] <=> $b['end']);

        return $out;
    }

    /** Cue-Text: nur erlaubte WebVTT-Tags, Entities normalisiert, höchstens 500 Zeichen und 4 Zeilen */
    public static function cleanText(string $t): string
    {
        $t = str_replace(["\r\n", "\r"], "\n", $t);
        $t = (string) preg_replace('~\{\\\\[^}]*\}~', '', $t);
        $t = str_replace('-->', '→', $t);
        $t = (string) preg_replace('~<(script|style)\b[^>]*>.*?</\1\s*>~is', '', $t);   // samt Inhalt
        $out = '';
        $open = [];
        foreach (preg_split('~(<[^>\n]{0,120}>)~', $t, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            if ('<' === $part[0] && str_ends_with($part, '>')) {
                if (preg_match('~^<(\d{1,3}:)?\d{1,2}:\d{2}\.\d{3}>$~', $part)) {
                    $out .= $part;
                } elseif (preg_match('~^</(b|i|u|c|v|lang|ruby|rt)>$~i', $part, $mm)) {
                    $tag = strtolower($mm[1]);
                    $k = array_search($tag, array_reverse($open, true), true);
                    if (false !== $k) {
                        unset($open[$k]);
                        $open = array_values($open);
                        $out .= '</' . $tag . '>';
                    }
                } elseif (preg_match('~^<(b|i|u|ruby|rt)>$~i', $part, $mm)) {
                    $open[] = strtolower($mm[1]);
                    $out .= '<' . strtolower($mm[1]) . '>';
                } elseif (preg_match('~^<c((?:\.[A-Za-z0-9_-]{1,30}){0,4})>$~', $part, $mm)) {
                    $open[] = 'c';
                    $out .= '<c' . $mm[1] . '>';
                } elseif (preg_match('~^<v(?:\.[A-Za-z0-9_-]{1,30})*\s+([^<>&]{1,60})>$~u', $part, $mm)) {
                    $open[] = 'v';
                    $out .= '<v ' . trim($mm[1]) . '>';
                } elseif (preg_match('~^<lang\s+([a-zA-Z]{2,3}(?:-[A-Za-z0-9]{2,8})?)>$~', $part, $mm)) {
                    $open[] = 'lang';
                    $out .= '<lang ' . $mm[1] . '>';
                }
                continue;   // alles andere (HTML, <font>, <script> …) entfällt
            }
            $out .= str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], html_entity_decode($part, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        foreach (array_reverse($open) as $tag) {
            $out .= '</' . $tag . '>';
        }
        $lines = array_values(array_filter(array_map(static fn ($l) => trim((string) preg_replace('~[ \t]+~u', ' ', $l)), explode("\n", $out)), static fn ($l) => '' !== $l));

        return mb_substr(implode("\n", \array_slice($lines, 0, 4)), 0, 500);
    }

    private static function cleanSettings(string $s): string
    {
        $out = [];
        foreach (preg_split('~\s+~', trim($s)) ?: [] as $p) {
            if (preg_match('~^(line|position|size):-?\d{1,3}(\.\d+)?%?(,(start|center|end|line-left|line-right|auto))?$~', $p)
                || preg_match('~^align:(start|center|end|left|right)$~', $p)) {
                $out[] = $p;
            }
        }

        return implode(' ', \array_slice($out, 0, 4));
    }

    private static function stamp(int $ms): string
    {
        return \sprintf('%02d:%02d:%02d.%03d', intdiv($ms, 3600000), intdiv($ms % 3600000, 60000), intdiv($ms % 60000, 1000), $ms % 1000);
    }

    public static function toVtt(array $cues): string
    {
        $out = "WEBVTT\n";
        foreach ($cues as $i => $c) {
            $out .= "\n" . ($i + 1) . "\n" . self::stamp($c['start']) . ' --> ' . self::stamp($c['end']) . ('' !== $c['settings'] ? ' ' . $c['settings'] : '') . "\n" . $c['text'] . "\n";
        }

        return $out;
    }

    /** Sichtbarer Text eines Cues (ohne Tags, Sprecher als „Name: “) */
    public static function plain(string $t): string
    {
        return trim(html_entity_decode(strip_tags((string) preg_replace('~<v\s+([^>]+)>~u', '$1: ', $t)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /** Fließtext aus Cues: nach Pausen ab 2 s neuer Absatz */
    public static function transcript(array $cues): array
    {
        $paras = [];
        $cur = '';
        $last = null;
        foreach ($cues as $c) {
            $t = (string) preg_replace('~\s+~u', ' ', self::plain($c['text']));
            if ('' === $t) {
                continue;
            }
            if (null !== $last && $c['start'] - $last >= 2000 && '' !== $cur) {
                $paras[] = trim($cur);
                $cur = '';
            }
            $cur .= ' ' . $t;
            $last = $c['end'];
        }
        if ('' !== trim($cur)) {
            $paras[] = trim($cur);
        }

        return $paras;
    }

    /** Bezeichnung einer Sprache für die Auswahl im Player */
    public static function label(string $kind, string $lang, string $label = ''): string
    {
        if ('' !== trim($label)) {
            return trim($label);
        }
        $names = ['de' => 'Deutsch', 'en' => 'English', 'fr' => 'Français', 'es' => 'Español', 'it' => 'Italiano', 'nl' => 'Nederlands', 'pl' => 'Polski',
            'tr' => 'Türkçe', 'ar' => 'العربية', 'ru' => 'Русский', 'uk' => 'Українська', 'pt' => 'Português', 'el' => 'Ελληνικά', 'sl' => 'Slovenščina'];
        foreach (\Redaxo\Core\Language\Language::getAll() as $l) {
            $names[$l->code] ??= $l->name;
        }
        $n = $names[strtolower(substr($lang, 0, 2))] ?? strtoupper($lang);

        return match ($kind) {
            'captions' => $n . ' (CC)',
            'chapters' => $n . ' – ' . (\function_exists('lt') ? lt('Kapitel') : 'Kapitel'),
            default => $n,
        };
    }
}

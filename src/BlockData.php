<?php

namespace FriendsOfRedaxo\Fluid;

use Redaxo\Core\Content\Module;

use function Redaxo\Core\View\escape;

/**
 * Blöcke als Daten – ohne Artikel und Slices, z. B. im YForm-Feld „Fluid-Blöcke“ (Data\FluidBlocks).
 *
 * Wert: Liste von ['module' => 'fluid.hero', 'data' => [...], 'tunes' => [...]] (als JSON gespeichert). Dieselben Blöcke,
 * Felder und Ausgaben wie auf den Seiten – nur ohne Layout-Spalten (die brauchen Slices).
 *
 *     echo BlockData::render($record->get('inhalt'));   // in einem Template oder einer eigenen Detailansicht
 */
final class BlockData
{
    /**
     * Liste der Blöcke; `uid` = feste Kennung je Block (für das Bearbeiten auf der Website), fehlt bei alten Daten.
     *
     * @return list<array{module: string, data: array<string, mixed>, tunes: array<string, mixed>, uid?: string}>
     */
    public static function decode(mixed $value): array
    {
        $list = \is_string($value) ? json_decode($value, true) : $value;
        $out = [];
        foreach (\is_array($list) ? $list : [] as $item) {
            if (\is_array($item) && \is_string($item['module'] ?? null) && self::module($item['module'])) {
                $row = ['module' => $item['module'], 'data' => \is_array($item['data'] ?? null) ? $item['data'] : [], 'tunes' => \is_array($item['tunes'] ?? null) ? $item['tunes'] : []];
                if (\is_string($item['uid'] ?? null) && preg_match('~^[a-z0-9]{6,16}$~', $item['uid'])) {
                    $row['uid'] = $item['uid'];
                }
                $out[] = $row;
            }
        }

        return $out;
    }

    /** Fehlende Kennungen ergänzen (doppelte werden neu vergeben) */
    public static function withUids(array $blocks): array
    {
        $seen = [];
        foreach ($blocks as $i => $b) {
            $uid = (string) ($b['uid'] ?? '');
            if ('' === $uid || isset($seen[$uid])) {
                $uid = self::uid();
                $blocks[$i]['uid'] = $uid;
            }
            $seen[$uid] = true;
        }

        return $blocks;
    }

    public static function uid(): string
    {
        return bin2hex(random_bytes(5));
    }

    public static function encode(array $blocks): ?string
    {
        $blocks = self::decode($blocks);

        return [] === $blocks ? null : json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Blöcke, die in Daten stehen dürfen: alle verfügbaren Blöcke außer Layout-Spalten.
     *
     * @param list<string>|null $allowed nur diese Typen (z. B. ['richtext', 'media_text']) bzw. Modul-Schlüssel
     * @return array<string, BlockModule> Modul-Schlüssel => Modul
     */
    public static function modules(?array $allowed = null): array
    {
        $out = [];
        foreach (Module::getAll() as $m) {
            if (!$m instanceof BlockModule || $m instanceof LayoutBlock || !$m->available()) {
                continue;
            }
            if (null !== $allowed && [] !== $allowed && !\in_array($m->type(), $allowed, true) && !\in_array($m->key, $allowed, true)) {
                continue;
            }
            $out[$m->key] = $m;
        }
        uasort($out, static fn (BlockModule $a, BlockModule $b) => [(string) ($a->definition()['group'] ?? ''), (string) ($a->definition()['label'] ?? '')]
            <=> [(string) ($b->definition()['group'] ?? ''), (string) ($b->definition()['label'] ?? '')]);

        return $out;
    }

    public static function module(string $key): ?BlockModule
    {
        $m = Module::get($key);

        return $m instanceof BlockModule && !$m instanceof LayoutBlock ? $m : null;
    }

    /** Alle Blöcke als Abschnitte – ohne Bearbeiten-Markup der Seite (die Blöcke gehören zu einem Datensatz) */
    public static function render(mixed $value, string $idPrefix = 'bd', int $articleId = 0): string
    {
        $blocks = self::decode($value);
        if ([] === $blocks) {
            return '';
        }

        return (string) Editing::without(static function () use ($blocks, $idPrefix, $articleId): string {
            $html = '';
            $prev = null;
            foreach ($blocks as $i => $item) {
                $m = self::module($item['module']);
                if (!$m) {
                    continue;
                }
                $b = $m->blockFromData($idPrefix . '-' . ($i + 1), $item['data'], $item['tunes'], $prev, $articleId);
                $html .= $m->renderData($b);
                $prev = $b;
            }

            return $html;
        });
    }

    /** Klartext aller Textfelder (Liste im Backend, Suche, Mails) */
    public static function text(mixed $value): string
    {
        $headings = [];
        $text = [];
        foreach (self::decode($value) as $item) {
            if ($m = self::module($item['module'])) {
                Search\Search::collect($m->fields(), $item['data'], $headings, $text);
            }
        }

        return trim(implode(' ', [...$headings, ...$text]));
    }

    /** Kurzbezeichnung eines Blocks für Listen: „Text – Erste Überschrift …“ */
    public static function label(array $item): string
    {
        $m = self::module((string) ($item['module'] ?? ''));
        if (!$m) {
            return '?';
        }
        $headings = [];
        $text = [];
        Search\Search::collect($m->fields(), (array) ($item['data'] ?? []), $headings, $text);
        $first = $headings[0] ?? $text[0] ?? '';

        return (string) ($m->definition()['label'] ?? $m->name) . ('' !== $first ? ' – ' . mb_strimwidth($first, 0, 60, '…') : '');
    }

    /** @internal Auswahl für die Oberfläche: [Schlüssel => [Bezeichnung, Gruppe, Hilfe]] */
    public static function catalog(?array $allowed = null): array
    {
        $out = [];
        foreach (self::modules($allowed) as $key => $m) {
            $d = $m->definition();
            $out[$key] = ['label' => (string) ($d['label'] ?? $m->name), 'group' => (string) ($d['group'] ?? ''), 'help' => (string) ($d['help'] ?? '')];
        }

        return $out;
    }

    /** @internal */
    public static function attr(mixed $v): string
    {
        return escape(json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP));
    }
}

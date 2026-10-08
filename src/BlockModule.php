<?php

namespace FriendsOfRedaxo\Blocks;

use FriendsOfRedaxo\Blocks\Form\FieldForm;
use Override;
use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Core;
use Redaxo\Core\Http\Request;

use function in_array;
use function is_array;
use function json_decode;
use function Redaxo\Core\View\escape;

/**
 * Basis für Module mit Feldschema („Block-Module“).
 *
 * Ein Block-Modul beschreibt nur seine Felder (in der Kit-Definition) und seine Ausgabe (View-Datei des Kits).
 * Eingabeformular, Speichern und Bearbeiten auf der Website ergeben sich daraus:
 *
 *   value1  Felder als JSON (`REX_INPUT_VALUE[1][feld]` → REDAXO legt Arrays als JSON ab)
 *   value2  Abschnitts-Optionen als JSON (Hintergrund, Sprungmarke, Abstände)
 *
 * Beispiel:
 *
 *     #[AsModule('fluid.cta', 'Handlungsaufruf')]
 *     final class Cta extends FluidBlock {}
 */
abstract class BlockModule extends Module
{
    abstract public function kit(): Kit;

    /** Blocktyp im Kit: Teil des Modulschlüssels nach dem letzten Punkt (`fluid.media_text` → `media_text`) */
    public function type(): string
    {
        $pos = strrpos($this->key, '.');

        return false === $pos ? $this->key : substr($this->key, $pos + 1);
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return $this->kit()->block($this->type());
    }

    /** @return list<array<string, mixed>> */
    public function fields(): array
    {
        /** @var list<array<string, mixed>> */
        return $this->definition()['fields'] ?? [];
    }

    /** @return array<string, string> */
    public function variants(): array
    {
        /** @var array<string, string> */
        return $this->definition()['variants'] ?? [];
    }

    /**
     * Felder des Slices mit Standardwerten.
     *
     * @return array<string, mixed>
     */
    public function data(ArticleSlice $slice): array
    {
        return FieldForm::withDefaults($this->fields(), self::decode($slice->getValue(1)), $this->variants());
    }

    /** @return array<string, mixed> */
    public function tunes(ArticleSlice $slice): array
    {
        return self::decode($slice->getValue(2));
    }

    #[Override]
    public function input(ArticleSlice $slice): string
    {
        return FieldForm::render($this, self::decode($slice->getValue(1)), self::decode($slice->getValue(2)), 'REX_INPUT_VALUE');
    }

    #[Override]
    public function output(ArticleSlice $slice): string
    {
        $parent = Layout::parentOf($slice);
        // Slice-Liste im Backend: Spalten-Blöcke mit Hinweis auf ihre Spalte, Layouts als Kurzinfo (keine doppelte Ausgabe)
        if (self::backendPreview()) {
            $badge = $parent ? '<p class="blocks-be-col"><i class="rex-icon fa-table-columns"></i> Spalte ' . Layout::col($slice) . ' im Layout darüber</p>' : '';

            return $badge . $this->renderBlock($slice);
        }
        // Spalten-Blöcke gibt ihr Layout aus (Layout::render)
        if (null !== $parent) {
            return '';
        }

        return $this->renderBlock($slice);
    }

    /** Vorschau in der Slice-Liste des Backends (nicht für den Cache – der entsteht im Frontend) */
    public static function backendPreview(): bool
    {
        return Core::isBackend() && str_starts_with(Request::get('page', 'string'), 'content');
    }

    /** Darf der Block (in dieser Variante) in einer Layout-Spalte stehen? (Kit: 'nestable' => true | ['variante', …]) */
    public function nestable(string $variant = ''): bool
    {
        $n = $this->definition()['nestable'] ?? false;

        return true === $n || (is_array($n) && ('' === $variant || in_array($variant, $n, true)));
    }

    /** Inhalt des Blocks (View des Kits) – ohne Abschnitts-Hülle */
    protected function renderInner(Block $b): string
    {
        return (static function (string $__file, Block $b, array $d): string {
            ob_start();
            try {
                require $__file;
            } finally {
                $html = (string) ob_get_clean();
            }

            return $html;
        })($this->kit()->view($b->type), $b, $b->data);
    }

    /** Attribute für den Editor (nur im Bearbeiten-Modus) */
    protected function editAttrs(ArticleSlice $slice, string $type, ?int $col = null): string
    {
        if (!Editing::active()) {
            return '';
        }

        return ' data-block="' . $slice->id . '" data-type="' . escape($type) . '" data-label="' . escape((string) ($this->definition()['label'] ?? $this->name)) . '"'
            . (null !== $col ? ' data-col="' . $col . '"' : '')
            . ($this instanceof LayoutBlock ? ' data-layout' : '')
            . (1 !== $slice->status ? ' data-offline' : '');
    }

    /**
     * Block in einer Layout-Spalte: ohne eigenen Abschnitt, Hintergrund wie das Layout – ein eigener, anderer Hintergrund
     * macht ihn zur Karte (Klassen sec + bg-*, wie bei Studio).
     */
    public function renderChild(ArticleSlice $slice, string $parentBg, ?array $data = null, ?array $tunes = null): string
    {
        $t = $tunes ?? $this->tunes($slice);
        $own = (string) ($t['background'] ?? '');
        $card = '' !== $own && $own !== $parentBg && isset($this->kit()->backgrounds()[$own]);
        $t['background'] = $card ? $own : $parentBg;
        $b = $this->block($slice, $data, $t, 6);
        $this->kit()->markUsed($b->type, $b->variant());
        $inner = $this->renderInner($b);
        if ('' === trim($inner) && !Editing::active()) {
            return '';
        }
        $cls = ['lay-item', 'sec--' . str_replace('_', '-', $b->type)];
        if ('' !== $b->variant()) {
            $cls[] = 'v-' . $b->variant();
        }
        if ($card) {
            array_push($cls, 'sec', 'lay-item--card', 'bg-' . $own);
        }
        $anchor = '' !== (string) ($t['anchor'] ?? '') ? ' id="' . escape($b->domId()) . '"' : '';
        $invalid = !$this->nestable($b->variant()) && Editing::active()
            ? '<p class="blocks-invalid">„' . escape((string) ($this->definition()['label'] ?? $this->name)) . '“ in dieser Darstellung ist für Spalten nicht vorgesehen.</p>'
            : '';

        return '<div' . $anchor . ' class="' . escape(implode(' ', $cls)) . '"' . $this->editAttrs($slice, $b->type, Layout::col($slice)) . '>' . $invalid . $inner . "</div>\n";
    }

    /** Ausgabe eines Slices als Abschnitt, optional mit anderen Daten (Vorschau beim Bearbeiten) */
    public function renderBlock(ArticleSlice $slice, ?array $data = null, ?array $tunes = null, int $depth = 0): string
    {
        $b = $this->block($slice, $data, $tunes, $depth);
        $this->kit()->markUsed($b->type, $b->variant());
        $inner = $this->renderInner($b);
        $titled = '' !== trim((string) ($b->data['title'] ?? ''));

        return '<section id="' . escape($b->domId()) . '" class="' . escape($b->sectionClass()) . '"'
            . ($titled ? ' aria-labelledby="' . escape($b->titleId()) . '"' : '') . $this->editAttrs($slice, $b->type) . '>'
            . $b->sectionBg() . $inner . "</section>\n";
    }

    /** @internal */
    public function block(ArticleSlice $slice, ?array $data = null, ?array $tunes = null, int $depth = 0): Block
    {
        $prev = null;
        // Vorgänger (z. B. „Text + Bild abwechselnd“) – nur wenige Ebenen, mehr braucht keine Ausgabe
        if ($depth < 6 && ($p = $slice->getPreviousSlice(!Editing::active())) && ($pm = Module::get($p->moduleKey)) instanceof self) {
            $prev = $pm->block($p, null, null, $depth + 1);
        }

        return new Block(
            $slice,
            $this,
            null !== $data ? FieldForm::withDefaults($this->fields(), $data, $this->variants()) : $this->data($slice),
            $tunes ?? $this->tunes($slice),
            $prev,
        );
    }

    /** @return array<string, mixed> */
    public static function decode(?string $json): array
    {
        if (null === $json || '' === $json) {
            return [];
        }
        $v = json_decode($json, true);

        return is_array($v) ? $v : [];
    }
}

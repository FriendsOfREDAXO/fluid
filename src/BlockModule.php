<?php

namespace FriendsOfRedaxo\Blocks;

use FriendsOfRedaxo\Blocks\Form\FieldForm;
use Override;
use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Content\Module;

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
        return $this->renderBlock($slice);
    }

    /** Ausgabe eines Slices, optional mit anderen Daten (Vorschau beim Bearbeiten) */
    public function renderBlock(ArticleSlice $slice, ?array $data = null, ?array $tunes = null, int $depth = 0): string
    {
        $b = $this->block($slice, $data, $tunes, $depth);
        $this->kit()->markUsed($b->type, $b->variant());
        $d = $b->data;

        $inner = (static function (string $__file, Block $b, array $d): string {
            ob_start();
            try {
                require $__file;
            } finally {
                $html = (string) ob_get_clean();
            }

            return $html;
        })($this->kit()->view($b->type), $b, $d);

        $titled = '' !== trim((string) ($d['title'] ?? ''));
        $attrs = Editing::active()
            ? ' data-block="' . $slice->id . '" data-type="' . escape($b->type) . '" data-label="' . escape((string) ($this->definition()['label'] ?? $this->name)) . '"' . (1 !== $slice->status ? ' data-offline' : '')
            : '';

        return '<section id="' . escape($b->domId()) . '" class="' . escape($b->sectionClass()) . '"'
            . ($titled ? ' aria-labelledby="' . escape($b->titleId()) . '"' : '') . $attrs . '>'
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

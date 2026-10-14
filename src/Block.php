<?php

namespace FriendsOfRedaxo\Fluid;

use Redaxo\Core\Content\ArticleSlice;

use function in_array;
use function Redaxo\Core\View\escape;

/**
 * Wird an jede Block-Ausgabe übergeben (`$b`). `$b->data` enthält die Felder mit Standardwerten.
 *
 * Kit-Ausgaben markieren Textfelder mit `$b->edit('feld')` – im Bearbeiten-Modus werden sie dann direkt auf der Website
 * bearbeitbar (`data-edit`), sonst liefert die Methode einen leeren String.
 */
final class Block
{
    /** @var array<string, mixed> */
    public readonly array $tunes;

    /**
     * @param ArticleSlice|null $slice null = Block aus Daten (z. B. YForm-Feld „Fluid-Blöcke“), dann $id und $articleId angeben
     */
    public function __construct(
        public readonly ?ArticleSlice $slice,
        public readonly BlockModule $module,
        /** @var array<string, mixed> */
        public readonly array $data,
        /** @var array<string, mixed> */
        array $tunes = [],
        public readonly ?self $prev = null,
        private readonly string $ownId = '',
        private readonly int $ownArticleId = 0,
    ) {
        if ('' === (string) ($tunes['background'] ?? '')) {
            unset($tunes['background']);   // leer = Standard des Blocks
        }
        $this->tunes = $tunes + ['background' => $module->definition()['background'] ?? 'white', 'anchor' => '', 'spaceTop' => 'normal', 'spaceBottom' => 'normal'];
    }

    public string $id {
        get => $this->slice ? (string) $this->slice->id : $this->ownId;
    }

    /** Seite, auf der der Block steht (für Adressen wie Blättern oder Detailansicht) */
    public int $articleId {
        get => $this->slice->articleId ?? ($this->ownArticleId ?: (int) \Redaxo\Core\Content\Article::getCurrentId());
    }

    /** Block aus einem Slice (Seite) oder aus Daten (YForm-Feld)? */
    public function fromSlice(): bool
    {
        return null !== $this->slice;
    }

    /** Blocktyp ohne Kit-Präfix (`cta`, `media_text` …) */
    public string $type {
        get => $this->module->type();
    }

    public function variant(): string
    {
        return (string) ($this->data['variant'] ?? '');
    }

    public function tune(string $key): mixed
    {
        return $this->tunes[$key] ?? null;
    }

    public function domId(): string
    {
        return '' !== (string) $this->tunes['anchor'] ? (string) $this->tunes['anchor'] : 'b-' . $this->id;
    }

    public function titleId(): string
    {
        return $this->domId() . '-title';
    }

    /**
     * Macht ein Feld im Bearbeiten-Modus direkt auf der Website bearbeitbar.
     * Pfad mit Punkten für Listen: `items.0.title`. Modus: `plain` (einzeilig), `lines` (mehrzeilig), `rich` (Formatierung).
     */
    public function edit(string $path, string $mode = 'plain'): string
    {
        if (!Editing::active()) {
            return '';
        }

        return ' data-edit="' . escape($path) . '"' . ('plain' !== $mode ? ' data-edit-mode="' . escape($mode) . '"' : '');
    }

    /** Zentral gepflegte Inhalte (z. B. aus den Einstellungen) im Bearbeiten-Modus kennzeichnen */
    public function central(?string $label = null): string
    {
        return Editing::active() ? ' data-central="' . escape($label ?? 'Einstellungen') . '"' : '';
    }

    /** „Ziel bearbeiten“ an Karten – im Prototyp noch ohne Funktion */
    public function targetEdit(?string $ref, ?string $label = null): string
    {
        return '';
    }

    public function dark(): bool
    {
        return in_array($this->tunes['background'], $this->module->kit()->darkBackgrounds(), true);
    }

    public function sectionClass(): string
    {
        $c = ['sec', 'sec--' . str_replace('_', '-', $this->type), 'bg-' . $this->tunes['background']];
        if ('normal' !== $this->tunes['spaceTop']) {
            $c[] = 'pt-' . $this->tunes['spaceTop'];
        }
        if ('normal' !== $this->tunes['spaceBottom']) {
            $c[] = 'pb-' . $this->tunes['spaceBottom'];
        }
        if ('' !== $this->variant()) {
            $c[] = 'v-' . $this->variant();
        }

        return implode(' ', $c);
    }

    public function sectionBg(): string
    {
        return '';
    }
}

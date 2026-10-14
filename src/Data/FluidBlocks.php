<?php

namespace FriendsOfRedaxo\Fluid\Data;

use FriendsOfRedaxo\Fluid\BlockData;
use Override;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Yakamara\YForm\Data\Record;
use Yakamara\YForm\Field\Field;
use Yakamara\YForm\Form\FieldContext;

use function Redaxo\Core\View\escape;

// Nur mit YForm (fluid läuft auch ohne)
if (!class_exists(Field::class)) {
    return;
}

/**
 * YForm-Feld „Fluid-Blöcke“: Inhalt aus den Blöcken von fluid – in jeder YForm-Tabelle.
 *
 *     yield FluidBlocks::new('inhalt')->label('Inhalt');
 *     yield FluidBlocks::new('inhalt')->label('Inhalt')->blocks('richtext', 'media_text', 'video');   // nur diese Blöcke
 *
 * Gespeichert als JSON (longtext): [{module, data, tunes}, …]. Bearbeiten in der YForm-Maske mit denselben Feldern
 * wie auf der Website (Medienpool, Linkmap, Text-Editor). Ausgabe: `BlockData::render($record->get('inhalt'))` – die
 * Detailansicht der Datenliste macht das automatisch. Layout-Spalten gibt es hier nicht (sie brauchen Slices).
 */
final class FluidBlocks extends Field
{
    /** @var list<string>|null */
    public protected(set) ?array $allowed = null;

    #[Override]
    protected function init(): void
    {
        $this->listed = false;
        $this->searchable = true;
    }

    /** Nur diese Blöcke anbieten (Typ wie „richtext“ oder Modul-Schlüssel wie „fluid.richtext“) */
    public function blocks(string ...$types): static
    {
        $this->allowed = $types;

        return $this;
    }

    #[Override]
    protected function columnType(): string
    {
        return 'longtext';
    }

    #[Override]
    public function fromDb(mixed $raw): mixed
    {
        return BlockData::decode(null === $raw ? null : (string) $raw);
    }

    #[Override]
    public function toDb(mixed $value): array
    {
        return [$this->name => BlockData::encode(\is_array($value) ? $value : [])];
    }

    #[Override]
    public function fromInput(mixed $input): mixed
    {
        return \is_string($input) ? BlockData::decode(json_decode($input, true)) : [];
    }

    #[Override]
    public function textValue(mixed $value): string
    {
        return BlockData::text($value);
    }

    #[Override]
    public function listValue(mixed $value, Record $record): string
    {
        $n = \count(BlockData::decode($value));

        return 0 === $n ? '' : escape($n . ' ' . (1 === $n ? 'Block' : 'Blöcke'));
    }

    #[Override]
    public function render(FieldContext $context): string
    {
        $blocks = BlockData::decode($context->value);
        $config = [
            'api' => Url::backendController(['page' => Request::get('page', 'string', 'yform/data')] + BlocksApi::getUrlParams()),
            'catalog' => BlockData::catalog($this->allowed),
            'labels' => array_map(BlockData::label(...), $blocks),
        ];

        return '<div class="fluid-blocks-field" data-fluid-blocks="' . BlockData::attr($config) . '">'
            . '<input type="hidden" ' . $this->controlAttributes($context, ['value' => json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)], false) . '>'
            . '<ol class="fbf-list" data-fbf-list></ol>'
            . '<div class="fbf-add"><select class="form-control" data-fbf-type aria-label="Blocktyp"></select> <button type="button" class="btn btn-default" data-fbf-add>Block hinzufügen</button></div>'
            . '</div>';
    }
}

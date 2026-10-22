<?php

namespace FriendsOfRedaxo\Fluid;

use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use FriendsOfRedaxo\Fluid\Form\BlockValues;
use FriendsOfRedaxo\Fluid\Form\FieldForm;
use Override;
use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Core;
use Redaxo\Core\Form\Form;
use Redaxo\Core\Http\Request;

use function array_filter;
use function in_array;
use function is_a;
use function is_array;
use function json_decode;
use function Redaxo\Core\View\escape;

/**
 * Basis für Module mit Feldschema („Block-Module“).
 *
 * Ein Block-Modul beschreibt nur seine Felder (in der Kit-Definition) und seine Ausgabe (View-Datei des Kits).
 * Eingabeformular, Speichern und Bearbeiten auf der Website ergeben sich daraus. Gespeichert wird in der JSON-Spalte
 * `data` des Slices:
 *
 *   values  Felder des Blocks (laut Kit-Definition)
 *   tunes   Abschnitts-Optionen (Hintergrund, Sprungmarke, Abstände, Spalte im Layout)
 *
 * Was den Blocktyp betrifft (Definition, Felder, Kit), ist statisch; eine Instanz gehört zu einem Slice
 * (`BlockModule::of($slice)`, wie bei allen Modulen des Cores).
 *
 * Beispiel:
 *
 *     #[AsModule('fluid.cta', 'Handlungsaufruf')]
 *     final class Cta extends FluidBlock {}
 */
abstract class BlockModule extends Module
{
    /** @var array<string, mixed> Felder des Blocks, wie gespeichert (ohne Standardwerte, siehe data()) */
    #[BlockValues]
    public array $values = [];

    /** @var array<string, mixed> Abschnitts-Optionen */
    #[BlockValues]
    public array $tunes = [];

    /** @var array<class-string<self>, array<string, mixed>> */
    private static array $definitions = [];

    abstract public static function kit(): Kit;

    /**
     * Block-Modul zum Modulschlüssel, `null` für andere Module.
     *
     * @return class-string<self>|null
     */
    public static function classFor(string $key): ?string
    {
        $class = Module::getClass($key);

        return null !== $class && is_a($class, self::class, true) ? $class : null;
    }

    /**
     * Alle Block-Module, nach Modulschlüssel.
     *
     * @return array<string, class-string<self>>
     */
    public static function all(): array
    {
        return array_filter(Module::getAll(), static fn (string $class) => is_a($class, self::class, true));
    }

    /** Blocktyp im Kit: Teil des Modulschlüssels nach dem letzten Punkt (`fluid.media_text` → `media_text`) */
    public static function type(): string
    {
        $key = static::key();
        $pos = strrpos($key, '.');

        return false === $pos ? $key : substr($key, $pos + 1);
    }

    /** @return array<string, mixed> */
    public static function definition(): array
    {
        // Eigener Block im Projekt: define() liefert die Definition; sonst die des Kits. Projekte passen sie über
        // den Extension Point FLUID_BLOCK_DEFINITION an (Felder ergänzen, Beschriftungen ändern …).
        return self::$definitions[static::class] ??= (array) Extension::dispatch(new ExtensionPoint('FLUID_BLOCK_DEFINITION',
            static::define() ?? static::kit()->block(static::type()), ['type' => static::type(), 'module' => static::class, 'kit' => static::kit()]));
    }

    /** Beschriftung des Blocks (Kit-Definition, sonst Name des Moduls) */
    public static function label(): string
    {
        return (string) (static::definition()['label'] ?? static::name());
    }

    /**
     * Definition eines eigenen Blocks (Format wie in kit.php: label, icon, group, help, variants, fields …).
     * null = Block des Kits. Beispiel siehe README („Eigener Block“).
     *
     * @return array<string, mixed>|null
     */
    protected static function define(): ?array
    {
        return null;
    }

    /** View-Datei: {Klasse}.view.php neben der Klasse, sonst views/{typ}.php des Kits; überschreibbar per FLUID_BLOCK_VIEW */
    public static function viewFile(Block $b): string
    {
        $own = substr((string) new \ReflectionClass(static::class)->getFileName(), 0, -4) . '.view.php';
        $file = is_file($own) ? $own : static::kit()->view($b->type);

        return (string) Extension::dispatch(new ExtensionPoint('FLUID_BLOCK_VIEW', $file, ['type' => $b->type, 'variant' => $b->variant(), 'block' => $b]));
    }

    /** Benötigte AddOns vorhanden? (Kit-Angabe „requires“, z. B. 'yform') – sonst fehlt der Block in der Auswahl */
    public static function available(): bool
    {
        return match (static::definition()['requires'] ?? null) {
            null => true,
            'yform' => Data\YFormBridge::available(),
            default => false,
        };
    }

    /** @return list<array<string, mixed>> */
    public static function fields(): array
    {
        /** @var list<array<string, mixed>> */
        return static::definition()['fields'] ?? [];
    }

    /** @return array<string, string> */
    public static function variants(): array
    {
        /** @var array<string, string> */
        return static::definition()['variants'] ?? [];
    }

    /**
     * Felder des Slices mit Standardwerten.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return FieldForm::withDefaults(static::fields(), $this->values, static::variants());
    }

    #[Override]
    public function input(Form $form): string
    {
        return FieldForm::render(static::class, $this->values, $this->tunes, 'slice', dataKey: 'values', tunesKey: 'tunes');
    }

    #[Override]
    public function output(): string
    {
        $slice = $this->slice;
        $parent = Layout::parentOf($slice);
        // Slice-Liste im Backend: Spalten-Blöcke mit Hinweis auf ihre Spalte, Layouts als Kurzinfo (keine doppelte Ausgabe)
        if (self::backendPreview()) {
            $badge = $parent ? '<p class="blocks-be-col"><i class="rex-icon fa-table-columns"></i> Spalte ' . Layout::col($slice) . ' im Layout darüber</p>' : '';

            return $badge . $this->renderBlock();
        }
        // Spalten-Blöcke gibt ihr Layout aus (Layout::render)
        if (null !== $parent) {
            return '';
        }

        return $this->renderBlock();
    }

    /**
     * Werte für die Spalte `data` eines Slices (z. B. ContentHandler::addSlice()).
     *
     * @param array<string, mixed> $values
     * @param array<string, mixed> $tunes
     * @return array{values: array<string, mixed>, tunes: array<string, mixed>}
     */
    public static function store(array $values, array $tunes = []): array
    {
        return ['values' => $values, 'tunes' => $tunes];
    }

    /**
     * Gespeicherte Werte aus der Spalte `data` (direkte Abfragen ohne ArticleSlice).
     *
     * @return array{values: array<string, mixed>, tunes: array<string, mixed>}
     */
    public static function stored(?string $json): array
    {
        $d = self::decode($json);

        return ['values' => is_array($d['values'] ?? null) ? $d['values'] : [], 'tunes' => is_array($d['tunes'] ?? null) ? $d['tunes'] : []];
    }

    /**
     * Abschnitts-Optionen eines Slices, ohne das Modul zu erzeugen (z. B. für Slices anderer Module).
     *
     * @return array<string, mixed>
     */
    public static function tunesOf(ArticleSlice $slice): array
    {
        return is_array($slice->data['tunes'] ?? null) ? $slice->data['tunes'] : [];
    }

    /**
     * Gespeicherte Felder eines Slices (ohne Standardwerte), ohne das Modul zu erzeugen.
     *
     * @return array<string, mixed>
     */
    public static function valuesOf(ArticleSlice $slice): array
    {
        return is_array($slice->data['values'] ?? null) ? $slice->data['values'] : [];
    }

    /** Werte als JSON für die Spalte `data` (direkte Updates ohne Handler) */
    public static function encodeStore(array $values, array $tunes = []): ?string
    {
        return ArticleSlice::encodeData(self::store($values, $tunes));
    }

    /** Vorschau in der Slice-Liste des Backends (nicht für den Cache – der entsteht im Frontend) */
    public static function backendPreview(): bool
    {
        return Core::isBackend() && str_starts_with(Request::get('page', 'string'), 'content');
    }

    /** Darf der Block (in dieser Variante) in einer Layout-Spalte stehen? (Kit: 'nestable' => true | ['variante', …]) */
    public static function nestable(string $variant = ''): bool
    {
        $n = static::definition()['nestable'] ?? false;

        return true === $n || (is_array($n) && ('' === $variant || in_array($variant, $n, true)));
    }

    /** Inhalt des Blocks (View des Kits) – ohne Abschnitts-Hülle */
    protected static function renderInner(Block $b): string
    {
        Seo\StructuredData::collect($b);   // schema.org der Blöcke (FAQ …) für den Seitenkopf
        static::kit()->definition();   // lädt auch die Hilfsfunktionen des Kits (functions.php) – auch für eigene Blöcke
        $outer = Block::$rendering;   // Layout: Spalten-Blöcke werden innerhalb seiner View ausgegeben
        Block::$rendering = $b;
        try {
            return (static function (string $__file, Block $b, array $d): string {
                ob_start();
                try {
                    require $__file;
                } finally {
                    $html = (string) ob_get_clean();
                }

                return $html;
            })(static::viewFile($b), $b, $b->data);
        } finally {
            Block::$rendering = $outer;
        }
    }

    /** Attribute für den Editor (nur im Bearbeiten-Modus) */
    protected function editAttrs(string $type, ?int $col = null): string
    {
        if (!Editing::active()) {
            return '';
        }
        $slice = $this->slice;

        return ' data-block="' . $slice->id . '" data-type="' . escape($type) . '" data-label="' . escape(static::label()) . '"'
            . (null !== $col ? ' data-col="' . $col . '"' : '')
            . ($this instanceof LayoutBlock ? ' data-layout' : '')
            . (1 !== $slice->status ? ' data-offline' : '');
    }

    /**
     * Block in einer Layout-Spalte: ohne eigenen Abschnitt, Hintergrund wie das Layout – ein eigener, anderer Hintergrund
     * macht ihn zur Karte (Klassen sec + bg-*, wie bei Studio).
     */
    public function renderChild(string $parentBg, ?array $data = null, ?array $tunes = null): string
    {
        $t = $tunes ?? $this->tunes;
        $own = (string) ($t['background'] ?? '');
        $card = '' !== $own && $own !== $parentBg && isset(static::kit()->backgrounds()[$own]);
        $t['background'] = $card ? $own : $parentBg;
        $b = $this->block($data, $t, 6);
        static::kit()->markUsed($b->type, $b->variant());
        $inner = static::renderInner($b);
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
        $invalid = !static::nestable($b->variant()) && Editing::active()
            ? '<p class="blocks-invalid">„' . escape(static::label()) . '“ in dieser Darstellung ist für Spalten nicht vorgesehen.</p>'
            : '';

        if ('' === trim($inner) && !Editing::active()) {
            return '';
        }

        return '<div' . $anchor . ' class="' . escape(implode(' ', $cls)) . '"' . $this->editAttrs($b->type, Layout::col($this->slice)) . '>' . $invalid . $inner . "</div>\n";
    }

    /** Ausgabe eines Slices als Abschnitt, optional mit anderen Daten (Vorschau beim Bearbeiten) */
    public function renderBlock(?array $data = null, ?array $tunes = null, int $depth = 0): string
    {
        $b = $this->block($data, $tunes, $depth);
        static::kit()->markUsed($b->type, $b->variant());
        $inner = static::renderInner($b);
        // Block ohne Ausgabe (z. B. Datenliste ohne Tabelle): für Besucher ganz weglassen
        if ('' === trim($inner) && !Editing::active()) {
            return '';
        }
        // aria-labelledby nur, wenn die Überschrift wirklich ausgegeben wird
        $titled = str_contains($inner, 'id="' . $b->titleId() . '"');

        return '<section id="' . escape($b->domId()) . '" class="' . escape($b->sectionClass()) . '"'
            . ($titled ? ' aria-labelledby="' . escape($b->titleId()) . '"' : '') . $this->editAttrs($b->type) . '>'
            . $b->sectionBg() . $inner . "</section>\n";
    }

    /** Block aus Daten (ohne Slice) – z. B. YForm-Feld „Fluid-Blöcke“, siehe BlockData */
    public static function blockFromData(string $id, array $data, array $tunes = [], ?Block $prev = null, int $articleId = 0): Block
    {
        return new Block(null, static::class, FieldForm::withDefaults(static::fields(), $data, static::variants()), $tunes, $prev, $id, $articleId);
    }

    /**
     * Abschnitt eines Blocks aus Daten – wie renderBlock(). Mit $editId (Datensatz-Block, Data\RecordBlocks) und im
     * Bearbeiten-Modus mit dem Markup des Editors, sonst ohne.
     */
    public static function renderData(Block $b, string $editId = ''): string
    {
        static::kit()->markUsed($b->type, $b->variant());
        $inner = static::renderInner($b);
        if ('' === trim($inner)) {
            return '';
        }
        $titled = str_contains($inner, 'id="' . $b->titleId() . '"');

        $edit = '' !== $editId && Editing::active()
            ? ' data-block="' . escape($editId) . '" data-type="' . escape($b->type) . '" data-label="' . escape(static::label()) . '" data-record'
            : '';

        return '<section id="' . escape($b->domId()) . '" class="' . escape($b->sectionClass()) . '"'
            . ($titled ? ' aria-labelledby="' . escape($b->titleId()) . '"' : '') . $edit . '>' . $b->sectionBg() . $inner . "</section>\n";
    }

    /** @internal */
    public function block(?array $data = null, ?array $tunes = null, int $depth = 0): Block
    {
        $prev = null;
        // Vorgänger (z. B. „Text + Bild abwechselnd“) – nur wenige Ebenen, mehr braucht keine Ausgabe
        if ($depth < 6 && ($p = $this->slice->getPreviousSlice(!Editing::active())) && null !== self::classFor($p->moduleKey)) {
            $prev = BlockModule::of($p)->block(null, null, $depth + 1);
        }

        return new Block(
            $this->slice,
            static::class,
            null !== $data ? FieldForm::withDefaults(static::fields(), $data, static::variants()) : $this->data(),
            $tunes ?? $this->tunes,
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

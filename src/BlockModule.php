<?php

namespace FriendsOfRedaxo\Fluid;

use FriendsOfRedaxo\Fluid\Form\FieldForm;
use FriendsOfRedaxo\Fluid\Form\Stored;
use Override;
use Redaxo\Core\Content\ArticleContentEditor;
use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Content\ExtensionPoint\SliceOutput;
use Redaxo\Core\Content\ExtensionPoint\SlicePreview;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Core;
use Redaxo\Core\ExtensionPoint\AsExtension;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionLevel;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Form\Form;
use Redaxo\Core\Form\FormSchema;
use Redaxo\Core\Http\Request;
use ReflectionClass;

use function array_diff_key;
use function array_filter;
use function array_keys;
use function count;
use function in_array;
use function is_a;
use function is_array;
use function json_decode;
use function Redaxo\Core\View\escape;

/**
 * Basis der Blöcke von fluid. Ein Block ist ein Modul des Cores – Felder als Attribute an den Eigenschaften, Ausgabe in
 * output() – mit dem, was fluid dazugibt: Bearbeiten direkt auf der Website, Abschnitt (Hintergrund, Abstände,
 * Sprungmarke), Darstellungen, Layout-Spalten und die Gestaltung durch ein Kit.
 *
 *     #[AsModule('fluid.hinweis', 'Hinweis')]
 *     #[AsBlock(icon: 'info', nestable: true)]
 *     final class Hinweis extends FluidBlock
 *     {
 *         #[Text('Überschrift', required: true, maxLength: 120)]
 *         public string $headline = '';
 *
 *         #[Choice('Art', choices: ['info' => 'Information', 'warn' => 'Warnung'])]
 *         public string $kind = 'info';
 *
 *         public function output(): string
 *         {
 *             return '<div class="wrap"><p class="note note--' . escape($this->kind) . '"' . $this->edit('headline') . '>'
 *                 . escape($this->headline) . '</p></div>';
 *         }
 *     }
 *
 * Die Felder sind die des Cores (Redaxo\Core\Form\Field\…) oder die gleichnamigen von fluid mit optionalen Ergänzungen
 * (FriendsOfRedaxo\Fluid\Field\…: width, variants, placeholder …), dazu Felder, die es nur in fluid gibt (RichText,
 * Link, Icon …). Gespeichert wird wie beim Core: je Feld unter seinem Schlüssel in der JSON-Spalte `data` des Slices,
 * dazu `variant` (Darstellung) und `tunes` (Abschnitt).
 *
 * Ohne eigenes output() gibt der Block die View seines Kits aus (views/{typ}.php mit `$b` = Block, `$d` = Werte als
 * Array, `$m` = das Modul). Den Abschnitt um die Ausgabe legt fluid selbst (Extension Points SliceOutput/SlicePreview).
 */
abstract class BlockModule extends Module
{
    /** Darstellung (Variante) des Blocks – Auswahl „Darstellung“ im Formular */
    #[Stored]
    public string $variant = '';

    /** @var array<string, mixed> Abschnitts-Optionen: Hintergrund, Sprungmarke, Abstände, Spalte im Layout */
    #[Stored]
    public array $tunes = [];

    /** Block, der gerade ausgegeben wird (Kontext für output(): edit(), Darstellung, Abschnitt) */
    public ?Block $block = null;

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

    /**
     * Definition des Blocks: #[AsBlock] an der Klasse und die Felder an den Eigenschaften (Field\BlockSchema), darüber
     * die Angaben des Kits zum Blocktyp (kit.php 'blocks' – z. B. zusätzliche Darstellungen) und der Extension Point
     * FLUID_BLOCK_DEFINITION (Beschriftungen ändern …).
     *
     * @return array<string, mixed>
     */
    public static function definition(): array
    {
        return self::$definitions[static::class] ??= (array) Extension::dispatch(new ExtensionPoint('FLUID_BLOCK_DEFINITION',
            Kit::overlayBlock(Field\BlockSchema::of(static::class, static::name()), static::kit()->block(static::type())),
            ['type' => static::type(), 'module' => static::class, 'kit' => static::kit()]));
    }

    /** Beschriftung des Blocks (#[AsBlock] label, sonst Name des Moduls) */
    public static function label(): string
    {
        return (string) (static::definition()['label'] ?? static::name());
    }

    /** Benötigte AddOns vorhanden? (#[AsBlock(requires: …)], z. B. 'yform') – sonst fehlt der Block in der Auswahl */
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

    // ------------------------------------------------------------------ Werte

    /**
     * Gespeicherte Werte des Slices (ohne Abschnitt), wie in der Spalte `data` – mit `variant` und ggf. `widths`.
     *
     * @return array<string, mixed>
     */
    public function values(): array
    {
        return self::valuesOf($this->slice);
    }

    /**
     * Werte des Slices mit Standardwerten – das `$d` der Views.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return FieldForm::withDefaults(static::fields(), $this->values(), static::variants());
    }

    /**
     * Werte für die Spalte `data` eines Slices (z. B. ContentHandler::addSlice()): Felder je Schlüssel wie beim Core,
     * dazu `tunes`.
     *
     * @param array<string, mixed> $values
     * @param array<string, mixed> $tunes
     * @return array<string, mixed>
     */
    public static function store(array $values, array $tunes = []): array
    {
        unset($values['tunes']);

        return $values + ['tunes' => $tunes];
    }

    /**
     * Werte und Abschnitt aus der Spalte `data` (direkte Abfragen ohne ArticleSlice).
     *
     * @return array{values: array<string, mixed>, tunes: array<string, mixed>}
     */
    public static function stored(?string $json): array
    {
        return self::split(self::decode($json));
    }

    /**
     * Abschnitts-Optionen eines Slices, ohne das Modul zu erzeugen (z. B. für Slices anderer Module).
     *
     * @return array<string, mixed>
     */
    public static function tunesOf(ArticleSlice $slice): array
    {
        return self::split($slice->data)['tunes'];
    }

    /**
     * Gespeicherte Werte eines Slices (ohne Standardwerte und Abschnitt), ohne das Modul zu erzeugen.
     *
     * @return array<string, mixed>
     */
    public static function valuesOf(ArticleSlice $slice): array
    {
        return self::split($slice->data)['values'];
    }

    /** Werte als JSON für die Spalte `data` (direkte Updates ohne Handler) */
    public static function encodeStore(array $values, array $tunes = []): ?string
    {
        return ArticleSlice::encodeData(self::store($values, $tunes));
    }

    /**
     * Spalte `data` → Werte und Abschnitt. Liest auch das frühere Format `{values: {…}, tunes: {…}}` (vor dem Umstieg
     * auf die Speicherung des Cores – `console fluid:upgrade:slices` stellt um).
     *
     * @param array<array-key, mixed> $data
     * @return array{values: array<string, mixed>, tunes: array<string, mixed>}
     */
    private static function split(array $data): array
    {
        $tunes = is_array($data['tunes'] ?? null) ? $data['tunes'] : [];
        if (self::isLegacy($data)) {
            return ['values' => $data['values'], 'tunes' => $tunes];
        }
        unset($data['tunes']);

        return ['values' => $data, 'tunes' => $tunes];
    }

    /** Frühere Speicherung `{values: {…}, tunes: {…}}`? */
    public static function isLegacy(array $data): bool
    {
        return is_array($data['values'] ?? null) && [] === array_diff_key($data, ['values' => 1, 'tunes' => 1]);
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

    // ------------------------------------------------------------------ Formular und Ausgabe

    #[Override]
    public function input(Form $form): string
    {
        // wie beim Core: Eingaben nach Eigenschaft (slice[moreLink]), das Formular des Cores liest sie in die Eigenschaften
        return FieldForm::render(static::class, $this->values(), $this->tunes, 'slice', dataKey: '', tunesKey: 'tunes', byProp: true);
    }

    /**
     * Inhalt des Blocks (ohne Abschnitt) – wie output() bei jedem Modul des Cores. Ohne eigene Methode: die View des
     * Kits (views/{typ}.php). In eigenen Blöcken: Eigenschaften lesen, `$this->edit('feld')` für das Bearbeiten auf der
     * Seite, `$this->context()` für Darstellung und Abschnitt.
     */
    #[Override]
    public function output(): string
    {
        $b = $this->context();
        // Spalten-Blöcke gibt ihr Layout aus (Layout::render) – nicht noch einmal als eigener Slice
        if (null === Block::$rendering && null !== $b->slice && null !== Layout::parentOf($b->slice) && !self::backendPreview()) {
            return '';
        }

        return static::view($b);
    }

    /** Block, der gerade ausgegeben wird – für eine Ausgabe durch den Core aus dem Slice */
    final public function context(): Block
    {
        return $this->block ??= $this->block();
    }

    /**
     * Macht ein Feld im Bearbeiten-Modus direkt auf der Seite bearbeitbar (Attribut am Element).
     * Pfad mit Punkten für Listen: `items.0.title`. Modus: `plain`, `lines` (mehrzeilig), `rich` (Formatierung).
     */
    final protected function edit(string $path, string $mode = 'plain'): string
    {
        return $this->context()->edit($path, $mode);
    }

    /** View des Kits (views/{typ}.php), überschreibbar per FLUID_BLOCK_VIEW */
    public static function viewFile(Block $b): string
    {
        return (string) Extension::dispatch(new ExtensionPoint('FLUID_BLOCK_VIEW', static::kit()->view($b->type), ['type' => $b->type, 'variant' => $b->variant(), 'block' => $b]));
    }

    /** View des Kits ausgeben: `$b` Block, `$d` Werte, `$m` Modul */
    protected function view(Block $b): string
    {
        $outer = Block::$rendering;   // Layout: Spalten-Blöcke werden innerhalb seiner View ausgegeben
        Block::$rendering = $b;
        try {
            return (static function (string $__file, Block $b, array $d, BlockModule $m): string {
                ob_start();
                try {
                    require $__file;
                } finally {
                    $html = (string) ob_get_clean();
                }

                return $html;
            })(static::viewFile($b), $b, $b->data, $this);
        } finally {
            Block::$rendering = $outer;
        }
    }

    /** Inhalt eines Blocks über output() der Instanz – mit dem Block als Kontext */
    private static function inner(self $m, Block $b): string
    {
        Seo\StructuredData::collect($b);   // schema.org der Blöcke (FAQ …) für den Seitenkopf
        static::kit()->definition();   // lädt auch die Hilfsfunktionen des Kits (functions.php) – auch für eigene Blöcke
        $m->block = $b;
        $outer = Block::$rendering;
        Block::$rendering = $b;
        try {
            return $m->output();
        } finally {
            Block::$rendering = $outer;
        }
    }

    /**
     * Instanz mit anderen Werten als gespeichert (Vorschau beim Bearbeiten, Blöcke aus Daten ohne Slice): befüllt wie
     * beim Core, ohne Slice.
     *
     * @param array<string, mixed> $values
     * @param array<string, mixed> $tunes
     */
    public static function withValues(array $values, array $tunes = []): static
    {
        $m = new ReflectionClass(static::class)->newInstanceWithoutConstructor();
        FormSchema::of(static::class)->hydrate($m, self::store($values, $tunes));

        return $m;
    }

    /**
     * @internal Abschnitt um die Ausgabe eines Blocks (Frontend). Nicht in der Slice-Liste des Backends: Dort ist der Inhalt
     * das ganze Panel des Cores (Kopf, Knöpfe, Vorschau) – Spalten-Blöcke verschwanden sonst samt Panel.
     */
    #[AsExtension(SliceOutput::class, ExtensionLevel::Early)]
    public static function wrapOutput(SliceOutput $ep): string
    {
        if (null === self::classFor($ep->slice->moduleKey) || $ep->content instanceof ArticleContentEditor) {
            return (string) $ep->subject;
        }

        return self::of($ep->slice)->wrap((string) $ep->subject);
    }

    /** @internal Vorschau in der Slice-Liste des Backends: Hinweis auf die Spalte im Layout */
    #[AsExtension(SlicePreview::class)]
    public static function wrapPreview(SlicePreview $ep): string
    {
        if (null === self::classFor($ep->slice->moduleKey) || null === Layout::parentOf($ep->slice)) {
            return (string) $ep->subject;
        }

        $parent = Layout::parentOf($ep->slice);
        $data = null !== $parent ? self::of($parent)->context()->data : [];

        return '<div class="blocks-be-nest"><p class="blocks-be-col"><span class="blocks-be-col__n">Spalte ' . Layout::col($ep->slice) . ' / ' . Layout::count($data) . '</span>'
            . ' im Layout ' . escape(Layout::PRESET_LABELS[Layout::preset($data)]) . ($parent ? ' <a href="#slice' . $parent->id . '">↑ zum Layout</a>' : '') . '</p>' . $ep->subject . '</div>';
    }

    /** Kurzbezeichnung eines Slices für Übersichten: Name des Blocks und – falls vorhanden – Überschrift bzw. Titel */
    public static function summary(ArticleSlice $slice): string
    {
        $class = self::classFor($slice->moduleKey);
        if (null === $class) {
            return $slice->moduleKey;
        }
        $data = self::of($slice)->context()->data;
        $title = trim(strip_tags((string) ($data['title'] ?? $data['name'] ?? '')));

        return $class::label() . ('' !== $title ? ' „' . mb_strimwidth((string) preg_replace('~\*([^*]+)\*~u', '$1', $title), 0, 60, '…') . '“' : '');
    }

    /** Ausgabe eines Slices (output()) als Abschnitt – Spalten-Blöcke gibt ihr Layout aus */
    private function wrap(string $inner): string
    {
        if (null !== Layout::parentOf($this->slice)) {
            return '';
        }
        $b = $this->context();
        Seo\StructuredData::collect($b);
        static::kit()->markUsed($b->type, $b->variant());

        return $this->section($b, $inner);
    }

    /** Ausgabe eines Slices als Abschnitt, optional mit anderen Daten (Vorschau beim Bearbeiten) */
    public function renderBlock(?array $data = null, ?array $tunes = null, int $depth = 0): string
    {
        $b = $this->block($data, $tunes, $depth);
        static::kit()->markUsed($b->type, $b->variant());
        $m = null === $data && null === $tunes ? $this : static::withValues($b->data, $b->tunes);

        return $this->section($b, self::inner($m, $b));
    }

    /** Abschnitt (section) mit Hintergrund, Abständen und dem Markup des Editors; leer für Besucher bei leerem Inhalt */
    private function section(Block $b, string $inner): string
    {
        if ('' === trim($inner) && !Editing::active()) {
            return '';
        }
        // aria-labelledby nur, wenn die Überschrift wirklich ausgegeben wird
        $titled = str_contains($inner, 'id="' . $b->titleId() . '"');

        return '<section id="' . escape($b->domId()) . '" class="' . escape($b->sectionClass()) . '"'
            . ($titled ? ' aria-labelledby="' . escape($b->titleId()) . '"' : '') . $this->editAttrs($b->type) . '>'
            . $b->sectionBg() . $inner . "</section>\n";
    }

    /** Vorschau in der Slice-Liste des Backends (nicht für den Cache – der entsteht im Frontend) */
    public static function backendPreview(): bool
    {
        return Core::isBackend() && str_starts_with(Request::get('page', 'string'), 'content');
    }

    /** Darf der Block (in dieser Variante) in einer Layout-Spalte stehen? (#[AsBlock(nestable: true | ['variante', …])]) */
    public static function nestable(string $variant = ''): bool
    {
        $n = static::definition()['nestable'] ?? false;

        return true === $n || (is_array($n) && ('' === $variant || in_array($variant, $n, true)));
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
        $m = null === $data && null === $tunes ? $this : static::withValues($b->data, $b->tunes);
        $inner = self::inner($m, $b);
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

        return '<div' . $anchor . ' class="' . escape(implode(' ', $cls)) . '"' . $this->editAttrs($b->type, Layout::col($this->slice)) . '>' . $invalid . $inner . "</div>\n";
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
        $inner = self::inner(static::withValues($b->data, $b->tunes), $b);
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
}

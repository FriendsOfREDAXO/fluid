<?php

namespace FriendsOfRedaxo\Fluid;

use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidKit;
use ReflectionClass;
use Redaxo\Core\ClassDiscovery;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;

use function array_key_exists;
use function dirname;
use function in_array;
use function is_dir;
use function is_file;

/**
 * Ein Kit: Gestaltung (Template, CSS, JS) plus Blocktypen mit Feldschema – im Format der Studio-Kits.
 *
 * Aufbau eines Kits (Ordner, {@see path()}):
 *   kit.php           Definition: label, blocks (Typ => label, variants, fields …), backgrounds, assets (CSS je Blocktyp),
 *                     css (weitere Stylesheets für jede Seite), design (Style-Editor)
 *   functions.php     Hilfsfunktionen der Ausgaben (wird einmal geladen)
 *   views/{typ}.php   Ausgabe je Blocktyp (`$b` = Block, `$d` = Daten)
 *   template.view.php Seitenrahmen (optional, sonst der des Eltern-Kits)
 *   assets/           CSS, JS, Bilder – fluid veröffentlicht sie nach `assets/fluid-kits/{kit}/`
 *
 * Kits werden mit {@see AsKit} angemeldet und erben von einem Eltern-Kit (Standard: `fluid`). Was ein Kit nicht selbst
 * mitbringt – kit.php-Einträge, Views, Template, Assets, Hilfsfunktionen –, kommt vom Eltern-Kit. In kit.php steht
 * dessen Definition als `$parent` bereit. Welches Kit die Website nutzt, steht in den Website-Einstellungen
 * ({@see active()}); alle Block-Module zeigen sich im gewählten Kit, die Inhalte bleiben beim Wechsel erhalten.
 */
abstract class Kit
{
    /** Das Grund-Kit, von dem alle anderen erben */
    public const string DEFAULT = 'fluid';

    /** @var array<class-string<self>, self> */
    private static array $instances = [];

    /** @var array<string, class-string<self>>|null Schlüssel => Klasse */
    private static ?array $registry = null;

    private static ?string $activeKey = null;

    /** @var array<string, mixed>|null */
    private ?array $def = null;

    private bool $functionsLoaded = false;

    private ?AsKit $attribute = null;

    /** @var array<string, true> In dieser Anfrage benutzte Blocktypen (für das CSS je Block) */
    private array $used = [];

    final private function __construct() {}

    final public static function instance(): static
    {
        /** @var static */
        return self::$instances[static::class] ??= new static();
    }

    // ------------------------------------------------------------------ Anmeldung und Auswahl

    /**
     * Alle angemeldeten Kits (`#[AsKit]` im Projekt, in fluid und in Composer-Paketen).
     *
     * @return array<string, self> Schlüssel => Kit, `fluid` zuerst
     */
    public static function all(): array
    {
        if (null === self::$registry) {
            $map = [self::DEFAULT => FluidKit::class];
            foreach (ClassDiscovery::getInstance()->discoverByAttribute(AsKit::class, self::class) as $class => $attribute) {
                $map[$attribute->key] ??= $class;
            }
            self::$registry = $map;
        }
        $out = [];
        foreach (self::$registry as $key => $class) {
            $out[$key] = $class::instance();
        }

        return $out;
    }

    public static function get(string $key): ?self
    {
        return self::all()[$key] ?? null;
    }

    /**
     * Das Kit der Website: Website-Einstellung „Kit“, anpassbar per Extension Point FLUID_KIT (z. B. je Domain).
     * Unbekannte Schlüssel fallen auf `fluid` zurück.
     */
    public static function active(): self
    {
        if (null === self::$activeKey) {
            $key = (string) (Settings::all()['kit'] ?? '');
            $key = (string) Extension::dispatch(new ExtensionPoint('FLUID_KIT', '' !== $key ? $key : self::DEFAULT));
            self::$activeKey = array_key_exists($key, self::all()) ? $key : self::DEFAULT;
        }

        return self::get(self::$activeKey) ?? FluidKit::instance();
    }

    /** Auswahl neu bestimmen (nach dem Speichern der Einstellung, in Tests) */
    public static function reset(): void
    {
        self::$activeKey = null;
        self::$registry = null;
    }

    /** @return array<string, string> Schlüssel => Name, für die Kit-Auswahl */
    public static function options(): array
    {
        $out = [];
        foreach (self::all() as $key => $kit) {
            $out[$key] = $kit->label();
        }

        return $out;
    }

    // ------------------------------------------------------------------ Angaben des Kits

    /** Schlüssel des Kits, z. B. `fluid` (aus `#[AsKit]`) */
    public function key(): string
    {
        return $this->attribute()->key ?? static::class;
    }

    /** Name in der Auswahl: `#[AsKit]`, sonst `label` aus kit.php */
    public function label(): string
    {
        return $this->attribute()->label ?? (string) ($this->definition()['label'] ?? $this->key());
    }

    /** Eltern-Kit (Standard `fluid`); das Kit `fluid` selbst hat keins */
    public function parent(): ?self
    {
        if (self::DEFAULT === $this->key()) {
            return null;
        }
        $parent = self::get($this->attribute()->parent ?? self::DEFAULT) ?? FluidKit::instance();

        // keine Schleifen: ein Kit, das (über Umwege) von sich selbst erbt, hängt direkt an fluid
        $seen = [];
        for ($p = $parent; null !== $p && self::DEFAULT !== $p->key(); $p = $p->parent()) {
            if ($p === $this || in_array($p, $seen, true)) {
                return FluidKit::instance();
            }
            $seen[] = $p;
        }

        return $parent;
    }

    /**
     * Das Kit und seine Vorfahren, das Kit selbst zuerst.
     *
     * @return list<self>
     */
    final public function lineage(): array
    {
        $out = [];
        for ($k = $this; null !== $k && !in_array($k, $out, true); $k = $k->parent()) {
            $out[] = $k;
        }

        return $out;
    }

    /** Ordner des Kits – Standard: Ordner der Kit-Klasse */
    public function path(string $file = ''): string
    {
        return dirname((string) new ReflectionClass($this)->getFileName()) . ('' !== $file ? '/' . $file : '');
    }

    /** Datei des Kits oder seiner Vorfahren (z. B. `views/hero.php`, `template.view.php`); null = keine */
    final public function file(string $file): ?string
    {
        foreach ($this->lineage() as $kit) {
            if (is_file($kit->path($file))) {
                return $kit->path($file);
            }
        }

        return null;
    }

    // ------------------------------------------------------------------ Definition (kit.php)

    /** @return array<string, mixed> */
    final public function definition(): array
    {
        if (null === $this->def) {
            $this->loadFunctions();
            $parent = $this->parent()?->definition() ?? [];
            $file = $this->path('kit.php');
            /** @var array<string, mixed> $own */
            $own = is_file($file) ? (static fn (array $parent, string $kitFile): array => (array) require $kitFile)($parent, $file) : [];
            $this->def = [] === $parent ? $own : self::merge($parent, $own);
        }

        return $this->def;
    }

    /**
     * Definition eines Kits über die des Eltern-Kits legen: Einträge ersetzen; Blöcke, Hintergründe und die
     * CSS-Abbildung je Blocktyp werden je Schlüssel ergänzt; `css` (Stylesheets für jede Seite) wird angehängt.
     *
     * @param array<string, mixed> $parent
     * @param array<string, mixed> $own
     * @return array<string, mixed>
     */
    private static function merge(array $parent, array $own): array
    {
        $out = array_replace($parent, $own);
        foreach (['blocks', 'backgrounds', 'assets'] as $key) {
            if (isset($parent[$key], $own[$key])) {
                $out[$key] = array_replace((array) $parent[$key], (array) $own[$key]);
            }
        }
        $out['css'] = [...(array) ($parent['css'] ?? []), ...(array) ($own['css'] ?? [])];

        return $out;
    }

    /** @return array<string, mixed> */
    final public function block(string $type): array
    {
        /** @var array<string, mixed> */
        return $this->definition()['blocks'][$type] ?? ['label' => $type, 'fields' => []];
    }

    final public function hasBlock(string $type): bool
    {
        return array_key_exists($type, $this->definition()['blocks'] ?? []);
    }

    /** @return list<string> */
    final public function darkBackgrounds(): array
    {
        /** @var list<string> */
        return $this->definition()['dark_backgrounds'] ?? [];
    }

    /** @return array<string, string> */
    final public function backgrounds(): array
    {
        /** @var array<string, string> */
        return $this->definition()['backgrounds'] ?? ['white' => 'Standard'];
    }

    /** View eines Blocktyps – aus dem Kit oder einem Vorfahren */
    final public function view(string $type): string
    {
        return $this->file('views/' . $type . '.php') ?? $this->path('views/' . $type . '.php');
    }

    // ------------------------------------------------------------------ Assets

    /** URL eines Assets (`css/site.css` …) – aus dem Kit oder einem Vorfahren, mit Versionskennung */
    final public function assetUrl(string $file): string
    {
        foreach ($this->lineage() as $kit) {
            $source = $kit->sourceAsset($file);
            if (is_file($source)) {
                return $kit->publicAssetUrl($file) . '?v=' . substr(md5((string) filemtime($source)), 0, 8);
            }
        }

        return $this->publicAssetUrl($file);
    }

    /** Lokale Quelldatei eines Assets (aus dem Kit oder einem Vorfahren) */
    final public function assetPath(string $file): string
    {
        foreach ($this->lineage() as $kit) {
            if (is_file($kit->sourceAsset($file))) {
                return $kit->sourceAsset($file);
            }
        }

        return $this->sourceAsset($file);
    }

    /** Quelle der Assets dieses Kits (ohne Vorfahren) */
    protected function sourceAsset(string $file): string
    {
        return $this->path('assets/' . $file);
    }

    /**
     * Öffentliche URL eines Assets dieses Kits. Kits außerhalb von fluid werden nach `assets/fluid-kits/{kit}/`
     * veröffentlicht – bei `console migrate` komplett, sonst je Datei, sobald sie fehlt oder sich geändert hat.
     */
    protected function publicAssetUrl(string $file): string
    {
        $source = $this->sourceAsset($file);
        $target = Path::assets('fluid-kits/' . $this->key() . '/' . $file);
        if (is_file($source) && (!is_file($target) || filemtime($source) > filemtime($target))) {
            File::copy($source, $target);
        }

        return Url::assets('fluid-kits/' . $this->key() . '/' . $file);
    }

    /** Assets aller Kits veröffentlichen (bei `console migrate`) */
    public static function publishAll(): void
    {
        foreach (self::all() as $kit) {
            $kit->publish();
        }
    }

    protected function publish(): void
    {
        if (is_dir($this->path('assets'))) {
            Dir::copy($this->path('assets'), Path::assets('fluid-kits/' . $this->key()));
        }
    }

    final public function markUsed(string $type, string $variant = ''): void
    {
        $this->used[$type] = true;
        if ('' !== $variant) {
            $this->used[$type . ':' . $variant] = true;
        }
    }

    /**
     * Stylesheets für jede Seite: `css` aus kit.php (Eltern-Kit zuerst), z. B. das eigene CSS eines abgeleiteten Kits.
     *
     * @return list<string> URLs
     */
    final public function kitCss(): array
    {
        return array_map($this->assetUrl(...), array_values(array_unique((array) ($this->definition()['css'] ?? []))));
    }

    /**
     * CSS-Dateien der in dieser Anfrage ausgegebenen Blöcke (Abbildung `assets` in kit.php, wie bei Studio).
     * Im Bearbeiten-Modus alle, damit neu eingefügte Blöcke sofort gestaltet sind.
     *
     * @return list<string> URLs
     */
    final public function blockCss(bool $all = false): array
    {
        $urls = [];
        /** @var array<string, list<string>> $map */
        $map = $this->definition()['assets'] ?? [];
        foreach ($map as $file => $types) {
            if (!str_ends_with($file, '.css')) {
                continue;
            }
            foreach ($types as $t) {
                if ($all || isset($this->used[$t])) {
                    $urls[] = $this->assetUrl($file);
                    continue 2;
                }
            }
        }

        return $urls;
    }

    /** @return list<string> URLs der Skripte benutzter Blöcke */
    final public function blockJs(): array
    {
        $urls = [];
        /** @var array<string, list<string>> $map */
        $map = $this->definition()['assets'] ?? [];
        foreach ($map as $file => $types) {
            if (str_ends_with($file, '.js') && array_filter($types, fn (string $t) => isset($this->used[$t]))) {
                $urls[] = $this->assetUrl($file);
            }
        }

        return $urls;
    }

    /** Style-Editor des Kits (eigene Werte je Kit) */
    final public function design(): Design
    {
        return Design::for($this);
    }

    /**
     * Stylesheets der gewählten Design-Optionen (kit.php → 'design_css': css/opt-{option}-{wert}.css, sofern vorhanden).
     *
     * @return list<string> URLs
     */
    final public function designCss(?array $values = null): array
    {
        $design = $this->design();
        $values ??= $design->values();
        $out = [];
        foreach ((array) ($this->definition()['design_css'] ?? []) as $opt) {
            $file = 'css/opt-' . $opt . '-' . preg_replace('~[^a-z]~', '', (string) ($values[$opt] ?? '')) . '.css';
            if (is_file($this->assetPath($file))) {
                $out[] = $this->assetUrl($file);
            }
        }

        return $out;
    }

    final public function isDark(string $background): bool
    {
        return in_array($background, $this->darkBackgrounds(), true);
    }

    private function attribute(): ?AsKit
    {
        if (null === $this->attribute) {
            $attributes = new ReflectionClass($this)->getAttributes(AsKit::class);
            $this->attribute = isset($attributes[0]) ? $attributes[0]->newInstance() : null;
        }

        return $this->attribute;
    }

    /** Hilfsfunktionen laden – die der Vorfahren zuerst, damit ein Kit sie nutzen kann */
    private function loadFunctions(): void
    {
        if ($this->functionsLoaded) {
            return;
        }
        $this->functionsLoaded = true;
        $this->parent()?->loadFunctions();
        if (is_file($this->path('functions.php'))) {
            require_once $this->path('functions.php');
        }
    }
}

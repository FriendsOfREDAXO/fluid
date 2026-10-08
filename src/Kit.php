<?php

namespace FriendsOfRedaxo\Blocks;

use Redaxo\Core\Filesystem\Url;

use function array_key_exists;
use function in_array;
use function is_file;

/**
 * Ein Kit: Gestaltung (Template, CSS, JS) plus Blocktypen mit Feldschema – im Format der Studio-Kits.
 *
 * Aufbau eines Kits (Ordner):
 *   kit.php        Definition: label, blocks (Typ => label, variants, fields …), backgrounds, assets (CSS je Blocktyp)
 *   functions.php  Hilfsfunktionen der Ausgaben (wird einmal geladen)
 *   views/{typ}.php Ausgabe je Blocktyp (`$b` = Block, `$d` = Daten)
 *   Assets liegen im Addon unter assets/kits/{kit}/ (veröffentlicht nach `assets/addons/blocks/kits/{kit}/`)
 */
abstract class Kit
{
    /** @var array<class-string<self>, self> */
    private static array $instances = [];

    /** @var array<string, mixed>|null */
    private ?array $def = null;

    private bool $functionsLoaded = false;

    /** @var array<string, true> In dieser Anfrage benutzte Blocktypen (für das CSS je Block) */
    private array $used = [];

    final private function __construct() {}

    final public static function instance(): static
    {
        /** @var static */
        return self::$instances[static::class] ??= new static();
    }

    /** Schlüssel des Kits, z. B. `fluid` */
    abstract public function key(): string;

    /** Ordner des Kits */
    abstract public function path(string $file = ''): string;

    /** @return array<string, mixed> */
    final public function definition(): array
    {
        if (null === $this->def) {
            $this->loadFunctions();
            /** @var array<string, mixed> $def */
            $def = require $this->path('kit.php');
            $this->def = $def;
        }

        return $this->def;
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

    final public function view(string $type): string
    {
        return $this->path('views/' . $type . '.php');
    }

    final public function assetUrl(string $file): string
    {
        $url = Url::addonAssets('blocks', 'kits/' . $this->key() . '/' . $file);
        $local = $this->assetPath($file);

        return is_file($local) ? $url . '?v=' . substr(md5((string) filemtime($local)), 0, 8) : $url;
    }

    /** Lokale Datei eines Assets (Quelle: `assets/kits/{kit}/` des Addons, veröffentlicht nach `assets/addons/blocks/kits/{kit}/`) */
    final public function assetPath(string $file): string
    {
        return dirname(__DIR__) . '/assets/kits/' . $this->key() . '/' . $file;
    }

    final public function markUsed(string $type, string $variant = ''): void
    {
        $this->used[$type] = true;
        if ('' !== $variant) {
            $this->used[$type . ':' . $variant] = true;
        }
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

    final public function isDark(string $background): bool
    {
        return in_array($background, $this->darkBackgrounds(), true);
    }

    private function loadFunctions(): void
    {
        if ($this->functionsLoaded) {
            return;
        }
        $this->functionsLoaded = true;
        if (is_file($this->path('functions.php'))) {
            require_once $this->path('functions.php');
        }
    }
}

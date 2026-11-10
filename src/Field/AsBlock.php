<?php

namespace FriendsOfRedaxo\Fluid\Field;

use Attribute;

/**
 * Angaben zum Block an der Klasse – die Felder stehen als Attribute an den Eigenschaften (siehe {@see Field}).
 *
 *     #[AsModule('fluid.hinweis', 'Hinweis')]
 *     #[AsBlock(icon: 'info', group: 'Inhalt', nestable: true, variants: ['info' => 'Information', 'warn' => 'Warnung'])]
 *     final class Hinweis extends FluidBlock { … }
 *
 * Beschriftung: Name des Moduls (AsModule), außer `label:` ist angegeben.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class AsBlock
{
    public function __construct(
        public readonly string $icon = 'square',
        /** Gruppe in der Blockauswahl (Kopf, Inhalt, Website, Daten …) */
        public readonly string $group = 'Inhalt',
        public readonly ?string $help = null,
        public readonly ?string $label = null,
        /** @var array<string, string> Varianten: Schlüssel => Beschriftung (erste = Standard) */
        public readonly array $variants = [],
        /** @var array<string, string> Wann welche Variante? (Handbuch) */
        public readonly array $variantHelp = [],
        /** @var bool|list<string> In einer Spalte des Blocks „Layout“ erlaubt – ganz oder nur diese Varianten */
        public readonly bool|array $nestable = false,
        /** @var list<array<string, string>> Griffe auf der Seite (field, target, class, apply, label) */
        public readonly array $drags = [],
        /** Benötigtes AddOn (z. B. 'yform') – sonst fehlt der Block in der Auswahl */
        public readonly ?string $requires = null,
        /** Teil der Seite ohne Neuladen aktualisieren (Blättern, Filter; Live::region) */
        public readonly bool $reload = false,
        /** Hintergrund neuer Blöcke */
        public readonly ?string $background = null,
        /** @var array<string, string>|null Strukturierte Daten (schema.org), z. B. ['type' => 'faq', …] */
        public readonly ?array $jsonld = null,
    ) {}
}

<?php

namespace FriendsOfRedaxo\Fluid;

use Attribute;

/**
 * Meldet ein Kit bei fluid an (wie `#[AsModule]` für Module): im Projekt (`src/`) oder in einem eigenen Composer-Paket.
 *
 *     #[AsKit('nature', 'Nature')]
 *     final class NatureKit extends Kit {}
 *
 * Ein Kit erbt vom Kit `parent` (Standard: `fluid`) und bringt nur mit, was es ändert: CSS, einzelne Views, das
 * Template, Design-Optionen oder Blöcke. Alles andere kommt vom Eltern-Kit. `bin/console fluid:kit:create` legt eins an.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsKit
{
    /**
     * @param non-empty-string $key Schlüssel des Kits (Kleinbuchstaben, Ziffern, `-`), z. B. `nature`
     * @param non-empty-string $label Name in der Kit-Auswahl
     * @param string|null $parent Eltern-Kit; `null` = `fluid` (nur das Kit `fluid` selbst hat keins)
     */
    public function __construct(
        public string $key,
        public string $label,
        public ?string $parent = null,
    ) {}
}

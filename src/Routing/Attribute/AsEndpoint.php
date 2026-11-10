<?php

namespace FriendsOfRedaxo\Fluid\Routing\Attribute;

use Attribute;

/**
 * Eigene Adresse, die vor den Seiten beantwortet wird – an einer statischen Methode
 * `fn (string $path, string $rest): void`, die die Anfrage selbst beendet (sonst geht es mit den Seiten weiter).
 *
 *     #[AsEndpoint('/feed.json')]            genau dieser Pfad
 *     #[AsEndpoint('/proxy/*')]              Präfix, der Rest kommt als $rest
 *     #[AsEndpoint('/mcp', param: 'mcp')]    Pfad oder Parameter ?mcp=…
 *     #[AsEndpoint('/live', early: true)]    noch vor der Umleitung von Alias-Domains (Dienste wie SSE)
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class AsEndpoint
{
    public function __construct(
        public string $path,
        public bool $early = false,
        public ?string $param = null,
    ) {}
}

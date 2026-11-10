<?php

namespace FriendsOfRedaxo\Fluid\Chart\Attribute;

use Attribute;

/**
 * Engine für Diagramme – an einer Klasse, die ChartEngine implementiert:
 *
 *     #[AsChartEngine('apex')]
 *     final class ApexEngine implements ChartEngine { public function url(): string { … } }
 *
 * Gewählt wird sie mit Chart::$config = new ChartConfig(engine: 'apex').
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsChartEngine
{
    public function __construct(
        public string $name,
    ) {}
}

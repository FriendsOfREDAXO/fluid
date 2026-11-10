<?php

namespace FriendsOfRedaxo\Fluid\Chart;

/**
 * Einstellungen der Diagramme, gesetzt vom Projekt (wie die Policies des Cores) – in Project::configure():
 *
 *     Chart::$config = new ChartConfig(engine: 'apex');
 *
 * Ohne Angabe zeichnet ECharts.
 */
final readonly class ChartConfig
{
    /**
     * @param string $engine Name einer per #[AsChartEngine] angemeldeten Engine (Standard: echarts)
     */
    public function __construct(
        public string $engine = 'echarts',
    ) {}
}

<?php

namespace FriendsOfRedaxo\Fluid\Chart;

use FriendsOfRedaxo\Fluid\Chart\Attribute\AsChartEngine;
use FriendsOfRedaxo\Fluid\FluidAddon;

/**
 * Standard-Engine: Apache ECharts (Apache-2.0), gebündelt und selbst gehostet – nur Linie, Balken, Torte, Canvas
 * (≈ 550 KB, gzip ≈ 180 KB, lädt erst, wenn ein Diagramm in Sichtweite kommt).
 */
#[AsChartEngine('echarts')]
final class EchartsEngine implements ChartEngine
{
    public function url(): string
    {
        return FluidAddon::instance()->getAssetsUrl('vendor/echarts/charts-echarts.mjs');
    }
}

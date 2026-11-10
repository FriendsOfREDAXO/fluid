<?php

namespace FriendsOfRedaxo\Fluid\Chart;

/**
 * Engine, die Diagramme zeichnet – anmelden mit #[AsChartEngine('name')] an der Klasse.
 *
 * url() ist die Adresse eines ES-Moduls mit genau einer Funktion:
 *
 *     export function render(el, spec, theme) → { update(theme, opts), resize(), dispose(), image() }
 *
 * spec = Chart::normalize() (type, labels, series, title, description, format, unit, stacked, height, locale),
 * theme = { colors[], ink, muted, grid, bg, font, dark } aus den CSS-Variablen an der Stelle des Diagramms.
 * Vorlage: resources/js/charts-echarts.mjs (Quelle von assets/vendor/echarts/charts-echarts.mjs).
 */
interface ChartEngine
{
    /** Adresse des ES-Moduls – absolut oder relativ zur Website */
    public function url(): string;
}

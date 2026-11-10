<?php

namespace FriendsOfRedaxo\Fluid\Chart\Attribute;

use Attribute;

/**
 * Datenquelle für den Block „Diagramm“ – an einer statischen Methode `fn (): array`, die labels und series liefert
 * (optional type, format, unit, description). Die Werte kommen bei jedem Aufruf der Seite frisch:
 *
 *     #[AsChartSource('news.per_month', 'Neuigkeiten je Monat')]
 *     public static function perMonth(): array
 *     {
 *         return ['labels' => ['Jan', 'Feb'], 'series' => [['name' => 'Beiträge', 'data' => [4, 7]]]];
 *     }
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class AsChartSource
{
    public function __construct(
        public string $key,
        public string $label,
    ) {}
}

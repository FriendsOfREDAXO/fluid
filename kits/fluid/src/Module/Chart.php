<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module;

use FriendsOfRedaxo\Fluid\Chart\Chart as Charts;
use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\{Checkbox, Choice, Collection, Text, Textarea};
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use FriendsOfRedaxo\Fluid\Kit\Fluid\Module\Item\ChartSeries;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.chart', 'Diagramm')]
#[AsBlock(
    icon: 'chart-line-up',
    group: 'Inhalt',
    help: 'Balken, Linie, Fläche, Torte oder Ring – Werte von Hand oder aus einer Datenquelle der Website. Die Zahlen stehen zusätzlich als Tabelle bereit (Screenreader, ohne JavaScript).',
    variants: [
        'standard' => 'Diagramm unter der Überschrift',
        'split' => 'Text links, Diagramm rechts',
    ],
    nestable: true,
)]
final class Chart extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half')]
    public string $title = '';

    #[Textarea('Einleitung (optional)', maxLength: 600, rows: 3)]
    public string $intro = '';

    #[Choice('Art', choices: Charts::TYPE_LABELS, required: true, width: 'half')]
    public string $chartType = 'bar';

    #[Choice('Daten', choices: static function (): array { return Charts::sourceChoices(); }, width: 'half',
        note: 'Von Hand oder aus einer Quelle der Website (#[AsChartSource]) – dann sind die Werte immer aktuell.')]
    public string $source = '';

    #[Textarea('Beschriftungen (eine je Zeile)', maxLength: 4000, rows: 5, placeholder: "Januar\nFebruar\nMärz",
        note: 'Nur bei „Werte von Hand eingeben“.')]
    public string $labels = '';

    #[Collection('Reihen', item: ChartSeries::class, itemLabel: 'Reihe', titleField: 'name', max: 8)]
    public array $series = [];

    #[Choice('Zahlen', choices: Charts::FORMATS, required: true, width: 'half')]
    public string $format = 'number';

    #[Text('Einheit (optional)', maxLength: 12, placeholder: 'z. B. €, kg', width: 'half')]
    public string $unit = '';

    #[Checkbox('Reihen stapeln (Balken, Fläche)', width: 'half')]
    public bool $stacked = false;

    #[Choice('Höhe', choices: Charts::HEIGHTS, required: true, width: 'half')]
    public string $height = '320';

    #[Choice('Tabelle unter dem Diagramm', choices: Charts::TABLE_VIEWS, width: 'half',
        note: '„Nicht anbieten“ nur, wenn dieselben Zahlen schon als Tabelle auf der Seite stehen.')]
    public string $tableView = '';

    #[Text('Beschreibung für Screenreader', maxLength: 300,
        note: 'Ein Satz, was das Diagramm zeigt (z. B. „Anfragen je Monat, höchster Wert im Mai“). Leer: die Überschrift.')]
    public string $description = '';

    /**
     * Beschreibung des Diagramms aus den Feldern (von Hand oder aus der gewählten Quelle).
     *
     * @param array<string, mixed> $d Werte des Blocks (Schlüssel wie gespeichert)
     * @return array<string, mixed>
     */
    public static function spec(array $d): array
    {
        $key = (string) ($d['source'] ?? '');
        $spec = '' !== $key ? Charts::fromSource($key) : Charts::fromInput((string) ($d['labels'] ?? ''), array_values((array) ($d['series'] ?? [])));

        return [
            'type' => (string) ($d['chart_type'] ?? '') ?: ($spec['type'] ?? 'bar'),
            'format' => (string) ($d['format'] ?? '') ?: ($spec['format'] ?? 'number'),
            'unit' => (string) ($d['unit'] ?? '') ?: (string) ($spec['unit'] ?? ''),
            'stacked' => !empty($d['stacked']),
            'height' => (int) ($d['height'] ?? 320),
            'description' => trim((string) ($d['description'] ?? '')) ?: trim((string) ($spec['description'] ?? '')) ?: trim((string) ($d['title'] ?? '')),
        ] + $spec;
    }

    /** Feld „Tabelle unter dem Diagramm“ → Option table von Chart::render() */
    public static function tableOption(array $d): bool|string
    {
        return match ((string) ($d['table_view'] ?? '')) {
            'open' => true,
            'none' => 'none',
            default => false,
        };
    }
}

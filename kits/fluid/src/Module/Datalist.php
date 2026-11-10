<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module;

use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\{Checkbox, Choice, Number, Text, Textarea, YFormField, YFormTable};
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.datalist', 'Datenliste')]
#[AsBlock(
    icon: 'rows',
    group: 'Daten',
    help: 'Einträge einer YForm-Tabelle als Karten, Liste oder Tabelle – mit Detailansicht auf derselben Seite. Die Tabelle ist eine Klasse im Projekt (#[AsTable]); im Bearbeiten-Modus öffnet „Bearbeiten“ am Eintrag die Maske von YForm im Popup.',
    requires: 'yform',
    variants: ['cards' => 'Karten', 'list' => 'Liste', 'table' => 'Tabelle'],
    reload: true,
)]
final class Datalist extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half')]
    public string $title = '';

    #[Textarea('Einleitung (optional)', maxLength: 400, rows: 2)]
    public string $intro = '';

    #[YFormTable('YForm-Tabelle', required: true)]
    public string $table = '';

    #[YFormField('Titel', width: 'half', note: 'Leer = erstes Textfeld')]
    public string $titleField = '';

    #[YFormField('Bild', width: 'half', note: 'Leer = erstes Medienfeld')]
    public string $imageField = '';

    #[YFormField('Text', width: 'half', note: 'Leer = erstes mehrzeiliges Feld')]
    public string $textField = '';

    #[YFormField('Zusatzangabe (z. B. Datum, Ort)', width: 'half')]
    public string $metaField = '';

    #[YFormField('Nur Einträge, bei denen …', width: 'half', note: 'z. B. „Online“')]
    public string $filterField = '';

    #[Text('… gleich ist', maxLength: 100, placeholder: '1', width: 'half')]
    public string $filterValue = '';

    #[YFormField('Sortieren nach', width: 'half', note: 'Leer = Einstellung der Tabelle')]
    public string $sortField = '';

    #[Choice(
        'Reihenfolge',
        required: true,
        choices: ['asc' => 'Aufsteigend (A–Z, älteste zuerst)', 'desc' => 'Absteigend (Z–A, neueste zuerst)'],
        width: 'half',
    )]
    public string $sortDir = 'asc';

    #[Number('Höchstens bzw. je Seite (0 = alle)', width: 'half')]
    public int $limit = 0;

    #[Checkbox('Blättern (Seite 2, 3 …)', width: 'half', note: 'Mit „Höchstens“ als Anzahl je Seite.')]
    public bool $pager = false;

    #[Choice(
        'Breite je Eintrag (mindestens)',
        required: true,
        choices: self::SIZES,
        width: 'half',
        note: 'Wie viele Einträge nebeneinander passen, ergibt sich aus dem verfügbaren Platz – auf jedem Gerät.',
    )]
    public string $size = 'm';

    #[Checkbox(
        'Einträge verlinken (Detailansicht mit allen Feldern)',
        note: 'Eigene Adresse je Eintrag, z. B. /presse/titel-12 – mit Titel und Beschreibung für Suchmaschinen und in der Sitemap.',
    )]
    public bool $detail = true;

    #[Text(
        'Text, wenn keine Einträge da sind',
        maxLength: 160,
        placeholder: 'Zurzeit gibt es hier keine Einträge.',
    )]
    public string $emptyText = '';
}

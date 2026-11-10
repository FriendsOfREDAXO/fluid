<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module\Item;

use FriendsOfRedaxo\Fluid\Field\{Text, Textarea};

/** Reihe im Block „Diagramm“ (Werte von Hand) */
final class ChartSeries
{
    #[Text('Name', required: true, maxLength: 60, placeholder: 'z. B. Anfragen')]
    public string $name = '';

    #[Textarea('Werte (je Beschriftung einer, getrennt mit ; oder Zeilenumbruch)', maxLength: 4000, rows: 3, placeholder: '12; 19; 15')]
    public string $values = '';
}

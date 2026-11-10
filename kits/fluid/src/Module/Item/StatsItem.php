<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module\Item;

use FriendsOfRedaxo\Fluid\Field\Text;

/** Eintrag von „Kennzahlen“ im Block Stats */
final class StatsItem
{
    #[Text('Wert', required: true, maxLength: 12, placeholder: 'z. B. 25+', width: 'half')]
    public string $value = '';

    #[Text('Bezeichnung', required: true, maxLength: 50, width: 'half')]
    public string $label = '';

    #[Text('Erläuterung (optional)', maxLength: 140)]
    public string $text = '';
}

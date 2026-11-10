<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module\Item;

use FriendsOfRedaxo\Fluid\Field\Icon;
use FriendsOfRedaxo\Fluid\Field\Text;
use FriendsOfRedaxo\Fluid\Field\Textarea;

/** Eintrag von „Schritte“ im Block Steps */
final class StepsItem
{
    #[Text('Phase / Datum (optional)', maxLength: 40, placeholder: 'z. B. Woche 1', width: 'half')]
    public string $meta = '';

    #[Text('Titel', required: true, maxLength: 80, width: 'half')]
    public string $title = '';

    #[Textarea('Text', maxLength: 400, rows: 3)]
    public string $text = '';

    #[Icon('Symbol statt Nummer (optional)')]
    public string $icon = '';
}

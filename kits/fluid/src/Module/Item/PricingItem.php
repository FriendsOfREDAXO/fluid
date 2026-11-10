<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module\Item;

use FriendsOfRedaxo\Fluid\Field\Checkbox;
use FriendsOfRedaxo\Fluid\Field\Link;
use FriendsOfRedaxo\Fluid\Field\Text;
use FriendsOfRedaxo\Fluid\Field\Textarea;

/** Eintrag von „Pakete“ im Block Pricing */
final class PricingItem
{
    #[Text('Name', required: true, maxLength: 40, width: 'half')]
    public string $name = '';

    #[Text('Etikett (optional)', maxLength: 24, placeholder: 'z. B. Empfohlen', width: 'half')]
    public string $badge = '';

    #[Text('Preis', maxLength: 24, placeholder: 'z. B. ab 490 €', width: 'half')]
    public string $price = '';

    #[Text('Zeitraum / Zusatz', maxLength: 32, placeholder: 'z. B. pro Monat', width: 'half')]
    public string $period = '';

    #[Textarea('Kurzbeschreibung', maxLength: 220, rows: 2)]
    public string $text = '';

    #[Textarea('Leistungen (eine pro Zeile, bei „Karten“)', rows: 4)]
    public string $features = '';

    #[Checkbox('Hervorheben')]
    public bool $highlight = false;

    #[Text('Button – Beschriftung', maxLength: 32, width: 'half')]
    public string $buttonLabel = '';

    #[Link('Button – Link', width: 'half')]
    public string $buttonLink = '';
}

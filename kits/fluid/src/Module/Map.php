<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module;

use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\{Checkbox, Choice, Geo, Text};
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use FriendsOfRedaxo\Fluid\Maps\Maps;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.map', 'Karte')]
#[AsBlock(
    icon: 'map-trifold',
    group: 'Website',
    help: 'Interaktive Karte (OpenStreetMap-Daten über den eigenen Server – ohne Einwilligung, ohne Cookies).',
    nestable: true,
)]
final class Map extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half')]
    public string $title = '';

    #[Choice(
        'Ort',
        required: true,
        choices: ['site' => 'Standort der Website (Website → Kontakt & Zeiten)', 'custom' => 'Eigener Ort'],
    )]
    public string $location = 'site';

    #[Geo(
        'Eigener Ort',
        note: '„Breite, Länge“ – oder „Aus Adresse ermitteln“ mit der Adresse unten.',
    )]
    public string $point = '';

    #[Text('Beschriftung', placeholder: 'Standard: Name der Website', width: 'half')]
    public string $label = '';

    #[Text('Adresse unter der Karte', placeholder: 'Standard: Adresse der Website', width: 'half')]
    public string $address = '';

    #[Choice(
        'Zoomstufe',
        required: true,
        choices: [
            11 => '11 – Stadt',
            13 => '13 – Stadtteil',
            15 => '15 – Straße',
            16 => '16',
            17 => '17 – Gebäude',
        ],
        width: 'half',
    )]
    public string $zoom = '15';

    #[Choice('Höhe', required: true, choices: Maps::HEIGHTS, width: 'half')]
    public string $height = 'm';

    #[Choice('Kartenstil', choices: Maps::STYLES, width: 'half')]
    public string $style = 'liberty';

    #[Checkbox('Erst nach Klick laden (Zwei-Klick)', width: 'half')]
    public bool $click = false;

    #[Checkbox('Link „Route planen“ anzeigen')]
    public bool $route = true;
}

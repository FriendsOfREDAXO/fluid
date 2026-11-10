<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module\Item;

use FriendsOfRedaxo\Fluid\Field\MediaPicker;
use FriendsOfRedaxo\Fluid\Field\Text;
use Redaxo\Core\MediaPool\Media;

/** Eintrag von „Dateien (bei „Einzelne Dateien“)“ im Block Downloads */
final class DownloadsFile
{
    #[Text('Bezeichnung', width: 'half', note: 'Leer = Titel der Datei')]
    public string $label = '';

    #[MediaPicker('Datei', width: 'half')]
    public ?Media $file = null;

    #[Text('Hinweis (optional)')]
    public string $note = '';
}

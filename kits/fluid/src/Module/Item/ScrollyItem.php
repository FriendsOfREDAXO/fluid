<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module\Item;

use FriendsOfRedaxo\Fluid\Field\MediaPicker;
use FriendsOfRedaxo\Fluid\Field\Text;
use FriendsOfRedaxo\Fluid\Field\Textarea;
use Redaxo\Core\MediaPool\Media;

/** Eintrag von „Abschnitte“ im Block Scrolly */
final class ScrollyItem
{
    #[Text('Dachzeile (optional)', maxLength: 40, width: 'half')]
    public string $eyebrow = '';

    #[Text('Titel', required: true, maxLength: 90, width: 'half')]
    public string $title = '';

    #[Textarea('Text', maxLength: 600, rows: 4)]
    public string $text = '';

    #[MediaPicker('Bild', preview: true)]
    public ?Media $image = null;
}

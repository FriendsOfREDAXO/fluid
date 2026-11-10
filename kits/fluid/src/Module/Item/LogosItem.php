<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module\Item;

use FriendsOfRedaxo\Fluid\Field\Link;
use FriendsOfRedaxo\Fluid\Field\MediaPicker;
use FriendsOfRedaxo\Fluid\Field\Text;
use Redaxo\Core\MediaPool\Media;

/** Eintrag von „Logos“ im Block Logos */
final class LogosItem
{
    #[Text('Name', required: true, width: 'half')]
    public string $name = '';

    #[Link('Link (optional)', width: 'half')]
    public string $link = '';

    #[MediaPicker('Logo (SVG oder PNG, transparent)', preview: true)]
    public ?Media $image = null;
}

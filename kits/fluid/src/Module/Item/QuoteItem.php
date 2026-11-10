<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module\Item;

use FriendsOfRedaxo\Fluid\Field\MediaPicker;
use FriendsOfRedaxo\Fluid\Field\Text;
use FriendsOfRedaxo\Fluid\Field\Textarea;
use Redaxo\Core\MediaPool\Media;

/** Eintrag von „Zitate“ im Block Quote */
final class QuoteItem
{
    #[Textarea('Zitat', required: true, maxLength: 480, rows: 3)]
    public string $text = '';

    #[Text('Name', width: 'half')]
    public string $name = '';

    #[Text('Funktion / Organisation', width: 'half')]
    public string $role = '';

    #[MediaPicker('Porträt (optional, 1:1)', preview: true)]
    public ?Media $image = null;
}

<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module\Item;

use FriendsOfRedaxo\Fluid\Field\{Choice, Link, MediaPicker, Text, Textarea};
use Redaxo\Core\MediaPool\Media;

/** Folie im Block „Slider“ */
final class Slide
{
    #[MediaPicker('Bild', required: true, types: ['jpg', 'jpeg', 'png', 'webp', 'avif'], preview: true)]
    public ?Media $image = null;

    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Titel (optional)', maxLength: 90, width: 'half')]
    public string $title = '';

    #[Textarea('Text (optional)', maxLength: 300, rows: 2)]
    public string $text = '';

    #[Text('Button – Beschriftung', maxLength: 32, width: 'half')]
    public string $buttonLabel = '';

    #[Link('Button – Link', width: 'half')]
    public string $buttonLink = '';

    #[Choice('Textposition', required: true, width: 'half', choices: [
        'bottom-left' => 'Unten links', 'bottom-center' => 'Unten mittig', 'center-left' => 'Mitte links', 'center' => 'Mitte', 'top-left' => 'Oben links',
    ])]
    public string $position = 'bottom-left';

    #[Choice('Bild hinter dem Text', required: true, width: 'half', choices: [
        'dark' => 'Abdunkeln (helle Schrift)', 'light' => 'Aufhellen (dunkle Schrift)', 'box' => 'Textkasten', 'none' => 'Unverändert',
    ])]
    public string $overlay = 'dark';
}

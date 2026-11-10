<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module\Item;

use FriendsOfRedaxo\Fluid\Data\YFormBridge;
use FriendsOfRedaxo\Fluid\Field\Checkbox;
use FriendsOfRedaxo\Fluid\Field\Choice;
use FriendsOfRedaxo\Fluid\Field\Text;
use FriendsOfRedaxo\Fluid\Field\Textarea;

/** Eintrag von „Felder des eigenen Formulars“ im Block Form */
final class FormField
{
    #[Choice('Art', required: true, choices: static function (): array { return YFormBridge::builderTypes(); }, width: 'half')]
    public string $type = 'text';

    #[Checkbox('Pflichtfeld', width: 'half')]
    public bool $required = false;

    #[Text('Bezeichnung (bei „Neuer Schritt“: Titel, bei Hinweis: Text)', maxLength: 300)]
    public string $label = '';

    #[Textarea('Auswahl: eine Möglichkeit je Zeile', rows: 3)]
    public string $options = '';

    #[Text('Platzhalter (optional)', maxLength: 120, width: 'half')]
    public string $placeholder = '';

    #[Text('Hilfetext (optional)', maxLength: 200, width: 'half')]
    public string $help = '';

    #[Text(
        'Feldname (optional, für eine YForm-Tabelle)',
        maxLength: 40,
        placeholder: 'automatisch aus der Bezeichnung',
        search: false,
    )]
    public string $name = '';
}

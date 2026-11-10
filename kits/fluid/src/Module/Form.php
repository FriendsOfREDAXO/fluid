<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module;

use FriendsOfRedaxo\Fluid\Data\YFormBridge;
use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\{Checkbox, Choice, Link, Repeater, Text, Textarea, YFormForm, YFormTable};
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.form', 'Formular')]
#[AsBlock(
    icon: 'envelope-simple',
    group: 'Daten',
    help: 'Formular mit YForm – selbst zusammengestellt (Felder, Schritte, speichern und/oder per E-Mail) oder eine Formular-Klasse aus dem Projekt (#[AsForm]). Prüfung, Schutz vor Bots und CSRF über YForm.',
    requires: 'yform',
    variants: ['single' => 'Formular unter der Überschrift', 'split' => 'Text links, Formular rechts'],
    nestable: true,
)]
final class Form extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half')]
    public string $title = '';

    #[Textarea('Einleitung (optional)', maxLength: 400, rows: 2)]
    public string $intro = '';

    #[YFormForm('Formular aus dem Projekt (optional)', note: 'Leer = eigenes Formular aus den Feldern unten.')]
    public string $form = '';

    #[Repeater(
        'Felder des eigenen Formulars',
        item: 'Feld',
        titleField: 'label',
        note: '„— Neuer Schritt —“ teilt das Formular in Schritte mit „Weiter“ und „Zurück“.',
        fields: [
            new Choice(
                'Art',
                key: 'type',
                required: true,
                choices: [YFormBridge::class, 'builderTypes'],
                width: 'half',
                default: 'text',
            ),
            new Checkbox('Pflichtfeld', key: 'required', width: 'half'),
            new Text(
                'Bezeichnung (bei „Neuer Schritt“: Titel, bei Hinweis: Text)',
                key: 'label',
                maxLength: 300,
            ),
            new Textarea('Auswahl: eine Möglichkeit je Zeile', key: 'options', rows: 3),
            new Text('Platzhalter (optional)', key: 'placeholder', maxLength: 120, width: 'half'),
            new Text('Hilfetext (optional)', key: 'help', maxLength: 200, width: 'half'),
            new Text(
                'Feldname (optional, für eine YForm-Tabelle)',
                key: 'name',
                maxLength: 40,
                placeholder: 'automatisch aus der Bezeichnung',
                search: false,
            ),
        ],
    )]
    public array $fields = [
        ['type' => 'text', 'label' => 'Name', 'required' => true],
        ['type' => 'email', 'label' => 'E-Mail', 'required' => true],
        ['type' => 'textarea', 'label' => 'Nachricht', 'required' => true],
        [
            'type' => 'checkbox',
            'label' => 'Ich bin mit der Verarbeitung meiner Angaben zur Beantwortung einverstanden.',
            'name' => 'datenschutz',
            'required' => true,
        ],
    ];

    #[Choice(
        'Nach dem Absenden',
        required: true,
        choices: [
            'save_mail' => 'Speichern und per E-Mail senden',
            'save' => 'Nur speichern',
            'mail' => 'Nur per E-Mail senden',
        ],
        width: 'half',
    )]
    public string $mode = 'save_mail';

    #[Text('E-Mail an (mehrere mit Komma)', maxLength: 300, placeholder: 'info@example.org', width: 'half')]
    public string $mailTo = '';

    #[Text(
        'Betreff der E-Mail (optional)',
        maxLength: 150,
        placeholder: 'Neue Nachricht: {name}',
        width: 'half',
    )]
    public string $mailSubject = '';

    #[YFormTable(
        'Speichern in (optional)',
        width: 'half',
        note: 'Leer = „Formular-Eingänge“ (Postfach). Eine eigene YForm-Tabelle übernimmt Felder mit gleichem Feldnamen.',
    )]
    public string $target = '';

    #[Checkbox('Bestätigung an den Absender (an das E-Mail-Feld)', width: 'half')]
    public bool $confirm = false;

    #[Checkbox('Angaben in die Bestätigung kopieren', width: 'half')]
    public bool $confirmCopy = false;

    #[Text('Betreff der Bestätigung', maxLength: 150, placeholder: 'Ihre Nachricht ist angekommen')]
    public string $confirmSubject = '';

    #[Textarea(
        'Text der Bestätigung',
        rows: 3,
        placeholder: 'Vielen Dank für Ihre Nachricht, {name}. Wir melden uns bald.',
    )]
    public string $confirmText = '';

    #[Text('Beschriftung des Buttons', maxLength: 40, placeholder: 'Absenden', width: 'half')]
    public string $submit = '';

    #[Text('Name im Postfach', maxLength: 80, placeholder: 'z. B. Kontakt, Anmeldung', width: 'half')]
    public string $formName = '';

    #[Textarea(
        'Text nach dem Absenden',
        maxLength: 600,
        rows: 2,
        placeholder: 'Vielen Dank! Ihre Nachricht ist angekommen.',
    )]
    public string $success = '';

    #[Link('Stattdessen weiter zu Seite (optional)')]
    public string $redirect = '';

    #[Text('Titel des ersten Schritts (bei Schritten)', maxLength: 80)]
    public string $firstStep = '';

    #[Textarea(
        'Hinweis unter dem Formular (optional)',
        maxLength: 400,
        rows: 2,
        note: 'Zum Beispiel Datenschutz: wofür die Angaben verwendet werden.',
    )]
    public string $note = '';
}

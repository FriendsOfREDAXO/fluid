<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module;

use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\{Checkbox, Text, Textarea, YFormForm};
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.contact', 'Kontakt')]
#[AsBlock(
    label: 'Kontakt (Karte, Formular, Zeiten)',
    icon: 'address-book',
    group: 'Website',
    help: 'Adresse, Telefon, E-Mail, Öffnungszeiten und Karte kommen aus „Website“ (Menü ⋯). Optional ein Formular aus YForm.',
)]
final class Contact extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half')]
    public string $title = '';

    #[Textarea('Einleitung (optional)', maxLength: 400, rows: 2)]
    public string $intro = '';

    #[Checkbox('Öffnungszeiten anzeigen (falls eingetragen)', width: 'half')]
    public bool $showHours = true;

    #[Checkbox('Karte anzeigen (falls Standort eingetragen)', width: 'half')]
    public bool $showMap = true;

    #[YFormForm(
        'Formular (optional, aus YForm)',
        note: 'Formular-Klasse im Projekt (#[AsForm]) – z. B. Rückrufwunsch oder Anfrage.',
    )]
    public string $form = '';

    #[Text('Überschrift über dem Formular', maxLength: 80, placeholder: 'z. B. Schreiben Sie uns')]
    public string $formTitle = '';

    #[Text('Hinweis unten (optional)')]
    public string $note = '';
}

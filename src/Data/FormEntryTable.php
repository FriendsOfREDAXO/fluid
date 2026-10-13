<?php

namespace FriendsOfRedaxo\Fluid\Data;

use Yakamara\YForm\Attribute\AsTable;
use Yakamara\YForm\Field\CreatedAt;
use Yakamara\YForm\Field\Text;
use Yakamara\YForm\Field\Textarea;
use Yakamara\YForm\Table\ListConfig;
use Yakamara\YForm\Table\Table;

// Nur mit YForm (fluid läuft auch ohne)
if (!class_exists(Table::class)) {
    return;
}

/**
 * Eingänge der Formulare, die die Redaktion im Block „Formular“ selbst zusammenstellt (BuiltForm) – eine YForm-Tabelle
 * wie jede andere: im Backend unter YForm, auf der Website im Postfach „Anfragen“. `console migrate` legt sie an.
 */
#[AsTable('rex_fluid_form_entry', label: 'Formular-Eingänge', icon: 'fa-inbox')]
final class FormEntryTable extends Table
{
    public function fields(): iterable
    {
        yield Text::new('form')->label('Formular')->maxLength(191);
        yield Text::new('page')->label('Seite')->maxLength(255);
        yield Textarea::new('summary')->label('Angaben');
        yield Textarea::new('data')->label('Daten (JSON)');
        yield CreatedAt::new('created')->label('Eingegangen');
    }

    public function list(ListConfig $list): ListConfig
    {
        return $list->columns('id', 'form', 'page', 'created')->sortBy('created', 'desc');
    }
}

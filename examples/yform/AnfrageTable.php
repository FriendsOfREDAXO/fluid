<?php

namespace Project\YForm;

use Yakamara\YForm\Attribute\AsTable;
use Yakamara\YForm\Field\Checkbox;
use Yakamara\YForm\Field\Choice;
use Yakamara\YForm\Field\CreatedAt;
use Yakamara\YForm\Field\Email;
use Yakamara\YForm\Field\Text;
use Yakamara\YForm\Field\Textarea;
use Yakamara\YForm\Table\ListConfig;
use Yakamara\YForm\Table\Table;

/** Demo „Anfragen“: Einträge des Formulars AnfrageForm (fluid-Postfach „Anfragen“) */
#[AsTable('rex_yf_anfrage', label: 'Anfragen (Beispiel)', icon: 'fa-envelope')]
final class AnfrageTable extends Table
{
    public const array TOPICS = ['frage' => 'Frage zu fluid', 'feedback' => 'Feedback', 'mitmachen' => 'Mitmachen'];

    public function fields(): iterable
    {
        yield Text::new('name')->label('Name')->required()->maxLength(191);
        yield Email::new('email')->label('E-Mail')->required();
        yield Choice::new('topic')->label('Worum geht es?')->options(self::TOPICS);
        yield Textarea::new('message')->label('Nachricht')->required();
        yield Checkbox::new('privacy')->label('Einwilligung zur Speicherung');
        yield CreatedAt::create('created')->label('Eingegangen');
    }

    public function list(ListConfig $list): ListConfig
    {
        return $list->columns('id', 'created', 'name', 'email', 'topic')->sortBy('created', 'DESC');
    }
}

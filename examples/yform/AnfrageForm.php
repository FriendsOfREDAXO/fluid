<?php

namespace Project\YForm;

use FriendsOfRedaxo\Fluid\Settings;
use Yakamara\YForm\Attribute\AsForm;
use Yakamara\YForm\Field\Checkbox;
use Yakamara\YForm\Field\Choice;
use Yakamara\YForm\Field\Email;
use Yakamara\YForm\Field\Text;
use Yakamara\YForm\Field\Textarea;
use Yakamara\YForm\Form\Action\SaveRecord;
use Yakamara\YForm\Form\Action\SendMail;
use Yakamara\YForm\Form\Action\ShowMessage;
use Yakamara\YForm\Form\Definition\FormDefinition;
use Yakamara\YForm\Validation\Errors;

/** Demo-Formular „Anfrage“: speichert in AnfrageTable, Benachrichtigung an die E-Mail aus „Website“ */
#[AsForm('anfrage', label: 'Anfrage (Beispiel)', module: true)]
final class AnfrageForm extends FormDefinition
{
    public function fields(): iterable
    {
        yield Text::new('name')->label('Name')->required()->attributes(['autocomplete' => 'name']);
        yield Email::new('email')->label('E-Mail')->required()->attributes(['autocomplete' => 'email']);
        yield Choice::new('topic')->label('Worum geht es?')->options(AnfrageTable::TOPICS);
        yield Textarea::new('message')->label('Nachricht')->required();
        yield Checkbox::new('privacy')->label('Ich bin einverstanden, dass meine Angaben zur Beantwortung gespeichert werden.');
    }

    public function validate(array $values, Errors $errors): void
    {
        if (empty($values['privacy'])) {
            $errors->add('privacy', 'Bitte der Speicherung zustimmen.');
        }
    }

    public function submitLabel(): string
    {
        return 'Nachricht senden';
    }

    public function actions(): iterable
    {
        yield new SaveRecord(AnfrageTable::class);
        $to = (string) Settings::get('email', '');
        if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
            yield new SendMail(to: $to, subject: 'Anfrage #{id} von {name}', body: "{name} ({email})\nThema: {topic}\n\n{message}", replyTo: '{email}');
        }
        yield new ShowMessage('<strong>Vielen Dank, {name}!</strong> Ihre Nachricht ist angekommen.');
    }
}

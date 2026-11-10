<?php

namespace Project\Module;

use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Form\Field\Text;
use Redaxo\Core\Form\Field\Textarea;
use Redaxo\Core\Form\Form;
use Redaxo\Core\Http\Response;

use function Redaxo\Core\View\escape;

/**
 * Beispiel: Modul mit eigenem input() – das Formular baut das Modul selbst zusammen. fluid zeigt dafür in der
 * Seitenleiste das Formular des Cores (dieselbe Maske wie im Backend).
 */
#[AsModule('hinweis_eigen', 'Hinweis mit eigenem Formular (Beispiel)')]
final class HinweisEigenesFormularModule extends Module
{
    #[Text('Titel', required: true, maxLength: 120)]
    public string $title = '';

    #[Textarea('Text', rows: 3)]
    public string $text = '';

    public function input(Form $form): string
    {
        return '<p class="rex-field-note">Eigener Aufbau: Hinweis über den Feldern, danach alle Felder.</p>' . $form->rest();
    }

    public function output(): string
    {
        // Keine style-Attribute (Content-Security-Policy des Backends): Klasse und einmal je Seite ein <style nonce>
        static $css = false;
        $style = $css ? '' : '<style nonce="' . escape(Response::getNonce()) . '">.hinweis-eigen{margin-block:2rem;padding:1.25rem 2rem;border:2px dashed var(--f-line-strong,#94a3b8);border-radius:var(--f-radius,6px)}.hinweis-eigen p{margin:.5rem 0 0}</style>';
        $css = true;

        return $style . '<div class="wrap"><aside class="hinweis-eigen">'
            . '<strong>' . escape($this->title) . '</strong>' . ('' !== $this->text ? '<p>' . nl2br(escape($this->text)) . '</p>' : '')
            . '</aside></div>';
    }
}

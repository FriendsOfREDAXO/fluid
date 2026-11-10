<?php

namespace Project\Module;

use DateTimeImmutable;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Form\Field\ArticlePicker;
use Redaxo\Core\Form\Field\Checkbox;
use Redaxo\Core\Form\Field\Choice;
use Redaxo\Core\Form\Field\DatePicker;
use Redaxo\Core\Form\Field\MediaPicker;
use Redaxo\Core\Form\Field\Text;
use Redaxo\Core\Form\Field\Textarea;
use Redaxo\Core\Http\Response;
use Redaxo\Core\MediaPool\Media;

use function Redaxo\Core\View\escape;

/**
 * Beispiel: ein gewöhnliches REDAXO-6-Modul (kein fluid-Block). Felder sind Eigenschaften mit den Feld-Attributen des
 * Cores, die Ausgabe schreibt output(). fluid zeigt die Felder auf der Website in der eigenen Seitenleiste.
 */
#[AsModule('hinweis', 'Hinweis (Beispiel)')]
final class HinweisModule extends Module
{
    #[Text('Überschrift', required: true, maxLength: 120)]
    public string $headline = '';

    #[Textarea('Text', rows: 4)]
    public string $text = '';

    #[Choice('Art', ['info' => 'Information', 'warn' => 'Warnung', 'ok' => 'Erledigt'])]
    public string $kind = 'info';

    #[Checkbox('Hervorheben')]
    public bool $strong = false;

    #[DatePicker('Gültig bis')]
    public ?DateTimeImmutable $until = null;

    #[MediaPicker('Bild', types: ['jpg', 'jpeg', 'png', 'webp'])]
    public ?Media $image = null;

    #[ArticlePicker('Mehr dazu')]
    public ?Article $link = null;

    public function output(): string
    {
        // Keine style-Attribute (Content-Security-Policy des Backends): Klassen und einmal je Seite ein <style nonce>
        $html = self::css() . '<div class="wrap"><aside class="hinweis hinweis--' . escape($this->kind) . ($this->strong ? ' is-strong' : '') . '">';
        $html .= '<h2>' . escape($this->headline) . '</h2>';
        if ('' !== $this->text) {
            $html .= '<p>' . nl2br(escape($this->text)) . '</p>';
        }
        if ($this->until) {
            $html .= '<p class="hinweis__until">Gültig bis ' . $this->until->format('d.m.Y') . '</p>';
        }
        if ($this->image) {
            $html .= '<img class="hinweis__image" src="' . escape($this->image->getUrl()) . '" alt="">';
        }
        if ($this->link) {
            $html .= '<p><a href="' . escape($this->link->getUrl()) . '">' . escape($this->link->name) . ' →</a></p>';
        }

        return $html . '</aside></div>';
    }

    /** Stile des Moduls – einmal je Seite, mit der Nonce der Antwort (erlaubt unter der CSP) */
    private static function css(): string
    {
        static $done = false;
        if ($done) {
            return '';
        }
        $done = true;

        return '<style nonce="' . escape(Response::getNonce()) . '">'
            . '.hinweis{--c:var(--f-a,#2563eb);margin-block:2rem;padding:1.5rem 2rem;border-left:6px solid var(--c);border-radius:var(--f-radius,6px);background:var(--f-surface,#f5f6f8)}'
            . '.hinweis--warn{--c:#dc2626}.hinweis--ok{--c:#16a34a}.hinweis.is-strong{box-shadow:0 0 0 2px var(--c)}'
            . '.hinweis h2{margin:0 0 .5rem}.hinweis p{margin:.75rem 0 0}.hinweis h2+p{margin-top:0}.hinweis__until{font-size:.9em}'
            . '.hinweis__image{display:block;max-width:min(100%,24rem);margin-top:1rem;border-radius:var(--f-radius,6px)}'
            . '</style>';
    }
}

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
        $color = ['warn' => '#dc2626', 'ok' => '#16a34a'][$this->kind] ?? 'var(--f-a, #2563eb)';
        $html = '<div class="wrap"><aside style="margin-block:2rem;padding:1.5rem 2rem;border-left:6px solid ' . $color
            . ';border-radius:var(--f-radius, 6px);background:var(--f-surface, #f5f6f8)' . ($this->strong ? ';box-shadow:0 0 0 2px ' . $color : '') . '">';
        $html .= '<h2 style="margin:0 0 .5rem">' . escape($this->headline) . '</h2>';
        if ('' !== $this->text) {
            $html .= '<p style="margin:0">' . nl2br(escape($this->text)) . '</p>';
        }
        if ($this->until) {
            $html .= '<p style="margin:.75rem 0 0;font-size:.9em">Gültig bis ' . $this->until->format('d.m.Y') . '</p>';
        }
        if ($this->image) {
            $html .= '<img src="' . escape($this->image->getUrl()) . '" alt="" style="display:block;max-width:min(100%,24rem);margin-top:1rem;border-radius:var(--f-radius, 6px)">';
        }
        if ($this->link) {
            $html .= '<p style="margin:.75rem 0 0"><a href="' . escape($this->link->getUrl()) . '">' . escape($this->link->name) . ' →</a></p>';
        }

        return $html . '</aside></div>';
    }
}

<?php

namespace Project\YForm;

use Yakamara\YForm\Attribute\AsTable;
use Yakamara\YForm\Field\Checkbox;
use Yakamara\YForm\Field\Date;
use Yakamara\YForm\Field\Media;
use Yakamara\YForm\Field\Text;
use Yakamara\YForm\Field\Textarea;
use Yakamara\YForm\Table\ListConfig;
use Yakamara\YForm\Table\Table;

/** Demo „Termine“ für den fluid-Block „Datenliste“ (Seite „Daten“) */
#[AsTable('rex_yf_termin', label: 'Termine (Beispiel)', icon: 'fa-calendar')]
final class TerminTable extends Table
{
    public function fields(): iterable
    {
        yield Text::new('title')->label('Titel')->required()->maxLength(191);
        yield Date::new('date')->label('Datum');
        yield Text::new('place')->label('Ort')->maxLength(191);
        yield Media::new('image')->label('Bild')->types('jpg', 'jpeg', 'png', 'webp', 'avif');
        yield Textarea::new('text')->label('Beschreibung');
        yield Checkbox::new('online')->label('Online')->default(true);
    }

    public function list(ListConfig $list): ListConfig
    {
        return $list->columns('id', 'title', 'date', 'place', 'online')->sortBy('date');
    }
}

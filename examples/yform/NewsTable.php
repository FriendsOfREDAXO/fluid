<?php

namespace Project\YForm;

use FriendsOfRedaxo\Fluid\Data\FluidBlocks;
use Yakamara\YForm\Attribute\AsTable;
use Yakamara\YForm\Field\Checkbox;
use Yakamara\YForm\Field\Date;
use Yakamara\YForm\Field\Media;
use Yakamara\YForm\Field\Text;
use Yakamara\YForm\Field\Textarea;
use Yakamara\YForm\Field\Url;
use Yakamara\YForm\Table\ListConfig;
use Yakamara\YForm\Table\Table;

/**
 * Kurzmeldungen (Presse, News) als YForm-Tabelle – Ausgabe mit dem fluid-Block „Datenliste“:
 * Karten oder Liste, nach Datum absteigend, Blättern, Detailansicht mit eigener Adresse (/presse/titel-12).
 * Für Beiträge mit Blöcken, Entwurf und Versionen eignet sich der Blog (Seiten in einer Blog-Kategorie).
 */
#[AsTable('rex_yf_news', label: 'Meldungen (Beispiel)', icon: 'fa-newspaper')]
final class NewsTable extends Table
{
    public function fields(): iterable
    {
        yield Text::new('title')->label('Titel')->required()->maxLength(191);
        yield Date::new('date')->label('Datum')->required();
        yield Textarea::new('teaser')->label('Kurztext');
        yield Media::new('image')->label('Bild')->types('jpg', 'jpeg', 'png', 'webp', 'avif');
        yield Textarea::new('text')->label('Text');
        // Inhalt aus Blöcken von fluid (Text, Bild + Text, Video …) – Ausgabe in der Detailansicht der Datenliste
        yield FluidBlocks::new('content')->label('Inhalt (Blöcke)')->blocks('richtext', 'media_text', 'video', 'quote', 'faq', 'downloads', 'cta');
        yield Url::new('link')->label('Weiterführender Link');
        yield Checkbox::new('online')->label('Online')->default(true);
    }

    public function list(ListConfig $list): ListConfig
    {
        return $list->columns('id', 'title', 'date', 'online')->sortBy('date', 'desc');
    }
}

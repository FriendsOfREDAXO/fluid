<?php

namespace Project\YForm;

use FriendsOfRedaxo\Fluid\Data\FluidBlocks;
use Yakamara\YForm\Attribute\AsTable;
use Yakamara\YForm\Field\Checkbox;
use Yakamara\YForm\Field\Integer;
use Yakamara\YForm\Field\Media;
use Yakamara\YForm\Field\Text;
use Yakamara\YForm\Field\Textarea;
use Yakamara\YForm\Table\ListConfig;
use Yakamara\YForm\Table\Table;

/**
 * Projekte / Referenzen: feste Angaben (Kunde, Jahr, Bild, Kurztext) für Übersicht und Suche – und eine Fallstudie aus
 * Blöcken (Feld „Fluid-Blöcke“), die die Redaktion direkt auf der Website gestaltet (Detailansicht der Datenliste).
 */
#[AsTable('rex_yf_project', label: 'Projekte (Beispiel)', icon: 'fa-briefcase')]
final class ProjectTable extends Table
{
    public function fields(): iterable
    {
        yield Text::new('title')->label('Projekt')->required()->maxLength(191);
        yield Text::new('client')->label('Kunde')->maxLength(191);
        yield Integer::new('year')->label('Jahr')->min(1990)->max(2100);
        yield Media::new('image')->label('Bild')->types('jpg', 'jpeg', 'png', 'webp', 'avif');
        yield Textarea::new('teaser')->label('Kurztext');
        yield FluidBlocks::new('story')->label('Fallstudie (Blöcke)')
            ->blocks('richtext', 'media_text', 'stats', 'quote', 'features', 'faq', 'video', 'cta');
        yield Checkbox::new('online')->label('Online')->default(true);
    }

    public function list(ListConfig $list): ListConfig
    {
        return $list->columns('id', 'title', 'client', 'year', 'online')->sortBy('year', 'desc');
    }
}

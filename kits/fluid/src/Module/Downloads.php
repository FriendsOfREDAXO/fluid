<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module;

use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\{Checkbox, Choice, Collection, MediaCategory, MediaPicker, Text, Textarea};
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;
use FriendsOfRedaxo\Fluid\Kit\Fluid\Module\Item\DownloadsFile;

#[AsModule('fluid.downloads', 'Downloads')]
#[AsBlock(
    icon: 'download-simple',
    group: 'Medien',
    help: 'Dateien aus dem Medienpool zum Herunterladen; PDFs auf Wunsch auch zum Ansehen. Größe und Typ erscheinen automatisch.',
    nestable: true,
)]
final class Downloads extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half')]
    public string $title = '';

    #[Textarea('Einleitung (optional)', maxLength: 400, rows: 2)]
    public string $intro = '';

    #[Choice(
        'Quelle',
        required: true,
        choices: ['manual' => 'Einzelne Dateien auswählen', 'collection' => 'Alle Dateien einer Medienkategorie'],
        width: 'half',
    )]
    public string $source = 'manual';

    #[MediaCategory('Medienkategorie', width: 'half')]
    public string $collection = '';

    #[Collection(
        'Dateien (bei „Einzelne Dateien“)',
        item: DownloadsFile::class,
        itemLabel: 'Datei',
        titleField: 'label',
    )]
    public array $files = [];

    #[Checkbox('PDFs zusätzlich „Ansehen“ anbieten (öffnet im Browser)')]
    public bool $showViewer = true;
}

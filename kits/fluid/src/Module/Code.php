<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module;

use FriendsOfRedaxo\Fluid\Field\AsBlock;
use FriendsOfRedaxo\Fluid\Field\{Choice, Repeater, Text, Textarea};
use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.code', 'Code')]
#[AsBlock(
    icon: 'code',
    group: 'Inhalt',
    help: 'Codebeispiele zum Kopieren – eine oder mehrere Dateien untereinander (z. B. Klasse und View). Der Code wird so ausgegeben, wie er eingegeben ist.',
    nestable: true,
)]
final class Code extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half')]
    public string $title = '';

    #[Textarea('Einleitung (optional)', maxLength: 400, rows: 2)]
    public string $intro = '';

    #[Repeater(
        'Dateien',
        item: 'Datei',
        titleField: 'file',
        min: 1,
        max: 6,
        fields: [
            new Text(
                'Dateiname oder Titel (optional)',
                key: 'file',
                maxLength: 120,
                placeholder: 'z. B. src/Module/Text.php',
                width: 'half',
            ),
            new Choice(
                'Sprache',
                key: 'lang',
                required: true,
                choices: [
                    'php' => 'PHP',
                    'bash' => 'Terminal',
                    'env' => '.env',
                    'json' => 'JSON',
                    'yaml' => 'YAML',
                    'html' => 'HTML',
                    'css' => 'CSS',
                    'js' => 'JavaScript',
                    'text' => 'Text',
                ],
                width: 'half',
                default: 'php',
            ),
            new Textarea('Code', key: 'code', required: true, maxLength: 12000, rows: 12, search: false),
        ],
    )]
    public array $items = [];

    #[Text('Hinweis unter dem Code (optional)', maxLength: 240)]
    public string $note = '';
}

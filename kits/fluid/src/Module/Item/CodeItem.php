<?php

namespace FriendsOfRedaxo\Fluid\Kit\Fluid\Module\Item;

use FriendsOfRedaxo\Fluid\Field\Choice;
use FriendsOfRedaxo\Fluid\Field\Text;
use FriendsOfRedaxo\Fluid\Field\Textarea;

/** Eintrag von „Dateien“ im Block Code */
final class CodeItem
{
    #[Text(
        'Dateiname oder Titel (optional)',
        maxLength: 120,
        placeholder: 'z. B. src/Module/Text.php',
        width: 'half',
    )]
    public string $file = '';

    #[Choice(
        'Sprache',
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
    )]
    public string $lang = 'php';

    #[Textarea('Code', required: true, maxLength: 12000, rows: 12, search: false)]
    public string $code = '';
}

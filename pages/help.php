<?php

use Redaxo\Core\Util\Markdown;
use Redaxo\Core\View\Fragment;

$fragment = new Fragment();
$fragment->setVar('title', 'Hilfe', false);
$fragment->setVar('body', '<div class="rex-docs">' . Markdown::factory()->parse((string) file_get_contents(dirname(__DIR__) . '/README.md')) . '</div>', false);
echo $fragment->parse('core/page/section.php');

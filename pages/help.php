<?php

use Redaxo\Core\Util\Markdown;
use Redaxo\Core\View\Fragment;

// README (Einstieg) und Entwickler-Referenz; Bilder (relative Pfade fürs Repository) im Backend weglassen
foreach (['Hilfe' => 'README.md', 'Entwickler-Referenz' => 'docs/entwickler.md'] as $title => $file) {
    $md = (string) @file_get_contents(dirname(__DIR__) . '/' . $file);
    $md = (string) preg_replace('~^\s*!\[[^\]]*\]\([^)]*\)\s*$~m', '', $md);
    $md = (string) preg_replace('~\|\s*!\[[^\]]*\]\([^)]*\)\s*\|\s*!\[[^\]]*\]\([^)]*\)\s*\|~', '', $md);
    $fragment = new Fragment();
    $fragment->setVar('title', $title, false);
    $fragment->setVar('body', '<div class="rex-docs">' . Markdown::factory()->parse($md) . '</div>', false);
    echo $fragment->parse('core/page/section.php');
}

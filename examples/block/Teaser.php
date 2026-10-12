<?php

namespace Project\Block;

use FriendsOfRedaxo\Fluid\Kit\Fluid\FluidBlock;
use Override;
use Redaxo\Core\Content\AsModule;

/** Eigener Block im Projekt: Klasse + Teaser.view.php daneben – das Kit bleibt unverändert. */
#[AsModule('fluid.teaser', 'Teaser (Projekt)')]
final class Teaser extends FluidBlock
{
    #[Override]
    protected function define(): array
    {
        return [
            'label' => 'Teaser (Projekt)', 'icon' => 'star', 'group' => 'Inhalt', 'nestable' => true,
            'help' => 'Beispiel für einen eigenen Block im Projekt.',
            'fields' => [
                ['name' => 'title', 'label' => 'Überschrift', 'type' => 'text', 'required' => true, 'max' => 80],
                ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 3],
                ['name' => 'link', 'label' => 'Link', 'type' => 'link'],
            ],
        ];
    }
}

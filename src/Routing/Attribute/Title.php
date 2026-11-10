<?php

namespace FriendsOfRedaxo\Fluid\Routing\Attribute;

use Attribute;

/** Spalte: Titel für Suchmaschinen */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Title extends MetaColumn
{
    public function role(): string
    {
        return 'title';
    }

    public function kind(): string
    {
        return 'text';
    }

    public function defaultLabel(): string
    {
        return 'Titel für Suchmaschinen';
    }
}

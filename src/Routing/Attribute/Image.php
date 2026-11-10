<?php

namespace FriendsOfRedaxo\Fluid\Routing\Attribute;

use Attribute;

/** Spalte: Bild für soziale Netzwerke */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Image extends MetaColumn
{
    public function role(): string
    {
        return 'image';
    }

    public function kind(): string
    {
        return 'media';
    }

    public function defaultLabel(): string
    {
        return 'Bild für soziale Netzwerke';
    }
}

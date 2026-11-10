<?php

namespace FriendsOfRedaxo\Fluid\Routing\Attribute;

use Attribute;

/** Spalte: Zuletzt geändert */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Modified extends MetaColumn
{
    public function __construct(string $column)
    {
        parent::__construct($column, null, false);
    }

    public function role(): string
    {
        return 'modified';
    }

    public function kind(): string
    {
        return 'none';
    }

    public function defaultLabel(): string
    {
        return 'Zuletzt geändert';
    }
}

<?php

namespace FriendsOfRedaxo\Fluid\Field;

use Attribute;

/** Zahl */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Number extends Field
{
    #[\Override]
    public function type(): string
    {
        return 'number';
    }
}
